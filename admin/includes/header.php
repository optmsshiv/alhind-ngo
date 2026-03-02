<?php

if (!isset($page_title)) {
$page_title = "NGO Admin Panel";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($page_title) ?></title>

<!-- Google Font -->

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">


<!-- FontAwesome -->

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


<!-- ADMIN CSS (FIXED) -->

<link rel="stylesheet"
href="/css/admin-style.css?v=2">


<link rel="icon"
href="/assets/images/logo.png">

</head>

<body>