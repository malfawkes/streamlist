<?php
// actions/process_watched.php — mark/unmark a movie as watched

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../database/db.php';

// Guard: the toggle button was pressed?
if (!isset($_POST['toggle-watched'])) {
    header('Location: ../watchlist.php');
    exit;
}

 $movieId = filter_var($_POST['movie_id'] ?? '', FILTER_VALIDATE_INT);
if ($movieId === false || $movieId < 1) {
    header('Location: ../watchlist.php?status=error&message=' . urlencode('Invalid movie.'));
    exit;
}

 $userId = currentUserId();

// Return-to-sender (the standard allow-list system)
 $redirectTo = $_POST['redirect'] ?? 'watchlist.php';
 $allowed = ['movies.php', 'watchlist.php', 'search.php', 'movie.php'];
 $isAllowed = false;
foreach ($allowed as $prefix) {
    if (str_starts_with($redirectTo, $prefix)) { $isAllowed = true; break; }
}
if (!$isAllowed) { $redirectTo = 'watchlist.php'; }
 $sep = (strpos($redirectTo, '?') !== false) ? '&' : '?';

try {
    // The currently-watched hidden field reports the CURRENT state → flip it
    $wasWatched = isset($_POST['currently-watched']);

    if ($wasWatched) {
        // un-mark: scoped delete (user AND movie — the delete discipline)
        $stmt = $pdo->prepare('DELETE FROM watch_history
                               WHERE user_id = :uid AND movie_id = :mid');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':mid', $movieId, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // mark: movie must exist (FK demands a real movie id)
        $check = $pdo->prepare('SELECT id FROM movies WHERE id = :id');
        $check->bindValue(':id', $movieId, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetchColumn()) {
            header('Location: ../' . $redirectTo . $sep . 'status=error&message='
                 . urlencode('Movie not found.'));
            exit;
        }

        // IGNORE = re-clicking on a stale page can't duplicate the row
        $stmt = $pdo->prepare('INSERT IGNORE INTO watch_history (user_id, movie_id)
                               VALUES (:uid, :mid)');
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':mid', $movieId, PDO::PARAM_INT);
        $stmt->execute();
    }

    header('Location: ../' . $redirectTo);
    exit;

} catch (PDOException $e) {
    error_log('Watched toggle error: ' . $e->getMessage());
    header('Location: ../' . $redirectTo . $sep . 'status=error&message='
         . urlencode('Could not update. Please try again.'));
    exit;
}