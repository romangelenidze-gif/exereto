@props(['brands' => []])

@php
    $searchBaseUrl = route('product-search');
@endphp

<template x-teleport="body">
    <div x-data="{
            open: false,
            q: '',
            activeLetter: 'TOP',
            brands: @js($brands),
            searchBaseUrl: @js($searchBaseUrl),
            letters: [],
            init() {
                const set = new Set();
                for (const b of this.brands) {
                    const ch = (b.n || '').trim().charAt(0).toUpperCase();
                    if (/^[A-Z]$/.test(ch)) set.add(ch);
                    else if (ch) set.add('#');
                }
                this.letters = ['TOP', ...Array.from(set).filter(l => l !== '#').sort(), ...(set.has('#') ? ['#'] : [])];
            },
            openDrawer() {
                this.open = true;
                this.q = '';
                this.activeLetter = 'TOP';
                this.$nextTick(() => {
                    if (this.$refs.listContainer) this.$refs.listContainer.scrollTop = 0;
                });
            },
            closeDrawer() {
                this.open = false;
            },
            selectLetter(letter) {
                this.q = '';
                this.activeLetter = letter;
                this.$nextTick(() => {
                    if (this.$refs.listContainer) this.$refs.listContainer.scrollTo({ top: 0, behavior: 'smooth' });
                });
            },
            brandUrl(name) {
                return this.searchBaseUrl + '?q=' + encodeURIComponent(name);
            },
            get visibleBrands() {
                if (!this.open) return [];
                const term = this.q.trim().toLowerCase();
                if (term.length > 0) {
                    const starts = [];
                    const contains = [];
                    for (const b of this.brands) {
                        const lower = b.n.toLowerCase();
                        if (lower.startsWith(term)) starts.push(b);
                        else if (lower.includes(term)) contains.push(b);
                        if (starts.length + contains.length >= 80) break;
                    }
                    return [...starts, ...contains];
                }
                if (this.activeLetter === 'TOP') {
                    return this.brands.slice(0, 40);
                }
                return this.brands
                    .filter(b => {
                        const ch = (b.n || '').trim().charAt(0).toUpperCase();
                        return this.activeLetter === '#' ? !/^[A-Z]$/.test(ch) : ch === this.activeLetter;
                    })
                    .slice()
                    .sort((a, b) => a.n.localeCompare(b.n));
            }
         }"
         x-show="open"
         x-cloak
         @open-brands.window="openDrawer()"
         @keydown.escape.window="if (open) closeDrawer()"
         x-effect="
            if (open) {
                const sbw = window.innerWidth - document.documentElement.clientWidth;
                if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
                document.body.style.paddingRight = '';
            }
         "
         x-transition:enter="transition duration-300"
         x-transition:leave="transition duration-200"
         class="fixed inset-0 z-[9998] flex justify-end"
    >
        <!-- Белый матовый фон -->
        <div x-show="open"
             x-transition:enter="transition-opacity ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeDrawer()"
             class="fixed inset-0 bg-white/60 backdrop-blur-md will-change-[opacity]"
        ></div>

        <!-- Выдвижная панель -->
        <div x-show="open"
             x-transition:enter="transition-transform ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
             x-transition:enter-end="translate-y-0 sm:translate-x-0"
             x-transition:leave="transition-transform ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:translate-x-0"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full"
             class="relative z-10 w-full sm:max-w-md bg-[#f5f5f7] h-full flex flex-col shadow-2xl border-l border-black/[0.08] overflow-hidden will-change-transform"
        >
            <!-- Шапка -->
            <div class="relative flex items-center justify-center px-5 pt-5 pb-3 shrink-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-[19px] font-semibold tracking-tight text-[#1d1d1f]">
                        {{ __('Все бренды') }}
                    </h2>
                    <span class="px-2 py-0.5 rounded-full bg-black/[0.06] text-[12px] font-semibold text-[#6e6e73] tabular-nums"
                          x-text="brands.length.toLocaleString()"></span>
                </div>
                <button type="button"
                        @click="closeDrawer()"
                        aria-label="{{ __('Закрыть') }}"
                        class="apple-icon-btn absolute right-4">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Поиск по брендам -->
            <div class="px-4 pb-3 shrink-0">
                <div class="flex items-center gap-2 px-3.5 h-11 rounded-full bg-white shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-black/[0.06] focus-within:border-[#0071e3]/40 focus-within:ring-2 focus-within:ring-[#0071e3]/15 transition-all">
                    <svg class="w-4 h-4 text-[#0071e3] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="search"
                           x-model="q"
                           placeholder="{{ __('Поиск бренда...') }}"
                           autocomplete="off"
                           class="w-full min-w-0 bg-transparent border-none text-[15px] font-medium text-[#1d1d1f] placeholder:text-[#6e6e73] focus:outline-none focus:ring-0 [&::-webkit-search-cancel-button]:hidden">
                    <button type="button"
                            x-show="q.length > 0"
                            @click="q = ''"
                            class="w-5 h-5 rounded-full bg-black/[0.08] text-[#6e6e73] flex items-center justify-center shrink-0 cursor-pointer">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Алфавитная лента Top / A–Z -->
            <div x-show="q.trim().length === 0" class="px-4 pb-3 border-b border-black/[0.06] shrink-0">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
                    <template x-for="letter in letters" :key="letter">
                        <button type="button"
                                @click="selectLetter(letter)"
                                :class="activeLetter === letter ? 'bg-[#1d1d1f] text-white' : 'bg-white text-[#1d1d1f] hover:bg-black/[0.05] border border-black/[0.06]'"
                                class="h-8 min-w-[34px] px-2.5 rounded-full text-[12px] font-semibold transition-colors shrink-0 cursor-pointer"
                                x-text="letter === 'TOP' ? '{{ __('Топ') }}' : letter">
                        </button>
                    </template>
                </div>
            </div>

            <!-- Список брендов -->
            <div x-ref="listContainer" class="flex-1 overflow-y-auto p-4 pb-10">
                <div x-show="visibleBrands.length > 0"
                     class="bg-white rounded-2xl border border-black/[0.05] divide-y divide-black/[0.04] overflow-hidden shadow-[0_2px_10px_rgba(0,0,0,0.03)]">
                    <template x-for="item in visibleBrands" :key="item.n">
                        <a :href="brandUrl(item.n)"
                           @click="$dispatch('search-loading')"
                           class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-[#f5f5f7] active:bg-[#ececf0] transition-colors">
                            <span class="text-[15px] font-semibold text-[#1d1d1f] truncate" x-text="item.n"></span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span x-show="item.d >= 15"
                                      x-text="'-' + item.d + '%'"
                                      class="px-2 py-0.5 rounded-full bg-red-50 text-red-600 text-[11px] font-bold tabular-nums"></span>
                                <span class="text-[13px] font-medium text-[#6e6e73] tabular-nums"
                                      x-text="item.c.toLocaleString()"></span>
                                <svg class="w-4 h-4 text-[#86868b]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    </template>
                </div>

                <div x-show="visibleBrands.length === 0" class="py-12 text-center text-sm text-[#6e6e73]">
                    {{ __('Бренды не найдены') }}
                </div>
            </div>
        </div>
    </div>
</template>