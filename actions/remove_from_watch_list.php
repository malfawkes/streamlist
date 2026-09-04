<?php
// actions/remove_from_watch_list.php — DELETE scoped to BOTH ids

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['remove-from-watchlist'])) {
    header('Location: ../watchlist.php');
    exit;
}

 $movieId = filter_var($_POST['movie_id'] ?? '', FILTER_VALIDATE_INT);
if ($movieId === false || $movieId < 1) {
    header('Location: ../watchlist.php?status=error&message=' . urlencode('Invalid movie.'));
    exit;
}

 $userId = currentUserId();   // from session — see the ⭐ note in file #9

try {
    // ⭐⭐ THE critical WHERE clause of this whole phase:
    $stmt = $pdo->prepare('DELETE FROM watch_list
                           WHERE user_id = :user_id AND movie_id = :movie_id');
    //                       ^ yours AND   ^ this movie — BOTH, always
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ../watchlist.php?status=removed');
    exit;

} catch (PDOException $e) {
    error_log('Watchlist remove error: ' . $e->getMessage());
    header('Location: ../watchlist.php?status=error&message='
         . urlencode('Could not remove. Please try again.'));
    exit;
}