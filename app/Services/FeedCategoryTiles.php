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
     *
     * @return Collection<int, array{slug: string, label: string, subtitle: string, img: string, count: int, url: string}>
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
     * Категории, в которых реально присутствуют товары указанного бренда,
     * со ссылками сразу на категорию с фильтром ?brands[]=Brand.
     *
     * @return Collection<int, array{slug: string, label: string, img: string, url: string}>
     */
    public function getForBrand(string $brand): Collection
    {
        $normalized = mb_strtolower(trim($brand));
        if ($normalized === '') {
            return collect();
        }

        $cacheKey = 'brand.category_tiles.v1.' . md5($normalized);

        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($brand) {
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
                $hasProducts = $this->productsQuery($tile)
                    ->where('brand', $exactBrand)
                    ->exists();

                if (!$hasProducts) {
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
            'url' => route('feed-category.show', [
                'slug' => $tile['slug'],
                'brands' => [$tile['brand']],
            ]),
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

    /** Базовый запрос товаров категории (по всем активным фидам, исключая мусорные разделы). */
    public function productsQuery(array $tile): Builder
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

        $this->applyFilter($query, (array) ($tile['where'] ?? []));

        return $query;
    }

    /**
     * Подкатегории для верхней карусели страницы категории с реальным фото товара и счётчиком.
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
     * Фасетные фильтры для страницы категории (считаем по уникальным моделям, как в выдаче).
     */
    public function buildFacets(array $tile): array
    {
        $cacheKey = 'category.facets.v4.' . ($tile['slug'] ?? md5(json_encode($tile)));

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($tile) {
            return $this->computeFacetsFromQuery($this->productsQuery($tile));
        });
    }

    /**
     * Фасетные фильтры для поискового запроса / бренда по всей базе активных товаров.
     */
    public function buildFacetsForSearch(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }

        $cacheKey = 'search.facets.v1.' . md5(mb_strtolower($q));

        return Cache::remember($cacheKey, now()->addHours(2), function () use ($q) {
            $base = FeedProduct::query()
                ->where('is_active', true)
                ->where('in_stock', true);

            $excludedTops = array_filter((array) config('home_categories.excluded_tops', ['Home']));
            if ($excludedTops !== []) {
                $base->where(function (Builder $sub) use ($excludedTops) {
                    $sub->whereNull('category_top')->orWhereNotIn('category_top', $excludedTops);
                });
            }

            // Если запрос совпадает с брендом — фильтруем точно по бренду, иначе по названию/бренду
            $exactBrand = (clone $base)->where('brand', $q)->value('brand');
            if ($exactBrand !== null) {
                $base->where('brand', $exactBrand);
            } else {
                $like = '%' . $q . '%';
                $base->where(function (Builder $sub) use ($like) {
                    $sub->where('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('category_path', 'like', $like);
                });
            }

            return $this->computeFacetsFromQuery($base);
        });
    }

    private function computeFacetsFromQuery(Builder $base): array
    {
        $distinctExpr = "COUNT(DISTINCT COALESCE(NULLIF(model_code, ''), sku))";

        $brands = (clone $base)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->selectRaw("brand, {$distinctExpr} as cnt")
            ->groupBy('brand')
            ->orderByDesc('cnt')
            ->limit(25)
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->brand, 'count' => (int) $r->cnt])
            ->all();

        $genders = (clone $base)
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->selectRaw("gender, {$distinctExpr} as cnt")
            ->groupBy('gender')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->gender, 'count' => (int) $r->cnt])
            ->all();

        $colors = (clone $base)
            ->whereNotNull('colour')
            ->where('colour', '!=', '')
            ->selectRaw("TRIM(SUBSTRING_INDEX(colour, '/', 1)) as main_color, {$distinctExpr} as cnt")
            ->groupBy('main_color')
            ->orderByDesc('cnt')
            ->limit(15)
            ->pluck('cnt', 'main_color')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();

        $sample = (clone $base)
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
     * Прогревает кэш плиток, подкатегорий и фасетов для всех категорий (вызывается из CLI после импорта).
     */
    public function warmAll(): void
    {
        $this->get();
        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $this->buildSubcategories($tile);
            $this->buildFacets($tile);
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