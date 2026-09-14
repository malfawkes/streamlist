<?php
// admin_messages.php — the contact inbox. Admin-only.
// ⚠️ SECURITY NOTE: this page renders ARBITRARY USER TEXT to a PRIVILEGED
// user — the most XSS-sensitive page in the app. Every echo escapes.

require_once 'includes/auth.php';
requireAdmin();

require_once 'database/db.php';

$status  = $_GET['status']  ?? null;
$message = $_GET['message'] ?? null;

$unreadCount = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages
                                  WHERE is_read = 0')->fetchColumn();

$messages = $pdo->query('SELECT id, name, email, subject, message, is_read, created_at
                         FROM contact_messages
                         ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

    <main>
        <div class="wrap message-wrap">

            <h1>Messages</h1>
            <p style="margin-bottom: 1rem;"><a href="admin.php">← Back to Admin</a> —
                <strong><?= $unreadCount ?></strong> unread of <?= count($messages) ?></p>

            <?php if ($status === 'read'): ?>
                <p class="message message-success">Message status updated.</p>
            <?php elseif ($status === 'deleted'): ?>
                <p class="message message-success">Message deleted.</p>
            <?php elseif ($status === 'error'): ?>
                <p class="message message-error"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <p>No messages yet.</p>
            <?php else: ?>
                <?php foreach ($messages as $m): ?>
                    <div class="contact-card <?= ((int) $m['is_read'] === 0) ? 'contact-unread' : '' ?>">
                        <div class="contact-head">
                            <div>
                                <strong><?= htmlspecialchars($m['name']) ?></strong>
                                &lt;<?= htmlspecialchars($m['email']) ?>&gt;
                                <?php if ((int) $m['is_read'] === 0): ?>
                                    <span class="unread-dot">● new</span>
                                <?php endif; ?>
                            </div>
                            <small><?= date('M j, Y g:i a', strtotime($m['created_at'])) ?></small>
                        </div>
                        <?php if ($m['subject']): ?>
                            <p class="contact-subject"><?= htmlspecialchars($m['subject']) ?></p>
                        <?php endif; ?>
                        <p class="contact-body"><?= nl2br(htmlspecialchars($m['message'])) ?></p>

                        <div class="contact-actions">
                            <!-- watched-toggle pattern, one more costume -->
                            <form method="post" action="actions/admin_message_actions.php">
                                <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
                                <input type="hidden" name="current-read" value="<?= (int) $m['is_read'] ?>">
                                <button name="toggle-read" type="submit">
                                    <?= ((int) $m['is_read'] === 1) ? 'Mark Unread' : 'Mark Read' ?>
                                </button>
                            </form>
                            <form method="post" action="actions/admin_message_actions.php"
                                  onsubmit="return confirm('Delete this message?');">
                                <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
                                <button name="delete-message" type="submit" class="admin-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>