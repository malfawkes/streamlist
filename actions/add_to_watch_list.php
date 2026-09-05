<?php
// actions/add_to_watch_list.php — logged-in guard → validate ID → gate → INSERT

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../database/db.php';

// Guard: the add button must have been pressed
if (!isset($_POST['add-to-watchlist'])) {
    header('Location: ../movies.php');
    exit;
}

$redirectTo = $_POST['redirect'] ?? 'movies.php';

$allowed = ['movies.php', 'watchlist.php', 'search.php', 'movie.php'];
$isAllowed = false;
foreach ($allowed as $prefix) {
    if (str_starts_with($redirectTo, $prefix)) {
        $isAllowed = true;
        break;
    }
}
if (!$isAllowed) {
    $redirectTo = 'movies.php';
}

// The glue: "&" if the target already has a "?", else "?"
 $sep = (strpos($redirectTo, '?') !== false) ? '&' : '?';

// movie_id comes from a form hidden input → USER-EDITABLE → validate it!
// Arrives as a string "3" → filter_var validates AND converts to int
 $movieId = filter_var($_POST['movie_id'] ?? '', FILTER_VALIDATE_INT);

if ($movieId === false || $movieId < 1) {
    header('Location: ../' . $redirectTo . $sep . 'status=error&message=' . urlencode('Invalid movie.'));
    exit;
}

 $userId = currentUserId();

try {
    // 1. Does the movie exist? (also fetch is_premium for tier gating)
    $stmt = $pdo->prepare('SELECT id, is_premium FROM movies WHERE id = :id');
    $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
    $stmt->execute();
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$movie) {
        header('Location: ../' . $redirectTo . $sep . 'status=error&message=' . urlencode('Movie not found.'));
        exit;
    }


    if ($_SESSION['user_tier'] !== 'premium' && $movie['is_premium']) {
        header('Location: ../upgrade.php?status=blocked&movie=' . (int) $movieId);
        exit;
    }

    // 3. Free-tier cap (your FREE_TIER_WATCHLIST_LIMIT constant, finally used!)
    if ($_SESSION['user_tier'] !== 'premium') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM watch_list WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() >= FREE_TIER_WATCHLIST_LIMIT) {
            header('Location: ../' . $redirectTo . $sep . 'status=error&message=' . urlencode('Free watchlist is full (20 titles). Upgrade for unlimited.'));
            exit;
        }
    }

    // 4. Insert — the FKs you built enforce that both ids are real
    $stmt = $pdo->prepare('INSERT INTO watch_list (user_id, movie_id)
                           VALUES (:user_id, :movie_id)');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':movie_id', $movieId, PDO::PARAM_INT);
    $stmt->execute();

    // Success → land ON the watchlist so they see it landed
    header('Location: ../watchlist.php?status=added');
    exit;

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        // uq_wl_pair fired — your UNIQUE constraint catching the double-add!
        $friendly = 'Already in your watchlist.';
    } else {
        error_log('Watchlist add error: ' . $e->getMessage());
        $friendly = 'Could not add to watchlist. Please try again.';
    }
    header('Location: ../' . $redirectTo . $sep . 'status=error&message=' . urlencode($friendly));
    exit;
}