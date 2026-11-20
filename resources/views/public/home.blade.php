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
                    <a href="{{ $heroBlock['url'] }}" class="text-sm font-semibold text-[#0071e3] hover:underline shrink-0">
                        {{ $heroBlock['cta'] }} &rarr;
                    </a>
                </div>

                <!-- Мобильный: горизонтальный свайп с точным левым отступом (scroll-px); Десктоп: сетка по 4 в ряд -->
                <div class="flex lg:grid lg:grid-cols-4 gap-3 lg:gap-5 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($heroBlock['products'] as $index => $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" :is-first="$index === 0" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Популярные бренды -->
        @if(isset($brands) && count($brands) > 0)
            <section class="max-w-5xl mx-auto px-4 sm:px-6 text-center space-y-4">
                <div class="space-y-1 max-w-3xl mx-auto">
                    <h2 class="text-xl lg:text-2xl font-bold tracking-tight text-[#1d1d1f]">
                        {{ __('Популярные бренды') }}
                    </h2>
                    <p class="text-sm text-zinc-500">
                        {{ __('Оригинальные коллекции из официальных греческих магазинов и универмагов') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    @foreach($brands as $brand)
                        <a href="{{ $brand['url'] }}" class="apple-brand-pill">
                            {{ $brand['name'] }}
                        </a>
                    @endforeach
                </div>
            </section>
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
                    <a href="{{ $block['url'] }}" class="text-sm font-semibold text-[#0071e3] hover:underline shrink-0">
                        {{ $block['cta'] }} &rarr;
                    </a>
                </div>

                <div class="flex lg:grid lg:grid-cols-4 gap-3 lg:gap-5 overflow-x-auto lg:overflow-visible snap-x snap-mandatory scroll-px-4 sm:scroll-px-6 lg:scroll-px-0 pb-2 lg:pb-0 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($block['products'] as $product)
                        <div class="w-[46%] sm:w-[31%] lg:w-auto shrink-0 snap-start">
                            <x-public-ui.product-card :product="$product" />
                        </div>
                    @endforeach
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