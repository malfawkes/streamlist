<?php
// search.php — search by title OR description. GET-based, bookmarkable.

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

 $term = trim($_GET['q'] ?? '');

 $movies = [];

if ($term !== '') {
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

<h1 class="search-title">Search</h1>

<?php if ($status === 'error'): ?>
    <p style="color: red;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="get" action="search.php">
    <input type="text" name="q"
           value="<?= htmlspecialchars($term) ?>"
           placeholder="Search movies by title or description…" autofocus>
    <button type="submit">Search</button>
</form>

<?php if ($term !== '' && empty($movies)): ?>
    <p>No movies found for "<?= htmlspecialchars($term) ?>".</p>
<?php elseif (!empty($movies)): ?>
    <p><?= count($movies) ?> result(s) for "<?= htmlspecialchars($term) ?>":</p>

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
<?php else: ?>
    <p>Type a movie title, then press <strong>Enter</strong> or click Search.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>