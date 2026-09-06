<?php
// movie.php — detail page: cinematic backdrop hero, poster + meta,
// overview, trailer, add-to-watchlist + mark-as-watched

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

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

// Watched state
 $stmtW = $pdo->prepare('SELECT 1 FROM watch_history
                        WHERE user_id = :uid AND movie_id = :mid');
 $stmtW->bindValue(':uid', currentUserId(), PDO::PARAM_INT);
 $stmtW->bindValue(':mid', $id, PDO::PARAM_INT);
 $stmtW->execute();
 $isWatched = (bool) $stmtW->fetchColumn();

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<main>

<?php if ($status === 'error'): ?>
    <div class="wrap">
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    </div>
<?php endif; ?>

<!-- ── CINEMATIC HERO: full-width backdrop + dark scrim ── -->
<section class="movie-hero">
    <?php if ($movie['backdrop_path']): ?>
        <img class="movie-hero-backdrop"
             src="https://image.tmdb.org/t/p/w1280<?= htmlspecialchars($movie['backdrop_path']) ?>"
             alt="<?= htmlspecialchars($movie['title']) ?>">
    <?php endif; ?>
    <!-- the scrim: a gradient from black → transparent, sitting ON TOP of
         the image. Legible text over ANY backdrop (dark or bright) -->
    <div class="movie-hero-scrim"></div>

    <div class="wrap movie-hero-content">
        <!-- Poster: rises out of the hero's bottom edge -->
        <?php if ($movie['poster_path']): ?>
            <img class="movie-poster"
                 src="https://image.tmdb.org/t/p/w342<?= htmlspecialchars($movie['poster_path']) ?>"
                 alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
        <?php endif; ?>

        <div class="movie-hero-info">
            <h1><?= htmlspecialchars($movie['title']) ?></h1>

            <p class="movie-meta">
                <?= $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : 'TBA' ?>
                <?php if ($movie['rating'] !== null && (float) $movie['rating'] > 0): ?>
                    <span class="movie-rating">★ <?= number_format((float) $movie['rating'], 1) ?></span>
                <?php endif; ?>
                <?php if ($movie['is_premium']): ?>
                    <span class="premium-badge">★ PREMIUM</span>
                <?php endif; ?>
                <?php if ($isWatched): ?>
                    <span class="watched-badge">✓ Watched</span>
                <?php endif; ?>
            </p>

            <!-- Action buttons: the page's decision points, styled bold -->
            <form method="post" action="actions/add_to_watch_list.php" class="movie-actions">
                <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                <input type="hidden" name="redirect" value="movie.php?id=<?= (int) $movie['id'] ?>">
                <button name="add-to-watchlist" type="submit" class="movie-btn-primary">
                    + Add to Watchlist
                </button>
            </form>

            <form method="post" action="actions/process_watched.php" class="movie-actions">
                <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                <input type="hidden" name="redirect" value="movie.php?id=<?= (int) $movie['id'] ?>">
                <?php if ($isWatched): ?>
                    <input type="hidden" name="currently-watched" value="1">
                    <button name="toggle-watched" type="submit" class="movie-btn-secondary">
                        ✓ Watched — click to unmark
                    </button>
                <?php else: ?>
                    <button name="toggle-watched" type="submit" class="movie-btn-secondary">
                        Mark as Watched
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</section>

<!-- ── OVERVIEW + TRAILER ── -->
<section class="movie-body">
    <div class="wrap">
        <h2>Overview</h2>
        <p class="movie-overview">
            <?= htmlspecialchars($movie['overview'] ?? 'No description available.') ?>
        </p>

        <?php if ($movie['trailer_key']): ?>
            <h2>Official Trailer</h2>
            <iframe class="movie-trailer"
                    src="https://www.youtube.com/embed/<?= htmlspecialchars($movie['trailer_key']) ?>"
                    title="Trailer for <?= htmlspecialchars($movie['title']) ?>"
                    frameborder="0"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        <?php else: ?>
            <p class="movie-no-trailer">No trailer available for this title yet.</p>
        <?php endif; ?>
    </div>
</section>

</main>

<?php include 'includes/footer.php'; ?>