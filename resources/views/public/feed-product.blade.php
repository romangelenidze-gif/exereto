@php
    $pdpTitle = !empty($product->brand)
        ? "{$title} | {$product->brand} | Exereto"
        : "{$title} | Exereto";
    $pdpDescription = $metaDescription
        ?? __('Официальная цена магазина в Греции на Exereto.');
    $isUnavailable = $isUnavailable ?? (!$product->is_active || !$product->in_stock);
@endphp

<x-layouts.public
    :title="$pdpTitle"
    :description="$pdpDescription"
    ogType="product"
    :ogImage="$product->image_url"
    :jsonLd="$productJsonLd ?? null"
>

<div class="apple-pdp">

    <div class="apple-pdp-grid">

        <!-- Галерея в стиле Skroutz: до 5 миниатюр слева (+N на 5-й), нативный PhotoSwipe на весь экран -->
        @php
            $images = is_array($product->image_urls)
                ? $product->image_urls
                : (is_string($product->image_urls) ? (json_decode($product->image_urls, true) ?: []) : []);

            $images = array_values(array_filter(array_unique($images)));

            if (empty($images) && $product->image_url) {
                $images = [$product->image_url];
            }

            $totalImages = count($images);
            $maxVisibleThumbs = 5;
            $hasOverflow = $totalImages > $maxVisibleThumbs;
            $visibleThumbs = array_slice($images, 0, $maxVisibleThumbs);
            $overflowCount = $totalImages - ($maxVisibleThumbs - 1);
            $galleryJson = json_encode($images, JSON_UNESCAPED_SLASHES);
        @endphp

        <div class="apple-pdp-media"
             x-data="{
                 activeIndex: 0,
                 images: @js($images),
                 prev() {
                     if (this.images.length < 2) return;
                     this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
                 },
                 next() {
                     if (this.images.length < 2) return;
                     this.activeIndex = (this.activeIndex + 1) % this.images.length;
                 }
             }"
             @pswp-change="activeIndex = $event.detail">

            <div class="relative flex flex-col-reverse sm:flex-row items-center gap-3 sm:gap-5 rounded-[24px] bg-[#f5f5f7] p-4 sm:p-6 select-none">

                {{-- Вертикальная колонка миниатюр слева (на мобильном — снизу), максимум 5 img в DOM --}}
                @if($totalImages > 1)
                    <div class="flex flex-row sm:flex-col items-center justify-center gap-2 sm:gap-2.5 shrink-0 z-10">
                        @foreach($visibleThumbs as $index => $thumbUrl)
                            @php
                                $isOverflowSlot = $hasOverflow && $index === ($maxVisibleThumbs - 1);
                            @endphp

                            @if($isOverflowSlot)
                                <button type="button"
                                        data-pswp-gallery="{{ $galleryJson }}"
                                        data-pswp-index="{{ $index }}"
                                        data-pswp-alt="{{ $title }}"
                                        :class="activeIndex >= {{ $index }} ? 'border-zinc-900 shadow-sm' : 'border-black/10 hover:border-black/30'"
                                        class="relative w-14 h-14 sm:w-[60px] sm:h-[60px] rounded-xl bg-white border overflow-hidden cursor-pointer transition-all shrink-0"
                                        aria-label="{{ __('Показать все фото') }} (+{{ $overflowCount }})">
                                    <img src="{{ $thumbUrl }}"
                                         alt=""
                                         loading="lazy"
                                         decoding="async"
                                         fetchpriority="low"
                                         class="w-full h-full object-contain p-1">
                                    <span class="absolute inset-0 bg-black/55 flex items-center justify-center text-white text-sm font-semibold">
                                        +{{ $overflowCount }}
                                    </span>
                                </button>
                            @else
                                <button type="button"
                                        @click="activeIndex = {{ $index }}"
                                        :class="activeIndex === {{ $index }} ? 'border-zinc-900 shadow-sm' : 'border-black/10 hover:border-black/30 opacity-80 hover:opacity-100'"
                                        class="w-14 h-14 sm:w-[60px] sm:h-[60px] rounded-xl bg-white border p-1.5 overflow-hidden cursor-pointer transition-all flex items-center justify-center shrink-0"
                                        aria-label="{{ __('Фото') }} {{ $index + 1 }}">
                                    <img src="{{ $thumbUrl }}"
                                         alt=""
                                         loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                         decoding="async"
                                         fetchpriority="low"
                                         class="max-w-full max-h-full object-contain">
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Главная сцена фото со стрелками < > и бесшовным фоном --}}
                <div class="relative flex-1 min-w-0 w-full flex items-center justify-center aspect-square max-h-[520px]">

                    @if($product->discount_percent > 0)
                        <span class="apple-pdp-badge z-20">-{{ $product->discount_percent }}%</span>
                    @endif

                    @if($totalImages > 1)
                        <button type="button"
                                @click.stop="prev()"
                                class="absolute left-0 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-10 sm:h-10 rounded-full text-zinc-500 hover:text-zinc-900 hover:bg-black/5 flex items-center justify-center transition-colors cursor-pointer"
                                aria-label="{{ __('Предыдущее фото') }}">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <button type="button"
                                @click.stop="next()"
                                class="absolute right-0 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-10 sm:h-10 rounded-full text-zinc-500 hover:text-zinc-900 hover:bg-black/5 flex items-center justify-center transition-colors cursor-pointer"
                                aria-label="{{ __('Следующее фото') }}">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    @endif

                    @if($totalImages > 0)
                        <button type="button"
                                data-pswp-gallery="{{ $galleryJson }}"
                                :data-pswp-index="activeIndex"
                                data-pswp-alt="{{ $title }}"
                                class="w-full h-full flex items-center justify-center px-8 sm:px-10 py-2 cursor-zoom-in focus:outline-none"
                                aria-label="{{ __('Увеличить фото') }}">
                            <img data-pdp-main
                                 :src="images[activeIndex]"
                                 src="{{ $images[0] }}"
                                 alt="{{ $title }}"
                                 fetchpriority="high"
                                 class="max-w-full max-h-full object-contain mix-blend-multiply transition-opacity duration-200">
                        </button>

                        <span class="pointer-events-none absolute right-1 bottom-1 z-10 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/80 text-zinc-600 text-xs font-medium shadow-sm">
                            @if($totalImages > 1)
                                <span x-text="(activeIndex + 1) + ' / ' + images.length">1 / {{ $totalImages }}</span>
                            @endif
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m-3-3h6" />
                            </svg>
                        </span>
                    @endif
                </div>
            </div>

            <p class="apple-pdp-photo-note">
                {{ __('Больше фото этого товара:') }}
                <a href="{{ $outboundUrl }}" target="_blank" rel="sponsored nofollow noopener noreferrer" class="underline underline-offset-2">{{ __('смотреть на :store', ['store' => $storeName]) }}</a>
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

            <!-- Другие расцветки (уникальные цвета, сохраняют текущую конфигурацию) -->
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

            <!-- Выпадающий список конфигураций (память / связь / объём) -->
            @if(!empty($variantOptions) && count($variantOptions) > 1)
                <div class="space-y-1.5">
                    <label for="pdp-variant-select" class="apple-pdp-label block">
                        {{ $variantAxisLabel ?? __('Вариант') }}
                    </label>
                    <div class="relative">
                        <select id="pdp-variant-select"
                                onchange="if (this.value && this.value !== window.location.href) window.location.href = this.value;"
                                class="w-full appearance-none rounded-2xl bg-white border border-black/12 hover:border-black/25 focus:border-[#0066cc] focus:ring-2 focus:ring-[#0066cc]/15 pl-4 pr-11 py-3.5 text-[15px] font-semibold text-[#1d1d1f] shadow-[0_2px_8px_rgba(0,0,0,0.03)] transition-all cursor-pointer focus:outline-none">
                            @foreach($variantOptions as $opt)
                                <option value="{{ $opt['url'] }}" @selected($opt['is_active'])>
                                    {{ $opt['label'] }} — {{ $opt['price_formatted'] }}@if(!$opt['same_color'] && $opt['colour'] !== '') ({{ $opt['colour'] }})@endif
                                </option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-[#6e6e73]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
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

            @if($isUnavailable)
                <div class="rounded-2xl bg-amber-50/80 border border-amber-200/70 px-4 py-3 text-sm text-[#1d1d1f] space-y-1.5">
                    <p class="font-semibold">{{ __('Сейчас нет в наличии') }}</p>
                    <p class="text-xs text-[#424245]">
                        {{ __('Товар временно закончился в магазине — посмотрите похожие предложения в наличии ниже.') }}
                    </p>
                    @if(isset($relatedProducts) && $relatedProducts->isNotEmpty())
                        <a href="#related-products" class="inline-flex items-center gap-1 pt-1 text-xs font-semibold text-[#0066cc] hover:underline">
                            {{ __('Смотреть похожие') }} ↓
                        </a>
                    @endif
                </div>
            @endif

            <!-- Действие -->
            <div class="apple-pdp-actions">
                <a href="{{ $outboundUrl }}" target="_blank" rel="sponsored nofollow noopener noreferrer" class="apple-btn-primary">
                    @if($isUnavailable)
                        {{ __('Проверить наличие на :store', ['store' => $storeName]) }}
                    @else
                        {{ __('Купить на :store', ['store' => $storeName]) }}
                    @endif
                </a>
            </div>

            <p class="apple-pdp-note">
                {{ __('Цена и наличие обновляются ежедневно, итоговые данные смотрите на сайте магазина.') }}
           <!-- {{ __('Ссылка на магазин партнёрская: мы получаем комиссию, а цена для вас не меняется.') }} -->
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
    <section id="related-products" class="max-w-[1680px] mx-auto px-6 md:px-10 xl:px-14 py-16 mt-0 scroll-mt-24">
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 mb-8">{{ __('Вам также может понравиться') }}</h2>
        <div class="apple-product-grid apple-product-grid--6">
            @foreach($relatedProducts as $related)
                <x-public-ui.product-card :product="$related" />
            @endforeach
        </div>
    </section>
@endif

<script>
    if (window.ExeretoRecent) {
        window.ExeretoRecent.push(@js([
            'feed' => (string) $product->feed,
            'sku' => (string) $product->sku,
            'brand' => (string) ($product->brand ?? ''),
            'title' => (string) $title,
            'price' => '€' . number_format((float) $product->price, 2, '.', ' '),
            'discount' => (int) ($product->discount_percent ?? 0),
            'image' => (string) ($product->image_url ?? ''),
        ]));
    }
</script>

</x-layouts.public>