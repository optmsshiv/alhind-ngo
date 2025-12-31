// PRESET BUTTONS
document.querySelectorAll(".donation-presets button").forEach(btn => {
  btn.addEventListener("click", () => {
    document.getElementById("donationAmount").value = btn.dataset.amount;
  });
});

// FORM SUBMIT
document.getElementById("donationForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  const name = donorName.value;
  const email = donorEmail.value;
  const amount = donationAmount.value * 100;

  const res = await fetch("backend/create-order.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ amount })
  });

  const order = await res.json();

  const options = {
    key: "RAZORPAY_KEY_ID",
    amount: order.amount,
    currency: "INR",
    name: "AL Education and Trust Charitable",
    description: "Education Donation",
    order_id: order.id,
    handler: function (response) {
      fetch("backend/verify-payment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(response)
      }).then(() => {
        window.location.href = "success.html";
      });
    },
    prefill: { name, email },
    theme: { color: "#0F766E" }
  };

  new Razorpay(options).open();
});
