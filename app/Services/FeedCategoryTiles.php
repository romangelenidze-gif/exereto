<?php

namespace App\Services;

use App\Models\FeedProduct;
use App\Services\Feeds\FeedEntityLocalizer;
use App\Services\Feeds\ScriptTransliterator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Meilisearch\Client;
use WeakMap;

class FeedCategoryTiles
{
    private const CACHE_HOURS = 30;

    /** Картинки департаментов L1 / хабов (если лежат в public/images/categories/) */
    private const CATEGORY_IMAGES = [
        'clothing' => 'womens-jackets.png',
        'shoes' => 'womens-sneakers.png',
        'accessories' => 'bags-backpacks.png',
        'beauty' => 'perfume.png',
        'toys' => 'toys-lego.png',
        'baby-mom' => 'childrens-fashion.png',
        'tech' => 'gadgets-gaming.png',
        'kids' => 'childrens-fashion.png',
        'childrens-fashion' => 'childrens-fashion.png',
        'sales-offers' => 'sales-offers.png',
    ];

    /** Подборка ключевых L2 для виртуального хаба «Детям» */
    private const KIDS_SUB_SLUGS = [
        'sneakers', 'boots', 'sandals', 'jackets', 'hoodies', 't-shirts',
        'dresses', 'pants', 'tracksuits', 'toys', 'baby-mom', 'backpacks',
    ];

    /** Мусорные значения размеров, которые никогда не должны попадать в фильтр */
    private const JUNK_SIZES = [
        'no size' => true, 'nosize' => true, 'no_size' => true, 'no-size' => true,
        'one size' => true, 'onesize' => true, 'one_size' => true, 'one-size' => true,
        'os' => true, 'o/s' => true, 'osfa' => true, 'osfm' => true,
        'u' => true, 'uni' => true, 'uniq' => true, 'unique' => true, 'unica' => true, 'tu' => true,
        'ns' => true, 'n/a' => true, 'na' => true, 'none' => true, 'misc' => true,
        'default' => true, 'std' => true, 'standard' => true, 'free' => true,
        'free size' => true, 'freesize' => true, 'all' => true, 'size' => true,
        '0' => true, '00' => true, '-' => true, '—' => true, '.' => true,
    ];

    /** Естественный порядок буквенных размеров одежды */
    private const LETTER_SIZE_ORDER = [
        'xxxs' => 10, '3xs' => 10,
        'xxs' => 20, '2xs' => 20,
        'xs' => 30, 'xs/s' => 35,
        's' => 40, 's/m' => 45,
        'm' => 50, 'm/l' => 55,
        'l' => 60, 'l/xl' => 65,
        'xl' => 70, 'xl/xxl' => 75,
        'xxl' => 80, '2xl' => 80,
        'xxxl' => 90, '3xl' => 90,
        '4xl' => 100, '5xl' => 110, '6xl' => 120,
    ];

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
     * Плоский список верхних департаментов (для HomeController, CategoryMatcher и обратной совместимости).
     */
    public function get(): Collection
    {
        $v = $this->catalogVersion();
        $locale = app()->getLocale();

        $raw = Cache::remember(
            "home.category_tiles.v{$v}.all",
            now()->addHours(self::CACHE_HOURS),
            fn () => $this->build()
        );

        return collect($raw)->map(fn (array $tile) => [
            'slug' => $tile['slug'],
            'label' => $this->pickLocalizedLabel($tile, $locale),
            'subtitle' => $tile['subtitle'] ?? '',
            'img' => $tile['img'],
            'image' => $tile['image'] ?? (!empty($tile['img']) && is_file(public_path('images/categories/' . $tile['img'])) ? '/images/categories/' . $tile['img'] : null),
            'count' => $tile['count'],
            'url' => route('feed-category.show', ['slug' => $tile['slug']]),
        ]);
    }

