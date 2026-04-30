/**
 * gallery.js — Dynamic Gallery for AL Hind Educational and Charitable Trust
 * Reads items from localStorage (set by admin panel). Falls back to defaults.
 */

const GALLERY_STORAGE_KEY = "alhind_gallery_items";

const DEFAULT_ITEMS = [
  { id: 1,  src: "/assets/gallery/1.jpeg",  caption: "Education Support Program" },
  { id: 2,  src: "/assets/gallery/7.jpeg",  caption: "Classroom Activity" },
  { id: 3,  src: "/assets/gallery/3.jpeg",  caption: "Community Outreach" },
  { id: 4,  src: "/assets/gallery/4.jpeg",  caption: "Health Camp" },
  { id: 5,  src: "/assets/gallery/5.jpeg",  caption: "Volunteer Activity" },
  { id: 6,  src: "/assets/gallery/6.jpeg",  caption: "Distribution Drive" },
  { id: 7,  src: "/assets/gallery/8.jpeg",  caption: "Skill Development" },
  { id: 8,  src: "/assets/gallery/9.jpeg",  caption: "Awareness Campaign" },
  { id: 9,  src: "/assets/gallery/10.jpeg", caption: "Free Medical Camp" },
];

function getGalleryItems() {
  try {
    const raw = localStorage.getItem(GALLERY_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : null;
    return Array.isArray(parsed) && parsed.length ? parsed : DEFAULT_ITEMS;
  } catch {
    return DEFAULT_ITEMS;
  }
}

/* ─── Grid ────────────────────────────────────────────────── */
function renderGrid() {
  const grid = document.querySelector(".gallery-grid");
  if (!grid) return;

  const items = getGalleryItems();
  grid.innerHTML = "";

  if (!items.length) {
    grid.innerHTML = `<p class="gallery-empty">No images available yet.</p>`;
    return;
  }

  items.forEach((item, i) => {
    const div = document.createElement("div");
    div.className = "gallery-item skeleton";

    const img = document.createElement("img");
    img.alt      = item.caption || "";
    img.loading  = "lazy";
    img.decoding = "async";
    img.className = "skeleton-hidden";

    img.addEventListener("load", () => {
      img.classList.add("loaded");
      img.classList.remove("skeleton-hidden");
      div.classList.remove("skeleton");
    });
    img.addEventListener("error", () => {
      div.classList.remove("skeleton");
      img.classList.remove("skeleton-hidden");
      img.style.opacity = "0.3";
    });

    // Stagger load to avoid layout thrash
    setTimeout(() => { img.src = item.src; }, i * 60);

    div.appendChild(img);
    grid.appendChild(div);
    div.addEventListener("click", () => openLightbox(items, i));
  });
}

/* ─── Carousel ────────────────────────────────────────────── */
function renderCarousel() {
  const track = document.querySelector(".carousel-track");
  if (!track) return;

  const items = getGalleryItems().slice(0, 6);
  track.innerHTML = "";

  items.forEach(item => {
    const slide = document.createElement("div");
    slide.className = "carousel-slide";
    slide.innerHTML = `<img src="${item.src}" alt="${escHtml(item.caption || "")}">
                       <p>${escHtml(item.caption || "")}</p>`;
    track.appendChild(slide);
  });

  initCarousel();
}

function initCarousel() {
  const carousel = document.querySelector(".gallery-carousel");
  const track    = document.querySelector(".carousel-track");
  if (!carousel || !track) return;

  const slides = track.querySelectorAll(".carousel-slide");
  if (!slides.length) return;

  let current = 0;
  const total = slides.length;

  function goTo(index) {
    current = (index + total) % total;
    track.style.transform = `translateX(-${current * 100}%)`;
  }

  carousel.querySelector(".carousel-btn.prev")
    ?.addEventListener("click", () => goTo(current - 1));
  carousel.querySelector(".carousel-btn.next")
    ?.addEventListener("click", () => goTo(current + 1));

  // Auto-play
  let autoplay = setInterval(() => goTo(current + 1), 4500);
  carousel.addEventListener("mouseenter", () => clearInterval(autoplay));
  carousel.addEventListener("mouseleave", () => {
    autoplay = setInterval(() => goTo(current + 1), 4500);
  });

  // Touch swipe
  let touchStartX = 0;
  track.addEventListener("touchstart", e => {
    touchStartX = e.touches[0].clientX;
  }, { passive: true });
  track.addEventListener("touchend", e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) goTo(current + (diff > 0 ? 1 : -1));
  });
}

/* ─── Lightbox ────────────────────────────────────────────── */
let lbItems = [];
let lbIndex = 0;

function openLightbox(items, index) {
  lbItems = items;
  lbIndex = index;
  const box = document.getElementById("lightbox");
  if (!box) return;
  updateLightboxContent();
  box.style.display = "flex";
  document.body.style.overflow = "hidden";
}

function updateLightboxContent() {
  const box = document.getElementById("lightbox");
  const img = box.querySelector(".lightbox-img");
  const cap = box.querySelector(".lightbox-caption");
  const counter = box.querySelector(".lightbox-counter");
  const item = lbItems[lbIndex];
  if (!item) return;
  img.src = item.src;
  img.alt = item.caption || "";
  if (cap) cap.textContent = item.caption || "";
  if (counter) counter.textContent = `${lbIndex + 1} / ${lbItems.length}`;
}

function lightboxNav(dir) {
  lbIndex = (lbIndex + dir + lbItems.length) % lbItems.length;
  updateLightboxContent();
}

function closeLightbox() {
  const box = document.getElementById("lightbox");
  if (!box) return;
  box.style.display = "none";
  document.body.style.overflow = "";
}

function initLightbox() {
  const box = document.getElementById("lightbox");
  if (!box) return;

  box.querySelector(".lightbox-close")
    ?.addEventListener("click", closeLightbox);
  box.querySelector(".lightbox-prev")
    ?.addEventListener("click", () => lightboxNav(-1));
  box.querySelector(".lightbox-next")
    ?.addEventListener("click", () => lightboxNav(1));

  // Click backdrop to close
  box.addEventListener("click", e => {
    if (e.target === box) closeLightbox();
  });

  // Keyboard nav
  document.addEventListener("keydown", e => {
    if (box.style.display !== "flex") return;
    if (e.key === "Escape")      closeLightbox();
    if (e.key === "ArrowLeft")   lightboxNav(-1);
    if (e.key === "ArrowRight")  lightboxNav(1);
  });

  // Touch swipe in lightbox
  let lbTouchX = 0;
  box.addEventListener("touchstart", e => {
    lbTouchX = e.touches[0].clientX;
  }, { passive: true });
  box.addEventListener("touchend", e => {
    const diff = lbTouchX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) lightboxNav(diff > 0 ? 1 : -1);
  });
}

/* ─── Helpers ─────────────────────────────────────────────── */
function escHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

/* ─── Init ────────────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  renderGrid();
  renderCarousel();
  initLightbox();
});
