<?php
// actions/process_upgrade.php — flips tier in BOTH places: DB + session

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../database/db.php';

// Guard: which button was pressed? (two buttons, two keys — your isset pattern)
if (!isset($_POST['upgrade-now']) && !isset($_POST['downgrade'])) {
    header('Location: ../upgrade.php');
    exit;
}

// ⭐ Identity from the SESSION, never from the form —
// same rule as watchlist add/remove. A forged "user_id=1" field changes nothing.
 $userId = currentUserId();

 $newTier = isset($_POST['upgrade-now']) ? 'premium' : 'free';
 $successStatus = ($newTier === 'premium') ? 'upgraded' : 'downgraded';

try {
    // 1. Source of truth: the database row
    $stmt = $pdo->prepare('UPDATE users SET tier = :tier WHERE id = :id');
    $stmt->bindValue(':tier', $newTier);
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    // 2. Cached copy: the session — without this, gates wouldn't notice
    //    until the next login (the cache-consistency lesson, live)
    $_SESSION['user_tier'] = $newTier;

    header('Location: ../upgrade.php?status=' . $successStatus);
    exit;

} catch (PDOException $e) {
    error_log('Tier change error: ' . $e->getMessage());
    header('Location: ../upgrade.php?status=error&message='
         . urlencode('Could not update your plan. Please try again.'));
    exit;
}