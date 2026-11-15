<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $filterSignature = md5(json_encode([
            $slug, $subIndex, $selectedBrands, $selectedGenders,
            $selectedSize, $selectedColor, $onlyDiscount, $minPrice, $maxPrice,
        ]));

        $pageCacheKey = "cat_page:v2:{$filterSignature}:{$sort}:p{$page}";

        [$rawCards, $total] = Cache::remember($pageCacheKey, now()->addHours(2), function () use (
            $tiles, $tile, $subIndex, $minPrice, $maxPrice, $selectedBrands,
            $selectedGenders, $selectedSize, $selectedColor, $onlyDiscount,
            $sort, $page, $perPage, $filterSignature
        ) {
            $base = $tiles->productsQuery($tile);

            if ($subIndex !== null && isset($tile['subcategories'][$subIndex])) {
                $tiles->applyPublicFilter($base, (array) ($tile['subcategories'][$subIndex]['where'] ?? []));
            }

            if ($minPrice !== null && $minPrice > 0) {
                $base->where('price', '>=', $minPrice);
            }
            if ($maxPrice !== null && $maxPrice > 0) {
                $base->where('price', '<=', $maxPrice);
            }
            if ($selectedBrands !== []) {
                $base->whereIn('brand', $selectedBrands);
            }
            if ($selectedGenders !== []) {
                $base->whereIn('gender', $selectedGenders);
            }
            if ($selectedSize !== '') {
                $base->where('sizes', 'like', '%"' . addslashes($selectedSize) . '"%');
            }
            if ($selectedColor !== '') {
                $base->whereRaw("TRIM(SUBSTRING_INDEX(colour, '/', 1)) = ?", [$selectedColor]);
            }
            if ($onlyDiscount) {
                $base->where('discount_percent', '>', 0);
            }

            $grouped = (clone $base)
                ->selectRaw("COALESCE(NULLIF(model_code, ''), sku) as group_key, MIN(id) as primary_id, MIN(price) as min_price, MAX(rating) as max_rating, MAX(discount_percent) as max_discount, COUNT(DISTINCT feed) as stores_count, COUNT(*) as colorways_count")
                ->groupBy('group_key');

            $total = Cache::remember("cat_total:v2:{$filterSignature}", now()->addHours(4), function () use ($grouped) {
                return DB::query()->fromSub($grouped, 'g')->count();
            });

            if ($sort === 'price_asc') {
                $grouped->orderBy('min_price');
            } elseif ($sort === 'price_desc') {
                $grouped->orderByDesc('min_price');
            } elseif ($sort === 'discount_desc') {
                $grouped->orderByDesc('max_discount')->orderBy('min_price');
            } else {
                $grouped->orderByRaw('COALESCE(MAX(rating), -1) DESC')
                        ->orderByDesc('max_discount')
                        ->orderBy('primary_id');
            }

            $rows = $grouped
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            $primaryIds = $rows->pluck('primary_id')->all();
            $metaMap = $rows->keyBy('primary_id');

            $productsMap = $primaryIds !== []
                ? FeedProduct::query()->whereIn('id', $primaryIds)->get()->keyBy('id')
                : collect();

            $cards = [];
            foreach ($primaryIds as $id) {
                /** @var FeedProduct|null $p */
                $p = $productsMap->get($id);
                if (!$p) {
                    continue;
                }

                $meta = $metaMap->get($id);
                $bestPrice = (float) ($meta->min_price ?? $p->price);
                $storesCount = (int) ($meta->stores_count ?? 1);
                $store = (string) config("feeds.feeds.{$p->feed}.store_name", $p->brand ?: $p->feed);
                $formatted = '€' . number_format($bestPrice, 2, '.', ' ');

                $cards[] = $p->toCard() + [
                    'price' => $bestPrice,
                    'price_amount' => $bestPrice,
                    'original_title' => $p->name,
                    'merchant' => $storesCount > 1 ? ($storesCount . ' ' . __('магазина')) : $store,
                    'price_from' => $bestPrice,
                    'price_formatted' => $formatted,
                    'price_note' => $formatted,
                    'formatted_price' => $formatted,
                    'offers_count' => $storesCount,
                    'colorways_count' => (int) ($meta->colorways_count ?? 1),
                ];
            }

            return [$cards, $total];
        });

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
            'facets' => $tiles->buildFacets($tile),
            'products' => $cards,
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
}