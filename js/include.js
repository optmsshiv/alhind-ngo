document.addEventListener("DOMContentLoaded", () => {

    // LOAD HEADER
    fetch("partials/header.html")
        .then(res => res.text())
        .then(html => {
            document.getElementById("header").innerHTML = html;

            // ACTIVE MENU
            const links = document.querySelectorAll(".nav-link");
            const currentPage = location.pathname.split("/").pop() || "index.html";

            links.forEach(link => {
                if (link.getAttribute("href") === currentPage) {
                    link.classList.add("active");
                }
            });

            // MOBILE MENU
            const toggle = document.getElementById("menuToggle");
            const nav = document.getElementById("mainNav");

            toggle.addEventListener("click", () => {
                nav.classList.toggle("open");
            });

            // STICKY HEADER
            const header = document.getElementById("siteHeader");
            const headerHeight = header.offsetHeight;

            window.addEventListener("scroll", () => {
                if (window.scrollY > headerHeight) {
                    header.classList.add("sticky");
                    document.body.classList.add("header-sticky");
                } else {
                    header.classList.remove("sticky");
                    document.body.classList.remove("header-sticky");
                }
            });
        });

    // LOAD FOOTER
    fetch("partials/footer.html")
        .then(res => res.text())
        .then(data => {
            document.getElementById("footer").innerHTML = data;

            // SET YEAR AFTER LOAD
            document.getElementById("year").textContent = new Date().getFullYear();
        });

});
