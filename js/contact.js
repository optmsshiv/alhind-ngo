/**
 * contact.js — AL Hind Trust
 * Multi-step join / contact form with validation.
 */

const CONTACT_API = 'https://api.alhindtrust.com';
const PAID_ROLES  = ['team'];

const JOIN_FEES = {
  volunteer : null,
  team      : '₹500 (one-time)',
  partner   : 'Contact us for rates',
  general   : null,
};

const MSG_PLACEHOLDER = {
  volunteer : 'Tell us about yourself, your skills, and why you want to volunteer with AL Hind Trust…',
  team      : 'Tell us about yourself and your motivation to become a member of AL Hind Trust…',
  partner   : 'Describe your organisation and the nature of collaboration you have in mind…',
  general   : 'Write your query or message here…',
};

let currentStep = 1;

/* ══════════════════════════════════════════════════
   STEP NAVIGATION
══════════════════════════════════════════════════ */
function goStep(target) {
  if (target > currentStep && !validateStep(currentStep)) return;

  document.querySelectorAll('.msf-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('step-' + target).classList.add('active');

  for (let i = 1; i <= 3; i++) {
    const dot    = document.getElementById('dot-' + i);
    const circle = dot.querySelector('.msf-dot');
    dot.classList.remove('active', 'done');
    circle.classList.remove('active', 'done');
    if (i < target)      { dot.classList.add('done');   circle.classList.add('done'); }
    else if (i === target){ dot.classList.add('active'); circle.classList.add('active'); }
  }

  if (target === 3) buildSummary();
  currentStep = target;
  const form = document.getElementById('joinForm');
  if (form) window.scrollTo({ top: form.offsetTop - 110, behavior: 'smooth' });
}

/* ══════════════════════════════════════════════════
   VALIDATION
══════════════════════════════════════════════════ */
function validateStep(step) {
  if (step === 1) {
    if (!v('f-name'))         return shake('f-name',        'Full name is required');
    if (!v('f-fathers-name')) return shake('f-fathers-name','Father\'s / Husband\'s name is required');
    if (!v('f-dob'))          return shake('f-dob',         'Date of birth is required');
    if (!v('f-gender'))       return shake('f-gender',      'Please select gender');
    if (!v('f-phone'))        return shake('f-phone',       'Mobile number is required');
    if (v('f-phone').replace(/\D/g,'').length < 10) return shake('f-phone', 'Enter a valid 10-digit number');
    if (!v('joinType'))       return shake('joinType',      'Please select the role you want to join as');
    return true;
  }
  if (step === 2) {
    if (!v('f-message')) return shake('f-message', 'Please write a short message');
    const role = v('joinType');
    if (role === 'volunteer' || role === 'team') {
      if (!v('f-city'))          return shake('f-city',         'City / District is required');
      if (!v('f-qualification')) return shake('f-qualification','Please select your qualification');
      const aoi = [...document.querySelectorAll('input[name="area_of_interest[]"]:checked')];
      if (aoi.length === 0) { alert('Please select at least one area of interest.'); return false; }
    }
    if (role === 'partner') {
      if (!v('f-org-name'))    return shake('f-org-name',   'Organisation name is required');
      if (!v('f-org-type'))    return shake('f-org-type',   'Please select organisation type');
      if (!v('f-collab-type')) return shake('f-collab-type','Please select nature of collaboration');
    }
    return true;
  }
  return true;
}

/* ── Get trimmed value by id ── */
function v(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : '';
}

/* ── Shake + highlight invalid field ── */
function shake(id, msg) {
  const el = document.getElementById(id);
  if (!el) { alert(msg); return false; }
  el.focus();
  el.style.borderColor = '#ef4444';
  el.style.boxShadow   = '0 0 0 3px rgba(239,68,68,.15)';
  setTimeout(() => { el.style.borderColor = ''; el.style.boxShadow = ''; }, 2500);
  let err = el.parentNode.querySelector('.field-err');
  if (!err) { err = document.createElement('span'); err.className = 'field-err'; el.parentNode.appendChild(err); }
  err.textContent = msg;
  setTimeout(() => { if (err.parentNode) err.remove(); }, 3000);
  return false;
}

/* ══════════════════════════════════════════════════
   ROLE CHANGE — show/hide fields + fee
══════════════════════════════════════════════════ */
const joinType = document.getElementById('joinType');
const feeBox   = document.getElementById('joinFeeBox');
const feeAmt   = document.getElementById('feeAmount');

function applyRoleUI(role) {
  // Fee box
  const fee = JOIN_FEES[role];
  if (feeBox) feeBox.style.display = fee ? 'block' : 'none';
  if (feeAmt && fee) feeAmt.textContent = fee;

  // Step-2 panels
  ['fields-volunteer','fields-partner','fields-general'].forEach(p => {
    const el = document.getElementById(p);
    if (el) el.style.display = 'none';
  });
  const map = { volunteer:'fields-volunteer', team:'fields-volunteer', partner:'fields-partner', general:'fields-general' };
  const show = document.getElementById(map[role] || 'fields-general');
  if (show) show.style.display = 'block';

  // Step-2 title
  const titles = { volunteer:'Step 2 — Volunteer Details', team:'Step 2 — Member Details', partner:'Step 2 — Organisation Details', general:'Step 2 — Your Message' };
  const t = document.getElementById('step2-title');
  if (t) t.textContent = titles[role] || 'Step 2 — Additional Details';

  // Message placeholder + label
  const msgEl = document.getElementById('f-message');
  if (msgEl) msgEl.placeholder = MSG_PLACEHOLDER[role] || 'Your message…';
  const lbl = document.getElementById('msg-label');
  if (lbl) lbl.innerHTML = (role === 'general')
    ? 'Your Message <span class="req">*</span>'
    : 'Message / Motivation <span class="req">*</span>';
}

if (joinType) joinType.addEventListener('change', () => applyRoleUI(joinType.value));

/* ══════════════════════════════════════════════════
   BUILD SUMMARY (step 3)
══════════════════════════════════════════════════ */
function buildSummary() {
  const role = v('joinType');
  const roleLabels = { volunteer:'Volunteer', team:'Member (₹500)', partner:'Partner / Collaborator', general:'General Inquiry' };
  const aoi = [...document.querySelectorAll('input[name="area_of_interest[]"]:checked')].map(c => c.value).join(', ') || '—';

  const base = [
    ['Full Name',               v('f-name')],
    ["Father's / Husband's",    v('f-fathers-name')],
    ['Date of Birth',           v('f-dob')],
    ['Gender',                  v('f-gender')],
    ['Mobile',                  v('f-phone')],
    ['Email',                   v('f-email') || '—'],
    ['Blood Group',             v('f-blood-group') || '—'],
    ['Aadhaar',                 v('f-aadhaar') ? '••••' + v('f-aadhaar').replace(/\s/g,'').slice(-4) : '—'],
    ['Role',                    roleLabels[role] || role],
  ];

  const extra = (role === 'volunteer' || role === 'team') ? [
    ['City / District',  v('f-city')         || '—'],
    ['PIN Code',         v('f-pin')          || '—'],
    ['Address',          v('f-address')      || '—'],
    ['Qualification',    v('f-qualification')|| '—'],
    ['Occupation',       v('f-occupation')   || '—'],
    ['Area of Interest', aoi],
    ['Availability',     v('f-availability') || '—'],
    ['How Heard',        v('f-how-heard')    || '—'],
  ] : role === 'partner' ? [
    ['Organisation',  v('f-org-name')    || '—'],
    ['Org Type',      v('f-org-type')    || '—'],
    ['Designation',   v('f-designation') || '—'],
    ['Website',       v('f-website')     || '—'],
    ['Collaboration', v('f-collab-type') || '—'],
  ] : [];

  const allItems = [...base, ...extra];
  const grid = allItems.map(([label, val]) =>
    `<div class="sum-item"><div class="sum-label">${label}</div><div class="sum-val">${esc(val)}</div></div>`
  ).join('');

  document.getElementById('summary-content').innerHTML = `
    <div class="summary-grid">${grid}</div>
    <div class="sum-item sum-message">
      <div class="sum-label">Message</div>
      <div class="sum-val" style="font-weight:400;white-space:pre-wrap">${esc(v('f-message'))}</div>
    </div>`;
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ══════════════════════════════════════════════════
   SAFE POST
══════════════════════════════════════════════════ */
async function safePost(url, payload) {
  const res  = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch (_) {
    console.error('Non-JSON:', res.status, text.slice(0,500));
    throw new Error(`Server error ${res.status}: ${res.statusText}`);
  }
  if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
  return data;
}

/* ══════════════════════════════════════════════════
   FORM SUBMIT
══════════════════════════════════════════════════ */
const contactForm = document.getElementById('joinForm');
const joinBtn     = document.getElementById('joinBtn');

if (contactForm) {
  contactForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    const consent = document.getElementById('consent');
    if (!consent || !consent.checked) {
      alert('Please confirm your consent before submitting.'); return;
    }

    const role = v('joinType');
    const aoi  = [...document.querySelectorAll('input[name="area_of_interest[]"]:checked')].map(c => c.value);

    const payload = {
      name             : v('f-name'),
      fathers_name     : v('f-fathers-name'),
      dob              : v('f-dob'),
      gender           : v('f-gender'),
      phone            : v('f-phone'),
      email            : v('f-email'),
      blood_group      : v('f-blood-group'),
      aadhaar          : v('f-aadhaar'),
      interest         : role,
      message          : v('f-message'),
      city             : v('f-city'),
      pin_code         : v('f-pin'),
      address          : v('f-address'),
      qualification    : v('f-qualification'),
      occupation       : v('f-occupation'),
      area_of_interest : aoi.join(', '),
      availability     : v('f-availability'),
      how_heard        : v('f-how-heard'),
      org_name         : v('f-org-name'),
      org_type         : v('f-org-type'),
      designation      : v('f-designation'),
      website          : v('f-website'),
      collab_type      : v('f-collab-type'),
    };

    if (joinBtn) { joinBtn.disabled = true; joinBtn.textContent = 'Submitting…'; }

    try {
      const data = await safePost(`${CONTACT_API}/contact`, payload);
      if (!data.success) throw new Error(data.error || 'Submission failed');

      const isPaid = PAID_ROLES.includes(role);
      const ticket = data.data?.ticket_id || '';
      const name   = v('f-name');

      if (typeof Swal !== 'undefined') {
        await Swal.fire({
          icon              : 'success',
          confirmButtonColor: '#0f766e',
          confirmButtonText : 'Close',
          title             : isPaid ? 'Almost there! 🙏' : 'Application Submitted! 🙏',
          html              : isPaid
            ? `Thank you <strong>${name}</strong>!<br>
               Check your email for the <strong>payment link</strong> to complete registration.<br>
               <small style="color:#64748b">Ticket: <b>${ticket}</b></small>`
            : `Thank you <strong>${name}</strong>!<br>
               Your application is <strong>under review</strong>. We'll respond within 2–3 working days.<br>
               <small style="color:#64748b">Ticket: <b>${ticket}</b></small>`,
        });
      } else {
        alert(isPaid
          ? `Thank you ${name}! Check email for payment link. Ticket: ${ticket}`
          : `Thank you ${name}! Application submitted. Ticket: ${ticket}`);
      }

      contactForm.reset();
      if (feeBox) feeBox.style.display = 'none';
      ['fields-volunteer','fields-partner','fields-general'].forEach(p => {
        const el = document.getElementById(p); if(el) el.style.display = 'none';
      });
      goStep(1);

    } catch (err) {
      console.error('Contact form error:', err.message);
      if (typeof Swal !== 'undefined') {
        Swal.fire({ icon:'error', confirmButtonColor:'#0f766e', title:'Something went wrong', text:'Please try again or contact us at alhindtrust@gmail.com' });
      } else {
        alert('Something went wrong. Please email us at alhindtrust@gmail.com');
      }
    } finally {
      if (joinBtn) { joinBtn.disabled = false; joinBtn.textContent = '✓ Submit Application'; }
    }
  });
}
