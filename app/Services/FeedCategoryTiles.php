<?php

namespace App\Services;

use App\Models\FeedProduct;
use App\Services\Feeds\ScriptTransliterator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Meilisearch\Client;
use WeakMap;

class FeedCategoryTiles
{
    private const CACHE_HOURS = 12;

    /** @var WeakMap<Builder, array<string, mixed>> */
    private WeakMap $meiliContexts;

    public function __construct()
    {
        $this->meiliContexts = new WeakMap();
    }

    /**
     * Текущая версия кэша каталога (переключается атомарно в конце warmAll без холодного окна).
     */
    public function catalogVersion(): int
    {
        return (int) Cache::get('catalog:version', 1);
    }

    /**
     * Все активные категории с иконками, количеством товаров и списком топ-брендов в подзаголовке.
     */
    public function get(): Collection
    {
        $v = $this->catalogVersion();
        $raw = Cache::remember(
            "home.category_tiles.v{$v}.all",
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
     * Категории для бренда/запроса ОДНИМ запросом к Meilisearch через фасет tile_slugs (вместо 25 exists()).
     */
    public function getForBrand(string $brand, array $genders = []): Collection
    {
        $normalized = mb_strtolower(trim($brand));
        if ($normalized === '') {
            return collect();
        }

        sort($genders);
        $v = $this->catalogVersion();
        $cacheKey = "brand.category_tiles.v{$v}." . md5($normalized . ':' . implode(',', $genders));

        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($brand, $genders) {
            $matchedBrands = $this->resolveMatchingBrands($brand);
            if ($matchedBrands === []) {
                return [];
            }

            if ($this->isMeiliReady()) {
                try {
                    $filters = [
                        'is_active = true',
                        'in_stock = true',
                        $this->meiliIn('brand', $matchedBrands),
                    ];
                    if ($genders !== []) {
                        $filters[] = $this->meiliIn('gender', $genders);
                    }

                    $res = $this->meiliIndex()->search('', [
                        'filter' => implode(' AND ', $filters),
                        'facets' => ['tile_slugs'],
                        'limit' => 0,
                    ]);

                    $dist = $this->extractFacetDistribution($res);
                    $slugCounts = $dist['tile_slugs'] ?? [];

                    if ($slugCounts !== []) {
                        $tiles = [];
                        foreach ((array) config('home_categories.tiles', []) as $tile) {
                            $slug = (string) ($tile['slug'] ?? '');
                            if ($slug === '' || empty($slugCounts[$slug])) {
                                continue;
                            }

                            $tiles[] = [
                                'slug' => $slug,
                                'label' => (string) $tile['label'],
                                'img' => (string) ($tile['img'] ?? ($slug . '.png')),
                                'brands' => $matchedBrands,
                            ];
                        }

                        return $tiles;
                    }
                } catch (\Throwable) {
                    // Fallback below if index is still building
                }
            }

            return [];
        });

        return collect($raw)->map(fn (array $tile) => [
            'slug' => $tile['slug'],
            'label' => __($tile['label']),
            'img' => $tile['img'],
            'url' => route('feed-category.show', array_filter([
                'slug' => $tile['slug'],
                'brands' => $tile['brands'] ?? [],
                'genders' => $genders !== [] ? $genders : null,
            ])),
        ]);
    }

    /**
     * Находит точный бренд или семейство суббрендов.
     *
     * @return array<int, string>
     */
    public function resolveMatchingBrands(string $query): array
    {
        $q = trim($query);
        if ($q === '' || mb_strlen($q) < 2) {
            return [];
        }

        $v = $this->catalogVersion();
        $allBrands = Cache::remember("catalog.distinct_brands.v{$v}", now()->addHours(self::CACHE_HOURS), function () {
            if ($this->isMeiliReady()) {
                try {
                    $res = $this->meiliIndex()->search('', [
                        'filter' => 'is_active = true AND in_stock = true',
                        'facets' => ['brand'],
                        'limit' => 0,
                    ]);
                    $dist = $this->extractFacetDistribution($res);
                    if (!empty($dist['brand'])) {
                        return array_keys($dist['brand']);
                    }
                } catch (\Throwable) {
                }
            }

            return FeedProduct::query()
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->distinct()
                ->pluck('brand')
                ->all();
        });

        $qLower = mb_strtolower($q);
        $exact = [];
        $family = [];

        foreach ($allBrands as $b) {
            $bStr = (string) $b;
            $bLower = mb_strtolower($bStr);

            if ($bLower === $qLower) {
                $exact[] = $bStr;
            } elseif (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($qLower, '/') . '(?![\p{L}\p{N}])/u', $bLower)) {
                $family[] = $bStr;
            }
        }

        return array_values(array_unique(array_merge($exact, $family)));
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

    /** Базовый запрос активных товаров. */
    public function baseActiveQuery(): Builder
    {
        $query = FeedProduct::query()
            ->where('is_active', true)
            ->where('in_stock', true);

        $feeds = array_filter((array) config('home_categories.feeds', []));
        if ($feeds !== []) {
            $query->whereIn('feed', $feeds);
        }

        return $query;
    }

    /** Базовый запрос товаров конкретной категории (с привязкой контекста Meilisearch). */
    public function productsQuery(array $tile): Builder
    {
        $query = $this->baseActiveQuery();
        $this->applyFilter($query, (array) ($tile['where'] ?? []));

        $this->meiliContexts[$query] = [
            'mode' => 'tile',
            'tile' => $tile,
            'tile_slug' => (string) ($tile['slug'] ?? ''),
            'sub_key' => null,
            'extra_where' => [],
            'search_query' => '',
            'matched_brands' => [],
            'user_filters' => [],
            'except' => null,
        ];

        return $query;
    }

    /** Базовый запрос для поиска / страницы бренда (с привязкой контекста Meilisearch). */
    public function searchBaseQuery(string $searchQuery): Builder
    {
        $base = $this->baseActiveQuery();
        $q = trim($searchQuery);

        $matchedBrands = $q !== '' ? $this->resolveMatchingBrands($q) : [];
        if ($matchedBrands !== []) {
            $base->whereIn('brand', $matchedBrands);
        }

        $this->meiliContexts[$base] = [
            'mode' => 'search',
            'tile' => null,
            'tile_slug' => null,
            'sub_key' => null,
            'extra_where' => [],
            'search_query' => $q,
            'matched_brands' => $matchedBrands,
            'user_filters' => [],
            'except' => null,
        ];

        return $base;
    }

    /**
     * Применяет пользовательские фильтры из сайдбара и сохраняет их для Meilisearch.
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

        if (isset($this->meiliContexts[$query])) {
            $ctx = $this->meiliContexts[$query];
            $ctx['user_filters'] = $filters;
            $ctx['except'] = $except;
            $this->meiliContexts[$q] = $ctx;
        }

        return $q;
    }

    /**
     * Подкатегории для верхней карусели страницы категории (через Meilisearch за 2 мс).
     */
    public function buildSubcategories(array $tile, ?int $targetVersion = null): array
    {
        $subs = (array) ($tile['subcategories'] ?? []);
        if ($subs === []) {
            return [];
        }

        $v = $targetVersion ?? $this->catalogVersion();
        $slug = (string) ($tile['slug'] ?? md5(json_encode($tile)));
        $cacheKey = "category.subs.v{$v}.{$slug}";

        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($tile, $subs, $slug) {
            $result = [];

            if ($this->isMeiliReady()) {
                try {
                    foreach ($subs as $idx => $sub) {
                        $subKey = "{$slug}:{$idx}";
                        $res = $this->meiliIndex()->search('', [
                            'filter' => 'is_active = true AND in_stock = true AND sub_keys = ' . $this->meiliQuote($subKey),
                            'sort' => ['priority_score:desc'],
                            'limit' => 5,
                            'attributesToRetrieve' => ['id', 'image_url'],
                        ]);

                        $hits = is_object($res) ? $res->getHits() : ($res['hits'] ?? []);
                        if ($hits === []) {
                            continue;
                        }

                        $sampleImage = null;
                        foreach ($hits as $h) {
                            if (!empty($h['image_url'])) {
                                $sampleImage = (string) $h['image_url'];
                                break;
                            }
                        }

                        if ($sampleImage === null) {
                            $firstId = (int) ($hits[0]['id'] ?? 0);
                            if ($firstId > 0) {
                                $sampleImage = FeedProduct::query()->where('id', $firstId)->value('image_url');
                            }
                        }

                        if (!$sampleImage) {
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
                } catch (\Throwable) {
                }
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
     * Считает фасеты категории через Meilisearch за миллисекунды.
     */
    public function buildFacets(array $tile, array $activeFilters = [], ?int $targetVersion = null): array
    {
        $v = $targetVersion ?? $this->catalogVersion();
        $slug = (string) ($tile['slug'] ?? '');
        $cacheKey = "category.facets.v{$v}.{$slug}." . md5(json_encode($activeFilters));

        return Cache::remember($cacheKey, now()->addHours(4), function () use ($tile, $slug, $activeFilters) {
            $subKey = null;
            if (isset($activeFilters['sub']) && $activeFilters['sub'] !== null && isset($tile['subcategories'][$activeFilters['sub']])) {
                $subKey = "{$slug}:" . (int) $activeFilters['sub'];
            }

            return $this->computeMeiliFacets(
                searchQuery: '',
                baseClauses: array_filter([
                    'is_active = true',
                    'in_stock = true',
                    'tile_slugs = ' . $this->meiliQuote($slug),
                    $subKey !== null ? ('sub_keys = ' . $this->meiliQuote($subKey)) : null,
                ]),
                filters: $activeFilters
            );
        });
    }

    /**
     * Фасетные фильтры для страницы поиска / бренда через Meilisearch за миллисекунды.
     */
    public function buildFacetsForSearch(string $query, array $activeFilters = []): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }

        $v = $this->catalogVersion();
        $cacheKey = "search.facets.v{$v}." . md5(mb_strtolower($q) . ':' . json_encode($activeFilters));

        return Cache::remember($cacheKey, now()->addHours(4), function () use ($q, $activeFilters) {
            $matchedBrands = $this->resolveMatchingBrands($q);

            if ($matchedBrands !== []) {
                return $this->computeMeiliFacets(
                    searchQuery: '',
                    baseClauses: [
                        'is_active = true',
                        'in_stock = true',
                        $this->meiliIn('brand', $matchedBrands),
                    ],
                    filters: $activeFilters
                );
            }

            $prepared = class_exists(ScriptTransliterator::class)
                ? ScriptTransliterator::prepareForMeilisearch($q)
                : $q;

            return $this->computeMeiliFacets(
                searchQuery: $prepared,
                baseClauses: ['is_active = true', 'in_stock = true'],
                filters: $activeFilters
            );
        });
    }

    /**
     * Вычисляет фасеты через Meilisearch (1 запрос, если нет активных фильтров, или контекстно по каждому фильтру).
     */
    private function computeMeiliFacets(string $searchQuery, array $baseClauses, array $filters): array
    {
        if (!$this->isMeiliReady()) {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }

        try {
            $hasFacetFilters = !empty($filters['brands'])
                || !empty($filters['genders'])
                || !empty($filters['color'])
                || !empty($filters['size']);

            if (!$hasFacetFilters) {
                $clauses = array_merge($baseClauses, $this->buildUserFilterClauses($filters, null));
                $res = $this->meiliIndex()->search($searchQuery, [
                    'filter' => implode(' AND ', $clauses),
                    'facets' => ['brand', 'gender', 'colour_main', 'sizes_list'],
                    'matchingStrategy' => 'all',
                    'limit' => 0,
                ]);

                $dist = $this->extractFacetDistribution($res);

                return $this->formatFacetDistribution(
                    $dist['brand'] ?? [],
                    $dist['gender'] ?? [],
                    $dist['colour_main'] ?? [],
                    $dist['sizes_list'] ?? []
                );
            }

            // Если фильтры выбраны — считаем каждую группу с исключением самой себя (по 2 мс на запрос)
            $brandDist = $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'brands', 'brand');
            $genderDist = $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'genders', 'gender');
            $colorDist = $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'color', 'colour_main');
            $sizeDist = $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'size', 'sizes_list');

            return $this->formatFacetDistribution($brandDist, $genderDist, $colorDist, $sizeDist);
        } catch (\Throwable) {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }
    }

