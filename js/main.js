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
       compaign animation
    ========================= */
    const revealElements = document.querySelectorAll(".reveal");

    const revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("visible");
                    revealObserver.unobserve(entry.target); // animate once
                }
            });
        },
        {
            threshold: 0.15
        }
    );

    revealElements.forEach(el => revealObserver.observe(el));


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


document.addEventListener("click", (e) => {
    const nav = document.getElementById("mainNav");
    const toggle = document.getElementById("menuToggle");

    if (!nav || !toggle) return;

    if (!nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove("open");
    }
});
