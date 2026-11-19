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
use Illuminate\Support\Facades\Lang;

class AutocompleteBuilder
{
    private const LOCALES = ['en', 'ru', 'ka', 'el'];
    private const MAX_FACET_BRANDS = 25;
    private const MAX_MODELS = 6000;
    private const PER_FEED_MODEL_SAMPLE = 1200;
    private const TOP_DISCOUNT_MODEL_SAMPLE = 4000;
    private const UPSERT_CHUNK = 500;

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

        // 1. Активные бренды (покрывающий индекс idx_fp_active_stock_brand — без тяжёлого COUNT(DISTINCT ...))
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

        $topFacetBrands = array_slice($activeBrands, 0, self::MAX_FACET_BRANDS, true);
        $topBrandNames = array_keys($topFacetBrands);

        // 2. Категории таксономии (только те, где реально есть товары)
        $tiles = $this->categoryTiles->get();
        foreach ($tiles as $tile) {
            $slug = (string) ($tile['slug'] ?? '');
            $count = (int) ($tile['count'] ?? 0);
            $rawLabel = (string) ($tile['label'] ?? '');

            if ($slug === '' || $count <= 0 || $rawLabel === '') {
                continue;
            }

            $labels = $this->translateUiLabel($rawLabel);
            $key = 'cat:' . $slug;

            $buffer[$key] = [
                'entry_key' => $key,
                'type' => 'category',
                'title_en' => $labels['en'],
                'title_ru' => $labels['ru'],
                'title_ka' => $labels['ka'],
                'title_el' => $labels['el'],
                'badge_en' => 'Category',
                'badge_ru' => 'Категория',
                'badge_ka' => 'კატეგორია',
                'badge_el' => 'Κατηγορία',
                'search_keywords' => $this->packKeywords(array_merge(array_values($labels), [$slug])),
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

            // 3. Фасеты Бренд + Категория (через Meilisearch за ~10мс, с безопасным MySQL-фолбэком)
            if ($topBrandNames !== []) {
                $brandCounts = $this->getTileBrandCounts($tile, $topBrandNames);

                foreach ($topFacetBrands as $brand => $brandTotal) {
                    $facetCount = (int) ($brandCounts[$brand] ?? 0);
                    if ($facetCount <= 0) {
                        continue;
                    }

                    $facetKey = 'facet:' . mb_strtolower($brand) . ':' . $slug;
                    $aliases = $brandAliasesMap[mb_strtolower($brand)] ?? [$brand];

                    $buffer[$facetKey] = [
                        'entry_key' => $facetKey,
                        'type' => 'facet',
                        'title_en' => $brand . ' — ' . $labels['en'],
                        'title_ru' => $brand . ' — ' . $labels['ru'],
                        'title_ka' => $brand . ' — ' . $labels['ka'],
                        'title_el' => $brand . ' — ' . $labels['el'],
                        'badge_en' => 'Category',
                        'badge_ru' => 'Категория',
                        'badge_ka' => 'კატეგორია',
                        'badge_el' => 'Κατηγορία',
                        'search_keywords' => $this->packKeywords(array_merge($aliases, array_values($labels), [$slug])),
                        'url' => route('product-search', ['q' => $brand . ' ' . $labels['ru']], false),
                        'image_url' => null,
                        'price' => null,
                        'products_count' => $facetCount,
                        'popularity_hits' => (int) ($existingHits[$facetKey] ?? 0),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $stats['facets']++;
                    $this->flushIfNeeded($buffer, $stats['total']);
                }
            }
        }

        // 4. Топ уникальных моделей через индексные выборки по фидам и скидкам (без дискового GROUP BY по 450k строк)
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
     * @param  array<string, mixed>  $tile
     * @param  string[]  $brandNames
     * @return array<string, int>
     */
    private function getTileBrandCounts(array $tile, array $brandNames): array
    {
        $slug = (string) ($tile['slug'] ?? '');
        if ($slug !== '' && config('scout.driver') === 'meilisearch') {
            try {
                $client = app(\Meilisearch\Client::class);
                $indexName = (new FeedProduct())->searchableAs();
                $escapedSlug = str_replace(['\\', '"'], ['\\\\', '\"'], $slug);

                $res = $client->index($indexName)->search('', [
                    'filter' => 'is_active = true AND in_stock = true AND tile_slugs = "' . $escapedSlug . '"',
                    'facets' => ['brand'],
                    'limit' => 0,
                ]);

                $facets = method_exists($res, 'getFacetDistribution')
                    ? $res->getFacetDistribution()
                    : ($res['facetDistribution'] ?? []);

                $dist = $facets['brand'] ?? null;
                if (is_array($dist) && $dist !== []) {
                    $out = [];
                    foreach ($brandNames as $b) {
                        if (!empty($dist[$b])) {
                            $out[$b] = (int) $dist[$b];
                        }
                    }

                    return $out;
                }
            } catch (\Throwable) {
                // Фолбэк на MySQL ниже
            }
        }

        return (clone $this->categoryTiles->productsQuery($tile))
            ->whereIn('brand', $brandNames)
            ->selectRaw('brand, COUNT(*) as cnt')
            ->groupBy('brand')
            ->pluck('cnt', 'brand')
            ->all();
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

        // 1. По индексу idx_fp_active_stock_feed_rating — гарантированное представительство каждого активного фида
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

        // 2. По индексу idx_fp_active_stock_discount — топ товаров со скидками по всей витрине
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
     * @return array{en: string, ru: string, ka: string, el: string}
     */
    private function translateUiLabel(string $label): array
    {
        $out = [];
        foreach (self::LOCALES as $loc) {
            $tr = Lang::get($label, [], $loc);
            $out[$loc] = is_string($tr) && $tr !== '' ? $tr : $label;
        }

        return $out;
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