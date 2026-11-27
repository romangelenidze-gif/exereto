import Alpine from 'alpinejs';

// Вычисление ширины скроллбара для десктопа без Forced Reflow на первом рендере
let scrollbarRaf = null;
const updateScrollbarWidth = () => {
    if (window.matchMedia && !window.matchMedia('(pointer: fine)').matches) {
        return;
    }
    if (scrollbarRaf) cancelAnimationFrame(scrollbarRaf);
    scrollbarRaf = requestAnimationFrame(() => {
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.documentElement.style.setProperty('--scrollbar-width', `${scrollbarWidth}px`);
    });
};

window.addEventListener('resize', updateScrollbarWidth, { passive: true });
window.addEventListener('load', updateScrollbarWidth, { once: true, passive: true });

// Ленивая загрузка PhotoSwipe (JS + CSS подгружаются только при первом клике на фото)
let photoSwipePromise = null;
const loadPhotoSwipe = () => {
    if (!photoSwipePromise) {
        photoSwipePromise = Promise.all([
            import('photoswipe'),
            import('photoswipe/style.css'),
        ]).then(([mod]) => mod.default);
    }
    return photoSwipePromise;
};

// Просмотр фото на весь экран (нативный PhotoSwipe 5):
// - [data-pswp-gallery] — полноценная галерея со всеми фото товара, стрелками, счётчиком и ленивой подгрузкой
// - [data-pswp-src]     — одиночное фото (обратная совместимость для остальных страниц)
document.addEventListener('click', async (e) => {
    const trigger = e.target.closest('[data-pswp-gallery], [data-pswp-src]');
    if (!trigger) return;

    const alt = trigger.dataset.pswpAlt || '';
    const galleryRaw = trigger.dataset.pswpGallery;

    if (galleryRaw) {
        let urls = [];
        try {
            urls = JSON.parse(galleryRaw);
        } catch {
            urls = [];
        }

        if (!Array.isArray(urls) || urls.length === 0) return;

        const startIndex = Math.max(
            0,
            Math.min(parseInt(trigger.dataset.pswpIndex || '0', 10) || 0, urls.length - 1)
        );

        const knownDims = new Map();
        const mediaWrap = trigger.closest('.apple-pdp-media');
        if (mediaWrap) {
            mediaWrap.querySelectorAll('img').forEach((imgEl) => {
                if (imgEl.naturalWidth > 0 && imgEl.naturalHeight > 0) {
                    const attrSrc = imgEl.getAttribute('src');
                    if (attrSrc) knownDims.set(attrSrc, { w: imgEl.naturalWidth, h: imgEl.naturalHeight });
                    if (imgEl.currentSrc) knownDims.set(imgEl.currentSrc, { w: imgEl.naturalWidth, h: imgEl.naturalHeight });
                }
            });
        }

        const dataSource = urls.map((src, idx) => {
            const dim = knownDims.get(src);
            return {
                src,
                alt: urls.length > 1 ? `${alt} (${idx + 1}/${urls.length})` : alt,
                width: dim ? dim.w : 1200,
                height: dim ? dim.h : 1200,
                _resolved: Boolean(dim),
            };
        });

        const PhotoSwipe = await loadPhotoSwipe();

        const pswp = new PhotoSwipe({
            dataSource,
            index: startIndex,
            bgOpacity: 0.96,
            preload: [1, 2],
            secondaryZoomLevel: 2,
            maxZoomLevel: 4,
            wheelToZoom: true,
            pinchToZoom: true,
            closeOnVerticalDrag: true,
            counter: urls.length > 1,
            arrowPrev: urls.length > 1,
            arrowNext: urls.length > 1,
            padding: { top: 24, bottom: 24, left: 16, right: 16 },
        });

        const syncRealDimensions = (content) => {
            const img = content?.element;
            if (!img || img.tagName !== 'IMG') return;

            img.style.objectFit = 'contain';

            const apply = () => {
                if (!img.naturalWidth || !img.naturalHeight) return;
                const item = dataSource[content.index];
                if (!item) return;

                if (!item._resolved || item.width !== img.naturalWidth || item.height !== img.naturalHeight) {
                    item.width = img.naturalWidth;
                    item.height = img.naturalHeight;
                    item._resolved = true;
                    pswp.refreshSlideContent(content.index);
                }
            };

            if (img.complete && img.naturalWidth > 0) {
                apply();
            } else {
                img.addEventListener('load', apply, { once: true });
            }
        };

        pswp.on('contentLoadImage', ({ content }) => syncRealDimensions(content));
        pswp.on('loadComplete', ({ content }) => syncRealDimensions(content));

        pswp.on('change', () => {
            if (pswp.currSlide?.content) {
                syncRealDimensions(pswp.currSlide.content);
            }
            trigger.dispatchEvent(new CustomEvent('pswp-change', {
                detail: pswp.currIndex,
                bubbles: true,
            }));
        });

        pswp.init();
        return;
    }

    const src = trigger.dataset.pswpSrc;
    if (!src) return;

    const PhotoSwipe = await loadPhotoSwipe();

    const probe = new Image();
    probe.onload = () => {
        const pswp = new PhotoSwipe({
            dataSource: [{
                src,
                alt,
                width: probe.naturalWidth,
                height: probe.naturalHeight,
            }],
            bgOpacity: 0.97,
            secondaryZoomLevel: 2,
            maxZoomLevel: 4,
            wheelToZoom: true,
            counter: false,
            arrowPrev: false,
            arrowNext: false,
            padding: { top: 20, bottom: 20, left: 0, right: 0 },
        });
        pswp.init();
    };
    probe.src = src;
});

window.Alpine = Alpine;
Alpine.start();