    private function fetchSingleFacet(string $searchQuery, array $baseClauses, array $filters, string $exceptKey, string $meiliFacet): array
    {
        $clauses = array_merge($baseClauses, $this->buildUserFilterClauses($filters, $exceptKey));
        $res = $this->meiliIndex()->search($searchQuery, [
            'filter' => implode(' AND ', $clauses),
            'facets' => [$meiliFacet],
            'matchingStrategy' => 'all',
            'limit' => 0,
        ]);

        $dist = $this->extractFacetDistribution($res);

        return $dist[$meiliFacet] ?? [];
    }

    private function formatFacetDistribution(array $brandDist, array $genderDist, array $colorDist, array $sizeDist): array
    {
        arsort($brandDist);
        $brands = [];
        foreach (array_slice($brandDist, 0, 30, true) as $name => $cnt) {
            if (trim((string) $name) !== '' && $cnt > 0) {
                $brands[] = ['name' => (string) $name, 'count' => (int) $cnt];
            }
        }

        arsort($genderDist);
        $genders = [];
        foreach ($genderDist as $code => $cnt) {
            if (trim((string) $code) !== '' && $cnt > 0) {
                $genders[] = ['code' => (string) $code, 'count' => (int) $cnt];
            }
        }

        arsort($colorDist);
        $colors = [];
        foreach (array_slice($colorDist, 0, 15, true) as $col => $cnt) {
            if (trim((string) $col) !== '' && $cnt > 0) {
                $colors[(string) $col] = (int) $cnt;
            }
        }

        arsort($sizeDist);
        $sizes = [];
        foreach ($sizeDist as $sz => $cnt) {
            $szStr = trim((string) $sz);
            if ($szStr !== '' && mb_strlen($szStr) <= 12 && $cnt > 0) {
                $sizes[] = $szStr;
                if (count($sizes) >= 18) {
                    break;
                }
            }
        }

        return [
            'brands' => $brands,
            'genders' => $genders,
            'sizes' => $sizes,
            'colors' => $colors,
        ];
    }

