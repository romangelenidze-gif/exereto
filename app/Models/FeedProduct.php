<?php

namespace App\Models;

use App\Services\Feeds\AwinLink;
use App\Services\Feeds\FeedEntityLocalizer;
use App\Services\Feeds\FeedText;
use App\Services\Feeds\FeedVariantGrouper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class FeedProduct extends Model
{
    use Searchable;

    protected $guarded = [];

    /** @var array<string, array<string, EntityTranslation>>|null */
    private static ?array $translationCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $compiledTiles = null;

    /** @var array<int, string>|null */
    private static ?array $excludedTops = null;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'group_min_price' => 'float',
            'old_price' => 'float',
            'rating' => 'float',
            'delivery_cost' => 'float',
            'discount_percent' => 'integer',
            'priority_score' => 'integer',
            'variants_count' => 'integer',
            'has_price_range' => 'boolean',
            'in_stock' => 'boolean',
            'is_active' => 'boolean',
            'sizes' => 'array',
            'specs' => 'array',
            'image_urls' => 'array',
            'last_seen_at' => 'datetime',
            'cascade_hidden' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function searchableAs(): string
    {
        return config('scout.prefix', '') . 'feed_products';
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) $this->is_active;
    }

    public function toSearchableArray(): array
    {
        $dict = self::getTranslationDictionary();

        $rawName = trim((string) $this->name);
        $titleEl = $rawName;
        $titleEn = FeedEntityLocalizer::translateTextWithDictionary($rawName, 'en', $dict);
        $titleRu = FeedEntityLocalizer::translateTextWithDictionary($rawName, 'ru', $dict);
        $titleKa = FeedEntityLocalizer::translateTextWithDictionary($rawName, 'ka', $dict);

        $catKeywords = [];
        foreach (explode(',', (string) $this->category_path) as $seg) {
            $seg = trim($seg);
            if ($seg === '') {
                continue;
            }
            $catKeywords[] = $seg;
            $catKeywords[] = FeedEntityLocalizer::translateTextWithDictionary($seg, 'en', $dict);
            $catKeywords[] = FeedEntityLocalizer::translateTextWithDictionary($seg, 'ru', $dict);
            $catKeywords[] = FeedEntityLocalizer::translateTextWithDictionary($seg, 'ka', $dict);
        }

        $colorKeywords = [];
        foreach (preg_split('/\s*[\/,]\s*/u', (string) $this->colour) ?: [] as $colPart) {
            $colPart = trim($colPart);
            if ($colPart === '') {
                continue;
            }
            $colorKeywords[] = $colPart;
            $colorKeywords[] = FeedEntityLocalizer::translateTextWithDictionary($colPart, 'en', $dict);
            $colorKeywords[] = FeedEntityLocalizer::translateTextWithDictionary($colPart, 'ru', $dict);
            $colorKeywords[] = FeedEntityLocalizer::translateTextWithDictionary($colPart, 'ka', $dict);
        }

        $genderKeywords = match ($this->gender) {
            'men' => 'men мужские мужская мужской для мужчин მამაკაცის ανδρικα',
            'women' => 'women женские женская женский для женщин ქალის γυναικεια',
            'kids' => 'kids детские детская детский для детей საბავშვო παιδικα',
            'unisex' => 'unisex унисекс უნისექსი',
            default => '',
        };

        $modelBase = ($this->model_code !== null && $this->model_code !== '')
            ? $this->model_code
            : ($rawName !== ''
                ? substr(md5(mb_strtolower(trim($this->brand . '|' . $rawName . '|' . $this->colour))), 0, 16)
                : $this->sku);

        $groupKey = $this->feed . ':' . $modelBase;

        [$tileSlugs, $subKeys] = $this->resolveTilesAndSubKeys();

        $colourMain = trim(explode('/', (string) $this->colour)[0] ?? '');

        return [
            'id' => (int) $this->id,
            'feed' => (string) $this->feed,
            'sku' => (string) $this->sku,
            'model_code' => (string) ($this->model_code ?? ''),
            'group_key' => $groupKey,
            'brand' => (string) ($this->brand ?? ''),
            'title_el' => $titleEl,
            'title_en' => $titleEn,
            'title_ru' => $titleRu,
            'title_ka' => $titleKa,
            'category_keywords' => implode(' ', array_unique($catKeywords)),
            'color_keywords' => implode(' ', array_unique($colorKeywords)),
            'gender_keywords' => $genderKeywords,
            'search_text' => FeedText::normalize((string) $this->search_text),
            'category_top' => (string) ($this->category_top ?? ''),
            'category_path' => (string) ($this->category_path ?? ''),
            'category_id' => $this->category_id ? (int) $this->category_id : null,
            'category_slugs' => $this->category_id
                ? (\App\Models\Category::ancestorSlugsMap()[(int) $this->category_id] ?? [])
                : [],
            'gender' => (string) ($this->gender ?? 'unisex'),
            'colour_main' => $colourMain,
            'sizes_list' => $this->extractSizeLabels(),
            'tile_slugs' => $tileSlugs,
            'sub_keys' => $subKeys,
            'image_url' => (string) ($this->image_url ?? ''),
            'price' => (float) $this->price,
            'discount_percent' => (int) ($this->discount_percent ?? 0),
            'priority_score' => (int) ($this->priority_score ?? 50),
            'rating' => $this->rating !== null ? (float) $this->rating : -1.0,
            'in_stock' => (bool) $this->in_stock,
            'is_active' => (bool) $this->is_active,
        ];
    }

    /**
     * Вычисляет принадлежность товара к плиткам и подкатегориям один раз при индексации.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public function resolveTilesAndSubKeys(): array
    {
        if (self::$excludedTops === null) {
            self::$excludedTops = array_map(
                fn ($t) => mb_strtolower(trim((string) $t)),
                array_filter((array) config('home_categories.excluded_tops', ['Home']))
            );
        }

        $topLower = mb_strtolower(trim((string) $this->category_top));
        if ($topLower !== '' && in_array($topLower, self::$excludedTops, true)) {
            return [[], []];
        }

        if (self::$compiledTiles === null) {
            self::$compiledTiles = (array) config('home_categories.tiles', []);
        }

        $pathLower = mb_strtolower((string) $this->category_path);
        $gender = (string) $this->gender;
        $discount = (int) $this->discount_percent;

        $tileSlugs = [];
        $subKeys = [];

        foreach (self::$compiledTiles as $tile) {
            $slug = (string) ($tile['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            if (!$this->matchesWhereClause((array) ($tile['where'] ?? []), $pathLower, $topLower, $gender, $discount)) {
                continue;
            }

            $tileSlugs[] = $slug;

            foreach ((array) ($tile['subcategories'] ?? []) as $idx => $sub) {
                if ($this->matchesWhereClause((array) ($sub['where'] ?? []), $pathLower, $topLower, $gender, $discount)) {
                    $subKeys[] = "{$slug}:{$idx}";
                }
            }
        }

        return [$tileSlugs, $subKeys];
    }

    private function matchesWhereClause(array $where, string $pathLower, string $topLower, string $gender, int $discount): bool
    {
        foreach ($where as $key => $value) {
            if ($key === 'gender') {
                if ($gender !== (string) $value) {
                    return false;
                }
            } elseif ($key === 'category_top') {
                if ($topLower !== mb_strtolower(trim((string) $value))) {
                    return false;
                }
            } elseif ($key === 'discount') {
                if ($value && $discount <= 0) {
                    return false;
                }
            } elseif ($key === 'category_path') {
                $matched = false;
                foreach ((array) $value as $needle) {
                    $n = mb_strtolower(trim((string) $needle));
                    if ($n !== '' && str_contains($pathLower, $n)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function extractSizeLabels(): array
    {
        $raw = $this->sizes;
        $arr = is_string($raw) ? json_decode($raw, true) : (array) $raw;
        if (!is_array($arr)) {
            return [];
        }

        $labels = [];
        foreach ($arr as $s) {
            $val = is_array($s)
                ? ($s['size'] ?? $s['name'] ?? $s['value'] ?? $s['label'] ?? null)
                : $s;

            if (is_scalar($val)) {
                $str = trim((string) $val);
                if ($str !== '' && mb_strlen($str) <= 12) {
                    $labels[$str] = true;
                }
            }
        }

        return array_keys($labels);
    }

    public static function getTranslationDictionary(): array
    {
        if (self::$translationCache !== null) {
            return self::$translationCache;
        }

        self::$translationCache = [
            'title_prefix' => [],
            'category_segment' => [],
            'color' => [],
            'spec' => [],
        ];

        EntityTranslation::query()
            ->whereIn('entity_type', ['title_prefix', 'category_segment', 'color', 'spec'])
            ->get()
            ->each(function (EntityTranslation $row) {
                self::$translationCache[$row->entity_type][$row->source_hash] = $row;
            });

        return self::$translationCache;
    }

    public function outboundUrl(): string
    {
        return AwinLink::forProduct(
            $this->deep_link,
            $this->merchant_url,
            (array) config("feeds.feeds.{$this->feed}.english_url", [])
        );
    }

    public function toCard(): array
    {
        $locale = app()->getLocale();
        $title = (string) $this->name;
        $colour = $this->colour;

        $variantsCount = (int) ($this->variants_count ?? 1);
        $hasPriceRange = (bool) ($this->has_price_range ?? false);
        $groupMinPrice = $this->group_min_price !== null && $this->group_min_price > 0
            ? (float) $this->group_min_price
            : (float) $this->price;

        // Если карточка объединяет несколько конфигураций (vg-), показываем чистое имя базовой модели без хвоста конкретной памяти/цвета
        if ($variantsCount > 1 && str_starts_with((string) ($this->model_code ?? ''), FeedVariantGrouper::GENERATED_PREFIX)) {
            $title = FeedVariantGrouper::cleanCardTitle($title, $this->variant_label, $colour);
        }

        if ($locale !== 'el') {
            $dict = self::getTranslationDictionary();
            if ($title !== '' && FeedEntityLocalizer::containsGreek($title)) {
                $title = FeedEntityLocalizer::translateTextWithDictionary($title, $locale, $dict);
            }
            if (is_string($colour) && $colour !== '' && FeedEntityLocalizer::containsGreek($colour)) {
                $colour = FeedEntityLocalizer::translateTextWithDictionary($colour, $locale, $dict);
            }
        }

        return [
            'title' => $title,
            'brand' => $this->brand,
            'colour' => $colour,
            'variant_label' => $this->variant_label,
            'variants_count' => $variantsCount,
            'has_price_range' => $hasPriceRange,
            'group_min_price' => $groupMinPrice,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'price_amount' => $this->price,
            'old_price' => $this->old_price,
            'discount_percent' => $this->discount_percent,
            'currency' => $this->currency ?: 'EUR',
            'rating' => $this->rating,
            'in_stock' => $this->in_stock,
            'detail_url' => $this->detailUrl(),
            'product_url' => $this->outboundUrl(),
            'merchant_name' => (string) config("feeds.feeds.{$this->feed}.store_name", $this->feed),
            'source' => 'feed',
        ];
    }

    public function detailUrl(): string
    {
        return route('feed-product.show', ['feed' => $this->feed, 'sku' => $this->sku]);
    }
}