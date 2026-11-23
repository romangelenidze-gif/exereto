<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Количество брендов рядом с карточкой A–Z: 5 (1 ряд из 6) или 11 (2 ряда по 6) */
    private const BRAND_SHOWCASE_LIMIT = 5;

    public function index(Request $request, FeedCategoryTiles $categoryTiles, FeedEntityLocalizer $localizer): View
    {
        $locale = app()->getLocale();
        $tiles = $categoryTiles->get();

        // 1. Тематические блоки главной страницы (в кэше строго чистые массивы — без объектов Collection)
        $blocks = Cache::remember(
            "home.thematic_blocks.v2.{$locale}",
            now()->addHours(30),
            function () use ($localizer, $locale, $tiles) {
                $definitions = $this->blockDefinitions($tiles);
                $rawBySlug = Cache::remember(
                    'home.thematic_blocks.raw.v2',
                    now()->addHours(30),
                    function () use ($definitions) {
                        $out = [];
                        foreach ($definitions as $def) {
                            $out[$def['slug']] = $this->fetchBlockCards(
                                $def['feeds'],
                                $def['gender'] ?? null,
                                (bool) ($def['only_discounts'] ?? false),
                                (int) ($def['limit'] ?? 8)
                            );
                        }

                        return $out;
                    }
                );

                $result = [];
                foreach ($definitions as $def) {
                    $rawCards = $rawBySlug[$def['slug']] ?? [];
                    if ($rawCards === []) {
                        continue;
                    }

                    $localized = array_values(array_map(
                        fn (array $c) => $this->localizeCardUrl($c),
                        $localizer->localizeCardTitles($rawCards, $locale)
                    ));

                    $result[$def['slug']] = [
                        'slug' => $def['slug'],
                        'title' => $def['title'],
                        'subtitle' => $def['subtitle'],
                        'cta' => $def['cta'],
                        'url' => $def['url'],
                        'count' => (int) ($def['count'] ?? 0),
                        'banner' => $def['banner'],
                        'gradient' => $def['gradient'],
                        'products' => $localized,
                    ];
                }

                return $result;
            }
        );

        // 2. Полный индекс всех активных брендов (для шторки A–Z и счётчиков, 1 запрос на 30 часов)
        $allBrandsIndex = Cache::remember(
            'home.all_brands_index.v1',
            now()->addHours(30),
            fn () => $this->fetchAllBrandsIndex()
        );

        // 3. Топ-витрины брендов (5 + карточка A–Z = 6 в ряд)
        $rawShowcases = Cache::remember(
            'home.brand_showcases.v1',
            now()->addHours(30),
            fn () => $this->fetchBrandShowcases($categoryTiles, $allBrandsIndex)
        );

        $brandShowcases = array_map(fn (array $b) => $b + [
            'url' => route('product-search', ['q' => $b['name']]),
        ], $rawShowcases);

        $totalBrandsCount = count($allBrandsIndex);

        $heroBlock = $blocks['fashion'] ?? null;
        $bottomBlocks = array_values(array_diff_key($blocks, ['fashion' => true]));

        return view('public.home', compact(
            'heroBlock',
            'bottomBlocks',
            'brandShowcases',
            'allBrandsIndex',
            'totalBrandsCount',
            'tiles'
        ));
    }

    /**
     * Загружает все активные бренды с количеством товаров и максимальной скидкой за 1 индексный запрос.
     *
     * @return array<int, array{n: string, c: int, d: int}>
     */
    private function fetchAllBrandsIndex(): array
    {
        $rows = FeedProduct::query()
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
     * Собирает карточки популярных брендов.
     * Если в public/images/brand-covers/{slug}.{webp,jpg,png} лежит ваше фото — включается full-bleed editorial режим.
     *
     * @param  array<int, array{n: string, c: int, d: int}>  $allBrandsIndex
     * @return array<int, array{slug: string, name: string, count: int, max_discount: int, image: string, has_custom_cover: bool}>
     */
    private function fetchBrandShowcases(FeedCategoryTiles $categoryTiles, array $allBrandsIndex): array
    {
        $curated = [
            'adidas',
            'Guess',
            'Tommy Hilfiger',
            'Calvin Klein',
            'New Balance',
            'Puma',
            'Stradivarius',
            'Polo Ralph Lauren',
            'Vans',
            'Geox',
            'LEGO',
            'BOSS',
        ];

        $statsByLower = [];
        foreach ($allBrandsIndex as $b) {
            $statsByLower[mb_strtolower($b['n'])] = $b;
        }

        $limit = self::BRAND_SHOWCASE_LIMIT;
        $primary = [];
        $fallback = [];

        foreach ($curated as $brandName) {
            if (count($primary) >= $limit) {
                break;
            }

            $slug = Str::slug($brandName);
            $family = $categoryTiles->resolveMatchingBrands($brandName);
            if ($family === []) {
                $family = [$brandName];
            }

            $totalCount = 0;
            $maxDiscount = 0;
            foreach ($family as $member) {
                $st = $statsByLower[mb_strtolower($member)] ?? null;
                if ($st !== null) {
                    $totalCount += $st['c'];
                    if ($st['d'] > $maxDiscount) {
                        $maxDiscount = $st['d'];
                    }
                }
            }

            if ($totalCount <= 0) {
                continue;
            }

            $cover = $this->resolveBrandCoverImage($slug, $family);
            if ($cover === null) {
                continue;
            }

            $item = [
                'slug' => $slug,
                'name' => $brandName,
                'count' => $totalCount,
                'max_discount' => $maxDiscount,
                'image' => $cover['url'],
                'has_custom_cover' => $cover['is_custom'],
            ];

            if ($totalCount >= 50) {
                $primary[] = $item;
            } else {
                $fallback[] = $item;
            }
        }

        while (count($primary) < $limit && $fallback !== []) {
            $primary[] = array_shift($fallback);
        }

        return array_slice($primary, 0, $limit);
    }

    /**
     * 1) Сначала ищет ваше подготовленное фото в public/images/brand-covers/{slug}.{webp,jpg,jpeg,png}.
     * 2) Если его ещё нет — берёт чистое фото товара для аккуратной светлой карточки-заглушки.
     *
     * @param  string[]  $brandFamily
     * @return array{url: string, is_custom: bool}|null
     */
    private function resolveBrandCoverImage(string $slug, array $brandFamily): ?array
    {
        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            $rel = "images/brand-covers/{$slug}.{$ext}";
            if (is_file(public_path($rel))) {
                return ['url' => '/' . $rel, 'is_custom' => true];
            }
        }

        $candidates = FeedProduct::query()
            ->where('is_active', true)
            ->where('in_stock', true)
            ->whereIn('brand', $brandFamily)
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->orderByDesc('priority_score')
            ->orderByDesc('price')
            ->limit(40)
            ->get(['name', 'category_path', 'price', 'image_url']);

        if ($candidates->isEmpty()) {
            return null;
        }

        $avoidWords = ['sock', 'underw', 'brief', 'boxer', 'bra', 'panty', 'slip', 'thong', 'keychain', 'wallet', 'belt', 'cap', 'beanie', 'glove', 'scarf', 'towel', 'case', 'cover', 'κάλτσ', 'εσώρουχ'];
        $preferWords = ['jacket', 'coat', 'blazer', 'dress', 'hoodie', 'sweatshirt', 'sweater', 'shirt', 't-shirt', 'sneaker', 'boot', 'bag', 'μπουφάν', 'φόρεμα', 'φούτερ'];

        $bestUrl = null;
        $bestScore = -999;

        foreach ($candidates as $idx => $cand) {
            $img = trim((string) $cand->image_url);
            if ($img === '') {
                continue;
            }

            $hay = mb_strtolower((string) $cand->name . ' ' . (string) $cand->category_path);
            $score = 40 - $idx;

            if ((float) $cand->price >= 70) {
                $score += 20;
            } elseif ((float) $cand->price >= 40) {
                $score += 10;
            }

            foreach ($preferWords as $pw) {
                if (str_contains($hay, $pw)) {
                    $score += 16;
                    break;
                }
            }

            foreach ($avoidWords as $aw) {
                if (str_contains($hay, $aw)) {
                    $score -= 45;
                    break;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestUrl = $img;
            }
        }

        $url = $bestUrl ?: trim((string) $candidates->first()->image_url);

        return $url !== '' ? ['url' => $url, 'is_custom' => false] : null;
    }

    /**
     * Конфигурация тематических блоков главной.
     *
     * @param  iterable<int, mixed>  $tiles
     * @return array<int, array<string, mixed>>
     */
    private function blockDefinitions(iterable $tiles): array
    {
        $fashion = $this->resolveBlockTarget($tiles, ['clothing', 'sales-offers'], 'Guess');
        $kids = $this->resolveBlockTarget($tiles, ['kids', 'kids-clothing', 'children'], 'Energiers');
        $shoes = $this->resolveBlockTarget($tiles, ['shoes', 'sneakers'], 'Sneakers');
        $toys = $this->resolveBlockTarget($tiles, ['toys', 'lego', 'kids-toys'], 'LEGO');
        $tech = $this->resolveBlockTarget($tiles, ['tech', 'electronics', 'gaming', 'gadgets'], 'Apple');

        return [
            [
                'slug' => 'fashion',
                'title' => __('Рекомендуемые предложения'),
                'subtitle' => __('Подборка оригинальной одежды, сумок и аксессуаров от мировых брендов'),
                'cta' => __('Смотреть все предложения'),
                'url' => $fashion['url'],
                'count' => $fashion['count'],
                'banner' => 'images/home-banners/fashion.webp',
                'gradient' => 'from-stone-900 via-zinc-800 to-stone-700',
                'feeds' => ['modivo', 'notos', 'stradivarius', 'cosselie', 'sephora'],
                'gender' => null,
                'only_discounts' => false,
                'limit' => 8,
            ],
            [
                'slug' => 'kids',
                'title' => __('Детская одежда и стиль'),
                'subtitle' => __('Качественные коллекции для детей и подростков из греческих магазинов'),
                'cta' => __('Все детские товары'),
                'url' => $kids['url'],
                'count' => $kids['count'],
                'banner' => 'images/home-banners/kids.webp',
                'gradient' => 'from-amber-800 via-orange-700 to-amber-600',
                'feeds' => ['energiers', 'adidas', 'modivo'],
                'gender' => 'kids',
                'only_discounts' => false,
                'limit' => 8,
            ],
            [
                'slug' => 'sneakers',
                'title' => __('Кроссовки и обувь'),
                'subtitle' => __('Новые релизы и хиты adidas, New Balance, Puma, Vans, Geox и других брендов'),
                'cta' => __('Вся обувь'),
                'url' => $shoes['url'],
                'count' => $shoes['count'],
                'banner' => 'images/home-banners/sneakers.webp',
                'gradient' => 'from-neutral-800 via-stone-700 to-neutral-600',
                'feeds' => ['epapoutsia', 'adidas'],
                'gender' => null,
                'only_discounts' => false,
                'limit' => 8,
            ],
            [
                'slug' => 'toys',
                'title' => __('Игрушки и конструкторы'),
                'subtitle' => __('LEGO, Playmobil, развивающие и STEM-наборы из официальных магазинов Греции'),
                'cta' => __('Все игрушки'),
                'url' => $toys['url'],
                'count' => $toys['count'],
                'banner' => 'images/home-banners/toys.webp',
                'gradient' => 'from-indigo-900 via-slate-800 to-blue-900',
                'feeds' => ['toysgr', 'moustakas'],
                'gender' => null,
                'only_discounts' => false,
                'limit' => 8,
            ],
            [
                'slug' => 'tech',
                'title' => __('Техника и гаджеты'),
                'subtitle' => __('Смартфоны, ноутбуки, планшеты, гейминг, аудио и дроны со склада Public'),
                'cta' => __('Вся техника'),
                'url' => $tech['url'],
                'count' => $tech['count'],
                'banner' => 'images/home-banners/tech.webp',
                'gradient' => 'from-slate-950 via-zinc-900 to-slate-800',
                'feeds' => ['public'],
                'gender' => null,
                'only_discounts' => false,
                'limit' => 8,
            ],
        ];
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
     * @param  string[]  $feeds
     * @return array<int, array<string, mixed>>
     */
    private function fetchBlockCards(array $feeds, ?string $gender, bool $onlyDiscounts, int $limit): array
    {
        $pool = collect();
        foreach ($feeds as $feed) {
            $q = FeedProduct::query()
                ->where('feed', $feed)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereNotNull('image_url')
                ->where('image_url', '!=', '');

            if ($gender !== null) {
                $q->where('gender', $gender);
            }

            if ($onlyDiscounts) {
                $q->where('discount_percent', '>=', 20)
                    ->orderByDesc('discount_percent');
            } else {
                $q->orderByDesc('priority_score')
                    ->orderByDesc('id');
            }

            $chunk = $q->limit(24)
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