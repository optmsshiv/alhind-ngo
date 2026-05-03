/**
 * contact.js — AL Hind Trust
 * Handles contact / join form submission to the real API.
 */

const CONTACT_API = 'https://api.alhindtrust.com';

/* ── Join fee display ──────────────────────────────────────── */
const JOIN_FEES = {
  volunteer: '₹200 (one-time)',
  team: '₹500 (one-time)',
  partner: 'Contact us for CSR / collaboration rates',
  general: null,
};

const joinType = document.getElementById('joinType');
const feeBox = document.getElementById('joinFeeBox');
const feeAmt = document.getElementById('feeAmount');

if (joinType) {
  joinType.addEventListener('change', () => {
    const fee = JOIN_FEES[joinType.value];
    if (fee && feeBox && feeAmt) {
      feeAmt.textContent = fee;
      feeBox.style.display = 'block';
    } else if (feeBox) {
      feeBox.style.display = 'none';
    }
  });
}

/* ── Form submit ───────────────────────────────────────────── */
const contactForm = document.querySelector('.ngo-form');
const joinBtn = document.getElementById('joinBtn');

if (contactForm) {
  contactForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const name = contactForm.querySelector("[name='name']")?.value.trim() || '';
    const email = contactForm.querySelector("[name='email']")?.value.trim() || '';
    const phone = contactForm.querySelector("[name='phone']")?.value.trim() || '';
    const interest = contactForm.querySelector("[name='interest']")?.value || 'general';
    const message = contactForm.querySelector("[name='message']")?.value.trim() || '';

    if (!name || !message) {
      alert('Please fill in your name and message.');
      return;
    }

    if (joinBtn) { joinBtn.disabled = true; joinBtn.textContent = 'Sending…'; }

    try {
      const res = await fetch(`${CONTACT_API}/contact`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, phone, interest, message }),
      });

      const data = await res.json();

      if (data.success) {
        // Show success — use SweetAlert2 if available
        if (typeof Swal !== 'undefined') {
          await Swal.fire({
            icon: 'success',
            title: 'Message Sent! 🙏',
            html: `Thank you <strong>${name}</strong>! We'll respond within 24–48 hours.<br><small>Ticket: ${data.data?.ticket_id || ''}</small>`,
            confirmButtonColor: '#0f766e',
            confirmButtonText: 'Close',
          });
        } else {
          alert(`Thank you ${name}! Your message has been received. We'll get back to you within 24–48 hours.`);
        }

        contactForm.reset();
        if (feeBox) feeBox.style.display = 'none';
      } else {
        throw new Error(data.error || 'Submission failed');
      }
    } catch (err) {
      console.error('Contact form error:', err);
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: 'Something went wrong',
          text: 'Please try again or contact us directly at alhindtrust@gmail.com',
          confirmButtonColor: '#0f766e',
        });
      } else {
        alert('Something went wrong. Please email us at alhindtrust@gmail.com');
      }
    } finally {
      if (joinBtn) { joinBtn.disabled = false; joinBtn.textContent = 'Send Message'; }
    }
  });
}