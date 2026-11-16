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
            'home.popular_cards.v4',
            now()->addMinutes(60),
            fn () => $this->fetchBalancedCards(onlyDiscounts: false, limit: 12)
        );

        $popularProducts = collect($localizer->localizeCardTitles($rawPopularCards, $locale));

        // 2. Лучшие скидки по всем брендам (кэшируем карточки в Redis на 60 мин)
        $rawDiscountCards = Cache::remember(
            'home.discount_cards.v4',
            now()->addMinutes(60),
            fn () => $this->fetchBalancedCards(onlyDiscounts: true, limit: 12)
        );

        $discountProducts = collect($localizer->localizeCardTitles($rawDiscountCards, $locale));

        // 3. Популярные бренды из каталога (курируемый топ + реальные лидеры из БД)
        $brands = Cache::remember('home.top_brands.v4', now()->addHours(6), function () {
            $curated = [
                'adidas', 'New Balance', 'Puma', 'Tommy Hilfiger', 'Calvin Klein',
                'Guess', 'Stradivarius', 'Polo Ralph Lauren', 'BOSS', 'Vans',
                'Reebok', 'Geox', 'Sephora Collection', 'Cosselie',
            ];

            $list = [];
            foreach ($curated as $b) {
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
        $showcaseFeeds = ['adidas', 'epapoutsia', 'modivo', 'stradivarius', 'sephora', 'notos', 'cosselie', 'energiers'];

        $pool = collect();
        foreach ($showcaseFeeds as $feed) {
            $q = FeedProduct::query()
                ->where('feed', $feed)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereNotNull('image_url')
                ->where('image_url', '!=', '');

            if ($onlyDiscounts) {
                $q->where('discount_percent', '>=', 20)
                  ->orderByDesc('discount_percent');
            } else {
                $q->orderByDesc('priority_score')
                  ->orderByDesc('id');
            }

            $chunk = $q->limit(20)
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