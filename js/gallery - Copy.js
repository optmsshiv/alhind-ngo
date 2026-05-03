/**
 * gallery.js — AL Hind Trust
 * Fetches gallery images from the real API and renders them dynamically.
 */

const GALLERY_API = 'https://api.alhindtrust.com';

let allGalleryItems = [];
let lbItems = [], lbIndex = 0;
const today = new Date().toISOString().split('T')[0];

/* ── Fetch from API ────────────────────────────────────────── */
async function fetchGallery() {
  try {
    const res = await fetch(`${GALLERY_API}/gallery`);
    const data = await res.json();
    return data.data || [];
  } catch (e) {
    console.error('Gallery fetch failed:', e);
    return [];
  }
}

/* ── Render Grid ───────────────────────────────────────────── */
function renderGrid(items) {
  const grid = document.querySelector('.gallery-grid');
  if (!grid) return;

  if (!items.length) {
    grid.innerHTML = `<p class="gallery-empty">No images available yet.</p>`;
    return;
  }

  grid.innerHTML = '';
  items.forEach((item, i) => {
    const src = item.filepath || item.src || '';
    const cap = item.title || item.caption || item.alt_text || '';

    const div = document.createElement('div');
    div.className = 'gallery-item skeleton';

    const img = document.createElement('img');
    img.alt = cap;
    img.loading = 'lazy';
    img.decoding = 'async';
    img.className = 'skeleton-hidden';

    img.addEventListener('load', () => {
      img.classList.add('loaded');
      img.classList.remove('skeleton-hidden');
      div.classList.remove('skeleton');
    });
    img.addEventListener('error', () => {
      div.classList.remove('skeleton');
      img.classList.remove('skeleton-hidden');
    });

    // Stagger load
    setTimeout(() => { img.src = src; }, i * 50);

    div.appendChild(img);
    grid.appendChild(div);
    div.addEventListener('click', () => openLightbox(items, i));
  });
}

/* ── Render Carousel ───────────────────────────────────────── */
function renderCarousel(items) {
  const track = document.querySelector('.carousel-track');
  if (!track) return;

  const featured = items.slice(0, 6);
  track.innerHTML = '';

  featured.forEach(item => {
    const src = item.filepath || item.src || '';
    const cap = item.title || item.caption || '';
    const slide = document.createElement('div');
    slide.className = 'carousel-slide';
    slide.innerHTML = `<img src="${src}" alt="${esc(cap)}"><p>${esc(cap)}</p>`;
    track.appendChild(slide);
  });

  initCarousel();
}

/* ── Carousel Logic ────────────────────────────────────────── */
function initCarousel() {
  const carousel = document.querySelector('.gallery-carousel');
  const track = document.querySelector('.carousel-track');
  if (!carousel || !track) return;

  const slides = track.querySelectorAll('.carousel-slide');
  if (!slides.length) return;

  let current = 0;
  const total = slides.length;

  function goTo(i) {
    current = (i + total) % total;
    track.style.transform = `translateX(-${current * 100}%)`;
  }

  carousel.querySelector('.carousel-btn.prev')?.addEventListener('click', () => goTo(current - 1));
  carousel.querySelector('.carousel-btn.next')?.addEventListener('click', () => goTo(current + 1));

  let autoplay = setInterval(() => goTo(current + 1), 4500);
  carousel.addEventListener('mouseenter', () => clearInterval(autoplay));
  carousel.addEventListener('mouseleave', () => { autoplay = setInterval(() => goTo(current + 1), 4500); });

  let startX = 0;
  track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) goTo(current + (diff > 0 ? 1 : -1));
  });
}

/* ── Lightbox ──────────────────────────────────────────────── */
function openLightbox(items, index) {
  lbItems = items; lbIndex = index;
  const box = document.getElementById('lightbox');
  if (!box) return;
  updateLightbox();
  box.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function updateLightbox() {
  const box = document.getElementById('lightbox');
  const item = lbItems[lbIndex];
  if (!item) return;
  const src = item.filepath || item.src || '';
  const cap = item.title || item.caption || '';
  box.querySelector('.lightbox-img').src = src;
  box.querySelector('.lightbox-img').alt = cap;
  const capEl = box.querySelector('.lightbox-caption');
  if (capEl) capEl.textContent = cap;
  const counter = box.querySelector('.lightbox-counter');
  if (counter) counter.textContent = `${lbIndex + 1} / ${lbItems.length}`;
}

function lightboxNav(dir) {
  lbIndex = (lbIndex + dir + lbItems.length) % lbItems.length;
  updateLightbox();
}

function closeLightbox() {
  const box = document.getElementById('lightbox');
  if (!box) return;
  box.style.display = 'none';
  document.body.style.overflow = '';
}

function initLightbox() {
  const box = document.getElementById('lightbox');
  if (!box) return;
  box.querySelector('.lightbox-close')?.addEventListener('click', closeLightbox);
  box.querySelector('.lightbox-prev')?.addEventListener('click', () => lightboxNav(-1));
  box.querySelector('.lightbox-next')?.addEventListener('click', () => lightboxNav(1));
  box.addEventListener('click', e => { if (e.target === box) closeLightbox(); });
  document.addEventListener('keydown', e => {
    if (box.style.display !== 'flex') return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxNav(-1);
    if (e.key === 'ArrowRight') lightboxNav(1);
  });
  let lbTouchX = 0;
  box.addEventListener('touchstart', e => { lbTouchX = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', e => {
    const diff = lbTouchX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) lightboxNav(diff > 0 ? 1 : -1);
  });
}

function esc(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ── Init ──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  initLightbox();
  const items = await fetchGallery();
  allGalleryItems = items;
  renderGrid(items);
  renderCarousel(items);
});