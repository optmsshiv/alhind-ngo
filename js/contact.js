/**
 * contact.js — AL Hind Trust
 * Handles contact / join form submission.
 */

const CONTACT_API = 'https://api.alhindtrust.com';

/* ── Paid roles (must match contact.php PAID_ROLES) ───────── */
const PAID_ROLES = ['team'];         // Member → ₹500

/* ── Join fee display ──────────────────────────────────────── */
const JOIN_FEES = {
  volunteer: null,                   // Free
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

/* ── Safe JSON fetch helper ────────────────────────────────── */
async function safePost(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  const text = await res.text();   // always read raw text first

  let data;
  try {
    data = JSON.parse(text);
  } catch (_) {
    // Server returned non-JSON — log exactly what came back
    console.error('Non-JSON response from server:');
    console.error('Status:', res.status, res.statusText);
    console.error('Body:', text.slice(0, 500));   // first 500 chars
    throw new Error(`Server error ${res.status}: ${res.statusText}`);
  }

  if (!res.ok) {
    // Server returned JSON but with an error status
    throw new Error(data.error || data.message || `HTTP ${res.status}`);
  }

  return data;
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
      const data = await safePost(`${CONTACT_API}/contact`, {
        name, email, phone, interest, message,
      });

      if (!data.success) {
        throw new Error(data.error || 'Submission failed');
      }

      /* ── Success message — different for paid vs free roles ── */
      const isPaid = PAID_ROLES.includes(interest);
      const ticket = data.data?.ticket_id || '';

      if (typeof Swal !== 'undefined') {
        await Swal.fire({
          icon: 'success',
          confirmButtonColor: '#0f766e',
          confirmButtonText: 'Close',
          title: isPaid
            ? 'Almost there! 🙏'
            : 'Message Sent! 🙏',
          html: isPaid
            ? `Thank you <strong>${name}</strong>!<br>
               Check your email for the <strong>payment link</strong>
               to complete your registration.<br>
               <small style="color:#64748b">Ticket: ${ticket}</small>`
            : `Thank you <strong>${name}</strong>!<br>
               We'll respond within <strong>24–48 hours</strong>.<br>
               <small style="color:#64748b">Ticket: ${ticket}</small>`,
        });
      } else {
        alert(isPaid
          ? `Thank you ${name}! Check your email for the payment link to complete registration.`
          : `Thank you ${name}! We'll respond within 24–48 hours. Ticket: ${ticket}`
        );
      }

      contactForm.reset();
      if (feeBox) feeBox.style.display = 'none';

    } catch (err) {
      console.error('Contact form error:', err.message);

      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          confirmButtonColor: '#0f766e',
          title: 'Something went wrong',
          text: 'Please try again or contact us at alhindtrust@gmail.com',
        });
      } else {
        alert('Something went wrong. Please email us at alhindtrust@gmail.com');
      }

    } finally {
      if (joinBtn) { joinBtn.disabled = false; joinBtn.textContent = 'Send Message'; }
    }
  });
}