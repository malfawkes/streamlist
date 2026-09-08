<?php
// actions/process_upgrade.php — simulated checkout + subscription durations

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

//  CANCEL / DOWNGRADE 
if (isset($_POST['downgrade'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET tier = 'free', tier_expires_at = NULL
                               WHERE id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $_SESSION['user_tier'] = 'free';        // cache sync

        header('Location: ../upgrade.php?status=downgraded');
        exit;
    } catch (PDOException $e) {
        error_log('Downgrade error: ' . $e->getMessage());
        header('Location: ../upgrade.php?status=error&message='
             . urlencode('Could not cancel. Please try again.'));
        exit;
    }
}

//  CHECKOUT 
 $months = $_POST['duration'] ?? '';
if (!in_array($months, ['1', '3', '6', '12'], true)) {
    header('Location: ../upgrade.php?status=error&message='
         . urlencode('Choose a subscription duration.'));
    exit;
}
 $months = (int) $months;

// Validate every card field — same validator discipline as registration
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

// No storage, no logging, no session. Only tier + expiry ever persist.
unset($cardName, $cardNumber, $cardExpiry, $cardCvv);

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('SELECT tier, tier_expires_at FROM users WHERE id = :id');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $me = $stmt->fetch(PDO::FETCH_ASSOC);

    $base = ($me['tier'] === 'premium'
             && $me['tier_expires_at'] !== null
             && $me['tier_expires_at'] > $today)
        ? $me['tier_expires_at']        // still active → stack from the end
        : $today;                       // not active → start now

    $expiry = date('Y-m-d', strtotime($base . ' +' . $months . ' months'));

    $stmt = $pdo->prepare("UPDATE users SET tier = 'premium', tier_expires_at = :e
                           WHERE id = :id");
    $stmt->bindValue(':e', $expiry);
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $_SESSION['user_tier'] = 'premium'; // cache sync

    header('Location: ../upgrade.php?status=upgraded');
    exit;

} catch (PDOException $e) {
    error_log('Checkout error: ' . $e->getMessage());
    header('Location: ../upgrade.php?status=error&message='
         . urlencode('Payment could not be processed. Please try again.'));
    exit;
}