<x-layouts.public
    title="{{ $categoryTitle }} — {{ __('Купить в Греции с доставкой в Грузию') }} | Exereto"
    description="{{ $categoryTitle }} — {{ __('оригинальные бренды из магазинов Греции с доставкой в Грузию.') }}"
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
@endphp

<div class="min-h-screen bg-[#f5f5f7] text-[#1d1d1f] antialiased pb-20"
     x-data="{
        mobileFiltersOpen: false,
        showAllBrands: false,
        loadingMore: false,
        currentPage: {{ (int) $pagination['current_page'] }},
        lastPage: {{ (int) $pagination['last_page'] }},
        totalItems: {{ (int) $pagination['total'] }},
        shownCount: {{ count($products) }},
        setPresetPrice(min, max) {
            this.$refs.minPriceInput.value = min ?? '';
            this.$refs.maxPriceInput.value = max ?? '';
            this.$refs.filterForm.submit();
        },
        toggleSize(sz) {
            this.$refs.sizeInput.value = (this.$refs.sizeInput.value === sz) ? '' : sz;
            this.$refs.filterForm.submit();
        },
        toggleColor(col) {
            this.$refs.colorInput.value = (this.$refs.colorInput.value === col) ? '' : col;
            this.$refs.filterForm.submit();
        },
        selectSub(idx) {
            this.$refs.subInput.value = (String(this.$refs.subInput.value) === String(idx)) ? '' : idx;
            this.$refs.filterForm.submit();
        },
        setSort(val) {
            this.$refs.sortInput.value = val;
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
                const grid = document.getElementById('category-products-grid');
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
>
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-6">

        <!-- Хлебные крошки -->
        <nav class="flex items-center gap-2 text-xs text-[#6e6e73]">
            <a href="{{ route('home') }}" class="hover:text-[#0066cc] transition-colors">{{ __('Главная') }}</a>
            <span>&rsaquo;</span>
            <span class="text-[#1d1d1f] font-medium">{{ $categoryTitle }}</span>
        </nav>

        <!-- Двухколоночная сетка: Слева фильтры, Справа подкатегории + выдача -->
        <div class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-8 items-start">

            <!-- ЛЕВЫЙ САЙДБАР ФИЛЬТРОВ -->
            <aside class="hidden lg:block bg-white rounded-3xl border border-black/[0.06] p-5 space-y-6 sticky top-20">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-[#1d1d1f]">{{ __('Фильтры') }}</h2>
                    @if(request()->query())
                        <a href="{{ route('feed-category.show', ['slug' => $tile['slug']]) }}" class="text-xs text-[#0066cc] hover:underline">
                            {{ __('Сбросить') }}
                        </a>
                    @endif
                </div>

                <form action="{{ route('feed-category.show', ['slug' => $tile['slug']]) }}" method="GET" x-ref="filterForm" class="space-y-6">
                    <input type="hidden" name="sub" x-ref="subInput" value="{{ $activeSubIndex ?? '' }}">
                    <input type="hidden" name="size" x-ref="sizeInput" value="{{ $filters['size'] ?? '' }}">
                    <input type="hidden" name="color" x-ref="colorInput" value="{{ $filters['color'] ?? '' }}">
                    <input type="hidden" name="sort" x-ref="sortInput" value="{{ $activeSort }}">

                    <!-- Диапазон цен -->
                    <div class="space-y-3 border-t border-black/[0.05] pt-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#1d1d1f]">{{ __('Цена, €') }}</div>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" x-ref="minPriceInput" value="{{ $filters['min_price'] ?? '' }}"
                                   placeholder="{{ __('От') }}" min="0"
                                   class="w-full px-3 py-2 text-xs rounded-xl bg-[#f5f5f7] border border-transparent focus:border-[#0066cc] focus:bg-white outline-none">
                            <input type="number" name="max_price" x-ref="maxPriceInput" value="{{ $filters['max_price'] ?? '' }}"
                                   placeholder="{{ __('До') }}" min="0"
                                   class="w-full px-3 py-2 text-xs rounded-xl bg-[#f5f5f7] border border-transparent focus:border-[#0066cc] focus:bg-white outline-none">
                            <button type="submit" class="p-2 rounded-xl bg-[#f5f5f7] hover:bg-[#0066cc] hover:text-white transition-colors shrink-0 cursor-pointer" title="{{ __('Применить') }}">
                                &rsaquo;
                            </button>
                        </div>

                        <div class="space-y-1.5 text-xs text-[#424245]">
                            <button type="button" @click="setPresetPrice('', 50)" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">{{ __('До 50 €') }}</button>
                            <button type="button" @click="setPresetPrice(50, 100)" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">50 – 100 €</button>
                            <button type="button" @click="setPresetPrice(100, 150)" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">100 – 150 €</button>
                            <button type="button" @click="setPresetPrice(150, '')" class="block w-full text-left py-1 hover:text-[#0066cc] cursor-pointer">{{ __('От 150 € и выше') }}</button>
                        </div>

                        <label class="flex items-center gap-2.5 pt-1 cursor-pointer text-xs font-medium text-red-600">
                            <input type="checkbox" name="discount" value="1" @checked(!empty($filters['discount'])) onchange="this.form.submit()"
                                   class="rounded border-zinc-300 text-[#0066cc] focus:ring-[#0066cc]">
                            <span>{{ __('Только со скидкой') }}</span>
                        </label>
                    </div>

                    <!-- Производители / Бренды -->
                    @if(!empty($facets['brands']))
                    <div class="space-y-2.5 border-t border-black/[0.05] pt-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#1d1d1f]">{{ __('Бренды') }}</div>
                        <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                            @foreach($facets['brands'] as $idx => $b)
                                <label x-show="showAllBrands || {{ $idx }} < 7" class="flex items-center justify-between gap-2 text-xs text-[#424245] hover:text-[#1d1d1f] cursor-pointer py-0.5">
                                    <span class="flex items-center gap-2 truncate">
                                        <input type="checkbox" name="brands[]" value="{{ $b['name'] }}"
                                               @checked(in_array($b['name'], $filters['brands'] ?? [], true))
                                               onchange="this.form.submit()"
                                               class="rounded border-zinc-300 text-[#0066cc] focus:ring-[#0066cc]">
                                        <span class="truncate">{{ $b['name'] }}</span>
                                    </span>
                                    <span class="text-[11px] text-[#6e6e73] shrink-0">{{ number_format($b['count']) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if(count($facets['brands']) > 7)
                            <button type="button" @click="showAllBrands = !showAllBrands" class="text-xs font-medium text-[#0066cc] hover:underline cursor-pointer">
                                <span x-text="showAllBrands ? '{{ __('Свернуть') }}' : '{{ __('Показать ещё') }}'"></span>
                            </button>
                        @endif
                    </div>
                    @endif

                    <!-- Пол -->
                    @if(count($facets['genders'] ?? []) > 1)
                    <div class="space-y-2 border-t border-black/[0.05] pt-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#1d1d1f]">{{ __('Пол') }}</div>
                        @foreach($facets['genders'] as $g)
                            <label class="flex items-center justify-between text-xs text-[#424245] cursor-pointer py-0.5">
                                <span class="flex items-center gap-2">
                                    <input type="checkbox" name="genders[]" value="{{ $g['code'] }}"
                                           @checked(in_array($g['code'], $filters['genders'] ?? [], true))
                                           onchange="this.form.submit()"
                                           class="rounded border-zinc-300 text-[#0066cc] focus:ring-[#0066cc]">
                                    <span>{{ $genderLabels[$g['code']] ?? $g['code'] }}</span>
                                </span>
                                <span class="text-[11px] text-[#6e6e73]">{{ number_format($g['count']) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @endif

                    <!-- Размеры -->
                    @if(!empty($facets['sizes']))
                    <div class="space-y-2.5 border-t border-black/[0.05] pt-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#1d1d1f]">{{ __('Размер') }}</div>
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach($facets['sizes'] as $sz)
                                @php $isSzActive = ($filters['size'] ?? '') === $sz; @endphp
                                <button type="button" @click="toggleSize(@js($sz))"
                                        class="py-1.5 px-1 text-[11px] font-medium rounded-full border text-center transition-all cursor-pointer truncate {{ $isSzActive ? 'bg-[#1d1d1f] text-white border-[#1d1d1f]' : 'bg-white text-[#1d1d1f] border-black/15 hover:border-[#0066cc]' }}">
                                    {{ $sz }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Цвета -->
                    @if(!empty($facets['colors']))
                    <div class="space-y-2 border-t border-black/[0.05] pt-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#1d1d1f]">{{ __('Цвет') }}</div>
                        <div class="space-y-1.5">
                            @foreach($facets['colors'] as $colName => $colCnt)
                                @php $isColActive = ($filters['color'] ?? '') === $colName; @endphp
                                <button type="button" @click="toggleColor(@js($colName))"
                                        class="flex items-center justify-between w-full text-left text-xs py-1 px-2 rounded-lg transition-colors cursor-pointer {{ $isColActive ? 'bg-[#0066cc]/10 text-[#0066cc] font-semibold' : 'text-[#424245] hover:bg-[#f5f5f7]' }}">
                                    <span class="truncate">{{ $colName }}</span>
                                    <span class="text-[11px] text-[#6e6e73]">{{ $colCnt }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </form>
            </aside>

            <!-- ПРАВАЯ ЧАСТЬ: ЗАГОЛОВОК, ПОДКАТЕГОРИИ, ПАНЕЛЬ СОРТИРОВКИ И СЕТКА ТОВАРОВ -->
            <div class="space-y-6 min-w-0">

                <!-- Заголовок категории + счётчик моделей -->
                <div class="flex flex-wrap items-baseline gap-3">
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-[#1d1d1f]">
                        {{ $categoryTitle }}
                    </h1>
                    <span class="text-sm text-[#6e6e73]">
                        {{ number_format($pagination['total']) }} {{ __('моделей') }}
                    </span>
                </div>

                <!-- Горизонтальная лента подкатегорий с мини-превью -->
                @if(!empty($subcategories))
                    <div class="flex items-stretch gap-3 overflow-x-auto no-scrollbar pb-2">
                        @foreach($subcategories as $sub)
                            @php $isSubActive = $activeSubIndex !== null && (int)$activeSubIndex === (int)$sub['index']; @endphp
                            <button type="button"
                                    @click="selectSub({{ (int) $sub['index'] }})"
                                    class="flex flex-col items-center justify-between w-28 sm:w-32 shrink-0 p-2.5 rounded-2xl border transition-all cursor-pointer text-center {{ $isSubActive ? 'bg-white border-[#0066cc] ring-2 ring-[#0066cc]/20 shadow-sm' : 'bg-white border-black/[0.06] hover:border-black/20' }}">
                                <div class="w-16 h-16 flex items-center justify-center overflow-hidden rounded-xl bg-[#f5f5f7] mb-2">
                                    @if(!empty($sub['image']))
                                        <img src="{{ $sub['image'] }}" alt="{{ $sub['label'] }}" class="max-w-full max-h-full object-contain">
                                    @else
                                        <span class="text-xs font-bold text-[#6e6e73]">{{ mb_substr($sub['label'], 0, 2) }}</span>
                                    @endif
                                </div>
                                <span class="text-xs font-medium text-[#1d1d1f] line-clamp-2 leading-tight">
                                    {{ $sub['label'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <!-- Верхняя панель быстрых пилюль + сортировка справа -->
                <div class="flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-3 rounded-2xl border border-black/[0.06]">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Кнопка фильтров на мобильном -->
                        <button type="button" @click="mobileFiltersOpen = true"
                                class="lg:hidden inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#f5f5f7] text-[#1d1d1f]">
                            <span>{{ __('Фильтры') }}</span>
                        </button>

                        <a href="{{ request()->fullUrlWithQuery(['discount' => empty($filters['discount']) ? 1 : null]) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-colors {{ !empty($filters['discount']) ? 'bg-red-600 text-white border-red-600' : 'bg-white text-[#1d1d1f] border-black/15 hover:border-[#0066cc]' }}">
                            <span>&#9889; {{ __('Скидки') }}</span>
                        </a>

                        @if(!empty($filters['brands']))
                            @foreach($filters['brands'] as $activeBrand)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] text-white">
                                    {{ $activeBrand }}
                                </span>
                            @endforeach
                        @endif

                        @if(!empty($filters['size']))
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-[#1d1d1f] text-white">
                                {{ __('Размер') }}: {{ $filters['size'] }}
                            </span>
                        @endif
                    </div>

                    <!-- Выпадающая сортировка справа -->
                    <div class="relative ml-auto" x-data="{ openSort: false }" @click.outside="openSort = false">
                        <button type="button" @click="openSort = !openSort"
                                class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-medium bg-[#f5f5f7] hover:bg-[#e8e8ed] text-[#1d1d1f] transition-colors cursor-pointer">
                            <span>&uarr;&darr; {{ $sortLabels[$activeSort] ?? __('Рекомендуемые') }}</span>
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

                <!-- Сетка карточек товаров (4 колонки) -->
                @if(!empty($products))
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5" id="category-products-grid">
                        @foreach($products as $product)
                            <x-public-ui.product-card :product="$product" :is-first="$loop->index < 2" />
                        @endforeach
                    </div>

                    <!-- Кнопка «Показать ещё» -->
                    <div x-show="currentPage < lastPage" x-cloak class="flex flex-col items-center justify-center pt-6 space-y-2">
                        <button type="button"
                                @click="loadMore()"
                                :disabled="loadingMore"
                                class="px-8 py-3 rounded-full bg-white hover:bg-[#e8e8ed] text-[#1d1d1f] text-sm font-semibold shadow-sm border border-black/[0.08] transition-all cursor-pointer disabled:opacity-60">
                            <span x-text="loadingMore ? '{{ __('Загружаем...') }}' : '{{ __('Показать ещё') }}'"></span>
                        </button>
                        <span class="text-xs text-[#6e6e73]">
                            {{ __('Показано') }} <span x-text="shownCount"></span> {{ __('из') }} <span x-text="totalItems"></span>
                        </span>
                    </div>
                @else
                    <div class="bg-white rounded-3xl border border-black/[0.06] p-12 text-center max-w-md mx-auto space-y-3">
                        <h3 class="text-lg font-semibold text-[#1d1d1f]">{{ __('Товары не найдены') }}</h3>
                        <p class="text-sm text-[#6e6e73]">{{ __('Попробуйте ослабить фильтры или сбросить выбранные параметры.') }}</p>
                        <a href="{{ route('feed-category.show', ['slug' => $tile['slug']]) }}" class="apple-btn-primary mt-2">
                            {{ __('Сбросить фильтры') }}
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
</x-layouts.public>