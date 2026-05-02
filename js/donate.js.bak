// ---------- PRESET BUTTONS ----------
const amountInput = document.getElementById("donationAmount");
const presetButtons = document.querySelectorAll(".donation-presets button");

presetButtons.forEach(btn => {
  btn.addEventListener("click", () => {

    const amount = parseInt(btn.dataset.amount, 10);

    // safety check (future-proof)
    if (isNaN(amount) || amount < 1) {
      console.error("Invalid preset amount:", btn.dataset.amount);
      return;
    }

    // set amount
    amountInput.value = amount;

    // visual active state
    presetButtons.forEach(b => b.classList.remove("active"));
    btn.classList.add("active");

    // trigger input event so validation logic runs
    amountInput.dispatchEvent(new Event("input", { bubbles: true }));
  });
});


// ---------- FORM SUBMIT ----------
document.getElementById("donationForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  // selectors from your form
  const donorNameEl = document.getElementById("donorName");
  const donorEmailEl = document.getElementById("donorEmail");
  const donationAmountEl = document.getElementById("donationAmount");
  const submitBtn = this.querySelector("button[type=submit]");

  const name = donorNameEl.value.trim();
  const email = donorEmailEl.value.trim();
  const amountInr = parseInt(donationAmountEl.value, 10);

  if (!name || !email || !amountInr) {
    alert("Required field missing");
    return;
  }

  // loading state + fade-down entrance already handled by CSS
  submitBtn.innerText = "Processing...";
  submitBtn.disabled = true;

  try {

    if (isNaN(amountInr) || amountInr < 1) {
      alert("Enter a valid donation amount (₹1 or more)");
      submitBtn.disabled = false;
      submitBtn.innerText = "Donate Securely";
      return;
    }


    const amount = amountInr * 100;

    // create razorpay order
    const res = await fetch("/backend/create-order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ amount })
    });

    const order = await res.ok ? await res.json() : null;

    if (!order || !order.id) {
      throw new Error("Order create failed");
    }

    const options = {
      key: "RAZORPAY_KEY_ID",
      amount: order.amount,
      currency: "INR",
      name: "AL Hind Education and Trust Charitable",
      description: "Education Donation",
      order_id: order.id,

      handler: async function (response) {

        // verify payment
        const v = await fetch("backend/verify-payment.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            ...response,
            donor: { name, email, amount: amountInr }
          })
        });

        if (v.ok) {
          window.location.href = "success.html";
        } else {
          alert("Verify failed");
        }
      },

      prefill: { name, email }
    };

    new Razorpay(options).open();

  } catch (err) {

    console.error(err);
    alert("Payment init error");

  } finally {

    submitBtn.innerText = "Donate Securely";
    submitBtn.disabled = false;

  }
});
