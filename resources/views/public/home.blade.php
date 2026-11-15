<x-layouts.public 
    title="{{ __('Брендовые товары из Греции с доставкой в Грузию') }} | Exereto"
    description="{{ __('Оригинальная одежда, обувь, парфюмерия и аксессуары из официальных магазинов Греции с доставкой в Грузию.') }}"
>

    <div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-20 space-y-14 relative"
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
        
        <!-- Поисковый Spotlight с меню категорий -->
        <x-public-ui.search-spotlight :categories="$tiles ?? []" />

        <!-- Сетка популярных категорий с картинками -->
        @if(isset($tiles) && $tiles->isNotEmpty())
            <x-public-ui.categories-grid :categories="$tiles" />
        @endif

        <!-- Популярные бренды и сервисы выкупа/форвардинга -->
        @if(isset($brands) && count($brands) > 0)
            <section class="w-full px-4 sm:px-6 lg:px-10 text-center space-y-5">
                <div class="space-y-1.5 max-w-3xl mx-auto">
                    <h2 class="apple-section-title">
                        {{ __('Популярные бренды') }}
                    </h2>
                    <p class="apple-section-subtitle">
                        {{ __('Оригинальные коллекции из официальных греческих магазинов и универмагов') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2 max-w-5xl mx-auto">
                    @foreach($brands as $brand)
                        <a href="{{ $brand['url'] }}" class="apple-brand-pill">
                            {{ $brand['name'] }}
                        </a>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <a href="{{ route('buy-for-me') }}" class="apple-outline-pill">
                        {{ __('Выкупите за меня') }}
                    </a>
                    <a href="{{ route('forwarding') }}?type=forwarding" class="apple-outline-pill">
                        {{ __('Форвардинг') }}
                    </a>
                </div>
            </section>
        @endif

        <!-- Мультибрендовые рекомендации (на всю ширину) -->
        @if(isset($popularProducts) && $popularProducts->isNotEmpty())
        <section class="w-full px-4 sm:px-6 lg:px-10 2xl:px-14 space-y-6">
            <div class="flex flex-wrap justify-between items-end gap-4">
                <div>
                    <h2 class="apple-section-title">{{ __('Рекомендуемые предложения') }}</h2>
                    <p class="apple-section-subtitle mt-1">{{ __('Подборка оригинальных моделей разных брендов') }}</p>
                </div>
                <a href="{{ route('feed-category.show', ['slug' => 'sales-offers']) }}" class="app-text-body font-semibold text-[#0071e3] hover:underline">
                    {{ __('Все акции') }} &rarr;
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-6">
                @foreach($popularProducts as $index => $product)
                    <x-public-ui.product-card :product="$product" :is-first="$index === 0" />
                @endforeach
            </div>
        </section>
        @endif

        <!-- Скидки и специальные предложения (на всю ширину) -->
        @if(isset($discountProducts) && $discountProducts->isNotEmpty())
        <section class="w-full px-4 sm:px-6 lg:px-10 2xl:px-14 space-y-6">
            <div class="flex flex-wrap justify-between items-end gap-4">
                <div>
                    <span class="app-badge-text uppercase font-semibold text-red-600">Special Sales</span>
                    <h2 class="apple-section-title mt-1">{{ __('Скидки дня') }}</h2>
                </div>
                <a href="{{ route('feed-category.show', ['slug' => 'sales-offers']) }}" class="app-text-body font-semibold text-[#0071e3] hover:underline">
                    {{ __('Смотреть все') }} &rarr;
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-6">
                @foreach($discountProducts as $product)
                    <x-public-ui.product-card :product="$product" />
                @endforeach
            </div>
        </section>
        @endif

        <!-- Мессенджеры -->
        <section class="max-w-3xl mx-auto px-6 py-8 text-center space-y-4">
            <div class="space-y-3 pt-2">
                <p class="app-text-body">
                    {{ __('Мы на связи и готовы помочь с любым вопросом по выкупу и доставке в Грузию') }}
                </p>
                <x-messenger-row align="justify-center" />
            </div>
        </section>

    </div>

</x-layouts.public>