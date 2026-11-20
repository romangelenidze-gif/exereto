<?php

namespace App\Services\Autocomplete;

use App\Models\AutocompleteEntry;
use App\Models\EntityTranslation;
use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use App\Services\Feeds\FeedText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;

class AutocompleteBuilder
{
    private const MAX_FACET_BRANDS = 30;
    private const MIN_FACET_COUNT = 5;
    private const MAX_MODELS = 6000;
    private const PER_FEED_MODEL_SAMPLE = 1200;
    private const TOP_DISCOUNT_MODEL_SAMPLE = 4000;
    private const UPSERT_CHUNK = 500;

    /** Разговорные синонимы для мгновенного попадания в нужную категорию */
    private const CATEGORY_SYNONYMS = [
        'jackets' => ['куртки', 'куртка', 'ветровка', 'верхняя одежда', 'jacket', 'coat'],
        'puffer-jackets' => ['пуховик', 'пуховики', 'парка', 'puffer', 'down jacket'],
        'coats' => ['пальто', 'тренч', 'плащ', 'coat', 'trench'],
        'hoodies' => ['худи', 'толстовка', 'толстовки', 'свитшот', 'кофта', 'hoodie', 'sweatshirt'],
        'sweaters' => ['свитер', 'свитеры', 'джемпер', 'кардиган', 'пуловер', 'sweater', 'knitwear'],
        't-shirts' => ['футболка', 'футболки', 'майка', 'tshirt', 't-shirt', 'tee'],
        'shirts' => ['рубашка', 'рубашки', 'блузка', 'блуза', 'shirt', 'blouse'],
        'dresses' => ['платье', 'платья', 'сарафан', 'комбинезон', 'dress', 'jumpsuit'],
        'skirts' => ['юбка', 'юбки', 'skirt'],
        'jeans' => ['джинсы', 'деним', 'jeans', 'denim'],
        'pants' => ['брюки', 'штаны', 'pants', 'trousers'],
        'shorts' => ['шорты', 'бермуды', 'shorts'],
        'sportswear' => ['спорт', 'спортивная одежда', 'спорттовары', 'sportswear'],
        'tracksuits' => ['спортивный костюм', 'спортивные костюмы', 'костюм', 'tracksuit'],
        'track-pants' => ['спортивные штаны', 'спортивки', 'джоггеры', 'joggers', 'track pants'],
        'leggings' => ['леггинсы', 'легинсы', 'лосины', 'тайтсы', 'leggings'],
        'swimwear' => ['купальник', 'купальники', 'плавки', 'бикини', 'bikini', 'swimwear'],
        'underwear-socks' => ['белье', 'нижнее белье', 'трусы', 'лифчик', 'бюстгальтер', 'underwear', 'lingerie'],
        'socks' => ['носки', 'гольфы', 'колготки', 'socks'],
        'sleepwear' => ['пижама', 'пижамы', 'халат', 'домашняя одежда', 'pyjama', 'sleepwear'],
        'shoes' => ['обувь', 'shoes', 'footwear'],
        'sneakers' => ['кроссовки', 'кросы', 'кеды', 'сникерсы', 'sneakers', 'trainers'],
        'running-shoes' => ['беговые кроссовки', 'для бега', 'running'],
        'football-boots' => ['бутсы', 'футбольные бутсы', 'сороконожки', 'шиповки', 'football boots', 'cleats'],
        'boots' => ['ботинки', 'сапоги', 'берцы', 'boots'],
        'ankle-boots' => ['ботильоны', 'челси', 'полусапожки', 'chelsea', 'ankle boots'],
        'heels' => ['туфли', 'каблуки', 'лодочки', 'heels', 'pumps'],
        'sandals' => ['сандалии', 'босоножки', 'сандали', 'sandals'],
        'slides' => ['шлепанцы', 'шлепки', 'сланцы', 'тапочки', 'тапки', 'кроксы', 'slides', 'slippers', 'crocs'],
        'bags' => ['сумка', 'сумки', 'сумочка', 'bags', 'handbag'],
        'backpacks' => ['рюкзак', 'рюкзаки', 'портфель', 'backpack'],
        'luggage-travel' => ['чемодан', 'чемоданы', 'дорожная сумка', 'luggage', 'suitcase'],
        'wallets' => ['кошелек', 'кошельки', 'портмоне', 'картхолдер', 'wallet'],
        'belts' => ['ремень', 'ремни', 'пояс', 'belt'],
        'eyewear' => ['очки', 'оправа', 'eyewear', 'glasses'],
        'sunglasses' => ['солнцезащитные очки', 'солнечные очки', 'очки от солнца', 'sunglasses'],
        'watches' => ['часы', 'наручные часы', 'watches'],
        'jewelry' => ['украшения', 'бижутерия', 'серьги', 'колье', 'браслет', 'кольцо', 'jewelry'],
        'hats-scarves' => ['шапка', 'кепка', 'бейсболка', 'шарф', 'перчатки', 'cap', 'hat', 'scarf'],
        'perfume' => ['парфюм', 'духи', 'парфюмерия', 'туалетная вода', 'одеколон', 'perfume', 'fragrance'],
        'makeup' => ['косметика', 'макияж', 'помада', 'тушь', 'тональник', 'консилер', 'тени', 'makeup'],
        'skincare' => ['крем', 'сыворотка', 'уход за лицом', 'умывалка', 'маска для лица', 'skincare'],
        'haircare' => ['шампунь', 'волосы', 'краска для волос', 'маска для волос', 'haircare'],
        'suncare' => ['спф', 'spf', 'от солнца', 'солнцезащитный крем', 'suncare'],
        'pharmacy-health' => ['витамины', 'бады', 'аптека', 'здоровье', 'vitamins', 'pharmacy'],
        'toys' => ['игрушки', 'игрушка', 'toys'],
        'lego-building' => ['лего', 'конструктор', 'конструкторы', 'lego', 'playmobil'],
        'dolls' => ['кукла', 'куклы', 'барби', 'barbie', 'dolls'],
        'plush-toys' => ['мягкая игрушка', 'мягкие игрушки', 'плюшевая', 'plush'],
        'puzzles-boardgames' => ['пазлы', 'пазл', 'настолки', 'настольные игры', 'puzzle', 'board games'],
        'gaming' => ['игры', 'гейминг', 'приставка', 'playstation', 'ps5', 'nintendo', 'xbox', 'gaming'],
        'video-games' => ['игры ps5', 'игры switch', 'диски ps5', 'video games'],
        'laptops' => ['ноутбук', 'ноутбуки', 'ноут', 'макбук', 'macbook', 'laptop'],
        'smartphones' => ['смартфон', 'смартфоны', 'телефон', 'айфон', 'iphone', 'smartphone'],
        'tablets' => ['планшет', 'планшеты', 'айпад', 'ipad', 'tablet'],
        'wearables' => ['смарт часы', 'умные часы', 'эпл вотч', 'apple watch', 'smartwatch'],
        'headphones' => ['наушники', 'аирподс', 'airpods', 'гарнитура', 'headphones', 'earbuds'],
        'speakers' => ['колонка', 'колонки', 'блютуз колонка', 'jbl', 'speaker'],
        'kids' => ['детское', 'детская одежда', 'детская обувь', 'детям', 'ребенку', 'kids', 'children'],
        'sales-offers' => ['скидки', 'акции', 'распродажа', 'sale', 'discount', 'offers'],
    ];

