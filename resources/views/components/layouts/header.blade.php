@php
    $isHomePage = request()->routeIs('home');
    $headerCategories = app(\App\Services\FeedCategoryTiles::class)->get();
@endphp

<!-- Статичный хедер -->
<header class="text-[#1d1d1f] relative w-full z-40">
    <div class="max-w-[96rem] mx-auto px-3.5 sm:px-6 lg:px-8 w-full">

        <!-- МОБИЛЬНАЯ ШАПКА (< lg): воздух сверху, логотип слева + широкий плоский поиск без тени и гамбургер категорий -->
        <div class="flex lg:hidden items-center gap-2.5 pt-4 pb-2.5"
             x-data="{
                query: @js((string) request('q', '')),
                suggestions: [],
                openSuggestions: false,
                activeIndex: -1,
                abortCtrl: null,
                fetchSuggestions() {
                    this.activeIndex = -1;
                    if (this.query.trim().length < 2) {
                        this.suggestions = [];
                        this.openSuggestions = false;
                        return;
                    }
                    if (this.abortCtrl) this.abortCtrl.abort();
                    this.abortCtrl = new AbortController();

                    fetch(`{{ route('api.autocomplete') }}?q=${encodeURIComponent(this.query.trim())}`, {
                        signal: this.abortCtrl.signal,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept-Language': '{{ app()->getLocale() }}'
                        },
                        credentials: 'same-origin'
                    })
                    .then(r => r.json())
                    .then(data => {
                        this.suggestions = data.suggestions || [];
                        this.activeIndex = -1;
                        this.openSuggestions = this.suggestions.length > 0;
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            this.suggestions = [];
                            this.openSuggestions = false;
                        }
                    });
                },
                selectSuggestion(item) {
                    this.query = item.label || item.value;
                    this.openSuggestions = false;
                    this.$dispatch('search-loading');
                    window.location.href = item.url || `{{ route('product-search') }}?q=${encodeURIComponent(item.value)}`;
                }
             }"
             @click.outside="openSuggestions = false"
             @scroll.window="openSuggestions = false">

            <a href="{{ route('home') }}" class="text-[23px] leading-none font-bold tracking-tight text-[#1d1d1f] shrink-0">
                exereto
            </a>

            <div class="relative flex-1 min-w-0">
                <form action="{{ route('product-search') }}" method="GET"
                      class="flex items-center gap-2 bg-white rounded-full pl-3.5 pr-1.5 h-11">
                    <button type="submit" aria-label="{{ __('Найти') }}" class="text-zinc-400 hover:text-[#0071e3] shrink-0 cursor-pointer">
                        <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>

                    <input type="text"
                           name="q"
                           x-model="query"
                           @focus="if (suggestions.length > 0) openSuggestions = true"
                           @input.debounce.180ms="fetchSuggestions()"
                           @keydown.escape="openSuggestions = false"
                           placeholder="{{ __('Поиск') }}"
                           required
                           maxlength="200"
                           autocomplete="off"
                           spellcheck="false"
                           class="w-full min-w-0 bg-transparent border-none text-[15px] text-[#1d1d1f] placeholder:text-zinc-400 focus:outline-none focus:ring-0">

                    <button type="button"
                            @click="$dispatch('open-categories')"
                            aria-label="{{ __('Категории') }}"
                            class="p-2 rounded-full text-[#1d1d1f] hover:bg-black/[0.05] transition-colors shrink-0 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </form>

                <!-- Подсказки автокомплита под мобильной шапкой -->
                <div x-show="openSuggestions"
                     x-cloak
                     role="listbox"
                     class="fixed left-3.5 right-3.5 top-[72px] bg-white/95 backdrop-blur-xl rounded-2xl shadow-xl border border-black/[0.06] overflow-y-auto max-h-80 z-50 text-left">
                    <template x-for="item in suggestions" :key="item.url || item.value">
                        <div role="option"
                             @click="selectSuggestion(item)"
                             class="px-4 py-2.5 flex items-center justify-between gap-2.5 cursor-pointer hover:bg-black/[0.04] border-b border-black/[0.04] last:border-b-0">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <img x-show="item.image" :src="item.image" alt="" class="w-8 h-8 rounded-lg object-cover bg-[#f5f5f7] shrink-0">
                                <span class="text-sm font-medium text-[#1d1d1f] truncate" x-text="item.label"></span>
                            </div>
                            <span class="text-[11px] px-2 py-0.5 bg-[#0071e3]/10 text-[#0071e3] rounded-full font-semibold shrink-0" x-text="item.badge"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ДЕСКТОПНАЯ ШАПКА (>= lg) — без изменений -->
        <div class="hidden lg:flex items-center justify-center gap-8 h-20">
            @unless($isHomePage)
                <a href="{{ route('home') }}" class="apple-section-title !text-xl flex items-center shrink-0">
                    exereto
                </a>
            @endunless

            <nav class="flex items-center space-x-1 shrink-0">
                <a href="{{ route('how-it-works') }}" class="apple-nav-link {{ request()->routeIs('how-it-works') ? 'active' : '' }}">
                    {{ __('Как это работает') }}
                </a>
                <a href="{{ route('buy-for-me') }}" class="apple-nav-link {{ request()->routeIs('buy-for-me') ? 'active' : '' }}">
                    {{ __('Выкупите за меня') }}
                </a>
                <a href="{{ route('forwarding') }}" class="apple-nav-link {{ request()->routeIs('forwarding') ? 'active' : '' }}">
                    {{ __('Форвардинг') }}
                </a>
                <a href="{{ route('help') }}" class="apple-nav-link {{ request()->routeIs('help') ? 'active' : '' }}">
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

    <!-- Единая выдвижная шторка категорий каталога -->
    <x-public-ui.categories-drawer :categories="$headerCategories" />
    <x-layouts.search-sheet />
</header>