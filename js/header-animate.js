document.addEventListener("DOMContentLoaded", () => {
    fetch("partials/header.html")
        .then(res => res.text())
        .then(html => {
            document.getElementById("header").innerHTML = html;

            // ✅ NOW header exists
            const header = document.getElementById("siteHeader");
            const toggle = document.getElementById("menuToggle");
            const nav = document.getElementById("mainNav");
            const navLinks = document.querySelectorAll(".nav-link");
            const currentPage = location.pathname.split("/").pop() || "index.html";

            // SHOW HEADER
            header.classList.add("visible");

            navLinks.forEach(link => {
                if (link.getAttribute("href") === currentPage) {
                    link.classList.add("active");
                }
            });
            
            // SCROLL EFFECT
            window.addEventListener("scroll", () => {
                if (window.scrollY > 80) {
                    header.classList.add("scrolled");
                } else {
                    header.classList.remove("scrolled");
                }
            });

            // MOBILE MENU
            toggle.addEventListener("click", () => {
                nav.classList.toggle("open");
            });
        });

    nav.querySelectorAll("a").forEach(link => {
        link.addEventListener("click", () => {
            nav.classList.remove("open");
        });
    });

});
