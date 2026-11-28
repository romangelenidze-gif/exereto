@props(['product', 'isFirst' => false])

@php
    if ($product instanceof \Illuminate\Database\Eloquent\Model) {
        $card = method_exists($product, 'toCard') ? $product->toCard() : $product->toArray();
    } else {
        $card = (array) $product;
    }

    $name = $card['title'] ?? $card['name'] ?? '';
    $imgUrl = $card['image_url'] ?? $card['image'] ?? '';
    $price = (float) ($card['price'] ?? $card['price_amount'] ?? 0);
    $hasPriceRange = (bool) ($card['has_price_range'] ?? false);
    $groupMinPrice = isset($card['group_min_price']) && (float) $card['group_min_price'] > 0
        ? (float) $card['group_min_price']
        : $price;
    $displayPrice = $hasPriceRange ? $groupMinPrice : $price;
    $oldPrice = (!$hasPriceRange || $groupMinPrice >= ($price - 0.01)) ? ($card['old_price'] ?? null) : null;
    $discount = (int) ($card['discount_percent'] ?? 0);
    $colour = $card['colour'] ?? $card['color'] ?? $card['attributes']['colour'] ?? $card['attributes']['color'] ?? null;
    $detailUrl = $card['detail_url'] ?? $card['product_url'] ?? '#';
    $brand = $card['brand'] ?? null;
@endphp

<div class="adidas-product-card group relative">

    <a href="{{ $detailUrl }}" class="absolute inset-0 z-10" aria-label="{{ $name }}"></a>

    <div class="adidas-product-top">
        @if(!empty($imgUrl))
            <img src="{{ $imgUrl }}"
                 alt="{{ $name }}"
                 width="400"
                 height="400"
                 loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                 @if($isFirst) fetchpriority="high" @endif
                 decoding="async"
                 class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
        @endif

        @if($discount > 0 && (!$hasPriceRange || $groupMinPrice >= ($price - 0.01)))
            <span class="absolute top-3 left-3 z-20 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow">
                -{{ $discount }}%
            </span>
        @endif

        @if(!empty($colour))
            <x-public-ui.color-dots
                :colour="$colour"
                :only-dots="true"
                :min-colors="2"
                :pill="true"
                class="pointer-events-none absolute bottom-2.5 left-1/2 -translate-x-1/2 z-20"
            />
        @endif
    </div>

    <div class="adidas-product-bottom">
        <div class="space-y-1 mb-2 min-w-0">
            @if($brand)
                <div class="text-xs font-bold text-[#6e6e73] uppercase tracking-wider truncate">{{ $brand }}</div>
            @endif
            <div class="font-semibold text-zinc-900 text-sm line-clamp-2 leading-snug">
                {{ $name }}
            </div>
        </div>

        <div class="flex items-end justify-between gap-2 pt-3 border-t border-zinc-100">
            <div class="min-w-0">
                <div class="text-base xl:text-lg font-bold text-zinc-900 whitespace-nowrap leading-tight">
                    @if($hasPriceRange)
                        {{ __('от :price', ['price' => '€' . number_format($displayPrice, 2)]) }}
                    @else
                        €{{ number_format($displayPrice, 2) }}
                    @endif
                </div>
                @if(!empty($oldPrice))
                    <div class="text-xs text-[#6e6e73] line-through whitespace-nowrap mt-0.5">€{{ number_format((float) $oldPrice, 2) }}</div>
                @endif
            </div>

            <div class="relative z-20 shrink-0">
                <a href="{{ $detailUrl }}" class="adidas-card-action-btn" title="{{ __('Открыть') }}" aria-label="{{ __('Открыть') }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>