    /**
     * Иерархическое дерево L1 -> L2 для шторки категорий, разбитое по вкладкам пола (all, women, men, kids).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getTreeByGender(?int $targetVersion = null): array
    {
        $v = $targetVersion ?? $this->catalogVersion();
        $locale = app()->getLocale();

        $raw = Cache::remember(
            "catalog.tree_by_gender.v{$v}",
            now()->addHours(self::CACHE_HOURS),
            fn () => $this->buildTreeByGenderRaw()
        );

        $out = [];
        foreach (['all', 'women', 'men', 'kids'] as $tab) {
            $departments = [];
            foreach ((array) ($raw[$tab] ?? []) as $dept) {
                $genderQuery = $tab === 'all' ? [] : ['genders' => [$tab]];
                $children = [];

                foreach ((array) ($dept['children'] ?? []) as $child) {
                    $children[] = [
                        'slug' => $child['slug'],
                        'label' => $this->pickLocalizedLabel($child, $locale),
                        'count' => (int) $child['count'],
                        'url' => route('feed-category.show', array_merge(['slug' => $child['slug']], $genderQuery)),
                    ];
                }

                $departments[] = [
                    'slug' => $dept['slug'],
                    'label' => $this->pickLocalizedLabel($dept, $locale),
                    'count' => (int) $dept['count'],
                    'img' => $dept['img'],
                    'image' => $dept['image'] ?? (!empty($dept['img']) ? '/images/categories/' . $dept['img'] : null),
                    'url' => route('feed-category.show', array_merge(['slug' => $dept['slug']], $genderQuery)),
                    'children' => $children,
                ];
            }

            $out[$tab] = $departments;
        }

        return $out;
    }

    /**
     * Категории для бренда с живыми фото товаров этого бренда и точными активными суббрендами в каждой категории.
     */
    public function getForBrand(string $brand, array $genders = []): Collection
    {
        $normalized = mb_strtolower(trim($brand));
        if ($normalized === '') {
            return collect();
        }

        sort($genders);
        $v = $this->catalogVersion();
        $locale = app()->getLocale();
        $cacheKey = "brand.category_tiles.v2.{$v}." . md5($normalized . ':' . implode(',', $genders));

        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($brand, $genders) {
            $matchedBrands = $this->resolveMatchingBrands($brand);
            if ($matchedBrands === [] || !$this->isMeiliReady()) {
                return [];
            }

            try {
                $baseFilters = [
                    'is_active = true',
                    'in_stock = true',
                    $this->meiliIn('brand', $matchedBrands),
                ];
                if ($genders !== []) {
                    $baseFilters[] = $this->meiliIn('gender', $genders);
                }

                $res = $this->meiliIndex()->search('', [
                    'filter' => implode(' AND ', $baseFilters),
                    'facets' => ['category_slugs'],
                    'limit' => 0,
                ]);

                $slugCounts = $this->extractFacetDistribution($res)['category_slugs'] ?? [];
                if ($slugCounts === []) {
                    return [];
                }

                $catMap = $this->loadCategoriesMap();

                // Сначала берём все L2-разделы (дети корней)
                $candidates = [];
                foreach ($catMap['roots'] as $rid) {
                    foreach ($catMap['children'][$rid] ?? [] as $cid) {
                        $node = $catMap['by_id'][$cid] ?? null;
                        if (!$node) {
                            continue;
                        }
                        $cnt = (int) ($slugCounts[$node['slug']] ?? 0);
                        if ($cnt > 0) {
                            $candidates[] = ['node' => $node, 'count' => $cnt];
                        }
                    }
                }

                // Фоллбек на L1-корни, если товары бренда лежат только на верхнем уровне
                if ($candidates === []) {
                    foreach ($catMap['roots'] as $rid) {
                        $node = $catMap['by_id'][$rid] ?? null;
                        if (!$node) {
                            continue;
                        }
                        $cnt = (int) ($slugCounts[$node['slug']] ?? 0);
                        if ($cnt > 0) {
                            $candidates[] = ['node' => $node, 'count' => $cnt];
                        }
                    }
                }

                usort($candidates, fn ($a, $b) => $b['count'] <=> $a['count']);
                $candidates = array_slice($candidates, 0, 14);

                $tiles = [];
                foreach ($candidates as $entry) {
                    $node = $entry['node'];
                    $slug = (string) $node['slug'];
                    $imgFile = self::CATEGORY_IMAGES[$slug] ?? ($slug . '.png');

                    // Одним запросом забираем реальное фото товара этого бренда в категории + только активные суббренды этой категории
                    $catFilters = array_merge($baseFilters, ['category_slugs = ' . $this->meiliQuote($slug)]);
                    $sampleRes = $this->meiliIndex()->search('', [
                        'filter' => implode(' AND ', $catFilters),
                        'facets' => ['brand'],
                        'sort' => ['priority_score:desc'],
                        'limit' => 4,
                        'attributesToRetrieve' => ['id', 'image_url'],
                    ]);

                    $hits = is_object($sampleRes) ? $sampleRes->getHits() : ($sampleRes['hits'] ?? []);
                    $sampleImage = null;
                    foreach ($hits as $h) {
                        if (!empty($h['image_url'])) {
                            $sampleImage = (string) $h['image_url'];
                            break;
                        }
                    }
                    if ($sampleImage === null && is_file(public_path('images/categories/' . $imgFile))) {
                        $sampleImage = '/images/categories/' . $imgFile;
                    }

                    $brandDist = $this->extractFacetDistribution($sampleRes)['brand'] ?? [];
                    $activeBrandsInCat = [];
                    foreach ($matchedBrands as $mb) {
                        if (($brandDist[$mb] ?? 0) > 0) {
                            $activeBrandsInCat[] = $mb;
                        }
                    }
                    if ($activeBrandsInCat === []) {
                        $activeBrandsInCat = $matchedBrands;
                    }

                    $tiles[] = [
                        'slug' => $slug,
                        'name_en' => (string) $node['name_en'],
                        'name_ru' => (string) $node['name_ru'],
                        'name_ka' => (string) $node['name_ka'],
                        'name_el' => (string) $node['name_el'],
                        'img' => $imgFile,
                        'image' => $sampleImage,
                        'count' => (int) $entry['count'],
                        'brands' => $activeBrandsInCat,
                    ];
                }

                return $tiles;
            } catch (\Throwable) {
                return [];
            }
        });

