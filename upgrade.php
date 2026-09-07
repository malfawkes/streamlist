<?php
// upgrade.php — StreamList Plus: plans, simulated checkout, manage/downgrade

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

 $plans = [
    1  => ['label' => '1 Month',  'price' => 4.99,  'per' => 4.99],
    3  => ['label' => '3 Months', 'price' => 12.99, 'per' => 4.33],
    6  => ['label' => '6 Months', 'price' => 23.99, 'per' => 4.00],
    12 => ['label' => '1 Year',   'price' => 39.99, 'per' => 3.33],
];

// Fresh plan status from the DB (never trust the session cache for display)
 $stmt = $pdo->prepare('SELECT tier, tier_expires_at FROM users WHERE id = :id');
 $stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
 $stmt->execute();
 $me = $stmt->fetch(PDO::FETCH_ASSOC);
 $isPremium = ($me['tier'] === 'premium');

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

// Paywall redirect support (?status=blocked&movie=ID)
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

    <?php if ($status === 'upgraded'): ?>
        <p class="message message-success">Payment successful you're a Plus member!</p>
    <?php elseif ($status === 'downgraded'): ?>
        <p class="message message-success">You're back on the Free plan.</p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    <?php elseif ($status === 'blocked' && $blockedTitle): ?>
        <p class="message message-error"><strong><?= htmlspecialchars($blockedTitle) ?></strong>
        is a Plus title subscribe below to unlock it.</p>
    <?php endif; ?>

    <?php if ($isPremium): ?>
        <p class="plan-status">Current plan: <strong>Premium</strong> active until <?= htmlspecialchars($me['tier_expires_at'] ?? '—') ?></p>
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
            <p class="plan-price">from $3.33<span class="plan-per">/month</span></p>
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
            <?php else: ?>

                <!-- ── SIMULATED CHECKOUT ── -->
                <form method="post" action="actions/process_upgrade.php" class="checkout-form">

                    <label for="duration">Duration</label>
                    <select name="duration" id="duration">
                        <?php foreach ($plans as $months => $p): ?>
                            <option value="<?= $months ?>">
                                <?= $p['label'] ?> — $<?= number_format($p['price'], 2) ?>
                                ($<?= number_format($p['per'], 2) ?>/mo)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="full_name">Name on card</label>
                    <input type="text" name="full_name" id="full_name"
                           placeholder="Juan Dela Cruz" required>

                    <label for="card_number">Card number</label>
                    <input type="text" name="card_number" id="card_number"
                           placeholder="4242 4242 4242 4242"
                           autocomplete="off" required>

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
                    <p class="checkout-note">Demo checkout — no real payment.
                    Card details are validated then <strong>immediately discarded</strong>,
                    never stored.</p>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<?php include 'includes/footer.php'; ?>