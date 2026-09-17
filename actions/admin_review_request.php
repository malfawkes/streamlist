<?php
// actions/admin_review_request.php — approve or reject a subscription request

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();          // forged POSTs die here — the privilege wall

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['approve-request']) && !isset($_POST['reject-request'])) {
    header('Location: ../admin.php');
    exit;
}

$requestId = filter_var($_POST['request_id'] ?? '', FILTER_VALIDATE_INT);
if ($requestId === false || $requestId < 1) {
    header('Location: ../admin.php?status=error&message=' . urlencode('Invalid request.'));
    exit;
}

 $note = trim($_POST['admin_note'] ?? '');
if (strlen($note) > 255) { $note = substr($note, 0, 255); }

try {
    // ⭐ THE ATOMIC CLAIM — this UPDATE only succeeds while the request is
    // still pending. Double-clicks, stale tabs, two admins at once: the
    // FIRST click wins, every later click gets rowCount 0 → "already
    // processed." This is how real state machines prevent double transitions.
    $claim = $pdo->prepare("UPDATE subscription_requests
                            SET status = :status, decided_at = NOW(), admin_note = :note
                            WHERE id = :id AND status = 'pending'");
    $claim->bindValue(':status', isset($_POST['approve-request']) ? 'approved' : 'rejected');
    $claim->bindValue(':note', $note !== '' ? htmlspecialchars($note, ENT_QUOTES, 'UTF-8') : null);
    $claim->bindValue(':id', $requestId, PDO::PARAM_INT);
    $claim->execute();

    if ($claim->rowCount() === 0) {
        header('Location: ../admin.php?status=error&message='
             . urlencode('That request was already processed.'));
        exit;
    }

    // ── APPROVE: apply the subscription (stacking math, as before) ──
    if (isset($_POST['approve-request'])) {
        $stmt = $pdo->prepare('SELECT sr.user_id, p.months
                               FROM subscription_requests sr
                               INNER JOIN plans p ON p.id = sr.plan_id
                               WHERE sr.id = :id');
        $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($req) {
            $stmt = $pdo->prepare('SELECT tier, tier_expires_at FROM users WHERE id = :id');
            $stmt->bindValue(':id', $req['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            $today = date('Y-m-d');
            $base = ($target['tier'] === 'premium'
                     && $target['tier_expires_at'] !== null
                     && $target['tier_expires_at'] > $today)
                ? $target['tier_expires_at'] : $today;
            $expiry = date('Y-m-d', strtotime($base . ' +' . (int) $req['months'] . ' months'));

            $stmt = $pdo->prepare("UPDATE users SET tier = 'premium', tier_expires_at = :e
                                   WHERE id = :id");
            $stmt->bindValue(':e', $expiry);
            $stmt->bindValue(':id', $req['user_id'], PDO::PARAM_INT);
            $stmt->execute();

            // cache sync if the admin approved their OWN request
            if ((int) $req['user_id'] === currentUserId()) {
                $_SESSION['user_tier'] = 'premium';
            }
        }
        header('Location: ../admin.php?status=req-approved');
        exit;
    }

    // ── REJECT: nothing to apply — the note travels to the user's banner ──
    header('Location: ../admin.php?status=req-rejected');
    exit;

} catch (PDOException $e) {
    error_log('Request review error: ' . $e->getMessage());
    header('Location: ../admin.php?status=error&message='
         . urlencode('Review failed. Please try again.'));
    exit;
}