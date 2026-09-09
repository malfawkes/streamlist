<?php
// actions/admin_toggle_premium.php — flip a movie's premium flag

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['toggle-premium'])) {
    header('Location: ../admin.php');
    exit;
}

 $movieId = filter_var($_POST['movie_id'] ?? '', FILTER_VALIDATE_INT);
if ($movieId === false || $movieId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Invalid movie.'));
    exit;
}

// Preserve the admin's search so they land back on their results
 $search = trim($_POST['movie_search'] ?? '');
 $back   = $search !== '' ? 'admin.php?movie_search=' . urlencode($search) : 'admin.php';

try {
    // read current → flip → write (the watched-toggle pattern)
    $stmt = $pdo->prepare('SELECT is_premium FROM movies WHERE id = :id');
    $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
    $stmt->execute();
    $current = $stmt->fetchColumn();
    if ($current === false) {
        header('Location: ../admin.php?status=error&message=' . urlencode('Movie not found.'));
        exit;
    }

    $stmt = $pdo->prepare('UPDATE movies SET is_premium = :p WHERE id = :id');
    $stmt->bindValue(':p', ((int) $current === 1) ? 0 : 1, PDO::PARAM_INT);
    $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ../' . $back . (strpos($back, '?') !== false ? '&' : '?') . 'status=premium');
    exit;

} catch (PDOException $e) {
    error_log('Toggle premium error: ' . $e->getMessage());
    header('Location: ../admin.php?status=error&message=' . urlencode('Update failed.'));
    exit;
}