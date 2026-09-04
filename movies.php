<?php

require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/database/db.php';

$movies = $pdo
    ->query('SELECT id, title, poster_path, release_date, is_premium
             FROM movies
             ORDER BY release_date DESC')
    ->fetchAll(PDO::FETCH_ASSOC);

$status  = $_GET['status']  ?? null;
$message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<h1>Trending Now</h1>
<p>Browse what's new. Add anything to your watchlist.</p>

<?php if ($status === 'error'): ?>
    <p style="color: red;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<section>
    <div class="trending-cards">
        <?php foreach ($movies as $movie): ?>
            <div class="card">
                <div class="card-image">
                    <img src="assets/img/canvas.png" alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
                </div>
                <p><?= htmlspecialchars($movie['title']) ?></p>
                <p><?= date('Y', strtotime($movie['release_date'])) ?></p>
                <?php if ($movie['is_premium']): ?>
                    <p class="premium-badge">★ PREMIUM</p>
                <?php endif; ?>

                <!-- NEW: add form per card -->
                <form method="post" action="actions/add_to_watch_list.php">
                    <!-- hidden input: data the user never types, rides along on submit -->
                    <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                    <button name="add-to-watchlist" type="submit">+ Watchlist</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>