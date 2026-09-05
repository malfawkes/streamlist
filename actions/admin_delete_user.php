<?php
// actions/admin_delete_user.php — remove a user; CASCADE cleans their data

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['delete-user'])) {
    header('Location: ../admin.php');
    exit;
}

 $targetId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
if ($targetId === false || $targetId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Invalid user.'));
    exit;
}

// ⭐ Self-delete guard — deleting yourself kills your session mid-request
if ($targetId === currentUserId()) {
    header('Location: ../admin.php?status=error&message='
         . urlencode('You cannot delete your own account.'));
    exit;
}

try {
    // Count FIRST — after the DELETE, the rows are gone and uncountable
    $c = $pdo->prepare('SELECT COUNT(*) FROM watch_list WHERE user_id = :id');
    $c->bindValue(':id', $targetId, PDO::PARAM_INT);
    $c->execute();
    $removed = (int) $c->fetchColumn();

    // The DELETE itself — scoped to ONE user, the delete discipline
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->bindValue(':id', $targetId, PDO::PARAM_INT);
    $stmt->execute();

    // watch_list + watch_history rows vanish via ON DELETE CASCADE —
    // the FK design you built in week one, cashing its check 💰
    header('Location: ../admin.php?status=user-deleted&wl=' . $removed);
    exit;

} catch (PDOException $e) {
    error_log('Admin delete user error: ' . $e->getMessage());
    header('Location: ../admin.php?status=error&message='
         . urlencode('Delete failed. Please try again.'));
    exit;
}