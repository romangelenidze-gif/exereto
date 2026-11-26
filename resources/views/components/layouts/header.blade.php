@php
    $headerCategories = app(\App\Services\FeedCategoryTiles::class)->get();
@endphp

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

    <div class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 w-full">

        <!-- МОБИЛЬНАЯ ШАПКА (< lg) -->
        <div class="relative flex lg:hidden items-center gap-4 pt-5 pb-2.5">
            <a href="{{ route('home') }}" class="text-[28px] leading-none font-bold tracking-tight text-[#1d1d1f] shrink-0">
                exereto
            </a>

            <div data-search-trigger="mobile"
                 class="flex-1 min-w-0 rounded-full bg-white shadow-[0_4px_20px_rgba(0,0,0,0.06)] border border-black/[0.06]">
                <div class="flex items-center gap-2 pl-4 pr-1.5 h-12">
                    <button type="button"
                            @click="$dispatch('open-search', { rect: $el.closest('[data-search-trigger]').getBoundingClientRect() })"
                            aria-label="{{ __('Поиск') }}"
                            class="flex-1 min-w-0 flex items-center gap-2.5 h-full text-left cursor-pointer focus:outline-none">
                        <svg class="w-5 h-5 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        @if(filled(request('q')))
                            <span class="home-header-search-text home-header-search-text--filled truncate">{{ request('q') }}</span>
                        @else
                            <span class="home-header-search-text truncate">{{ __('Найти товар или бренд') }}</span>
                        @endif
                    </button>

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
        <div class="hidden lg:flex items-center justify-between gap-6 h-20">
            <div class="flex items-center gap-6 flex-1 min-w-0 max-w-3xl">
                <a href="{{ route('home') }}" class="text-[28px] leading-none font-bold tracking-tight text-[#1d1d1f] shrink-0 hover:opacity-85 transition-opacity">
                    exereto
                </a>

                <div data-search-trigger="desktop"
                     class="flex-1 min-w-0 max-w-xl rounded-full bg-white shadow-[0_2px_14px_rgba(0,0,0,0.05)] border border-black/[0.06] hover:border-[#0071e3]/40 transition-colors">
                    <div class="flex items-center gap-2 pl-5 pr-2 h-12">
                        <button type="button"
                                @click="$dispatch('open-search', { rect: $el.closest('[data-search-trigger]').getBoundingClientRect() })"
                                aria-label="{{ __('Поиск') }}"
                                class="flex-1 min-w-0 flex items-center gap-2.5 h-full text-left cursor-pointer focus:outline-none">
                            <svg class="w-5 h-5 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            @if(filled(request('q')))
                                <span class="home-header-search-text home-header-search-text--filled truncate">{{ request('q') }}</span>
                            @else
                                <span class="home-header-search-text truncate">{{ __('Что вы ищете?') }}</span>
                            @endif
                        </button>

                        <span class="h-5 w-px bg-black/[0.08] shrink-0"></span>

                        <button type="button"
                                @click.stop="$dispatch('open-categories')"
                                aria-label="{{ __('Категории') }}"
                                class="home-header-cat-btn shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <span>{{ __('Категории') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            @php $showDormant = (bool) config('services.exereto.show_dormant', false); @endphp

            <div class="flex items-center gap-5 shrink-0">
                <nav class="flex items-center space-x-1 shrink-0">
                    @if($showDormant)
                        <a href="{{ route('how-it-works') }}" class="apple-nav-link {{ request()->routeIs(['how-it-works', '*.how-it-works']) ? 'active' : '' }}">
                            {{ __('Как это работает') }}
                        </a>
                        <a href="{{ route('buy-for-me') }}" class="apple-nav-link {{ request()->routeIs(['buy-for-me', '*.buy-for-me']) ? 'active' : '' }}">
                            {{ __('Выкупите за меня') }}
                        </a>
                        <a href="{{ route('forwarding') }}" class="apple-nav-link {{ request()->routeIs(['forwarding', '*.forwarding']) ? 'active' : '' }}">
                            {{ __('Форвардинг') }}
                        </a>
                    @endif
                    <a href="{{ route('help') }}" class="apple-nav-link {{ request()->routeIs(['help', '*.help']) ? 'active' : '' }}">
                        {{ __('Помощь') }}
                    </a>
                </nav>

                <div class="flex items-center gap-3 shrink-0">
                    @if($showDormant)
                        @auth
                            <a href="{{ route('dashboard') }}" class="apple-nav-link">
                                {{ __('Личный кабинет') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="apple-nav-link">
                                {{ __('Войти') }}
                            </a>
                        @endauth
                    @endif

                    <x-language-switcher theme="light" />
                </div>
            </div>
        </div>

    </div>
</header>

<x-public-ui.categories-drawer :categories="$headerCategories" />
<x-layouts.search-sheet />