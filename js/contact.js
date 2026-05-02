/**
 * contact.js — Contact / Join form handler
 * Saves messages to localStorage so they appear in the admin panel.
 */

const MSG_STORAGE_KEY = "alhind_messages";

/* ── Join fee display ───────────────────────────────────────── */
const JOIN_FEES = {
  volunteer: { label: "₹200 (one-time)" },
  team:      { label: "₹500 (one-time)" },
  partner:   { label: "Contact us for CSR / collaboration rates" },
  general:   { label: null },
};

const joinType = document.getElementById("joinType");
const feeBox   = document.getElementById("joinFeeBox");
const feeAmt   = document.getElementById("feeAmount");

if (joinType) {
  joinType.addEventListener("change", () => {
    const val  = joinType.value;
    const info = JOIN_FEES[val];
    if (info?.label) {
      feeAmt.textContent = info.label;
      feeBox.style.display = "block";
    } else {
      feeBox.style.display = "none";
    }
  });
}

/* ── Save message record to localStorage ───────────────────── */
function saveMsgRecord(name, email, phone, interest, message) {
  try {
    const existing = JSON.parse(localStorage.getItem(MSG_STORAGE_KEY) || "[]");
    existing.unshift({
      id:        "msg_" + Date.now(),
      name:      name     || "",
      email:     email    || "",
      phone:     phone    || "",
      interest:  interest || "general",
      message:   message  || "",
      read:      false,
      createdAt: new Date().toISOString(),
    });
    localStorage.setItem(MSG_STORAGE_KEY, JSON.stringify(existing));
  } catch (e) {
    console.warn("Could not save message:", e);
  }
}

/* ── Form submission ────────────────────────────────────────── */
const form   = document.querySelector(".ngo-form");
const joinBtn = document.getElementById("joinBtn");

if (form) {
  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    if (joinBtn) { joinBtn.disabled = true; joinBtn.textContent = "Sending…"; }

    const name     = form.querySelector("[name='name']")?.value.trim()    || "";
    const email    = form.querySelector("[name='email']")?.value.trim()   || "";
    const phone    = form.querySelector("[name='phone']")?.value.trim()   || "";
    const interest = form.querySelector("[name='interest']")?.value       || "general";
    const message  = form.querySelector("[name='message']")?.value.trim() || "";

    // Save to localStorage for admin panel
    saveMsgRecord(name, email, phone, interest, message);

    // Optional: send via FormSubmit / backend
    // Uncomment and set your endpoint if you have one:
    /*
    try {
      await fetch("https://formsubmit.co/YOUR_EMAIL", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ name, email, phone, interest, message }),
      });
    } catch {}
    */

    // Show success with SweetAlert2 if available, else native alert
    if (typeof Swal !== "undefined") {
      await Swal.fire({
        icon:             "success",
        title:            "Message Sent!",
        text:             "Thank you for reaching out. We'll respond within 24–48 hours.",
        confirmButtonColor: "#0f766e",
        confirmButtonText:  "Close",
      });
    } else {
      alert("Thank you! We'll get back to you within 24–48 hours.");
    }

    form.reset();
    if (feeBox) feeBox.style.display = "none";
    if (joinBtn) { joinBtn.disabled = false; joinBtn.textContent = "Send Message"; }
  });
}
