/**
 * post.js — AL Hind Trust
 * Single blog post page — reads ?id= from URL, fetches post, renders it.
 */

const POST_API = 'https://api.alhindtrust.com';
let currentPost = null;

/* ── Helpers ─────────────────────────────────────────────────── */
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function fmtDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-IN', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  });
}

/* ── Fetch post ──────────────────────────────────────────────── */
async function fetchPost(id) {
  const res  = await fetch(`${POST_API}/blog/${id}`);
  const data = await res.json();
  if (!res.ok || !data.data) throw new Error(data.message || 'Not found');
  return data.data;
}

/* ── Render post ─────────────────────────────────────────────── */
function renderPost(p) {
  currentPost = p;

  // Page title & meta
  document.title = `${p.title} | AL Hind Trust`;
  const metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) metaDesc.content = p.excerpt || p.title;

  // Breadcrumb title
  document.getElementById('bl-post-bc-title').textContent = p.title.length > 40
    ? p.title.substring(0, 40) + '…' : p.title;

  // Cover image
  if (p.cover_image) {
    document.getElementById('bl-post-cover-img').src = p.cover_image;
    document.getElementById('bl-post-cover-img').alt = p.title;
    document.getElementById('bl-post-cover').style.display = 'block';
  }

  // Category
  if (p.category) {
    document.getElementById('bl-post-cat').textContent = p.category;
    document.getElementById('bl-post-cat').style.display = 'inline-block';
  }

  // Title, meta
  document.getElementById('bl-post-title').textContent   = p.title;
  document.getElementById('bl-post-author').textContent  = p.author || 'AL Hind Trust';
  document.getElementById('bl-post-date').textContent    = fmtDate(p.published_at || p.created_at);
  document.getElementById('bl-post-views').textContent   = p.views || 0;

  // Content — rendered as HTML (written via rich text editor)
  document.getElementById('bl-post-content').innerHTML = p.content || '';

  // Tags
  if (p.tags) {
    const tags = p.tags.split(',').map(t => t.trim()).filter(Boolean);
    if (tags.length) {
      document.getElementById('bl-post-tags').innerHTML =
        tags.map(t => `<span class="bl-tag">${esc(t)}</span>`).join('');
      document.getElementById('bl-tags-wrap').style.display = 'block';
    }
  }

  // Show post
  document.getElementById('bl-post-wrap').style.display = 'block';
}

/* ── Share ───────────────────────────────────────────────────── */
function sharePost(type) {
  const url   = encodeURIComponent(window.location.href);
  const title = encodeURIComponent(currentPost?.title || document.title);

  const urls = {
    whatsapp: `https://wa.me/?text=${title}%20${url}`,
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}`,
    twitter:  `https://twitter.com/intent/tweet?text=${title}&url=${url}`,
  };

  if (type === 'copy') {
    navigator.clipboard.writeText(window.location.href)
      .then(() => {
        // Brief visual feedback
        const btns = document.querySelectorAll('.bl-share-btn');
        btns.forEach(b => { if (b.querySelector('.fa-link')) { b.style.background = '#0f766e'; b.style.color = '#fff'; setTimeout(() => { b.style.background = ''; b.style.color = ''; }, 1500); } });
      });
    return;
  }

  if (urls[type]) window.open(urls[type], '_blank', 'width=600,height=450');
}

/* ── Init ────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(window.location.search);
  const id     = params.get('id');

  if (!id) {
    document.getElementById('bl-post-loading').style.display = 'none';
    document.getElementById('bl-post-error').style.display   = 'block';
    return;
  }

  try {
    const post = await fetchPost(id);
    document.getElementById('bl-post-loading').style.display = 'none';
    renderPost(post);
  } catch (e) {
    document.getElementById('bl-post-loading').style.display = 'none';
    document.getElementById('bl-post-error').style.display   = 'block';
  }
});
