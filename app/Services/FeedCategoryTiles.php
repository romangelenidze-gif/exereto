<?php

namespace App\Services;

use App\Models\FeedProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class FeedCategoryTiles
{
    private const CACHE_HOURS = 12;

    /**
     * Все активные категории с иконками, количеством товаров и списком топ-брендов в подзаголовке.
     */
    public function get(): Collection
    {
        $raw = Cache::remember(
            'home.category_tiles.v5.all',
            now()->addHours(self::CACHE_HOURS),
            fn () => $this->build()
        );

        return collect($raw)->map(fn (array $tile) => [
            'slug' => $tile['slug'],
            'label' => __($tile['label']),
            'subtitle' => $tile['subtitle'] ?? '',
            'img' => $tile['img'],
            'count' => $tile['count'],
            'url' => route('feed-category.show', ['slug' => $tile['slug']]),
        ]);
    }

    /**
     * Категории, в которых реально присутствуют товары указанного бренда/запроса (с учётом выбранного пола).
     */
    public function getForBrand(string $brand, array $genders = []): Collection
    {
        $normalized = mb_strtolower(trim($brand));
        if ($normalized === '') {
            return collect();
        }

        sort($genders);
        $cacheKey = 'brand.category_tiles.v2.' . md5($normalized . ':' . implode(',', $genders));

        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($brand, $genders) {
            $exactBrand = FeedProduct::query()
                ->where('is_active', true)
                ->where('in_stock', true)
                ->where('brand', $brand)
                ->value('brand');

            if ($exactBrand === null) {
                return [];
            }

            $tiles = [];
            foreach ((array) config('home_categories.tiles', []) as $tile) {
                $q = $this->productsQuery($tile)->where('brand', $exactBrand);
                if ($genders !== []) {
                    $q->whereIn('gender', $genders);
                }

                if (!$q->exists()) {
                    continue;
                }

                $tiles[] = [
                    'slug' => (string) $tile['slug'],
                    'label' => (string) $tile['label'],
                    'img' => (string) ($tile['img'] ?? ($tile['slug'] . '.png')),
                    'brand' => $exactBrand,
                ];
            }

            return $tiles;
        });

        return collect($raw)->map(fn (array $tile) => [
            'slug' => $tile['slug'],
            'label' => __($tile['label']),
            'img' => $tile['img'],
            'url' => route('feed-category.show', array_filter([
                'slug' => $tile['slug'],
                'brands' => [$tile['brand']],
                'genders' => $genders !== [] ? $genders : null,
            ])),
        ]);
    }

    /** Описание категории по slug или null. */
    public function tile(string $slug): ?array
    {
        foreach ((array) config('home_categories.tiles', []) as $tile) {
            if (($tile['slug'] ?? null) === $slug) {
                return $tile;
            }
        }

        return null;
    }

    /** Базовый запрос активных товаров (общий фундамент для категорий и поиска). */
    public function baseActiveQuery(): Builder
    {
        $query = FeedProduct::query()
            ->where('is_active', true)
            ->where('in_stock', true);

        $feeds = array_filter((array) config('home_categories.feeds', []));
        if ($feeds !== []) {
            $query->whereIn('feed', $feeds);
        }

        $excludedTops = array_filter((array) config('home_categories.excluded_tops', ['Home']));
        if ($excludedTops !== []) {
            $query->where(function (Builder $q) use ($excludedTops) {
                $q->whereNull('category_top')
                  ->orWhereNotIn('category_top', $excludedTops);
            });
        }

        return $query;
    }

    /** Базовый запрос товаров конкретной категории. */
    public function productsQuery(array $tile): Builder
    {
        $query = $this->baseActiveQuery();
        $this->applyFilter($query, (array) ($tile['where'] ?? []));

        return $query;
    }

    /** Базовый запрос для поиска / страницы бренда. */
    public function searchBaseQuery(string $searchQuery): Builder
    {
        $base = $this->baseActiveQuery();
        $q = trim($searchQuery);
        if ($q === '') {
            return $base;
        }

        // Проверяем, является ли запрос точным названием бренда
        $exactBrand = (clone $base)->where('brand', $q)->value('brand');
        if ($exactBrand !== null) {
            return $base->where('brand', $exactBrand);
        }

        $words = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q];
        foreach ($words as $word) {
            if (mb_strlen($word) < 2) {
                continue;
            }
            $like = '%' . $word . '%';
            $base->where(function (Builder $sub) use ($like) {
                $sub->where('name', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('category_path', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('model_code', 'like', $like);
            });
        }

        return $base;
    }

    /**
     * Применяет пользовательские фильтры из сайдбара к запросу.
     * Параметр $except позволяет не применять фильтр к самому себе при подсчёте его фасета.
     */
    public function applyUserFilters(Builder $query, array $filters, ?string $except = null): Builder
    {
        $q = clone $query;

        if ($except !== 'price') {
            if (!empty($filters['min_price']) && (float) $filters['min_price'] > 0) {
                $q->where('price', '>=', (float) $filters['min_price']);
            }
            if (!empty($filters['max_price']) && (float) $filters['max_price'] > 0) {
                $q->where('price', '<=', (float) $filters['max_price']);
            }
        }

        if ($except !== 'discount' && !empty($filters['discount'])) {
            $q->where('discount_percent', '>', 0);
        }

        if ($except !== 'brands' && !empty($filters['brands'])) {
            $q->whereIn('brand', (array) $filters['brands']);
        }

        if ($except !== 'genders' && !empty($filters['genders'])) {
            $q->whereIn('gender', (array) $filters['genders']);
        }

        if ($except !== 'size' && !empty($filters['size'])) {
            $q->where('sizes', 'like', '%"' . addslashes((string) $filters['size']) . '"%');
        }

        if ($except !== 'color' && !empty($filters['color'])) {
            $q->whereRaw("TRIM(SUBSTRING_INDEX(colour, '/', 1)) = ?", [(string) $filters['color']]);
        }

        return $q;
    }

    /**
     * Подкатегории для верхней карусели страницы категории.
     */
    public function buildSubcategories(array $tile): array
    {
        $subs = (array) ($tile['subcategories'] ?? []);
        if ($subs === []) {
            return [];
        }

        $cacheKey = 'category.subs.v2.' . ($tile['slug'] ?? md5(json_encode($tile)));

        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($tile, $subs) {
            $result = [];
            foreach ($subs as $idx => $sub) {
                $q = $this->productsQuery($tile);
                $this->applyFilter($q, (array) ($sub['where'] ?? []));

                $sampleImage = (clone $q)
                    ->whereNotNull('image_url')
                    ->where('image_url', '!=', '')
                    ->value('image_url');

                if ($sampleImage === null) {
                    continue;
                }

                $result[] = [
                    'index' => $idx,
                    'label' => (string) ($sub['label'] ?? ''),
                    'count' => 1,
                    'image' => $sampleImage,
                ];
            }

            return $result;
        });

        return array_map(fn (array $item) => [
            'index' => $item['index'],
            'label' => __($item['label']),
            'count' => $item['count'],
            'image' => $item['image'],
        ], $raw);
    }

    /**
     * Считает фасеты (Бренды, Пол, Размеры, Цвета) с учётом текущих активных фильтров!
     * Благодаря этому цифры возле брендов, полов и цветов всегда на 100% совпадают с заголовком «N товаров».
     */
    public function buildFacets(array $tile, array $activeFilters = []): array
    {
        $base = $this->productsQuery($tile);
        if (isset($activeFilters['sub']) && $activeFilters['sub'] !== null && isset($tile['subcategories'][$activeFilters['sub']])) {
            $this->applyFilter($base, (array) ($tile['subcategories'][$activeFilters['sub']]['where'] ?? []));
        }

        $cacheKey = 'category.facets.v5.' . ($tile['slug'] ?? '') . '.' . md5(json_encode($activeFilters));

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($base, $activeFilters) {
            return $this->computeContextualFacets($base, $activeFilters);
        });
    }

    /**
     * Фасетные фильтры для страницы поиска / бренда с учётом выбранных фильтров (пол, бренд, цена и т.д.).
     */
    public function buildFacetsForSearch(string $query, array $activeFilters = []): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }

        $cacheKey = 'search.facets.v5.' . md5(mb_strtolower($q) . ':' . json_encode($activeFilters));

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($q, $activeFilters) {
            $base = $this->searchBaseQuery($q);
            return $this->computeContextualFacets($base, $activeFilters);
        });
    }

    private function computeContextualFacets(Builder $base, array $filters): array
    {
        // Если model_code пустой, группируем по хэшу Бренд+Название+Цвет, чтобы склеить разбитые размеры
        $groupExpr = "COALESCE(NULLIF(model_code, ''), MD5(CONCAT(COALESCE(brand, ''), '_', COALESCE(name, ''), '_', COALESCE(colour, ''))), sku)";
        $distinctExpr = "COUNT(DISTINCT {$groupExpr})";

        // Бренды считаем с учётом всех активных фильтров, кроме самих brands
        $brandsQuery = $this->applyUserFilters($base, $filters, except: 'brands');
        $brands = $brandsQuery
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->selectRaw("brand, {$distinctExpr} as cnt")
            ->groupBy('brand')
            ->orderByDesc('cnt')
            ->limit(25)
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->brand, 'count' => (int) $r->cnt])
            ->all();

        // Пол считаем с учётом всех активных фильтров, кроме самого genders
        $gendersQuery = $this->applyUserFilters($base, $filters, except: 'genders');
        $genders = $gendersQuery
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->selectRaw("gender, {$distinctExpr} as cnt")
            ->groupBy('gender')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->gender, 'count' => (int) $r->cnt])
            ->all();

        // Цвета считаем с учётом всех активных фильтров (включая выбранный Пол и Бренд!), кроме самого color
        $colorsQuery = $this->applyUserFilters($base, $filters, except: 'color');
        $colors = $colorsQuery
            ->whereNotNull('colour')
            ->where('colour', '!=', '')
            ->selectRaw("TRIM(SUBSTRING_INDEX(colour, '/', 1)) as main_color, {$distinctExpr} as cnt")
            ->groupBy('main_color')
            ->orderByDesc('cnt')
            ->limit(15)
            ->pluck('cnt', 'main_color')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();

        // Размеры собираем с учётом всех активных фильтров, кроме самого size
        $sizesQuery = $this->applyUserFilters($base, $filters, except: 'size');
        $sample = $sizesQuery
            ->toBase()
            ->select(['sizes'])
            ->limit(800)
            ->get();

        $sizeCounts = [];
        foreach ($sample as $row) {
            $sizesArr = is_string($row->sizes) ? json_decode($row->sizes, true) : (array) $row->sizes;
            if (!is_array($sizesArr)) {
                continue;
            }
            foreach ($sizesArr as $s) {
                $rawSize = is_array($s)
                    ? ($s['size'] ?? $s['name'] ?? $s['value'] ?? $s['label'] ?? null)
                    : $s;

                if (!is_scalar($rawSize)) {
                    continue;
                }

                $sizeStr = trim((string) $rawSize);
                if ($sizeStr !== '' && mb_strlen($sizeStr) <= 12) {
                    $sizeCounts[$sizeStr] = ($sizeCounts[$sizeStr] ?? 0) + 1;
                }
            }
        }
        arsort($sizeCounts);

        return [
            'brands' => $brands,
            'genders' => $genders,
            'sizes' => array_slice(array_keys($sizeCounts), 0, 18),
            'colors' => $colors,
        ];
    }

    /**
     * Выполняет группировку по уникальным моделям, считает точный $total и возвращает карточки страницы.
     * Используется и в FeedCategoryController, и в ProductSearchController!
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public function paginateGroupedCards(Builder $filteredBase, string $sort, int $page, int $perPage, string $cachePrefix): array
    {
        $pageCacheKey = "catalog_page:v5:{$cachePrefix}:{$sort}:p{$page}";

return Cache::remember($pageCacheKey, now()->addHours(2), function () use ($filteredBase, $sort, $page, $perPage, $cachePrefix) {
            $groupExpr = "COALESCE(NULLIF(model_code, ''), MD5(CONCAT(COALESCE(brand, ''), '_', COALESCE(name, ''), '_', COALESCE(colour, ''))), sku)";
            
            $grouped = (clone $filteredBase)
                ->selectRaw("
                    {$groupExpr} as group_key,
                    CAST(SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY priority_score DESC, price ASC, id ASC), ',', 1) AS UNSIGNED) as primary_id,
                    MIN(price) as min_price,
                    MAX(rating) as max_rating,
                    MAX(discount_percent) as max_discount,
                    COUNT(DISTINCT feed) as stores_count,
                    COUNT(*) as colorways_count
                ")
                ->groupBy('group_key');

            $total = Cache::remember("catalog_total:v5:{$cachePrefix}", now()->addHours(2), function () use ($grouped) {
                return \Illuminate\Support\Facades\DB::query()->fromSub($grouped, 'g')->count();
            });

            if ($sort === 'price_asc') {
                $grouped->orderBy('min_price');
            } elseif ($sort === 'price_desc') {
                $grouped->orderByDesc('min_price');
            } elseif ($sort === 'discount_desc') {
                $grouped->orderByDesc('max_discount')->orderBy('min_price');
            } else {
                $grouped->orderByRaw('COALESCE(MAX(rating), -1) DESC')
                        ->orderByDesc('max_discount')
                        ->orderBy('primary_id');
            }

            $rows = $grouped
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $primaryIds = $rows->pluck('primary_id')->all();
            $metaMap = $rows->keyBy('primary_id');

            $productsMap = $primaryIds !== []
                ? FeedProduct::query()->whereIn('id', $primaryIds)->get()->keyBy('id')
                : collect();

            $cards = [];
            foreach ($primaryIds as $id) {
                /** @var FeedProduct|null $p */
                $p = $productsMap->get($id);
                if (!$p) {
                    continue;
                }

                $meta = $metaMap->get($id);
                $bestPrice = (float) ($meta->min_price ?? $p->price);
                $storesCount = (int) ($meta->stores_count ?? 1);
                $store = (string) config("feeds.feeds.{$p->feed}.store_name", $p->brand ?: $p->feed);
                $formatted = '€' . number_format($bestPrice, 2, '.', ' ');

                $cards[] = $p->toCard() + [
                    'price' => $bestPrice,
                    'price_amount' => $bestPrice,
                    'original_title' => $p->name,
                    'merchant' => $storesCount > 1 ? ($storesCount . ' ' . __('магазина')) : $store,
                    'price_from' => $bestPrice,
                    'price_formatted' => $formatted,
                    'price_note' => $formatted,
                    'formatted_price' => $formatted,
                    'offers_count' => $storesCount,
                    'colorways_count' => (int) ($meta->colorways_count ?? 1),
                ];
            }

            return [$cards, $total];
        });
    }

    /**
     * Прогревает кэш плиток, подкатегорий и базовых фасетов для всех категорий.
     */
    public function warmAll(): void
    {
        $this->get();
        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $this->buildSubcategories($tile);
            $this->buildFacets($tile, []);
        }
    }

    public function applyPublicFilter(Builder $query, array $where): void
    {
        $this->applyFilter($query, $where);
    }

    private function build(): array
    {
        $tiles = [];

        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $base = $this->productsQuery($tile);
            $count = (clone $base)->count();
            if ($count === 0) {
                continue;
            }

            $topBrands = (clone $base)
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->selectRaw('brand, COUNT(*) as cnt')
                ->groupBy('brand')
                ->orderByDesc('cnt')
                ->limit(3)
                ->pluck('brand')
                ->all();

            $tiles[] = [
                'slug' => (string) $tile['slug'],
                'label' => (string) $tile['label'],
                'subtitle' => implode(', ', $topBrands),
                'img' => (string) ($tile['img'] ?? ($tile['slug'] . '.png')),
                'count' => $count,
            ];
        }

        return $tiles;
    }

    private function applyFilter(Builder $query, array $where): void
    {
        foreach ($where as $key => $value) {
            match ($key) {
                'category_top', 'gender' => $query->where($key, $value),
                'category_path' => $this->wherePathLike($query, (array) $value),
                'discount' => $value ? $query->where('discount_percent', '>', 0) : null,
                default => throw new InvalidArgumentException("config/home_categories.php: неизвестный ключ where «{$key}»"),
            };
        }
    }

    private function wherePathLike(Builder $query, array $needles): void
    {
        $query->where(function (Builder $q) use ($needles) {
            foreach ($needles as $needle) {
                $q->orWhere('category_path', 'like', '%' . $needle . '%');
            }
        });
    }
}