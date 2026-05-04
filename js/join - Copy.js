/**
 * join.js — AL Hind Trust
 * Reads ?id= from URL, fetches event, handles registration form.
 */

const API = 'https://api.alhindtrust.com';
const today = new Date().toISOString().split('T')[0];
let currentEvent = null;

/* ── Helpers ─────────────────────────────────────────────────── */
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  });
}
function $(id) { return document.getElementById(id); }

/* ── Load Event ──────────────────────────────────────────────── */
async function loadEvent(id) {
  try {
    const res  = await fetch(`${API}/events`);
    const data = await res.json();
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
  badge.className   = 'jn-status-badge ' + (isPast ? 'past' : 'upcoming');

  // Category
  if (e.category) {
    $('jn-category').textContent = e.category;
    $('jn-category').style.display = 'inline-block';
  } else {
    $('jn-category').style.display = 'none';
  }

  // Details
  $('jn-event-title').textContent    = e.title || 'Event';
  $('jn-event-date').textContent     = fmtDate(e.date);
  $('jn-event-location').textContent = e.location || '—';
  $('jn-event-desc').textContent     = e.description || '';

  // Page title
  document.title = `Join: ${e.title} | AL Hind Trust`;

  // Map
  if (e.mapQuery) {
    $('jn-map').src = `https://www.google.com/maps?q=${encodeURIComponent(e.mapQuery)}&output=embed`;
    $('jn-map-wrap').style.display = 'block';
  }

  // If past event — show notice instead of form
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
  ['name','phone','email','city'].forEach(f => {
    $(`err-${f}`).textContent = '';
    $(`jn-${f}`).classList.remove('error');
  });
}

function validate() {
  let ok = true;

  const name  = $('jn-name').value.trim();
  const phone = $('jn-phone').value.trim();
  const email = $('jn-email').value.trim();
  const city  = $('jn-city').value.trim();

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

/* ── Submit Registration ─────────────────────────────────────── */
async function submitJoin() {
  clearErrors();
  if (!validate()) return;

  const btn = $('jn-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Registering…';

  const payload = {
    event_id: currentEvent.id,
    name:     $('jn-name').value.trim(),
    phone:    $('jn-phone').value.trim(),
    email:    $('jn-email').value.trim(),
    city:     $('jn-city').value.trim(),
    message:  $('jn-message').value.trim(),
  };

  try {
    const res  = await fetch(`${API}/event-volunteers`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });
    const data = await res.json();

    if (!res.ok) throw new Error(data.message || 'Registration failed');

    // Show success
    $('jn-form-wrap').style.display = 'none';
    $('jn-success').style.display   = 'block';
    $('jn-success-msg').textContent = `Thank you, ${payload.name}! You are registered for "${currentEvent.title}".`;

    if (payload.email) {
      $('jn-success-msg').textContent += ' A confirmation email has been sent to ' + payload.email + '.';
    }

    $('jn-success-details').innerHTML = `
      <strong><i class="fa-solid fa-calendar-days"></i> Event:</strong> ${esc(currentEvent.title)}<br>
      <strong><i class="fa-solid fa-calendar-check"></i> Date:</strong> ${fmtDate(currentEvent.date)}<br>
      <strong><i class="fa-solid fa-location-dot"></i> Venue:</strong> ${esc(currentEvent.location || '—')}<br>
      ${payload.phone ? `<strong><i class="fa-solid fa-phone"></i> Your Phone:</strong> ${esc(payload.phone)}<br>` : ''}
    `;

    // Scroll to success message
    $('jn-success').scrollIntoView({ behavior: 'smooth', block: 'center' });

  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Register Now';
    // Show error inline
    const errDiv = document.createElement('div');
    errDiv.style.cssText = 'background:#fee2e2;color:#991b1b;border-radius:8px;padding:.75rem 1rem;font-size:.82rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;';
    errDiv.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> ${esc(err.message || 'Something went wrong. Please try again.')}`;
    const existing = document.querySelector('.jn-api-err');
    if (existing) existing.remove();
    errDiv.className = 'jn-api-err';
    $('jn-submit').insertAdjacentElement('beforebegin', errDiv);
  }
}

/* ── Init ────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const id     = params.get('id');

  if (!id) {
    $('jn-loading').style.display = 'none';
    $('jn-error').style.display   = 'block';
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
