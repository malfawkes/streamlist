<?php
// movie.php — detail page: backdrop, overview, rating, trailer,
// add-to-watchlist + mark-as-watched

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

// id arrives via ?id=5 — user-editable URL text → validate
 $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: movies.php');
    exit;
}

 $stmt = $pdo->prepare('SELECT id, title, poster_path, backdrop_path, release_date,
                              overview, rating, is_premium, trailer_key
                       FROM movies WHERE id = :id');
 $stmt->bindValue(':id', $id, PDO::PARAM_INT);
 $stmt->execute();
 $movie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movie) {
    header('Location: movies.php?status=error&message=' . urlencode('Movie not found.'));
    exit;
}

// ── Watched state: is this movie in MY history? ───────────────────
 $stmtW = $pdo->prepare('SELECT 1 FROM watch_history
                        WHERE user_id = :uid AND movie_id = :mid');
 $stmtW->bindValue(':uid', currentUserId(), PDO::PARAM_INT);
 $stmtW->bindValue(':mid', $id, PDO::PARAM_INT);
 $stmtW->execute();
 $isWatched = (bool) $stmtW->fetchColumn();   // 1 → true; false → false

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<?php if ($status === 'error'): ?>
    <p class="message message-error"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<section class="movie-detail">
    <?php if ($movie['backdrop_path']): ?>
        <img class="movie-backdrop"
             src="https://image.tmdb.org/t/p/w1280<?= htmlspecialchars($movie['backdrop_path']) ?>"
             alt="<?= htmlspecialchars($movie['title']) ?>">
    <?php endif; ?>

    <h1><?= htmlspecialchars($movie['title']) ?></h1>

    <p>
        <?= $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : 'TBA' ?>
        <?php if ($movie['rating'] !== null && (float) $movie['rating'] > 0): ?>
            | ★ <?= number_format((float) $movie['rating'], 1) ?> / 10
        <?php endif; ?>
        <?php if ($movie['is_premium']): ?> | <span class="premium-badge">★ PREMIUM</span><?php endif; ?>
        <?php if ($isWatched): ?> | <span class="watched-badge">✓ Watched</span><?php endif; ?>
    </p>

    <p><?= htmlspecialchars($movie['overview'] ?? 'No description available.') ?></p>

    <!-- Add to watchlist (errors return right here) -->
    <form method="post" action="actions/add_to_watch_list.php">

        <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
        <input type="hidden" name="redirect" value="movie.php?id=<?= (int) $movie['id'] ?>">
        <button name="add-to-watchlist" type="submit">+ Add to Watchlist</button>
    </form>

    <!-- Mark as watched / unmark -->
    <form method="post" action="actions/process_watched.php">
        
        <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
        <input type="hidden" name="redirect" value="movie.php?id=<?= (int) $movie['id'] ?>">
        <?php if ($isWatched): ?>
            <input type="hidden" name="currently-watched" value="1">
            <button name="toggle-watched" type="submit">✓ Watched — click to unmark</button>
        <?php else: ?>
            <button name="toggle-watched" type="submit">Mark as Watched</button>
        <?php endif; ?>
    </form>

    <?php if ($movie['trailer_key']): ?>
        <h2>Official Trailer</h2>
        <iframe class="movie-trailer"
                src="https://www.youtube.com/embed/<?= htmlspecialchars($movie['trailer_key']) ?>"
                title="Trailer for <?= htmlspecialchars($movie['title']) ?>"
                frameborder="0"
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
    <?php else: ?>
        <p>No trailer available for this title yet.</p>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>