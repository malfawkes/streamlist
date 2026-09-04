<?php
// watchlist.php — my watchlist, movies JOINed in

require_once 'includes/auth.php';
requireLogin();                      // protected page

require_once 'database/db.php';

// THE JOIN. watch_list holds only IDs; the titles live in movies.
// JOIN stitches each watch_list row to its matching movies row.
 $userId = currentUserId();

 $stmt = $pdo->prepare(
    'SELECT m.id, m.title, m.poster_path, m.release_date, m.is_premium, wl.created_at
     FROM watch_list wl
     INNER JOIN movies m ON wl.movie_id = m.id
     WHERE wl.user_id = :user_id
     ORDER BY wl.created_at DESC'
);
 $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
 $stmt->execute();
 $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<h1>My Watchlist</h1>

<?php if ($status === 'added'): ?>
    <p style="color: green;">Added to your watchlist.</p>
<?php elseif ($status === 'removed'): ?>
    <p style="color: green;">Removed from your watchlist.</p>
<?php elseif ($status === 'error'): ?>
    <p style="color: red;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if (empty($watchlist)): ?>
    <p>No movies yet — <a href="movies.php">browse trending</a> to start one.</p>
<?php else: ?>
    <div class="trending-cards">
        <?php foreach ($watchlist as $movie): ?>
            <div class="card">
                <div class="card-image">
                    <img src="assets/img/canvas.png"
                         alt="Poster for <?= htmlspecialchars($movie['title']) ?>">
                </div>
                <p><?= htmlspecialchars($movie['title']) ?></p>
                <p><?= date('Y', strtotime($movie['release_date'])) ?></p>
                <?php if ($movie['is_premium']): ?>
                    <p class="premium-badge">★ PREMIUM</p>
                <?php endif; ?>

                <form method="post" action="actions/remove_from_watch_list.php">
                    <input type="hidden" name="movie_id" value="<?= (int) $movie['id'] ?>">
                    <button name="remove-from-watchlist" type="submit">✕ Remove</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>