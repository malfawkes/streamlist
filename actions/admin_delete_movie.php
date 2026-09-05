<?php
// actions/admin_delete_movie.php — remove a movie; CASCADE cleans references

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['delete-movie'])) {
    header('Location: ../admin.php');
    exit;
}

 $movieId = filter_var($_POST['movie_id'] ?? '', FILTER_VALIDATE_INT);
if ($movieId === false || $movieId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Invalid movie.'));
    exit;
}

try {
    $c = $pdo->prepare('SELECT COUNT(*) FROM watch_list WHERE movie_id = :id');
    $c->bindValue(':id', $movieId, PDO::PARAM_INT);
    $c->execute();
    $removed = (int) $c->fetchColumn();

    $stmt = $pdo->prepare('DELETE FROM movies WHERE id = :id');
    $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
    $stmt->execute();

    // CASCADE wipes: watch_list rows, watch_history rows, movie_genres links
    header('Location: ../admin.php?status=movie-deleted&wl=' . $removed);
    exit;

} catch (PDOException $e) {
    error_log('Admin delete movie error: ' . $e->getMessage());
    header('Location: ../admin.php?status=error&message='
         . urlencode('Delete failed. Please try again.'));
    exit;
}