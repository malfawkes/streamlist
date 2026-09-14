<?php
// actions/admin_message_actions.php — mark read/unread + delete messages

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();                 // inbox is admin-only, processors included

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['toggle-read']) && !isset($_POST['delete-message'])) {
    header('Location: ../admin_messages.php');
    exit;
}

$id = filter_var($_POST['message_id'] ?? '', FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    header('Location: ../admin_messages.php?status=error&message=' . urlencode('Invalid message.'));
    exit;
}

try {
    if (isset($_POST['toggle-read'])) {
        $newState = ((int) ($_POST['current-read'] ?? 0) === 1) ? 0 : 1;
        $stmt = $pdo->prepare('UPDATE contact_messages SET is_read = :r WHERE id = :id');
        $stmt->bindValue(':r', $newState, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        header('Location: ../admin_messages.php?status=read');
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    header('Location: ../admin_messages.php?status=deleted');
    exit;

} catch (PDOException $e) {
    error_log('Message action error: ' . $e->getMessage());
    header('Location: ../admin_messages.php?status=error&message='
        . urlencode('Action failed. Please try again.'));
    exit;
}