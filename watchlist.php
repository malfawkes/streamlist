<?php
// watchlist.php — my watchlist: movie details (JOIN), watched badges

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

$sort = $_GET['sort'] ?? 'added';
$sortMap = [
    'added'  => 'wl.created_at DESC',
    'date'   => 'm.release_date DESC',
    'name'   => 'm.title ASC',
    'rating' => 'm.rating DESC',
];
if (!array_key_exists($sort, $sortMap)) { $sort = 'added'; }
$orderBy = $sortMap[$sort];

$userId = currentUserId();

$redirectValue = ($sort !== 'added') ? 'watchlist.php?sort=' . $sort : 'watchlist.php';

$stmt = $pdo->prepare(
    "SELECT m.id, m.title, m.poster_path, m.release_date, m.rating, m.is_premium,
            wl.created_at,
            (wh.id IS NOT NULL) AS is_watched
    FROM watch_list wl
    INNER JOIN movies m ON m.id = wl.movie_id
    LEFT JOIN watch_history wh ON wh.movie_id = m.id AND wh.user_id = wl.user_id
    WHERE wl.user_id = :user_id
    ORDER BY {$orderBy}"
);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = count($watchlist);

$status  = $_GET['status']  ?? null;
$message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<section class="wrap watchlist-page">

    <h1 class="-h1">My Watchlist</h1>

    <?php if ($count > 0): ?>
        <?php if ($_SESSION['user_tier'] !== 'premium'): ?>
            <p class="message" style="background: var(--color-bg-alt); color: var(--color-text-muted);><?= $count ?> of <?= (int) FREE_TIER_WATCHLIST_LIMIT ?> titles used
            <a href="upgrade.php">go unlimited with Plus</a></p>
        <?php else: ?>
            <p><?= $count ?> titles</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($status === 'added'): ?>
        <p class="message message-success">Added to your watchlist.</p>
    <?php elseif ($status === 'removed'): ?>
        <p class="message message-success">Removed from your watchlist.</p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-erorr"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- Sort chips — GET links, read-only, no CSRF needed -->
    <div class="sort-bar">
        <span>Sort by:</span>
        <?php
        $labels = [
            'added'  => 'Recently Added',
            'date'   => 'Newest Movies',
            'name'   => 'Name A–Z',
            'rating' => 'Rating',
        ];
        foreach ($labels as $key => $label): ?>
            <a href="watchlist.php?sort=<?= $key ?>"
            class="chip <?= ($sort === $key) ? 'chip-active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($watchlist)): ?>
        <div class="empty-watchlist">
            <h2>Your watchlist is empty</h2>
            <p>Find something worth watching — browse what's trending right now.</p>
            <a href="movies.php" class="btn btn-primary">Browse Trending Movies</a>
            <a href="search.php" class="btn btn-secondary">Search Movies</a>
        </div>
    <?php else: ?>
        <div class="trending-cards">
            <?php foreach ($watchlist as $movie): ?>
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

                    <!-- Watched toggle -->
                    <form method="post" action="actions/process_watched.php">
                        <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectValue) ?>">
                        <?php if ((int) $movie['is_watched'] === 1): ?>
                            <input type="hidden" name="currently-watched" value="1">
                            <button name="toggle-watched" type="submit">✓ Watched - unmark</button>
                        <?php else: ?>
                            <button name="toggle-watched" type="submit">Mark as Watched</button>
                        <?php endif; ?>
                    </form>

                    <!-- Remove -->
                    <form method="post" action="actions/remove_from_watch_list.php"
                        onsubmit="return confirm('Remove this movie from your watchlist?');">
                        <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectValue) ?>">
                        <button name="remove-from-watchlist" type="submit">✕ Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>