document.addEventListener("DOMContentLoaded", () => {

    const track = document.querySelector(".carousel-track");
    const slides = document.querySelectorAll(".carousel-slide");
    const prev = document.querySelector(".carousel-btn.prev");
    const next = document.querySelector(".carousel-btn.next");

    if (track && slides.length && prev && next) {
        let index = 0;

        const update = () => {
            track.style.transform = `translateX(-${index * 100}%)`;
        };

        next.addEventListener("click", () => {
            index = (index + 1) % slides.length;
            update();
        });

        prev.addEventListener("click", () => {
            index = (index - 1 + slides.length) % slides.length;
            update();
        });
    }


    /* =========================
       GALLERY IMAGE LOAD + SKELETON
    ========================= */
    const galleryItems = document.querySelectorAll(".gallery-item");

    galleryItems.forEach(item => {
        const img = item.querySelector("img");
        if (!img) return;

        const revealImage = () => {
            item.classList.remove("skeleton");
            img.classList.remove("skeleton-hidden");
            img.classList.add("loaded");
        };

        if (img.complete && img.naturalWidth !== 0) {
            revealImage();
        } else {
            img.addEventListener("load", revealImage);
            img.addEventListener("error", () => {
                // fallback if image fails
                item.classList.remove("skeleton");
            });
        }
    });

    /* =========================
       LIGHTBOX
    ========================= */
    const lightbox = document.getElementById("lightbox");
    const lightboxImg = document.querySelector(".lightbox-img");
    const closeBtn = document.querySelector(".lightbox-close");

    if (!lightbox || !lightboxImg || !closeBtn) {
        console.warn("Lightbox elements not found. Check #lightbox, .lightbox-img, .lightbox-close in HTML.");
        return;
    }

    document.querySelectorAll(".gallery-item img").forEach(img => {
        img.addEventListener("click", () => {
            lightboxImg.src = img.src;
            lightbox.style.display = "flex";
            document.body.style.overflow = "hidden";
        });
    });

    const closeLightbox = () => {
        lightbox.style.display = "none";
        lightboxImg.src = "";
        document.body.style.overflow = "";
    };

    closeBtn.addEventListener("click", closeLightbox);

    lightbox.addEventListener("click", e => {
        if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener("keydown", e => {
        if (e.key === "Escape" && lightbox.style.display === "flex") {
            closeLightbox();
        }
    });

});

