<?php
// movies.php — TRENDING: protected page, paginated movie grid

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

// ── Pagination: which page are we on? ─────────────────────────
 $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if ($page === false || $page < 1) { $page = 1; }

 $perPage = 24;                     // cards per page
 $offset  = ($page - 1) * $perPage; // page 1 → skip 0, page 2 → skip 24 …

 $totalMovies = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
 $totalPages  = (int) ceil($totalMovies / $perPage);

 $stmt = $pdo->prepare('SELECT id, title, poster_path, release_date, rating, is_premium
                       FROM movies
                       ORDER BY release_date DESC
                       LIMIT :limit OFFSET :offset');
 $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
 $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
 $stmt->execute();
 $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <img src="<?= $movie['poster_path']
                            ? 'https://image.tmdb.org/t/p/w342' . htmlspecialchars($movie['poster_path'])
                            : 'assets/img/canvas.png' ?>"
                         alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
                </div>
                <p><a href="movie.php?id=<?= (int) $movie['id'] ?>"><?= htmlspecialchars($movie['title']) ?></a></p>
                <p>
                    <?= date('Y', strtotime($movie['release_date'] ?: 'now')) ?>
                    <?php if ($movie['rating'] !== null): ?>
                        | ★ <?= number_format((float) $movie['rating'], 1) ?>
                    <?php endif; ?>
                </p>
                <?php if ($movie['is_premium']): ?>
                    <p class="premium-badge">★ PREMIUM</p>
                <?php endif; ?>

                <form method="post" action="actions/add_to_watch_list.php">
                    <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                    <button name="add-to-watchlist" type="submit">+ Watchlist</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="movies.php?page=<?= $page - 1 ?>">&larr; Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="movies.php?page=<?= $page + 1 ?>">Next &rarr;</a>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>