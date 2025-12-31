document.querySelectorAll(".count").forEach(counter => {
    const target = +counter.dataset.target;
    let count = 0;

    const update = () => {
        if (count < target) {
            count += Math.ceil(target / 80);
            counter.innerText = count;
            requestAnimationFrame(update);
        } else {
            counter.innerText = target;
        }
    };
    update();
});

function closeSuccess() {
    document.getElementById("successPopup").style.display = "none";
}


window.addEventListener("scroll", () => {
    const sticky = document.getElementById("stickyDonate");
    if (window.scrollY > 600) {
        sticky.classList.add("show");
    } else {
        sticky.classList.remove("show");
    }
});

function scrollToDonate() {
    document.querySelector(".donation-box").scrollIntoView({ behavior: "smooth" });
}
