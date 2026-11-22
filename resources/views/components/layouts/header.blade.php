@php
    $isHomePage = request()->routeIs(['home', '*.home']);
    $headerCategories = app(\App\Services\FeedCategoryTiles::class)->get();
    $photoSearchEnabled = (bool) config('services.gemini.vision_enabled', true)
        && filled(config('services.gemini.vision_api_key'));
@endphp

<!-- На мобильном (< lg): умная липкая шапка. На десктопе (>= lg): статичная -->
<header class="sticky top-0 lg:static z-40 text-[#1d1d1f] w-full transition-transform duration-300 ease-out will-change-transform"
        x-data="{
            hidden: false,
            scrolled: false,
            lastY: 0,
            onScroll() {
                if (window.innerWidth >= 1024) {
                    this.hidden = false;
                    this.scrolled = false;
                    return;
                }

                const y = Math.max(0, window.scrollY || window.pageYOffset);
                this.scrolled = y > 8;

                const inputFocused = document.activeElement
                    && document.activeElement.tagName === 'INPUT'
                    && this.$el.contains(document.activeElement);

                if (y <= 80 || categoriesDrawerOpen || searchOpen || inputFocused) {
                    this.hidden = false;
                    this.lastY = y;
                    return;
                }

                const delta = y - this.lastY;
                if (Math.abs(delta) < 10) return;

                this.hidden = delta > 0;
                this.lastY = y;
            }
        }"
        @scroll.window.passive="onScroll()"
        @resize.window.passive="if (window.innerWidth >= 1024) { hidden = false; scrolled = false; }"
        :class="[
            hidden ? '-translate-y-full lg:translate-y-0' : 'translate-y-0',
            scrolled ? 'bg-[#f5f5f7]/90 backdrop-blur-md lg:bg-transparent lg:backdrop-blur-none' : 'bg-transparent'
        ]">

    <div class="max-w-[96rem] mx-auto px-6 sm:px-6 lg:px-8 w-full">

        <!-- МОБИЛЬНАЯ ШАПКА (< lg): лёгкий триггер полноэкранного поиска + поиск по фото + категории -->
        <div class="relative flex lg:hidden items-center gap-5 pt-6 pb-2.5">
            <a href="{{ route('home') }}" class="text-[28px] leading-none font-semibold tracking-tight text-[#1d1d1f] shrink-0">
                exereto
            </a>

            <div class="flex-1 min-w-0 rounded-full bg-white shadow-[0_4px_20px_rgba(0,0,0,0.06)] border border-black/[0.05]">
                <div class="flex items-center gap-1 pl-3.5 pr-1.5 h-12">
                    <button type="button"
                            @click="$dispatch('open-search')"
                            aria-label="{{ __('Поиск') }}"
                            class="flex-1 min-w-0 flex items-center gap-2 h-full text-left cursor-pointer focus:outline-none">
                        <svg class="w-5 h-5 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        @if(filled(request('q')))
                            <span class="text-[15px] font-medium text-[#1d1d1f] truncate">{{ request('q') }}</span>
                        @else
                            <span class="text-[15px] font-medium text-[#6e6e73] truncate">{{ __('Что вы ищете?') }}</span>
                        @endif
                    </button>

                    @if($photoSearchEnabled)
                        <button type="button"
                                @click.stop="$dispatch('open-photo-search')"
                                aria-label="{{ __('Поиск по фото') }}"
                                title="{{ __('Поиск по фото') }}"
                                class="p-2 rounded-full text-[#6e6e73] hover:text-[#0071e3] hover:bg-[#f5f5f7] transition-colors shrink-0 cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    @endif

                    <button type="button"
                            @click.stop="$dispatch('open-categories')"
                            aria-label="{{ __('Категории') }}"
                            class="p-2 rounded-full text-[#1d1d1f] hover:bg-[#f5f5f7] transition-colors shrink-0 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- ДЕСКТОПНАЯ ШАПКА (>= lg) -->
        <div class="hidden lg:flex items-center justify-center gap-8 h-20">
            @unless($isHomePage)
                <a href="{{ route('home') }}" class="apple-section-title !text-xl flex items-center shrink-0">
                    exereto
                </a>
            @endunless

            <nav class="flex items-center space-x-1 shrink-0">
                <a href="{{ route('how-it-works') }}" class="apple-nav-link {{ request()->routeIs(['how-it-works', '*.how-it-works']) ? 'active' : '' }}">
                    {{ __('Как это работает') }}
                </a>
                <a href="{{ route('buy-for-me') }}" class="apple-nav-link {{ request()->routeIs(['buy-for-me', '*.buy-for-me']) ? 'active' : '' }}">
                    {{ __('Выкупите за меня') }}
                </a>
                <a href="{{ route('forwarding') }}" class="apple-nav-link {{ request()->routeIs(['forwarding', '*.forwarding']) ? 'active' : '' }}">
                    {{ __('Форвардинг') }}
                </a>
                <a href="{{ route('help') }}" class="apple-nav-link {{ request()->routeIs(['help', '*.help']) ? 'active' : '' }}">
                    {{ __('Помощь / FAQ') }}
                </a>
            </nav>

            <div class="flex items-center gap-4 shrink-0">
                @auth
                    <a href="{{ route('dashboard') }}" class="apple-nav-link">
                        {{ __('Личный кабинет') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="apple-nav-link">
                        {{ __('Войти') }}
                    </a>
                @endauth

                <x-language-switcher theme="light" />
            </div>
        </div>

    </div>
</header>

<!-- Вынесены из <header>, чтобы CSS transform при скрытии шапки не ломал position: fixed -->
<x-public-ui.categories-drawer :categories="$headerCategories" />
<x-layouts.search-sheet />
<x-public-ui.photo-search-modal />