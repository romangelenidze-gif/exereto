<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#f5f5f7] scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? __('Exereto | Поиск товаров в онлайн магазинах и стоках Греции') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    @if(isset($description))
        <meta name="description" content="{{ $description }}">
    @endif

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