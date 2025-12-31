document.addEventListener("DOMContentLoaded", () => {

    /* =========================
       COUNTER ANIMATION
    ========================= */
    const counters = document.querySelectorAll(".counter");

    counters.forEach(counter => {
        const update = () => {
            const target = +counter.dataset.target;
            const current = +counter.innerText;
            const increment = Math.max(1, target / 100);

            if (current < target) {
                counter.innerText = Math.min(target, Math.ceil(current + increment));
                setTimeout(update, 30);
            } else {
                counter.innerText = target;
            }
        };
        update();
    });

    /* =========================
       FOOTER YEAR
    ========================= */
    const yearEl = document.getElementById("year");
    if (yearEl) {
        yearEl.textContent = new Date().getFullYear();
    }

    /* =========================
       IMAGE SKELETON LOADER
    ========================= */
    document.querySelectorAll(".image-box img").forEach(img => {
        const container = img.closest(".skeleton");
        if (!container) return;

        const reveal = () => {
            container.classList.remove("skeleton");
            img.classList.remove("skeleton-hidden");
            img.classList.add("loaded");
        };

        if (img.complete) {
            reveal();
        } else {
            img.addEventListener("load", reveal);
        }
    });

});

/* =========================
   CARD SKELETON REMOVAL
========================= */
window.addEventListener("load", () => {
    document.querySelectorAll(".card").forEach(card => {
        card.classList.add("loaded");
    });
});

/* =========================
   SIMPLE CAROUSEL
========================= */
const track = document.querySelector(".carousel-track");
const slides = document.querySelectorAll(".carousel-slide");
const prevBtn = document.querySelector(".carousel-btn.prev");
const nextBtn = document.querySelector(".carousel-btn.next");

let index = 0;

function updateCarousel() {
    track.style.transform = `translateX(-${index * 100}%)`;
}

nextBtn.addEventListener("click", () => {
    index = (index + 1) % slides.length;
    updateCarousel();
});

prevBtn.addEventListener("click", () => {
    index = (index - 1 + slides.length) % slides.length;
    updateCarousel();
});

/* Auto-slide */
setInterval(() => {
    index = (index + 1) % slides.length;
    updateCarousel();
}, 5000);

const galleryImages = document.querySelectorAll(".gallery-item img");
const lightbox = document.getElementById("lightbox");
const lightboxImg = document.querySelector(".lightbox-img");
const closeBtn = document.querySelector(".lightbox-close");

galleryImages.forEach(img => {
    img.addEventListener("click", () => {
        lightboxImg.src = img.src;
        lightbox.style.display = "flex";
    });
});

closeBtn.addEventListener("click", () => {
    lightbox.style.display = "none";
});

lightbox.addEventListener("click", (e) => {
    if (e.target === lightbox) {
        lightbox.style.display = "none";
    }
});
