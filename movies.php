<?php
// movies.php — TRENDING: protected, paginated, genre-filterable

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

// ── Pagination: which page? ──────────────────────────────────────
 $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if ($page === false || $page < 1) { $page = 1; }

// ── Genre filter: ?genre=27 (validated like every URL param) ────
 $genreId = filter_var($_GET['genre'] ?? '', FILTER_VALIDATE_INT);
 $hasGenre = ($genreId !== false && $genreId > 0);

// ── Active genre name: ONE direct query — simpler than looping the chips ──
 $activeGenreName = null;
if ($hasGenre) {
    $stmtName = $pdo->prepare('SELECT name FROM genres WHERE id = :gid');
    $stmtName->bindValue(':gid', $genreId, PDO::PARAM_INT);
    $stmtName->execute();
    // fetchColumn() returns the name, or false if the genre doesn't exist
    $activeGenreName = $stmtName->fetchColumn() ?: null;
}

// Guard: a genre ID that isn't in our table (e.g. ?genre=999 typed by hand)
// → same "validate the record exists" pattern as movie.php's not-found redirect
if ($hasGenre && $activeGenreName === null) {
    header('Location: movies.php');
    exit;
}

 $perPage = 24;
 $offset  = ($page - 1) * $perPage;

// ── The movie query: genre-filtered OR full catalog ──────────────
if ($hasGenre) {
    $c = $pdo->prepare('SELECT COUNT(*) FROM movies m
                        INNER JOIN movie_genres mg ON mg.movie_id = m.id
                        WHERE mg.genre_id = :gid AND m.release_date <= CURDATE()');
    $c->bindValue(':gid', $genreId, PDO::PARAM_INT);
    $c->execute();
    $totalMovies = (int) $c->fetchColumn();

    $stmt = $pdo->prepare('SELECT m.id, m.title, m.poster_path, m.release_date, m.rating, m.is_premium
                           FROM movies m
                           INNER JOIN movie_genres mg ON mg.movie_id = m.id
                           WHERE mg.genre_id = :gid AND m.release_date <= CURDATE()
                           ORDER BY m.release_date DESC
                           LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':gid', $genreId, PDO::PARAM_INT);
} else {
    $totalMovies = (int) $pdo->query('SELECT COUNT(*) FROM movies
                                      WHERE release_date <= CURDATE()')->fetchColumn();

    $stmt = $pdo->prepare('SELECT id, title, poster_path, release_date, rating, is_premium
                           FROM movies
                           WHERE release_date <= CURDATE()
                           ORDER BY release_date DESC
                           LIMIT :limit OFFSET :offset');
}

 $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
 $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
 $stmt->execute();
 $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $totalPages = (int) ceil($totalMovies / $perPage);

// ── Genre chips data: every genre + its movie count ──────────────
 $genres = $pdo->query(
    'SELECT g.id, g.name, COUNT(mg.movie_id) AS movie_count
     FROM genres g
     INNER JOIN movie_genres mg ON mg.genre_id = g.id
     GROUP BY g.id, g.name
     ORDER BY g.name'
)->fetchAll(PDO::FETCH_ASSOC);

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<h1><?= $activeGenreName ? htmlspecialchars($activeGenreName) . ' Movies' : 'Trending Now' ?></h1>
<p>Browse what's new. Add anything to your watchlist.</p>

<?php if ($status === 'error'): ?>
    <p style="color: red;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- Genre chips: All + one per genre, active one highlighted -->
<div class="genre-chips">
    <a href="movies.php" class="chip <?= $hasGenre ? '' : 'chip-active' ?>">All</a>
    <?php foreach ($genres as $g): ?>
        <a href="movies.php?genre=<?= (int) $g['id'] ?>"
           class="chip <?= ($hasGenre && $genreId === (int) $g['id']) ? 'chip-active' : '' ?>">
            <?= htmlspecialchars($g['name']) ?> (<?= (int) $g['movie_count'] ?>)
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($movies)): ?>
    <p>No movies in this genre yet.</p>
<?php else: ?>

<section>
    <div class="trending-cards">
        <?php foreach ($movies as $movie): ?>
            <div class="card">
                <div class="card-image">
                    <img src="<?= $movie['poster_path']
                            ? 'https://image.tmdb.org/t/p/w342' . htmlspecialchars($movie['poster_path'])
                            : 'assets/img/canvas.png' ?>"
                         alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
                </div>
                <p><a href="movie.php?id=<?= (int) $movie['id'] ?>"><?= htmlspecialchars($movie['title']) ?></a></p>
                <p>
                    <?= date('Y', strtotime($movie['release_date'] ?: 'now')) ?>
                    <?php if ($movie['rating'] !== null && (float) $movie['rating'] > 0): ?>
                        | ★ <?= number_format((float) $movie['rating'], 1) ?>
                    <?php endif; ?>
                </p>
                <?php if ($movie['is_premium']): ?>
                    <p class="premium-badge">★ PREMIUM</p>
                <?php endif; ?>

                <form method="post" action="actions/add_to_watch_list.php">
                    <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                    <input type="hidden" name="redirect"
                           value="movies.php<?= $hasGenre ? '?genre=' . (int) $genreId : '' ?>">
                    <button name="add-to-watchlist" type="submit">+ Watchlist</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <?php $keep = $hasGenre ? 'genre=' . (int) $genreId . '&' : ''; ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="movies.php?<?= $keep ?>page=<?= $page - 1 ?>">&larr; Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="movies.php?<?= $keep ?>page=<?= $page + 1 ?>">Next &rarr;</a>
        <?php endif; ?>
    </div>
</section>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>