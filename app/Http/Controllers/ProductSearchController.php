<?php

namespace App\Http\Controllers;

use App\Services\CategoryMatcher;
use App\Services\FeedCategoryTiles;
use App\Services\ProductAutocompleteService;
use App\Services\SmartProductSearchManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

class ProductSearchController extends Controller
{
    public function __construct(protected SmartProductSearchManager $searchManager)
    {
    }

    public function index(Request $request, CategoryMatcher $categories, FeedCategoryTiles $tiles): View|RedirectResponse|JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));

        $selectedBrands = array_values(array_filter((array) $request->input('brands', [])));
        $selectedGenders = array_values(array_filter((array) $request->input('genders', [])));
        $selectedSize = trim((string) $request->input('size', ''));
        $selectedColor = trim((string) $request->input('color', ''));
        $onlyDiscount = $request->boolean('discount');
        $minPrice = $request->filled('min_price') ? (float) $request->input('min_price') : null;
        $maxPrice = $request->filled('max_price') ? (float) $request->input('max_price') : null;
        $sort = in_array($request->input('sort'), ['relevance', 'price_asc', 'price_desc', 'discount_desc'], true)
            ? $request->input('sort')
            : 'relevance';

        $hasAnyExtraFilter = $minPrice !== null || $maxPrice !== null
            || $selectedBrands !== [] || $selectedGenders !== []
            || $selectedSize !== '' || $selectedColor !== '' || $onlyDiscount;

        $products = [];
        $pagination = ['total' => 0, 'per_page' => 24, 'current_page' => 1, 'last_page' => 1];

        if ($query !== '') {
            $query = mb_substr($query, 0, 200);

            if ($page === 1 && !$request->expectsJson()) {
                $this->trackQueryHit($query);
            }

            if (!$hasAnyExtraFilter && !$request->expectsJson() && ($slug = $categories->resolve($query))) {
                return redirect()->route('feed-category.show', array_filter([
                    'slug' => $slug,
                    'sort' => in_array($sort, ['price_asc', 'price_desc'], true) ? $sort : null,
                ]));
            }

            $searchFilters = array_filter([
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => in_array($sort, ['relevance', 'price_asc', 'price_desc'], true) ? $sort : 'relevance',
            ], fn ($v) => $v !== null && $v !== '');

            $searchResult = $this->searchManager->search($query, app()->getLocale(), $searchFilters, $page);
            $products = (array) ($searchResult['products'] ?? []);
            $pagination = (array) ($searchResult['pagination'] ?? $pagination);
        }

        $facets = $tiles->buildFacetsForSearch($query);

        if ($selectedBrands !== []) {
            $products = array_values(array_filter($products, fn ($p) => in_array($p['brand'] ?? '', $selectedBrands, true)));
        }
        if ($selectedColor !== '') {
            $products = array_values(array_filter($products, function ($p) use ($selectedColor) {
                $main = trim(explode('/', (string) ($p['colour'] ?? ''))[0]);
                return mb_strtolower($main) === mb_strtolower($selectedColor);
            }));
        }
        if ($onlyDiscount) {
            $products = array_values(array_filter($products, fn ($p) => (int) ($p['discount_percent'] ?? 0) > 0));
        }
        if ($sort === 'discount_desc') {
            usort($products, fn ($a, $b) => (int) ($b['discount_percent'] ?? 0) <=> (int) ($a['discount_percent'] ?? 0));
        }

        if ($hasAnyExtraFilter && $query !== '') {
            $pagination['total'] = count($products);
        }

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

        // Если запрос — бренд, показываем только категории, где есть товары этого бренда,
        // со ссылкой сразу на категорию с фильтром ?brands[]=Brand
        $sourceTiles = $query !== ''
            ? $tiles->getForBrand($query)
            : $allCategories->take(12);

        $subcategories = $sourceTiles->values()->map(fn ($cat, $idx) => [
            'index' => $idx,
            'label' => $cat['label'],
            'count' => $cat['count'] ?? 0,
            'image' => file_exists(public_path('images/categories/' . $cat['img']))
                ? asset('images/categories/' . $cat['img'])
                : null,
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
            'facets' => $facets,
            'products' => $products,
            'pagination' => $pagination,
            'filters' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'brands' => $selectedBrands,
                'genders' => $selectedGenders,
                'size' => $selectedSize,
                'color' => $selectedColor,
                'discount' => $onlyDiscount,
                'sort' => $sort,
            ],
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