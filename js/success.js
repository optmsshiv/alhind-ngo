/* SCRIPT AT BOTTOM – NO NEED DOMContentLoaded */

const form = document.querySelector('.ngo-form');
const btn = document.querySelector('#joinBtn');

if (!form || !btn) {
    console.error('Selector mismatch OR js path wrong');
}

form.onsubmit = async function (e) {
    e.preventDefault();

    const fd = new FormData(this);
    const role = document.getElementById('joinType').value;

    /* SHOW LOADING BAR IN SWAL */
    Swal.fire({
        title: 'Submitting your request...',
        html: `
            <div style="margin-top:10px">
                <progress id="swalProgress" value="10" max="100" style="width:100%"></progress>
                <p id="progressText">Processing...</p>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            const p = document.getElementById('swalProgress');
            let v = 10;

            window.swalTimer = setInterval(() => {
                if (v < 90) {
                    v += 5;
                    p.value = v;
                }
            }, 200);
        }
    });

    try {
        const r = await fetch('process/join.php', {
            method: 'POST',
            body: fd
        });

        const t = await r.text();

        clearInterval(window.swalTimer);

        const p = document.getElementById('swalProgress');
        if (p) p.value = 100;

        await new Promise(res => setTimeout(res, 400));
        Swal.close();

        /* SUCCESS */
        if (t.trim().includes('success')) {

            if (role === 'volunteer' || role === 'team') {

                Swal.fire({
                    icon: 'success',
                    title: 'Almost Done!',
                    text: 'Please complete the joining contribution.',
                    confirmButtonText: 'Proceed',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = 'donate.html?purpose=join';
                });

            } else {

                Swal.fire({
                    icon: 'success',
                    title: 'Thank You',
                    text: 'We received your request. Please check your email.',
                    timer: 3000,
                    showConfirmButton: false
                });

            }

            this.reset();

        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: t || 'Try again later.'
            });
        }

    } catch (err) {
        clearInterval(window.swalTimer);

        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Server not reachable.'
        });

        console.error(err);
    }
};

/* JOIN TYPE FEE LOGIC */
const joinType = document.getElementById('joinType');
const feeBox = document.getElementById('joinFeeBox');
const feeAmount = document.getElementById('feeAmount');

joinType.addEventListener('change', () => {
    feeBox.style.display = 'none';

    if (joinType.value === 'volunteer') {
        feeAmount.innerHTML = '₹0 (Volunteer Joining Fee)';
        feeBox.style.display = 'block';
    }

    if (joinType.value === 'team') {
        feeAmount.innerHTML = '₹499 (Core Team Onboarding Fee)';
        feeBox.style.display = 'block';
    }
});