    /**
     * Возвращает карточки страницы и $total через 1 запрос к Meilisearch + выборку 24 строк по ID из MySQL.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public function paginateGroupedCards(Builder $filteredBase, string $sort, int $page, int $perPage, string $cachePrefix): array
    {
        $v = $this->catalogVersion();
        $pageCacheKey = "catalog_page:v{$v}:{$cachePrefix}:{$sort}:p{$page}";

        return Cache::remember($pageCacheKey, now()->addHours(4), function () use ($filteredBase, $sort, $page, $perPage) {
            if ($this->isMeiliReady() && isset($this->meiliContexts[$filteredBase])) {
                $meiliResult = $this->paginateViaMeilisearch($this->meiliContexts[$filteredBase], $sort, $page, $perPage);
                if ($meiliResult !== null) {
                    return $meiliResult;
                }
            }

            return $this->paginateFallbackSql($filteredBase, $sort, $page, $perPage);
        });
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array{0: array<int, array<string, mixed>>, 1: int}|null
     */
    private function paginateViaMeilisearch(array $ctx, string $sort, int $page, int $perPage): ?array
    {
        try {
            $clauses = ['is_active = true', 'in_stock = true'];
            $searchQuery = '';

            if (($ctx['mode'] ?? '') === 'tile') {
                $slug = (string) ($ctx['tile_slug'] ?? '');
                if ($slug !== '') {
                    $clauses[] = 'tile_slugs = ' . $this->meiliQuote($slug);
                }
                if (!empty($ctx['sub_key'])) {
                    $clauses[] = 'sub_keys = ' . $this->meiliQuote((string) $ctx['sub_key']);
                }
            } elseif (($ctx['mode'] ?? '') === 'search') {
                $matchedBrands = (array) ($ctx['matched_brands'] ?? []);
                if ($matchedBrands !== []) {
                    $clauses[] = $this->meiliIn('brand', $matchedBrands);
                } else {
                    $rawQ = (string) ($ctx['search_query'] ?? '');
                    $searchQuery = class_exists(ScriptTransliterator::class)
                        ? ScriptTransliterator::prepareForMeilisearch($rawQ)
                        : $rawQ;
                }
            }

            $userFilters = (array) ($ctx['user_filters'] ?? []);
            $clauses = array_merge($clauses, $this->buildUserFilterClauses($userFilters, $ctx['except'] ?? null));

            $meiliSort = match ($sort) {
                'price_asc' => ['price:asc', 'priority_score:desc'],
                'price_desc' => ['price:desc', 'priority_score:desc'],
                'discount_desc' => ['discount_percent:desc', 'price:asc'],
                default => ['priority_score:desc', 'discount_percent:desc', 'id:asc'],
            };

            $res = $this->meiliIndex()->search($searchQuery, [
                'filter' => implode(' AND ', $clauses),
                'sort' => $meiliSort,
                'matchingStrategy' => 'all',
                'offset' => ($page - 1) * $perPage,
                'limit' => $perPage,
                'attributesToRetrieve' => ['id'],
            ]);

            $hits = is_object($res) ? $res->getHits() : ($res['hits'] ?? []);
            $total = is_object($res)
                ? (int) ($res->getEstimatedTotalHits() ?? count($hits))
                : (int) ($res['estimatedTotalHits'] ?? $res['totalHits'] ?? $res['nbHits'] ?? count($hits));

            if ($hits === []) {
                return [[], 0];
            }

            $primaryIds = array_values(array_filter(array_map(fn ($h) => (int) ($h['id'] ?? 0), $hits)));
            $productsMap = FeedProduct::query()->whereIn('id', $primaryIds)->get()->keyBy('id');

            $cards = [];
            foreach ($primaryIds as $id) {
                /** @var FeedProduct|null $p */
                $p = $productsMap->get($id);
                if (!$p) {
                    continue;
                }

                $store = (string) config("feeds.feeds.{$p->feed}.store_name", $p->brand ?: $p->feed);
                $formatted = '€' . number_format($p->price, 2, '.', ' ');

                $cards[] = $p->toCard() + [
                    'price' => $p->price,
                    'price_amount' => $p->price,
                    'original_title' => $p->name,
                    'merchant' => $store,
                    'price_from' => $p->price,
                    'price_formatted' => $formatted,
                    'price_note' => $formatted,
                    'formatted_price' => $formatted,
                    'offers_count' => 1,
                    'colorways_count' => 1,
                ];
            }

            return [$cards, $total];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Быстрый резервный SQL-фоллбэк без тяжёлых GROUP BY и MD5, если Meilisearch недоступен.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function paginateFallbackSql(Builder $filteredBase, string $sort, int $page, int $perPage): array
    {
        $q = clone $filteredBase;

        if ($sort === 'price_asc') {
            $q->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $q->orderByDesc('price');
        } elseif ($sort === 'discount_desc') {
            $q->orderByDesc('discount_percent')->orderBy('price');
        } else {
            $q->orderByDesc('priority_score')->orderByDesc('id');
        }

        $items = $q->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $cards = [];

        foreach ($items as $p) {
            $store = (string) config("feeds.feeds.{$p->feed}.store_name", $p->brand ?: $p->feed);
            $formatted = '€' . number_format($p->price, 2, '.', ' ');
            $cards[] = $p->toCard() + [
                'price' => $p->price,
                'price_amount' => $p->price,
                'original_title' => $p->name,
                'merchant' => $store,
                'price_from' => $p->price,
                'price_formatted' => $formatted,
                'price_note' => $formatted,
                'formatted_price' => $formatted,
                'offers_count' => 1,
                'colorways_count' => 1,
            ];
        }

        return [$cards, count($cards) === $perPage ? ($page * $perPage + $perPage) : (($page - 1) * $perPage + count($cards))];
    }

    /**
     * Мгновенный прогрев новой версии кэша в фоне с атомарным переключением версии (без холодного окна).
     */
    public function warmAll(): void
    {
        $nextVersion = $this->catalogVersion() + 1;

        Cache::put(
            "home.category_tiles.v{$nextVersion}.all",
            $this->build(),
            now()->addHours(self::CACHE_HOURS)
        );

        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $this->buildSubcategories($tile, $nextVersion);
            $this->buildFacets($tile, [], $nextVersion);
        }

        Cache::forever('catalog:version', $nextVersion);
    }

    public function applyPublicFilter(Builder $query, array $where): void
    {
        $this->applyFilter($query, $where);

        if (isset($this->meiliContexts[$query])) {
            $ctx = $this->meiliContexts[$query];
            $tile = $ctx['tile'] ?? null;

            if (is_array($tile) && !empty($tile['subcategories'])) {
                foreach ($tile['subcategories'] as $idx => $sub) {
                    if (($sub['where'] ?? []) === $where) {
                        $ctx['sub_key'] = ($tile['slug'] ?? '') . ':' . $idx;
                        break;
                    }
                }
            }

            $ctx['extra_where'] = $where;
            $this->meiliContexts[$query] = $ctx;
        }
    }

    /**
     * Строит все 25 плиток главной страницы через Meilisearch за ~30 мс (вместо 50 тяжелых SQL-запросов).
     */
    private function build(): array
    {
        $tilesConfig = (array) config('home_categories.tiles', []);

        if ($this->isMeiliReady()) {
            try {
                $res = $this->meiliIndex()->search('', [
                    'filter' => 'is_active = true AND in_stock = true',
                    'facets' => ['tile_slugs'],
                    'limit' => 0,
                ]);

                $dist = $this->extractFacetDistribution($res);
                $slugCounts = $dist['tile_slugs'] ?? [];

                if ($slugCounts !== []) {
                    $tiles = [];
                    foreach ($tilesConfig as $tile) {
                        $slug = (string) ($tile['slug'] ?? '');
                        $count = (int) ($slugCounts[$slug] ?? 0);
                        if ($slug === '' || $count === 0) {
                            continue;
                        }

                        // Получаем топ-3 бренда для подзаголовка плитки из фасета Meilisearch (за 1 мс)
                        $bRes = $this->meiliIndex()->search('', [
                            'filter' => 'is_active = true AND in_stock = true AND tile_slugs = ' . $this->meiliQuote($slug),
                            'facets' => ['brand'],
                            'limit' => 0,
                        ]);
                        $bDist = $this->extractFacetDistribution($bRes)['brand'] ?? [];
                        arsort($bDist);
                        $topBrands = array_slice(array_keys(array_filter($bDist, fn ($c, $k) => trim((string) $k) !== '' && $c > 0, ARRAY_FILTER_USE_BOTH)), 0, 3);

                        $tiles[] = [
                            'slug' => $slug,
                            'label' => (string) $tile['label'],
                            'subtitle' => implode(', ', $topBrands),
                            'img' => (string) ($tile['img'] ?? ($slug . '.png')),
                            'count' => $count,
                        ];
                    }

                    return $tiles;
                }
            } catch (\Throwable) {
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function buildUserFilterClauses(array $filters, ?string $except): array
    {
        $clauses = [];

        if ($except !== 'price') {
            if (!empty($filters['min_price']) && (float) $filters['min_price'] > 0) {
                $clauses[] = 'price >= ' . (float) $filters['min_price'];
            }
            if (!empty($filters['max_price']) && (float) $filters['max_price'] > 0) {
                $clauses[] = 'price <= ' . (float) $filters['max_price'];
            }
        }

        if ($except !== 'discount' && !empty($filters['discount'])) {
            $clauses[] = 'discount_percent > 0';
        }

        if ($except !== 'brands' && !empty($filters['brands'])) {
            $clauses[] = $this->meiliIn('brand', (array) $filters['brands']);
        }

        if ($except !== 'genders' && !empty($filters['genders'])) {
            $clauses[] = $this->meiliIn('gender', (array) $filters['genders']);
        }

        if ($except !== 'size' && !empty($filters['size'])) {
            $clauses[] = 'sizes_list = ' . $this->meiliQuote((string) $filters['size']);
        }

        if ($except !== 'color' && !empty($filters['color'])) {
            $clauses[] = 'colour_main = ' . $this->meiliQuote((string) $filters['color']);
        }

        return $clauses;
    }

    private function isMeiliReady(): bool
    {
        return config('scout.driver') === 'meilisearch'
            && !empty(config('scout.meilisearch.host') ?: config('scout.meilisearch.url'));
    }

    private function meiliIndex(): \Meilisearch\Endpoints\Indexes
    {
        $client = new Client(
            (string) (config('scout.meilisearch.host') ?: config('scout.meilisearch.url')),
            config('scout.meilisearch.key') ?: null
        );

        return $client->index((new FeedProduct())->searchableAs());
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function extractFacetDistribution(mixed $res): array
    {
        if (is_object($res) && method_exists($res, 'getFacetDistribution')) {
            return (array) $res->getFacetDistribution();
        }

        if (is_array($res) && isset($res['facetDistribution'])) {
            return (array) $res['facetDistribution'];
        }

        return [];
    }

    private function meiliQuote(string $val): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $val) . '"';
    }

    private function meiliIn(string $field, array $values): string
    {
        $quoted = array_map(fn ($v) => $this->meiliQuote((string) $v), array_values($values));

        return $field . ' IN [' . implode(', ', $quoted) . ']';
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