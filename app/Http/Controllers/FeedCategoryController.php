<?php

namespace App\Http\Controllers;

use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedCategoryController extends Controller
{
    public function show(Request $request, string $slug, FeedCategoryTiles $tiles, FeedEntityLocalizer $localizer): View|JsonResponse
    {
        $tile = $tiles->tile($slug);
        if ($tile === null) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) config('feeds.search_limit', 24);

        $subIndex = $request->filled('sub') ? (int) $request->input('sub') : null;
        $filters = [
            'sub' => $subIndex,
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

        $base = $tiles->productsQuery($tile);
        if ($subIndex !== null && isset($tile['subcategories'][$subIndex])) {
            $tiles->applyPublicFilter($base, (array) ($tile['subcategories'][$subIndex]['where'] ?? []));
        }

        $filteredBase = $tiles->applyUserFilters($base, $filters);
        $cachePrefix = 'cat:' . $slug . ':' . md5(json_encode($filters));

        [$rawCards, $total] = $tiles->paginateGroupedCards(
            $filteredBase,
            $filters['sort'],
            $page,
            $perPage,
            $cachePrefix
        );

        $cards = $localizer->localizeCardTitles($rawCards, app()->getLocale());

        $pagination = [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];

        if ($request->expectsJson()) {
            $html = '';
            foreach ($cards as $product) {
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

        return view('public.catalog', [
            'isSearchMode' => false,
            'searchQuery' => '',
            'formAction' => route('feed-category.show', ['slug' => $tile['slug']]),
            'resetUrl' => route('feed-category.show', ['slug' => $tile['slug']]),
            'pageTitle' => __((string) $tile['label']),
            'allCategories' => $tiles->get(),
            'subcategories' => $tiles->buildSubcategories($tile),
            'activeSubIndex' => $subIndex,
            'facets' => $tiles->buildFacets($tile, $filters),
            'products' => $cards,
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }
}