<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\CategoryMatcher;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\GeminiVisionSearchService;
use App\Services\Feeds\ScriptTransliterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Meilisearch\Client;

class PhotoSearchController extends Controller
{
    public function search(
        Request $request,
        GeminiVisionSearchService $vision,
        FeedCategoryTiles $tiles,
        CategoryMatcher $categoryMatcher
    ): JsonResponse {
        if (!$vision->isAvailable()) {
            return response()->json([
                'ok' => false,
                'message' => __('Поиск по фото временно отключён.'),
            ], 503);
        }

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $validated['image'];
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || $bytes === '') {
            return response()->json([
                'ok' => false,
                'message' => __('Не удалось прочитать изображение. Попробуйте другое фото.'),
            ], 422);
        }

        $locale = app()->getLocale();
        $result = $vision->analyze($bytes, (string) $file->getMimeType(), $locale);

        if ($result['status'] === 'quota_exceeded') {
            return response()->json([
                'ok' => false,
                'message' => __('Дневной лимит поиска по фото исчерпан. Пожалуйста, воспользуйтесь текстовым поиском.'),
            ], 429);
        }

        if ($result['status'] === 'not_product') {
            return response()->json([
                'ok' => false,
                'message' => __('Не удалось распознать товар на фото. Сфотографируйте вещь крупнее и чётче.'),
            ], 422);
        }

        if ($result['status'] !== 'ok') {
            return response()->json([
                'ok' => false,
                'message' => __('Сервис распознавания временно недоступен. Попробуйте ещё раз чуть позже.'),
            ], 503);
        }

        $redirectUrl = $this->resolveCatalogUrl($result, $tiles, $categoryMatcher);
        if ($redirectUrl === null) {
            return response()->json([
                'ok' => false,
                'message' => __('Похожие товары пока не найдены в каталоге. Попробуйте другое фото или текстовый поиск.'),
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'redirect_url' => $redirectUrl,
            'detected' => [
                'label' => $result['item_label'],
                'brand' => $result['brand'],
                'color' => $result['color'],
                'category_slug' => $result['category_slug'],
                'model_query' => $result['model_query'],
            ],
        ]);
    }

