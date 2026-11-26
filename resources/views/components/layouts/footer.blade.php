@php
    $showDormant = (bool) config('services.exereto.show_dormant', false);

    $sections = [];

    if ($showDormant) {
        $sections[] = [
            'title' => __('Услуги'),
            'links' => [
                ['route' => 'buy-for-me', 'label' => __('Выкупите за меня')],
                ['route' => 'forwarding', 'label' => __('Форвардинг')],
            ],
        ];
    }

    $infoLinks = [];
    if ($showDormant) {
        $infoLinks[] = ['route' => 'how-it-works', 'label' => __('Как это работает')];
    }
    $infoLinks[] = ['route' => 'help',    'label' => __('Помощь')];
    $infoLinks[] = ['route' => 'terms',   'label' => __('Условия обслуживания')];
    $infoLinks[] = ['route' => 'privacy', 'label' => __('Политика конфиденциальности')];

    $sections[] = [
        'title' => __('Информация'),
        'links' => $infoLinks,
    ];
@endphp

<footer class="apple-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="apple-footer-grid">

            {{-- Колонка 1: о бренде --}}
            <div>
                <div class="apple-footer-brand">exereto</div>
                <p class="apple-footer-text">
                    {{ __('Платформа поиска и сравнения товаров в интернет-магазинах Греции.') }}
                </p>
            </div>

            {{-- Информация (и Услуги, когда включены) --}}
            @foreach ($sections as $section)
                <div>
                    <h4 class="apple-footer-title">{{ $section['title'] }}</h4>
                    <ul class="apple-footer-list">
                        @foreach ($section['links'] as $link)
                            <li><a href="{{ route($link['route']) }}" class="apple-footer-link">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

        </div>

        <div class="apple-footer-bottom flex flex-col gap-3">
            <p class="text-xs leading-relaxed text-[#6e6e73] max-w-4xl">
                {{ __('Exereto — информационный каталог. Все товары представлены по официальным ценам магазинов; вознаграждение сервиса выплачивается партнёрами и не влияет на стоимость покупок.') }}
            </p>
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>© {{ date('Y') }} exereto. {{ __('Все права защищены.') }}</div>
                {{-- На ПК переключатель уже есть в шапке; в подвале показываем только на мобильных/планшетах --}}
                <div class="shrink-0 lg:hidden">
                    <x-language-switcher theme="light" direction="up" />
                </div>
            </div>
        </div>
    </div>
</footer>