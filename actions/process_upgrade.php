<?php
// actions/process_upgrade.php — checkout creates a PENDING REQUEST
// (admin verifies and activates). Downgrade unchanged.

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../validation.php';

if (!isset($_POST['checkout']) && !isset($_POST['downgrade'])) {
    header('Location: ../upgrade.php');
    exit;
}

$userId = currentUserId();

// ── CANCEL / DOWNGRADE (unchanged) ───────────────────────────────
if (isset($_POST['downgrade'])) {
    try {
        // New detail: cancelling also voids any pending request —
        // you can't cancel your sub and still have one in the queue.
        // Conditional UPDATE (WHERE status='pending') = only touches live requests.
        $stmt = $pdo->prepare("UPDATE subscription_requests
                               SET status = 'rejected', decided_at = NOW(),
                                   admin_note = 'Cancelled by user'
                               WHERE user_id = :id AND status = 'pending'");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("UPDATE users SET tier = 'free', tier_expires_at = NULL
                               WHERE id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $_SESSION['user_tier'] = 'free';

        header('Location: ../upgrade.php?status=downgraded');
        exit;
    } catch (PDOException $e) {
        error_log('Downgrade error: ' . $e->getMessage());
        header('Location: ../upgrade.php?status=error&message='
             . urlencode('Could not cancel. Please try again.'));
        exit;
    }
}

// ── CHECKOUT → pending request ───────────────────────────────────
 $planId = filter_var($_POST['plan_id'] ?? '', FILTER_VALIDATE_INT);
if ($planId === false) {
    header('Location: ../upgrade.php?status=error&message='
         . urlencode('Choose a plan.'));
    exit;
}
 $stmt = $pdo->prepare('SELECT months, price, label FROM plans
                       WHERE id = :id AND is_active = 1');
 $stmt->bindValue(':id', $planId, PDO::PARAM_INT);
 $stmt->execute();
 $plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) {
    header('Location: ../upgrade.php?status=error&message='
         . urlencode('Choose a plan.'));
    exit;
}

// Card validation — UNCHANGED: the "payment" is still validated…
 $errors = [];
 $cardName   = trim($_POST['full_name']  ?? '');
 $cardNumber = $_POST['card_number'] ?? '';
 $cardExpiry = trim($_POST['card_expiry'] ?? '');
 $cardCvv    = trim($_POST['card_cvv']    ?? '');

if ($e = validateRequired($cardName, 'Name on card')) { $errors[] = $e; }
if ($e = validateCardNumber($cardNumber))             { $errors[] = $e; }
if ($e = validateCardExpiry($cardExpiry))             { $errors[] = $e; }
if ($e = validateCvv($cardCvv))                       { $errors[] = $e; }

if (!empty($errors)) {
    header('Location: ../upgrade.php?status=error&message='
         . urlencode(implode(' ', $errors)));
    exit;
}

// …and still DISCARDED — validated, used, destroyed, never stored.
unset($cardName, $cardNumber, $cardExpiry, $cardCvv);

try {
    // One pending request per user — no queue spam
    $check = $pdo->prepare("SELECT id FROM subscription_requests
                            WHERE user_id = :id AND status = 'pending'");
    $check->bindValue(':id', $userId, PDO::PARAM_INT);
    $check->execute();
    if ($check->fetchColumn()) {
        header('Location: ../upgrade.php?status=error&message='
             . urlencode('You already have a request pending review.'));
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO subscription_requests (user_id, plan_id)
                           VALUES (:u, :p)');
    $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':p', $planId, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ../upgrade.php?status=submitted');
    exit;

} catch (PDOException $e) {
    error_log('Checkout request error: ' . $e->getMessage());
    header('Location: ../upgrade.php?status=error&message='
         . urlencode('Could not submit your request. Please try again.'));
    exit;
}