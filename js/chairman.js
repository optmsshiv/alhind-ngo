/* =====================================
   REVEAL ANIMATION (BEST METHOD)
===================================== */

const reveals = document.querySelectorAll(".reveal");

if (reveals.length) {

    const observer = new IntersectionObserver((entries, observer) => {

        entries.forEach(entry => {

            if (entry.isIntersecting) {

                entry.target.classList.add("active");

                observer.unobserve(entry.target);

            }

        });

    }, { threshold: 0.15 });

    reveals.forEach(el => observer.observe(el));

}



/* =====================================
   TYPEWRITER MESSAGE
===================================== */

const message = `
Education is not merely about literacy—it is about shaping character,building confidence, and empowering individuals with dignity and purpose. At AL Hind Educational and Charitable Trust, every initiative begins with compassion.

We strive to reach underserved communities through education, skill development, and social support systems driven by transparency and accountability.

Sustainable transformation happens when opportunity meets education. Together with volunteers, donors, and supporters, we move toward a future where no child is left behind.

`;

let i = 0;

const typingBox = document.getElementById("typingText");

function typing() {

    if (!typingBox) return;

    if (i < message.length) {

        typingBox.innerHTML +=
            message.charAt(i).replace(/\n/g, "<br>");

        i++;

        setTimeout(typing, 18);

    }

}

typing();