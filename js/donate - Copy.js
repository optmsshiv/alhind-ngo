/**
 * donate.js — AL Hind Trust
 * Handles Razorpay payment and saves donation records to the real API.
 */

const DONATE_API = 'https://api.alhindtrust.com';

/* ── Preset amount buttons ─────────────────────────────────── */
document.querySelectorAll('.donation-presets button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.donation-presets button').forEach(b => b.classList.remove('active-preset'));
    btn.classList.add('active-preset');
    const amtEl = document.getElementById('donationAmount');
    if (amtEl) amtEl.value = btn.dataset.amount;
  });
});

/* ── Sticky donate bar ─────────────────────────────────────── */
window.addEventListener('scroll', () => {
  const sticky = document.getElementById('stickyDonate');
  if (sticky) sticky.style.display = window.scrollY > 300 ? 'flex' : 'none';
});

function scrollToDonate() {
  document.getElementById('donationForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ── Success popup ─────────────────────────────────────────── */
function showSuccess() {
  const popup = document.getElementById('successPopup');
  if (popup) popup.style.display = 'flex';
}
function closeSuccess() {
  const popup = document.getElementById('successPopup');
  if (popup) popup.style.display = 'none';
}

/* ── Save donation to API ──────────────────────────────────── */
async function saveDonationToAPI(name, email, amount, method, status, razorpayOrderId = '', razorpayPaymentId = '') {
  try {
    const res = await fetch(`${DONATE_API}/donate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name, email, amount, method,
        razorpay_order_id: razorpayOrderId,
      }),
    });
    const data = await res.json();
    return data.data?.id || null;
  } catch (e) {
    console.error('Failed to save donation:', e);
    return null;
  }
}

async function confirmDonationAPI(donationId, razorpayPaymentId) {
  if (!donationId) return;
  try {
    await fetch(`${DONATE_API}/donate/${donationId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ razorpay_payment_id: razorpayPaymentId }),
    });
  } catch (e) {
    console.error('Failed to confirm donation:', e);
  }
}

/* ── Form submit ───────────────────────────────────────────── */
const donationForm = document.getElementById('donationForm');
if (donationForm) {
  donationForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const name = document.getElementById('donorName')?.value.trim() || '';
    const email = document.getElementById('donorEmail')?.value.trim() || '';
    const amount = parseFloat(document.getElementById('donationAmount')?.value || 0);

    if (!name || !email || !amount || amount < 1) {
      alert('Please fill in all fields with a valid amount.');
      return;
    }

    const submitBtn = donationForm.querySelector('button[type="submit"]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Processing…'; }

    // Save pending record to API
    const donationId = await saveDonationToAPI(name, email, amount, 'Razorpay', 'pending');

    const options = {
      key: 'YOUR_RAZORPAY_KEY_ID',  // ← Replace with your actual Razorpay key
      amount: Math.round(amount * 100), // paise
      currency: 'INR',
      name: 'AL Hind Educational and Charitable Trust',
      description: 'Donation',
      image: '/assets/logo.png',
      prefill: { name, email },
      theme: { color: '#0f766e' },

      handler: async function (response) {
        // Confirm payment in API
        await confirmDonationAPI(donationId, response.razorpay_payment_id);
        showSuccess();
        donationForm.reset();
        document.querySelectorAll('.donation-presets button').forEach(b => b.classList.remove('active-preset'));
      },

      modal: {
        ondismiss: function () {
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Donate Securely'; }
        }
      }
    };

    try {
      const rzp = new Razorpay(options);
      rzp.open();
    } catch (err) {
      console.error('Razorpay error:', err);
      alert('Payment gateway unavailable. Please donate via UPI QR or bank transfer.');
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Donate Securely'; }
    }
  });
}