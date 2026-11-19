import './bootstrap';
import Alpine from 'alpinejs';
import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

import 'swiper/css';
import 'swiper/css/navigation';

import PhotoSwipe from 'photoswipe';
import 'photoswipe/style.css';

window.Swiper = Swiper;
window.SwiperModules = [Navigation, Pagination];

// Вычисление ширины скроллбара для предотвращения сдвига контента (Layout Shift)
const updateScrollbarWidth = () => {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--scrollbar-width', `${scrollbarWidth}px`);
};

window.addEventListener('resize', updateScrollbarWidth);
document.addEventListener('DOMContentLoaded', updateScrollbarWidth);

// Просмотр фото на весь экран (нативный PhotoSwipe 5):
// - [data-pswp-gallery] — полноценная галерея со всеми фото товара, стрелками, счётчиком и ленивой подгрузкой
// - [data-pswp-src]     — одиночное фото (обратная совместимость для остальных страниц)
document.addEventListener('click', (e) => {
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

        const mainImg = trigger.closest('.apple-pdp-media')?.querySelector('img[data-pdp-main]');
        const matchVisible = mainImg && mainImg.naturalWidth > 0 && mainImg.currentSrc === urls[startIndex];

        const dataSource = urls.map((src, idx) => ({
            src,
            msrc: src,
            alt: urls.length > 1 ? `${alt} (${idx + 1}/${urls.length})` : alt,
            width: idx === startIndex && matchVisible ? mainImg.naturalWidth : 1200,
            height: idx === startIndex && matchVisible ? mainImg.naturalHeight : 1200,
        }));

        const pswp = new PhotoSwipe({
            dataSource,
            index: startIndex,
            bgOpacity: 0.96,
            preload: [1, 1],
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

        // Подгоняем реальные пропорции каждого слайда сразу после его загрузки (без искажений)
        pswp.on('loadComplete', ({ content }) => {
            const img = content.element;
            if (!img || !img.naturalWidth || !img.naturalHeight) return;
            if (content.data.width !== img.naturalWidth || content.data.height !== img.naturalHeight) {
                content.data.width = img.naturalWidth;
                content.data.height = img.naturalHeight;
                content.width = img.naturalWidth;
                content.height = img.naturalHeight;
                content.slide?.updateContentSize(true);
            }
        });

        // Синхронизируем выбранный кадр обратно в карточку товара
        pswp.on('change', () => {
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