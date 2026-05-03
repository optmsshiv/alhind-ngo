/**
 * events.js — AL Hind Trust
 * Fetches events from the real API and renders them dynamically.
 */

const EVENTS_API = 'https://api.alhindtrust.com';
const today = new Date().toISOString().split('T')[0];
let allEvents = [];
let activeFilter = 'all';

/* ── Fetch ─────────────────────────────────────────────────── */
async function fetchEvents() {
  try {
    const res = await fetch(`${EVENTS_API}/events`);
    const data = await res.json();
    return data.data || [];
  } catch (e) {
    console.error('Events fetch failed:', e);
    return [];
  }
}

/* ── Render ────────────────────────────────────────────────── */
function renderEvents(filter = 'all') {
  const grid = document.getElementById('events');
  if (!grid) return;

  activeFilter = filter;

  // Update filter buttons
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.filter === filter);
  });

  const filtered = allEvents.filter(e => {
    if (filter === 'upcoming') return e.date >= today;
    if (filter === 'past') return e.date < today;
    return true;
  });

  if (!filtered.length) {
    grid.innerHTML = `<p class="events-empty">No ${filter !== 'all' ? filter : ''} events found.</p>`;
    return;
  }

  grid.innerHTML = filtered.map(e => {
    const isPast = e.date < today;
    const dateStr = fmtDate(e.date);
    const mapSrc = e.mapQuery
      ? `https://www.google.com/maps?q=${encodeURIComponent(e.mapQuery)}&output=embed`
      : '';

    return `
      <div class="event-card ${isPast ? 'past-event' : 'upcoming-event'}" data-date="${e.date}">
        ${e.image
        ? `<div class="event-img-wrap">
               <img src="${esc(e.image)}" alt="${esc(e.title)}" loading="lazy">
               <span class="event-status-badge ${isPast ? 'badge-past' : 'badge-upcoming'}">
                 ${isPast ? 'Past' : 'Upcoming'}
               </span>
             </div>`
        : `<div class="event-img-placeholder">
               <span>📅</span>
               <span class="event-status-badge ${isPast ? 'badge-past' : 'badge-upcoming'}">
                 ${isPast ? 'Past' : 'Upcoming'}
               </span>
             </div>`
      }
        <div class="event-body">
          ${e.category ? `<span class="event-category">${esc(e.category)}</span>` : ''}
          <h3 class="event-title">${esc(e.title)}</h3>
          <p class="event-meta"><span class="event-badge">📅 ${dateStr}</span></p>
          <p class="event-meta">📍 ${esc(e.location || '')}</p>
          <p class="event-desc">${esc(e.description || '')}</p>
          ${mapSrc ? `<iframe class="event-map" src="${mapSrc}" loading="lazy" allowfullscreen></iframe>` : ''}
          ${!isPast && e.joinLink
        ? `<a href="${esc(e.joinLink)}" class="btn-join">Join Event →</a>`
        : isPast
          ? `<span class="btn-past">Event Completed</span>`
          : ''}
        </div>
      </div>`;
  }).join('');
}

/* ── Filters ───────────────────────────────────────────────── */
function initFilters() {
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => renderEvents(btn.dataset.filter));
  });
}

/* ── Date formatter ────────────────────────────────────────── */
function fmtDate(iso) {
  if (!iso) return '';
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', {
    day: 'numeric', month: 'long', year: 'numeric'
  });
}

function esc(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/* ── Init ──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  initFilters();

  // Show loading state
  const grid = document.getElementById('events');
  if (grid) grid.innerHTML = `<p class="events-empty" style="color:#94a3b8;">Loading events…</p>`;

  allEvents = await fetchEvents();
  renderEvents('all');
});