<?php
include 'config/db.php';

$res=$conn->query("
SELECT * FROM events
ORDER BY event_date DESC
");
?>

<!DOCTYPE html>
<html>

<head>

<title>Events | AL Hind Trust</title>

<style>

/* ---------- PAGE ---------- */

body{

font-family:sans-serif;
background:#f5f8fc;

}

/* ---------- TITLE ---------- */

.title{

text-align:center;
margin:40px;

font-size:34px;

}

/* ---------- GRID ---------- */

.event-grid{

max-width:1200px;
margin:auto;

display:grid;

grid-template-columns:
repeat(auto-fit,minmax(300px,1fr));

gap:30px;

padding:20px;

}

/* ---------- CARD ---------- */

.event-card{

background:white;

border-radius:14px;

overflow:hidden;

box-shadow:
0 5px 20px rgba(0,0,0,.1);

transition:.4s;

opacity:0;

transform:translateY(40px);

}

.event-card.show{

opacity:1;

transform:translateY(0);

}

/* hover */

.event-card:hover{

transform:translateY(-8px);

}

/* image */

.event-card img{

width:100%;
height:220px;

object-fit:cover;

}

/* content */

.event-content{

padding:18px;

}

.event-date{

color:#0a7cff;

font-weight:bold;

}

.event-location{

font-size:13px;

color:#777;

margin-bottom:10px;

}

.btn{

display:inline-block;

background:#0a7cff;

color:white;

padding:7px 16px;

border-radius:20px;

text-decoration:none;

margin-top:10px;

}

</style>

</head>

<body>

<h2 class="title">

Our Events & Activities

</h2>

<div class="event-grid">

<?php while($row=$res->fetch_assoc()){ ?>

<div class="event-card">

<img src="assets/events/<?=$row['image']?>">

<div class="event-content">

<div class="event-date">

<?= date("d M Y",
strtotime($row['event_date'])) ?>

</div>

<h3>

<?=$row['title']?>

</h3>

<div class="event-location">

📍 <?=$row['location']?>

</div>

<p>

<?=$row['short_desc']?>

</p>

<a class="btn"

href="event-detail.php?id=<?=$row['id']?>">

Read More

</a>

</div>

</div>

<?php } ?>

</div>


<script>

/* scroll animation */

const cards=document.querySelectorAll('.event-card');

const obs=new IntersectionObserver(e=>{

e.forEach(x=>{

if(x.isIntersecting){

x.target.classList.add('show');

}

});

},{threshold:.2});

cards.forEach(c=>obs.observe(c));

</script>

</body>

</html>