@props(['brands' => []])
@php
    $showDormant = (bool) config('services.exereto.show_dormant', false);
@endphp

<section class="max-w-4xl mx-auto px-6 py-12 text-center space-y-6">
    <div class="space-y-2">
        <h2 class="apple-section-title text-2xl mb-2">
            {{ __('Популярные магазины и бренды') }}
        </h2>
        <p class="app-text-body text-[#424245] max-w-2xl mx-auto leading-relaxed">
            @if($showDormant)
                {{ __('Найдите нужный товар на сайте любого магазина Греции, скопируйте ссылку и отправьте её нам для выкупа или укажите наш греческий адрес при самостоятельном оформлении.') }}
            @else
                {{ __('Официальные цены, сезонные скидки и широкий выбор товаров греческого рынка. Находите нужные позиции в каталоге и переходите напрямую в выбранный магазин.') }}
            @endif
        </p>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-2">
        @foreach ($brands as $brand)
            <a href="{{ $brand['url'] }}" target="_blank" rel="noopener noreferrer" class="apple-brand-pill">
                {{ $brand['name'] }}
            </a>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-center gap-3 pt-3">
        @if($showDormant)
            <a href="{{ route('buy-for-me') }}" class="apple-outline-pill">
                {{ __('Выкупите за меня') }}
            </a>
            <a href="{{ route('forwarding') }}?type=forwarding" class="apple-outline-pill">
                {{ __('Форвардинг') }}
            </a>
        @else
            <a href="{{ route('help') }}" class="apple-outline-pill">
                {{ __('Как заказать и доставить') }}
            </a>
        @endif
    </div>
</section>