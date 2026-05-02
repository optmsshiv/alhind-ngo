/**
 * donate.js — Razorpay integration + saves donation records to localStorage
 * for the AL Hind admin panel.
 */

const DON_STORAGE_KEY = "alhind_donations";

/* ── Preset amount buttons ─────────────────────────────────── */
document.querySelectorAll(".donation-presets button").forEach(btn => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".donation-presets button").forEach(b => b.classList.remove("active-preset"));
    btn.classList.add("active-preset");
    document.getElementById("donationAmount").value = btn.dataset.amount;
  });
});

/* ── Scroll helper (sticky bar) ─────────────────────────────── */
function scrollToDonate() {
  document.getElementById("donationForm")?.scrollIntoView({ behavior: "smooth", block: "center" });
}

/* ── Sticky donate bar show/hide ────────────────────────────── */
window.addEventListener("scroll", () => {
  const sticky = document.getElementById("stickyDonate");
  if (!sticky) return;
  sticky.style.display = window.scrollY > 300 ? "flex" : "none";
});

/* ── Close success popup ────────────────────────────────────── */
function closeSuccess() {
  const popup = document.getElementById("successPopup");
  if (popup) popup.style.display = "none";
}

/* ── Save donation record to localStorage ───────────────────── */
function saveDonationRecord(name, email, amount, method, status) {
  try {
    const existing = JSON.parse(localStorage.getItem(DON_STORAGE_KEY) || "[]");
    existing.unshift({
      id:        "don_" + Date.now(),
      name:      name      || "",
      email:     email     || "",
      amount:    parseFloat(amount) || 0,
      method:    method    || "Online",
      status:    status    || "paid",
      createdAt: new Date().toISOString(),
    });
    localStorage.setItem(DON_STORAGE_KEY, JSON.stringify(existing));
  } catch (e) {
    console.warn("Could not save donation record:", e);
  }
}

/* ── Form submit → Razorpay ─────────────────────────────────── */
document.getElementById("donationForm").addEventListener("submit", function (e) {
  e.preventDefault();

  const name   = document.getElementById("donorName").value.trim();
  const email  = document.getElementById("donorEmail").value.trim();
  const amount = parseFloat(document.getElementById("donationAmount").value);

  if (!name || !email || !amount || amount < 1) {
    alert("Please fill in all fields with a valid amount.");
    return;
  }

  // Save as pending first — will update to paid on Razorpay success
  saveDonationRecord(name, email, amount, "Razorpay", "pending");

  const options = {
    key:         "YOUR_RAZORPAY_KEY_ID",   // ← Replace with your Razorpay key
    amount:      amount * 100,             // paise
    currency:    "INR",
    name:        "AL Hind Educational and Charitable Trust",
    description: "Donation",
    image:       "/assets/logo.png",
    prefill: { name, email },
    theme: { color: "#0f766e" },

    handler: function (response) {
      // Update last record status to paid
      try {
        const records = JSON.parse(localStorage.getItem(DON_STORAGE_KEY) || "[]");
        const last = records.find(r => r.name === name && r.email === email && r.status === "pending");
        if (last) {
          last.status     = "paid";
          last.razorpayId = response.razorpay_payment_id;
          localStorage.setItem(DON_STORAGE_KEY, JSON.stringify(records));
        }
      } catch {}

      // Show success popup
      const popup = document.getElementById("successPopup");
      if (popup) popup.style.display = "flex";
    },

    modal: {
      ondismiss: function () {
        // Update pending → cancelled if user closes without paying
        try {
          const records = JSON.parse(localStorage.getItem(DON_STORAGE_KEY) || "[]");
          const last = records.find(r => r.name === name && r.email === email && r.status === "pending");
          if (last) { last.status = "cancelled"; localStorage.setItem(DON_STORAGE_KEY, JSON.stringify(records)); }
        } catch {}
      }
    }
  };

  try {
    const rzp = new Razorpay(options);
    rzp.open();
  } catch (err) {
    // Razorpay not loaded or key invalid — still record as pending
    alert("Payment gateway unavailable. Please try bank transfer or UPI QR.");
  }
});
