@props(['categories' => []])

@php
    $items = collect($categories)->values();
    if ($items->isEmpty()) {
        $items = app(\App\Services\FeedCategoryTiles::class)->get()->values();
    }
    $featured = $items->take(3);
    $rest = $items->slice(3);
@endphp

<template x-teleport="body">
    <div x-show="categoriesDrawerOpen"
         x-cloak
         @open-categories.window="categoriesDrawerOpen = true"
         x-effect="document.body.classList.toggle('overflow-hidden', categoriesDrawerOpen)"
         @keydown.escape.window="categoriesDrawerOpen = false"
         class="fixed inset-0 z-[9998] flex justify-end"
         @click.self="categoriesDrawerOpen = false"
    >
        <div x-show="categoriesDrawerOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full opacity-0"
             x-transition:enter-end="translate-y-0 sm:translate-x-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:translate-x-0 opacity-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full opacity-0"
             class="w-full sm:max-w-md bg-white h-full flex flex-col shadow-2xl overflow-hidden sm:rounded-none"
        >
            <!-- Шапка шторки -->
            <div class="relative flex items-center justify-center px-5 py-4 border-b border-black/[0.06] shrink-0">
                <h2 class="app-text-title font-semibold text-[#1d1d1f]">
                    {{ __('Категории') }}
                </h2>
                <button type="button"
                        @click="categoriesDrawerOpen = false"
                        aria-label="{{ __('Закрыть') }}"
                        class="apple-icon-btn absolute right-4">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Прокручиваемый список категорий -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-5">

                <!-- Верхние 3 выделенные карточки -->
                @if($featured->isNotEmpty())
                    <div class="grid grid-cols-3 gap-2.5">
                        @foreach($featured as $cat)
                            @php
                                $imgFile = $cat['img'] ?? ($cat['slug'] . '.png');
                                $hasLocalFile = file_exists(public_path('images/categories/' . $imgFile));
                            @endphp
                            <a href="{{ $cat['url'] }}"
                               class="flex flex-col items-center text-center gap-2 group">
                                <div class="w-full aspect-square rounded-2xl bg-[#f5f5f7] p-3 flex items-center justify-center group-hover:bg-[#e8e8ed] transition-colors">
                                    @if($hasLocalFile)
                                        <img src="{{ asset('images/categories/' . $imgFile) }}"
                                             alt="{{ $cat['label'] }}"
                                             class="max-w-full max-h-full object-contain">
                                    @else
                                        <svg class="w-7 h-7 text-[#1d1d1f]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    @endif
                                </div>
                                <span class="app-text-xs font-medium text-[#1d1d1f] line-clamp-2 leading-tight">
                                    {{ $cat['label'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <!-- Вертикальный список остальных категорий -->
                <div class="space-y-2.5">
                    @foreach($rest as $cat)
                        @php
                            $imgFile = $cat['img'] ?? ($cat['slug'] . '.png');
                            $hasLocalFile = file_exists(public_path('images/categories/' . $imgFile));
                        @endphp
                        <a href="{{ $cat['url'] }}"
                           class="flex items-center justify-between gap-3.5 p-3.5 rounded-2xl bg-[#f5f5f7] hover:bg-[#e8e8ed] transition-colors group">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-white p-1.5 flex items-center justify-center shrink-0 shadow-2xs">
                                    @if($hasLocalFile)
                                        <img src="{{ asset('images/categories/' . $imgFile) }}"
                                             alt="{{ $cat['label'] }}"
                                             loading="lazy"
                                             class="max-w-full max-h-full object-contain">
                                    @else
                                        <svg class="w-6 h-6 text-[#1d1d1f]/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="app-text-body font-semibold text-[#1d1d1f] truncate">
                                        {{ $cat['label'] }}
                                    </div>
                                    @if(!empty($cat['subtitle']))
                                        <div class="app-text-sub text-xs text-[#86868b] truncate">
                                            {!! mb_strimwidth(strip_tags($cat['subtitle']), 0, 45, '...') !!}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-[#86868b] group-hover:text-[#1d1d1f] shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</template>