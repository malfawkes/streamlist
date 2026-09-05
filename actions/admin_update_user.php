<?php
// actions/admin_update_user.php — tier flips + admin privilege changes

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();          // ⭐ THE privilege-escalation wall: a non-admin forging
                         // this POST dies HERE with 403, before any SQL runs

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['toggle-tier']) && !isset($_POST['toggle-admin'])) {
    header('Location: ../admin.php');
    exit;
}

 $targetId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
if ($targetId === false || $targetId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Invalid user.'));
    exit;
}

// Fixed redirect target — admin actions always land on admin.php.
// No allow-list needed: the destination is never user-supplied.
 $redirect = '../admin.php';

try {
    if (isset($_POST['toggle-tier'])) {
        // Flip via reported current state (watched-toggle pattern)
        $newTier = ($_POST['current-tier'] ?? '') === 'premium' ? 'free' : 'premium';

        $stmt = $pdo->prepare('UPDATE users SET tier = :tier WHERE id = :id');
        $stmt->bindValue(':tier', $newTier);
        $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
        $stmt->execute();

        header('Location: ' . $redirect . '?status=tier');
        exit;
    }

    // ── toggle-admin ──
    // ⭐ Lockout guard, server-side (the UI hides the button; this ENFORCES it —
    // defense at the layer that matters, since UI can be bypassed)
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