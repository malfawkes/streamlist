<?php
// search.php — search by title OR description. GET-based, bookmarkable.

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

 $term = trim($_GET['q'] ?? '');

 $movies = [];

if ($term !== '') {
    // Two placeholder names for one value (MySQL driver quirk)
    $stmt = $pdo->prepare(
        'SELECT id, title, poster_path, release_date, rating, is_premium
         FROM movies
         WHERE (title LIKE :term OR overview LIKE :term2)
         ORDER BY release_date DESC
         LIMIT 20'
    );
    $stmt->bindValue(':term',  '%' . $term . '%');
    $stmt->bindValue(':term2', '%' . $term . '%');
    $stmt->execute();
    $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<div class="wrap search-page">

    <!-- Hero: the search experience itself IS the page -->
    <div class="search-hero">
        <h1>Search</h1>
        <p class="search-hero-sub">By title or description press Enter to search.</p>

        <form method="get" action="search.php" class="search-bar">
            <input type="text" name="q"
                   value="<?= htmlspecialchars($term) ?>"
                   placeholder="Search movies by title or description…"
                   autofocus>
            <button type="submit">Search</button>
        </form>

        <!-- Landing state only: suggested terms = plain links, zero backend.
             Each is just search.php?q=word — the same URL a real search builds -->
        <?php if ($term === ''): ?>
            <div class="search-suggestions">
                <span>Try:</span>
                <a href="search.php?q=space">space</a>
                <a href="search.php?q=love">love</a>
                <a href="search.php?q=war">war</a>
                <a href="search.php?q=night">night</a>
                <a href="search.php?q=city">city</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($term !== '' && empty($movies)): ?>
        <div class="search-empty">
            <p class="search-empty-icon">🔍</p>
            <p>No movies found for "<?= htmlspecialchars($term) ?>".</p>
            <p class="search-empty-hint">Try a shorter word, or check the spelling.</p>
        </div>
    <?php elseif (!empty($movies)): ?>
        <p class="search-results-count">
            <?= count($movies) ?> result<?= count($movies) === 1 ? '' : 's' ?>
            for "<?= htmlspecialchars($term) ?>"
        </p>

        <div class="trending-cards">
            <?php foreach ($movies as $movie): ?>
                <div class="card">
                    <div class="card-image">
                        <?php if ($movie['poster_path']): ?>
                            <img src="https://image.tmdb.org/t/p/w342<?= htmlspecialchars($movie['poster_path']) ?>"
                                 alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
                        <?php else: ?>
                            <div class="poster-fallback">
                                <span>🎬</span>
                                <p><?= htmlspecialchars($movie['title']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p><a href="movie.php?id=<?= (int) $movie['id'] ?>"><?= htmlspecialchars($movie['title']) ?></a></p>
                    <p>
                        <?= $movie['release_date'] ? date('Y', strtotime($movie['release_date'])) : 'TBA' ?>
                        <?php if ($movie['rating'] !== null && (float) $movie['rating'] > 0): ?>
                            | ★ <?= number_format((float) $movie['rating'], 1) ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($movie['is_premium']): ?>
                        <p class="premium-badge">★ PREMIUM</p>
                    <?php endif; ?>

                    <form method="post" action="actions/add_to_watch_list.php">
                        <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                        <input type="hidden" name="redirect" value="search.php?q=<?= urlencode($term) ?>">
                        <button name="add-to-watchlist" type="submit">+ Watchlist</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>