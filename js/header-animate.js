document.addEventListener("DOMContentLoaded", () => {

    fetch("/partials/header.html")
        .then(res => res.text())
        .then(html => {

            document.getElementById("header").innerHTML = html;

            // ----- HEADER EXISTS ONLY HERE -----
            const header = document.getElementById("siteHeader");
            const toggle = document.getElementById("menuToggle");
            const nav = document.getElementById("mainNav");
            const navLinks = document.querySelectorAll(".nav-link");
            const currentPage = location.pathname.split("/").pop() || "index.html";

            header.classList.add("visible");

            navLinks.forEach(link => {
                if (link.getAttribute("href") === currentPage) {
                    link.classList.add("active");
                }
            });

            window.addEventListener("scroll", () => {
                window.scrollY > 80
                    ? header.classList.add("scrolled")
                    : header.classList.remove("scrolled");
            });

            toggle.addEventListener("click", () => {
                nav.classList.toggle("open");
            });

            // ✅ close menu on link click
            nav.querySelectorAll("a").forEach(link => {
                link.addEventListener("click", () => {
                    nav.classList.remove("open");
                });
            });

        });

});
