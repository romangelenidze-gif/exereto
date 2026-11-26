@php
    $appUrl = rtrim((string) config('app.url', 'https://exereto.com'), '/');
    $locale = app()->getLocale();
    $rawPath = trim(request()->path(), '/');

    // Снимаем языковой префикс, чтобы построить корректные hreflang-ссылки
    $basePath = preg_replace('#^(ru|ka)(/|$)#', '', $rawPath) ?? $rawPath;
    $basePath = trim($basePath, '/');

    $showDormant = (bool) config('services.exereto.show_dormant', false);
    $isDormantRoute = request()->routeIs([
        'how-it-works', '*.how-it-works',
        'buy-for-me', '*.buy-for-me',
        'forwarding', '*.forwarding',
        'login', '*.login',
        'register', '*.register',
        'password.*', '*.password.*',
        'verification.*', '*.verification.*',
    ]);
    $isSearchRoute = request()->routeIs(['product-search', '*.product-search']);
    $isCategoryRoute = request()->routeIs(['feed-category.show', '*.feed-category.show']);

    $filterKeys = ['sub', 'min_price', 'max_price', 'brands', 'genders', 'size', 'color', 'discount', 'sort'];
    $hasCategoryFilters = $isCategoryRoute && collect($filterKeys)->contains(function (string $key) {
        $val = request()->query($key);
        if ($key === 'sort') {
            return filled($val) && $val !== 'relevance';
        }
        return is_array($val) ? count(array_filter($val)) > 0 : filled($val);
    });

    // 1. Robots
    if (isset($robots)) {
        $seoRobots = $robots;
    } elseif (!$showDormant && $isDormantRoute) {
        $seoRobots = 'noindex, nofollow';
    } elseif ($isSearchRoute || $hasCategoryFilters) {
        $seoRobots = 'noindex, follow';
    } else {
        $seoRobots = 'index, follow';
    }

    $isIndexable = !str_contains($seoRobots, 'noindex');

    // 2. Canonical
    $currentCleanUrl = $rawPath === '' ? $appUrl . '/' : $appUrl . '/' . $rawPath;
    $pageNum = (int) request()->query('page', 1);

    if (isset($canonical)) {
        $seoCanonical = $canonical;
    } elseif ($isCategoryRoute && !$hasCategoryFilters && $pageNum > 1) {
        $seoCanonical = $currentCleanUrl . '?page=' . $pageNum;
    } else {
        $seoCanonical = $currentCleanUrl;
    }

    // 3. Hreflang (только на индексируемых страницах)
    $pageQuery = ($isCategoryRoute && !$hasCategoryFilters && $pageNum > 1) ? '?page=' . $pageNum : '';
    $hreflangUrls = [
        'en' => ($basePath === '' ? $appUrl . '/' : $appUrl . '/' . $basePath) . $pageQuery,
        'ru' => ($basePath === '' ? $appUrl . '/ru' : $appUrl . '/ru/' . $basePath) . $pageQuery,
        'ka' => ($basePath === '' ? $appUrl . '/ka' : $appUrl . '/ka/' . $basePath) . $pageQuery,
    ];

    // 4. OpenGraph
    $seoTitle = $title ?? __('Exereto | Поиск товаров в онлайн магазинах и стоках Греции');
    $seoDescription = $description ?? null;
    $seoOgType = $ogType ?? 'website';
    $seoOgImage = $ogImage ?? asset('images/home-banners/hero.webp');
    $ogLocaleMap = ['en' => 'en_US', 'ru' => 'ru_RU', 'ka' => 'ka_GE'];
    $seoOgLocale = $ogLocaleMap[$locale] ?? 'en_US';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#f5f5f7] scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $seoTitle }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoCanonical }}">

    @if($seoDescription)
        <meta name="description" content="{{ $seoDescription }}">
    @endif

    @if($isIndexable)
        <link rel="alternate" hreflang="en" href="{{ $hreflangUrls['en'] }}">
        <link rel="alternate" hreflang="ru" href="{{ $hreflangUrls['ru'] }}">
        <link rel="alternate" hreflang="ka" href="{{ $hreflangUrls['ka'] }}">
        <link rel="alternate" hreflang="x-default" href="{{ $hreflangUrls['en'] }}">
    @endif

    {{-- OpenGraph / Social --}}
    <meta property="og:site_name" content="Exereto">
    <meta property="og:locale" content="{{ $seoOgLocale }}">
    <meta property="og:type" content="{{ $seoOgType }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    @if($seoDescription)
        <meta property="og:description" content="{{ $seoDescription }}">
    @endif
    <meta property="og:url" content="{{ $seoCanonical }}">
    @if($seoOgImage)
        <meta property="og:image" content="{{ $seoOgImage }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    {{-- Структурированные данные Schema.org (JSON-LD) --}}
    @if(!empty($jsonLd))
        @php
            $schemas = isset($jsonLd['@context']) ? [$jsonLd] : $jsonLd;
        @endphp
        @foreach($schemas as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach
    @endif
    @stack('structured-data')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col min-h-full text-[#1d1d1f] antialiased font-sans bg-[#f5f5f7]"
      x-data="{ mobileNavOpen: false, searchOpen: false, categoriesDrawerOpen: false }"
      @open-categories.window="searchOpen = false; categoriesDrawerOpen = true"
      @open-search.window="categoriesDrawerOpen = false; searchOpen = true"
>

    <script>
        window.ExeretoRecent = (() => {
            const KEY = 'exereto_recent_v1';
            const MAX = 8;

            const read = () => {
                try {
                    const raw = window.sessionStorage.getItem(KEY);
                    const parsed = raw ? JSON.parse(raw) : [];
                    return Array.isArray(parsed) ? parsed.filter(i => i && i.feed && i.sku) : [];
                } catch {
                    return [];
                }
            };

            const write = (items) => {
                try {
                    window.sessionStorage.setItem(KEY, JSON.stringify(items.slice(0, MAX)));
                } catch {}
            };

            return {
                get() {
                    const lang = document.documentElement.lang || 'en';
                    const prefix = (lang === 'ru' || lang === 'ka') ? '/' + lang : '';
                    return read().map(item => ({
                        ...item,
                        url: `${prefix}/product/${encodeURIComponent(item.feed)}/${encodeURIComponent(item.sku)}`,
                    }));
                },
                push(product) {
                    if (!product || !product.feed || !product.sku) return;
                    const id = `${product.feed}:${product.sku}`;
                    const filtered = read().filter(i => `${i.feed}:${i.sku}` !== id);
                    filtered.unshift({
                        feed: String(product.feed),
                        sku: String(product.sku),
                        brand: String(product.brand || ''),
                        title: String(product.title || ''),
                        price: String(product.price || ''),
                        discount: Number(product.discount || 0),
                        image: String(product.image || ''),
                    });
                    write(filtered);
                },
                clear() {
                    try {
                        window.sessionStorage.removeItem(KEY);
                    } catch {}
                },
            };
        })();
    </script>

    <!-- Header + Drawer -->
    <x-layouts.header />

    <!-- Main Content -->
    <main class="flex-grow relative">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <x-layouts.footer />

</body>
</html>