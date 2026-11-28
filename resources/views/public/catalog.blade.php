@php
    $currentPageNum = (int) ($pagination['current_page'] ?? 1);
    $totalProductsCount = (int) ($pagination['total'] ?? 0);
    $pageSuffix = $currentPageNum > 1 ? ' — ' . __('Страница') . ' ' . $currentPageNum : '';

    $catalogTitle = $isSearchMode
        ? $pageTitle . $pageSuffix . ' | Exereto'
        : $pageTitle . $pageSuffix . ' — ' . __('Официальные цены и скидки в интернет-магазинах Греции') . ' | Exereto';

    $catalogDescription = $totalProductsCount > 0
        ? __(':title — :count товаров по официальным ценам и скидкам в интернет-магазинах Греции на Exereto.', [
            'title' => $pageTitle,
            'count' => number_format($totalProductsCount, 0, '.', ' '),
        ])
        : __(':title — официальные цены и скидки в интернет-магазинах Греции на Exereto.', [
            'title' => $pageTitle,
        ]);

    $catalogOgImage = !empty($products[0]['image']) ? $products[0]['image'] : null;

    $catalogJsonLd = null;
    if (!$isSearchMode) {
        $appUrl = rtrim((string) config('app.url', 'https://exereto.com'), '/');
        $rawPath = trim(request()->path(), '/');
        $currentUrl = $rawPath === '' ? $appUrl . '/' : $appUrl . '/' . $rawPath;

        $crumbList = [];
        $pos = 1;
        foreach (($breadcrumbs ?? []) as $crumb) {
            if (empty($crumb['label'])) {
                continue;
            }
            $crumbList[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => (string) $crumb['label'],
                'item' => !empty($crumb['url']) ? (string) $crumb['url'] : $currentUrl,
            ];
        }

        $catalogJsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => (string) $pageTitle,
                'description' => $catalogDescription,
                'url' => $currentUrl,
            ],
        ];

        if (count($crumbList) >= 2) {
            $catalogJsonLd[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $crumbList,
            ];
        }
    }
@endphp

<x-layouts.public
    :title="$catalogTitle"
    :description="$catalogDescription"
    :ogImage="$catalogOgImage"
    :jsonLd="$catalogJsonLd"
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
    $hasPriceFilter = !empty($filters['min_price']) || !empty($filters['max_price']);
    $priceChipLabel = match (true) {
        !empty($filters['min_price']) && !empty($filters['max_price']) => '€' . (int) $filters['min_price'] . ' – €' . (int) $filters['max_price'],
        !empty($filters['min_price']) => '≥ €' . (int) $filters['min_price'],
        !empty($filters['max_price']) => '≤ €' . (int) $filters['max_price'],
        default => '',
    };
    $activeFiltersCount = count($filters['brands'] ?? [])
        + count($filters['genders'] ?? [])
        + (!empty($filters['size']) ? 1 : 0)
        + (!empty($filters['color']) ? 1 : 0)
        + (!empty($filters['discount']) ? 1 : 0)
        + ($hasPriceFilter ? 1 : 0);
@endphp

