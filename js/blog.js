/**
 * blog.js — AL Hind Trust
 * Blog listing page — fetches posts, renders grid, handles filters.
 */

const BLOG_API = 'https://api.alhindtrust.com';
let allPosts = [];
let activeCategory = '';

/* ── Fetch ─────────────────────────────────────────────────── */
async function fetchPosts() {
  try {
    const res  = await fetch(`${BLOG_API}/blog`);
    const data = await res.json();
    return data.data || [];
  } catch (e) {
    console.error('Blog fetch failed:', e);
    return [];
  }
}

/* ── Render grid ────────────────────────────────────────────── */
function renderGrid(posts) {
  const grid  = document.getElementById('bl-grid');
  const empty = document.getElementById('bl-empty');

  if (!posts.length) {
    grid.style.display  = 'none';
    empty.style.display = 'block';
    return;
  }

  empty.style.display = 'none';
  grid.style.display  = 'grid';

  grid.innerHTML = posts.map(p => {
    const date    = p.published_at || p.created_at || '';
    const dateStr = date
      ? new Date(date).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' })
      : '';

    return `
      <a href="/blog/post.html?id=${p.id}" class="bl-card">
        ${p.cover_image
          ? `<img src="${esc(p.cover_image)}" alt="${esc(p.title)}" class="bl-card-img" loading="lazy">`
          : `<div class="bl-card-no-img"><i class="fa-solid fa-newspaper"></i></div>`}
        <div class="bl-card-body">
          ${p.category ? `<span class="bl-card-cat">${esc(p.category)}</span>` : ''}
          <h2 class="bl-card-title">${esc(p.title)}</h2>
          ${p.excerpt ? `<p class="bl-card-excerpt">${esc(p.excerpt)}</p>` : ''}
          <div class="bl-card-foot">
            <span class="bl-card-date">
              <i class="fa-solid fa-calendar" style="color:#0f766e;font-size:.7rem"></i>
              ${dateStr}
            </span>
            <span class="bl-read-more">
              Read <i class="fa-solid fa-arrow-right" style="font-size:.65rem"></i>
            </span>
          </div>
        </div>
      </a>`;
  }).join('');
}

/* ── Filter ─────────────────────────────────────────────────── */
function initFilters() {
  document.querySelectorAll('.bl-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeCategory = btn.dataset.cat;
      document.querySelectorAll('.bl-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filtered = activeCategory
        ? allPosts.filter(p => p.category === activeCategory)
        : allPosts;
      renderGrid(filtered);
    });
  });
}

/* ── Escape ─────────────────────────────────────────────────── */
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Init ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  initFilters();

  document.getElementById('bl-loading').style.display = 'flex';
  document.getElementById('bl-loading').style.flexDirection = 'column';
  document.getElementById('bl-loading').style.alignItems = 'center';

  allPosts = await fetchPosts();

  document.getElementById('bl-loading').style.display = 'none';
  renderGrid(allPosts);
});
