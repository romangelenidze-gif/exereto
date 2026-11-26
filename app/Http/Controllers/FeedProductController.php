<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\Feeds\FeedEntityLocalizer;
use App\Services\Feeds\FeedVariantGrouper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FeedProductController extends Controller
{
    public function show(Request $request, string $feed, string $sku, FeedEntityLocalizer $localizer): View|RedirectResponse
    {
        $product = FeedProduct::query()
            ->where('feed', $feed)
            ->where('sku', $sku)
            ->first();

        if ($product === null) {
            abort(404);
        }

        if (!$product->is_active) {
            // 1. Дубль по EAN, скрытый дедупликатором -> 301 на активного победителя
            if ($product->cascade_hidden && !empty($product->ean)) {
                $winner = FeedProduct::query()
                    ->where('ean', $product->ean)
                    ->where('is_active', true)
                    ->orderByDesc('in_stock')
                    ->orderByDesc('priority_score')
                    ->first(['feed', 'sku']);

                if ($winner && ($winner->feed !== $product->feed || $winner->sku !== $product->sku)) {
                    return redirect()->route('feed-product.show', [
                        'feed' => $winner->feed,
                        'sku' => $winner->sku,
                    ], 301);
                }
            }

            // 2. Нет даты последнего появления (синтетическая/тестовая запись) -> 404
            if (empty($product->last_seen_at)) {
                abort(404);
            }

            // 3. Пропал из фида более 60 дней назад -> 410 Gone
            if (Carbon::parse($product->last_seen_at)->lt(now()->subDays(60))) {
                abort(410);
            }
        }

        $isUnavailable = !$product->is_active || !$product->in_stock;
        $locale = app()->getLocale();

        $rawSiblings = Cache::remember(
            "product:variants:v3:{$feed}:" . ($product->model_code ?: $product->id),
            now()->addHours(4),
            fn () => $product->model_code
                ? FeedProduct::query()
                    ->where('feed', $feed)
                    ->where('model_code', $product->model_code)
                    ->where('is_active', true)
                    ->orderBy('price')
                    ->orderBy('colour')
                    ->get(['id', 'feed', 'sku', 'colour', 'variant_label', 'price', 'in_stock', 'image_url'])
                    ->toArray()
                : [$product->only(['id', 'feed', 'sku', 'colour', 'variant_label', 'price', 'in_stock', 'image_url'])]
        );

        [$colorways, $variantOptions, $variantAxisLabel] = $this->buildVariantSelectors($product, $rawSiblings);

        $rawRelated = Cache::remember(
            "product:related_raw:v3:{$feed}:{$product->category_top}:{$product->gender}:{$product->id}",
            now()->addHours(4),
            fn () => FeedProduct::query()
                ->where('feed', $feed)
                ->where('id', '!=', $product->id)
                ->when($product->model_code, fn ($q) => $q->where(function ($sub) use ($product) {
                    $sub->whereNull('model_code')->orWhere('model_code', '!=', $product->model_code);
                }))
                ->where('is_active', true)
                ->where('in_stock', true)
                ->when($product->category_top, fn ($q) => $q->where('category_top', $product->category_top))
                ->when($product->gender, fn ($q) => $q->where('gender', $product->gender))
                ->limit(36)
                ->get()
                ->unique(fn (FeedProduct $p) => $p->model_code ?: $p->sku)
                ->take(12)
                ->map(fn (FeedProduct $p) => $p->toCard())
                ->values()
                ->all()
        );

        $relatedProducts = collect(Cache::remember(
            "product:related_loc:v3:{$locale}:{$product->id}",
            now()->addHours(4),
            fn () => $localizer->localizeCardTitles($rawRelated, $locale)
        ))->map(function (array $card) {
            if (!empty($card['feed']) && !empty($card['sku'])) {
                $card['url'] = route('feed-product.show', ['feed' => $card['feed'], 'sku' => $card['sku']]);
            }

            return $card;
        });

        $localized = Cache::remember(
            "product:localized:v2:{$locale}:{$product->id}",
            now()->addHours(4),
            fn () => $localizer->localizeProduct($product, $locale)
        );

        $previous = url()->previous();
        $backUrl = str_contains($previous, '/product-search') ? $previous : route('product-search');
        $storeName = (string) config("feeds.feeds.{$feed}.store_name", $product->brand ?: $feed);
        $canonicalUrl = route('feed-product.show', ['feed' => $product->feed, 'sku' => $product->sku]);

        $schemaImages = array_values(array_filter(
            is_array($product->image_urls) && $product->image_urls !== []
                ? $product->image_urls
                : [$product->image_url]
        ));

        $productSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $localized['title'],
            'sku' => (string) $product->sku,
            'url' => $canonicalUrl,
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => $product->currency ?: 'EUR',
                'price' => number_format((float) $product->price, 2, '.', ''),
                'availability' => $isUnavailable
                    ? 'https://schema.org/OutOfStock'
                    : 'https://schema.org/InStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        if ($schemaImages !== []) {
            $productSchema['image'] = $schemaImages;
        }
        if (!empty($product->brand)) {
            $productSchema['brand'] = [
                '@type' => 'Brand',
                'name' => (string) $product->brand,
            ];
        }
        if (!empty($product->ean) && preg_match('/^\d{13}$/', (string) $product->ean)) {
            $productSchema['gtin13'] = (string) $product->ean;
        }

        $metaDescription = trim(sprintf(
            '%s — €%s (%s). %s',
            $localized['title'],
            number_format((float) $product->price, 2),
            $storeName,
            $isUnavailable
                ? __('Нет в наличии — смотрите похожие предложения в каталоге Exereto.')
                : __('Официальная цена магазина в Греции на Exereto.')
        ));

        return view('public.feed-product', [
            'product' => $product,
            'isUnavailable' => $isUnavailable,
            'productJsonLd' => $productSchema,
            'metaDescription' => $metaDescription,
            'title' => $localized['title'],
            'colour' => $localized['colour'],
            'specs' => $localized['specs'],
            'categories' => $localized['categories'],
            'colorways' => $colorways,
            'variantOptions' => $variantOptions,
            'variantAxisLabel' => $variantAxisLabel,
            'relatedProducts' => $relatedProducts,
            'backUrl' => $backUrl,
            'outboundUrl' => $product->outboundUrl(),
            'storeName' => $storeName,
        ]);
    }

    /**
     * Разводит матрицу «Цвет × Конфигурация» на два чистых селектора без дублей.
     *
     * @param  array<int, array<string, mixed>>  $rawSiblings
     * @return array{0: \Illuminate\Support\Collection, 1: array<int, array<string, mixed>>, 2: string}
     */
    protected function buildVariantSelectors(FeedProduct $current, array $rawSiblings): array
    {
        $curColourKey = $this->normalizeColourKey($current->colour);
        $curVariantKey = mb_strtolower(trim((string) ($current->variant_label ?? '')));

        // 1. Уникальные цвета: нормализуем grey/gray, приоритет — текущей конфигурации
        $byColor = [];
        foreach ($rawSiblings as $row) {
            $colKey = $this->normalizeColourKey($row['colour'] ?? null);
            if ($colKey === '') {
                continue;
            }

            $isExactCurrent = (string) $row['sku'] === (string) $current->sku;
            $matchesCurVar = $curVariantKey !== '' && mb_strtolower(trim((string) ($row['variant_label'] ?? ''))) === $curVariantKey;
            $score = ($isExactCurrent ? 10 : 0) + ($matchesCurVar ? 5 : 0) + (!empty($row['in_stock']) ? 1 : 0);

            if (!isset($byColor[$colKey]) || $score > $byColor[$colKey]['score']) {
                $byColor[$colKey] = ['row' => $row, 'score' => $score];
            }
        }

        $colorRows = $byColor !== []
            ? array_values(array_map(fn ($item) => $item['row'], $byColor))
            : array_values(array_filter($rawSiblings, fn ($r) => empty($r['variant_label'])));

        $colorways = FeedProduct::hydrate($colorRows);

        // 2. Уникальные конфигурации (память / связь / объём) для дропдауна: приоритет текущему цвету
        $byVariant = [];
        foreach ($rawSiblings as $row) {
            $label = trim((string) ($row['variant_label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $varKey = mb_strtolower($label);
            $isExactCurrent = (string) $row['sku'] === (string) $current->sku;
            $matchesCurCol = $curColourKey !== '' && $this->normalizeColourKey($row['colour'] ?? null) === $curColourKey;
            $score = ($isExactCurrent ? 10 : 0) + ($matchesCurCol ? 5 : 0) + (!empty($row['in_stock']) ? 1 : 0);

            if (!isset($byVariant[$varKey]) || $score > $byVariant[$varKey]['score']) {
                $byVariant[$varKey] = [
                    'row' => $row,
                    'label' => $label,
                    'same_color' => $matchesCurCol || $curColourKey === '',
                    'score' => $score,
                ];
            }
        }

        $variantOptions = [];
        $labels = [];
        foreach ($byVariant as $entry) {
            $row = $entry['row'];
            $labels[] = $entry['label'];
            $variantOptions[] = [
                'label' => $entry['label'],
                'colour' => trim((string) ($row['colour'] ?? '')),
                'same_color' => (bool) $entry['same_color'],
                'price' => (float) $row['price'],
                'price_formatted' => '€' . number_format((float) $row['price'], 2),
                'in_stock' => (bool) ($row['in_stock'] ?? true),
                'is_active' => (string) $row['sku'] === (string) $current->sku,
                'url' => route('feed-product.show', ['feed' => $row['feed'], 'sku' => $row['sku']]),
            ];
        }

        usort($variantOptions, fn (array $a, array $b) => FeedVariantGrouper::compareVariantLabels($a['label'], $b['label']));

        $axisLabel = FeedVariantGrouper::detectAxisLabel($labels);

        return [$colorways, $variantOptions, $axisLabel];
    }

    protected function normalizeColourKey(?string $colour): string
    {
        $clean = mb_strtolower(trim((string) ($colour ?? '')));
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('/\bgrey\b/u', 'gray', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return trim($clean);
    }
}