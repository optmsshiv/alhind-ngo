/**
 * donate.js — AL Hind Educational and Charitable Trust
 * Secure Razorpay integration:
 *   1. Frontend collects name, email, amount
 *   2. Backend creates Razorpay order (create-order.php)
 *   3. Razorpay checkout opens with real order_id
 *   4. Backend verifies signature (verify-payment.php)
 *   5. DB updated → success popup shown
 */

'use strict';

// ── API base (update if your PHP files are in a different folder) ──
const DONATE_PHP_BASE = '/backend'; // e.g. /php/create-order.php

/* ════════════════════════════════════════════════════
   PRESET AMOUNT BUTTONS
════════════════════════════════════════════════════ */
document.querySelectorAll('.donation-presets button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.donation-presets button')
      .forEach(b => b.classList.remove('active-preset'));
    btn.classList.add('active-preset');
    const amtEl = document.getElementById('donationAmount');
    if (amtEl) amtEl.value = btn.dataset.amount;
  });
});

/* ════════════════════════════════════════════════════
   STICKY DONATE BAR
════════════════════════════════════════════════════ */
window.addEventListener('scroll', () => {
  const sticky = document.getElementById('stickyDonate');
  if (sticky) sticky.style.display = window.scrollY > 300 ? 'flex' : 'none';
}, { passive: true });

function scrollToDonate() {
  document.getElementById('donationForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ════════════════════════════════════════════════════
   SUCCESS POPUP
════════════════════════════════════════════════════ */
function showSuccess(name, amount, paymentId) {
  const popup = document.getElementById('successPopup');
  const nameEl = document.getElementById('success-name');
  const amtEl = document.getElementById('success-amount');
  const pidEl = document.getElementById('success-pid');

  if (nameEl) nameEl.textContent = name || 'Donor';
  if (amtEl) amtEl.textContent = amount ? `₹${parseFloat(amount).toLocaleString('en-IN')}` : '';
  if (pidEl) pidEl.textContent = paymentId || '';

  if (popup) {
    popup.style.display = 'flex';
    popup.classList.add('active');
  }
}

function closeSuccess() {
  const popup = document.getElementById('successPopup');
  if (popup) {
    popup.style.display = 'none';
    popup.classList.remove('active');
  }
}

// Close popup on backdrop click
document.getElementById('successPopup')?.addEventListener('click', function (e) {
  if (e.target === this) closeSuccess();
});

/* ════════════════════════════════════════════════════
   FORM VALIDATION
════════════════════════════════════════════════════ */
function validateForm(name, email, amount) {
  if (!name || name.length < 2) {
    showFormError('Please enter your full name.');
    return false;
  }
  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!email || !emailRx.test(email)) {
    showFormError('Please enter a valid email address.');
    return false;
  }
  if (!amount || isNaN(amount) || amount < 10) {
    showFormError('Minimum donation amount is ₹10.');
    return false;
  }
  if (amount > 500000) {
    showFormError('Maximum donation amount is ₹5,00,000. Please contact us for larger donations.');
    return false;
  }
  clearFormError();
  return true;
}

function showFormError(msg) {
  let errEl = document.getElementById('donate-error');
  if (!errEl) {
    errEl = document.createElement('p');
    errEl.id = 'donate-error';
    errEl.className = 'donate-error-msg';
    errEl.style.cssText = 'color:#dc2626;font-size:.85rem;margin:.5rem 0;font-weight:600;';
    document.getElementById('donationForm')
      ?.querySelector('button[type="submit"]')
      ?.insertAdjacentElement('beforebegin', errEl);
  }
  errEl.textContent = '⚠ ' + msg;
  errEl.style.display = 'block';
}

function clearFormError() {
  const errEl = document.getElementById('donate-error');
  if (errEl) errEl.style.display = 'none';
}

/* ════════════════════════════════════════════════════
   SET BUTTON STATE
════════════════════════════════════════════════════ */
function setSubmitState(btn, state) {
  if (!btn) return;
  if (state === 'loading') {
    btn.disabled = true;
    btn.dataset.orig = btn.textContent;
    btn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:.5rem">'
      + '<svg style="animation:spin 1s linear infinite" width="16" height="16" viewBox="0 0 16 16" fill="none">'
      + '<circle cx="8" cy="8" r="6" stroke="rgba(255,255,255,.3)" stroke-width="2"/>'
      + '<path d="M8 2a6 6 0 0 1 6 6" stroke="white" stroke-width="2" stroke-linecap="round"/>'
      + '</svg>Processing…</span>';
  } else {
    btn.disabled = false;
    btn.textContent = btn.dataset.orig || 'Donate Securely';
  }
}

// Spinner keyframe
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
document.head.appendChild(spinStyle);

/* ════════════════════════════════════════════════════
   STEP 1 — CREATE RAZORPAY ORDER (backend)
════════════════════════════════════════════════════ */
async function createOrder(name, email, amount) {
  const res = await fetch(`${DONATE_PHP_BASE}/create-order.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, amount }),
  });

  if (!res.ok) throw new Error(`Order API returned ${res.status}`);
  const data = await res.json();

  if (data.status !== 'success') {
    throw new Error(data.message || 'Order creation failed');
  }
  return data; // { order_id, amount, currency, key_id }
}

/* ════════════════════════════════════════════════════
   STEP 2 — OPEN RAZORPAY CHECKOUT
════════════════════════════════════════════════════ */
function openRazorpay(orderData, name, email, amount, submitBtn) {
  return new Promise((resolve, reject) => {
    const options = {
      key: orderData.key_id,
      amount: orderData.amount,       // paise — from backend
      currency: orderData.currency,
      order_id: orderData.order_id,     // backend-created order ID
      name: 'AL Hind Educational and Charitable Trust',
      description: 'Donation — Empowering Communities',
      image: '/assets/logo.png',
      prefill: { name, email },
      theme: { color: '#0f766e' },

      notes: {
        donor_name: name,
        donor_email: email,
      },

      // ✅ Payment succeeded — resolve with payment response
      handler: function (response) {
        resolve(response);
      },

      modal: {
        // User closed the modal without paying
        ondismiss: function () {
          setSubmitState(submitBtn, 'reset');
          reject(new Error('dismissed'));
        },
        escape: false,
        backdropclose: false,
        animation: true,
        confirm_close: true,
      },
    };

    try {
      const rzp = new Razorpay(options);

      rzp.on('payment.failed', function (resp) {
        console.error('[AL Hind] Payment failed:', resp.error);
        setSubmitState(submitBtn, 'reset');
        showFormError('Payment failed: ' + (resp.error?.description || 'Please try again.'));
        reject(new Error('payment_failed'));
      });

      rzp.open();
    } catch (err) {
      reject(err);
    }
  });
}

/* ════════════════════════════════════════════════════
   STEP 3 — VERIFY PAYMENT SIGNATURE (backend)
════════════════════════════════════════════════════ */
async function verifyPayment(razorpayOrderId, razorpayPaymentId, razorpaySignature) {
  const res = await fetch(`${DONATE_PHP_BASE}/verify-payment.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      razorpay_order_id: razorpayOrderId,
      razorpay_payment_id: razorpayPaymentId,
      razorpay_signature: razorpaySignature,
    }),
  });

  if (!res.ok) throw new Error(`Verify API returned ${res.status}`);
  const data = await res.json();

  if (data.status !== 'success') {
    throw new Error(data.message || 'Verification failed');
  }
  return data; // { name, email, amount, payment_id }
}

