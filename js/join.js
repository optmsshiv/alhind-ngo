/**
 * join.js — AL Hind Trust
 * Reads ?id= from URL, fetches event, handles registration form.
 * Fixes: duplicate phone handling, JSON parse errors, SweetAlert2 success popup
 */

const API = 'https://api.alhindtrust.com';
const today = new Date().toISOString().split('T')[0];
let currentEvent = null;

/* ── Load SweetAlert2 dynamically ────────────────────────────── */
(function loadSwal() {
  if (window.Swal) return;
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
  document.head.appendChild(link);

  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
  script.async = true;
  document.head.appendChild(script);
})();

/* ── Helpers ─────────────────────────────────────────────────── */
function esc(s) {
  return String(s || '')
    .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
    .replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  });
}
function $(id) { return document.getElementById(id); }

/* ── Safe JSON parse — handles HTML error pages from PHP ─────── */
async function safeJson(res) {
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch {
    // API returned HTML (PHP error/duplicate entry page) instead of JSON
    console.error('Non-JSON response from API:', text.slice(0, 200));

    // Try to detect duplicate entry from common PHP/MySQL error strings
    const lower = text.toLowerCase();
    if (
      lower.includes('duplicate') ||
      lower.includes('already registered') ||
      lower.includes('already exists') ||
      lower.includes('unique') ||
      res.status === 409
    ) {
      return { status: 'duplicate', message: 'already_registered' };
    }

    return { status: 'error', message: 'server_error' };
  }
}

/* ── Load Event ──────────────────────────────────────────────── */
async function loadEvent(id) {
  try {
    const res = await fetch(`${API}/events`);
    const data = await safeJson(res);
    const list = data.data || [];
    return list.find(e => String(e.id) === String(id)) || null;
  } catch (e) {
    console.error('Failed to fetch events:', e);
    return null;
  }
}

/* ── Render Event Details ────────────────────────────────────── */
function renderEvent(e) {
  const isPast = e.date < today;

  // Image
  if (e.image || e.image_path) {
    $('jn-event-img').src = e.image || e.image_path;
    $('jn-event-img').alt = e.title;
    $('jn-img-placeholder').style.display = 'none';
  } else {
    $('jn-event-img').style.display = 'none';
    $('jn-img-placeholder').style.display = 'flex';
  }

  // Status badge
  const badge = $('jn-status-badge');
  badge.textContent = isPast ? 'Past Event' : 'Upcoming';
  badge.className = 'jn-status-badge ' + (isPast ? 'past' : 'upcoming');

  // Category
  if (e.category) {
    $('jn-category').textContent = e.category;
    $('jn-category').style.display = 'inline-block';
  } else {
    $('jn-category').style.display = 'none';
  }

  // Details
  $('jn-event-title').textContent = e.title || 'Event';
  $('jn-event-date').textContent = fmtDate(e.date);
  $('jn-event-location').textContent = e.location || '—';
  $('jn-event-desc').textContent = e.description || '';

  document.title = `Join: ${e.title} | AL Hind Trust`;

  // Map
  if (e.mapQuery) {
    $('jn-map').src = `https://www.google.com/maps?q=${encodeURIComponent(e.mapQuery)}&output=embed`;
    $('jn-map-wrap').style.display = 'block';
  }

  // Past event — hide form
  if (isPast) {
    $('jn-form-wrap').innerHTML = `
      <div class="jn-past-notice">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <strong>This Event Has Ended</strong>
        Registration is closed. Check upcoming events below.
        <br><br>
        <a href="/events.html" class="jn-back-btn" style="margin:0 auto;width:fit-content">
          <i class="fa-solid fa-calendar-days"></i> View Upcoming Events
        </a>
      </div>`;
  }
}

/* ── Validation ──────────────────────────────────────────────── */
function clearErrors() {
  ['name', 'phone', 'email', 'city'].forEach(f => {
    $(`err-${f}`).textContent = '';
    $(`jn-${f}`).classList.remove('error');
  });
}

function validate() {
  let ok = true;
  const name = $('jn-name').value.trim();
  const phone = $('jn-phone').value.trim();
  const email = $('jn-email').value.trim();
  const city = $('jn-city').value.trim();

  if (!name || name.length < 2) {
    $('err-name').textContent = 'Please enter your full name.';
    $('jn-name').classList.add('error');
    ok = false;
  }
  if (!phone || !/^[6-9]\d{9}$/.test(phone)) {
    $('err-phone').textContent = 'Enter a valid 10-digit Indian mobile number.';
    $('jn-phone').classList.add('error');
    ok = false;
  }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    $('err-email').textContent = 'Enter a valid email address.';
    $('jn-email').classList.add('error');
    ok = false;
  }
  if (!city || city.length < 2) {
    $('err-city').textContent = 'Please enter your city or district.';
    $('jn-city').classList.add('error');
    ok = false;
  }

  return ok;
}

/* ── SweetAlert helpers ──────────────────────────────────────── */
function swalSuccess(name, email, eventTitle, eventDate, eventLocation) {
  const emailLine = email
    ? `<div style="margin-top:.5rem;font-size:.82rem;color:#64748b;">
         📧 Confirmation sent to <strong>${esc(email)}</strong>
       </div>`
    : '';

  Swal.fire({
    icon: 'success',
    title: `You're Registered! 🎉`,
    html: `
      <div style="text-align:left;font-size:.9rem;line-height:1.7;">
        <p style="margin-bottom:.75rem;">Thank you, <strong>${esc(name)}</strong>! Your spot is confirmed.</p>
        <div style="background:#f0fdf4;border-radius:10px;padding:.85rem 1rem;margin-bottom:.5rem;">
          <div>📅 <strong>Event:</strong> ${esc(eventTitle)}</div>
          <div>🗓️ <strong>Date:</strong> ${eventDate}</div>
          <div>📍 <strong>Venue:</strong> ${esc(eventLocation || '—')}</div>
        </div>
        ${emailLine}
      </div>`,
    confirmButtonText: 'View All Events',
    confirmButtonColor: '#0f766e',
    showCancelButton: true,
    cancelButtonText: 'Stay on Page',
    cancelButtonColor: '#64748b',
    allowOutsideClick: false,
    customClass: {
      popup: 'swal-alhind-popup',
      title: 'swal-alhind-title',
    },
  }).then(result => {
    if (result.isConfirmed) {
      window.location.href = '/events.html';
    }
  });
}

