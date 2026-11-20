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
                <h2 class="text-[19px] font-semibold tracking-tight text-[#1d1d1f]">
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
                                :class="activeTab === '{{ $t['key'] }}' ? 'bg-white text-[#1d1d1f] shadow-xs font-semibold' : 'text-[#6e6e73] hover:text-[#1d1d1f] font-medium'"
                                class="py-2.5 px-1.5 rounded-xl text-[13px] transition-all cursor-pointer truncate">
                            {{ $t['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Прокручиваемое дерево категорий -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3">

                <!-- Премиальный блок скидок (без сходства с уведомлением об ошибке) -->
                <a href="{{ $salesUrl }}"
                   class="flex items-center justify-between gap-3.5 px-4 py-4 rounded-2xl bg-[#1d1d1f] hover:bg-[#2c2c2e] text-white transition-colors group">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-white/12 text-white flex items-center justify-center text-lg font-bold shrink-0">
                            %
                        </span>
                        <div class="min-w-0">
                            <div class="text-[17px] font-semibold leading-tight tracking-tight truncate">
                                {{ __('Скидки и акции') }}
                            </div>
                            <div class="text-[13px] text-white/70 mt-0.5 truncate">
                                {{ __('Товары со сниженной ценой') }}
                            </div>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-white/60 group-hover:text-white group-hover:translate-x-0.5 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <!-- 7 департаментов L1 с крупной графикой и типографикой -->
                <template x-for="dept in departments" :key="activeTab + ':' + dept.slug">
                    <div class="rounded-2xl bg-[#f5f5f7] overflow-hidden border border-black/[0.04]"
                         x-data="{ imgError: false }">
                        <button type="button"
                                @click="dept.children.length ? toggleDept(dept.slug) : (window.location.href = dept.url)"
                                class="w-full flex items-center justify-between gap-3.5 pl-2.5 pr-4 py-2 text-left hover:bg-[#ececf0] transition-colors cursor-pointer">
                            <div class="flex items-center gap-4 min-w-0">
                                <!-- Крупный слот под фото на всю высоту карточки без тесной белой рамки -->
                                <div class="w-[72px] h-[72px] rounded-xl flex items-center justify-center shrink-0 overflow-hidden">
                                    <img x-show="!imgError"
                                         :src="'/images/categories/' + dept.img"
                                         :alt="dept.label"
                                         @error="imgError = true"
                                         class="w-full h-full object-contain mix-blend-multiply scale-105">
                                    <div x-show="imgError" x-cloak class="w-12 h-12 rounded-xl bg-white/80 flex items-center justify-center text-[#86868b]">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 py-1">
                                    <div class="text-[18px] sm:text-[19px] font-semibold leading-snug tracking-tight text-[#1d1d1f] truncate"
                                         x-text="dept.label"></div>
                                    <div class="text-[14px] text-[#6e6e73] font-medium mt-0.5"
                                         x-text="dept.count.toLocaleString()"></div>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-[#86868b] shrink-0 transition-transform duration-200"
                                 :class="openDept === dept.slug ? 'rotate-90 text-[#1d1d1f]' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Подкатегории L2 -->
                        <div x-show="openDept === dept.slug"
                             x-collapse
                             x-cloak
                             class="bg-white/90 border-t border-black/[0.05] divide-y divide-black/[0.04]">
                            <a :href="dept.url"
                               class="flex items-center justify-between px-4 py-3 text-[15px] font-semibold text-[#0071e3] hover:bg-[#0071e3]/[0.05] transition-colors">
                                <span>{{ __('Смотреть все') }} &rarr;</span>
                                <span class="text-[13px] text-[#6e6e73] font-normal" x-text="dept.count.toLocaleString()"></span>
                            </a>
                            <template x-for="child in dept.children" :key="child.slug">
                                <a :href="child.url"
                                   class="flex items-center justify-between gap-2 px-4 py-3 text-[15px] text-[#1d1d1f] hover:bg-[#f5f5f7] transition-colors">
                                    <span class="truncate" x-text="child.label"></span>
                                    <span class="text-[13px] text-[#6e6e73] shrink-0" x-text="child.count.toLocaleString()"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </div>
</template>