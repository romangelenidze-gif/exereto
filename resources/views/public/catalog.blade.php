<x-layouts.public
    title="{{ $pageTitle }} — {{ __('Купить в Греции с доставкой в Грузию') }} | Exereto"
    description="{{ $pageTitle }} — {{ __('оригинальные бренды из магазинов Греции с доставкой в Грузию.') }}"
>
@php
    $activeSort = $filters['sort'] ?? 'relevance';
    $sortLabels = [
        'relevance' => __('Рекомендуемые'),
        'price_asc' => __('Сначала дешевле'),
        'price_desc' => __('Сначала дороже'),
        'discount_desc' => __('По размеру скидки'),
    ];
    $genderLabels = [
        'women' => __('Женское'),
        'men' => __('Мужское'),
        'kids' => __('Детское'),
        'unisex' => __('Унисекс'),
    ];
    $activeFiltersCount = count($filters['brands'] ?? [])
        + count($filters['genders'] ?? [])
        + (!empty($filters['size']) ? 1 : 0)
        + (!empty($filters['color']) ? 1 : 0)
        + (!empty($filters['discount']) ? 1 : 0)
        + (!empty($filters['min_price']) || !empty($filters['max_price']) ? 1 : 0);
@endphp

<div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-20"
     x-data="{
        loading: false,
        mobileFiltersOpen: false,
        categoriesDrawerOpen: false,
        showAllBrands: false,
        loadingMore: false,
        initialQuery: @js($searchQuery ?? ''),
        query: @js($searchQuery ?? ''),
        suggestions: [],
        openSuggestions: false,
        activeIndex: -1,
        abortCtrl: null,
        currentPage: {{ (int) $pagination['current_page'] }},
        lastPage: {{ (int) $pagination['last_page'] }},
        totalItems: {{ (int) $pagination['total'] }},
        shownCount: {{ count($products) }},
        submitSearchBar() {
            if (this.openSuggestions && this.activeIndex >= 0 && this.suggestions[this.activeIndex]) {
                this.selectSuggestion(this.suggestions[this.activeIndex]);
                return;
            }
            if (!this.query.trim()) return;
            this.loading = true;
            window.location.href = `{{ route('product-search') }}?q=${encodeURIComponent(this.query.trim())}`;
        },
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
                this.openSuggestions = this.suggestions.length > 0;
            })
            .catch(() => {});
        },
        moveSelection(step) {
            if (!this.suggestions.length) return;
            if (!this.openSuggestions) {
                this.openSuggestions = true;
                this.activeIndex = step > 0 ? 0 : this.suggestions.length - 1;
            } else {
                this.activeIndex = (this.activeIndex + step + this.suggestions.length) % this.suggestions.length;
            }
        },
        selectSuggestion(item) {
            this.query = item.label || item.value;
            this.openSuggestions = false;
            this.loading = true;
            window.location.href = item.url || `{{ route('product-search') }}?q=${encodeURIComponent(item.value)}`;
        },
        setPresetPrice(min, max) {
            this.$refs.minPriceInput.value = min ?? '';
            this.$refs.maxPriceInput.value = max ?? '';
            this.loading = true;
            this.$refs.filterForm.submit();
        },
        toggleSize(sz) {
            this.$refs.sizeInput.value = (this.$refs.sizeInput.value === sz) ? '' : sz;
            this.loading = true;
            this.$refs.filterForm.submit();
        },
        toggleColor(col) {
            this.$refs.colorInput.value = (this.$refs.colorInput.value === col) ? '' : col;
            this.loading = true;
            this.$refs.filterForm.submit();
        },
        selectSub(idx) {
            if (!this.$refs.subInput) return;
            this.$refs.subInput.value = (String(this.$refs.subInput.value) === String(idx)) ? '' : idx;
            this.loading = true;
            this.$refs.filterForm.submit();
        },
        setSort(val) {
            this.$refs.sortInput.value = val;
            this.loading = true;
            this.$refs.filterForm.submit();
        },
        loadMore() {
            if (this.loadingMore || this.currentPage >= this.lastPage) return;
            this.loadingMore = true;
            const params = new URLSearchParams(window.location.search);
            params.set('page', String(this.currentPage + 1));

            fetch(`${window.location.pathname}?${params.toString()}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                const grid = document.getElementById('apple-product-grid');
                if (grid && data.html) grid.insertAdjacentHTML('beforeend', data.html);
                if (data.pagination) {
                    this.currentPage = data.pagination.current_page;
                    this.lastPage = data.pagination.last_page;
                    this.totalItems = data.pagination.total;
                }
                this.shownCount = grid ? grid.children.length : this.shownCount;
                this.loadingMore = false;
            })
            .catch(() => { this.loadingMore = false; });
        }
     }"
     @pageshow.window="loading = false"
>
    <!-- Оверлей загрузки -->
    <template x-teleport="body">
        <div x-show="loading" x-cloak class="fixed inset-0 z-[9999] bg-white/80 backdrop-blur-md flex flex-col items-center justify-center space-y-4">
            <svg class="animate-spin h-10 w-10 text-[#0071e3]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="app-text-body font-semibold animate-pulse">{{ __('Обновляем выдачу...') }}</span>
        </div>
    </template>

    <!-- Выдвижная шторка категорий (по кнопке ☰ в строке поиска) -->
    <x-public-ui.categories-drawer :categories="$allCategories" />

    <!-- Полноширинный контейнер Apple -->
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-6 space-y-5 sm:space-y-6">

        <!-- Поисковая строка с кнопкой меню категорий справа -->
        <div class="max-w-4xl mx-auto relative" @click.outside="openSuggestions = false; activeIndex = -1">
            <form @submit.prevent="submitSearchBar()">
                <div class="apple-search-bar flex items-center justify-between gap-2">
                    <button type="submit" aria-label="{{ __('Найти') }}" class="p-1.5 text-[#0071e3] hover:opacity-85 transition-opacity cursor-pointer shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                    <input
                        type="text"
                        x-model="query"
                        @focus="if (suggestions.length > 0) openSuggestions = true"
                        @input.debounce.180ms="fetchSuggestions()"
                        @keydown.arrow-down.prevent="moveSelection(1)"
                        @keydown.arrow-up.prevent="moveSelection(-1)"
                        @keydown.enter="if (openSuggestions && activeIndex >= 0 && suggestions[activeIndex]) { $event.preventDefault(); selectSuggestion(suggestions[activeIndex]); }"
                        @keydown.escape="openSuggestions = false; activeIndex = -1"
                        placeholder="{{ __('Что вы ищете?') }}"
                        maxlength="200"
                        autocomplete="off"
                        class="apple-search-input flex-1 text-base sm:text-lg"
                    >
                    <button type="button"
                            @click="categoriesDrawerOpen = true"
                            aria-label="{{ __('Категории') }}"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[#1d1d1f] hover:bg-[#f5f5f7] transition-colors cursor-pointer shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span class="text-sm font-medium">{{ __('Категории') }}</span>
                    </button>
                </div>
            </form>

            <!-- Выпадающие подсказки -->
            <div x-show="openSuggestions"
                 x-cloak
                 role="listbox"
                 class="absolute left-0 right-0 mt-2 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-black/[0.08] overflow-y-auto max-h-96 z-50">
                <template x-for="(item, idx) in suggestions" :key="item.url || item.value">
                    <div role="option"
                         :aria-selected="activeIndex === idx"
                         @mouseenter="activeIndex = idx"
                         @click="selectSuggestion(item)"
                         :class="activeIndex === idx ? 'bg-[#0071e3]/[0.08]' : 'hover:bg-[#f5f5f7]'"
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

        <!-- Хлебные крошки -->
        <nav class="flex items-center gap-2 app-text-xs text-[#86868b]">
            <a href="{{ route('home') }}" class="hover:text-[#0071e3] transition-colors">{{ __('Главная') }}</a>
            <span>&rsaquo;</span>
            <span class="text-[#1d1d1f] font-medium truncate">{{ $pageTitle }}</span>
        </nav>

        <!-- Двухколоночная сетка на всю ширину: Слева сайдбар (десктоп), Справа подкатегории + выдача -->
        <div class="grid grid-cols-1 lg:grid-cols-[270px_minmax(0,1fr)] gap-6 xl:gap-8 items-start">

            <!-- САЙДБАР ФИЛЬТРОВ (на десктопе — левая колонка, на мобильном — выдвижная шторка) -->
            <div :class="mobileFiltersOpen ? 'fixed inset-0 z-[9990] flex bg-black/40 backdrop-blur-xs lg:static lg:block lg:bg-transparent lg:backdrop-blur-none' : 'hidden lg:block'"
                 @click.self="mobileFiltersOpen = false">

                <aside class="w-full max-w-sm lg:max-w-none bg-white h-full lg:h-auto overflow-y-auto lg:overflow-visible rounded-none lg:rounded-3xl border-r lg:border border-black/[0.06] p-5 sm:p-6 space-y-6 lg:sticky lg:top-20 ml-auto lg:ml-0">
                    <div class="flex items-center justify-between">
                        <h2 class="app-text-title font-semibold">{{ __('Фильтры') }}</h2>
                        <div class="flex items-center gap-3">
                            @if($activeFiltersCount > 0)
                                <a href="{{ $resetUrl }}" class="app-text-xs text-[#0071e3] font-medium hover:underline">
                                    {{ __('Сбросить') }}
                                </a>
                            @endif
                            <button type="button" @click="mobileFiltersOpen = false" class="lg:hidden apple-icon-btn">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <form action="{{ $formAction }}" method="GET" x-ref="filterForm" @submit="loading = true" class="space-y-6">
                        @if($isSearchMode && $searchQuery !== '')
                            <input type="hidden" name="q" value="{{ $searchQuery }}">
                        @endif
                        @if(!$isSearchMode)
                            <input type="hidden" name="sub" x-ref="subInput" value="{{ $activeSubIndex ?? '' }}">
                        @endif
                        <input type="hidden" name="size" x-ref="sizeInput" value="{{ $filters['size'] ?? '' }}">
                        <input type="hidden" name="color" x-ref="colorInput" value="{{ $filters['color'] ?? '' }}">
                        <input type="hidden" name="sort" x-ref="sortInput" value="{{ $activeSort }}">

                        <!-- Цена -->
                        <div class="space-y-3 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Цена, €') }}</div>
                            <div class="flex items-center gap-2">
                                <input type="number" name="min_price" x-ref="minPriceInput" value="{{ $filters['min_price'] ?? '' }}"
                                       placeholder="{{ __('От') }}" min="0"
                                       class="w-full px-3 py-2 app-text-xs rounded-xl bg-[#f5f5f7] border border-transparent focus:border-[#0071e3] focus:bg-white outline-none">
                                <input type="number" name="max_price" x-ref="maxPriceInput" value="{{ $filters['max_price'] ?? '' }}"
                                       placeholder="{{ __('До') }}" min="0"
                                       class="w-full px-3 py-2 app-text-xs rounded-xl bg-[#f5f5f7] border border-transparent focus:border-[#0071e3] focus:bg-white outline-none">
                                <button type="submit" class="px-3 py-2 rounded-xl bg-[#f5f5f7] hover:bg-[#0071e3] hover:text-white transition-colors shrink-0 cursor-pointer font-semibold" title="{{ __('Применить') }}">
                                    &rsaquo;
                                </button>
                            </div>

                            <div class="space-y-1.5 app-text-sub text-sm">
                                <button type="button" @click="setPresetPrice('', 50)" class="block w-full text-left py-1 hover:text-[#0071e3] cursor-pointer">{{ __('До 50 €') }}</button>
                                <button type="button" @click="setPresetPrice(50, 100)" class="block w-full text-left py-1 hover:text-[#0071e3] cursor-pointer">50 – 100 €</button>
                                <button type="button" @click="setPresetPrice(100, 150)" class="block w-full text-left py-1 hover:text-[#0071e3] cursor-pointer">100 – 150 €</button>
                                <button type="button" @click="setPresetPrice(150, '')" class="block w-full text-left py-1 hover:text-[#0071e3] cursor-pointer">{{ __('От 150 € и выше') }}</button>
                            </div>

                            <label class="flex items-center gap-2.5 pt-1 cursor-pointer app-text-sub text-sm font-medium text-red-600">
                                <input type="checkbox" name="discount" value="1" @checked(!empty($filters['discount'])) onchange="this.form.submit()"
                                       class="rounded border-zinc-300 text-[#0071e3] focus:ring-[#0071e3]">
                                <span>{{ __('Только со скидкой') }}</span>
                            </label>
                        </div>

                        <!-- Бренды -->
                        @if(!empty($facets['brands']))
                        <div class="space-y-2.5 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Бренды') }}</div>
                            <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                                @foreach($facets['brands'] as $idx => $b)
                                    <label x-show="showAllBrands || {{ $idx }} < 7" class="flex items-center justify-between gap-2 app-text-sub text-sm hover:text-[#1d1d1f] cursor-pointer py-0.5">
                                        <span class="flex items-center gap-2 truncate">
                                            <input type="checkbox" name="brands[]" value="{{ $b['name'] }}"
                                                   @checked(in_array($b['name'], $filters['brands'] ?? [], true))
                                                   onchange="this.form.submit()"
                                                   class="rounded border-zinc-300 text-[#0071e3] focus:ring-[#0071e3]">
                                            <span class="truncate">{{ $b['name'] }}</span>
                                        </span>
                                        <span class="app-text-xs text-[#86868b] shrink-0">{{ number_format($b['count']) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if(count($facets['brands']) > 7)
                                <button type="button" @click="showAllBrands = !showAllBrands" class="app-text-xs font-medium text-[#0071e3] hover:underline cursor-pointer">
                                    <span x-text="showAllBrands ? '{{ __('Свернуть') }}' : '{{ __('Показать ещё') }}'"></span>
                                </button>
                            @endif
                        </div>
                        @endif

                        <!-- Пол -->
                        @if(count($facets['genders'] ?? []) > 1)
                        <div class="space-y-2 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Пол') }}</div>
                            @foreach($facets['genders'] as $g)
                                <label class="flex items-center justify-between app-text-sub text-sm cursor-pointer py-0.5">
                                    <span class="flex items-center gap-2">
                                        <input type="checkbox" name="genders[]" value="{{ $g['code'] }}"
                                               @checked(in_array($g['code'], $filters['genders'] ?? [], true))
                                               onchange="this.form.submit()"
                                               class="rounded border-zinc-300 text-[#0071e3] focus:ring-[#0071e3]">
                                        <span>{{ $genderLabels[$g['code']] ?? $g['code'] }}</span>
                                    </span>
                                    <span class="app-text-xs text-[#86868b]">{{ number_format($g['count']) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @endif

                        <!-- Размеры -->
                        @if(!empty($facets['sizes']))
                        <div class="space-y-2.5 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Размер') }}</div>
                            <div class="grid grid-cols-4 gap-1.5">
                                @foreach($facets['sizes'] as $sz)
                                    @php $isSzActive = ($filters['size'] ?? '') === $sz; @endphp
                                    <button type="button" @click="toggleSize(@js($sz))"
                                            class="py-1.5 px-1 app-text-xs font-medium rounded-full border text-center transition-all cursor-pointer truncate {{ $isSzActive ? 'bg-[#1d1d1f] text-white border-[#1d1d1f]' : 'bg-white text-[#1d1d1f] border-black/15 hover:border-[#0071e3]' }}">
                                        {{ $sz }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Цвета -->
                        @if(!empty($facets['colors']))
                        <div class="space-y-2 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Цвет') }}</div>
                            <div class="space-y-1">
                                @foreach($facets['colors'] as $colName => $colCnt)
                                    @php $isColActive = ($filters['color'] ?? '') === $colName; @endphp
                                    <button type="button" @click="toggleColor(@js($colName))"
                                            class="flex items-center justify-between w-full text-left app-text-sub text-sm py-1.5 px-2.5 rounded-xl transition-colors cursor-pointer {{ $isColActive ? 'bg-[#0071e3]/10 text-[#0071e3] font-semibold' : 'hover:bg-[#f5f5f7]' }}">
                                        <span class="truncate">{{ $colName }}</span>
                                        <span class="app-text-xs text-[#86868b]">{{ $colCnt }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <div class="lg:hidden pt-2">
                            <button type="submit" class="apple-btn-primary w-full">
                                {{ __('Показать товары') }}
                            </button>
                        </div>
                    </form>
                </aside>
            </div>

            <!-- ПРАВАЯ ЧАСТЬ: ЗАГОЛОВОК, ПОДКАТЕГОРИИ, ПАНЕЛЬ БЫСТРЫХ ФИЛЬТРОВ И СЕТКА ТОВАРОВ -->
            <div class="space-y-5 sm:space-y-6 min-w-0">

                <!-- Заголовок + счётчик моделей в единой типографике -->
                <div class="flex flex-wrap items-baseline gap-3">
                    <h1 class="apple-section-title">
                        {{ $pageTitle }}
                    </h1>
                    @if($pagination['total'] > 0)
                        <span class="app-text-sub text-[#86868b]">
                            {{ number_format($pagination['total']) }} {{ __('товаров') }}
                        </span>
                    @endif
                </div>

                <!-- Горизонтальная лента подкатегорий (или категорий на странице поиска) -->
                @if(!empty($subcategories))
                    <div class="flex items-stretch gap-2.5 sm:gap-3 overflow-x-auto no-scrollbar pb-1">
                        @foreach($subcategories as $sub)
                            @php $isSubActive = $activeSubIndex !== null && (int)$activeSubIndex === (int)$sub['index']; @endphp
                            @if(!empty($sub['url']))
                                <a href="{{ $sub['url'] }}"
                                   class="flex flex-col items-center justify-between w-24 sm:w-28 shrink-0 p-2.5 rounded-2xl bg-white border border-black/[0.06] hover:border-[#0071e3]/40 transition-all text-center">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 flex items-center justify-center overflow-hidden rounded-xl bg-[#f5f5f7] mb-2">
                                        @if(!empty($sub['image']))
                                            <img src="{{ $sub['image'] }}" alt="{{ $sub['label'] }}" class="max-w-full max-h-full object-contain">
                                        @else
                                            <span class="app-text-xs font-bold text-[#86868b]">{{ mb_substr($sub['label'], 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <span class="app-text-xs font-medium text-[#1d1d1f] line-clamp-2 leading-tight">
                                        {{ $sub['label'] }}
                                    </span>
                                </a>
                            @else
                                <button type="button"
                                        @click="selectSub({{ (int) $sub['index'] }})"
                                        class="flex flex-col items-center justify-between w-24 sm:w-28 shrink-0 p-2.5 rounded-2xl border transition-all cursor-pointer text-center {{ $isSubActive ? 'bg-white border-[#0071e3] ring-2 ring-[#0071e3]/20 shadow-sm' : 'bg-white border-black/[0.06] hover:border-black/20' }}">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 flex items-center justify-center overflow-hidden rounded-xl bg-[#f5f5f7] mb-2">
                                        @if(!empty($sub['image']))
                                            <img src="{{ $sub['image'] }}" alt="{{ $sub['label'] }}" class="max-w-full max-h-full object-contain">
                                        @else
                                            <span class="app-text-xs font-bold text-[#86868b]">{{ mb_substr($sub['label'], 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <span class="app-text-xs font-medium text-[#1d1d1f] line-clamp-2 leading-tight">
                                        {{ $sub['label'] }}
                                    </span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif

                <!-- Верхняя панель быстрых пилюль + сортировка справа -->
                <div class="flex items-center justify-between gap-2 bg-white px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-2xl border border-black/[0.06]">
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
                        <!-- Кнопка открытия шторки фильтров на мобильном -->
                        <button type="button" @click="mobileFiltersOpen = true"
                                class="lg:hidden inline-flex items-center gap-1.5 px-4 py-2 rounded-full app-text-xs font-semibold bg-[#0071e3] text-white shrink-0 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span>{{ __('Фильтры') }}</span>
                            @if($activeFiltersCount > 0)
                                <span class="px-1.5 py-0.2 bg-white/20 rounded-full">{{ $activeFiltersCount }}</span>
                            @endif
                        </button>

                        <a href="{{ request()->fullUrlWithQuery(['discount' => empty($filters['discount']) ? 1 : null]) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full app-text-xs font-medium border transition-colors shrink-0 {{ !empty($filters['discount']) ? 'bg-red-600 text-white border-red-600' : 'bg-white text-[#1d1d1f] border-black/15 hover:border-[#0071e3]' }}">
                            <span>&#9889; {{ __('Скидки') }}</span>
                        </a>

                        @if(!empty($filters['brands']))
                            @foreach($filters['brands'] as $activeBrand)
                                <span class="inline-flex items-center px-3 py-1.5 rounded-full app-text-xs font-medium bg-[#1d1d1f] text-white shrink-0">
                                    {{ $activeBrand }}
                                </span>
                            @endforeach
                        @endif

                        @if(!empty($filters['size']))
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full app-text-xs font-medium bg-[#1d1d1f] text-white shrink-0">
                                {{ __('Размер') }}: {{ $filters['size'] }}
                            </span>
                        @endif
                    </div>

                    <!-- Выпадающая сортировка справа -->
                    <div class="relative shrink-0" x-data="{ openSort: false }" @click.outside="openSort = false">
                        <button type="button" @click="openSort = !openSort"
                                class="inline-flex items-center gap-1.5 px-3.5 sm:px-4 py-2 rounded-full app-text-xs font-medium bg-[#f5f5f7] hover:bg-[#e8e8ed] text-[#1d1d1f] transition-colors cursor-pointer">
                            <span>&uarr;&darr; {{ $sortLabels[$activeSort] ?? __('Рекомендуемые') }}</span>
                        </button>

                        <div x-show="openSort" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-black/[0.08] py-1.5 z-40">
                            @foreach($sortLabels as $sKey => $sLabel)
                                <button type="button" @click="setSort('{{ $sKey }}')"
                                        class="block w-full text-left px-4 py-2 app-text-xs transition-colors cursor-pointer {{ $activeSort === $sKey ? 'text-[#0071e3] font-semibold bg-[#0071e3]/5' : 'text-[#1d1d1f] hover:bg-[#f5f5f7]' }}">
                                    {{ $sLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Сетка карточек товаров: 2 колонки на мобильном, 3-5 на десктопе -->
                @if(!empty($products))
                    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-5" id="apple-product-grid">
                        @foreach($products as $product)
                            <x-public-ui.product-card :product="$product" :is-first="$loop->first" />
                        @endforeach
                    </div>

                    <!-- Кнопка «Показать ещё» -->
                    <div x-show="currentPage < lastPage" x-cloak class="flex flex-col items-center justify-center pt-6 space-y-2">
                        <button type="button"
                                @click="loadMore()"
                                :disabled="loadingMore"
                                class="px-8 py-3 rounded-full bg-white hover:bg-[#e8e8ed] text-[#1d1d1f] app-text-body text-sm font-semibold shadow-sm border border-black/[0.08] transition-all cursor-pointer disabled:opacity-60">
                            <span x-text="loadingMore ? '{{ __('Загружаем...') }}' : '{{ __('Показать ещё') }}'"></span>
                        </button>
                        <span class="app-text-xs text-[#86868b]">
                            {{ __('Показано') }} <span x-text="shownCount"></span> {{ __('из') }} <span x-text="totalItems"></span>
                        </span>
                    </div>
                @else
                    <div class="bg-white rounded-3xl border border-black/[0.06] p-10 sm:p-12 text-center max-w-md mx-auto space-y-3">
                        <h3 class="app-text-title font-semibold">{{ __('Товары не найдены') }}</h3>
                        <p class="app-text-sub text-sm">{{ __('Попробуйте изменить запрос, ослабить фильтры или выбрать категорию из меню сверху.') }}</p>
                        <div class="pt-2">
                            <button type="button" @click="categoriesDrawerOpen = true" class="apple-btn-primary">
                                {{ __('Открыть категории') }}
                            </button>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
</x-layouts.public>