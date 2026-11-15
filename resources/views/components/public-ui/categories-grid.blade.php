@props(['categories' => []])

<section class="apple-categories">
    <div class="apple-categories-head">
        <h2 class="apple-section-title">
            {{ __('Популярные категории') }}
        </h2>
        <p class="apple-section-subtitle">
            <span>{{ __('Выбирайте оригинальную одежду, обувь, парфюмерию и уход из ведущих магазинов Греции с быстрой доставкой в Грузию.') }}</span>
            <x-public-ui.nav-tooltip />
        </p>
    </div>

    <div class="apple-categories-grid">
        @foreach($categories as $cat)
            @php
                $imgFile = $cat['img'] ?? ($cat['slug'] . '.png');
                $hasLocalFile = file_exists(public_path('images/categories/' . $imgFile));
            @endphp
            <a href="{{ $cat['url'] ?? '#' }}" class="apple-category-card group">
                <div class="apple-category-media">
                    @if($hasLocalFile)
                        <img src="{{ asset('images/categories/' . $imgFile) }}"
                             alt="{{ $cat['label'] ?? $cat['name'] ?? '' }}"
                             loading="lazy"
                             class="apple-category-img">
                    @else
                        <div class="w-12 h-12 rounded-2xl bg-[#f5f5f7] flex items-center justify-center text-[#1d1d1f]/60 group-hover:text-[#0071e3] transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                    @endif
                </div>
                <span class="apple-category-name">
                    {{ $cat['label'] ?? __($cat['name'] ?? '') }}
                </span>
            </a>
        @endforeach
    </div>
</section>