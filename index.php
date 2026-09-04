<?php
require_once 'database/db.php';

// Count total movies
$movieCount = $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();

// HERO: the highest-rated movie that has a backdrop image
// (no user input → query() shortcut, same as movies.php)
$hero = $pdo->query(
    'SELECT id, title, overview, rating, backdrop_path
     FROM movies
     WHERE backdrop_path IS NOT NULL AND rating IS NOT NULL
     ORDER BY rating DESC
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);      // fetch() = ONE row — exactly one hero

// TRENDING strip: the 5 newest releases
$trending = $pdo->query(
    'SELECT id, title, poster_path, release_date, rating, is_premium
     FROM movies
     ORDER BY release_date DESC
     LIMIT 5'
)->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>


<section class="hero">
    <div class="wrap hero-wrap">
        <div class="hero-content">

            <h1 class="hero-heading">Find your next favorite film, before it starts.</h1>
            <p class="hero-description">Browse trending titles, watch official trailers, and build a watchlist that's actually yours all in one calm, uncluttered place.</p>

            <div class="hero-buttons">
                <a href="../register.php" class="btn btn-primary get-started">Get Started Free</a>
                <a href="movies.php" class="btn btn-secondary browse-trending">Browse Trending</a>
            </div>

            <div class="hero-stats">
                <div>
                    <p class="hero-stat"><?= $movieCount ?></p>
                    <p class="hero-stat-label">Movies Indexed</p>
                </div>
                <div>
                    <p class="hero-stat">48</p>
                    <p class="hero-stat-label">Genres & Moods</p>
                </div>
                <div>
                    <p class="hero-stat">100%</p>
                    <p class="hero-stat-label">Free Trailer Access</p>
                </div>
            </div>
        </div>

        <div class="hero-image">
            <?php if ($hero): ?>
            <!-- w780 = wider size for backdrops (posters use w342) -->
            <img src="https://image.tmdb.org/t/p/w780<?= htmlspecialchars($hero['backdrop_path']) ?>"
                alt="<?= htmlspecialchars($hero['title']) ?>">
            <div class="hero-movie-info">
                <h2><?= htmlspecialchars($hero['title']) ?></h2>
                <p class="hero-movie-rating">★ <?= number_format((float) $hero['rating'], 1) ?> / 10</p>
                <p><?= htmlspecialchars(mb_substr($hero['overview'] ?? '', 0, 180)) ?>…</p>
            </div>
            <?php else: ?>
                <img src="assets/img/canvas.png" alt="Hero image of a movie theater with a film reel">
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="trending-now">

    <div class="wrap trending-wrap">

        <h4 class="section-heading">TRENDING NOW</h4>
        <p class="section-description">What everyone's watching right now.</p>
        <p class="section-subdescription">Updated daily, pulled straight from what's actually popular right now.</p>

        <div class="trending-cards">
    <?php foreach ($trending as $movie): ?>
        <div class="card">
            <div class="card-image">
                <img src="<?= $movie['poster_path']
                        ? 'https://image.tmdb.org/t/p/w342' . htmlspecialchars($movie['poster_path'])
                        : 'assets/img/canvas.png' ?>"
                     alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
            </div>
            <p><?= htmlspecialchars($movie['title']) ?></p>
            <p>
                <?= date('Y', strtotime($movie['release_date'] ?: 'now')) ?>
                <?php if ($movie['rating'] !== null): ?>
                    | ★ <?= number_format((float) $movie['rating'], 1) ?>
                <?php endif; ?>
            </p>
            <?php if ($movie['is_premium']): ?>
                <p class="premium-badge">★ PREMIUM</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

        <div class="btn-browse-trending">
            <a href="movies.php" class="btn-secondary">See All Trending Titles</a>
        </div>
    </div>
</section>

