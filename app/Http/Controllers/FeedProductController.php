<?php

namespace App\Http\Controllers;

use App\Models\FeedProduct;
use App\Services\Feeds\FeedEntityLocalizer;
use App\Services\Feeds\FeedVariantGrouper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FeedProductController extends Controller
{
    public function show(Request $request, string $feed, string $sku, FeedEntityLocalizer $localizer): View
    {
        $product = $this->findProduct($feed, $sku);
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
            "product:related_raw:v2:{$feed}:{$product->category_top}:{$product->gender}:{$product->id}",
            now()->addHours(4),
            fn () => FeedProduct::query()
                ->where('feed', $feed)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->when($product->category_top, fn ($q) => $q->where('category_top', $product->category_top))
                ->when($product->gender, fn ($q) => $q->where('gender', $product->gender))
                ->limit(12)
                ->get()
                ->map(fn (FeedProduct $p) => $p->toCard())
                ->all()
        );

        $relatedProducts = collect(Cache::remember(
            "product:related_loc:v2:{$locale}:{$product->id}",
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

        return view('public.feed-product', [
            'product' => $product,
            'title' => $localized['title'],
            'colour' => $localized['colour'],
            'specs' => $localized['specs'],
            'categories' => $localized['categories'],
            'colorways' => $colorways,
            'variantOptions' => $variantOptions,
            'variantAxisLabel' => $variantAxisLabel,
            'backUrl' => $backUrl,
            'outboundUrl' => $product->outboundUrl(),
            'storeName' => (string) config("feeds.feeds.{$feed}.store_name", $product->brand ?: $feed),
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
        $curColourKey = mb_strtolower(trim((string) ($current->colour ?? '')));
        $curVariantKey = mb_strtolower(trim((string) ($current->variant_label ?? '')));

        // 1. Уникальные цвета: для каждого цвета приоритет отдаём варианту с текущей конфигурацией
        $byColor = [];
        foreach ($rawSiblings as $row) {
            $colKey = mb_strtolower(trim((string) ($row['colour'] ?? '')));
            if ($colKey === '') {
                continue;
            }

            $isExactCurrent = (string) $row['sku'] === (string) $current->sku;
            $matchesCurVar = $curVariantKey !== '' && mb_strtolower(trim((string) ($row['variant_label'] ?? ''))) === $curVariantKey;

            if (!isset($byColor[$colKey])) {
                $byColor[$colKey] = ['row' => $row, 'score' => ($isExactCurrent ? 10 : 0) + ($matchesCurVar ? 5 : 0) + (!empty($row['in_stock']) ? 1 : 0)];
                continue;
            }

            $score = ($isExactCurrent ? 10 : 0) + ($matchesCurVar ? 5 : 0) + (!empty($row['in_stock']) ? 1 : 0);
            if ($score > $byColor[$colKey]['score']) {
                $byColor[$colKey] = ['row' => $row, 'score' => $score];
            }
        }

        // Если цвета вообще не заполнены (например, старый фид с разными SKU в одной группе), оставляем классическое поведение по всем siblings без variant_label
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
            $matchesCurCol = $curColourKey !== '' && mb_strtolower(trim((string) ($row['colour'] ?? ''))) === $curColourKey;
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

    protected function findProduct(string $feed, string $sku): FeedProduct
    {
        return FeedProduct::query()
            ->where('feed', $feed)
            ->where('sku', $sku)
            ->where('is_active', true)
            ->firstOrFail();
    }
}