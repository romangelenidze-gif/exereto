@php
    $showDormant = (bool) config('services.exereto.show_dormant', false);

    $links = [];
    if ($showDormant) {
        $links[] = ['route' => 'how-it-works', 'label' => __('Как это работает')];
        $links[] = ['route' => 'buy-for-me',   'label' => __('Выкупите за меня')];
        $links[] = ['route' => 'forwarding',   'label' => __('Форвардинг')];
    }
    $links[] = ['route' => 'help',    'label' => __('Помощь')];
    $links[] = ['route' => 'terms',   'label' => __('Условия обслуживания')];
    $links[] = ['route' => 'privacy', 'label' => __('Политика конфиденциальности')];
@endphp

<footer class="apple-footer !py-10 lg:!py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center text-center gap-6 lg:grid lg:grid-cols-[1.35fr_0.75fr_1.15fr] lg:items-stretch lg:text-left lg:gap-0">

            {{-- Колонка 1: Логотип, подзаголовок и капсулы ссылок --}}
            <div class="order-1 flex flex-col items-center lg:items-start lg:justify-center lg:pr-8 space-y-4">
                <div class="flex flex-col items-center gap-1.5 lg:flex-row lg:items-center lg:gap-3">
                    <a href="{{ route('home') }}" class="shrink-0 hover:opacity-80 transition-opacity" aria-label="exereto">
                        <svg class="w-9 h-9 lg:w-8 lg:h-8" viewBox="16 12 96 102" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path fill="#141416" stroke="#141416" stroke-width="5" stroke-linejoin="round"
                                  d="M64 16 L104 38.5 L88.5 47.2 L64 33.4 L32.5 51.2 L48 59.9 L84.5 39.4 L99.5 47.8 L56.5 72.1 L22.5 53 Z"/>
                            <path fill="#141416" stroke="#141416" stroke-width="5" stroke-linejoin="round"
                                  d="M22.5 72.5 L38 81.2 L64 95.8 L95.5 78 L105.5 83.6 L64 107 L22.5 83.6 Z"/>
                        </svg>
                    </a>
                    <div class="space-y-0.5">
                        <a href="{{ route('home') }}" class="block text-[22px] lg:text-xl leading-none font-bold tracking-tight text-[#1d1d1f]">
                            exereto
                        </a>
                        <p class="text-xs text-[#6e6e73] leading-snug">
                            {{ __('Платформа поиска и сравнения товаров в интернет-магазинах Греции.') }}
                        </p>
                    </div>
                </div>

                <nav class="flex flex-wrap items-center justify-center lg:justify-start gap-2">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route']) }}"
                           class="px-3.5 py-1.5 rounded-full bg-black/[0.045] hover:bg-black/[0.09] text-xs font-medium text-[#424245] hover:text-[#1d1d1f] transition-colors">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>

            {{-- Колонка 2 на ПК (и нижний блок на мобильном): Копирайт + мобильный язык --}}
            <div class="order-3 lg:order-2 w-full lg:w-auto flex flex-col items-center justify-center gap-3 pt-5 border-t border-black/[0.06] lg:pt-0 lg:border-t-0 lg:border-x lg:border-black/[0.06] lg:px-8">
                <div class="text-xs text-[#6e6e73] text-center">
                    {{ __('Все права защищены') }} © {{ date('Y') }} exereto
                </div>
                <div class="lg:hidden">
                    <x-language-switcher theme="light" direction="up" />
                </div>
            </div>

            {{-- Колонка 3 на ПК (и средний блок на мобильном): Дисклеймер официальных цен --}}
            <div class="order-2 lg:order-3 flex items-center justify-center lg:justify-start lg:pl-8">
                <p class="text-xs leading-relaxed text-[#6e6e73] max-w-md">
                    {{ __('Все товары на exereto представлены по официальным ценам и скидкам магазинов-партнёров, без дополнительных наценок. Комиссию сервиса оплачивают партнёры, а не покупатели.') }}
                </p>
            </div>

        </div>
    </div>
</footer>