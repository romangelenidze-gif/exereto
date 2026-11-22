@props(['categories' => []])

@php
    $photoSearchEnabled = (bool) config('services.gemini.vision_enabled', true)
        && filled(config('services.gemini.vision_api_key'));
@endphp

<section class="pt-1 lg:pt-3 pb-0 px-4 sm:px-6 lg:px-10 text-center w-full"
         x-data="{
            query: '',
            suggestions: [],
            openSuggestions: false,
            activeIndex: -1,
            loadingSuggestions: false,
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
                this.loadingSuggestions = true;

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
                    this.loadingSuggestions = false;
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        this.suggestions = [];
                        this.activeIndex = -1;
                        this.openSuggestions = false;
                        this.loadingSuggestions = false;
                    }
                });
            },
            moveSelection(step) {
                if (!this.suggestions.length) return;
                if (!this.openSuggestions) {
                    this.openSuggestions = true;
                    this.activeIndex = step > 0 ? 0 : this.suggestions.length - 1;
                } else {
                    this.activeIndex = (this.activeIndex + step + this.suggestions.length) % this.suggestions.length;
                }
                this.$nextTick(() => {
                    const items = this.$refs.suggestionsBox?.querySelectorAll('[data-suggestion-item]');
                    if (items && items[this.activeIndex]) {
                        items[this.activeIndex].scrollIntoView({ block: 'nearest' });
                    }
                });
            },
            selectSuggestion(item) {
                this.query = item.label || item.value;
                this.openSuggestions = false;
                this.activeIndex = -1;
                this.$dispatch('search-loading');
                window.location.href = item.url || `{{ route('product-search') }}?q=${encodeURIComponent(item.value)}`;
            }
         }">

    <!-- Мобильный компактный слоган под шапкой -->
    <p class="lg:hidden text-xs sm:text-sm text-zinc-500 max-w-xs mx-auto leading-snug pt-1">
        {{ __('Быстрый поиск по официальным каталогам брендов в Греции.') }}
    </p>

    <!-- Десктопный Spotlight (>= lg) -->
    <div class="hidden lg:block space-y-10">
        <div class="space-y-3 max-w-4xl mx-auto">
            <h1 class="app-text-logo truncate px-2">
                {{ __('exereto') }}
            </h1>
            <p class="app-text-body max-w-2xl mx-auto px-2">
                {{ __('Быстрый поиск по официальным каталогам брендов в Греции.') }}
            </p>
        </div>

        <div class="max-w-3xl mx-auto relative"
             @click.outside="openSuggestions = false; activeIndex = -1"
             @scroll.window="openSuggestions = false; activeIndex = -1">
            <form action="{{ route('product-search') }}" method="GET" class="relative">
                <div class="apple-search-bar flex items-center justify-between gap-2">
                    <button type="submit" aria-label="{{ __('Найти') }}" class="p-1.5 text-[#0071e3] hover:opacity-85 transition-opacity cursor-pointer shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                    <input
                        type="text"
                        name="q"
                        x-model="query"
                        @focus="if (suggestions.length > 0) openSuggestions = true"
                        @input.debounce.180ms="fetchSuggestions()"
                        @keydown.arrow-down.prevent="moveSelection(1)"
                        @keydown.arrow-up.prevent="moveSelection(-1)"
                        @keydown.enter="if (openSuggestions && activeIndex >= 0 && suggestions[activeIndex]) { $event.preventDefault(); selectSuggestion(suggestions[activeIndex]); }"
                        @keydown.escape="openSuggestions = false; activeIndex = -1"
                        placeholder="{{ __('Что вы ищете?') }}"
                        required
                        maxlength="200"
                        autocomplete="off"
                        spellcheck="false"
                        class="apple-search-input flex-1 bg-transparent border-none focus:outline-none text-base sm:text-lg"
                    >
                    @if($photoSearchEnabled)
                        <button type="button"
                                @click="$dispatch('open-photo-search')"
                                aria-label="{{ __('Поиск по фото') }}"
                                title="{{ __('Поиск по фото') }}"
                                class="p-2 rounded-full text-[#6e6e73] hover:text-[#0071e3] hover:bg-[#f5f5f7] transition-colors cursor-pointer shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    @endif
                    <button type="button"
                            @click="$dispatch('open-categories')"
                            aria-label="{{ __('Категории') }}"
                            class="inline-flex items-center gap-2 p-2 sm:px-3 sm:py-1.5 rounded-full text-[#1d1d1f] hover:bg-[#f5f5f7] transition-colors cursor-pointer shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span class="hidden sm:inline text-sm font-medium">{{ __('Категории') }}</span>
                    </button>
                </div>
            </form>

            <div x-show="openSuggestions"
                 x-ref="suggestionsBox"
                 x-cloak
                 role="listbox"
                 class="absolute left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-black/[0.08] overflow-y-auto max-h-96 z-50 text-left">
                <template x-for="(item, idx) in suggestions" :key="item.url || item.value">
                    <div data-suggestion-item
                         role="option"
                         :aria-selected="activeIndex === idx"
                         @mouseenter="activeIndex = idx"
                         @click="selectSuggestion(item)"
                         :class="activeIndex === idx ? 'bg-[#0071e3]/[0.08]' : 'hover:bg-black/[0.04]'"
                         class="px-5 py-3 flex items-center justify-between gap-3 cursor-pointer transition-colors border-b border-black/[0.03] last:border-b-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <img x-show="item.image" :src="item.image" alt="" class="w-9 h-9 rounded-lg object-cover bg-[#f5f5f7] shrink-0">
                            <span class="app-text-body font-medium text-[#1d1d1f] truncate" x-text="item.label"></span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span x-show="item.price" class="app-text-xs font-semibold text-[#1d1d1f]" x-text="item.price"></span>
                            <span class="app-text-xs px-2.5 py-1 bg-[#0071e3]/10 text-[#0071e3] rounded-full font-semibold" x-text="item.badge"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</section>