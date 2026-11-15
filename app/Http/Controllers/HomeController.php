<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, FeedCategoryTiles $categoryTiles, FeedEntityLocalizer $localizer): View
    {
        $locale = app()->getLocale();

        // 1. Мультибрендовые рекомендации (чередуем бренды и магазины, исключая Home-мусор)
        $rawPopular = $this->fetchBalancedProducts(onlyDiscounts: false, limit: 8);

        $popularProducts = collect(
            $localizer->localizeCardTitles(
                $rawPopular->map(fn (FeedProduct $p) => $p->toCard())->all(),
                $locale
            )
        );

        // 2. Лучшие скидки по всем брендам
        $rawDiscounts = $this->fetchBalancedProducts(onlyDiscounts: true, limit: 8);

        $discountProducts = collect(
            $localizer->localizeCardTitles(
                $rawDiscounts->map(fn (FeedProduct $p) => $p->toCard())->all(),
                $locale
            )
        );

        // 3. Популярные бренды из каталога (ведут на мгновенный поиск по бренду) + флагманские магазины Греции
        $brands = Cache::remember('home.top_brands.v1', now()->addHours(2), function () {
            $dbBrands = FeedProduct::query()
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

        // 4. Сетка категорий с картинками (из config/home_categories.php)
        $tiles = $categoryTiles->get();

        return view('public.home', compact('popularProducts', 'discountProducts', 'brands', 'tiles'));
    }

    /**
     * Выбирает товары из разных магазинов и брендов, чередуя их по принципу round-robin,
     * чтобы ни один бренд не занимал всю витрину.
     */
    private function fetchBalancedProducts(bool $onlyDiscounts, int $limit): Collection
    {
        $excludedTops = array_filter((array) config('home_categories.excluded_tops', ['Home']));

        $base = FeedProduct::query()
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
                 ->orderByDesc('discount_percent');
        } else {
            $base->orderByRaw('rating IS NULL')
                 ->orderByDesc('rating')
                 ->orderByDesc('id');
        }

        // Берём пул из разных фидов и группируем по бренду для равномерного чередования
        $pool = collect();
        foreach (['notos', 'adidas'] as $feed) {
            $chunk = (clone $base)
                ->where('feed', $feed)
                ->limit(36)
                ->get()
                ->unique(fn (FeedProduct $p) => $p->model_code ?: $p->sku);
            $pool = $pool->merge($chunk);
        }

        $byBrand = $pool->groupBy(fn (FeedProduct $p) => mb_strtolower((string) ($p->brand ?: $p->feed)));
        $balanced = collect();
        $usedModels = [];

        while ($balanced->count() < $limit && $byBrand->isNotEmpty()) {
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
                    $balanced->push($item);
                }

                if ($items->isEmpty()) {
                    $byBrand->forget($brandKey);
                } else {
                    $byBrand->put($brandKey, $items);
                }

                if ($balanced->count() >= $limit) {
                    break;
                }
            }
        }

        return $balanced;
    }
}