document.addEventListener("DOMContentLoaded", () => {
    const reveals = document.querySelectorAll(
        ".reveal, .reveal-left, .reveal-right"
    );

    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("active");
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.10 }
    );

    reveals.forEach(el => observer.observe(el));
});


/* DONOR CARD REVEAL */

const donorCards = document.querySelectorAll(".donor-card");

const observer = new IntersectionObserver((entries) => {

    entries.forEach(entry => {

        if (entry.isIntersecting) {

            entry.target.classList.add("show");

        }

    });

}, {
    threshold: 0.2
});

donorCards.forEach(card => {

    observer.observe(card);

});