    public function __construct(private FeedCategoryTiles $categoryTiles)
    {
    }

    /**
     * Полная материализация таблицы autocomplete_entries по актуальным товарам в наличии
     * с сохранением накопленной популярности (popularity_hits).
     *
     * @return array{brands: int, categories: int, facets: int, models: int, total: int}
     */
    public function rebuild(): array
    {
        $now = now();
        $existingHits = AutocompleteEntry::query()
            ->where('popularity_hits', '>', 0)
            ->pluck('popularity_hits', 'entry_key')
            ->all();

        $prefixTranslations = EntityTranslation::query()
            ->where('entity_type', 'title_prefix')
            ->get()
            ->keyBy('source_hash');

        $brandAliasesMap = $this->buildBrandAliasesMap();
        $buffer = [];
        $stats = ['brands' => 0, 'categories' => 0, 'facets' => 0, 'models' => 0, 'total' => 0];

        // 1. Активные бренды
        $brandRows = FeedProduct::query()
            ->where('is_active', true)
            ->where('in_stock', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->selectRaw('brand, COUNT(*) as models_count')
            ->groupBy('brand')
            ->orderByDesc('models_count')
            ->get();

        $activeBrands = [];
        foreach ($brandRows as $bRow) {
            $brand = trim((string) $bRow->brand);
            $count = (int) $bRow->models_count;
            if ($brand === '' || $count <= 0) {
                continue;
            }

            $activeBrands[$brand] = $count;
            $key = 'brand:' . mb_strtolower($brand);
            $aliases = $brandAliasesMap[mb_strtolower($brand)] ?? [$brand];

            $buffer[$key] = [
                'entry_key' => $key,
                'type' => 'brand',
                'title_en' => $brand,
                'title_ru' => $brand,
                'title_ka' => $brand,
                'title_el' => $brand,
                'badge_en' => 'Brand',
                'badge_ru' => 'Бренд',
                'badge_ka' => 'ბრენდი',
                'badge_el' => 'Μάρκα',
                'search_keywords' => $this->packKeywords($aliases),
                'url' => route('product-search', ['q' => $brand], false),
                'image_url' => null,
                'price' => null,
                'products_count' => $count,
                'popularity_hits' => (int) ($existingHits[$key] ?? 0),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $stats['brands']++;
            $this->flushIfNeeded($buffer, $stats['total']);
        }

        // 2. Все активные узлы дерева categories (L1 + L2 + L3) + виртуальные хабы (kids, sales-offers)
        $categoriesBySlug = $this->loadTreeCategoriesWithCounts();

        foreach ($categoriesBySlug as $slug => $cat) {
            $count = (int) $cat['count'];
            if ($count <= 0) {
                continue;
            }

            $key = 'cat:' . $slug;
            $synonyms = self::CATEGORY_SYNONYMS[$slug] ?? [];

            $buffer[$key] = [
                'entry_key' => $key,
                'type' => 'category',
                'title_en' => $cat['name_en'],
                'title_ru' => $cat['name_ru'],
                'title_ka' => $cat['name_ka'],
                'title_el' => $cat['name_el'],
                'badge_en' => 'Category',
                'badge_ru' => 'Категория',
                'badge_ka' => 'კატეგორია',
                'badge_el' => 'Κατηγορία',
                'search_keywords' => $this->packKeywords(array_merge(
                    [$cat['name_en'], $cat['name_ru'], $cat['name_ka'], $cat['name_el'], str_replace('-', ' ', $slug), $slug],
                    $synonyms
                )),
                'url' => route('feed-category.show', ['slug' => $slug], false),
                'image_url' => null,
                'price' => null,
                'products_count' => $count,
                'popularity_hits' => (int) ($existingHits[$key] ?? 0),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $stats['categories']++;
            $this->flushIfNeeded($buffer, $stats['total']);
        }

        // 3. Фасеты Бренд + Категория (1 запрос к Meilisearch на бренд вместо 1 на категорию)
        $topFacetBrands = array_slice($activeBrands, 0, self::MAX_FACET_BRANDS, true);
        foreach ($topFacetBrands as $brand => $brandTotal) {
            $slugCounts = $this->getBrandCategoryCounts($brand);
            if ($slugCounts === []) {
                continue;
            }

            $aliases = $brandAliasesMap[mb_strtolower($brand)] ?? [$brand];

            foreach ($slugCounts as $slug => $facetCount) {
                if ($facetCount < self::MIN_FACET_COUNT || !isset($categoriesBySlug[$slug])) {
                    continue;
                }

                $cat = $categoriesBySlug[$slug];
                // Для виртуальных хабов отдельные бренд-фасеты не плодим
                if (!empty($cat['is_virtual'])) {
                    continue;
                }

                $facetKey = 'facet:' . mb_strtolower($brand) . ':' . $slug;
                $synonyms = self::CATEGORY_SYNONYMS[$slug] ?? [];

                $buffer[$facetKey] = [
                    'entry_key' => $facetKey,
                    'type' => 'facet',
                    'title_en' => $brand . ' — ' . $cat['name_en'],
                    'title_ru' => $brand . ' — ' . $cat['name_ru'],
                    'title_ka' => $brand . ' — ' . $cat['name_ka'],
                    'title_el' => $brand . ' — ' . $cat['name_el'],
                    'badge_en' => 'Category',
                    'badge_ru' => 'Категория',
                    'badge_ka' => 'კატეგორია',
                    'badge_el' => 'Κατηγορία',
                    'search_keywords' => $this->packKeywords(array_merge(
                        $aliases,
                        [$cat['name_en'], $cat['name_ru'], $cat['name_ka'], $cat['name_el'], str_replace('-', ' ', $slug)],
                        $synonyms
                    )),
                    'url' => route('feed-category.show', ['slug' => $slug, 'brands' => [$brand]], false),
                    'image_url' => null,
                    'price' => null,
                    'products_count' => (int) $facetCount,
                    'popularity_hits' => (int) ($existingHits[$facetKey] ?? 0),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $stats['facets']++;
                $this->flushIfNeeded($buffer, $stats['total']);
            }
        }

        // 4. Топ уникальных моделей через индексные выборки по фидам и скидкам
        $selectedModels = $this->collectTopModels();

        foreach ($selectedModels as $item) {
            $product = $item['row'];
            $brand = trim((string) ($product->brand ?? ''));
            $rawName = trim((string) $product->name);
            if ($rawName === '') {
                continue;
            }

            $gKey = $product->feed . ':' . ($product->model_code ?: $product->sku);
            $titleEl = $rawName;
            $titleEn = $rawName;
            $titleRu = $rawName;
            $titleKa = $rawName;

            $split = FeedEntityLocalizer::splitGreekTitle($rawName);
            if ($split !== null) {
                $tr = $prefixTranslations->get(EntityTranslation::hash($split['prefix']));
                if ($tr !== null) {
                    $titleEn = trim($tr->forLocale('en') . ' ' . $split['model']);
                    $titleRu = trim($tr->forLocale('ru') . ' ' . $split['model']);
                    $titleKa = trim($tr->forLocale('ka') . ' ' . $split['model']);
                }
            }

            $entryKey = 'model:' . mb_strtolower($gKey);
            $aliases = $brand !== '' ? ($brandAliasesMap[mb_strtolower($brand)] ?? [$brand]) : [];

            $buffer[$entryKey] = [
                'entry_key' => $entryKey,
                'type' => 'model',
                'title_en' => mb_substr($titleEn, 0, 255),
                'title_ru' => mb_substr($titleRu, 0, 255),
                'title_ka' => mb_substr($titleKa, 0, 255),
                'title_el' => mb_substr($titleEl, 0, 255),
                'badge_en' => $brand !== '' ? $brand : 'Model',
                'badge_ru' => $brand !== '' ? $brand : 'Модель',
                'badge_ka' => $brand !== '' ? $brand : 'მოდელი',
                'badge_el' => $brand !== '' ? $brand : 'Μοντέλο',
                'search_keywords' => $this->packKeywords(array_merge(
                    [$titleEn, $titleRu, $titleKa, $titleEl, (string) $product->model_code, (string) $product->sku],
                    $aliases
                )),
                'url' => route('feed-product.show', ['feed' => $product->feed, 'sku' => $product->sku], false),
                'image_url' => $product->image_url ? (string) $product->image_url : null,
                'price' => (float) $product->price,
                'products_count' => $item['count'],
                'popularity_hits' => (int) ($existingHits[$entryKey] ?? 0),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $stats['models']++;
            $this->flushIfNeeded($buffer, $stats['total']);
        }

        $this->flushBuffer($buffer, $stats['total']);

        if ($stats['total'] > 0) {
            AutocompleteEntry::query()
                ->where('updated_at', '<', $now)
                ->delete();
        }

        Cache::increment('autocomplete:cache_ver');

        return $stats;
    }

    /**
     * Загружает все активные узлы дерева categories с реальными счётчиками из Meilisearch (или MySQL-фолбэком).
     *
     * @return array<string, array{name_en:string, name_ru:string, name_ka:string, name_el:string, count:int, is_virtual:bool}>
     */
    private function loadTreeCategoriesWithCounts(): array
    {
        $rows = DB::table('categories')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'slug', 'name_en', 'name_ru', 'name_ka', 'name_el']);

        $slugCounts = [];
        $kidsCount = 0;
        $salesCount = 0;

        $index = $this->meiliIndex();
        if ($index !== null) {
            try {
                $res = $index->search('', [
                    'filter' => 'is_active = true AND in_stock = true',
                    'facets' => ['category_slugs', 'gender'],
                    'limit' => 0,
                ]);
                $dist = method_exists($res, 'getFacetDistribution')
                    ? (array) $res->getFacetDistribution()
                    : (array) ($res['facetDistribution'] ?? []);

                $slugCounts = (array) ($dist['category_slugs'] ?? []);
                $kidsCount = (int) ($dist['gender']['kids'] ?? 0);

                $salesRes = $index->search('', [
                    'filter' => 'is_active = true AND in_stock = true AND discount_percent > 0',
                    'limit' => 0,
                ]);
                $salesCount = is_object($salesRes)
                    ? (int) ($salesRes->getEstimatedTotalHits() ?? 0)
                    : (int) ($salesRes['estimatedTotalHits'] ?? 0);
            } catch (\Throwable) {
            }
        }

        // MySQL-фолбэк, если Meilisearch временно недоступен
        if ($slugCounts === []) {
            $directCounts = DB::table('feed_products')
                ->where('is_active', 1)
                ->where('in_stock', 1)
                ->whereNotNull('category_id')
                ->selectRaw('category_id, COUNT(*) as cnt')
                ->groupBy('category_id')
                ->pluck('cnt', 'category_id')
                ->all();

            $byId = $rows->keyBy('id');
            foreach ($directCounts as $catId => $cnt) {
                $curr = $byId->get($catId);
                $guard = 0;
                while ($curr !== null && $guard < 6) {
                    $slugCounts[$curr->slug] = ($slugCounts[$curr->slug] ?? 0) + (int) $cnt;
                    $curr = $curr->parent_id ? $byId->get($curr->parent_id) : null;
                    $guard++;
                }
            }
        }

        $result = [];
        foreach ($rows as $r) {
            $cnt = (int) ($slugCounts[$r->slug] ?? 0);
            if ($cnt <= 0) {
                continue;
            }

            $result[(string) $r->slug] = [
                'name_en' => (string) $r->name_en,
                'name_ru' => (string) ($r->name_ru ?: $r->name_en),
                'name_ka' => (string) ($r->name_ka ?: $r->name_en),
                'name_el' => (string) ($r->name_el ?: $r->name_en),
                'count' => $cnt,
                'is_virtual' => false,
            ];
        }

        if ($kidsCount > 0) {
            $result['kids'] = [
                'name_en' => 'Kids & Teens',
                'name_ru' => 'Детские товары',
                'name_ka' => 'საბავშვო',
                'name_el' => 'Παιδικά',
                'count' => $kidsCount,
                'is_virtual' => true,
            ];
        }

        if ($salesCount > 0) {
            $result['sales-offers'] = [
                'name_en' => 'Sales & Offers',
                'name_ru' => 'Скидки и акции',
                'name_ka' => 'ფასდაკლებები',
                'name_el' => 'Προσφορές',
                'count' => $salesCount,
                'is_virtual' => true,
            ];
        }

        return $result;
    }

    /**
     * Возвращает карту [category_slug => count] для одного бренда за один фасетный запрос к Meilisearch.
     *
     * @return array<string, int>
     */
    private function getBrandCategoryCounts(string $brand): array
    {
        $index = $this->meiliIndex();
        if ($index !== null) {
            try {
                $escapedBrand = str_replace(['\\', '"'], ['\\\\', '\"'], $brand);
                $res = $index->search('', [
                    'filter' => 'is_active = true AND in_stock = true AND brand = "' . $escapedBrand . '"',
                    'facets' => ['category_slugs'],
                    'limit' => 0,
                ]);
                $dist = method_exists($res, 'getFacetDistribution')
                    ? (array) $res->getFacetDistribution()
                    : (array) ($res['facetDistribution'] ?? []);

                $catDist = $dist['category_slugs'] ?? [];
                if (is_array($catDist) && $catDist !== []) {
                    $out = [];
                    foreach ($catDist as $slug => $cnt) {
                        if ((int) $cnt > 0) {
                            $out[(string) $slug] = (int) $cnt;
                        }
                    }

                    return $out;
                }
            } catch (\Throwable) {
            }
        }

        return [];
    }

    private function meiliIndex(): ?\Meilisearch\Endpoints\Indexes
    {
        $host = (string) (config('scout.meilisearch.host') ?: config('scout.meilisearch.url'));
        if (config('scout.driver') !== 'meilisearch' || $host === '') {
            return null;
        }

        $client = new Client($host, config('scout.meilisearch.key') ?: null);

        return $client->index((new FeedProduct())->searchableAs());
    }

    /**
     * Быстрая индексная выборка кандидатов моделей по каждому фиду и по максимальным скидкам.
     *
     * @return array<int, array{row: object, count: int, priority: int, rating: float, discount: int}>
     */
    private function collectTopModels(): array
    {
        $columns = ['id', 'feed', 'sku', 'model_code', 'brand', 'name', 'price', 'image_url', 'priority_score', 'rating', 'discount_percent'];
        $grouped = [];

        $ingest = static function ($rows) use (&$grouped): void {
            foreach ($rows as $row) {
                $groupKey = $row->feed . ':' . ($row->model_code ?: $row->sku);
                $priority = (int) ($row->priority_score ?? 0);
                $rating = (float) ($row->rating ?? 0);
                $discount = (int) ($row->discount_percent ?? 0);

                if (!isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'row' => $row,
                        'count' => 1,
                        'priority' => $priority,
                        'rating' => $rating,
                        'discount' => $discount,
                        'seen_ids' => [(int) $row->id => true],
                    ];
                    continue;
                }

                if (isset($grouped[$groupKey]['seen_ids'][(int) $row->id])) {
                    continue;
                }

                $grouped[$groupKey]['seen_ids'][(int) $row->id] = true;
                $grouped[$groupKey]['count']++;

                if (
                    $priority > $grouped[$groupKey]['priority']
                    || ($priority === $grouped[$groupKey]['priority'] && $rating > $grouped[$groupKey]['rating'])
                    || ($priority === $grouped[$groupKey]['priority'] && $rating === $grouped[$groupKey]['rating'] && $discount > $grouped[$groupKey]['discount'])
                ) {
                    $grouped[$groupKey]['row'] = $row;
                    $grouped[$groupKey]['priority'] = $priority;
                    $grouped[$groupKey]['rating'] = $rating;
                    $grouped[$groupKey]['discount'] = $discount;
                }
            }
        };

        $activeFeeds = array_keys(array_filter(
            (array) config('feeds.feeds', []),
            fn ($cfg) => !empty($cfg['enabled'])
        ));

        foreach ($activeFeeds as $feedKey) {
            $rows = DB::table('feed_products')
                ->where('is_active', true)
                ->where('in_stock', true)
                ->where('feed', $feedKey)
                ->orderByDesc('rating')
                ->limit(self::PER_FEED_MODEL_SAMPLE)
                ->get($columns);

            $ingest($rows);
        }

        $discountRows = DB::table('feed_products')
            ->where('is_active', true)
            ->where('in_stock', true)
            ->orderByDesc('discount_percent')
            ->limit(self::TOP_DISCOUNT_MODEL_SAMPLE)
            ->get($columns);

        $ingest($discountRows);

        $items = array_values($grouped);
        usort($items, static function (array $a, array $b): int {
            return ($b['count'] <=> $a['count'])
                ?: ($b['priority'] <=> $a['priority'])
                ?: ($b['rating'] <=> $a['rating'])
                ?: ($b['discount'] <=> $a['discount'])
                ?: ((int) $a['row']->id <=> (int) $b['row']->id);
        });

        return array_slice($items, 0, self::MAX_MODELS);
    }

    /**
     * @param  array<string, array<string, mixed>>  $buffer
     */
    private function flushIfNeeded(array &$buffer, int &$total): void
    {
        if (count($buffer) >= self::UPSERT_CHUNK) {
            $this->flushBuffer($buffer, $total);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $buffer
     */
    private function flushBuffer(array &$buffer, int &$total): void
    {
        if ($buffer === []) {
            return;
        }

        $chunk = array_values($buffer);
        DB::table('autocomplete_entries')->upsert(
            $chunk,
            ['entry_key'],
            [
                'type',
                'title_en',
                'title_ru',
                'title_ka',
                'title_el',
                'badge_en',
                'badge_ru',
                'badge_ka',
                'badge_el',
                'search_keywords',
                'url',
                'image_url',
                'price',
                'products_count',
                'is_active',
                'updated_at',
            ]
        );

        $total += count($chunk);
        $buffer = [];
    }

    /**
     * @return array<string, string[]>
     */
    private function buildBrandAliasesMap(): array
    {
        $map = [];
        foreach ((array) config('search_glossary.brands', []) as $alias => $canonical) {
            $canonKey = mb_strtolower(trim((string) $canonical));
            if ($canonKey === '') {
                continue;
            }
            $map[$canonKey][] = (string) $canonical;
            $map[$canonKey][] = (string) $alias;
        }

        foreach ($map as $k => $list) {
            $map[$k] = array_values(array_unique($list));
        }

        return $map;
    }

    /**
     * @param  string[]  $parts
     */
    private function packKeywords(array $parts): string
    {
        $normalized = [];
        foreach ($parts as $part) {
            $norm = FeedText::normalize($part);
            if ($norm !== '') {
                $normalized[] = $norm;
            }
        }

        $joined = implode(' ', array_unique($normalized));

        return ' ' . trim($joined) . ' ';
    }
}