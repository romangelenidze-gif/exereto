@php
    $visionEnabled = (bool) config('services.gemini.vision_enabled', true)
        && filled(config('services.gemini.vision_api_key'));
    $locale = app()->getLocale();
    $photoEndpoint = in_array($locale, ['ru', 'ka'], true) && \Illuminate\Support\Facades\Route::has("{$locale}.api.photo-search")
        ? route("{$locale}.api.photo-search")
        : route('api.photo-search');
@endphp

@if($visionEnabled)
<template x-teleport="body">
    <div x-data="{
            open: false,
            dragging: false,
            analyzing: false,
            previewUrl: '',
            errorMsg: '',
            endpoint: @js($photoEndpoint),
            openModal() {
                this.open = true;
                this.errorMsg = '';
                this.analyzing = false;
                this.previewUrl = '';
            },
            closeModal() {
                if (this.analyzing) return;
                this.open = false;
                this.dragging = false;
                this.errorMsg = '';
                if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                    URL.revokeObjectURL(this.previewUrl);
                }
                this.previewUrl = '';
            },
            onFileInput(event) {
                const file = event.target?.files?.[0];
                if (event.target) event.target.value = '';
                if (file) this.handleFile(file);
            },
            onDrop(event) {
                this.dragging = false;
                const file = event.dataTransfer?.files?.[0];
                if (file) this.handleFile(file);
            },
            onPaste(event) {
                if (!this.open || this.analyzing) return;
                const items = event.clipboardData?.items || [];
                for (const item of items) {
                    if (item.type && item.type.startsWith('image/')) {
                        const file = item.getAsFile();
                        if (file) {
                            event.preventDefault();
                            this.handleFile(file);
                            break;
                        }
                    }
                }
            },
            resizeImage(file, maxSide, quality) {
                return new Promise((resolve, reject) => {
                    const url = URL.createObjectURL(file);
                    const img = new Image();
                    img.onload = () => {
                        try {
                            let w = img.naturalWidth || img.width;
                            let h = img.naturalHeight || img.height;
                            if (w > maxSide || h > maxSide) {
                                const scale = maxSide / Math.max(w, h);
                                w = Math.max(1, Math.round(w * scale));
                                h = Math.max(1, Math.round(h * scale));
                            }
                            const canvas = document.createElement('canvas');
                            canvas.width = w;
                            canvas.height = h;
                            const ctx = canvas.getContext('2d');
                            ctx.fillStyle = '#ffffff';
                            ctx.fillRect(0, 0, w, h);
                            ctx.drawImage(img, 0, 0, w, h);

                            // Маленькая превьюшка для плашки над результатами (sessionStorage)
                            const tSide = 160;
                            const tScale = Math.min(1, tSide / Math.max(w, h));
                            const tCanvas = document.createElement('canvas');
                            tCanvas.width = Math.max(1, Math.round(w * tScale));
                            tCanvas.height = Math.max(1, Math.round(h * tScale));
                            const tCtx = tCanvas.getContext('2d');
                            tCtx.fillStyle = '#ffffff';
                            tCtx.fillRect(0, 0, tCanvas.width, tCanvas.height);
                            tCtx.drawImage(canvas, 0, 0, tCanvas.width, tCanvas.height);
                            const thumbDataUrl = tCanvas.toDataURL('image/jpeg', 0.72);

                            canvas.toBlob((blob) => {
                                URL.revokeObjectURL(url);
                                if (blob) resolve({ blob, thumbDataUrl });
                                else reject(new Error('toBlob failed'));
                            }, 'image/jpeg', quality);
                        } catch (err) {
                            URL.revokeObjectURL(url);
                            reject(err);
                        }
                    };
                    img.onerror = () => {
                        URL.revokeObjectURL(url);
                        reject(new Error('decode failed'));
                    };
                    img.src = url;
                });
            },
            async handleFile(file) {
                if (!file.type || !file.type.startsWith('image/')) {
                    this.errorMsg = @js(__('Пожалуйста, выберите изображение (JPG, PNG или WebP).'));
                    return;
                }

                this.errorMsg = '';
                this.analyzing = true;
                this.previewUrl = URL.createObjectURL(file);

                let uploadBlob = file;
                let thumbDataUrl = '';

                try {
                    const resized = await this.resizeImage(file, 768, 0.82);
                    uploadBlob = resized.blob;
                    thumbDataUrl = resized.thumbDataUrl;
                } catch {
                    if (file.size > 4.5 * 1024 * 1024) {
                        this.analyzing = false;
                        this.errorMsg = @js(__('Файл слишком большой. Выберите фото размером до 5 МБ.'));
                        return;
                    }
                }

                const formData = new FormData();
                formData.append('image', uploadBlob, 'photo.jpg');

                const csrf = document.querySelector('meta[name=&quot;csrf-token&quot;]')?.getAttribute('content') || '';

                try {
                    const res = await fetch(this.endpoint, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept-Language': '{{ app()->getLocale() }}'
                        },
                        credentials: 'same-origin'
                    });

                    const data = await res.json().catch(() => ({}));

                    if (res.ok && data.ok && data.redirect_url) {
                        try {
                            window.sessionStorage.setItem('exereto_photo_search_v1', JSON.stringify({
                                thumb: thumbDataUrl,
                                detected: data.detected || {},
                                ts: Date.now()
                            }));
                        } catch {}
                        window.location.href = data.redirect_url;
                        return;
                    }

                    this.analyzing = false;
                    if (res.status === 429) {
                        this.errorMsg = data.message || @js(__('Слишком много запросов. Подождите минуту и попробуйте снова.'));
                    } else {
                        this.errorMsg = data.message || @js(__('Не удалось найти похожие товары. Попробуйте другое фото.'));
                    }
                } catch {
                    this.analyzing = false;
                    this.errorMsg = @js(__('Ошибка соединения. Проверьте интернет и попробуйте снова.'));
                }
            }
         }"
         x-show="open"
         x-cloak
         @open-photo-search.window="openModal()"
         @keydown.escape.window="if (open) closeModal()"
         @paste.window="onPaste($event)"
         x-effect="
            if (open) {
                const sbw = window.innerWidth - document.documentElement.clientWidth;
                if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
                document.body.classList.add('overflow-hidden');
            } else if (!searchOpen && !categoriesDrawerOpen) {
                document.body.classList.remove('overflow-hidden');
                document.body.style.paddingRight = '';
            }
         "
         class="fixed inset-0 z-[9998] flex items-end sm:items-center justify-center p-0 sm:p-4"
    >
        <!-- Светлый матовый фон -->
        <div x-show="open"
             x-transition:enter="transition-opacity ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeModal()"
             class="fixed inset-0 bg-black/35 backdrop-blur-md"></div>

        <!-- Карточка модалки -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
             class="relative z-10 w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl border border-black/[0.08] p-6 pb-8 sm:p-7 space-y-5">

            <!-- Шапка -->
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-[#1d1d1f]">
                        {{ __('Поиск по фото') }}
                    </h2>
                    <p class="text-xs sm:text-sm text-[#6e6e73] mt-0.5">
                        {{ __('Загрузите фото вещи — найдём похожие предложения в магазинах Греции') }}
                    </p>
                </div>
                <button type="button"
                        @click="closeModal()"
                        :disabled="analyzing"
                        aria-label="{{ __('Закрыть') }}"
                        class="apple-icon-btn shrink-0 disabled:opacity-40">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Скрытые инпуты: галерея / камера -->
            <input type="file" x-ref="galleryInput" accept="image/*" @change="onFileInput($event)" class="hidden">
            <input type="file" x-ref="cameraInput" accept="image/*" capture="environment" @change="onFileInput($event)" class="hidden">

            <!-- Состояние 1: загрузка / выбор фото -->
            <div x-show="!analyzing" class="space-y-4">
                <div @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="onDrop($event)"
                     @click="$refs.galleryInput.click()"
                     :class="dragging ? 'border-[#0071e3] bg-[#0071e3]/[0.05]' : 'border-black/15 bg-[#f5f5f7] hover:border-[#0071e3]/50'"
                     class="rounded-2xl border-2 border-dashed p-6 sm:p-8 text-center cursor-pointer transition-all space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-white shadow-xs border border-black/[0.06] flex items-center justify-center mx-auto text-[#0071e3]">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <div class="text-sm font-semibold text-[#1d1d1f]">
                            {{ __('Перетащите фото сюда или выберите файл') }}
                        </div>
                        <div class="text-xs text-[#6e6e73]">
                            {{ __('JPG, PNG, WebP · можно вставить скриншот (Ctrl+V)') }}
                        </div>
                    </div>
                </div>

                <!-- Кнопки действий (на мобильном сразу доступны и камера, и галерея) -->
                <div class="grid grid-cols-2 gap-2.5 sm:hidden">
                    <button type="button"
                            @click="$refs.cameraInput.click()"
                            class="flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-[#1d1d1f] text-white text-sm font-semibold cursor-pointer active:scale-[0.99] transition-transform">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ __('Камера') }}</span>
                    </button>
                    <button type="button"
                            @click="$refs.galleryInput.click()"
                            class="flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-[#f5f5f7] text-[#1d1d1f] text-sm font-semibold cursor-pointer active:scale-[0.99] transition-transform">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>{{ __('Галерея') }}</span>
                    </button>
                </div>

                <!-- Ошибка -->
                <div x-show="errorMsg" x-cloak
                     class="rounded-2xl bg-red-50 border border-red-200/70 px-4 py-3 text-xs sm:text-sm text-red-700 font-medium">
                    <span x-text="errorMsg"></span>
                </div>
            </div>

            <!-- Состояние 2: анализ фото -->
            <div x-show="analyzing" x-cloak class="space-y-4 py-2 text-center">
                <div class="relative w-44 h-44 mx-auto rounded-2xl overflow-hidden bg-[#f5f5f7] border border-black/[0.08] shadow-inner">
                    <img x-show="previewUrl" :src="previewUrl" alt="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-[#0071e3]/15 animate-pulse"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-11 h-11 rounded-full bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center">
                            <svg class="animate-spin h-6 w-6 text-[#0071e3]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="text-sm font-semibold text-[#1d1d1f]">
                        {{ __('Анализируем фото...') }}
                    </div>
                    <div class="text-xs text-[#6e6e73]">
                        {{ __('Определяем бренд, модель и ищем в каталоге') }}
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
@endif