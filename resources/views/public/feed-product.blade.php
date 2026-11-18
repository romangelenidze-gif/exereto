<x-layouts.public
    title="{{ $title }} | {{ $product->brand }} | Buyer Greece"
    description="{{ __('Цена, размеры и наличие в магазине. Переход в магазин по партнёрской ссылке, цена для вас не меняется.') }}"
>

<div class="apple-pdp">

    <div class="apple-pdp-grid">

        <!-- Фото: по клику открывается на весь экран; под ним ссылка на остальные фото в магазине -->
        @php
            $images = is_array($product->image_urls)
                ? $product->image_urls
                : (is_string($product->image_urls) ? (json_decode($product->image_urls, true) ?: []) : []);

            if (empty($images) && $product->image_url) {
                $images = [$product->image_url];
            }
        @endphp

        <div class="apple-pdp-media" x-data="{ activeIndex: 0, images: @js($images) }">
            <button type="button"
                    class="apple-pdp-gallery"
                    :data-pswp-src="images[activeIndex] || '{{ $product->image_url }}'"
                    data-pswp-alt="{{ $title }}"
                    aria-label="{{ __('Увеличить фото') }}">
                @if($product->discount_percent > 0)
                    <span class="apple-pdp-badge">-{{ $product->discount_percent }}%</span>
                @endif

                @if(!empty($images))
                    <img :src="images[activeIndex]" src="{{ $images[0] }}" alt="{{ $title }}" class="apple-pdp-image apple-photo-lift">
                    <span class="apple-pdp-zoom-hint" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
                        </svg>
                    </span>
                @endif
            </button>

            @if(count($images) > 1)
                <div class="flex gap-2 overflow-x-auto pb-2 mt-3">
                    @foreach($images as $index => $img)
                        <button type="button"
                                @click="activeIndex = {{ $index }}"
                                :class="activeIndex === {{ $index }} ? 'ring-2 ring-[#0071e3]' : 'ring-1 ring-black/10 hover:ring-black/30'"
                                class="w-16 h-16 shrink-0 rounded-xl bg-white p-1.5 overflow-hidden transition-all flex items-center justify-center cursor-pointer">
                            <img src="{{ $img }}" alt="" class="max-w-full max-h-full object-contain" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif

            <p class="apple-pdp-photo-note">
                {{ __('Больше фото этого товара:') }}
                <a href="{{ $outboundUrl }}" target="_blank" rel="sponsored nofollow noopener noreferrer">{{ __('смотреть на :store', ['store' => $storeName]) }}</a>
            </p>
        </div>

        <!-- Информация -->
        <div class="apple-pdp-info">

            <p class="apple-pdp-brand">{{ $product->brand }}</p>
            <h1 class="apple-pdp-title">{{ $title }}</h1>

            <div class="apple-pdp-price-row">
                <span class="apple-pdp-price">€{{ number_format($product->price, 2) }}</span>
                @if($product->old_price)
                    <span class="apple-pdp-price-old">€{{ number_format($product->old_price, 2) }}</span>
                @endif
            </div>

            @if($product->colour)
                <div class="apple-pdp-colour space-y-1">
                    <span class="apple-pdp-label">{{ __('Цвет') }}:</span>
                    <div>
                        <x-public-ui.color-dots :colour="$product->colour" :label="$colour ?? $product->colour" :only-dots="false" />
                    </div>
                </div>
            @endif

            <!-- Другие расцветки -->
            @if($colorways->count() > 1)
                <div class="apple-pdp-colors" role="list">
                    @foreach($colorways as $cw)
                        <a href="{{ route('feed-product.show', ['feed' => $cw->feed, 'sku' => $cw->sku]) }}"
                           role="listitem"
                           title="{{ $cw->colour }}"
                           class="apple-pdp-color @if($cw->sku === $product->sku) is-active @endif">
                            @if($cw->image_url)
                                <img src="{{ $cw->image_url }}" alt="{{ $cw->colour }}" loading="lazy" class="apple-photo-lift">
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif

            <!-- Размеры -->
            @if(!empty($product->sizes))
                <div>
                    <p class="apple-pdp-label">{{ __('Размеры в наличии') }}</p>
                    <div class="apple-pdp-sizes">
                        @foreach($product->sizes as $size)
                            <span class="apple-pdp-size @if(isset($size['stock']) && $size['stock'] <= 0) is-out @endif">{{ $size['size'] }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @unless($product->in_stock)
                <p class="apple-pdp-note">{{ __('Сейчас нет в наличии') }}</p>
            @endunless

            <!-- Действие -->
            <div class="apple-pdp-actions">
                <a href="{{ $outboundUrl }}" target="_blank" rel="sponsored nofollow noopener noreferrer" class="apple-btn-primary">
                    {{ __('Купить на :store', ['store' => $storeName]) }}
                </a>
            </div>

            <p class="apple-pdp-note">
                {{ __('Цена и наличие обновляются ежедневно, итоговые данные смотрите на сайте магазина.') }}
                {{ __('Ссылка на магазин партнёрская: мы получаем комиссию, а цена для вас не меняется.') }}
            </p>

            <!-- Характеристики -->
            @if(!empty($specs))
                <div>
                    <h2 class="apple-pdp-section-title">{{ __('Характеристики') }}</h2>
                    <ul class="apple-pdp-specs">
                        @foreach($specs as $spec)
                            <li>{{ $spec }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>
</div>

<!-- Релевантные (похожие) товары -->
    @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
        <section class="max-w-[1680px] mx-auto px-6 md:px-10 xl:px-14 py-16 mt-0">
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900 mb-8">{{ __('Вам также может понравиться') }}</h2>
            <div class="apple-product-grid apple-product-grid--6">
                @foreach($relatedProducts as $related)
                    <x-public-ui.product-card :product="$related" />
                @endforeach
            </div>
        </section>
    @endif

</x-layouts.public>
