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
        $tiles = $categoryTiles->get();

        // 1. Тематические блоки главной страницы (в кэше строго чистые массивы — без объектов Collection)
        $blocks = Cache::remember(
            "home.thematic_blocks.v2.{$locale}",
            now()->addMinutes(60),
            function () use ($localizer, $locale, $tiles) {
                $definitions = $this->blockDefinitions($tiles);
                $rawBySlug = Cache::remember(
                    'home.thematic_blocks.raw.v2',
                    now()->addMinutes(60),
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
                        'banner' => $def['banner'],
                        'gradient' => $def['gradient'],
                        'products' => $localized,
                    ];
                }

                return $result;
            }
        );

        // 2. Популярные бренды
        $curated = [
            'adidas', 'New Balance', 'Puma', 'Tommy Hilfiger', 'Calvin Klein',
            'Guess', 'Stradivarius', 'Polo Ralph Lauren', 'BOSS', 'Vans',
            'Reebok', 'Geox', 'Sephora Collection', 'Cosselie', 'LEGO', 'Apple',
        ];

        $brands = [];
        foreach ($curated as $b) {
            $brands[] = [
                'name' => $b,
                'url' => route('product-search', ['q' => $b]),
                'external' => false,
            ];
        }

        // Первый блок — над популярными брендами, остальные — под ними
        $heroBlock = $blocks['fashion'] ?? null;
        $bottomBlocks = array_values(array_diff_key($blocks, ['fashion' => true]));

        return view('public.home', compact('heroBlock', 'bottomBlocks', 'brands', 'tiles'));
    }

    /**
     * Конфигурация тематических блоков главной.
     * Баннеры кладутся в public/images/home-banners/{slug}.webp (пока файла нет — рендерится градиент).
     *
     * @param  iterable<int, mixed>  $tiles
     * @return array<int, array<string, mixed>>
     */
    private function blockDefinitions(iterable $tiles): array
    {
        return [
            [
                'slug' => 'fashion',
                'title' => __('Рекомендуемые предложения'),
                'subtitle' => __('Подборка оригинальной одежды, сумок и аксессуаров от мировых брендов'),
                'cta' => __('Все предложения'),
                'url' => $this->resolveBlockUrl($tiles, ['sales-offers', 'clothing', 'women', 'fashion'], 'Guess'),
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
                'url' => $this->resolveBlockUrl($tiles, ['kids', 'kids-clothing', 'children'], 'Energiers'),
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
                'url' => $this->resolveBlockUrl($tiles, ['sneakers', 'shoes', 'footwear'], 'Sneakers'),
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
                'url' => $this->resolveBlockUrl($tiles, ['toys', 'lego', 'kids-toys'], 'LEGO'),
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
                'url' => $this->resolveBlockUrl($tiles, ['tech', 'electronics', 'gaming', 'gadgets'], 'Apple'),
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
     */
    private function resolveBlockUrl(iterable $tiles, array $candidateSlugs, string $fallbackQuery): string
    {
        $available = [];
        foreach ($tiles as $tile) {
            $slug = is_array($tile) ? ($tile['slug'] ?? null) : ($tile->slug ?? null);
            if (!empty($slug)) {
                $available[(string) $slug] = true;
            }
        }

        foreach ($candidateSlugs as $slug) {
            if (isset($available[$slug])) {
                return route('feed-category.show', ['slug' => $slug]);
            }
        }

        return route('product-search', ['q' => $fallbackQuery]);
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