    /**
     * Каскадный подбор URL выдачи с проверкой наличия товаров в Meilisearch (гарантия непустой выдачи).
     *
     * @param  array<string, mixed>  $vision
     */
    private function resolveCatalogUrl(
        array $vision,
        FeedCategoryTiles $tiles,
        CategoryMatcher $categoryMatcher
    ): ?string {
        $brand = !empty($vision['brand']) ? trim((string) $vision['brand']) : null;
        $categorySlug = !empty($vision['category_slug']) ? trim((string) $vision['category_slug']) : null;
        $gender = in_array($vision['gender'] ?? null, ['men', 'women', 'kids'], true)
            ? (string) $vision['gender']
            : null;
        $color = !empty($vision['color']) ? trim((string) $vision['color']) : null;
        $modelQuery = trim((string) ($vision['model_query'] ?? ''));

        $matchedBrands = $brand !== null ? $tiles->resolveMatchingBrands($brand) : [];
        $tile = $categorySlug !== null ? $tiles->tile($categorySlug) : null;
        $validCategorySlug = ($tile !== null && ($tile['mode'] ?? '') === 'category') ? $categorySlug : null;

        // 1. Точный модельный запрос (например "adidas samba", "apple ipad air", "nike air max 90")
        $words = array_values(array_filter(preg_split('/\s+/u', $modelQuery) ?: []));
        $isDistinctModelQuery = count($words) >= 2
            && ($brand === null || mb_strtolower($modelQuery) !== mb_strtolower($brand))
            && $categoryMatcher->resolve($modelQuery) === null;

        if ($isDistinctModelQuery) {
            if ($gender !== null && $this->countMeiliHits($modelQuery, ['gender = ' . $this->meiliQuote($gender)]) > 0) {
                return $this->localizedRoute('product-search', [
                    'q' => $modelQuery,
                    'genders' => [$gender],
                    'photo' => 1,
                ]);
            }

            if ($this->countMeiliHits($modelQuery, []) > 0) {
                return $this->localizedRoute('product-search', [
                    'q' => $modelQuery,
                    'photo' => 1,
                ]);
            }
        }

        // 2. Бренд + категория (с каскадным ослаблением цвета и пола)
        if ($matchedBrands !== [] && $validCategorySlug !== null) {
            $catClause = 'category_slugs = ' . $this->meiliQuote($validCategorySlug);
            $activeBrands = $this->filterActiveBrandsInCategory($matchedBrands, $catClause);

            if ($activeBrands !== []) {
                $brandClause = $this->meiliIn('brand', $activeBrands);
                $matchedColor = $color !== null
                    ? $this->resolveMeiliColorValue($color, [$catClause, $brandClause])
                    : null;

                if ($gender !== null && $matchedColor !== null) {
                    if ($this->countMeiliHits('', [$catClause, $brandClause, 'gender = ' . $this->meiliQuote($gender), 'colour_main = ' . $this->meiliQuote($matchedColor)]) > 0) {
                        return $this->localizedRoute('feed-category.show', [
                            'slug' => $validCategorySlug,
                            'brands' => $activeBrands,
                            'genders' => [$gender],
                            'color' => $matchedColor,
                            'photo' => 1,
                        ]);
                    }
                }

                if ($gender !== null && $this->countMeiliHits('', [$catClause, $brandClause, 'gender = ' . $this->meiliQuote($gender)]) > 0) {
                    return $this->localizedRoute('feed-category.show', [
                        'slug' => $validCategorySlug,
                        'brands' => $activeBrands,
                        'genders' => [$gender],
                        'photo' => 1,
                    ]);
                }

                if ($matchedColor !== null && $this->countMeiliHits('', [$catClause, $brandClause, 'colour_main = ' . $this->meiliQuote($matchedColor)]) > 0) {
                    return $this->localizedRoute('feed-category.show', [
                        'slug' => $validCategorySlug,
                        'brands' => $activeBrands,
                        'color' => $matchedColor,
                        'photo' => 1,
                    ]);
                }

                return $this->localizedRoute('feed-category.show', [
                    'slug' => $validCategorySlug,
                    'brands' => $activeBrands,
                    'photo' => 1,
                ]);
            }
        }

        // 3. Только бренд (если модель и точная категория не совпали)
        if ($matchedBrands !== []) {
            $brandClause = $this->meiliIn('brand', $matchedBrands);
            if ($gender !== null && $this->countMeiliHits('', [$brandClause, 'gender = ' . $this->meiliQuote($gender)]) > 0) {
                return $this->localizedRoute('product-search', [
                    'q' => $brand,
                    'genders' => [$gender],
                    'photo' => 1,
                ]);
            }

            if ($this->countMeiliHits('', [$brandClause]) > 0) {
                return $this->localizedRoute('product-search', [
                    'q' => $brand,
                    'photo' => 1,
                ]);
            }
        }

        // 4. Категория + пол + цвет (для вещей без явного логотипа)
        if ($validCategorySlug !== null) {
            $catClause = 'category_slugs = ' . $this->meiliQuote($validCategorySlug);
            $matchedColor = $color !== null ? $this->resolveMeiliColorValue($color, [$catClause]) : null;

            if ($gender !== null && $matchedColor !== null) {
                if ($this->countMeiliHits('', [$catClause, 'gender = ' . $this->meiliQuote($gender), 'colour_main = ' . $this->meiliQuote($matchedColor)]) > 0) {
                    return $this->localizedRoute('feed-category.show', [
                        'slug' => $validCategorySlug,
                        'genders' => [$gender],
                        'color' => $matchedColor,
                        'photo' => 1,
                    ]);
                }
            }

            if ($matchedColor !== null && $this->countMeiliHits('', [$catClause, 'colour_main = ' . $this->meiliQuote($matchedColor)]) > 0) {
                return $this->localizedRoute('feed-category.show', [
                    'slug' => $validCategorySlug,
                    'color' => $matchedColor,
                    'photo' => 1,
                ]);
            }

            if ($gender !== null && $this->countMeiliHits('', [$catClause, 'gender = ' . $this->meiliQuote($gender)]) > 0) {
                return $this->localizedRoute('feed-category.show', [
                    'slug' => $validCategorySlug,
                    'genders' => [$gender],
                    'photo' => 1,
                ]);
            }

            if ($this->countMeiliHits('', [$catClause]) > 0) {
                return $this->localizedRoute('feed-category.show', [
                    'slug' => $validCategorySlug,
                    'photo' => 1,
                ]);
            }
        }

        // 5. Текстовый фоллбек (полный или укороченный запрос)
        if ($modelQuery !== '') {
            if ($this->countMeiliHits($modelQuery, []) > 0) {
                return $this->localizedRoute('product-search', [
                    'q' => $modelQuery,
                    'photo' => 1,
                ]);
            }

            if (count($words) > 2) {
                $shorter = implode(' ', array_slice($words, 0, 2));
                if ($this->countMeiliHits($shorter, []) > 0) {
                    return $this->localizedRoute('product-search', [
                        'q' => $shorter,
                        'photo' => 1,
                    ]);
                }
            }
        }

        return null;
    }

