<?php
// includes/header.php — now also boots the session for every page
require_once __DIR__ . '/auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StreamList — Find Your Next Favorite Film</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <div class="wrap nav">
        <!-- Logo: links back to homepage -->
        <a href="index.php" class="logo">
            <img src="assets/logo/g4.png" alt="Streamlist icon">
            <span class="logo-text">StreamList</span>
        </a>

        <!-- Main navigation links -->
        <nav class="nav-links">
            <a href="#trending">Trending</a>
            <a href="#genres">Genres</a>
            <a href="#pricing">Pricing</a>
            <a href="#about">About</a>
        </nav>

        <!-- Login / Sign up -->
        <div class="nav-auth">
            <?php if (isLoggedIn()): ?>
                <span class="login">Hi, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <a href="watchlist.php" class="login">My Watchlist</a>
                <a href="logout.php" class="btn btn-primary">Log Out</a>
            <?php else: ?>
                <a href="login.php" class="login">Log In</a>
                <a href="register.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>