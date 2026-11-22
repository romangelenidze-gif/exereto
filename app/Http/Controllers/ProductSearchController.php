<?php

namespace App\Http\Controllers;

use App\Services\CategoryMatcher;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use App\Services\ProductAutocompleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

class ProductSearchController extends Controller
{
    public function index(
        Request $request,
        CategoryMatcher $categories,
        FeedCategoryTiles $tiles,
        FeedEntityLocalizer $localizer
    ): View|RedirectResponse|JsonResponse {
        $query = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) config('feeds.search_limit', 24);

        $filters = [
            'min_price' => $request->filled('min_price') ? (float) $request->input('min_price') : null,
            'max_price' => $request->filled('max_price') ? (float) $request->input('max_price') : null,
            'brands' => array_values(array_filter((array) $request->input('brands', []))),
            'genders' => array_values(array_filter((array) $request->input('genders', []))),
            'size' => trim((string) $request->input('size', '')),
            'color' => trim((string) $request->input('color', '')),
            'discount' => $request->boolean('discount'),
            'sort' => in_array($request->input('sort'), ['relevance', 'price_asc', 'price_desc', 'discount_desc'], true)
                ? $request->input('sort')
                : 'relevance',
        ];

        $hasAnyExtraFilter = $filters['min_price'] !== null || $filters['max_price'] !== null
            || $filters['brands'] !== [] || $filters['genders'] !== []
            || $filters['size'] !== '' || $filters['color'] !== '' || $filters['discount'];

        $products = [];
        $total = 0;

        if ($query !== '') {
            $query = mb_substr($query, 0, 200);

            if ($page === 1 && !$request->expectsJson()) {
                $this->trackQueryHit($query);
            }

            if (!$hasAnyExtraFilter && !$request->expectsJson() && ($slug = $categories->resolve($query))) {
                return redirect()->route('feed-category.show', array_filter([
                    'slug' => $slug,
                    'sort' => in_array($filters['sort'], ['price_asc', 'price_desc'], true) ? $filters['sort'] : null,
                ]));
            }

            $base = $tiles->searchBaseQuery($query);
            $filteredBase = $tiles->applyUserFilters($base, $filters);
            $cachePrefix = 'search:' . md5(mb_strtolower($query) . ':' . json_encode($filters));

            [$rawCards, $total] = $tiles->paginateGroupedCards(
                $filteredBase,
                $filters['sort'],
                $page,
                $perPage,
                $cachePrefix
            );

            $products = $localizer->localizeCardTitles($rawCards, app()->getLocale());
        }

        $pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];

        if ($request->expectsJson()) {
            $html = '';
            foreach ($products as $product) {
                $html .= view('components.public-ui.product-card', [
                    'product' => $product,
                    'isFirst' => false,
                ])->render();
            }

            return response()->json([
                'html' => $html,
                'pagination' => $pagination,
            ]);
        }

        $allCategories = $tiles->get();

        // Плитки категорий бренда с реальными фото товаров этого бренда и учётом выбранного пола
        $sourceTiles = $query !== ''
            ? $tiles->getForBrand($query, $filters['genders'])
            : $allCategories->take(12);

        $subcategories = $sourceTiles->values()->map(fn ($cat, $idx) => [
            'index' => $idx,
            'label' => $cat['label'],
            'count' => $cat['count'] ?? 0,
            'image' => !empty($cat['image'])
                ? $cat['image']
                : (!empty($cat['img']) && file_exists(public_path('images/categories/' . $cat['img']))
                    ? asset('images/categories/' . $cat['img'])
                    : null),
            'url' => $cat['url'],
        ])->all();

        $pageTitle = $query !== '' ? $query : __('Каталог и поиск товаров');

        return view('public.catalog', [
            'isSearchMode' => true,
            'searchQuery' => $query,
            'formAction' => route('product-search'),
            'resetUrl' => $query !== '' ? route('product-search', ['q' => $query]) : route('product-search'),
            'pageTitle' => $pageTitle,
            'allCategories' => $allCategories,
            'subcategories' => $subcategories,
            'activeSubIndex' => null,
            'facets' => $tiles->buildFacetsForSearch($query, $filters),
            'products' => $products,
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    public function autocomplete(Request $request, ProductAutocompleteService $autocompleteService): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $suggestions = $autocompleteService->getSuggestions($query, app()->getLocale());

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    private function trackQueryHit(string $query): void
    {
        try {
            $norm = mb_strtolower(trim($query));
            if (mb_strlen($norm) >= 2 && mb_strlen($norm) <= 100) {
                Redis::hincrby('autocomplete_hits_counter', $norm, 1);
            }
        } catch (\Throwable) {
        }
    }
}