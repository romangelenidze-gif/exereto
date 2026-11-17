<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use App\Models\FeedProduct;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedProductController extends Controller
{
public function show(Request $request, string $feed, string $sku, FeedEntityLocalizer $localizer): View
    {
        $product = $this->findProduct($feed, $sku);
        $locale = app()->getLocale();

        $colorways = Cache::remember(
            "product:colorways:{$feed}:" . ($product->model_code ?: $product->id),
            now()->addHours(4),
            fn () => $product->model_code
                ? FeedProduct::query()
                    ->where('feed', $feed)
                    ->where('model_code', $product->model_code)
                    ->where('is_active', true)
                    ->orderBy('colour')
                    ->get(['id', 'feed', 'sku', 'colour', 'image_url'])
                : collect([$product])
        );

        $rawRelated = Cache::remember(
            "product:related_raw:{$feed}:{$product->category_top}:{$product->gender}:{$product->id}",
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
            "product:related_loc:{$locale}:{$product->id}",
            now()->addHours(4),
            fn () => $localizer->localizeCardTitles($rawRelated, $locale)
        ))->map(function (array $card) {
            if (!empty($card['feed']) && !empty($card['sku'])) {
                $card['url'] = route('feed-product.show', ['feed' => $card['feed'], 'sku' => $card['sku']]);
            }
            return $card;
        });

        $localized = Cache::remember(
            "product:localized:{$locale}:{$product->id}",
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
            'relatedProducts' => $relatedProducts,
            'backUrl' => $backUrl,
            'outboundUrl' => $product->outboundUrl(),
            'storeName' => (string) config("feeds.feeds.{$feed}.store_name", $product->brand ?: $feed),
        ]);
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