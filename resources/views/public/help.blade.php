<x-layouts.public
    title="{{ __('help.meta_title') }}"
    description="{{ __('help.meta_description') }}"
>
    <div class="py-10 bg-[#f5f5f7] min-h-[calc(100vh-4rem)] text-[#1d1d1f]"
         x-data="{ openFaq: null }">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- HERO --}}
            <header class="text-center max-w-3xl mx-auto space-y-4">
                <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-semibold bg-white text-[#0071e3] border border-black/[0.06] shadow-xs">
                    {{ __('help.hero_badge') }}
                </span>
                <h1 class="apple-section-title text-center">
                    {{ __('help.hero_title') }}
                </h1>
                <p class="apple-hero-subtitle text-center mx-auto">
                    {{ __('help.hero_subtitle') }}
                </p>

                {{-- Быстрая навигация по разделам --}}
                <nav class="flex flex-wrap items-center justify-center gap-2 pt-2">
                    <a href="#about-catalog" class="px-3.5 py-1.5 rounded-full text-sm font-medium bg-white hover:bg-[#1d1d1f] hover:text-white border border-black/[0.08] transition-colors">
                        {{ __('help.nav.catalog') }}
                    </a>
                    <a href="#translate-guide" class="px-3.5 py-1.5 rounded-full text-sm font-medium bg-white hover:bg-[#1d1d1f] hover:text-white border border-black/[0.08] transition-colors">
                        {{ __('help.nav.translate') }}
                    </a>
                    <a href="#delivery-georgia" class="px-3.5 py-1.5 rounded-full text-sm font-medium bg-white hover:bg-[#1d1d1f] hover:text-white border border-black/[0.08] transition-colors">
                        {{ __('help.nav.delivery') }}
                    </a>
                    <a href="#faq" class="px-3.5 py-1.5 rounded-full text-sm font-medium bg-white hover:bg-[#1d1d1f] hover:text-white border border-black/[0.08] transition-colors">
                        {{ __('help.nav.faq') }}
                    </a>
                </nav>
            </header>

            {{-- РАЗДЕЛ 1: КАК РАБОТАЕТ КАТАЛОГ EXERETO --}}
            <section id="about-catalog" class="apple-card space-y-6 scroll-mt-24">
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold tracking-tight text-[#1d1d1f]">
                        {{ __('help.about.title') }}
                    </h2>
                    <p class="app-text-body text-[#424245] leading-relaxed">
                        {{ __('help.about.lead') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach(__('help.about.cards') as $idx => $card)
                        <div class="rounded-2xl bg-[#f5f5f7] p-5 space-y-2 border border-black/[0.04]">
                            <div class="w-8 h-8 rounded-full bg-[#1d1d1f] text-white text-sm font-bold flex items-center justify-center">
                                {{ $idx + 1 }}
                            </div>
                            <h3 class="font-semibold text-base text-[#1d1d1f]">{{ $card['title'] }}</h3>
                            <p class="text-sm text-[#424245] leading-relaxed">{{ $card['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- РАЗДЕЛ 2: КАК ПЕРЕВЕСТИ ГРЕЧЕСКИЙ САЙТ В БРАУЗЕРЕ --}}
            <section id="translate-guide" class="apple-card space-y-6 scroll-mt-24">
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold tracking-tight text-[#1d1d1f]">
                        {{ __('help.translate.title') }}
                    </h2>
                    <p class="app-text-body text-[#424245] leading-relaxed">
                        {{ __('help.translate.subtitle') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {{-- ПК-версия --}}
                    <div class="rounded-2xl bg-[#f5f5f7] p-5 space-y-4 border border-black/[0.05]">
                        <div class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-white border border-black/[0.06] flex items-center justify-center text-[#0071e3] shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <h3 class="font-semibold text-lg text-[#1d1d1f]">{{ __('help.translate.desktop_title') }}</h3>
                        </div>

                        <ol class="space-y-2.5 text-sm text-[#424245] list-decimal list-inside leading-relaxed">
                            @foreach(__('help.translate.desktop_steps') as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- Мобильная версия --}}
                    <div class="rounded-2xl bg-[#f5f5f7] p-5 space-y-4 border border-black/[0.05]">
                        <div class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-white border border-black/[0.06] flex items-center justify-center text-[#0071e3] shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <h3 class="font-semibold text-lg text-[#1d1d1f]">{{ __('help.translate.mobile_title') }}</h3>
                        </div>

                        {{-- Chrome Android / iOS --}}
                        <div class="rounded-xl bg-white p-4 border border-black/[0.06] space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-sm text-[#1d1d1f]">{{ __('help.translate.mobile_chrome_title') }}</span>
                                <span class="px-2 py-0.5 rounded-md bg-[#f5f5f7] text-xs font-mono text-[#0071e3] shrink-0">⋮ → 文A</span>
                            </div>
                            <ol class="space-y-1.5 text-sm text-[#424245] list-decimal list-inside leading-relaxed">
                                @foreach(__('help.translate.mobile_chrome_steps') as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                        </div>

                        {{-- Safari iPhone --}}
                        <div class="rounded-xl bg-white p-4 border border-black/[0.06] space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-sm text-[#1d1d1f]">{{ __('help.translate.mobile_safari_title') }}</span>
                                <span class="px-2 py-0.5 rounded-md bg-[#f5f5f7] text-xs font-mono text-[#0071e3] shrink-0">aA → 文A</span>
                            </div>
                            <ol class="space-y-1.5 text-sm text-[#424245] list-decimal list-inside leading-relaxed">
                                @foreach(__('help.translate.mobile_safari_steps') as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-blue-50/70 border border-blue-200/60 px-4 py-3 text-sm text-[#1d1d1f]">
                    {{ __('help.translate.tip') }}
                </div>
            </section>

            {{-- РАЗДЕЛ 3: ДОСТАВКА ИЗ ГРЕЦИИ (В ГРУЗИЮ И ПО ГРЕЦИИ) --}}
            <section id="delivery-georgia" class="apple-card space-y-6 scroll-mt-24">
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold tracking-tight text-[#1d1d1f]">
                        {{ __('help.delivery.title') }}
                    </h2>
                    <p class="app-text-body text-[#424245] leading-relaxed">
                        {{ __('help.delivery.subtitle') }}
                    </p>
                </div>

                {{-- Для жителей Греции --}}
                <div class="rounded-2xl bg-[#f5f5f7] p-4 sm:p-5 border border-black/[0.05] space-y-1.5">
                    <h3 class="font-semibold text-base text-[#1d1d1f]">{{ __('help.delivery.greece_title') }}</h3>
                    <p class="text-sm text-[#424245] leading-relaxed">{{ __('help.delivery.greece_text') }}</p>
                </div>

                {{-- Пошаговая схема для покупателей из Грузии --}}
                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <h3 class="font-semibold text-lg text-[#1d1d1f]">{{ __('help.delivery.georgia_title') }}</h3>
                        <p class="text-sm text-[#424245] leading-relaxed">{{ __('help.delivery.georgia_intro') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                        @foreach(__('help.delivery.steps') as $sIdx => $step)
                            <div class="rounded-2xl bg-[#f5f5f7] p-4 border border-black/[0.05] space-y-2">
                                <span class="inline-flex w-7 h-7 rounded-full bg-[#0071e3] text-white text-xs font-bold items-center justify-center">
                                    {{ $sIdx + 1 }}
                                </span>
                                <h4 class="font-semibold text-sm text-[#1d1d1f]">{{ $step['title'] }}</h4>
                                <p class="text-xs text-[#424245] leading-relaxed">{{ $step['text'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Сводная информация по перевозчикам --}}
                <div class="space-y-3">
                    <div class="space-y-1">
                        <h3 class="font-semibold text-lg text-[#1d1d1f]">{{ __('help.delivery.carriers_title') }}</h3>
                        <p class="text-xs text-[#6e6e73] leading-relaxed">{{ __('help.delivery.carriers_note') }}</p>
                    </div>

                    {{-- Мобильный вид (< md): карточки без горизонтального скролла --}}
                    <div class="space-y-3 md:hidden">
                        @foreach(__('help.delivery.carriers') as $carrier)
                            <div class="rounded-2xl bg-[#f5f5f7] p-4 border border-black/[0.06] space-y-2.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-base text-[#1d1d1f]">{{ $carrier['name'] }}</div>
                                        <a href="{{ $carrier['url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="text-xs font-medium text-[#0071e3] hover:underline">
                                            {{ $carrier['domain'] }} ↗
                                        </a>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-white text-[#1d1d1f] border border-black/[0.06]">
                                        {{ $carrier['eta'] }}
                                    </span>
                                </div>

                                <div class="text-xs text-[#6e6e73]">
                                    <span class="font-medium text-[#1d1d1f]">{{ __('help.delivery.table_headers.type') }}:</span>
                                    {{ $carrier['type'] }}
                                </div>

                                <p class="text-xs text-[#424245] leading-relaxed">
                                    {{ $carrier['notes'] }}
                                </p>
                            </div>
                        @endforeach
                    </div>

                    {{-- Десктопный вид (>= md): сводная таблица --}}
                    <div class="hidden md:block overflow-x-auto rounded-2xl border border-black/[0.08]">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-[#1d1d1f] text-white text-xs">
                                    <th class="py-3 px-4 font-semibold">{{ __('help.delivery.table_headers.carrier') }}</th>
                                    <th class="py-3 px-4 font-semibold">{{ __('help.delivery.table_headers.type') }}</th>
                                    <th class="py-3 px-4 font-semibold">{{ __('help.delivery.table_headers.eta') }}</th>
                                    <th class="py-3 px-4 font-semibold">{{ __('help.delivery.table_headers.notes') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/[0.06] bg-white">
                                @foreach(__('help.delivery.carriers') as $carrier)
                                    <tr class="hover:bg-[#f5f5f7]/60 transition-colors">
                                        <td class="py-3 px-4 align-top whitespace-nowrap">
                                            <div class="font-semibold text-[#1d1d1f]">{{ $carrier['name'] }}</div>
                                            <a href="{{ $carrier['url'] }}" target="_blank" rel="noopener noreferrer"
                                               class="text-xs text-[#0071e3] hover:underline">
                                                {{ $carrier['domain'] }}
                                            </a>
                                        </td>
                                        <td class="py-3 px-4 align-top text-xs text-[#424245]">{{ $carrier['type'] }}</td>
                                        <td class="py-3 px-4 align-top text-xs font-medium text-[#1d1d1f] whitespace-nowrap">{{ $carrier['eta'] }}</td>
                                        <td class="py-3 px-4 align-top text-xs text-[#424245] leading-relaxed">{{ $carrier['notes'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Памятка по таможне в Грузии --}}
                <div class="rounded-xl bg-amber-50/80 border border-amber-200/70 px-4 py-3.5 space-y-1">
                    <div class="text-sm font-semibold text-[#1d1d1f]">{{ __('help.delivery.customs_title') }}</div>
                    <p class="text-xs sm:text-sm text-[#424245] leading-relaxed">{{ __('help.delivery.customs_text') }}</p>
                </div>
            </section>

            {{-- РАЗДЕЛ 4: ЧАСТЫЕ ВОПРОСЫ (FAQ) --}}
            <section id="faq" class="space-y-4 scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight text-[#1d1d1f] px-1">
                    {{ __('help.faq.title') }}
                </h2>

                <div class="apple-card !space-y-0 divide-y divide-black/[0.06]">
                    @foreach(__('help.faq.items') as $index => $faq)
                        <x-public-ui.faq-item :index="$index + 1" :question="$faq['q']">
                            <p>{{ $faq['a'] }}</p>
                        </x-public-ui.faq-item>
                    @endforeach
                </div>
            </section>

            {{-- КОНТАКТЫ --}}
            <div class="apple-dark-cta">
                <h3 class="apple-dark-cta-title">{{ __('help.contact.title') }}</h3>
                <p class="apple-dark-cta-desc">{{ __('help.contact.text') }}</p>
                <div class="pt-2">
                    <a href="mailto:info@exereto.com"
                       class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-white text-[#1d1d1f] font-semibold text-sm hover:bg-[#f5f5f7] transition-colors">
                        {{ __('help.contact.button') }}: info@exereto.com
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-layouts.public>