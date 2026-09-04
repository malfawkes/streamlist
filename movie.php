<?php
// movie.php — ONE movie's detail page: backdrop, overview, rating, trailer, add button

require_once 'includes/auth.php';
requireLogin();                 // protected, same as movies.php

require_once 'database/db.php'; // ← verify this exact path

// id arrives via ?id=5 — URL text, user-editable → validate as always
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
 $movie = $stmt->fetch(PDO::FETCH_ASSOC);      // one row or false

if (!$movie) {
    header('Location: movies.php?status=error&message=' . urlencode('Movie not found.'));
    exit;
}

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<?php if ($status === 'error'): ?>
    <p style="color: red;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<section class="movie-detail">
    <?php if ($movie['backdrop_path']): ?>
        <img class="movie-backdrop"
             src="https://image.tmdb.org/t/p/w1280<?= htmlspecialchars($movie['backdrop_path']) ?>"
             alt="<?= htmlspecialchars($movie['title']) ?>">
    <?php endif; ?>

    <!-- FIXED: heading, not a self-link -->
    <h1><?= htmlspecialchars($movie['title']) ?></h1>

    <p>
        <?= date('Y', strtotime($movie['release_date'] ?: 'now')) ?>
        <?php if ($movie['rating'] !== null): ?>
            | ★ <?= number_format((float) $movie['rating'], 1) ?> / 10
        <?php endif; ?>
        <?php if ($movie['is_premium']): ?> | <span class="premium-badge">★ PREMIUM</span><?php endif; ?>
    </p>

    <p><?= htmlspecialchars($movie['overview'] ?? 'No description available.') ?></p>

    <!-- redirect returns errors RIGHT HERE — your Option A system, reused -->
    <form method="post" action="actions/add_to_watch_list.php">
        <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
        <input type="hidden" name="redirect" value="movie.php?id=<?= (int) $movie['id'] ?>">
        <button name="add-to-watchlist" type="submit">+ Add to Watchlist</button>
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