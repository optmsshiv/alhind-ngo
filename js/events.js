/**
 * events.js — Dynamic Events Page for AL Hind Educational and Charitable Trust
 * Reads events from localStorage (managed via admin panel). Falls back to defaults.
 */

const EVENTS_STORAGE_KEY = "alhind_events";

const DEFAULT_EVENTS = [
  {
    id: 1,
    title: "Free Medical Camp",
    date: "2026-03-10",
    location: "Madhepura, Bihar",
    description: "Health checkup camp organised for rural families.",
    image: "images/event1.jpg",
    mapQuery: "Madhepura,Bihar",
    joinLink: "join.html",
    category: "Health",
  },
  {
    id: 2,
    title: "Winter Blanket Distribution",
    date: "2025-12-20",
    location: "Saharsa, Bihar",
    description: "Helping needy families during winter.",
    image: "images/event2.jpg",
    mapQuery: "Saharsa,Bihar",
    joinLink: "join.html",
    category: "Welfare",
  },
];

function getEvents() {
  try {
    const raw = localStorage.getItem(EVENTS_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : null;
    return Array.isArray(parsed) && parsed.length ? parsed : DEFAULT_EVENTS;
  } catch {
    return DEFAULT_EVENTS;
  }
}

/* ─── Render ──────────────────────────────────────────────── */
let allEvents = [];
const today = new Date().toISOString().split("T")[0];

function renderEvents(filter = "all") {
  const grid = document.getElementById("events");
  if (!grid) return;

  const events = allEvents.filter(e => {
    if (filter === "upcoming") return e.date >= today;
    if (filter === "past")     return e.date < today;
    return true;
  });

  // Update active filter button
  document.querySelectorAll(".filter-btn").forEach(btn => {
    btn.classList.toggle("active", btn.dataset.filter === filter);
  });

  if (!events.length) {
    grid.innerHTML = `<p class="events-empty">No ${filter !== "all" ? filter : ""} events found.</p>`;
    return;
  }

  grid.innerHTML = events.map(e => {
    const isPast   = e.date < today;
    const dateStr  = formatDate(e.date);
    const mapSrc   = e.mapQuery
      ? `https://www.google.com/maps?q=${encodeURIComponent(e.mapQuery)}&output=embed`
      : "";

    return `
      <div class="event-card ${isPast ? "past-event" : "upcoming-event"}" data-date="${e.date}">
        ${e.image
          ? `<div class="event-img-wrap">
               <img src="${e.image}" alt="${esc(e.title)}" loading="lazy">
               <span class="event-status-badge ${isPast ? "badge-past" : "badge-upcoming"}">
                 ${isPast ? "Past" : "Upcoming"}
               </span>
             </div>`
          : `<div class="event-img-placeholder">
               <span>📅</span>
               <span class="event-status-badge ${isPast ? "badge-past" : "badge-upcoming"}">
                 ${isPast ? "Past" : "Upcoming"}
               </span>
             </div>`
        }
        <div class="event-body">
          ${e.category ? `<span class="event-category">${esc(e.category)}</span>` : ""}
          <h3 class="event-title">${esc(e.title)}</h3>
          <p class="event-meta"><span class="event-badge">📅 ${dateStr}</span></p>
          <p class="event-meta">📍 ${esc(e.location)}</p>
          <p class="event-desc">${esc(e.description)}</p>
          ${mapSrc ? `<iframe class="event-map" src="${mapSrc}" loading="lazy" allowfullscreen></iframe>` : ""}
          ${!isPast && e.joinLink
            ? `<a href="${e.joinLink}" class="btn-join">Join Event →</a>`
            : isPast
            ? `<span class="btn-past">Event Completed</span>`
            : ""}
        </div>
      </div>`;
  }).join("");
}

function formatDate(iso) {
  if (!iso) return "";
  const d = new Date(iso + "T00:00:00");
  return d.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
}

function esc(s) {
  return String(s || "")
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

/* ─── Filter buttons ──────────────────────────────────────── */
function initFilters() {
  document.querySelectorAll(".filter-btn").forEach(btn => {
    btn.addEventListener("click", () => renderEvents(btn.dataset.filter));
  });
}

/* ─── Init ────────────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", () => {
  allEvents = getEvents();
  renderEvents("all");
  initFilters();
});
