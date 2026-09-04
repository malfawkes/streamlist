<?php
// actions/add_to_watch_list.php — logged-in guard → validate ID → gate → INSERT

require_once __DIR__ . '/../includes/auth.php';

// Bouncer: adding requires a session (can't check a button that never arrived)
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

// movie_id comes from a form hidden input → USER-EDITABLE → validate it!
// Arrives as a string "3" → filter_var validates AND converts to int
 $movieId = filter_var($_POST['movie_id'] ?? '', FILTER_VALIDATE_INT);

if ($movieId === false || $movieId < 1) {
    header('Location: ../movies.php?status=error&message=' . urlencode('Invalid movie.'));
    exit;
}

// ⭐ user_id comes from the SESSION — server-trusted, never from the form.
// (If it came from a form field, anyone could edit it and fill OTHER
// people's watchlists. Same rule for DELETE below.)
 $userId = currentUserId();

try {
    // 1. Does the movie exist? (also fetch is_premium for tier gating)
    $stmt = $pdo->prepare('SELECT id, is_premium FROM movies WHERE id = :id');
    $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
    $stmt->execute();
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$movie) {
        header('Location: ../movies.php?status=error&message=' . urlencode('Movie not found.'));
        exit;
    }

    // 2. Tier gate: free user + premium movie → blocked
    if ($_SESSION['user_tier'] !== 'premium' && $movie['is_premium']) {
        header('Location: ../movies.php?status=error&message='
             . urlencode('That title is for StreamList Plus members. Upgrade to add it.'));
        exit;
    }

    // 3. Free-tier cap (your FREE_TIER_WATCHLIST_LIMIT constant, finally used!)
    if ($_SESSION['user_tier'] !== 'premium') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM watch_list WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() >= FREE_TIER_WATCHLIST_LIMIT) {
            header('Location: ../movies.php?status=error&message='
                 . urlencode('Free watchlist is full (20 titles). Upgrade for unlimited.'));
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
    header('Location: ../movies.php?status=error&message=' . urlencode($friendly));
    exit;
}