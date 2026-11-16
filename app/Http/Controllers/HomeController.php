<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, FeedCategoryTiles $categoryTiles, FeedEntityLocalizer $localizer): View
    {
        $locale = app()->getLocale();

        // 1. Мультибрендовые рекомендации (кэшируем карточки в Redis на 60 мин)
        $rawPopularCards = Cache::remember(
            'home.popular_cards.v3',
            now()->addMinutes(60),
            fn () => $this->fetchBalancedCards(onlyDiscounts: false, limit: 12)
        );

        $popularProducts = collect($localizer->localizeCardTitles($rawPopularCards, $locale));

        // 2. Лучшие скидки по всем брендам (кэшируем карточки в Redis на 60 мин)
        $rawDiscountCards = Cache::remember(
            'home.discount_cards.v3',
            now()->addMinutes(60),
            fn () => $this->fetchBalancedCards(onlyDiscounts: true, limit: 12)
        );

        $discountProducts = collect($localizer->localizeCardTitles($rawDiscountCards, $locale));

        // 3. Популярные бренды из каталога
        $brands = Cache::remember('home.top_brands.v3', now()->addHours(6), function () {
            $activeFeeds = array_keys(array_filter(config('feeds.feeds', []), fn($f) => $f['enabled'] ?? true));

            $dbBrands = FeedProduct::query()
                ->whereIn('feed', $activeFeeds)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->selectRaw('brand, COUNT(*) as cnt')
                ->groupBy('brand')
                ->orderByDesc('cnt')
                ->limit(14)
                ->pluck('brand')
                ->all();

            $list = [];
            foreach ($dbBrands as $b) {
                $list[] = [
                    'name' => $b,
                    'url' => route('product-search', ['q' => $b]),
                    'external' => false,
                ];
            }

            return $list;
        });

        // 4. Сетка категорий с картинками
        $tiles = $categoryTiles->get();

        return view('public.home', compact('popularProducts', 'discountProducts', 'brands', 'tiles'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBalancedCards(bool $onlyDiscounts, int $limit): array
    {
        $excludedTops = array_filter((array) config('home_categories.excluded_tops', ['Home']));
        $activeFeeds = array_keys(array_filter(config('feeds.feeds', []), fn($f) => $f['enabled'] ?? true));

        $base = FeedProduct::query()
            ->whereIn('feed', $activeFeeds)
            ->where('is_active', true)
            ->where('in_stock', true)
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->when($excludedTops !== [], function ($q) use ($excludedTops) {
                $q->where(function ($sub) use ($excludedTops) {
                    $sub->whereNull('category_top')->orWhereNotIn('category_top', $excludedTops);
                });
            });

        if ($onlyDiscounts) {
            $base->where('discount_percent', '>=', 15)
                 ->orderByDesc('priority_score')
                 ->orderByDesc('discount_percent');
        } else {
            $base->orderByDesc('priority_score')
                 ->orderByDesc('rating')
                 ->orderByDesc('id');
        }

        $pool = collect();
        foreach ($activeFeeds as $feed) {
            $chunk = (clone $base)
                ->where('feed', $feed)
                ->limit(36)
                ->get()
                ->unique(fn (FeedProduct $p) => $p->model_code ?: $p->sku);
            $pool = $pool->merge($chunk);
        }

        $byBrand = $pool->groupBy(fn (FeedProduct $p) => mb_strtolower((string) ($p->brand ?: $p->feed)));
        $balanced = [];
        $usedModels = [];

        while (count($balanced) < $limit && $byBrand->isNotEmpty()) {
            foreach ($byBrand->keys()->all() as $brandKey) {
                $items = $byBrand->get($brandKey);
                if (!$items || $items->isEmpty()) {
                    $byBrand->forget($brandKey);
                    continue;
                }

                /** @var FeedProduct $item */
                $item = $items->shift();
                $dedupKey = mb_strtolower((string) ($item->model_code ?: $item->name));
                if (!isset($usedModels[$dedupKey])) {
                    $usedModels[$dedupKey] = true;
                    $balanced[] = $item->toCard();
                }

                if ($items->isEmpty()) {
                    $byBrand->forget($brandKey);
                } else {
                    $byBrand->put($brandKey, $items);
                }

                if (count($balanced) >= $limit) {
                    break;
                }
            }
        }

        return $balanced;
    }
}