<?php
// includes/header.php — shared layout: boots session, renders nav on every page
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
            <span class="logo-text">Streamlist</span>
        </a>

        <!-- Main navigation links -->
        <nav class="nav-links">
            <a href="movies.php">Trending</a>
            <a href="genres.php">Genres</a>
            <?php if (isAdmin()): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="upgrade.php">Pricing</a>
            <a href="index.php#about">About</a>
        </nav>

        <?php if (isLoggedIn()): ?>
            <form method="get" action="search.php" class="nav-search">
                <input type="text" name="q" placeholder="Search movies…"
                       value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                <button type="submit">Go</button>
            </form>
        <?php endif; ?>

        
        <div class="nav-auth">
            <?php if (isLoggedIn()): ?>
                <?php if (($_SESSION['user_tier'] ?? 'free') === 'premium'): ?>
                    <span class="plus-badge">★ PLUS</span>
                <?php else: ?>
                    <a href="upgrade.php" class="upgrade-link">Upgrade</a>
                <?php endif; ?>

                <div class="profile-menu">
                    <button type="button" class="profile-btn">
                        <span class="profile-avatar"><?= strtoupper(htmlspecialchars(mb_substr($_SESSION['user_name'], 0, 1))) ?></span>
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <span class="profile-caret">▾</span>
                    </button>

                    <div class="profile-dropdown">
                        <div class="profile-dropdown-header">
                            <p class="profile-dropdown-name"><?= htmlspecialchars($_SESSION['user_name']) ?></p>
                            <p class="profile-dropdown-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                            <p class="profile-dropdown-tier"><?= htmlspecialchars($_SESSION['user_tier'] ?? 'free') ?> member</p>
                        </div>
                        <a href="watchlist.php" class="profile-dropdown-item">My Watchlist</a>
                        <a href="upgrade.php" class="profile-dropdown-item">Subscription</a>
                        <a href="logout.php" class="profile-dropdown-item profile-dropdown-logout">Log Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="login">Log In</a>
                <a href="register.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>