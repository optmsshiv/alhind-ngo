/**
 * gallery.js — AL Hind Trust Premium Gallery
 * Masonry layout · Category filters · Lightbox · API-powered
 */

const GALLERY_API = 'https://api.alhindtrust.com';
const PAGE_SIZE = 12; // images per page
let allItems = [];
let filteredItems = [];
let visibleCount = PAGE_SIZE;
let activeCategory = 'all';
let lbItems = [];
let lbIndex = 0;
let carouselCurrent = 0;
let carouselTimer = null;

/* ── Category config ─────────────────────────────────────── */
const CAT_CONFIG = {
  'education-programs': { icon: '📚', label: 'Education', cls: 'cat-education' },
  'health-camps': { icon: '🏥', label: 'Health', cls: 'cat-health' },
  'community-outreach': { icon: '🤝', label: 'Community', cls: 'cat-community' },
  'volunteer-activities': { icon: '🙌', label: 'Volunteer', cls: 'cat-volunteer' },
  'skill-development': { icon: '⚙️', label: 'Skills', cls: 'cat-skill' },
  'awareness-campaigns': { icon: '📢', label: 'Awareness', cls: 'cat-awareness' },
  'welfare': { icon: '💚', label: 'Welfare', cls: 'cat-welfare' },
};

function getCatInfo(catSlug, catName) {
  if (!catSlug && !catName) return { icon: '📷', label: 'General', cls: 'cat-default' };
  const key = catSlug || slugify(catName || '');
  return CAT_CONFIG[key] || { icon: '📷', label: catName || 'General', cls: 'cat-default' };
}

function slugify(s) {
  return s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

/* ── Fetch ───────────────────────────────────────────────── */
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

/* ── Build filter bar ────────────────────────────────────── */
function buildFilterBar(items) {
  const bar = document.getElementById('gallery-filter-bar');
  if (!bar) return;

  // Count per category
  const catCounts = {};
  items.forEach(item => {
    const key = item.category_slug || slugify(item.category_name || '');
    if (key) catCounts[key] = (catCounts[key] || 0) + 1;
  });

  // Update all count
  const allCount = document.getElementById('fcount-all');
  if (allCount) allCount.textContent = items.length;

  // Update stats
  const statPhotos = document.getElementById('stat-photos');
  const statCats = document.getElementById('stat-cats');
  if (statPhotos) statPhotos.textContent = items.length;
  if (statCats) statCats.textContent = Object.keys(catCounts).length;

  // Add category buttons
  Object.entries(catCounts).forEach(([slug, count]) => {
    const info = getCatInfo(slug, slug.replace(/-/g, ' '));
    const btn = document.createElement('button');
    btn.className = 'gfb-btn';
    btn.dataset.cat = slug;
    btn.innerHTML = `<span class="gfb-icon">${info.icon}</span> ${info.label} <span class="gfb-count">${count}</span>`;
    btn.addEventListener('click', () => filterGallery(slug));
    bar.appendChild(btn);
  });

  // All button click
  bar.querySelector('[data-cat="all"]')?.addEventListener('click', () => filterGallery('all'));
}

/* ── Filter ──────────────────────────────────────────────── */
function filterGallery(cat) {
  activeCategory = cat;
  visibleCount = PAGE_SIZE;

  // Update button states
  document.querySelectorAll('.gfb-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.cat === cat);
  });

  filteredItems = cat === 'all'
    ? [...allItems]
    : allItems.filter(item => {
      const slug = item.category_slug || slugify(item.category_name || '');
      return slug === cat;
    });

  renderMasonry();
}

/* ── Masonry render ──────────────────────────────────────── */
function renderMasonry() {
  const grid = document.getElementById('masonry-grid');
  if (!grid) return;

  const toShow = filteredItems.slice(0, visibleCount);

  if (!toShow.length) {
    grid.innerHTML = `<p class="gallery-empty">No images in this category yet.</p>`;
    document.getElementById('load-more-wrap').style.display = 'none';
    return;
  }

  grid.innerHTML = '';
  toShow.forEach((item, i) => {
    const src = item.filepath || item.src || '';
    const cap = item.title || item.alt_text || '';
    const catSlug = item.category_slug || slugify(item.category_name || '');
    const catInfo = getCatInfo(catSlug, item.category_name);

    const div = document.createElement('div');
    div.className = 'masonry-item';
    div.innerHTML = `
      <img src="" data-src="${esc(src)}" alt="${esc(cap)}" class="skeleton-hidden" loading="lazy">
      <div class="masonry-zoom">🔍</div>
      <div class="masonry-overlay">
        ${catInfo.label !== 'General'
        ? `<span class="masonry-cat-badge ${catInfo.cls}">${catInfo.icon} ${catInfo.label}</span>`
        : ''}
        ${cap ? `<div class="masonry-overlay-title">${esc(cap)}</div>` : ''}
      </div>`;

    const img = div.querySelector('img');

    // Lazy load with IntersectionObserver
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          img.src = img.dataset.src;
          img.addEventListener('load', () => {
            img.classList.add('loaded');
            img.classList.remove('skeleton-hidden');
          });
          observer.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });

    observer.observe(img);

    div.addEventListener('click', () => {
      const lightboxItems = filteredItems.slice(0, visibleCount);
      openLightbox(lightboxItems, i);
    });

    grid.appendChild(div);
  });

  // Load more button
  const wrap = document.getElementById('load-more-wrap');
  if (wrap) wrap.style.display = visibleCount < filteredItems.length ? 'block' : 'none';
}

