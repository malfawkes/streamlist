<?php
// about.php — public: the project story, stack, and honest scope notes.

require_once 'includes/auth.php';   // session boot + header (nav needs it)
// no requireLogin — public by design, like contact.php

require_once 'database/db.php';     // for the live stat card below

// One live stat — real data beats fictional numbers
$statMovies  = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
$statGenres  = (int) $pdo->query('SELECT COUNT(*) FROM genres')->fetchColumn();
$statTrailer = (int) $pdo->query('SELECT COUNT(*) FROM movies
                                  WHERE trailer_key IS NOT NULL')->fetchColumn();

include 'includes/header.php';
?>

    <main>
        <div class="wrap about-page">

            <section class="about-hero">
                <h1>About StreamList</h1>
                <p class="about-tagline">Movie discovery, simplified.</p>
                <p class="about-intro">
                    StreamList is a watchlist-first movie discovery platform: browse what's
                    trending, explore by genre, watch official trailers, and build a
                    watchlist that's actually yours — all in one calm, uncluttered place.
                </p>
            </section>

            <!-- Live numbers from the actual database — the honest version of stats -->
            <div class="about-stats">
                <div class="about-stat">
                    <p class="about-stat-value"><?= number_format($statMovies) ?></p>
                    <p class="about-stat-label">Movies indexed</p>
                </div>
                <div class="about-stat">
                    <p class="about-stat-value"><?= $statGenres ?></p>
                    <p class="about-stat-label">Genres browsable</p>
                </div>
                <div class="about-stat">
                    <p class="about-stat-value"><?= number_format($statTrailer) ?></p>
                    <p class="about-stat-label">Trailers playable</p>
                </div>
            </div>

            <div class="about-section">
                <h2>How it works</h2>
                <div class="about-grid">
                    <div class="about-card">
                        <h3>🔍 Discover</h3>
                        <p>Trending titles, genre browsing, and search across titles and
                            descriptions — powered by a catalog of <?= number_format($statMovies) ?> movies.</p>
                    </div>
                    <div class="about-card">
                        <h3>🎬 Watch</h3>
                        <p>Every movie page carries its official trailer, plus the details
                            that matter: rating, release year, genres, cast, and director.</p>
                    </div>
                    <div class="about-card">
                        <h3>📋 Curate</h3>
                        <p>Build a personal watchlist, mark what you've watched, and sort
                            your library your way. Free accounts get 20 titles;
                            StreamList Plus is unlimited.</p>
                    </div>
                </div>
            </div>

            <div class="about-section">
                <h2>Under the hood</h2>
                <p class="about-tech">
                    Built as a server-rendered PHP application with MySQL (MariaDB) via PDO —
                    prepared statements throughout, session-based authentication, role-based
                    and admin access. Movie data is imported
                    from <a href="https://www.themoviedb.org" target="_blank" rel="noopener">The Movie Database (TMDB)</a>.
                </p>
                <p class="about-tech">
                    A student project - designed, built, and debugged end to end.
                </p>
            </div>

            <div class="about-section">
                <h2>What's real, what's simulated</h2>
                <div class="about-sim">
                    <div>
                        <h3>✔ Fully functional</h3>
                        <ul>
                            <li>Registration, login &amp; sessions</li>
                            <li>Watchlists, watched history &amp; sorting</li>
                            <li>Genre browsing, search &amp; detail pages</li>
                            <li>Trailer playback &amp; TMDB catalog import</li>
                            <li>Admin dashboard &amp; contact inbox</li>
                        </ul>
                    </div>
                    <div>
                        <h3>Simulated for the demo</h3>
                        <ul>
                            <li>Payments — validated (Luhn-checked) then discarded,
                                never stored; no real charges</li>
                            <li>Subscriptions — real tier logic &amp; expiry, but no
                                payment provider behind checkout</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="about-cta">
                <a href="<?= isLoggedIn() ? 'movies.php' : 'register.php' ?>" class="btn btn-primary">
                    <?= isLoggedIn() ? 'Browse Trending Movies' : 'Get Started Free' ?>
                </a>
                <a href="contact.php" class="btn btn-secondary">Contact Us</a>
            </div>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>