<?php
// upgrade.php — plans, checkout (→ pending request), status banner, manage/downgrade

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

 $plans = $pdo->query('SELECT id, label, months, price FROM plans
                      WHERE is_active = 1 ORDER BY months')->fetchAll(PDO::FETCH_ASSOC);

 $stmt = $pdo->prepare('SELECT tier, tier_expires_at FROM users WHERE id = :id');
 $stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
 $stmt->execute();
 $me = $stmt->fetch(PDO::FETCH_ASSOC);
 $isPremium = ($me['tier'] === 'premium');

// ── The user's LATEST request — drives the banner + form visibility ──
 $stmt = $pdo->prepare('SELECT sr.status, sr.admin_note, p.label
                       FROM subscription_requests sr
                       INNER JOIN plans p ON p.id = sr.plan_id
                       WHERE sr.user_id = :uid
                       ORDER BY sr.requested_at DESC
                       LIMIT 1');
 $stmt->bindValue(':uid', currentUserId(), PDO::PARAM_INT);
 $stmt->execute();
 $lastRequest = $stmt->fetch(PDO::FETCH_ASSOC);

 $hasPending = ($lastRequest && $lastRequest['status'] === 'pending');

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

// Paywall redirect support
 $blockedTitle = null;
if ($status === 'blocked') {
    $movieId = filter_var($_GET['movie'] ?? '', FILTER_VALIDATE_INT);
    if ($movieId !== false && $movieId > 0) {
        $stmt = $pdo->prepare('SELECT title FROM movies WHERE id = :id');
        $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
        $stmt->execute();
        $blockedTitle = $stmt->fetchColumn() ?: null;
    }
}

include 'includes/header.php';
?>

<main>
<div class="wrap upgrade-wrap">

    <h1 class="streamlist-plus-h1">StreamList Plus</h1>

    <?php if ($status === 'submitted'): ?>
        <p class="message message-success">Request submitted. Awaiting verification.</p>
    <?php elseif ($status === 'downgraded'): ?>
        <p class="message message-success">You're back on the Free plan.</p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    <?php elseif ($status === 'blocked' && $blockedTitle): ?>
        <p class="message message-error"><strong><?= htmlspecialchars($blockedTitle) ?></strong>
        is a Plus title — subscribe below to unlock it.</p>
    <?php endif; ?>

    <?php if ($isPremium): ?>
        <p class="plan-status">★ Current plan: <strong>Premium</strong>
        — active until <?= htmlspecialchars($me['tier_expires_at'] ?? '—') ?></p>
    <?php elseif ($hasPending): ?>
        <p class="plan-status"><?= htmlspecialchars($lastRequest['label']) ?>
        . Payment received, pending admin verification. You'll be upgraded once approved.</p>
    <?php elseif ($lastRequest && $lastRequest['status'] === 'rejected'): ?>
        <p class="message message-error">Your <?= htmlspecialchars($lastRequest['label']) ?>
        request was rejected.<?= $lastRequest['admin_note']
            ? ' Reason: ' . htmlspecialchars($lastRequest['admin_note']) : '' ?>
          You can try again below.</p>
    <?php endif; ?>

    <div class="plan-grid">
        <div class="plan-card">
            <h2>Free</h2>
            <p class="plan-price">$0</p>
            <ul>
                <li>Browse & search all movies</li>
                <li>Watch trailers</li>
                <li>Watchlist up to <?= (int) FREE_TIER_WATCHLIST_LIMIT ?> titles</li>
            </ul>
            <?php if (!$isPremium): ?><p class="plan-current">✓ Current plan</p><?php endif; ?>
        </div>

        <div class="plan-card plan-highlight">
            <h2>StreamList Plus</h2>
            <p class="plan-price">from $<?= $plans
                ? number_format((float) $plans[0]['price'] / (int) $plans[0]['months'], 2)
                : '0.00' ?><span class="plan-per">/month</span></p>
            <ul>
                <li>Everything in Free</li>
                <li><strong>Unlimited</strong> watchlist</li>
                <li>★ Premium titles unlocked</li>
            </ul>

            <?php if ($isPremium): ?>
                <p class="plan-current">✓ Current plan</p>
                <form method="post" action="actions/process_upgrade.php"
                      onsubmit="return confirm('Cancel your subscription?');">
                    <button name="downgrade" type="submit" class="btn-cancel">Cancel subscription</button>
                </form>
            <?php elseif ($hasPending): ?>
                <!-- pending → no form; the banner above explains why -->
                <p class="plan-current">Request pending review…</p>
            <?php else: ?>
                <form method="post" action="actions/process_upgrade.php" class="checkout-form">

                    <label for="duration">Duration</label>
                    <select name="plan_id" id="duration">
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= htmlspecialchars($p['label']) ?> — $<?= number_format((float) $p['price'], 2) ?>
                                ($<?= number_format((float) $p['price'] / (int) $p['months'], 2) ?>/mo)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="full_name">Name on card</label>
                    <input type="text" name="full_name" id="full_name"
                           placeholder="Juan Dela Cruz" required>

                    <label for="card_number">Card number</label>
                    <input type="text" name="card_number" id="card_number"
                           placeholder="4242 4242 4242 4242" autocomplete="off" required>

                    <div class="checkout-row">
                        <div>
                            <label for="card_expiry">Expiry</label>
                            <input type="text" name="card_expiry" id="card_expiry"
                                   placeholder="MM/YY" required>
                        </div>
                        <div>
                            <label for="card_cvv">CVV</label>
                            <input type="text" name="card_cvv" id="card_cvv"
                                   placeholder="123" autocomplete="off" required>
                        </div>
                    </div>

                    <button name="checkout" type="submit" class="btn btn-primary checkout-btn">
                        Subscribe
                    </button>
                    <p class="checkout-note">Demo checkout - no real payment.
                    Card details are validated then <strong>immediately discarded</strong>,
                    never stored. Subscriptions activate after admin verification.</p>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<?php include 'includes/footer.php'; ?>