/* ── Load more ───────────────────────────────────────────── */
function loadMore() {
  visibleCount += PAGE_SIZE;
  renderMasonry();
  // Smooth scroll to new items
  const grid = document.getElementById('masonry-grid');
  const items = grid.querySelectorAll('.masonry-item');
  items[visibleCount - PAGE_SIZE]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ── Carousel ────────────────────────────────────────────── */
function buildCarousel(items) {
  const track = document.querySelector('.carousel-track');
  const dots = document.getElementById('carousel-dots');
  if (!track) return;

  const featured = items.filter(item => item.is_featured == 1 || item.is_featured === true).slice(0, 8);
  const slides = featured.length ? featured : items.slice(0, 6);

  if (!slides.length) {
    document.querySelector('.gallery-carousel-wrap')?.style.setProperty('display', 'none');
    return;
  }

  track.innerHTML = '';
  if (dots) dots.innerHTML = '';

  slides.forEach((item, i) => {
    const src = item.filepath || item.src || '';
    const cap = item.title || item.alt_text || '';
    const catSlug = item.category_slug || slugify(item.category_name || '');
    const catInfo = getCatInfo(catSlug, item.category_name);

    const slide = document.createElement('div');
    slide.className = 'carousel-slide';
    slide.innerHTML = `
      <img src="${esc(src)}" alt="${esc(cap)}" loading="${i === 0 ? 'eager' : 'lazy'}">
      <div class="carousel-slide-overlay">
        ${catInfo.label !== 'General'
        ? `<div class="carousel-slide-badge">${catInfo.icon} ${catInfo.label}</div>`
        : ''}
        ${cap ? `<div class="carousel-slide-caption">${esc(cap)}</div>` : ''}
      </div>`;
    track.appendChild(slide);

    // Dot
    if (dots) {
      const dot = document.createElement('button');
      dot.className = `carousel-dot${i === 0 ? ' active' : ''}`;
      dot.addEventListener('click', () => goToSlide(i));
      dots.appendChild(dot);
    }
  });

  initCarousel(slides.length);
}

function initCarousel(total) {
  const carousel = document.querySelector('.gallery-carousel');
  const track = document.querySelector('.carousel-track');
  if (!carousel || !track || total === 0) return;

  function goToSlide(i) {
    carouselCurrent = (i + total) % total;
    track.style.transform = `translateX(-${carouselCurrent * 100}%)`;
    document.querySelectorAll('.carousel-dot').forEach((d, idx) => {
      d.classList.toggle('active', idx === carouselCurrent);
    });
  }

  carousel.querySelector('.carousel-btn.prev')?.addEventListener('click', () => goToSlide(carouselCurrent - 1));
  carousel.querySelector('.carousel-btn.next')?.addEventListener('click', () => goToSlide(carouselCurrent + 1));

  // Auto-play
  function startAuto() {
    carouselTimer = setInterval(() => goToSlide(carouselCurrent + 1), 5000);
  }
  startAuto();
  carousel.addEventListener('mouseenter', () => clearInterval(carouselTimer));
  carousel.addEventListener('mouseleave', startAuto);

  // Touch swipe
  let startX = 0;
  track.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) goToSlide(carouselCurrent + (diff > 0 ? 1 : -1));
  });
}

/* ── Lightbox ────────────────────────────────────────────── */
function openLightbox(items, index) {
  lbItems = items; lbIndex = index;
  const box = document.getElementById('lightbox');
  if (!box) return;
  updateLightbox();
  box.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function updateLightbox() {
  const item = lbItems[lbIndex];
  if (!item) return;
  const src = item.filepath || item.src || '';
  const cap = item.title || item.alt_text || '';
  const catSlug = item.category_slug || slugify(item.category_name || '');
  const catInfo = getCatInfo(catSlug, item.category_name);

  document.querySelector('.lightbox-img').src = src;
  document.querySelector('.lightbox-img').alt = cap;
  document.querySelector('.lightbox-caption').textContent = cap;
  document.querySelector('.lightbox-counter').textContent = `${lbIndex + 1} / ${lbItems.length}`;

  const catBadge = document.querySelector('.lightbox-cat-badge');
  if (catBadge) {
    catBadge.textContent = `${catInfo.icon} ${catInfo.label}`;
    catBadge.className = `lightbox-cat-badge ${catInfo.cls}`;
  }
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

  let lbTx = 0;
  box.addEventListener('touchstart', e => { lbTx = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', e => {
    const d = lbTx - e.changedTouches[0].clientX;
    if (Math.abs(d) > 40) lightboxNav(d > 0 ? 1 : -1);
  });
}

/* ── Escape helper ───────────────────────────────────────── */
function esc(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ── Init ────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  initLightbox();

  const items = await fetchGallery();
  allItems = items;
  filteredItems = [...items];

  // Remove skeleton
  document.getElementById('masonry-grid').innerHTML = '';

  buildFilterBar(items);
  buildCarousel(items);
  renderMasonry();
});