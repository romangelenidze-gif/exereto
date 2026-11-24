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

    $brandsDisplay = '1 000';
    $maxDisc = (int) ($maxCatalogDiscount ?? 70);
@endphp

    <div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-16 pt-2 lg:pt-4 space-y-10 lg:space-y-14 relative"
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

        <!-- 1. ПЕРВЫЙ ЭКРАН: HERO-БАННЕР (506px, текст внизу слева) + GRAPHITE AURA (506px) -->
        <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6 lg:space-y-7">
            <div class="grid grid-cols-1 lg:grid-cols-[2.45fr_1fr] gap-3 lg:gap-4 items-stretch">

                <!-- Главный баннер -->
                <div role="button"
                     tabindex="0"
                     @click="$dispatch('open-search')"
                     @keydown.enter.prevent="$dispatch('open-search')"
                     @keydown.space.prevent="$dispatch('open-search')"
                     class="relative h-[280px] sm:h-[360px] lg:h-[506px] rounded-[20px] overflow-hidden bg-[#1f3a5c] cursor-pointer group select-none flex items-end focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0071e3] focus-visible:ring-offset-2">

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
                                 class="w-full h-full object-cover object-[62%_25%] group-hover:scale-[1.02] transition-transform duration-700 ease-out">
                        </picture>
                    @endif

                    <!-- Двойной градиент (слева и снизу) для чистой читаемости нижнего контента -->
                    <div class="pointer-events-none absolute inset-0"
                         style="background: linear-gradient(90deg, rgba(24, 48, 80, 0.88) 0%, rgba(34, 62, 96, 0.64) 44%, rgba(36, 64, 91, 0.05) 76%), linear-gradient(0deg, rgba(18, 34, 58, 0.72) 0%, rgba(18, 34, 58, 0.18) 45%, transparent 72%);"></div>

                    <div class="relative z-10 p-6 sm:p-9 lg:px-14 lg:pb-12 lg:pt-10 max-w-2xl space-y-3.5 lg:space-y-5">
                        <h1 class="home-hero-title">
                            {{ __('Мы не продаём — мы помогаем покупать!') }}
                        </h1>
                        <p class="hidden sm:block home-hero-subtitle max-w-xl">
                            {{ __('Exereto — это единая точка доступа к мировым брендам, ценам и скидкам, созданная в интересах покупателя.') }}
                        </p>
                        <div class="pt-1 lg:pt-2">
                            <span class="home-hero-cta group-hover:!bg-[#f0f7ff] group-hover:!text-[#0071e3]">
                                <span>{{ __('Начать поиск') }}</span>
                                <span aria-hidden="true" class="group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Правая карточка Graphite Aura: ДЕСКТОП (506px) -->
                <button type="button"
                        @click="$dispatch('open-brands')"
                        class="hidden lg:flex lg:h-[506px] aura-card flex-col justify-between p-8 xl:p-9 text-left cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0071e3] focus-visible:ring-offset-2">

                    <div class="flex items-center justify-between gap-2 w-full">
                        <span class="aura-eyebrow">
                            {{ __('Каталог A–Z') }}
                        </span>
                        <span class="aura-card-circle w-11 h-11" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8" />
                            </svg>
                        </span>
                    </div>

                    <div class="my-auto py-4">
                        <strong class="aura-count block text-[60px] xl:text-[80px] font-extrabold text-white whitespace-nowrap">
                            {{ $brandsDisplay }}<span class="aura-plus">+</span>
                        </strong>
                        <div class="aura-title mt-3">
                            {{ __('брендов') }}
                        </div>
                        <div class="aura-subtitle mt-1.5">
                            {{ __('Весь выбор — в одном месте') }}
                        </div>
                    </div>

                    <div class="w-full space-y-5">
                        <div class="flex items-center justify-between gap-3 py-4 border-y border-white/20">
                            <span class="aura-deal-label">
                                {{ __('Актуальные скидки') }}
                            </span>
                            <b class="aura-deal-value">
                                {{ __('до') }} {{ $maxDisc }}%
                            </b>
                        </div>

                        <div class="aura-footer-cta flex items-center justify-between gap-2">
                            <span>{{ __('Смотреть все бренды') }}</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </div>
                    </div>
                </button>

                <!-- Правая карточка Graphite Aura: МОБИЛЬНЫЙ -->
                <button type="button"
                        @click="$dispatch('open-brands')"
                        class="grid lg:hidden aura-card grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)_36px] items-center gap-3 px-4 py-4 text-left cursor-pointer">
                    <div class="min-w-0">
                        <strong class="aura-count block text-[36px] sm:text-[42px] font-extrabold text-white whitespace-nowrap">
                            {{ $brandsDisplay }}<span class="aura-plus">+</span>
                        </strong>
                        <span class="aura-muted-text block text-[13px] mt-1 truncate">
                            {{ __('брендов') }} · A–Z
                        </span>
                    </div>

                    <div class="border-l border-white/20 pl-3 min-w-0">
                        <span class="aura-muted-text block text-[13px] leading-tight">
                            {{ __('Скидки до') }}
                        </span>
                        <strong class="aura-accent-text block text-[28px] sm:text-[32px] font-extrabold leading-tight tracking-tight whitespace-nowrap">
                            {{ $maxDisc }}%
                        </strong>
                    </div>

                    <span class="aura-card-circle w-9 h-9" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </span>
                </button>

            </div>

            <!-- 2. ПОЛОСА ПРЕИМУЩЕСТВ -->
            <div class="hidden lg:grid lg:grid-cols-4 gap-8 py-5 border-b border-black/[0.06]">
                <div class="flex items-start gap-3.5">
                    <svg class="w-8 h-8 text-[#0071e3] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="home-benefit-title">{{ __('Только официальные магазины') }}</div>
                        <div class="home-benefit-desc">{{ __('Покупка на сайтах брендов без риска контрафакта') }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <svg class="w-8 h-8 text-[#0071e3] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="home-benefit-title">{{ __('Выгодные цены') }}</div>
                        <div class="home-benefit-desc">{{ __('Тысячи товаров со скидкой до') }} {{ $maxDisc }}%</div>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <svg class="w-8 h-8 text-[#0071e3] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="home-benefit-title">{{ __('Огромный выбор') }}</div>
                        <div class="home-benefit-desc">{{ __('Одежда, обувь, аксессуары и многое другое') }} · {{ $brandsDisplay }}+ {{ __('брендов') }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3.5">
                    <svg class="w-8 h-8 text-[#0071e3] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <div class="min-w-0">
                        <div class="home-benefit-title">{{ __('Быстрая доставка в Грузию') }}</div>
                        <div class="home-benefit-desc">{{ __('Благодаря нашим партнёрам за считанные дни') }}</div>
                    </div>
                </div>
            </div>

            <!-- Компактная мобильная полоса преимуществ -->
            <div class="grid lg:hidden grid-cols-3 gap-2.5 py-3 border-b border-black/[0.06]">
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-6 h-6 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span class="home-benefit-mobile-text">{{ __('Проверенные магазины') }}</span>
                </div>
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-6 h-6 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    <span class="home-benefit-mobile-text">{{ __('Покупка напрямую') }}</span>
                </div>
                <div class="flex items-center gap-1.5 min-w-0">
                    <svg class="w-6 h-6 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    <span class="home-benefit-mobile-text">{{ __('Доставка в Грузию') }}</span>
                </div>
            </div>
        </section>

        <!-- 3. КАТЕГОРИИ (8 плиток + Все категории) -->
        @if(!empty($homeCategories))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4 lg:space-y-0">
                <div class="flex lg:hidden items-baseline justify-between gap-2">
                    <h2 class="apple-section-title">{{ __('Категории') }}</h2>
                    <button type="button"
                            @click="$dispatch('open-categories')"
                            class="home-section-link">
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
                            if (empty($tUrl) &&$tSlug !== '') {
                                $tUrl = route('feed-category.show', ['slug' =>$tSlug]);
                            }
                        @endphp
                        <a href="{{ $tUrl ?: '#' }}"
                           @click="loading = true"
                           class="w-[104px] sm:w-[120px] lg:w-auto shrink-0 snap-start group flex flex-col items-center text-center gap-2.5">
                            <div class="w-full aspect-square rounded-2xl bg-[#e9eaee] group-hover:bg-[#e5f0fc] p-3 flex items-center justify-center overflow-hidden transition-colors">
                                @if(!empty($tImg))
                                    <img src="{{ $tImg }}"
                                         alt="{{ $tLabel }}"
                                         loading="lazy"
                                         decoding="async"
                                         class="max-w-full max-h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-300">
                                @else
                                    <span class="app-text-sub font-semibold">{{ mb_substr((string) $tLabel, 0, 2) }}</span>
                                @endif
                            </div>
                            <span class="home-category-label group-hover:!text-[#0071e3] line-clamp-2">
                                {{ $tLabel }}
                            </span>
                        </a>
                    @endforeach

                    <!-- 9-я плитка: Все категории -->
                    <button type="button"
                            @click="$dispatch('open-categories')"
                            class="w-[104px] sm:w-[120px] lg:w-auto shrink-0 snap-start group flex flex-col items-center text-center gap-2.5 cursor-pointer">
                        <div class="w-full aspect-square rounded-2xl bg-white border border-black/[0.06] group-hover:border-[#0071e3]/45 group-hover:bg-[#f0f7ff] flex items-center justify-center transition-colors">
                            <svg class="w-8 h-8 text-[#1d1d1f] group-hover:text-[#0071e3] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <rect x="4" y="4" width="6.5" height="6.5" rx="1.5" />
                                <rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5" />
                                <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5" />
                                <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5" />
                            </svg>
                        </div>
                        <span class="home-category-label group-hover:!text-[#0071e3]">
                            {{ __('Все категории') }}
                        </span>
                    </button>
                </div>
            </section>
        @endif

        <!-- 4. ПОПУЛЯРНЫЕ БРЕНДЫ -->
        @if(!empty($popularBrands))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="apple-section-title">
                            {{ __('Популярные бренды') }}
                        </h2>
                        <p class="hidden sm:block apple-section-subtitle mt-1">
                            {{ __('Оригинальные коллекции из официальных греческих магазинов и универмагов') }}
                        </p>
                    </div>
                    <button type="button"
                            @click="$dispatch('open-brands')"
                            class="home-section-link shrink-0">
                        <span>{{ __('Все бренды') }} ({{ $brandsDisplay }}+)</span>
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </div>

                <div class="flex lg:grid lg:grid-cols-9 gap-2.5 lg:gap-3.5 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 pb-1 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($popularBrands as $brand)
                        <a href="{{ $brand['url'] }}"
                           @click="loading = true"
                           class="home-brand-pill shrink-0 snap-start min-w-[132px] lg:min-w-0">
                            <span class="truncate">{{ $brand['name'] }}</span>
                        </a>
                    @endforeach

                    <button type="button"
                            @click="$dispatch('open-brands')"
                            aria-label="{{ __('Все бренды') }}"
                            class="home-brand-pill shrink-0 snap-start min-w-[76px] lg:min-w-0 tracking-widest">
                        &bull;&bull;&bull;
                    </button>
                </div>
            </section>

            <!-- Шторка всех брендов A–Z -->
            <x-public-ui.brands-drawer :brands="$allBrandsIndex ?? []" />
        @endif

        <!-- 5. РЕКОМЕНДУЕМЫЕ ПРЕДЛОЖЕНИЯ -->
        @if(!empty($recommendedBlock))
            <section class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 space-y-5 lg:space-y-6">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <h2 class="apple-section-title">{{ $recommendedBlock['title'] }}</h2>
                        <p class="hidden sm:block apple-section-subtitle mt-1">{{ $recommendedBlock['subtitle'] }}</p>
                    </div>
                    <a href="{{ $recommendedBlock['url'] }}"
                       @click="loading = true"
                       class="home-section-link shrink-0">
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
                        <span class="app-text-body !font-semibold !text-[#0071e3] group-hover:underline">
                            {{ $recommendedBlock['cta'] }}
                        </span>
                        @if(!empty($recommendedBlock['count']))
                            <span class="app-text-sub !font-medium !text-[#1d1d1f] tabular-nums">
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