        return collect($raw)->map(fn (array $tile) => [
            'slug' => $tile['slug'],
            'label' => $this->pickLocalizedLabel($tile, $locale),
            'img' => $tile['img'],
            'image' => $tile['image'] ?? null,
            'count' => (int) ($tile['count'] ?? 0),
            'url' => route('feed-category.show', array_filter([
                'slug' => $tile['slug'],
                'brands' => $tile['brands'] ?? [],
                'genders' => $genders !== [] ? $genders : null,
            ])),
        ]);
    }

    /**
     * Находит точный бренд или семейство суббрендов по ВСЕМ активным брендам БД (без обрезки лимитом фасетов Meilisearch).
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
        $allBrands = Cache::remember("catalog.distinct_brands.v2.{$v}", now()->addHours(self::CACHE_HOURS), function () {
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

    /**
     * Резолвит категорию по slug:
     * 1) Виртуальные хабы (sales-offers, kids, childrens-fashion)
     * 2) Реальное дерево categories (L1 / L2 / L3)
     * 3) Legacy-конфиг config('home_categories.tiles') для сохранения старых SEO-ссылок
     */
    public function tile(string $slug): ?array
    {
        $locale = app()->getLocale();

        if ($slug === 'sales-offers') {
            return [
                'mode' => 'virtual',
                'virtual_type' => 'sales',
                'slug' => 'sales-offers',
                'label' => __('Скидки и акции'),
                'where' => ['discount' => true],
                'subcategories' => [],
            ];
        }

        if ($slug === 'kids' || $slug === 'childrens-fashion') {
            return [
                'mode' => 'virtual',
                'virtual_type' => 'kids',
                'slug' => $slug,
                'label' => __('Детские товары'),
                'where' => ['gender' => 'kids'],
                'subcategories' => [],
            ];
        }

        $catMap = $this->loadCategoriesMap();
        if (isset($catMap['by_slug'][$slug])) {
            $row = $catMap['by_slug'][$slug];

            return [
                'mode' => 'category',
                'category_id' => (int) $row['id'],
                'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'slug' => $slug,
                'label' => $this->pickLocalizedLabel($row, $locale),
                'descendant_ids' => $catMap['descendants'][(int) $row['id']] ?? [(int) $row['id']],
                'subcategories' => [],
            ];
        }

        foreach ((array) config('home_categories.tiles', []) as $tile) {
            if (($tile['slug'] ?? null) === $slug) {
                $tile['mode'] = 'tile';

                return $tile;
            }
        }

        return null;
    }

    /**
     * Хлебные крошки для страницы категории (Главная -> L1 -> L2 -> L3).
     *
     * @param  string[]  $activeGenders
     * @return array<int, array{label:string, url:?string}>
     */
    public function buildBreadcrumbs(array $tile, array $activeGenders = []): array
    {
        $locale = app()->getLocale();
        $crumbs = [
            ['label' => __('Главная'), 'url' => route('home')],
        ];

        if (($tile['mode'] ?? '') !== 'category') {
            $crumbs[] = ['label' => __((string) ($tile['label'] ?? '')), 'url' => null];

            return $crumbs;
        }

        $catMap = $this->loadCategoriesMap();
        $chain = [];
        $currId = (int) ($tile['category_id'] ?? 0);
        $guard = 0;

        while ($currId > 0 && isset($catMap['by_id'][$currId]) && $guard < 6) {
            $node = $catMap['by_id'][$currId];
            array_unshift($chain, $node);
            $currId = (int) ($node['parent_id'] ?? 0);
            $guard++;
        }

        $genderQuery = count($activeGenders) === 1 ? ['genders' => $activeGenders] : [];
        $lastIdx = count($chain) - 1;

        foreach ($chain as $i => $node) {
            $crumbs[] = [
                'label' => $this->pickLocalizedLabel($node, $locale),
                'url' => $i === $lastIdx
                    ? null
                    : route('feed-category.show', array_merge(['slug' => $node['slug']], $genderQuery)),
            ];
        }

        return $crumbs;
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
        $mode = (string) ($tile['mode'] ?? 'tile');

        if ($mode === 'category') {
            $ids = (array) ($tile['descendant_ids'] ?? [(int) ($tile['category_id'] ?? 0)]);
            $query->whereIn('category_id', $ids);
        } else {
            $this->applyFilter($query, (array) ($tile['where'] ?? []));
        }

        $this->meiliContexts[$query] = [
            'mode' => $mode,
            'virtual_type' => $tile['virtual_type'] ?? null,
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
            'virtual_type' => null,
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
     * Нормализует массив фильтров, чтобы дефолтные пустые значения давали единый ключ кэша.
     */
    public function normalizeFiltersForCache(array $filters): array
    {
        $out = [];
        if (isset($filters['sub']) && $filters['sub'] !== null && $filters['sub'] !== '') {
            $out['sub'] = (int) $filters['sub'];
        }
        if (!empty($filters['min_price']) && (float) $filters['min_price'] > 0) {
            $out['min_price'] = (float) $filters['min_price'];
        }
        if (!empty($filters['max_price']) && (float) $filters['max_price'] > 0) {
            $out['max_price'] = (float) $filters['max_price'];
        }
        if (!empty($filters['brands'])) {
            $b = array_values(array_filter((array) $filters['brands']));
            sort($b);
            if ($b !== []) {
                $out['brands'] = $b;
            }
        }
        if (!empty($filters['genders'])) {
            $g = array_values(array_filter((array) $filters['genders']));
            sort($g);
            if ($g !== []) {
                $out['genders'] = $g;
            }
        }
        if (!empty($filters['size'])) {
            $out['size'] = trim((string) $filters['size']);
        }
        if (!empty($filters['color'])) {
            $out['color'] = trim((string) $filters['color']);
        }
        if (!empty($filters['discount'])) {
            $out['discount'] = true;
        }

        return $out;
    }

    /**
     * Автоматические уточняющие плитки подкатегорий для верхней карусели.
     */
    public function buildSubcategories(array $tile, ?int $targetVersion = null, array $activeFilters = []): array
    {
        $v = $targetVersion ?? $this->catalogVersion();
        $mode = (string) ($tile['mode'] ?? 'tile');
        $slug = (string) ($tile['slug'] ?? '');
        $locale = app()->getLocale();

        if ($mode === 'category' || $mode === 'virtual') {
            $genders = array_values(array_filter((array) ($activeFilters['genders'] ?? [])));
            sort($genders);
            $genderSuffix = $genders !== [] ? implode('_', $genders) : 'all';
            $cacheKey = "category.tree_subs.v{$v}.{$slug}.{$genderSuffix}";

            $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($tile, $mode, $slug, $genders) {
                $catMap = $this->loadCategoriesMap();
                $candidateNodes = [];
                $extraClauses = [];

                if ($mode === 'category') {
                    $catId = (int) ($tile['category_id'] ?? 0);
                    $childIds = $catMap['children'][$catId] ?? [];
                    foreach ($childIds as $cid) {
                        if (isset($catMap['by_id'][$cid])) {
                            $candidateNodes[] = $catMap['by_id'][$cid];
                        }
                    }
                    if ($genders !== []) {
                        $extraClauses[] = $this->meiliIn('gender', $genders);
                    }
                } elseif (($tile['virtual_type'] ?? '') === 'sales') {
                    foreach ($catMap['roots'] as $rid) {
                        if (isset($catMap['by_id'][$rid])) {
                            $candidateNodes[] = $catMap['by_id'][$rid];
                        }
                    }
                    $extraClauses[] = 'discount_percent > 0';
                } elseif (($tile['virtual_type'] ?? '') === 'kids') {
                    foreach (self::KIDS_SUB_SLUGS as $kSlug) {
                        if (isset($catMap['by_slug'][$kSlug])) {
                            $candidateNodes[] = $catMap['by_slug'][$kSlug];
                        }
                    }
                    $extraClauses[] = 'gender = "kids"';
                }

                if ($candidateNodes === [] || !$this->isMeiliReady()) {
                    return [];
                }

                try {
                    $facetClauses = array_merge(['is_active = true', 'in_stock = true'], $extraClauses);
                    if ($mode === 'category') {
                        $facetClauses[] = 'category_slugs = ' . $this->meiliQuote($slug);
                    }

                    $facetRes = $this->meiliIndex()->search('', [
                        'filter' => implode(' AND ', $facetClauses),
                        'facets' => ['category_slugs'],
                        'limit' => 0,
                    ]);
                    $slugCounts = $this->extractFacetDistribution($facetRes)['category_slugs'] ?? [];

                    $result = [];
                    foreach ($candidateNodes as $idx => $node) {
                        $nSlug = (string) $node['slug'];
                        $cnt = (int) ($slugCounts[$nSlug] ?? 0);
                        if ($cnt <= 0) {
                            continue;
                        }

                        $imgClauses = array_merge(
                            ['is_active = true', 'in_stock = true', 'category_slugs = ' . $this->meiliQuote($nSlug)],
                            $extraClauses
                        );

                        $imgRes = $this->meiliIndex()->search('', [
                            'filter' => implode(' AND ', $imgClauses),
                            'sort' => ['priority_score:desc'],
                            'limit' => 4,
                            'attributesToRetrieve' => ['id', 'image_url'],
                        ]);
                        $hits = is_object($imgRes) ? $imgRes->getHits() : ($imgRes['hits'] ?? []);
                        $sampleImage = null;
                        foreach ($hits as $h) {
                            if (!empty($h['image_url'])) {
                                $sampleImage = (string) $h['image_url'];
                                break;
                            }
                        }

                        $result[] = [
                            'index' => $idx,
                            'slug' => $nSlug,
                            'name_en' => $node['name_en'],
                            'name_ru' => $node['name_ru'],
                            'name_ka' => $node['name_ka'],
                            'name_el' => $node['name_el'],
                            'count' => $cnt,
                            'image' => $sampleImage,
                        ];
                    }

                    return $result;
                } catch (\Throwable) {
                    return [];
                }
            });

            $queryParams = [];
            if (($tile['virtual_type'] ?? '') === 'sales') {
                $queryParams['discount'] = 1;
            } elseif (($tile['virtual_type'] ?? '') === 'kids') {
                $queryParams['genders'] = ['kids'];
            } elseif ($genders !== []) {
                $queryParams['genders'] = $genders;
            }

            return array_map(fn (array $item) => [
                'index' => $item['index'],
                'slug' => $item['slug'],
                'label' => $this->pickLocalizedLabel($item, $locale),
                'count' => (int) $item['count'],
                'image' => $item['image'],
                'url' => route('feed-category.show', array_merge(['slug' => $item['slug']], $queryParams)),
            ], $raw);
        }

        // Legacy fallback для старых плиток из config/home_categories.php
        $subs = (array) ($tile['subcategories'] ?? []);
        if ($subs === []) {
            return [];
        }

        $cacheKey = "category.subs.v{$v}.{$slug}";
        $raw = Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($subs, $slug) {
            $result = [];
            if (!$this->isMeiliReady()) {
                return $result;
            }

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
            } catch (\Throwable) {
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
     * Считает адаптивные фасеты категории через Meilisearch с учётом специфики раздела.
     */
    public function buildFacets(array $tile, array $activeFilters = [], ?int $targetVersion = null): array
    {
        $v = $targetVersion ?? $this->catalogVersion();
        $slug = (string) ($tile['slug'] ?? '');
        $normalizedFilters = $this->normalizeFiltersForCache($activeFilters);
        $cacheKey = "category.facets.v2.{$v}.{$slug}." . md5(json_encode($normalizedFilters));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($tile, $normalizedFilters) {
            $baseClauses = $this->buildTileBaseClauses($tile, $normalizedFilters);
            $profile = $this->resolveCategoryFilterProfile($tile);

            return $this->computeMeiliFacets(
                searchQuery: '',
                baseClauses: $baseClauses,
                filters: $normalizedFilters,
                profile: $profile
            );
        });
    }

    /**
     * Фасетные фильтры для страницы поиска / бренда через Meilisearch.
     */
    public function buildFacetsForSearch(string $query, array $activeFilters = []): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }

        $v = $this->catalogVersion();
        $normalizedFilters = $this->normalizeFiltersForCache($activeFilters);
        $cacheKey = "search.facets.v2.{$v}." . md5(mb_strtolower($q) . ':' . json_encode($normalizedFilters));

        return Cache::remember($cacheKey, now()->addHours(4), function () use ($q, $normalizedFilters) {
            $matchedBrands = $this->resolveMatchingBrands($q);
            $profile = ['show_size' => true, 'show_gender' => true, 'size_mode' => 'all'];

            if ($matchedBrands !== []) {
                return $this->computeMeiliFacets(
                    searchQuery: '',
                    baseClauses: [
                        'is_active = true',
                        'in_stock = true',
                        $this->meiliIn('brand', $matchedBrands),
                    ],
                    filters: $normalizedFilters,
                    profile: $profile
                );
            }

            $prepared = class_exists(ScriptTransliterator::class)
                ? ScriptTransliterator::prepareForMeilisearch($q)
                : $q;

            return $this->computeMeiliFacets(
                searchQuery: $prepared,
                baseClauses: ['is_active = true', 'in_stock = true'],
                filters: $normalizedFilters,
                profile: $profile
            );
        });
    }

    /**
     * Определяет набор релевантных фильтров исходя из особенностей категории (L1 / L2 / L3).
     *
     * @return array{show_size: bool, show_gender: bool, size_mode: string}
     */
    private function resolveCategoryFilterProfile(?array $tile): array
    {
        $default = ['show_size' => true, 'show_gender' => true, 'size_mode' => 'all'];
        if (!$tile) {
            return $default;
        }

        $mode = (string) ($tile['mode'] ?? '');
        $slug = (string) ($tile['slug'] ?? '');

        if ($mode === 'virtual') {
            if (($tile['virtual_type'] ?? '') === 'kids') {
                return ['show_size' => true, 'show_gender' => false, 'size_mode' => 'all'];
            }

            return $default;
        }

        if ($mode !== 'category') {
            return $default;
        }

        $catMap = $this->loadCategoriesMap();
        $chainSlugs = [];
        $currId = (int) ($tile['category_id'] ?? 0);
        $guard = 0;

        while ($currId > 0 && isset($catMap['by_id'][$currId]) && $guard < 6) {
            $node = $catMap['by_id'][$currId];
            array_unshift($chainSlugs, (string) $node['slug']);
            $currId = (int) ($node['parent_id'] ?? 0);
            $guard++;
        }

        $rootSlug = $chainSlugs[0] ?? $slug;

        if (in_array($rootSlug, ['tech', 'toys'], true)) {
            return ['show_size' => false, 'show_gender' => false, 'size_mode' => 'none'];
        }

        if ($rootSlug === 'beauty') {
            $isPerfume = false;
            foreach ($chainSlugs as $s) {
                if (str_contains($s, 'perfume') || str_contains($s, 'fragrance')) {
                    $isPerfume = true;
                    break;
                }
            }

            return ['show_size' => false, 'show_gender' => $isPerfume, 'size_mode' => 'none'];
        }

        if ($rootSlug === 'baby-mom') {
            return ['show_size' => true, 'show_gender' => false, 'size_mode' => 'all'];
        }

        if ($rootSlug === 'shoes') {
            return ['show_size' => true, 'show_gender' => true, 'size_mode' => 'shoes'];
        }

        if ($rootSlug === 'clothing') {
            return ['show_size' => true, 'show_gender' => true, 'size_mode' => 'clothing'];
        }

        if ($rootSlug === 'accessories') {
            foreach ($chainSlugs as $s) {
                if (str_contains($s, 'belt') || str_contains($s, 'hat') || str_contains($s, 'cap') || str_contains($s, 'glove')) {
                    return ['show_size' => true, 'show_gender' => true, 'size_mode' => 'all'];
                }
                if (
                    str_contains($s, 'bag')
                    || str_contains($s, 'backpack')
                    || str_contains($s, 'luggage')
                    || str_contains($s, 'sunglass')
                    || str_contains($s, 'eyewear')
                    || str_contains($s, 'watch')
                    || str_contains($s, 'jewel')
                    || str_contains($s, 'tote')
                    || str_contains($s, 'clutch')
                    || str_contains($s, 'wallet')
                ) {
                    return ['show_size' => false, 'show_gender' => true, 'size_mode' => 'none'];
                }
            }
        }

        return $default;
    }

    /**
     * @return array<int, string>
     */
    private function buildTileBaseClauses(array $tile, array $normalizedFilters = []): array
    {
        $clauses = ['is_active = true', 'in_stock = true'];
        $mode = (string) ($tile['mode'] ?? 'tile');
        $slug = (string) ($tile['slug'] ?? '');

        if ($mode === 'category') {
            if ($slug !== '') {
                $clauses[] = 'category_slugs = ' . $this->meiliQuote($slug);
            }
        } elseif ($mode === 'virtual') {
            if (($tile['virtual_type'] ?? '') === 'sales') {
                $clauses[] = 'discount_percent > 0';
            } elseif (($tile['virtual_type'] ?? '') === 'kids') {
                $clauses[] = 'gender = "kids"';
            }
        } else {
            if ($slug !== '') {
                $clauses[] = 'tile_slugs = ' . $this->meiliQuote($slug);
            }
            if (isset($normalizedFilters['sub']) && isset($tile['subcategories'][$normalizedFilters['sub']])) {
                $clauses[] = 'sub_keys = ' . $this->meiliQuote("{$slug}:" . (int) $normalizedFilters['sub']);
            }
        }

        return $clauses;
    }

    /**
     * @param  array{show_size: bool, show_gender: bool, size_mode: string}  $profile
     */
    private function computeMeiliFacets(string $searchQuery, array $baseClauses, array $filters, array $profile): array
    {
        if (!$this->isMeiliReady()) {
            return ['brands' => [], 'genders' => [], 'sizes' => [], 'colors' => []];
        }

        try {
            $hasFacetFilters = !empty($filters['brands'])
                || !empty($filters['genders'])
                || !empty($filters['color'])
                || !empty($filters['size']);

            $needGender = $profile['show_gender'] || !empty($filters['genders']);
            $needSize = $profile['show_size'] || !empty($filters['size']);

            if (!$hasFacetFilters) {
                $facetsToRequest = ['brand', 'colour_main'];
                if ($needGender) {
                    $facetsToRequest[] = 'gender';
                }
                if ($needSize) {
                    $facetsToRequest[] = 'sizes_list';
                }

                $clauses = array_merge($baseClauses, $this->buildUserFilterClauses($filters, null));
                $res = $this->meiliIndex()->search($searchQuery, [
                    'filter' => implode(' AND ', $clauses),
                    'facets' => $facetsToRequest,
                    'matchingStrategy' => 'all',
                    'limit' => 0,
                ]);

                $dist = $this->extractFacetDistribution($res);

                return $this->formatFacetDistribution(
                    $dist['brand'] ?? [],
                    $needGender ? ($dist['gender'] ?? []) : [],
                    $dist['colour_main'] ?? [],
                    $needSize ? ($dist['sizes_list'] ?? []) : [],
                    $filters,
                    $profile
                );
            }

            $brandDist = $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'brands', 'brand');
            $genderDist = $needGender ? $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'genders', 'gender') : [];
            $colorDist = $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'color', 'colour_main');
            $sizeDist = $needSize ? $this->fetchSingleFacet($searchQuery, $baseClauses, $filters, 'size', 'sizes_list') : [];

            return $this->formatFacetDistribution($brandDist, $genderDist, $colorDist, $sizeDist, $filters, $profile);
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

    /**
     * @param  array{show_size: bool, show_gender: bool, size_mode: string}  $profile
     */
    private function formatFacetDistribution(
        array $brandDist,
        array $genderDist,
        array $colorDist,
        array $sizeDist,
        array $filters = [],
        array $profile = ['show_size' => true, 'show_gender' => true, 'size_mode' => 'all']
    ): array {
        // Выбранные бренды всегда поднимаем наверх списка, чтобы чекбоксы сразу были видны и снимались
        $selectedBrands = array_values(array_filter((array) ($filters['brands'] ?? [])));
        $selectedSet = array_fill_keys($selectedBrands, true);

        arsort($brandDist);
        $brands = [];
        $addedBrands = [];

        foreach ($selectedBrands as $sb) {
            $brands[] = ['name' => (string) $sb, 'count' => (int) ($brandDist[$sb] ?? 0)];
            $addedBrands[$sb] = true;
        }
        foreach ($brandDist as $name => $cnt) {
            $nameStr = trim((string) $name);
            if ($nameStr === '' || $cnt <= 0 || isset($addedBrands[$nameStr])) {
                continue;
            }
            $brands[] = ['name' => $nameStr, 'count' => (int) $cnt];
            $addedBrands[$nameStr] = true;
            if (count($brands) >= 30) {
                break;
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

        $sizes = $profile['show_size'] || !empty($filters['size'])
            ? $this->filterAndSortSizes($sizeDist, (string) ($profile['size_mode'] ?? 'all'), $filters['size'] ?? null)
            : [];

        return [
            'brands' => $brands,
            'genders' => $genders,
            'sizes' => $sizes,
            'colors' => $colors,
        ];
    }

    /**
     * Убирает мусорные размеры (NO SIZE, ONE SIZE и т.п.), фильтрует под тип категории и сортирует по возрастанию.
     *
     * @param  array<string, int>  $sizeDist
     * @return string[]
     */
    private function filterAndSortSizes(array $sizeDist, string $mode, ?string $activeSize = null): array
    {
        arsort($sizeDist);
        $picked = [];
        $activeTrim = $activeSize !== null ? trim($activeSize) : '';

        foreach ($sizeDist as $raw => $cnt) {
            $sz = trim((string) $raw);
            if ($sz === '' || $cnt <= 0) {
                continue;
            }
            if ($sz !== $activeTrim && ($this->isJunkSize($sz) || !$this->sizeMatchesMode($sz, $mode))) {
                continue;
            }

            $picked[$sz] = true;
            if (count($picked) >= 24) {
                break;
            }
        }

        if ($activeTrim !== '' && !isset($picked[$activeTrim])) {
            $picked[$activeTrim] = true;
        }

        $list = array_keys($picked);

        usort($list, function (string $a, string $b): int {
            $ka = $this->sizeSortKey($a);
            $kb = $this->sizeSortKey($b);

            if ($ka[0] !== $kb[0]) {
                return $ka[0] <=> $kb[0];
            }
            if ($ka[1] !== $kb[1]) {
                return $ka[1] <=> $kb[1];
            }

            return strnatcasecmp($a, $b);
        });

        return $list;
    }

    private function isJunkSize(string $size): bool
    {
        if (mb_strlen($size) > 12) {
            return true;
        }

        $low = mb_strtolower(trim($size));
        if (isset(self::JUNK_SIZES[$low])) {
            return true;
        }

        foreach (['no ', 'no_', 'no-', 'one ', 'one_', 'one-'] as $prefix) {
            if (str_starts_with($low, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function sizeMatchesMode(string $size, string $mode): bool
    {
        if ($mode === 'none') {
            return false;
        }

        $clean = trim($size);

        if ($mode === 'shoes') {
            if (preg_match('/^(\d{2})(?:[.,]5|\s+[123]\/[234])?(?:\s*(?:EU|UK|US))?$/i', $clean, $m)) {
                $num = (int) $m[1];

                return $num >= 15 && $num <= 52;
            }
            if (preg_match('/^(\d{2})\s*[-–\/]\s*(\d{2})$/', $clean, $m)) {
                $a = (int) $m[1];
                $b = (int) $m[2];

                return $a >= 15 && $b <= 52 && $b > $a && ($b - $a) <= 4;
            }

            return false;
        }

        if ($mode === 'clothing') {
            // Отсекаем обувные дробные размеры вроде "40 2/3", "42 1/3", "38.5"
            if (preg_match('/^\d{2}\s+[123]\/[234]$/', $clean)) {
                return false;
            }
            if (preg_match('/^(\d{2})[.,]5$/', $clean, $m) && (int) $m[1] >= 16 && (int) $m[1] <= 50) {
                return false;
            }

            return true;
        }

        return true;
    }

    /**
     * @return array{0: int, 1: float}
     */
    private function sizeSortKey(string $size): array
    {
        $low = mb_strtolower(trim($size));

        // 1. Буквенные размеры (XXXS .. 5XL)
        if (isset(self::LETTER_SIZE_ORDER[$low])) {
            return [1, (float) self::LETTER_SIZE_ORDER[$low]];
        }

        // 2. Числовые размеры (36, 36.5, 40 2/3, 42)
        if (preg_match('/^(\d{1,3})(?:[.,](\d+)|\s+([123])\/([234]))?$/', $low, $m)) {
            $base = (float) $m[1];
            if (!empty($m[2])) {
                $base += (float) ('0.' . $m[2]);
            } elseif (!empty($m[3]) && !empty($m[4])) {
                $base += ((float) $m[3]) / ((float) $m[4]);
            }

            return [2, $base];
        }

        // 3. Джинсовые W_L / W/L (28_30, 30_32, 32/32)
        if (preg_match('/^(\d{2})[\/_](\d{2})$/', $low, $m)) {
            return [3, (float) $m[1] * 100.0 + (float) $m[2]];
        }

        // 4. Остальные (детские 2Y, 92-98, бра 75B и т.д.)
        return [4, 0.0];
    }

    /**
     * Возвращает переведённые карточки страницы и $total из Redis (строго чистые массивы).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public function paginateGroupedCards(Builder $filteredBase, string $sort, int $page, int $perPage, string $cachePrefix, ?int $targetVersion = null): array
    {
        $v = $targetVersion ?? $this->catalogVersion();
        $locale = app()->getLocale();
        $pageCacheKey = "catalog_page:v{$v}:{$locale}:{$cachePrefix}:{$sort}:p{$page}";

        return Cache::remember($pageCacheKey, now()->addHours(6), function () use ($filteredBase, $sort, $page, $perPage, $locale) {
            $result = null;
            if ($this->isMeiliReady() && isset($this->meiliContexts[$filteredBase])) {
                $result = $this->paginateViaMeilisearch($this->meiliContexts[$filteredBase], $sort, $page, $perPage);
            }

            if ($result === null) {
                $result = $this->paginateFallbackSql($filteredBase, $sort, $page, $perPage);
            }

            [$rawCards, $total] = $result;
            if ($rawCards !== []) {
                $rawCards = app(FeedEntityLocalizer::class)->localizeCardTitles($rawCards, $locale);
            }

            return [$rawCards, $total];
        });
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{0: array<int, array<string, mixed>>, 1: int}|null
     */
    private function paginateViaMeilisearch(array $ctx, string $sort, int $page, int $perPage): ?array
    {
        try {
            $clauses = ['is_active = true', 'in_stock = true'];
            $searchQuery = '';
            $mode = (string) ($ctx['mode'] ?? '');

            if ($mode === 'category') {
                $slug = (string) ($ctx['tile_slug'] ?? '');
                if ($slug !== '') {
                    $clauses[] = 'category_slugs = ' . $this->meiliQuote($slug);
                }
            } elseif ($mode === 'virtual') {
                if (($ctx['virtual_type'] ?? '') === 'sales') {
                    $clauses[] = 'discount_percent > 0';
                } elseif (($ctx['virtual_type'] ?? '') === 'kids') {
                    $clauses[] = 'gender = "kids"';
                }
            } elseif ($mode === 'tile') {
                $slug = (string) ($ctx['tile_slug'] ?? '');
                if ($slug !== '') {
                    $clauses[] = 'tile_slugs = ' . $this->meiliQuote($slug);
                }
                if (!empty($ctx['sub_key'])) {
                    $clauses[] = 'sub_keys = ' . $this->meiliQuote((string) $ctx['sub_key']);
                }
            } elseif ($mode === 'search') {
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
     * Быстрый резервный SQL-фоллбэк, если Meilisearch недоступен.
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
     * Атомарный прогрев новой версии кэша (дерево шторки, департаменты L1, подкатегории, фасеты и 1-я страница).
     */
    public function warmAll(): void
    {
        $nextVersion = $this->catalogVersion() + 1;
        $originalLocale = app()->getLocale();

        Cache::forget('catalog.categories_map.v1');

        Cache::put(
            "home.category_tiles.v{$nextVersion}.all",
            $this->build(),
            now()->addHours(self::CACHE_HOURS)
        );

        Cache::put(
            "catalog.tree_by_gender.v{$nextVersion}",
            $this->buildTreeByGenderRaw(),
            now()->addHours(self::CACHE_HOURS)
        );

        $perPage = (int) config('feeds.search_limit', 24);
        $defaultFilterHash = md5(json_encode([]));

        $catMap = $this->loadCategoriesMap();
        $warmSlugs = ['sales-offers', 'kids'];
        foreach ($catMap['roots'] as $rid) {
            if (isset($catMap['by_id'][$rid])) {
                $warmSlugs[] = (string) $catMap['by_id'][$rid]['slug'];
            }
        }
        foreach (['sneakers', 'sportswear', 'jackets', 'hoodies', 'bags', 'perfume', 'makeup', 'lego-building', 'gaming'] as $popularL2) {
            if (isset($catMap['by_slug'][$popularL2])) {
                $warmSlugs[] = $popularL2;
            }
        }

        foreach (array_unique($warmSlugs) as $slug) {
            $tile = $this->tile($slug);
            if ($tile === null) {
                continue;
            }

            $this->buildSubcategories($tile, $nextVersion);
            $this->buildFacets($tile, [], $nextVersion);

            $base = $this->productsQuery($tile);
            $filtered = $this->applyUserFilters($base, []);

            foreach (['en', 'ru', 'ka'] as $loc) {
                app()->setLocale($loc);
                $this->paginateGroupedCards($filtered, 'relevance', 1, $perPage, "cat:{$slug}:{$defaultFilterHash}", $nextVersion);
            }
        }

        app()->setLocale($originalLocale);
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
     * Строит верхние департаменты L1 + виртуальные хабы (kids, sales-offers) для быстрого доступа.
     */
    private function build(): array
    {
        $catMap = $this->loadCategoriesMap();
        $slugCounts = [];
        $kidsCount = 0;
        $salesCount = 0;

        if ($this->isMeiliReady()) {
            try {
                $res = $this->meiliIndex()->search('', [
                    'filter' => 'is_active = true AND in_stock = true',
                    'facets' => ['category_slugs', 'gender'],
                    'limit' => 0,
                ]);
                $dist = $this->extractFacetDistribution($res);
                $slugCounts = $dist['category_slugs'] ?? [];
                $kidsCount = (int) ($dist['gender']['kids'] ?? 0);

                $salesRes = $this->meiliIndex()->search('', [
                    'filter' => 'is_active = true AND in_stock = true AND discount_percent > 0',
                    'limit' => 0,
                ]);
                $salesCount = is_object($salesRes)
                    ? (int) ($salesRes->getEstimatedTotalHits() ?? 0)
                    : (int) ($salesRes['estimatedTotalHits'] ?? 0);
            } catch (\Throwable) {
            }
        }

        $tiles = [];

        foreach ($catMap['roots'] as $rid) {
            $node = $catMap['by_id'][$rid] ?? null;
            if (!$node) {
                continue;
            }
            $slug = (string) $node['slug'];
            $count = (int) ($slugCounts[$slug] ?? 0);
            if ($count === 0) {
                continue;
            }

            $topBrands = $this->fetchTopBrandsForClause('category_slugs = ' . $this->meiliQuote($slug));
            $imgFile = self::CATEGORY_IMAGES[$slug] ?? ($slug . '.png');

            $tiles[] = [
                'slug' => $slug,
                'name_en' => $node['name_en'],
                'name_ru' => $node['name_ru'],
                'name_ka' => $node['name_ka'],
                'name_el' => $node['name_el'],
                'subtitle' => implode(', ', $topBrands),
                'img' => $imgFile,
                'image' => $this->resolveDepartmentImage($slug, $imgFile),
                'count' => $count,
            ];
        }

        if ($kidsCount > 0) {
            $imgFile = self::CATEGORY_IMAGES['kids'];
            $tiles[] = [
                'slug' => 'kids',
                'name_en' => 'Kids & Teens',
                'name_ru' => 'Детские товары',
                'name_ka' => 'საბავშვო',
                'name_el' => 'Παιδικά',
                'subtitle' => implode(', ', $this->fetchTopBrandsForClause('gender = "kids"')),
                'img' => $imgFile,
                'image' => $this->resolveDepartmentImage('clothing', $imgFile, 'gender = "kids"'),
                'count' => $kidsCount,
            ];
        }

        if ($salesCount > 0) {
            $imgFile = self::CATEGORY_IMAGES['sales-offers'];
            $tiles[] = [
                'slug' => 'sales-offers',
                'name_en' => 'Sales & Offers',
                'name_ru' => 'Скидки и акции',
                'name_ka' => 'ფასდაკლებები',
                'name_el' => 'Προσφορές',
                'subtitle' => '',
                'img' => $imgFile,
                'image' => is_file(public_path('images/categories/' . $imgFile)) ? '/images/categories/' . $imgFile : null,
                'count' => $salesCount,
            ];
        }

        return $tiles;
    }

    /**
     * Строит сырое мультиязычное дерево L1 -> L2 для всех 4 вкладок пола за 4 фасетных запроса Meilisearch (~12 мс).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildTreeByGenderRaw(): array
    {
        $catMap = $this->loadCategoriesMap();
        $tabs = [
            'all' => null,
            'women' => 'gender = "women"',
            'men' => 'gender = "men"',
            'kids' => 'gender = "kids"',
        ];

        $result = [];

        foreach ($tabs as $tab => $genderClause) {
            $slugCounts = [];
            if ($this->isMeiliReady()) {
                try {
                    $clauses = ['is_active = true', 'in_stock = true'];
                    if ($genderClause !== null) {
                        $clauses[] = $genderClause;
                    }
                    $res = $this->meiliIndex()->search('', [
                        'filter' => implode(' AND ', $clauses),
                        'facets' => ['category_slugs'],
                        'limit' => 0,
                    ]);
                    $slugCounts = $this->extractFacetDistribution($res)['category_slugs'] ?? [];
                } catch (\Throwable) {
                }
            }

            $depts = [];
            foreach ($catMap['roots'] as $rid) {
                $root = $catMap['by_id'][$rid] ?? null;
                if (!$root) {
                    continue;
                }
                $rSlug = (string) $root['slug'];
                $rCount = (int) ($slugCounts[$rSlug] ?? 0);
                if ($rCount <= 0) {
                    continue;
                }

                $children = [];
                foreach ($catMap['children'][$rid] ?? [] as $cid) {
                    $child = $catMap['by_id'][$cid] ?? null;
                    if (!$child) {
                        continue;
                    }
                    $cSlug = (string) $child['slug'];
                    $cCount = (int) ($slugCounts[$cSlug] ?? 0);
                    if ($cCount <= 0) {
                        continue;
                    }

                    $children[] = [
                        'slug' => $cSlug,
                        'name_en' => $child['name_en'],
                        'name_ru' => $child['name_ru'],
                        'name_ka' => $child['name_ka'],
                        'name_el' => $child['name_el'],
                        'count' => $cCount,
                    ];
                }

                $imgFile = self::CATEGORY_IMAGES[$rSlug] ?? ($rSlug . '.png');

                $depts[] = [
                    'slug' => $rSlug,
                    'name_en' => $root['name_en'],
                    'name_ru' => $root['name_ru'],
                    'name_ka' => $root['name_ka'],
                    'name_el' => $root['name_el'],
                    'img' => $imgFile,
                    'image' => $this->resolveDepartmentImage($rSlug, $imgFile, $genderClause),
                    'count' => $rCount,
                    'children' => $children,
                ];
            }

            $result[$tab] = $depts;
        }

        return $result;
    }

    /**
     * Возвращает локальную иконку из public/images/categories/, а если файла нет —
     * подтягивает реальное фото топового товара из Meilisearch (с учётом пола).
     */
    private function resolveDepartmentImage(string $slug, string $imgFile, ?string $genderClause = null): ?string
    {
        if ($imgFile !== '' && is_file(public_path('images/categories/' . $imgFile))) {
            return '/images/categories/' . $imgFile;
        }

        if ($this->isMeiliReady()) {
            try {
                $clauses = [
                    'is_active = true',
                    'in_stock = true',
                    'category_slugs = ' . $this->meiliQuote($slug),
                ];
                if ($genderClause !== null) {
                    $clauses[] = $genderClause;
                }

                $res = $this->meiliIndex()->search('', [
                    'filter' => implode(' AND ', $clauses),
                    'sort' => ['priority_score:desc'],
                    'limit' => 5,
                    'attributesToRetrieve' => ['id', 'image_url'],
                ]);

                $hits = is_object($res) ? $res->getHits() : ($res['hits'] ?? []);
                foreach ($hits as $h) {
                    if (!empty($h['image_url'])) {
                        return (string) $h['image_url'];
                    }
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * Загружает всю таблицу categories в компактную карту (строго чистые массивы).
     *
     * @return array{by_id:array<int,array<string,mixed>>, by_slug:array<string,array<string,mixed>>, roots:int[], children:array<int,int[]>, descendants:array<int,int[]>}
     */
    private function loadCategoriesMap(): array
    {
        return Cache::remember('catalog.categories_map.v1', now()->addHours(self::CACHE_HOURS), function () {
            $rows = DB::table('categories')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'parent_id', 'slug', 'name_en', 'name_ru', 'name_ka', 'name_el', 'sort_order']);

            $byId = [];
            $bySlug = [];
            $roots = [];
            $children = [];

            foreach ($rows as $r) {
                $item = [
                    'id' => (int) $r->id,
                    'parent_id' => $r->parent_id !== null ? (int) $r->parent_id : null,
                    'slug' => (string) $r->slug,
                    'name_en' => (string) $r->name_en,
                    'name_ru' => (string) $r->name_ru,
                    'name_ka' => (string) $r->name_ka,
                    'name_el' => (string) $r->name_el,
                    'sort_order' => (int) $r->sort_order,
                ];
                $byId[$item['id']] = $item;
                $bySlug[$item['slug']] = $item;

                if ($item['parent_id'] === null) {
                    $roots[] = $item['id'];
                } else {
                    $children[$item['parent_id']][] = $item['id'];
                }
            }

            $collectDescendants = function (int $id) use (&$collectDescendants, $children): array {
                $ids = [$id];
                foreach ($children[$id] ?? [] as $cid) {
                    $ids = array_merge($ids, $collectDescendants($cid));
                }

                return $ids;
            };

            $descendants = [];
            foreach (array_keys($byId) as $id) {
                $descendants[$id] = $collectDescendants($id);
            }

            return [
                'by_id' => $byId,
                'by_slug' => $bySlug,
                'roots' => $roots,
                'children' => $children,
                'descendants' => $descendants,
            ];
        });
    }

    /**
     * @return string[]
     */
    private function fetchTopBrandsForClause(string $clause): array
    {
        if (!$this->isMeiliReady()) {
            return [];
        }

        try {
            $bRes = $this->meiliIndex()->search('', [
                'filter' => 'is_active = true AND in_stock = true AND ' . $clause,
                'facets' => ['brand'],
                'limit' => 0,
            ]);
            $bDist = $this->extractFacetDistribution($bRes)['brand'] ?? [];
            arsort($bDist);

            return array_slice(
                array_keys(array_filter($bDist, fn ($c, $k) => trim((string) $k) !== '' && $c > 0, ARRAY_FILTER_USE_BOTH)),
                0,
                3
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private function pickLocalizedLabel(array $item, string $locale): string
    {
        $key = match ($locale) {
            'ru' => 'name_ru',
            'ka' => 'name_ka',
            'el' => 'name_el',
            default => 'name_en',
        };

        if (!empty($item[$key])) {
            return (string) $item[$key];
        }
        if (!empty($item['name_en'])) {
            return (string) $item['name_en'];
        }

        return __((string) ($item['label'] ?? $item['slug'] ?? ''));
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