<?php
// upgrade.php — StreamList Plus: pricing, benefits, upgrade/downgrade

require_once 'includes/auth.php';
requireLogin();                 // must be logged in to have a tier at all

require_once 'database/db.php';

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

// Sent here by the paywall? Show WHICH movie was blocked — personal upsell
 $blockedTitle = null;
if ($status === 'blocked') {
    $movieId = filter_var($_GET['movie'] ?? '', FILTER_VALIDATE_INT);
    if ($movieId !== false && $movieId > 0) {
        $stmt = $pdo->prepare('SELECT title FROM movies WHERE id = :id');
        $stmt->bindValue(':id', $movieId, PDO::PARAM_INT);
        $stmt->execute();
        $blockedTitle = $stmt->fetchColumn() ?: null;   // ?: null = "or null if false"
    }
}

// Current tier — from the session (which we now keep in sync!)
$isPremium = ($_SESSION['user_tier'] ?? 'free') === 'premium';

include 'includes/header.php';
?>

<section class="wrap">

    <h1 class="streamlist-plus-h1">StreamList Plus</h1>

    <?php if ($status === 'upgraded'): ?>
        <p class="message message-success">You're now a Plus member unlimited watchlists and premium titles unlocked!</p>
    <?php elseif ($status === 'downgraded'): ?>
        <p class="message message-success">You're back on the Free plan.</p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    <?php elseif ($status === 'blocked' && $blockedTitle): ?>
        <p class="message message-error"><strong><?= htmlspecialchars($blockedTitle) ?></strong>
        is a Plus title upgrade below to unlock it.</p>
    <?php endif; ?>

    <!-- Plan comparison -->
    <div class="plan-grid">
        <div class="plan-card">
            <h2>Free</h2>
            <p class="plan-price">$0</p>
            <ul>
                <li>Browse & search all movies</li>
                <li>Watch trailers</li>
                <li>Watchlist up to <?= (int) FREE_TIER_WATCHLIST_LIMIT ?> titles</li>
                <li>Standard titles only</li>
            </ul>
            <?php if (!$isPremium): ?>
                <p class="plan-current">✓ Your current plan</p>
            <?php endif; ?>
        </div>

        <div class="plan-card plan-highlight">
            <h2>StreamList Plus</h2>
            <p class="plan-price">$4.99<span class="plan-per">/month</span></p>
            <ul>
                <li>Everything in Free</li>
                <li><strong>Unlimited</strong> watchlist</li>
                <li>★ Premium titles unlocked</li>
            </ul>

            <?php if ($isPremium): ?>
                <p class="plan-current">✓ Your current plan</p>

                <!-- Downgrade = the "cancel subscription" flow -->
                <form method="post" action="actions/process_upgrade.php"
                    onsubmit="return confirm('Downgrade to Free? Your watchlist stays, but new adds are capped at <?= (int) FREE_TIER_WATCHLIST_LIMIT ?>.');">
                    <button name="downgrade" type="submit">Downgrade to Free</button>
                </form>
            <?php else: ?>
                <!-- Simulated checkout: JS confirm stands in for the payment step -->
                <form method="post" action="actions/process_upgrade.php"
                    onsubmit="return confirm('Simulate payment of $4.99/month?');">
                    <button name="upgrade-now" type="submit" class="btn btn-primary">
                        Upgrade to Plus $4.99/mo
                    </button>
                </form>
                <p class="plan-note">Demo checkout no real payment is processed.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>