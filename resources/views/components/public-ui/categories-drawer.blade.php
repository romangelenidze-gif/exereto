@props(['categories' => []])

@php
    $treeByGender = app(\App\Services\FeedCategoryTiles::class)->getTreeByGender();
    $tabs = [
        ['key' => 'all', 'label' => __('Все')],
        ['key' => 'women', 'label' => __('Женщинам')],
        ['key' => 'men', 'label' => __('Мужчинам')],
        ['key' => 'kids', 'label' => __('Детям')],
    ];
    $salesUrl = route('feed-category.show', ['slug' => 'sales-offers']);
@endphp

<template x-teleport="body">
    <div x-show="categoriesDrawerOpen"
         x-cloak
         @open-categories.window="categoriesDrawerOpen = true"
         x-effect="document.body.classList.toggle('overflow-hidden', categoriesDrawerOpen)"
         @keydown.escape.window="categoriesDrawerOpen = false"
         class="fixed inset-0 z-[9998] flex justify-end bg-black/35 backdrop-blur-[2px]"
         @click.self="categoriesDrawerOpen = false"
    >
        <div x-show="categoriesDrawerOpen"
             x-data="{
                activeTab: 'all',
                openDept: null,
                tree: @js($treeByGender),
                get departments() {
                    return this.tree[this.activeTab] || [];
                },
                switchTab(key) {
                    this.activeTab = key;
                    this.openDept = null;
                },
                toggleDept(slug) {
                    this.openDept = (this.openDept === slug) ? null : slug;
                }
             }"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full opacity-0"
             x-transition:enter-end="translate-y-0 sm:translate-x-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:translate-x-0 opacity-100"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full opacity-0"
             class="w-full sm:max-w-md bg-white h-full flex flex-col shadow-2xl overflow-hidden"
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

            <!-- Вкладки пола (Все / Женщинам / Мужчинам / Детям) -->
            <div class="px-4 py-3 border-b border-black/[0.06] shrink-0 bg-[#f5f5f7]/60">
                <div class="grid grid-cols-4 gap-1 p-1 rounded-2xl bg-black/[0.05]">
                    @foreach($tabs as $t)
                        <button type="button"
                                @click="switchTab('{{ $t['key'] }}')"
                                :class="activeTab === '{{ $t['key'] }}' ? 'bg-white text-[#1d1d1f] shadow-xs font-semibold' : 'text-[#6e6e73] hover:text-[#1d1d1f]'"
                                class="py-2 px-1 rounded-xl text-xs transition-all cursor-pointer truncate">
                            {{ $t['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Прокручиваемое дерево категорий -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-2.5">

                <!-- Быстрый вход в скидки -->
                <a href="{{ $salesUrl }}"
                   class="flex items-center justify-between gap-3 px-4 py-3.5 rounded-2xl bg-red-50 hover:bg-red-100/80 border border-red-200/60 transition-colors group">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-9 h-9 rounded-xl bg-red-600 text-white flex items-center justify-center text-sm font-bold shrink-0">%</span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-red-700 truncate">{{ __('Скидки и акции') }}</div>
                            <div class="text-xs text-red-600/80 truncate">{{ __('Товары со сниженной ценой') }}</div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <!-- 7 департаментов L1 с раскрытием L2 -->
                <template x-for="dept in departments" :key="activeTab + ':' + dept.slug">
                    <div class="rounded-2xl bg-[#f5f5f7] overflow-hidden border border-black/[0.04]">
                        <button type="button"
                                @click="dept.children.length ? toggleDept(dept.slug) : (window.location.href = dept.url)"
                                class="w-full flex items-center justify-between gap-3 p-3.5 text-left hover:bg-[#ececf0] transition-colors cursor-pointer">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-white p-1.5 flex items-center justify-center shrink-0 shadow-2xs overflow-hidden">
                                    <img :src="'/images/categories/' + dept.img"
                                         :alt="dept.label"
                                         onerror="this.style.display='none'"
                                         class="max-w-full max-h-full object-contain">
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[15px] font-semibold text-[#1d1d1f] truncate" x-text="dept.label"></div>
                                    <div class="text-xs text-[#86868b]" x-text="dept.count.toLocaleString()"></div>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-[#86868b] shrink-0 transition-transform duration-200"
                                 :class="openDept === dept.slug ? 'rotate-90 text-[#1d1d1f]' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Подкатегории L2 -->
                        <div x-show="openDept === dept.slug"
                             x-collapse
                             x-cloak
                             class="bg-white/80 border-t border-black/[0.05] divide-y divide-black/[0.04]">
                            <a :href="dept.url"
                               class="flex items-center justify-between px-4 py-2.5 text-sm font-semibold text-[#0071e3] hover:bg-[#0071e3]/[0.05] transition-colors">
                                <span>{{ __('Смотреть все') }} &rarr;</span>
                                <span class="text-xs text-[#86868b] font-normal" x-text="dept.count.toLocaleString()"></span>
                            </a>
                            <template x-for="child in dept.children" :key="child.slug">
                                <a :href="child.url"
                                   class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm text-[#1d1d1f] hover:bg-[#f5f5f7] transition-colors">
                                    <span class="truncate" x-text="child.label"></span>
                                    <span class="text-xs text-[#86868b] shrink-0" x-text="child.count.toLocaleString()"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </div>
</template>