<div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-20"
     x-data="{
        loading: false,
        mobileFiltersOpen: false,
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
        removeBrand(brand) {
            let matched = false;
            this.$refs.filterForm.querySelectorAll('input[name=&quot;brands[]&quot;]').forEach(el => {
                if (el.value === brand) {
                    el.checked = false;
                    matched = true;
                }
            });
            this.loading = true;
            if (matched) {
                this.$refs.filterForm.submit();
                return;
            }
            const url = new URL(window.location.href);
            for (const [k, v] of Array.from(url.searchParams.entries())) {
                if ((k === 'brands[]' || /^brands\[\d+\]$/.test(k)) && v === brand) {
                    url.searchParams.delete(k);
                }
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        },
        removeGender(code) {
            let matched = false;
            this.$refs.filterForm.querySelectorAll('input[name=&quot;genders[]&quot;]').forEach(el => {
                if (el.value === code) {
                    el.checked = false;
                    matched = true;
                }
            });
            this.loading = true;
            if (matched) {
                this.$refs.filterForm.submit();
                return;
            }
            const url = new URL(window.location.href);
            for (const [k, v] of Array.from(url.searchParams.entries())) {
                if ((k === 'genders[]' || /^genders\[\d+\]$/.test(k)) && v === code) {
                    url.searchParams.delete(k);
                }
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
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
            <svg class="animate-spin h-10 w-10 text-[#0066cc]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="app-text-body font-semibold animate-pulse">{{ __('Обновляем выдачу...') }}</span>
        </div>
    </template>

    <!-- Полноширинный контейнер Apple -->
    <div class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 pt-4 sm:pt-6 space-y-5 sm:space-y-6">

        <!-- Хлебные крошки: путь наверх без дублирования текущего заголовка H1 -->
        @php
            $parentCrumbs = !empty($breadcrumbs)
                ? array_values(array_filter($breadcrumbs, fn ($c) => !empty($c['url'])))
                : [['label' => __('Главная'), 'url' => route('home')]];
        @endphp
        @if(!empty($parentCrumbs))
            <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 app-text-xs text-[#6e6e73] overflow-x-auto no-scrollbar whitespace-nowrap">
                @if(count($parentCrumbs) === 1)
                    <a href="{{ $parentCrumbs[0]['url'] }}" class="inline-flex items-center gap-1 hover:text-[#0066cc] transition-colors shrink-0">
                        <span>&lsaquo;</span>
                        <span>{{ $parentCrumbs[0]['label'] }}</span>
                    </a>
                @else
                    @foreach($parentCrumbs as $crumb)
                        @if(!$loop->first)
                            <span class="shrink-0">&rsaquo;</span>
                        @endif
                        <a href="{{ $crumb['url'] }}" class="hover:text-[#0066cc] transition-colors shrink-0">
                            {{ $crumb['label'] }}
                        </a>
                    @endforeach
                @endif
            </nav>
        @endif

        <!-- Двухколоночная сетка на всю ширину: Слева сайдбар (десктоп), Справа подкатегории + выдача -->
        <div class="grid grid-cols-1 lg:grid-cols-[270px_minmax(0,1fr)] gap-6 xl:gap-8 items-start">

            <!-- САЙДБАР ФИЛЬТРОВ (на десктопе — левая колонка, на мобильном — выдвижная шторка) -->
            <div class="hidden lg:block"
                 :class="{
                    'hidden': !mobileFiltersOpen,
                    'fixed inset-0 z-[9990] flex bg-white/60 backdrop-blur-md lg:static lg:bg-transparent lg:backdrop-blur-none': mobileFiltersOpen
                 }"
                 @click.self="mobileFiltersOpen = false">

                <aside class="w-full max-w-sm lg:max-w-none bg-white h-full lg:h-auto overflow-y-auto lg:overflow-visible rounded-none lg:rounded-3xl border-r lg:border border-black/[0.06] p-5 sm:p-6 space-y-6 lg:sticky lg:top-20 ml-auto lg:ml-0">
                    <div class="flex items-center justify-between">
                        <h2 class="app-text-title font-semibold">{{ __('Фильтры') }}</h2>
                        <div class="flex items-center gap-3">
                            @if($activeFiltersCount > 0)
                                <a href="{{ $resetUrl }}" class="app-text-xs text-[#0066cc] font-medium hover:underline">
                                    {{ __('Сбросить') }}
                                </a>
                            @endif
                            <button type="button"
                                    @click="mobileFiltersOpen = false"
                                    aria-label="{{ __('Закрыть') }}"
                                    class="lg:hidden apple-icon-btn">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
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
                                       class="w-full px-3 py-2 app-text-xs rounded-xl bg-[#f5f5f7] border border-transparent focus:border-[#0066cc] focus:bg-white outline-none">
                                <input type="number" name="max_price" x-ref="maxPriceInput" value="{{ $filters['max_price'] ?? '' }}"
                                       placeholder="{{ __('До') }}" min="0"
                                       class="w-full px-3 py-2 app-text-xs rounded-xl bg-[#f5f5f7] border border-transparent focus:border-[#0066cc] focus:bg-white outline-none">
                                <button type="submit" class="px-3 py-2 rounded-xl bg-[#f5f5f7] hover:bg-[#0066cc] hover:text-white transition-colors shrink-0 cursor-pointer font-semibold" title="{{ __('Применить') }}">
                                    &rsaquo;
                                </button>
                            </div>

                            <div class="space-y-1.5 app-text-sub text-sm">
                                <button type="button" @click="setPresetPrice('', 50)" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">{{ __('До 50 €') }}</button>
                                <button type="button" @click="setPresetPrice(50, 100)" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">50 – 100 €</button>
                                <button type="button" @click="setPresetPrice(100, 150)" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">100 – 150 €</button>
                                <button type="button" @click="setPresetPrice(150, '')" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">{{ __('От 150 € и выше') }}</button>
                            </div>

                            <label class="flex items-center gap-2.5 pt-1 cursor-pointer app-text-sub text-sm font-medium text-red-600">
                                <input type="checkbox" name="discount" value="1" @checked(!empty($filters['discount'])) onchange="this.form.submit()"
                                       class="rounded border-zinc-300 text-[#0066cc] focus:ring-[#0066cc]">
                                <span>{{ __('Только со скидкой') }}</span>
                            </label>
                        </div>

                        <!-- Бренды (выбранные всегда закреплены наверху) -->
                        @if(!empty($facets['brands']))
                        <div class="space-y-2.5 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Бренды') }}</div>
                            <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                                @foreach($facets['brands'] as $idx => $b)
                                    @php $isBrandChecked = in_array($b['name'], $filters['brands'] ?? [], true); @endphp
                                    <label x-show="showAllBrands || {{ $idx }} < 7 || {{ $isBrandChecked ? 'true' : 'false' }}" class="flex items-center justify-between gap-2 app-text-sub text-sm hover:text-[#1d1d1f] cursor-pointer py-0.5">
                                        <span class="flex items-center gap-2 truncate">
                                            <input type="checkbox" name="brands[]" value="{{ $b['name'] }}"
                                                   @checked($isBrandChecked)
                                                   onchange="this.form.submit()"
                                                   class="rounded border-zinc-300 text-[#0066cc] focus:ring-[#0066cc]">
                                            <span class="truncate">{{ $b['name'] }}</span>
                                        </span>
                                        <span class="app-text-xs text-[#6e6e73] shrink-0">{{ number_format($b['count']) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if(count($facets['brands']) > 7)
                                <button type="button" @click="showAllBrands = !showAllBrands" class="app-text-xs font-medium text-[#0066cc] hover:underline cursor-pointer">
                                    <span x-text="showAllBrands ? '{{ __('Свернуть') }}' : '{{ __('Показать ещё') }}'"></span>
                                </button>
                            @endif
                        </div>
                        @endif

                        <!-- Пол (скрыт автоматически для техники, игрушек и категорий с 1 вариантом) -->
                        @if(count($facets['genders'] ?? []) > 1 || !empty($filters['genders']))
                        <div class="space-y-2 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Пол') }}</div>
                            @foreach($facets['genders'] as $g)
                                <label class="flex items-center justify-between app-text-sub text-sm cursor-pointer py-0.5">
                                    <span class="flex items-center gap-2">
                                        <input type="checkbox" name="genders[]" value="{{ $g['code'] }}"
                                               @checked(in_array($g['code'], $filters['genders'] ?? [], true))
                                               onchange="this.form.submit()"
                                               class="rounded border-zinc-300 text-[#0066cc] focus:ring-[#0066cc]">
                                        <span>{{ $genderLabels[$g['code']] ?? $g['code'] }}</span>
                                    </span>
                                    <span class="app-text-xs text-[#6e6e73]">{{ number_format($g['count']) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @endif

                        <!-- Размеры (адаптивные, без NO SIZE, без обрезки 32/32, в естественном порядке) -->
                        @if(!empty($facets['sizes']))
                        <div class="space-y-2.5 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Размер') }}</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($facets['sizes'] as $sz)
                                    @php
                                        $isSzActive = ($filters['size'] ?? '') === $sz;
                                        $szLabel = str_replace('_', '/', (string) $sz);
                                    @endphp
                                    <button type="button" @click="toggleSize(@js($sz))"
                                            class="min-w-[46px] py-1.5 px-2.5 app-text-xs font-medium rounded-full border text-center transition-all cursor-pointer whitespace-nowrap {{ $isSzActive ? 'bg-[#1d1d1f] text-white border-[#1d1d1f]' : 'bg-white text-[#1d1d1f] border-black/15 hover:border-[#0066cc]' }}">
                                        {{ $szLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Цвета с цветовыми квадратиками слева -->
                        @if(!empty($facets['colors']))
                        @php
                            $resolveSwatchCss = static function (string $label): string {
                                $n = mb_strtolower(trim($label));
                                return match (true) {
                                    str_contains($n, 'multicolor') || str_contains($n, 'πολύχρωμ') => 'linear-gradient(135deg, #ef4444 0%, #eab308 50%, #3b82f6 100%)',
                                    str_contains($n, 'μαύρ') || str_contains($n, 'black') || str_contains($n, 'noir') => '#1d1d1f',
                                    str_contains($n, 'λευκ') || str_contains($n, 'άσπρ') || str_contains($n, 'white') || str_contains($n, 'blanc') => '#ffffff',
                                    str_contains($n, 'σιελ') || str_contains($n, 'light blue') || str_contains($n, 'sky') || str_contains($n, 'γαλάζ') => '#7dd3fc',
                                    str_contains($n, 'denim') => '#3b6998',
                                    str_contains($n, 'σκούρο') && str_contains($n, 'μπλε') || str_contains($n, 'navy') || str_contains($n, 'dark blue') => '#1e3a8a',
                                    str_contains($n, 'μπλε') || str_contains($n, 'blue') || str_contains($n, 'bleu') => '#2563eb',
                                    str_contains($n, 'ανθρακ') || str_contains($n, 'anthracite') || str_contains($n, 'charcoal') => '#374151',
                                    str_contains($n, 'γκρι') || str_contains($n, 'grey') || str_contains($n, 'gray') || str_contains($n, 'gris') => '#9ca3af',
                                    str_contains($n, 'μπεζ') || str_contains($n, 'beige') || str_contains($n, 'ecru') || str_contains($n, 'εκρού') || str_contains($n, 'cream') => '#e7d8c1',
                                    str_contains($n, 'taupe') => '#8b7d70',
                                    str_contains($n, 'καφέ') || str_contains($n, 'brown') || str_contains($n, 'camel') || str_contains($n, 'ταμπά') => '#78350f',
                                    str_contains($n, 'κεραμιδ') || str_contains($n, 'terracotta') || str_contains($n, 'rust') => '#c2410c',
                                    str_contains($n, 'μπορντ') || str_contains($n, 'burgundy') || str_contains($n, 'bordeaux') || str_contains($n, 'wine') => '#7f1d1d',
                                    str_contains($n, 'κόκκιν') || str_contains($n, 'red') || str_contains($n, 'rouge') => '#dc2626',
                                    str_contains($n, 'ροζ') || str_contains($n, 'pink') || str_contains($n, 'rose') => '#f472b6',
                                    str_contains($n, 'πορτοκαλ') || str_contains($n, 'orange') => '#f97316',
                                    str_contains($n, 'κίτριν') || str_contains($n, 'yellow') => '#facc15',
                                    str_contains($n, 'χακί') || str_contains($n, 'khaki') || str_contains($n, 'olive') || str_contains($n, 'λαδί') => '#4d5d33',
                                    str_contains($n, 'πράσιν') || str_contains($n, 'green') || str_contains($n, 'vert') => '#16a34a',
                                    str_contains($n, 'μωβ') || str_contains($n, 'purple') || str_contains($n, 'violet') || str_contains($n, 'λιλά') => '#7c3aed',
                                    str_contains($n, 'χρυσ') || str_contains($n, 'gold') => '#d4af37',
                                    str_contains($n, 'ασημ') || str_contains($n, 'silver') => '#cbd5e1',
                                    default => '#d4d4d8',
                                };
                            };
                        @endphp
                        <div class="space-y-2 border-t border-black/[0.05] pt-4">
                            <div class="app-text-xs font-semibold uppercase tracking-wider text-[#1d1d1f]">{{ __('Цвет') }}</div>
                            <div class="space-y-1">
                                @foreach($facets['colors'] as $colName => $colCnt)
                                    @php
                                        $isColActive = ($filters['color'] ?? '') === $colName;
                                        $swatchBg = $resolveSwatchCss((string) $colName);
                                    @endphp
                                    <button type="button" @click="toggleColor(@js($colName))"
                                            class="flex items-center justify-between gap-2.5 w-full text-left app-text-sub text-sm py-1.5 px-2.5 rounded-xl transition-colors cursor-pointer {{ $isColActive ? 'bg-[#0066cc]/10 text-[#0066cc] font-semibold' : 'hover:bg-[#f5f5f7]' }}">
                                        <span class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-4 h-4 rounded-[5px] border border-black/15 shrink-0 shadow-2xs"
                                                  style="background: {{ $swatchBg }};"></span>
                                            <span class="truncate">{{ $colName }}</span>
                                        </span>
                                        <span class="app-text-xs text-[#6e6e73] shrink-0">{{ $colCnt }}</span>
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
                        <span class="app-text-sub text-[#6e6e73]">
                            {{ number_format($pagination['total']) }} {{ __('товаров') }}
                        </span>
                    @endif
                </div>

                <!-- Горизонтальная лента подкатегорий с реальными фото и drag-скроллингом -->
                @if(!empty($subcategories))
                    <div x-data="{
                            isDown: false,
                            startX: 0,
                            scrollLeft: 0,
                            moved: false,
                            onMouseDown(e) {
                                this.isDown = true;
                                this.moved = false;
                                this.startX = e.pageX - this.$el.offsetLeft;
                                this.scrollLeft = this.$el.scrollLeft;
                            },
                            onMouseMove(e) {
                                if (!this.isDown) return;
                                const x = e.pageX - this.$el.offsetLeft;
                                const walk = (x - this.startX) * 1.3;
                                if (Math.abs(walk) > 6) {
                                    this.moved = true;
                                    e.preventDefault();
                                    this.$el.scrollLeft = this.scrollLeft - walk;
                                }
                            },
                            onMouseUp() {
                                this.isDown = false;
                            }
                         }"
                         @mousedown="onMouseDown($event)"
                         @mousemove="onMouseMove($event)"
                         @mouseup="onMouseUp()"
                         @mouseleave="onMouseUp()"
                         @click.capture="if (moved) { $event.preventDefault(); $event.stopPropagation(); moved = false; }"
                         class="flex items-stretch gap-2.5 sm:gap-3 overflow-x-auto no-scrollbar pb-1 cursor-grab active:cursor-grabbing select-none"
                    >
                        @foreach($subcategories as $sub)
                            @php $isSubActive = $activeSubIndex !== null && (int)$activeSubIndex === (int)$sub['index']; @endphp
                            @if(!empty($sub['url']))
                                <a href="{{ $sub['url'] }}"
                                   draggable="false"
                                   class="flex flex-col items-center justify-between w-24 sm:w-28 shrink-0 p-2.5 rounded-2xl bg-white border border-black/[0.06] hover:border-[#0066cc]/40 transition-all text-center">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 flex items-center justify-center overflow-hidden rounded-xl bg-[#f5f5f7] p-1 mb-2 pointer-events-none">
                                        @if(!empty($sub['image']))
                                            <img src="{{ $sub['image'] }}" alt="{{ $sub['label'] }}" width="64" height="64" loading="lazy" draggable="false" class="w-full h-full object-contain mix-blend-multiply">
                                        @else
                                            <span class="app-text-xs font-bold text-[#6e6e73]">{{ mb_substr($sub['label'], 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <span class="app-text-xs font-medium text-[#1d1d1f] line-clamp-2 leading-tight">
                                        {{ $sub['label'] }}
                                    </span>
                                </a>
                            @else
                                <button type="button"
                                        @click="selectSub({{ (int) $sub['index'] }})"
                                        class="flex flex-col items-center justify-between w-24 sm:w-28 shrink-0 p-2.5 rounded-2xl border transition-all cursor-pointer text-center {{ $isSubActive ? 'bg-white border-[#0066cc] ring-2 ring-[#0066cc]/20 shadow-sm' : 'bg-white border-black/[0.06] hover:border-black/20' }}">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 flex items-center justify-center overflow-hidden rounded-xl bg-[#f5f5f7] p-1 mb-2 pointer-events-none">
                                        @if(!empty($sub['image']))
                                            <img src="{{ $sub['image'] }}" alt="{{ $sub['label'] }}" width="64" height="64" loading="lazy" draggable="false" class="w-full h-full object-contain mix-blend-multiply">
                                        @else
                                            <span class="app-text-xs font-bold text-[#6e6e73]">{{ mb_substr($sub['label'], 0, 2) }}</span>
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

                <!-- Компактная панель быстрых фильтров и снимаемых активных чипсов -->
                <div class="flex items-center justify-between gap-2 bg-white px-3 sm:px-4 py-1.5 sm:py-2.5 rounded-2xl border border-black/[0.06]">
                    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1 pl-0.5 pr-1">
                        <!-- Кнопка открытия шторки фильтров (мобильный) -->
                        <button type="button" @click="mobileFiltersOpen = true"
                                aria-label="{{ __('Фильтры') }}"
                                class="lg:hidden relative inline-flex items-center justify-center gap-1.5 w-10 h-10 sm:w-auto sm:h-auto sm:px-4 sm:py-2 rounded-full text-xs font-semibold bg-[#0066cc] text-white shrink-0 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span class="hidden sm:inline">{{ __('Фильтры') }}</span>
                            @if($activeFiltersCount > 0)
                                <span class="absolute -top-1 -right-1 sm:static min-w-[18px] h-[18px] px-1 sm:w-auto sm:h-auto flex items-center justify-center text-[10px] sm:text-xs sm:px-1.5 bg-[#1d1d1f] sm:bg-white/20 text-white rounded-full ring-2 ring-white sm:ring-0 leading-none">{{ $activeFiltersCount }}</span>
                            @endif
                        </button>

                        <!-- Кнопка скидок -->
                        <a href="{{ request()->fullUrlWithQuery(['discount' => empty($filters['discount']) ? 1 : null, 'page' => null]) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full app-text-xs font-medium border transition-colors shrink-0 {{ !empty($filters['discount']) ? 'bg-red-600 text-white border-red-600' : 'bg-white text-[#1d1d1f] border-black/15 hover:border-[#0066cc]' }}">
                            <span>&#9889; {{ __('Скидки') }}</span>
                        </a>

                        <!-- Активные фильтры: кликабельные чипсы с кнопкой снятия × -->
                        @foreach($filters['brands'] ?? [] as $activeBrand)
                            <button type="button"
                                    @click="removeBrand(@js($activeBrand))"
                                    title="{{ __('Убрать фильтр') }}"
                                    class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] hover:bg-[#3a3a3c] text-white transition-colors shrink-0 cursor-pointer">
                                <span>{{ $activeBrand }}</span>
                                <svg class="w-3.5 h-3.5 text-white/75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endforeach

                        @foreach($filters['genders'] ?? [] as $activeGender)
                            <button type="button"
                                    @click="removeGender(@js($activeGender))"
                                    title="{{ __('Убрать фильтр') }}"
                                    class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] hover:bg-[#3a3a3c] text-white transition-colors shrink-0 cursor-pointer">
                                <span>{{ $genderLabels[$activeGender] ?? $activeGender }}</span>
                                <svg class="w-3.5 h-3.5 text-white/75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endforeach

                        @if(!empty($filters['size']))
                            <button type="button"
                                    @click="toggleSize(@js($filters['size']))"
                                    title="{{ __('Убрать фильтр') }}"
                                    class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] hover:bg-[#3a3a3c] text-white transition-colors shrink-0 cursor-pointer">
                                <span>{{ str_replace('_', '/', (string) $filters['size']) }}</span>
                                <svg class="w-3.5 h-3.5 text-white/75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif

                        @if(!empty($filters['color']))
                            <button type="button"
                                    @click="toggleColor(@js($filters['color']))"
                                    title="{{ __('Убрать фильтр') }}"
                                    class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] hover:bg-[#3a3a3c] text-white transition-colors shrink-0 cursor-pointer">
                                <span>{{ $filters['color'] }}</span>
                                <svg class="w-3.5 h-3.5 text-white/75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif

                        @if($hasPriceFilter)
                            <button type="button"
                                    @click="setPresetPrice('', '')"
                                    title="{{ __('Убрать фильтр') }}"
                                    class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] hover:bg-[#3a3a3c] text-white transition-colors shrink-0 cursor-pointer">
                                <span>{{ $priceChipLabel }}</span>
                                <svg class="w-3.5 h-3.5 text-white/75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif

                        @if($activeFiltersCount > 1)
                            <a href="{{ $resetUrl }}"
                               class="px-2.5 py-1.5 text-xs font-medium text-[#0066cc] hover:underline shrink-0">
                                {{ __('Сбросить') }}
                            </a>
                        @endif
                    </div>

                    <!-- Сортировка справа -->
                    <div class="relative shrink-0" x-data="{ openSort: false }" @click.outside="openSort = false">
                        <button type="button" @click="openSort = !openSort"
                                aria-label="{{ __('Сортировка') }}"
                                class="inline-flex items-center justify-center gap-1.5 w-10 h-10 sm:w-auto sm:h-auto sm:px-4 sm:py-2 rounded-full text-xs font-medium bg-[#f5f5f7] hover:bg-[#e8e8ed] text-[#1d1d1f] transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                            </svg>
                            <span class="hidden sm:inline">{{ $sortLabels[$activeSort] ?? __('Рекомендуемые') }}</span>
                        </button>

                        <div x-show="openSort" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-black/[0.08] py-1.5 z-40">
                            @foreach($sortLabels as $sKey => $sLabel)
                                <button type="button" @click="setSort('{{ $sKey }}')"
                                        class="block w-full text-left px-4 py-2 text-xs transition-colors cursor-pointer {{ $activeSort === $sKey ? 'text-[#0066cc] font-semibold bg-[#0066cc]/5' : 'text-[#1d1d1f] hover:bg-[#f5f5f7]' }}">
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
                            <x-public-ui.product-card :product="$product" :is-first="$loop->index < 2" />
                        @endforeach
                    </div>

                    <!-- Кнопка «Показать ещё» (реальная ссылка ?page=N для Googlebot + AJAX для пользователей) -->
                    @if($pagination['current_page'] < $pagination['last_page'])
                        <div x-show="currentPage < lastPage" class="flex flex-col items-center justify-center pt-6 space-y-2">
                            <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}"
                               :href="(() => { const p = new URLSearchParams(window.location.search); p.set('page', String(currentPage + 1)); return `${window.location.pathname}?${p.toString()}`; })()"
                               @click.prevent="loadMore()"
                               :class="{ 'opacity-60 pointer-events-none': loadingMore }"
                               rel="next"
                               class="px-8 py-3 rounded-full bg-white hover:bg-[#e8e8ed] text-[#1d1d1f] app-text-body text-sm font-semibold shadow-sm border border-black/[0.08] transition-all cursor-pointer">
                                <span x-text="loadingMore ? '{{ __('Загружаем...') }}' : '{{ __('Показать ещё') }}'">{{ __('Показать ещё') }}</span>
                            </a>
                            <span class="app-text-xs text-[#6e6e73]">
                                {{ __('Показано') }} <span x-text="shownCount">{{ count($products) }}</span> {{ __('из') }} <span x-text="totalItems">{{ (int) $pagination['total'] }}</span>
                            </span>
                        </div>
                    @endif
                @else
                    <div class="bg-white rounded-3xl border border-black/[0.06] p-10 sm:p-12 text-center max-w-md mx-auto space-y-3">
                        <h3 class="app-text-title font-semibold">{{ __('Товары не найдены') }}</h3>
                        <p class="app-text-sub text-sm">{{ __('Попробуйте изменить запрос, ослабить фильтры или выбрать категорию из меню сверху.') }}</p>
                        <div class="pt-2">
                            <button type="button" @click="$dispatch('open-categories')" class="apple-btn-primary">
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