    /**
     * @param  string[]  $matchedBrands
     * @return string[]
     */
    private function filterActiveBrandsInCategory(array $matchedBrands, string $catClause): array
    {
        try {
            $index = $this->meiliIndex();
            if ($index === null) {
                return $matchedBrands;
            }

            $res = $index->search('', [
                'filter' => implode(' AND ', [
                    'is_active = true',
                    'in_stock = true',
                    $catClause,
                    $this->meiliIn('brand', $matchedBrands),
                ]),
                'facets' => ['brand'],
                'limit' => 0,
            ]);

            $dist = is_object($res) && method_exists($res, 'getFacetDistribution')
                ? (array) ($res->getFacetDistribution()['brand'] ?? [])
                : (array) ($res['facetDistribution']['brand'] ?? []);

            $active = [];
            foreach ($matchedBrands as $b) {
                if (($dist[$b] ?? 0) > 0) {
                    $active[] = $b;
                }
            }

            return $active;
        } catch (\Throwable) {
            return $matchedBrands;
        }
    }

    /**
     * Подбирает точное значение colour_main из фасета Meilisearch (включая синонимы Gray/Grey).
     *
     * @param  string[]  $extraClauses
     */
    private function resolveMeiliColorValue(string $color, array $extraClauses): ?string
    {
        try {
            $index = $this->meiliIndex();
            if ($index === null) {
                return null;
            }

            $res = $index->search('', [
                'filter' => implode(' AND ', array_merge(['is_active = true', 'in_stock = true'], $extraClauses)),
                'facets' => ['colour_main'],
                'limit' => 0,
            ]);

            $dist = is_object($res) && method_exists($res, 'getFacetDistribution')
                ? (array) ($res->getFacetDistribution()['colour_main'] ?? [])
                : (array) ($res['facetDistribution']['colour_main'] ?? []);

            $target = mb_strtolower($color);
            $aliases = [$target];
            if ($target === 'grey') {
                $aliases[] = 'gray';
            } elseif ($target === 'gray') {
                $aliases[] = 'grey';
            } elseif ($target === 'navy') {
                $aliases[] = 'dark blue';
                $aliases[] = 'blue';
            }

            foreach ($aliases as $alias) {
                foreach ($dist as $facetVal => $cnt) {
                    if ($cnt > 0 && mb_strtolower(trim((string) $facetVal)) === $alias) {
                        return (string) $facetVal;
                    }
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  string[]  $extraClauses
     */
    private function countMeiliHits(string $query, array $extraClauses): int
    {
        try {
            $index = $this->meiliIndex();
            if ($index === null) {
                return 1;
            }

            $prepared = $query !== '' && class_exists(ScriptTransliterator::class)
                ? ScriptTransliterator::prepareForMeilisearch($query)
                : $query;

            $res = $index->search($prepared, [
                'filter' => implode(' AND ', array_merge(['is_active = true', 'in_stock = true'], $extraClauses)),
                'matchingStrategy' => 'all',
                'limit' => 0,
            ]);

            return is_object($res)
                ? (int) ($res->getEstimatedTotalHits() ?? 0)
                : (int) ($res['estimatedTotalHits'] ?? $res['totalHits'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
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
     * @param  array<string, mixed>  $params
     */
    private function localizedRoute(string $baseName, array $params): string
    {
        $locale = app()->getLocale();
        $name = in_array($locale, ['ru', 'ka'], true) && Route::has("{$locale}.{$baseName}")
            ? "{$locale}.{$baseName}"
            : $baseName;

        return route($name, $params);
    }

    private function meiliQuote(string $val): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $val) . '"';
    }

    /**
     * @param  string[]  $values
     */
    private function meiliIn(string $field, array $values): string
    {
        $quoted = array_map(fn ($v) => $this->meiliQuote((string) $v), array_values($values));

        return $field . ' IN [' . implode(', ', $quoted) . ']';
    }
}