function swalDuplicate(phone) {
  Swal.fire({
    icon: 'info',
    title: 'Already Registered',
    html: `
      <p style="font-size:.92rem;color:#475569;line-height:1.6;">
        The mobile number <strong>${esc(phone)}</strong> is already registered for this event.
      </p>
      <p style="font-size:.85rem;color:#94a3b8;margin-top:.5rem;">
        If you think this is a mistake, please contact us.
      </p>`,
    confirmButtonText: 'OK, Got It',
    confirmButtonColor: '#0f766e',
    showCancelButton: true,
    cancelButtonText: 'View Events',
    cancelButtonColor: '#64748b',
  }).then(result => {
    if (!result.isConfirmed) {
      window.location.href = '/events.html';
    }
  });
}

function swalError(message) {
  Swal.fire({
    icon: 'error',
    title: 'Registration Failed',
    text: message || 'Something went wrong. Please try again.',
    confirmButtonText: 'Try Again',
    confirmButtonColor: '#0f766e',
  });
}

/* ── Reset button state ──────────────────────────────────────── */
function resetBtn(btn) {
  btn.disabled = false;
  btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Register Now';
}

/* ── Submit Registration ─────────────────────────────────────── */
async function submitJoin() {
  clearErrors();
  if (!validate()) return;

  const btn = $('jn-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registering…';

  // Remove any previous inline error
  document.querySelector('.jn-api-err')?.remove();

  const payload = {
    event_id: currentEvent.id,
    name: $('jn-name').value.trim(),
    phone: $('jn-phone').value.trim(),
    email: $('jn-email').value.trim(),
    city: $('jn-city').value.trim(),
    message: $('jn-message').value.trim(),
  };

  try {
    const res = await fetch(`${API}/volunteers`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    // Read raw text first — API may return HTML on errors
    const rawText = await res.text();
    let data = {};
    try {
      data = JSON.parse(rawText);
    } catch {
      console.warn('[AL Hind] Non-JSON response (status ' + res.status + '):', rawText.slice(0, 300));
    }

    const lower = rawText.toLowerCase();

    // ── Duplicate phone ───────────────────────────────────────
    const isDuplicate =
      res.status === 409 ||
      data.status === 'duplicate' ||
      (data.message || '').toLowerCase().includes('already') ||
      (data.message || '').toLowerCase().includes('duplicate') ||
      lower.includes('duplicate entry') ||
      lower.includes('already registered');

    if (isDuplicate) {
      resetBtn(btn);
      swalDuplicate(payload.phone);
      return;
    }

    // ── Treat 200/201 as success always ──────────────────────
    const isSuccess =
      res.status === 200 ||
      res.status === 201 ||
      data.status === 'success' ||
      data.status === 'ok';

    if (!isSuccess) {
      resetBtn(btn);
      const errMsg = data.message || (res.status >= 500 ? 'Server error. Please try again.' : 'Registration failed.');
      swalError(errMsg);
      return;
    }

    // ── Success ───────────────────────────────────────────────
    resetBtn(btn);

    // Hide the form
    $('jn-form-wrap').style.display = 'none';

    // Show inline success card too (good UX backup)
    $('jn-success').style.display = 'block';
    $('jn-success-msg').textContent =
      `Thank you, ${payload.name}! You're registered for "${currentEvent.title}".` +
      (payload.email ? ` A confirmation has been sent to ${payload.email}.` : '');

    $('jn-success-details').innerHTML = `
      <strong><i class="fa-solid fa-calendar-days"></i> Event:</strong> ${esc(currentEvent.title)}<br>
      <strong><i class="fa-solid fa-calendar-check"></i> Date:</strong> ${fmtDate(currentEvent.date)}<br>
      <strong><i class="fa-solid fa-location-dot"></i> Venue:</strong> ${esc(currentEvent.location || '—')}<br>
      ${payload.phone ? `<strong><i class="fa-solid fa-phone"></i> Phone:</strong> ${esc(payload.phone)}<br>` : ''}
    `;

    $('jn-success').scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Fire SweetAlert2 popup
    swalSuccess(
      payload.name,
      payload.email,
      currentEvent.title,
      fmtDate(currentEvent.date),
      currentEvent.location
    );

    // Reset form fields
    ['jn-name', 'jn-phone', 'jn-email', 'jn-city', 'jn-message'].forEach(id => {
      const el = $(id);
      if (el) el.value = '';
    });

  } catch (err) {
    console.error('[AL Hind] submitJoin error:', err);
    resetBtn(btn);
    swalError('Something went wrong. Please check your connection and try again.');
  }
}

/* ── Init ────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    $('jn-loading').style.display = 'none';
    $('jn-error').style.display = 'block';
    return;
  }

  const event = await loadEvent(id);
  $('jn-loading').style.display = 'none';

  if (!event) {
    $('jn-error').style.display = 'block';
    return;
  }

  currentEvent = event;
  renderEvent(event);
  $('jn-content').style.display = 'grid';
});