$subject_user = "Welcome to AL Hind Trust – We’ve Received Your Request";

$mail_user = "
<div style='text-align:center;padding:10px;'>

<img src='cid:ngologo' style='width:90px;height:90px;border-radius:50%;object-fit:cover;margin-bottom:10px;border:3px solid #0f3d2e;'>

</div>

<h2 style='color:#0f3d2e;text-align:center;'>Dear $name,</h2>

<p>
Thank you for reaching out to AL HIND EDUCATIONAL AND CHARITABLE TRUST.
</p>

<p>
We appreciate your interest in:
<strong>$interest</strong>
</p>

<p>
Your message has been received:
Our team will review your message and connect with you within 24–48 hours.
</p>
<p>
In the meantime, feel free to explore our website and learn more about our initiatives and impact.
Together we can bring real change through education and empowerment.
</p>

<hr>

<h4>Your Message:</h4>
<p>$message</p>

<br>

<p>
Warm regards,<br>
<b>AL Hind Team<>/b><br>
<a href='https://alhindtrust.com/'>alhindtrust.com</a><br>
Info:@alhindtrust.com<br>
+91 926 319 0568<br>
Madhepura, Bihar – 852113
</p>
";

$headers_user = "From: info@alhindtrust.com";

mail($email,$subject_user,$mail_user,$headers_user);
