<x-layouts.public
    title="{{ __('Брендовые товары из Греции с доставкой в Грузию') }} | Exereto"
    description="{{ __('Оригинальная одежда, обувь, парфюмерия, игрушки и техника из официальных магазинов Греции с доставкой в Грузию.') }}"
>

@php
    $heroDesktop = is_file(public_path('images/home-banners/hero.webp'))
        ? 'images/home-banners/hero.webp'
        : (is_file(public_path('images/home-banners/hero.jpg'))
            ? 'images/home-banners/hero.jpg'
            : (is_file(public_path('images/home-banners/fashion.webp')) ? 'images/home-banners/fashion.webp' : null));

    $heroMobile = is_file(public_path('images/home-banners/hero-mobile.webp'))
        ? 'images/home-banners/hero-mobile.webp'
        : (is_file(public_path('images/home-banners/hero-mobile.jpg'))
            ? 'images/home-banners/hero-mobile.jpg'
            : $heroDesktop);

    $brandsFormatted = number_format((int) ($totalBrandsCount ?? 0), 0, '.', ' ');
    $maxDisc = (int) ($maxCatalogDiscount ?? 70);
@endphp

    <div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-16 pt-2 lg:pt-4 space-y-8 lg:space-y-12 relative"
         x-data="{ loading: false }"
         @pageshow.window="loading = false"
         @submit.window="loading = true"
         @search-loading.window="loading = true"
    >

        <template x-teleport="body">
            <div x-show="loading" x-cloak class="fixed inset-0 z-[9999] bg-white/80 backdrop-blur-md flex flex-col items-center justify-center space-y-4">
                <svg class="animate-spin h-10 w-10 text-[#6a47bd]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="app-text-body font-semibold animate-pulse">{{ __('Загружаем каталог...') }}</span>
            </div>
        </template>

        <!-- 1. ПЕРВЫЙ ЭКРАН: HERO-БАННЕР (клик -> полноэкранный поиск) + GRAPHITE AURA (клик -> A–Z бренды) -->
        <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-5 lg:space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-[2.45fr_1fr] gap-3 lg:gap-4 items-stretch">

                <!-- Главный баннер -->
                <div role="button"
                     tabindex="0"
                     @click="$dispatch('open-search')"
                     @keydown.enter.prevent="$dispatch('open-search')"
                     @keydown.space.prevent="$dispatch('open-search')"
                     class="relative min-h-[240px] sm:min-h-[300px] lg:min-h-[350px] rounded-[20px] overflow-hidden bg-[#1f3a5c] cursor-pointer group select-none flex items-end lg:items-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6a47bd] focus-visible:ring-offset-2">

                    @if($heroDesktop || $heroMobile)
                        <picture class="absolute inset-0 w-full h-full">
                            @if($heroMobile)
                                <source media="(max-width: 767px)" srcset="/{{ $heroMobile }}">
                            @endif
                            @if($heroDesktop)
                                <source media="(min-width: 768px)" srcset="/{{ $heroDesktop }}">
                            @endif
                            <img src="/{{ $heroDesktop ?: $heroMobile }}"
                                 alt="{{ __('Мы не продаём — мы помогаем покупать!') }}"
                                 fetchpriority="high"
                                 class="w-full h-full object-cover object-[62%_center] group-hover:scale-[1.02] transition-transform duration-700 ease-out">
                        </picture>
                    @endif

                    <!-- Сланцево-синий градиент слева для чистой читаемости текста -->
                    <div class="pointer-events-none absolute inset-0"
                         style="background: linear-gradient(90deg, rgba(26, 52, 86, 0.86) 0%, rgba(36, 65, 100, 0.62) 42%, rgba(36, 64, 91, 0.06) 76%);"></div>

                    <div class="relative z-10 p-5 sm:p-8 lg:p-11 max-w-xl space-y-3 lg:space-y-4 text-white">
                        <h1 class="text-[26px] sm:text-4xl lg:text-[42px] font-bold tracking-tight leading-[1.06]">
                            {{ __('Мы не продаём — мы помогаем покупать!') }}
                        </h1>
                        <p class="hidden sm:block text-sm lg:text-[15px] text-white/90 leading-relaxed max-w-md">
                            {{ __('Exereto — это единая точка доступа к мировым брендам, ценам и скидкам, созданная в интересах покупателя.') }}
                        </p>
                        <div class="pt-1 lg:pt-2">
                            <span class="inline-flex items-center gap-2 px-5 py-2.5 lg:px-6 lg:py-3 rounded-full bg-white text-[#1d1d1f] text-[13px] lg:text-[14px] font-semibold shadow-sm group-hover:bg-[#f5f3ff] group-hover:text-[#6a47bd] transition-colors">
                                <span>{{ __('Начать поиск') }}</span>
                                <span aria-hidden="true" class="group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Правая карточка Graphite Aura: ДЕСКТОП (вертикальная) -->
                <button type="button"
                        @click="$dispatch('open-brands')"
                        class="hidden lg:flex aura-card flex-col justify-between p-6 xl:p-7 text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6a47bd] focus-visible:ring-offset-2">

                    <div class="flex items-center justify-between gap-2 w-full">
                        <span class="aura-muted-text text-[11px] font-bold uppercase tracking-[0.13em]">
                            {{ __('Каталог A–Z') }}
                        </span>
                        <span class="aura-card-circle w-9 h-9" aria-hidden="true">
                            <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8" />
                            </svg>
                        </span>
                    </div>

                    <div class="my-auto py-3">
                        <strong class="aura-count block text-[52px] xl:text-[68px] font-extrabold text-white whitespace-nowrap">
                            {{ $brandsFormatted }}<span class="aura-plus">+</span>
                        </strong>
                        <div class="text-[22px] font-semibold text-white mt-2 leading-tight">
                            {{ __('брендов') }}
                        </div>
                        <div class="aura-muted-text text-[13px] mt-1">
                            {{ __('Весь выбор — в одном месте') }}
                        </div>
                    </div>

                    <div class="w-full space-y-4">
                        <div class="flex items-center justify-between gap-3 py-3 border-y border-white/20">
                            <span class="aura-muted-text text-[12px] leading-snug">
                                {{ __('Актуальные скидки') }}
                            </span>
                            <b class="aura-accent-text text-[22px] xl:text-[24px] font-bold whitespace-nowrap tracking-tight">
                                {{ __('до') }} {{ $maxDisc }}%
                            </b>
                        </div>

                        <div class="flex items-center justify-between gap-2 text-[13px] font-bold text-white">
                            <span>{{ __('Смотреть все бренды') }}</span>
                            <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </div>
                    </div>
                </button>

                <!-- Правая карточка Graphite Aura: МОБИЛЬНЫЙ (компактная горизонтальная плашка) -->
                <button type="button"
                        @click="$dispatch('open-brands')"
                        class="grid lg:hidden aura-card grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)_34px] items-center gap-3 px-4 py-3.5 text-left cursor-pointer">
                    <div class="min-w-0">
                        <strong class="aura-count block text-[34px] sm:text-[40px] font-extrabold text-white whitespace-nowrap">
                            {{ $brandsFormatted }}<span class="aura-plus">+</span>
                        </strong>
                        <span class="aura-muted-text block text-[11px] mt-1 truncate">
                            {{ __('брендов') }} · A–Z
                        </span>
                    </div>

                    <div class="border-l border-white/20 pl-3 min-w-0">
                        <span class="aura-muted-text block text-[11px] leading-tight">
                            {{ __('Скидки до') }}
                        </span>
                        <strong class="aura-accent-text block text-[26px] sm:text-[30px] font-extrabold leading-tight tracking-tight whitespace-nowrap">
                            {{ $maxDisc }}%
                        </strong>
                    </div>

                    <span class="aura-card-circle w-[34px] h-[34px]" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </span>
                </button>

            </div>

            <!-- 2. ПОЛОСА ПРЕИМУЩЕСТВ (Десктоп: 4 колонки / Мобильный: компактные 3 колонки) -->
            <div class="hidden lg:grid lg:grid-cols-4 gap-6 py-4 border-b border-black/[0.06]">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#6a47bd] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-[#1d1d1f]">{{ __('Только официальные магазины') }}</div>
                        <div class="text-[12px] text-[#6e6e73] leading-snug mt-0.5">{{ __('Покупка на сайтах брендов без риска контрафакта') }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#6a47bd] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-[#1d1d1f]">{{ __('Выгодные цены') }}</div>
                        <div class="text-[12px] text-[#6e6e73] leading-snug mt-0.5">{{ __('Тысячи товаров со скидкой до') }} {{ $maxDisc }}%</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#6a47bd] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-[#1d1d1f]">{{ __('Огромный выбор') }}</div>
                        <div class="text-[12px] text-[#6e6e73] leading-snug mt-0.5">{{ __('Одежда, обувь, аксессуары и многое другое') }} · {{ $brandsFormatted }}+ {{ __('брендов') }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-[#6a47bd] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-[#1d1d1f]">{{ __('Быстрая доставка в Грузию') }}</div>
                        <div class="text-[12px] text-[#6e6e73] leading-snug mt-0.5">{{ __('Благодаря нашим партнёрам за считанные дни') }}</div>
                    </div>
                </div>
            </div>

            <!-- Компактная мобильная полоса преимуществ -->
            <div class="grid lg:hidden grid-cols-3 gap-2 py-2.5 border-b border-black/[0.06]">
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-4 h-4 text-[#6a47bd] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#4b4657] leading-tight">{{ __('Проверенные магазины') }}</span>
                </div>
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-4 h-4 text-[#6a47bd] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#4b4657] leading-tight">{{ __('Покупка напрямую') }}</span>
                </div>
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-4 h-4 text-[#6a47bd] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span class="text-[11px] font-medium text-[#4b4657] leading-tight">{{ __('Доставка в Грузию') }}</span>
                </div>
            </div>
        </section>

        <!-- 3. КАТЕГОРИИ (8 плиток + Все категории) -->
        @if(!empty($homeCategories))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-3 lg:space-y-0">
                <div class="flex lg:hidden items-center justify-between gap-2">
                    <h2 class="text-lg font-bold tracking-tight text-[#1d1d1f]">{{ __('Категории') }}</h2>
                    <button type="button"
                            @click="$dispatch('open-categories')"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-[#6a47bd] cursor-pointer">
                        <span>{{ __('Все') }}</span>
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </div>

                <div class="flex lg:grid lg:grid-cols-9 gap-3 lg:gap-4 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 pb-1 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($homeCategories as $tile)
                        @php
                            $tSlug = is_array($tile) ? ($tile['slug'] ?? '') : ($tile->slug ?? '');
                            $tLabel = is_array($tile) ? ($tile['label'] ?? $tile['title'] ?? $tile['name'] ?? '') : ($tile->label ?? $tile->title ?? $tile->name ?? '');
                            $tImg = is_array($tile) ? ($tile['image'] ?? null) : ($tile->image ?? null);
                            $tUrl = is_array($tile) ? ($tile['url'] ?? null) : ($tile->url ?? null);
                            if (empty($tUrl) && $tSlug !== '') {
                                $tUrl = route('feed-category.show', ['slug' => $tSlug]);
                            }
                        @endphp
                        <a href="{{ $tUrl ?: '#' }}"
                           @click="loading = true"
                           class="w-[96px] sm:w-[116px] lg:w-auto shrink-0 snap-start group flex flex-col items-center text-center gap-2">
                            <div class="w-full aspect-square rounded-2xl bg-[#e9eaee] group-hover:bg-[#e1dff0] p-3 flex items-center justify-center overflow-hidden transition-colors">
                                @if(!empty($tImg))
                                    <img src="{{ $tImg }}"
                                         alt="{{ $tLabel }}"
                                         loading="lazy"
                                         decoding="async"
                                         class="max-w-full max-h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-300">
                                @else
                                    <span class="text-sm font-bold text-[#6e6e73]">{{ mb_substr((string) $tLabel, 0, 2) }}</span>
                                @endif
                            </div>
                            <span class="text-[12px] lg:text-[13px] font-medium text-[#1d1d1f] group-hover:text-[#6a47bd] leading-tight line-clamp-2 transition-colors">
                                {{ $tLabel }}
                            </span>
                        </a>
                    @endforeach

                    <!-- 9-я плитка: Все категории (десктоп + конец ленты на мобилке) -->
                    <button type="button"
                            @click="$dispatch('open-categories')"
                            class="w-[96px] sm:w-[116px] lg:w-auto shrink-0 snap-start group flex flex-col items-center text-center gap-2 cursor-pointer">
                        <div class="w-full aspect-square rounded-2xl bg-white border border-black/[0.06] group-hover:border-[#6a47bd]/45 group-hover:bg-[#f6f3ff] flex items-center justify-center transition-colors">
                            <svg class="w-7 h-7 text-[#1d1d1f] group-hover:text-[#6a47bd] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="4" y="4" width="6.5" height="6.5" rx="1.5" />
                                <rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5" />
                                <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5" />
                                <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5" />
                            </svg>
                        </div>
                        <span class="text-[12px] lg:text-[13px] font-medium text-[#1d1d1f] group-hover:text-[#6a47bd] leading-tight transition-colors">
                            {{ __('Все категории') }}
                        </span>
                    </button>
                </div>
            </section>
        @endif

        <!-- 4. ПОПУЛЯРНЫЕ БРЕНДЫ (чистые белые пилюли + кнопка "...") -->
        @if(!empty($popularBrands))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg lg:text-2xl font-bold tracking-tight text-[#1d1d1f]">
                            {{ __('Популярные бренды') }}
                        </h2>
                        <p class="hidden sm:block text-sm text-zinc-500 mt-0.5">
                            {{ __('Оригинальные коллекции из официальных греческих магазинов и универмагов') }}
                        </p>
                    </div>
                    <button type="button"
                            @click="$dispatch('open-brands')"
                            class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#6a47bd] hover:text-[#5837a5] hover:underline shrink-0 cursor-pointer">
                        <span>{{ __('Все бренды') }} ({{ $brandsFormatted }})</span>
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </div>

                <div class="flex lg:grid lg:grid-cols-9 gap-2.5 lg:gap-3 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 pb-1 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($popularBrands as $brand)
                        <a href="{{ $brand['url'] }}"
                           @click="loading = true"
                           class="shrink-0 snap-start min-w-[116px] lg:min-w-0 h-12 lg:h-14 px-4 rounded-xl bg-white border border-black/[0.06] hover:border-[#6a47bd]/45 hover:text-[#6a47bd] shadow-[0_2px_8px_rgba(0,0,0,0.03)] flex items-center justify-center text-[13px] lg:text-[14px] font-bold tracking-tight text-[#1d1d1f] whitespace-nowrap transition-all">
                            <span class="truncate">{{ $brand['name'] }}</span>
                        </a>
                    @endforeach

                    <button type="button"
                            @click="$dispatch('open-brands')"
                            aria-label="{{ __('Все бренды') }}"
                            class="shrink-0 snap-start min-w-[68px] lg:min-w-0 h-12 lg:h-14 px-4 rounded-xl bg-white border border-black/[0.06] hover:border-[#6a47bd]/45 hover:text-[#6a47bd] shadow-[0_2px_8px_rgba(0,0,0,0.03)] flex items-center justify-center text-lg font-bold tracking-widest text-[#1d1d1f] cursor-pointer transition-all">
                        &bull;&bull;&bull;
                    </button>
                </div>
            </section>

            <!-- Шторка всех брендов A–Z -->
            <x-public-ui.brands-drawer :brands="$allBrandsIndex ?? []" />
        @endif

        <!-- 5. РЕКОМЕНДУЕМЫЕ ПРЕДЛОЖЕНИЯ (единая 6-колоночная сетка из app.css) -->
        @if(!empty($recommendedBlock))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4 lg:space-y-6">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg lg:text-2xl font-bold tracking-tight text-[#1d1d1f]">{{ $recommendedBlock['title'] }}</h2>
                        <p class="hidden sm:block text-sm text-zinc-500 mt-0.5">{{ $recommendedBlock['subtitle'] }}</p>
                    </div>
                    <a href="{{ $recommendedBlock['url'] }}"
                       @click="loading = true"
                       class="inline-flex items-center gap-1 text-xs sm:text-sm font-semibold text-[#6a47bd] hover:text-[#5837a5] hover:underline shrink-0">
                        <span>{{ $recommendedBlock['cta'] }}</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>

                <div class="apple-product-grid apple-product-grid--6 apple-product-grid--carousel scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
                    @foreach($recommendedBlock['products'] as $index => $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" :is-first="$index === 0" />
                        </div>
                    @endforeach

                    <a href="{{ $recommendedBlock['url'] }}"
                       @click="loading = true"
                       class="lg:hidden w-[46%] sm:w-[31%] shrink-0 snap-start flex flex-col items-center justify-center text-center gap-1.5 rounded-3xl px-4 py-8 hover:bg-white/60 active:bg-white transition-colors group">
                        <span class="text-[16px] font-semibold leading-snug text-[#6a47bd] group-hover:underline">
                            {{ $recommendedBlock['cta'] }}
                        </span>
                        @if(!empty($recommendedBlock['count']))
                            <span class="text-[15px] font-medium text-[#1d1d1f] tabular-nums">
                                {{ number_format((int) $recommendedBlock['count']) }}
                            </span>
                        @endif
                    </a>
                </div>
            </section>
        @endif

        <!-- Мессенджеры -->
        <section class="max-w-3xl mx-auto px-6 py-4 text-center space-y-4">
            <p class="app-text-body">
                {{ __('Мы на связи и готовы помочь с любым вопросом по выкупу и доставке в Грузию') }}
            </p>
            <x-messenger-row align="justify-center" />
        </section>

    </div>

</x-layouts.public>