/* ════════════════════════════════════════════════════
   MAIN FORM SUBMIT HANDLER
════════════════════════════════════════════════════ */
const donationForm = document.getElementById('donationForm');

if (donationForm) {
  donationForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    clearFormError();

    const name = document.getElementById('donorName')?.value.trim() || '';
    const email = document.getElementById('donorEmail')?.value.trim() || '';
    const amount = parseFloat(document.getElementById('donationAmount')?.value || 0);

    // Validate
    if (!validateForm(name, email, amount)) return;

    const submitBtn = donationForm.querySelector('button[type="submit"]');
    setSubmitState(submitBtn, 'loading');

    try {
      // ── Step 1: Create order on backend ──────────────
      let orderData;
      try {
        orderData = await createOrder(name, email, amount);
      } catch (err) {
        console.error('[AL Hind] Order creation failed:', err);
        showFormError('Could not initiate payment. Please try again or use UPI/bank transfer below.');
        setSubmitState(submitBtn, 'reset');
        return;
      }

      // ── Step 2: Open Razorpay checkout ───────────────
      let paymentResponse;
      try {
        paymentResponse = await openRazorpay(orderData, name, email, amount, submitBtn);
      } catch (err) {
        if (err.message !== 'dismissed' && err.message !== 'payment_failed') {
          showFormError('Payment gateway unavailable. Please donate via UPI QR or bank transfer below.');
        }
        // If dismissed or failed, setSubmitState already called in ondismiss/on payment.failed
        return;
      }

      // ── Step 3: Verify payment signature on backend ──
      setSubmitState(submitBtn, 'loading'); // keep loading while verifying
      try {
        const verified = await verifyPayment(
          paymentResponse.razorpay_order_id,
          paymentResponse.razorpay_payment_id,
          paymentResponse.razorpay_signature
        );

        // ✅ All done — show success
        donationForm.reset();
        document.querySelectorAll('.donation-presets button')
          .forEach(b => b.classList.remove('active-preset'));
        setSubmitState(submitBtn, 'reset');
        showSuccess(verified.name || name, verified.amount || amount, paymentResponse.razorpay_payment_id);

      } catch (err) {
        console.error('[AL Hind] Verification failed:', err);
        // Payment went through but verification call failed — still show success
        // (Razorpay webhook handles this as a fallback)
        setSubmitState(submitBtn, 'reset');
        showSuccess(name, amount, paymentResponse.razorpay_payment_id);
      }

    } catch (err) {
      console.error('[AL Hind] Unexpected error:', err);
      showFormError('Something went wrong. Please refresh and try again.');
      setSubmitState(submitBtn, 'reset');
    }
  });
}