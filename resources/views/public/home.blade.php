<x-layouts.public
    title="{{ __('Брендовые товары из Греции с доставкой в Грузию') }} | Exereto"
    description="{{ __('Оригинальная одежда, обувь, парфюмерия, игрушки и техника из официальных магазинов Греции с доставкой в Грузию.') }}"
>

    <div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-16 space-y-10 lg:space-y-16 relative"
         x-data="{ loading: false }"
         @pageshow.window="loading = false"
         @submit.window="loading = true"
         @search-loading.window="loading = true"
    >

        <template x-teleport="body">
            <div x-show="loading" x-cloak class="fixed inset-0 z-[9999] bg-white/80 backdrop-blur-md flex flex-col items-center justify-center space-y-4">
                <svg class="animate-spin h-10 w-10 text-[#0071e3]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="app-text-body font-semibold animate-pulse">{{ __('Загружаем каталог...') }}</span>
            </div>
        </template>

        <!-- Мобильный слоган / Десктопный поиск -->
        <x-public-ui.search-spotlight :categories="$tiles ?? []" />

        <!-- Тематический блок №1: Одежда и бренды (над популярными брендами) -->
        @if(!empty($heroBlock))
            @php
                $desktopRel = ltrim($heroBlock['banner'], '/');
                $mobileRel = preg_replace('/\.webp$/i', '-mobile.webp', $desktopRel);
                $hasDesktop = file_exists(public_path($desktopRel));
                $hasMobile = file_exists(public_path($mobileRel));
                $hasAnyBanner = $hasDesktop || $hasMobile;
                $fallbackSrc = $hasDesktop ? '/' . $desktopRel : ($hasMobile ? '/' . $mobileRel : '');
            @endphp
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4 lg:space-y-6">
                <a href="{{ $heroBlock['url'] }}"
                   class="block relative w-full h-36 sm:h-48 lg:h-64 rounded-2xl lg:rounded-3xl overflow-hidden bg-gradient-to-r {{ $heroBlock['gradient'] }} shadow-sm group">
                    @if($hasAnyBanner)
                        <picture>
                            @if($hasMobile)
                                <source media="(max-width: 767px)" srcset="/{{ $mobileRel }}">
                            @endif
                            @if($hasDesktop)
                                <source media="(min-width: 768px)" srcset="/{{ $desktopRel }}">
                            @endif
                            <img src="{{ $fallbackSrc }}"
                                 alt="{{ $heroBlock['title'] }}"
                                 fetchpriority="high"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-500">
                        </picture>
                    @else
                        <div class="w-full h-full flex flex-col justify-end p-5 lg:p-8 text-white">
                            <span class="text-xs uppercase tracking-widest text-white/70 font-semibold">Exereto Selection</span>
                            <span class="text-2xl lg:text-4xl font-bold tracking-tight mt-1">{{ $heroBlock['title'] }}</span>
                        </div>
                    @endif
                </a>

                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                    <div>
                        <h2 class="text-xl lg:text-2xl font-bold tracking-tight text-[#1d1d1f]">{{ $heroBlock['title'] }}</h2>
                        <p class="text-sm text-zinc-500 mt-0.5">{{ $heroBlock['subtitle'] }}</p>
                    </div>
                    <a href="{{ $heroBlock['url'] }}" class="hidden lg:inline-flex items-center gap-1 text-sm font-semibold text-[#0071e3] hover:underline shrink-0">
                        {{ $heroBlock['cta'] }} &rarr;
                    </a>
                </div>

                <!-- Единая 6-колоночная сетка из app.css (на мобильном — свайп-карусель с CTA) -->
                <div class="apple-product-grid apple-product-grid--6 apple-product-grid--carousel scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
                    @foreach($heroBlock['products'] as $index => $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" :is-first="$index === 0" />
                        </div>
                    @endforeach

                    <a href="{{ $heroBlock['url'] }}"
                       @click="loading = true"
                       class="lg:hidden w-[46%] sm:w-[31%] shrink-0 snap-start flex flex-col items-center justify-center text-center gap-1.5 rounded-3xl px-4 py-8 hover:bg-white/60 active:bg-white transition-colors group">
                        <span class="text-[16px] font-semibold leading-snug text-[#0071e3] group-hover:underline">
                            {{ $heroBlock['cta'] }}
                        </span>
                        @if(!empty($heroBlock['count']))
                            <span class="text-[15px] font-medium text-[#1d1d1f] tabular-nums">
                                {{ number_format((int) $heroBlock['count']) }}
                            </span>
                        @endif
                    </a>
                </div>
            </section>
        @endif

        <!-- Популярные бренды: единая 6-колоночная сетка и высота 460px + чистый дымчатый блюр снизу -->
        @if(!empty($brandShowcases))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4 lg:space-y-6">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-xl lg:text-2xl font-bold tracking-tight text-[#1d1d1f]">
                            {{ __('Популярные бренды') }}
                        </h2>
                        <p class="text-sm text-zinc-500 mt-0.5">
                            {{ __('Оригинальные коллекции из официальных греческих магазинов и универмагов') }}
                        </p>
                    </div>
                    <button type="button"
                            @click="$dispatch('open-brands')"
                            class="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold text-[#0071e3] hover:underline shrink-0 cursor-pointer">
                        <span>{{ __('Все бренды') }} ({{ number_format($totalBrandsCount ?? 0) }})</span>
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </div>

                <div class="apple-product-grid apple-product-grid--6 apple-product-grid--carousel scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
                    @foreach($brandShowcases as $brand)
                        @php
                            $heroImg = $brand['image'] ?? null;
                            $objPos = $brand['object_position'] ?? '50% 10%';
                        @endphp
                        <a href="{{ $brand['url'] }}"
                           @click="loading = true"
                           class="brand-showcase-card w-[60%] sm:w-[33%] lg:w-auto shrink-0 snap-start flex flex-col justify-end group">

                            @if($heroImg)
                                <img src="{{ $heroImg }}"
                                     alt="{{ $brand['name'] }}"
                                     loading="lazy"
                                     decoding="async"
                                     style="object-position: {{ $objPos }};"
                                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                            @endif

                            <!-- Нейтральный прогрессивный блюр и дымчатое затемнение снизу -->
                            <div class="brand-showcase-blur"></div>
                            <div class="brand-showcase-scrim"></div>

                            <!-- Белая типографика снизу слева -->
                            <div class="relative z-10 p-5 flex flex-col items-start text-left">
                                <div class="text-[22px] xl:text-[25px] font-bold tracking-tight leading-tight text-white drop-shadow-[0_1px_6px_rgba(0,0,0,0.28)] max-w-full truncate">
                                    {{ $brand['name'] }}
                                </div>
                                <div class="text-[12px] xl:text-[13px] font-medium text-white/90 mt-0.5 tabular-nums">
                                    {{ number_format((int) $brand['count']) }} {{ __('товаров') }}
                                </div>
                                @if(($brand['max_discount'] ?? 0) >= 10)
                                    <div class="mt-3.5 flex items-baseline gap-1.5 text-white">
                                        <span class="text-[12px] font-medium text-white/90">{{ __('до') }}</span>
                                        <span class="text-[20px] xl:text-[22px] font-bold tracking-tight leading-none tabular-nums">-{{ (int) $brand['max_discount'] }}%</span>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach

                    <!-- 6-я карточка: каталог всех брендов A–Z (в той же высоте и сетке) -->
                    <button type="button"
                            @click="$dispatch('open-brands')"
                            class="brand-showcase-card !bg-[#1d1d1f] hover:!bg-[#2c2c2e] !border-white/10 w-[60%] sm:w-[33%] lg:w-auto shrink-0 snap-start text-white p-5 sm:p-6 flex flex-col items-start justify-between text-left cursor-pointer group">
                        <span class="px-3 py-1 rounded-full bg-white/12 text-[12px] font-semibold tracking-wider text-white/90">
                            A–Z
                        </span>
                        <div class="space-y-1.5">
                            <div class="text-3xl xl:text-4xl font-bold tracking-tight tabular-nums">
                                {{ number_format($totalBrandsCount ?? 0) }}+
                            </div>
                            <div class="text-[16px] xl:text-[17px] font-semibold text-white/95 leading-snug">
                                {{ __('Все бренды каталога') }}
                            </div>
                            <div class="text-[13px] text-white/60 leading-snug">
                                {{ __('Мгновенный поиск по алфавиту') }}
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-[14px] font-semibold text-white group-hover:translate-x-1 transition-transform">
                            <span>{{ __('Открыть список') }}</span>
                            <span aria-hidden="true">&rarr;</span>
                        </span>
                    </button>
                </div>
            </section>

            <!-- Шторка всех брендов A–Z -->
            <x-public-ui.brands-drawer :brands="$allBrandsIndex ?? []" />
        @endif

        <!-- Тематические блоки №2–5: Детское -> Кроссовки -> Игрушки -> Техника -->
        @foreach($bottomBlocks ?? [] as $block)
            @php
                $desktopRel = ltrim($block['banner'], '/');
                $mobileRel = preg_replace('/\.webp$/i', '-mobile.webp', $desktopRel);
                $hasDesktop = file_exists(public_path($desktopRel));
                $hasMobile = file_exists(public_path($mobileRel));
                $hasAnyBanner = $hasDesktop || $hasMobile;
                $fallbackSrc = $hasDesktop ? '/' . $desktopRel : ($hasMobile ? '/' . $mobileRel : '');
            @endphp
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4 lg:space-y-6">
                <a href="{{ $block['url'] }}"
                   class="block relative w-full h-36 sm:h-48 lg:h-64 rounded-2xl lg:rounded-3xl overflow-hidden bg-gradient-to-r {{ $block['gradient'] }} shadow-sm group">
                    @if($hasAnyBanner)
                        <picture>
                            @if($hasMobile)
                                <source media="(max-width: 767px)" srcset="/{{ $mobileRel }}">
                            @endif
                            @if($hasDesktop)
                                <source media="(min-width: 768px)" srcset="/{{ $desktopRel }}">
                            @endif
                            <img src="{{ $fallbackSrc }}"
                                 alt="{{ $block['title'] }}"
                                 loading="lazy"
                                 decoding="async"
                                 class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-500">
                        </picture>
                    @else
                        <div class="w-full h-full flex flex-col justify-end p-5 lg:p-8 text-white">
                            <span class="text-xs uppercase tracking-widest text-white/70 font-semibold">Exereto Catalog</span>
                            <span class="text-2xl lg:text-4xl font-bold tracking-tight mt-1">{{ $block['title'] }}</span>
                        </div>
                    @endif
                </a>

                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                    <div>
                        <h2 class="text-xl lg:text-2xl font-bold tracking-tight text-[#1d1d1f]">{{ $block['title'] }}</h2>
                        <p class="text-sm text-zinc-500 mt-0.5">{{ $block['subtitle'] }}</p>
                    </div>
                    <a href="{{ $block['url'] }}" class="hidden lg:inline-flex items-center gap-1 text-sm font-semibold text-[#0071e3] hover:underline shrink-0">
                        {{ $block['cta'] }} &rarr;
                    </a>
                </div>

                <!-- Единая 6-колоночная сетка из app.css -->
                <div class="apple-product-grid apple-product-grid--6 apple-product-grid--carousel scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
                    @foreach($block['products'] as $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" />
                        </div>
                    @endforeach

                    <a href="{{ $block['url'] }}"
                       @click="loading = true"
                       class="lg:hidden w-[46%] sm:w-[31%] shrink-0 snap-start flex flex-col items-center justify-center text-center gap-1.5 rounded-3xl px-4 py-8 hover:bg-white/60 active:bg-white transition-colors group">
                        <span class="text-[16px] font-semibold leading-snug text-[#0071e3] group-hover:underline">
                            {{ $block['cta'] }}
                        </span>
                        @if(!empty($block['count']))
                            <span class="text-[15px] font-medium text-[#1d1d1f] tabular-nums">
                                {{ number_format((int) $block['count']) }}
                            </span>
                        @endif
                    </a>
                </div>
            </section>
        @endforeach

        <!-- Мессенджеры -->
        <section class="max-w-3xl mx-auto px-6 py-4 text-center space-y-4">
            <p class="app-text-body">
                {{ __('Мы на связи и готовы помочь с любым вопросом по выкупу и доставке в Грузию') }}
            </p>
            <x-messenger-row align="justify-center" />
        </section>

    </div>

</x-layouts.public>