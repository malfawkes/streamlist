<?php
// admin.php — stats, user subscriptions, PLANS (services), movie add/manage (products)

require_once 'includes/auth.php';
requireAdmin();

require_once 'database/db.php';

$status  = $_GET['status']  ?? null;
$message = $_GET['message'] ?? null;

// Stats
$statUsers     = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$statPremium   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE tier = 'premium'")->fetchColumn();
$statAdmins    = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
$statMovies    = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
$statTrailers  = (int) $pdo->query('SELECT COUNT(*) FROM movies WHERE trailer_key IS NOT NULL')->fetchColumn();
$statWatchlist = (int) $pdo->query('SELECT COUNT(*) FROM watch_list')->fetchColumn();
$statHistory   = (int) $pdo->query('SELECT COUNT(*) FROM watch_history')->fetchColumn();

// Users
$users = $pdo->query(
'SELECT u.id, u.name, u.email, u.tier, u.is_admin, u.tier_expires_at, u.created_at,
        COUNT(wl.id) AS watchlist_count
    FROM users u
    LEFT JOIN watch_list wl ON wl.user_id = u.id
    GROUP BY u.id, u.name, u.email, u.tier, u.is_admin, u.tier_expires_at, u.created_at
    ORDER BY u.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

// Plans (services offered)
$plans = $pdo->query('SELECT id, label, months, price FROM plans
                    WHERE is_active = 1 ORDER BY months')->fetchAll(PDO::FETCH_ASSOC);

// Pending subscription requests (oldest first — a real queue)
$requests = $pdo->query(
    'SELECT sr.id, sr.requested_at, u.name, u.email, u.tier, u.tier_expires_at,
            p.label, p.months, p.price
     FROM subscription_requests sr
     INNER JOIN users u ON u.id = sr.user_id
     INNER JOIN plans p  ON p.id  = sr.plan_id
     WHERE sr.status = "pending"
     ORDER BY sr.requested_at ASC'
)->fetchAll(PDO::FETCH_ASSOC);

// Movie search + results
$movieTerm    = trim($_GET['movie_search'] ?? '');
$movieResults = [];
if ($movieTerm !== '') {
    $stmt = $pdo->prepare('SELECT id, title, release_date, is_premium
                           FROM movies WHERE title LIKE :term
                           ORDER BY release_date DESC LIMIT 10');
    $stmt->bindValue(':term', '%' . $movieTerm . '%');
    $stmt->execute();
    $movieResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<main>
<div class="wrap admin-wrap">

    <h1>Admin</h1>

    <?php if ($status === 'movie-added'): ?>
        <p class="message message-success">Movie added: <?= htmlspecialchars($_GET['title'] ?? '') ?></p>
    <?php elseif ($status === 'plan-added'): ?>
        <p class="message message-success">New plan created it's live on the Pricing page.</p>
    <?php elseif ($status === 'plan-deleted'): ?>
        <p class="message message-success">Plan removed.</p>
    <?php elseif ($status === 'premium'): ?>
        <p class="message message-success">Movie tier updated.</p>
    <?php elseif ($status === 'sub'): ?>
        <p class="message message-success">Subscription updated.</p>
    <?php elseif ($status === 'user-deleted'): ?>
        <p class="message message-success">User deleted <?= (int) ($_GET['wl'] ?? 0) ?></p>
    <?php elseif ($status === 'movie-deleted'): ?>
        <p class="message message-success">Movie deleted <?= (int) ($_GET['wl'] ?? 0) ?></p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
        <?php elseif ($status === 'req-approved'): ?>
    <p class="message message-success">Request approved. Subscription activated.</p>
    <?php elseif ($status === 'req-rejected'): ?>
        <p class="message message-success">Request rejected. The user has been notified.</p>
    <?php endif; ?>

    <!-- Stats -->
    <div class="admin-stats">
        <div class="stat-card"><p class="stat-value"><?= $statUsers ?></p><p class="stat-label">Users</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statPremium ?></p><p class="stat-label">Premium users</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statAdmins ?></p><p class="stat-label">Admins</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statMovies ?></p><p class="stat-label">Movies</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statTrailers ?></p><p class="stat-label">With trailers</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statWatchlist ?></p><p class="stat-label">Watchlist rows</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statHistory ?></p><p class="stat-label">History rows</p></div>
    </div>

    <!-- USERS -->
    <h2>Users</h2>
    <table class="admin-table">
        <tr>
            <th>User</th><th>Email</th><th>Subscription</th><th>Role</th>
            <th>Watchlist</th><th>Joined</th><th>Actions</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <?php $isSelf = ((int) $u['id'] === currentUserId()); ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?><?= $isSelf ? ' (you)' : '' ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?= $u['tier'] === 'premium'
                        ? '★ premium<br><small>until ' . htmlspecialchars($u['tier_expires_at'] ?? '—') . '</small>'
                        : 'free' ?>
                </td>
                <td><?= ((int) $u['is_admin'] === 1) ? '<span class="admin-badge">ADMIN</span>' : 'user' ?></td>
                <td><?= (int) $u['watchlist_count'] ?></td>
                <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <form method="post" action="actions/admin_update_user.php" class="admin-sub-form">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <select name="months">
                            <option value="1">+1 mo</option>
                            <option value="3">+3 mo</option>
                            <option value="6">+6 mo</option>
                            <option value="12">+1 yr</option>
                            <option value="revoke">revoke</option>
                        </select>
                        <button name="set-subscription" type="submit">Apply</button>
                    </form>

                    <?php if (!$isSelf): ?>
                        <form method="post" action="actions/admin_delete_user.php"
                              onsubmit="return confirm('Delete this user? Their watchlist and history are removed too.');">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button name="delete-user" type="submit" class="admin-danger">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

        <!-- ═══ PENDING SUBSCRIPTION REQUESTS ═══ -->
    <h2>Pending Subscription Requests<?= $requests ? ' (' . count($requests) . ')' : '' ?></h2>
    <?php if (empty($requests)): ?>
        <p class="admin-muted">No pending requests. The queue is clear.</p>
    <?php else: ?>
        <table class="admin-table">
            <tr><th>User</th><th>Plan</th><th>Requested</th><th>Actions</th></tr>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($r['name']) ?><br>
                        <small><?= htmlspecialchars($r['email']) ?></small><br>
                        <small>current: <?= $r['tier'] === 'premium'
                            ? 'premium until ' . htmlspecialchars($r['tier_expires_at'] ?? '—')
                            : 'free' ?></small>
                    </td>
                    <td>
                        <?= htmlspecialchars($r['label']) ?> —
                        $<?= number_format((float) $r['price'], 2) ?>
                        <small>(<?= (int) $r['months'] ?> mo)</small>
                    </td>
                    <td><?= date('M j, g:i a', strtotime($r['requested_at'])) ?></td>
                    <td>
                        <form method="post" action="actions/admin_review_request.php"
                              onsubmit="return confirm('Approve this subscription?');">
                            <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                            <button name="approve-request" type="submit">✓ Approve</button>
                        </form>
                        <form method="post" action="actions/admin_review_request.php"
                              class="admin-reject-form">
                            <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                            <input type="text" name="admin_note" placeholder="Reason (optional)"
                                   class="admin-note-input">
                            <button name="reject-request" type="submit" class="admin-danger">✕ Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- SERVICES: subscription plans -->
    <h2>Subscription Plans</h2>
    <table class="admin-table">
        <tr><th>Plan</th><th>Duration</th><th>Price</th><th>Per month</th><th>Action</th></tr>
        <?php foreach ($plans as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['label']) ?></td>
                <td><?= (int) $p['months'] ?> months</td>
                <td>$<?= number_format((float) $p['price'], 2) ?></td>
                <td>$<?= number_format((float) $p['price'] / (int) $p['months'], 2) ?></td>
                <td>
                    <form method="post" action="actions/admin_manage_plans.php"
                          onsubmit="return confirm('Remove this plan from the Pricing page?');">
                        <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
                        <button name="delete-plan" type="submit" class="admin-danger">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <form method="post" action="actions/admin_manage_plans.php" class="admin-plan-form">
        <input type="text" name="label" placeholder="Plan name (e.g. Lifetime)" required>
        <input type="number" name="months" placeholder="Months" min="1" max="120" required>
        <input type="number" name="price" placeholder="Price" min="0.01" step="0.01" required>
        <button name="add-plan" type="submit">+ Add Plan</button>
    </form>

    <!-- PRODUCTS: movies - add + manage -->
    <h2>Add a Movie</h2>
    <form method="post" action="actions/admin_add_movie.php" class="admin-plan-form">
        <input type="number" name="tmdb_id" placeholder="TMDB ID (from themoviedb.org/movie/…)" required>
        <label class="admin-check">
            <input type="checkbox" name="make_premium" value="1"> ★ Premium title
        </label>
        <button name="add-movie" type="submit">Import Movie</button>
    </form>
    <p class="admin-muted">Imports title, poster, backdrop, overview, rating, genres and trailer automatically from TMDB.</p>

    <h2>Manage Movies</h2>
    <form method="get" action="admin.php" class="admin-movie-search">
        <input type="text" name="movie_search"
               value="<?= htmlspecialchars($movieTerm) ?>"
               placeholder="Find a movie…">
        <button type="submit">Find</button>
    </form>

    <?php if ($movieTerm !== '' && empty($movieResults)): ?>
        <p>No movies match "<?= htmlspecialchars($movieTerm) ?>".</p>
    <?php elseif (!empty($movieResults)): ?>
        <table class="admin-table">
            <tr><th>Title</th><th>Released</th><th>Tier</th><th>Actions</th></tr>
            <?php foreach ($movieResults as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['title']) ?></td>
                    <td><?= $m['release_date'] ? date('Y', strtotime($m['release_date'])) : 'TBA' ?></td>
                    <td><?= $m['is_premium'] ? '★ Premium' : 'Free' ?></td>
                    <td>
                        <!-- premium toggle carries the search term (return-to-sender, GET edition) -->
                        <form method="post" action="actions/admin_toggle_premium.php">
                            <input type="hidden" name="movie_id" value="<?= (int) $m['id'] ?>">
                            <input type="hidden" name="movie_search" value="<?= htmlspecialchars($movieTerm) ?>">
                            <button name="toggle-premium" type="submit">
                                <?= $m['is_premium'] ? 'Make Free' : 'Make Premium' ?>
                            </button>
                        </form>

                        <form method="post" action="actions/admin_delete_movie.php"
                              onsubmit="return confirm('Delete this movie? All references are removed too.');">
                            <input type="hidden" name="movie_id" value="<?= (int) $m['id'] ?>">
                            <button name="delete-movie" type="submit" class="admin-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

</div>
</main>

<?php include 'includes/footer.php'; ?>