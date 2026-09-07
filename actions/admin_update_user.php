<?php
// actions/admin_update_user.php — subscription management + admin privileges

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();     // the privilege wall — forged POSTs die here

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['set-subscription']) && !isset($_POST['toggle-admin'])) {
    header('Location: ../admin.php');
    exit;
}

 $targetId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
if ($targetId === false || $targetId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Invalid user.'));
    exit;
}

 $redirect = '../admin.php';

try {
    // ── SUBSCRIPTION: grant months or revoke ────────────────────
    if (isset($_POST['set-subscription'])) {
        $choice = $_POST['months'] ?? '';

        if ($choice === 'revoke') {
            $stmt = $pdo->prepare("UPDATE users SET tier = 'free', tier_expires_at = NULL
                                   WHERE id = :id");
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
        } elseif (in_array($choice, ['1', '3', '6', '12'], true)) {
            // stack from the user's current end date if still active
            $stmt = $pdo->prepare('SELECT tier, tier_expires_at FROM users WHERE id = :id');
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            $base = ($target['tier'] === 'premium'
                     && $target['tier_expires_at'] !== null
                     && $target['tier_expires_at'] > $today)
                ? $target['tier_expires_at'] : $today;

            $expiry = date('Y-m-d', strtotime($base . ' +' . (int) $choice . ' months'));

            $stmt = $pdo->prepare("UPDATE users SET tier = 'premium', tier_expires_at = :e
                                   WHERE id = :id");
            $stmt->bindValue(':e', $expiry);
            $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            header('Location: ' . $redirect . '?status=error&message='
                 . urlencode('Invalid duration.'));
            exit;
        }

        // If the ADMIN edited THEMSELVES, sync their own session cache
        if ($targetId === currentUserId()) {
            $_SESSION['user_tier'] = ($choice === 'revoke') ? 'free' : 'premium';
        }

        header('Location: ' . $redirect . '?status=sub');
        exit;
    }

    // ── ADMIN PRIVILEGE TOGGLE (unchanged logic) ────────────────
    $currentlyAdmin = ((int) ($_POST['current-admin'] ?? 0) === 1);
    if ($currentlyAdmin && $targetId === currentUserId()) {
        header('Location: ' . $redirect . '?status=error&message='
             . urlencode('You cannot revoke your own admin access.'));
        exit;
    }

    $stmt = $pdo->prepare('UPDATE users SET is_admin = :a WHERE id = :id');
    $stmt->bindValue(':a', $currentlyAdmin ? 0 : 1, PDO::PARAM_INT);
    $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ' . $redirect . '?status=admin');
    exit;

} catch (PDOException $e) {
    error_log('Admin update error: ' . $e->getMessage());
    header('Location: ' . $redirect . '?status=error&message='
         . urlencode('Update failed. Please try again.'));
    exit;
}