<section class="about" id="about">

    <div class="wrap about-wrap">
        <div class="about-image-container">
            <img src="assets/img/canvas.png" alt="Image with streamlist logo" class="about-image">
        </div>

        <div class="about-content">
            <h4 class="section-heading">ABOUT STREAMLIST</h4>
            <p class="section-description">Discovery first. <br> Everything else follows.</p>
            <p class="section-subdescription first-para">StreamList brings trending and popular films together in one place, so you can browse, watch trailers, and build a curated watchlist all before the feature even begins.</p>
            <p class="section-subdescription second-para">No clutter, no algorithm mystery box. Just a clear, calm way to find what to watch next, and remember what you already loved</p>

            <div class="about-stats">
                <div>
                    <p class="about-stat-heading">2026</p>
                    <p class="about-stat-description">Founded as a student project</p>
                </div>
                <div>
                    <p class="about-stat-heading">4.8/5</p>
                    <p class="about-stat-description">Average user rating</p>
                </div>
            </div>
        </div>
    </div>

</section>

<section class="why-streamlist">

    <div class="wrap why-wrap">
        <div class="why-content">
            <h4 class="section-heading">WHY STREAMLIST</h4>
            <p class="section-description">Built around how people actually watch.</p>
            <p class="section-subdescription">Three things we obsess over so you don't have to.</p>
        </div>

        <div class="why-cards">
            <div class="card first">
                <img src="assets/img/play.png" alt="Play icon">
                <p class="card-heading">Instant Trailers</p>
                <p class="card-description">Every title comes with its official trailer, one tap away, no digging through search.</p>
            </div>
            <div class="card second">
                <img src="assets/img/play.png" alt="Play icon">
                <p class="card-heading">Curated Watchlists</p>
                <p class="card-description">Save what catches your eye, organize it your way, and pick up right where you left off.</p>
            </div>
            <div class="card third">
                <img src="assets/img/play.png" alt="Play icon">
                <p class="card-heading">Build Your Watchlist</p>
                <p class="card-description">The more you favorite, the sharper your recommnedations get, genuinely, not just in theory.</p>
            </div>
        </div>

    </div>
    
</section>

<section class="user-feedbacks">

    <div class="wrap user-feedbacks-wrap">
        <h4 class="section-heading">USER FEEDBACK</h4>
        <p class="section-description">Here's what viewers are saying</p>

        <div class="user-feedbacks-cards">
            <div class="card first">
                <div class="star-rating">★★★★★ <span>5.0</span></div>
                <p class="feedback-text">"The trailers are perfect for deciding what to watch next. Love the curated lists too!"</p>
                <div class="user-info">
                    <img src="assets/img/bear.png" alt="User avatar">
                    <div class="user-details">
                        <p class="user-name">Alex M.</p>
                        <p class="user-location">New York, USA</p>
                    </div>
                </div>
            </div>
            <div class="card second">
                <div class="star-rating">★★★★★ <span>5.0</span></div>
                <p class="feedback-text">"The trailers are perfect for deciding what to watch next. Love the curated lists too!"</p>
                <div class="user-info">
                    <img src="assets/img/chicken.png" alt="User avatar">
                    <div class="user-details">
                        <p class="user-name">Alex M.</p>
                        <p class="user-location">New York, USA</p>
                    </div>
                </div>
            </div>
            <div class="card third">
                <div class="star-rating">★★★★★ <span>5.0</span></div>
                <p class="feedback-text">"The trailers are perfect for deciding what to watch next. Love the curated lists too!"</p>
                <div class="user-info">
                    <img src="assets/img/meerkat.png" alt="User avatar">
                    <div class="user-details">
                        <p class="user-name">Alex M.</p>
                        <p class="user-location">New York, USA</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>

<section class="cta">
    <div class="wrap cta-wrap">
        <h4 class="section-heading">READY TO START WATCHING</h4>
        <p class="section-description">Join thousands of satisfied users and start your journey today!</p>
        <p class="section-subdescription">Free to browse, free to watch trailers, forever. Upgrade whenever you want more.</p>
        <button class="btn btn-primary get-started">Get Started Free</button>
    </div>
</section>

<?php include 'includes/footer.php'; ?>