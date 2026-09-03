<?php

require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/database/db.php';

 $movies = $pdo
    ->query('SELECT id, title, poster_path, release_date, is_premium
             FROM movies
             ORDER BY release_date DESC')
    ->fetchAll(PDO::FETCH_ASSOC);

include 'include/header.php';
?>

<h1>Trending Now</h1>
<p>Browse what's new. Add anything to your watchlist.</p>

<section>
    <div class="trending-cards">
        <?php foreach ($movies as $movie): ?>
            <div class="card">
                <div class="card-image">
                    <img src="assets/img/canvas.png"
                        alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
                </div>
                <p><?= htmlspecialchars($movie['title']) ?></p>

                <!-- date('Y') extracts just the year from '2025-06-13' -->
                <p><?= date('Y', strtotime($movie['release_date'])) ?></p>

                <?php if ($movie['is_premium']): ?>
                    <p class="premium-badge">★ PREMIUM</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'include/footer.php'; ?>