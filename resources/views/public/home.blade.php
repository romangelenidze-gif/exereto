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

                <!-- Мобильный: горизонтальный свайп с финальной карточкой CTA; Десктоп: сетка по 4 в ряд -->
                <div class="flex lg:grid lg:grid-cols-4 gap-3 lg:gap-5 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($heroBlock['products'] as $index => $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" :is-first="$index === 0" />
                        </div>
                    @endforeach

                    <!-- Финальная карточка перехода в категорию (мобильный / планшет) -->
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

        <!-- Популярные бренды: editorial full-bleed карточки с цветным прогрессивным блюром + карточка каталога A–Z -->
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

                <!-- Мобильный: крупные editorial-карточки в свайп-ленте; Десктоп: ровная сетка 4×2 (7 брендов + 1 карточка A–Z) -->
                <div class="flex items-stretch lg:grid lg:grid-cols-4 gap-3.5 lg:gap-5 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($brandShowcases as $brand)
                        @php
                            $heroImg = $brand['image'] ?? ($brand['images'][0] ?? null);
                            $tintRgb = $brand['tint_rgb'] ?? '24, 24, 32';
                            $glowRgb = $brand['glow_rgb'] ?? '72, 72, 92';
                            $objPos = $brand['object_position'] ?? '50% 18%';
                        @endphp
                        <a href="{{ $brand['url'] }}"
                           @click="loading = true"
                           class="relative w-[68%] sm:w-[42%] lg:w-auto h-[300px] sm:h-[330px] lg:h-[350px] shrink-0 snap-start rounded-3xl overflow-hidden bg-[#e8e8ed] shadow-[0_6px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_14px_34px_rgba(0,0,0,0.12)] transition-all duration-300 group flex flex-col justify-between">

                            <!-- 1. Full-bleed фоновое фото товара -->
                            @if($heroImg)
                                <img src="{{ $heroImg }}"
                                     alt="{{ $brand['name'] }}"
                                     loading="lazy"
                                     decoding="async"
                                     style="object-position: {{ $objPos }};"
                                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                            @endif

                            <!-- 2. Мягкое верхнее затемнение для читаемости верхнего бейджа на светлых студийных фото -->
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-black/25 via-black/10 to-transparent"></div>

                            <!-- 3. Прогрессивный стеклянный блюр нижней зоны (плавное растворение фото в блюр) -->
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[62%] backdrop-blur-xl"
                                 style="-webkit-mask-image: linear-gradient(to top, black 0%, black 46%, rgba(0,0,0,0.65) 68%, transparent 100%); mask-image: linear-gradient(to top, black 0%, black 46%, rgba(0,0,0,0.65) 68%, transparent 100%);"></div>

                            <!-- 4. Атмосферный цветной градиент-свечение бренда поверх блюра -->
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-[72%]"
                                 style="background: linear-gradient(to top, rgba({{ $tintRgb }}, 0.94) 0%, rgba({{ $tintRgb }}, 0.78) 36%, rgba({{ $glowRgb }}, 0.42) 64%, transparent 100%);"></div>

                            <!-- 5. Внутренняя деликатная стеклянная кромка -->
                            <div class="pointer-events-none absolute inset-0 rounded-3xl ring-1 ring-inset ring-white/20"></div>

                            <!-- Верхняя строка: стеклянный бейдж скидки -->
                            <div class="relative z-10 flex items-center justify-between p-4 sm:p-5">
                                @if(($brand['max_discount'] ?? 0) >= 10)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-black/35 backdrop-blur-md border border-white/20 text-[12px] font-bold text-white tracking-tight tabular-nums shadow-xs">
                                        {{ __('до') }} -{{ (int) $brand['max_discount'] }}%
                                    </span>
                                @else
                                    <span></span>
                                @endif
                            </div>

                            <!-- Нижняя зона на цветном блюре: название бренда, кол-во товаров и стеклянная кнопка-стрелка -->
                            <div class="relative z-10 p-5 sm:p-6 pt-8 flex items-end justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[22px] sm:text-[25px] font-bold tracking-tight leading-tight text-white drop-shadow-[0_2px_10px_rgba(0,0,0,0.2)] truncate">
                                        {{ $brand['name'] }}
                                    </div>
                                    <div class="text-[13px] sm:text-[14px] font-medium text-white/80 mt-1 tabular-nums">
                                        {{ number_format((int) $brand['count']) }} {{ __('товаров') }}
                                    </div>
                                </div>
                                <span class="w-10 h-10 rounded-full bg-white text-[#1d1d1f] group-hover:scale-105 group-hover:translate-x-0.5 flex items-center justify-center shrink-0 shadow-md transition-all duration-300" aria-hidden="true">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach

                    <!-- 8-я контрастная карточка: вход в каталог всех брендов A–Z (оставляем без изменений, выровнена по высоте) -->
                    <button type="button"
                            @click="$dispatch('open-brands')"
                            class="w-[68%] sm:w-[42%] lg:w-auto h-[300px] sm:h-[330px] lg:h-[350px] shrink-0 snap-start bg-[#1d1d1f] hover:bg-[#2c2c2e] text-white rounded-3xl p-6 flex flex-col items-start justify-between text-left transition-colors cursor-pointer group shadow-[0_6px_24px_rgba(0,0,0,0.08)]">
                        <span class="px-3 py-1 rounded-full bg-white/12 text-[12px] font-semibold tracking-wider text-white/90">
                            A–Z
                        </span>
                        <div class="space-y-1.5 my-4">
                            <div class="text-3xl sm:text-4xl font-bold tracking-tight tabular-nums">
                                {{ number_format($totalBrandsCount ?? 0) }}+
                            </div>
                            <div class="text-[17px] font-semibold text-white/95 leading-snug">
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

                <div class="flex lg:grid lg:grid-cols-4 gap-3 lg:gap-5 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($block['products'] as $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" />
                        </div>
                    @endforeach

                    <!-- Финальная карточка перехода в категорию (мобильный / планшет) -->
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