@php
    $quickQueries = \Illuminate\Support\Facades\Cache::remember(
        'search.quick_queries.v1',
        now()->addMinutes(30),
        function (): array {
            try {
                $rows = \Illuminate\Support\Facades\DB::table('learned_autocomplete')
                    ->orderByDesc('hits')
                    ->limit(8)
                    ->pluck('query')
                    ->filter(fn ($q) => is_string($q) && trim($q) !== '')
                    ->values()
                    ->all();

                if ($rows !== []) {
                    return $rows;
                }
            } catch (\Throwable) {
            }

            return ['adidas', 'New Balance', 'Guess', 'Tommy Hilfiger', 'LEGO', 'Sephora'];
        }
    );
@endphp

<template x-teleport="body">
    <div x-data="{
            query: @js((string) request('q', '')),
            suggestions: [],
            activeIndex: -1,
            recentItems: [],
            loadingSuggestions: false,
            loading: false,
            abortCtrl: null,
            init() {
                this.loadRecent();
            },
            loadRecent() {
                this.recentItems = window.ExeretoRecent ? window.ExeretoRecent.get() : [];
            },
            clearRecent() {
                if (window.ExeretoRecent) window.ExeretoRecent.clear();
                this.recentItems = [];
            },
            openSearch() {
                this.loadRecent();
                this.activeIndex = -1;
                const root = this.$refs.sheetRoot;
                if (root) root.style.visibility = 'visible';
                this.$nextTick(() => {
                    const input = this.$refs.searchInput;
                    if (input) {
                        input.focus({ preventScroll: true });
                        const len = input.value.length;
                        try { input.setSelectionRange(len, len); } catch {}
                    }
                });
                if (this.query.trim().length >= 2 && this.suggestions.length === 0) {
                    this.fetchSuggestions();
                }
            },
            closeSearch() {
                if (this.$refs.searchInput) this.$refs.searchInput.blur();
                if (this.$refs.sheetRoot) this.$refs.sheetRoot.style.visibility = '';
                this.activeIndex = -1;
                this.searchOpen = false;
            },
            clearQuery() {
                this.query = '';
                this.suggestions = [];
                this.activeIndex = -1;
                if (this.abortCtrl) this.abortCtrl.abort();
                if (this.$refs.searchInput) this.$refs.searchInput.focus({ preventScroll: true });
            },
            moveSelection(step) {
                if (!this.suggestions.length) return;
                if (this.activeIndex < 0) {
                    this.activeIndex = step > 0 ? 0 : this.suggestions.length - 1;
                } else {
                    this.activeIndex = (this.activeIndex + step + this.suggestions.length) % this.suggestions.length;
                }
            },
            fetchSuggestions() {
                const q = this.query.trim();
                this.activeIndex = -1;
                if (q.length < 2) {
                    this.suggestions = [];
                    this.loadingSuggestions = false;
                    return;
                }
                if (this.abortCtrl) this.abortCtrl.abort();
                this.abortCtrl = new AbortController();
                this.loadingSuggestions = true;

                fetch(`{{ route('api.autocomplete') }}?q=${encodeURIComponent(q)}`, {
                    signal: this.abortCtrl.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept-Language': '{{ app()->getLocale() }}'
                    },
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    this.suggestions = data.suggestions || [];
                    this.activeIndex = -1;
                    this.loadingSuggestions = false;
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        this.suggestions = [];
                        this.activeIndex = -1;
                        this.loadingSuggestions = false;
                    }
                });
            },
            submitSearch() {
                if (this.activeIndex >= 0 && this.suggestions[this.activeIndex]) {
                    this.selectSuggestion(this.suggestions[this.activeIndex]);
                    return;
                }
                const q = this.query.trim();
                if (!q) return;
                this.loading = true;
                window.location.href = `{{ route('product-search') }}?q=${encodeURIComponent(q)}`;
            },
            selectSuggestion(item) {
                this.query = item.label || item.value || '';
                this.loading = true;
                window.location.href = item.url || `{{ route('product-search') }}?q=${encodeURIComponent(item.value || this.query)}`;
            }
         }"
         @open-search.window="openSearch()"
         @keydown.escape.window="if (searchOpen) closeSearch()"
         @pageshow.window="loading = false; loadRecent()"
         x-effect="
            if (searchOpen) {
                const sbw = window.innerWidth - document.documentElement.clientWidth;
                if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
                document.body.classList.add('overflow-hidden');
            } else if (!categoriesDrawerOpen) {
                document.body.classList.remove('overflow-hidden');
                document.body.style.paddingRight = '';
            }
         "
    >
        <!-- Оверлей загрузки при переходе -->
        <div x-show="loading" x-cloak class="fixed inset-0 z-[9999] bg-white/80 backdrop-blur-md flex flex-col items-center justify-center space-y-4">
            <svg class="animate-spin h-10 w-10 text-[#6a47bd]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-base font-semibold text-[#1d1d1f] animate-pulse">{{ __('Ищем предложения в магазинах Греции...') }}</span>
        </div>

        <!-- Полноэкранная панель поиска (мобильный + десктоп) -->
        <div x-ref="sheetRoot"
             x-cloak
             role="dialog"
             aria-modal="true"
             :aria-hidden="(!searchOpen).toString()"
             aria-label="{{ __('Поиск') }}"
             :class="searchOpen ? 'opacity-100 visible pointer-events-auto' : 'opacity-0 invisible pointer-events-none'"
             class="fixed inset-0 z-[9995] flex flex-col bg-[#f5f5f7]/98 lg:backdrop-blur-xl transition-[opacity,visibility] duration-200 ease-out"
        >
            <!-- Верхняя строка ввода -->
            <div class="px-4 sm:px-6 pt-5 lg:pt-10 pb-3 bg-[#f5f5f7] shrink-0">
                <div class="max-w-3xl mx-auto">
                    <div :class="searchOpen ? 'ml-0' : 'ml-[116px] lg:ml-0'"
                         class="transition-[margin] duration-250 ease-out">
                        <form @submit.prevent="submitSearch()"
                              class="flex items-center gap-2 pl-4 pr-2 h-12 lg:h-14 rounded-full bg-white shadow-[0_4px_24px_rgba(0,0,0,0.08)] border border-black/[0.07] focus-within:border-[#6a47bd]/50 focus-within:ring-2 focus-within:ring-[#6a47bd]/15 transition-all">
                            <button type="submit" aria-label="{{ __('Найти') }}" class="text-[#6a47bd] hover:opacity-85 transition-opacity shrink-0 cursor-pointer">
                                <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>

                            <input type="search"
                                   name="q"
                                   x-ref="searchInput"
                                   x-model="query"
                                   :tabindex="searchOpen ? 0 : -1"
                                   @input.debounce.180ms="fetchSuggestions()"
                                   @keydown.arrow-down.prevent="moveSelection(1)"
                                   @keydown.arrow-up.prevent="moveSelection(-1)"
                                   @keydown.escape.stop="closeSearch()"
                                   placeholder="{{ __('Найти товар или бренд') }}"
                                   maxlength="200"
                                   autocomplete="off"
                                   enterkeyhint="search"
                                   spellcheck="false"
                                   class="w-full min-w-0 bg-transparent border-none text-[16px] lg:text-[17px] font-medium text-[#1d1d1f] placeholder:text-[#6e6e73] focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:hidden">

                            <!-- Очистить текст -->
                            <button type="button"
                                    x-show="query.length > 0"
                                    @click="clearQuery()"
                                    aria-label="{{ __('Очистить запрос') }}"
                                    class="w-6 h-6 rounded-full bg-black/[0.08] text-[#6e6e73] hover:bg-black/[0.14] flex items-center justify-center shrink-0 cursor-pointer transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            <!-- Закрыть поиск -->
                            <button type="button"
                                    @click="closeSearch()"
                                    aria-label="{{ __('Закрыть поиск') }}"
                                    class="p-2 rounded-full text-[#1d1d1f] hover:bg-[#f5f5f7] transition-colors shrink-0 cursor-pointer">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Контентная область (автокомплит или недавно просмотренное + популярные запросы) -->
            <div :class="searchOpen ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'"
                 class="flex-1 overflow-y-auto px-4 sm:px-6 py-5 transition-all duration-200 ease-out">
                <div class="max-w-3xl mx-auto space-y-6">

                    <!-- РЕЖИМ 1: АВТОКОМПЛИТ (когда введено >= 2 символов) -->
                    <div x-show="query.trim().length >= 2" class="space-y-3">
                        <div x-show="loadingSuggestions && suggestions.length === 0" class="flex items-center justify-center py-8">
                            <svg class="animate-spin h-6 w-6 text-[#6a47bd]" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        <div x-show="suggestions.length > 0"
                             class="bg-white rounded-2xl border border-black/[0.06] divide-y divide-black/[0.04] overflow-hidden shadow-2xs">
                            <template x-for="(item, idx) in suggestions" :key="item.url || item.value">
                                <button type="button"
                                        @mouseenter="activeIndex = idx"
                                        @click="selectSuggestion(item)"
                                        :class="activeIndex === idx ? 'bg-[#6a47bd]/[0.07]' : 'hover:bg-[#f5f5f7] active:bg-[#ececf0]'"
                                        class="w-full px-4 py-3 flex items-center justify-between gap-3 text-left transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img x-show="item.image" :src="item.image" alt="" class="w-10 h-10 rounded-xl object-contain bg-[#f5f5f7] p-0.5 shrink-0 mix-blend-multiply">
                                        <span class="text-[15px] font-medium text-[#1d1d1f] truncate" x-text="item.label"></span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span x-show="item.price" class="text-[13px] font-semibold text-[#1d1d1f]" x-text="item.price"></span>
                                        <span class="text-[11px] px-2.5 py-0.5 bg-[#6a47bd]/10 text-[#6a47bd] rounded-full font-semibold" x-text="item.badge"></span>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <!-- Кнопка полного поиска по введённой фразе -->
                        <button type="button"
                                @click="submitSearch()"
                                class="w-full px-4 py-3.5 rounded-2xl bg-white border border-black/[0.06] flex items-center justify-between text-left hover:border-[#6a47bd]/40 transition-colors cursor-pointer">
                            <span class="text-[15px] font-semibold text-[#6a47bd] truncate">
                                {{ __('Показать все результаты') }}: «<span x-text="query.trim()"></span>»
                            </span>
                            <svg class="w-4 h-4 text-[#6a47bd] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- РЕЖИМ 2: НЕДАВНО ПРОСМОТРЕННОЕ + ПОПУЛЯРНЫЕ ЗАПРОСЫ -->
                    <div x-show="query.trim().length < 2" class="space-y-7">

                        <!-- Недавно просмотренное -->
                        <div x-show="recentItems.length > 0" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-[15px] font-semibold tracking-tight text-[#1d1d1f]">
                                    {{ __('Недавно просмотренное') }}
                                </h3>
                                <button type="button"
                                        @click="clearRecent()"
                                        class="text-xs font-medium text-[#6e6e73] hover:text-[#6a47bd] transition-colors cursor-pointer">
                                    {{ __('Очистить') }}
                                </button>
                            </div>

                            <div class="flex gap-3 overflow-x-auto no-scrollbar pb-1 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
                                <template x-for="item in recentItems" :key="item.feed + ':' + item.sku">
                                    <a :href="item.url"
                                       @click="loading = true"
                                       class="w-[144px] shrink-0 bg-white rounded-2xl border border-black/[0.06] p-2.5 flex flex-col gap-2 hover:border-[#6a47bd]/35 transition-colors">
                                        <div class="relative w-full aspect-square rounded-xl bg-[#f5f5f7] p-2 flex items-center justify-center overflow-hidden">
                                            <img x-show="item.image" :src="item.image" :alt="item.title" loading="lazy" class="max-w-full max-h-full object-contain mix-blend-multiply">
                                            <span x-show="item.discount > 0"
                                                  x-text="'-' + item.discount + '%'"
                                                  class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-bold leading-none"></span>
                                        </div>
                                        <div class="min-w-0 flex-1 flex flex-col justify-between gap-1">
                                            <div>
                                                <div x-show="item.brand" class="text-[10px] font-semibold uppercase tracking-wide text-[#86868b] truncate" x-text="item.brand"></div>
                                                <div class="text-[12px] font-medium text-[#1d1d1f] line-clamp-2 leading-snug" x-text="item.title"></div>
                                            </div>
                                            <div class="text-[13px] font-semibold text-[#1d1d1f]" x-text="item.price"></div>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </div>

                        <!-- Популярные запросы -->
                        @if(!empty($quickQueries))
                            <div class="space-y-3">
                                <h3 class="text-[15px] font-semibold tracking-tight text-[#1d1d1f]">
                                    {{ __('Популярные запросы') }}
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($quickQueries as $qText)
                                        <a href="{{ route('product-search', ['q' => $qText]) }}"
                                           @click="loading = true"
                                           class="px-4 py-2 rounded-full bg-white border border-black/[0.06] hover:border-[#6a47bd]/45 hover:text-[#6a47bd] text-[14px] font-medium text-[#1d1d1f] transition-colors">
                                            {{ $qText }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>

                </div>
            </div>
        </div>
    </div>
</template>