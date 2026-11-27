<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const RECOMMENDED_LIMIT = 12;

    public function index(Request $request, FeedCategoryTiles $categoryTiles, FeedEntityLocalizer $localizer): View
    {
        $locale = app()->getLocale();
        $tiles = $categoryTiles->get();

        // 1. Единственный товарный блок главной — Рекомендуемые предложения (12 товаров = 2 ряда по 6)
        $recommendedBlock = Cache::remember(
            "home.recommended_block.v3.{$locale}",
            now()->addHours(48),
            function () use ($localizer, $locale, $tiles) {
                $target = $this->resolveBlockTarget($tiles, ['clothing', 'sales-offers'], 'Guess');

                $rawCards = Cache::remember(
                    'home.recommended_block.raw.v3',
                    now()->addHours(48),
                    fn () => $this->fetchBlockCards(
                        ['modivo', 'notos', 'stradivarius', 'markakis', 'cosselie', 'notino', 'samsonite', 'epapoutsia', 'adidas'],
                        self::RECOMMENDED_LIMIT
                    )
                );

                if ($rawCards === []) {
                    return null;
                }

                $localized = array_values(array_map(
                    fn (array $c) => $this->localizeCardUrl($c),
                    $localizer->localizeCardTitles($rawCards, $locale)
                ));

                return [
                    'title' => __('Рекомендуемые предложения'),
                    'subtitle' => __('Подборка оригинальной одежды, сумок и аксессуаров от мировых брендов'),
                    'cta' => __('Все предложения'),
                    'url' => $target['url'],
                    'count' => (int) $target['count'],
                    'products' => $localized,
                ];
            }
        );

        // 2. Полный индекс активных брендов (для шторки A–Z и счётчика в Graphite Aura)
        $allBrandsIndex = Cache::remember(
            'home.all_brands_index.v1',
            now()->addHours(48),
            fn () => $this->fetchAllBrandsIndex()
        );

        $totalBrandsCount = count($allBrandsIndex);

        $maxCatalogDiscount = 70;
        if ($allBrandsIndex !== []) {
            $maxDisc = max(array_column($allBrandsIndex, 'd'));
            if ($maxDisc >= 20) {
                $maxCatalogDiscount = min(80, (int) (floor($maxDisc / 5) * 5));
            }
        }

        // 3. Пилюли популярных брендов (ровно 8 брендов + кнопка "...")
        $popularBrands = $this->buildPopularBrandPills($categoryTiles, $allBrandsIndex);

        // 4. Первые 8 категорий для горизонтальной плитки на главной
        $homeCategories = array_slice(is_array($tiles) ? $tiles : iterator_to_array($tiles), 0, 8);

        return view('public.home', compact(
            'recommendedBlock',
            'popularBrands',
            'allBrandsIndex',
            'totalBrandsCount',
            'maxCatalogDiscount',
            'homeCategories',
            'tiles'
        ));
    }

    /**
     * Все активные бренды с количеством товаров и максимальной скидкой за 1 агрегационный запрос без гидрации моделей.
     *
     * @return array<int, array{n: string, c: int, d: int}>
     */
    private function fetchAllBrandsIndex(): array
    {
        $rows = DB::table('feed_products')
            ->where('is_active', true)
            ->where('in_stock', true)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->selectRaw('brand, COUNT(*) as cnt, MAX(discount_percent) as max_disc')
            ->groupBy('brand')
            ->orderByDesc('cnt')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->brand);
            $cnt = (int) $row->cnt;
            if ($name === '' || $cnt <= 0) {
                continue;
            }

            $out[] = [
                'n' => $name,
                'c' => $cnt,
                'd' => (int) ($row->max_disc ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Формирует список из 8 топовых брендов для пилюль, проверяя их наличие в каталоге.
     *
     * @param  array<int, array{n: string, c: int, d: int}>  $allBrandsIndex
     * @return array<int, array{name: string, url: string}>
     */
    private function buildPopularBrandPills(FeedCategoryTiles $categoryTiles, array $allBrandsIndex): array
    {
        $curated = [
            'adidas',
            'New Balance',
            'Guess',
            'Tommy Hilfiger',
            'Calvin Klein',
            'Stradivarius',
            'BOSS',
            'Lacoste',
            'Puma',
            'Polo Ralph Lauren',
            'Vans',
            'Geox',
        ];

        $availableLower = [];
        foreach ($allBrandsIndex as $b) {
            $availableLower[mb_strtolower($b['n'])] = true;
        }

        $pills = [];
        foreach ($curated as $brandName) {
            if (count($pills) >= 8) {
                break;
            }

            $family = $categoryTiles->resolveMatchingBrands($brandName);
            if ($family === []) {
                $family = [$brandName];
            }

            $exists = false;
            foreach ($family as $member) {
                if (isset($availableLower[mb_strtolower($member)])) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                $pills[] = [
                    'name' => $brandName,
                    'url' => route('product-search', ['q' => $brandName]),
                ];
            }
        }

        return $pills;
    }

    /**
     * @param  iterable<int, mixed>  $tiles
     * @param  string[]  $candidateSlugs
     * @return array{url: string, count: int}
     */
    private function resolveBlockTarget(iterable $tiles, array $candidateSlugs, string $fallbackQuery): array
    {
        $available = [];
        foreach ($tiles as $tile) {
            $slug = is_array($tile) ? ($tile['slug'] ?? null) : ($tile->slug ?? null);
            $count = is_array($tile) ? (int) ($tile['count'] ?? 0) : (int) ($tile->count ?? 0);
            if (!empty($slug)) {
                $available[(string) $slug] = $count;
            }
        }

        foreach ($candidateSlugs as $slug) {
            if (isset($available[$slug])) {
                return [
                    'url' => route('feed-category.show', ['slug' => $slug]),
                    'count' => $available[$slug],
                ];
            }
        }

        return [
            'url' => route('product-search', ['q' => $fallbackQuery]),
            'count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function localizeCardUrl(array $card): array
    {
        if (!empty($card['feed']) && !empty($card['sku'])) {
            $card['url'] = route('feed-product.show', [
                'feed' => $card['feed'],
                'sku' => $card['sku'],
            ]);
        }

        return $card;
    }

    /**
     * Двухшаговая выборка (сначала только id, затем один PK-запрос), чтобы не тащить широкие поля в sort-буфер MariaDB.
     *
     * @param  string[]  $feeds
     * @return array<int, array<string, mixed>>
     */
    private function fetchBlockCards(array $feeds, int $limit): array
    {
        $idsByFeed = [];
        $allIds = [];

        foreach ($feeds as $feed) {
            $ids = DB::table('feed_products')
                ->where('feed', $feed)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereNotNull('image_url')
                ->where('image_url', '!=', '')
                ->orderByDesc('priority_score')
                ->orderByDesc('id')
                ->limit(24)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $idsByFeed[$feed] = $ids;
                foreach ($ids as $id) {
                    $allIds[] = $id;
                }
            }
        }

        if ($allIds === []) {
            return [];
        }

        $modelsById = FeedProduct::query()
            ->whereIn('id', $allIds)
            ->get()
            ->keyBy('id');

        $pool = collect();
        foreach ($idsByFeed as $ids) {
            $chunk = collect($ids)
                ->map(fn ($id) => $modelsById->get($id))
                ->filter()
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