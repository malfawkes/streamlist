<?php
// admin.php — admin dashboard: stats, user management (subscriptions),
// movie management (search + delete). No admin-granting UI (SQL only).

require_once 'includes/auth.php';
requireAdmin();                 // authentication + authorization

require_once 'database/db.php';

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

// ── Stats ────────────────────────────────────────────────────────
 $statUsers     = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
 $statPremium   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE tier = 'premium'")->fetchColumn();
 $statAdmins    = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
 $statMovies    = (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn();
 $statTrailers  = (int) $pdo->query('SELECT COUNT(*) FROM movies WHERE trailer_key IS NOT NULL')->fetchColumn();
 $statWatchlist = (int) $pdo->query('SELECT COUNT(*) FROM watch_list')->fetchColumn();
 $statHistory   = (int) $pdo->query('SELECT COUNT(*) FROM watch_history')->fetchColumn();

// ── Users + watchlist counts + subscription expiry ──────────────
// tier_expires_at in BOTH the SELECT and the GROUP BY (ONLY_FULL_GROUP_BY rule)
 $users = $pdo->query(
    'SELECT u.id, u.name, u.email, u.tier, u.is_admin, u.tier_expires_at, u.created_at,
            COUNT(wl.id) AS watchlist_count
     FROM users u
     LEFT JOIN watch_list wl ON wl.user_id = u.id
     GROUP BY u.id, u.name, u.email, u.tier, u.is_admin, u.tier_expires_at, u.created_at
     ORDER BY u.created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

// ── Movie lookup (search + delete) ──────────────────────────────
 $movieTerm    = trim($_GET['movie_search'] ?? '');
 $movieResults = [];
if ($movieTerm !== '') {
    $stmt = $pdo->prepare('SELECT id, title, release_date, is_premium
                           FROM movies
                           WHERE title LIKE :term
                           ORDER BY release_date DESC
                           LIMIT 10');
    $stmt->bindValue(':term', '%' . $movieTerm . '%');
    $stmt->execute();
    $movieResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<main>
<div class="wrap">

    <h1>Admin</h1>

    <?php if ($status === 'sub'): ?>
        <p class="message message-success">Subscription updated.</p>
    <?php elseif ($status === 'user-deleted'): ?>
        <p class="message message-success">User deleted — <?= (int) ($_GET['wl'] ?? 0) ?>
            watchlist entries removed automatically (ON DELETE CASCADE).</p>
    <?php elseif ($status === 'movie-deleted'): ?>
        <p class="message message-success">Movie deleted — <?= (int) ($_GET['wl'] ?? 0) ?>
            watchlist entries removed automatically (ON DELETE CASCADE).</p>
    <?php elseif ($status === 'admin'): ?>
        <p class="message message-success">Admin privileges updated.</p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- ── Stats ── -->
    <div class="admin-stats">
        <div class="stat-card"><p class="stat-value"><?= $statUsers ?></p><p class="stat-label">Users</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statPremium ?></p><p class="stat-label">Premium users</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statAdmins ?></p><p class="stat-label">Admins</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statMovies ?></p><p class="stat-label">Movies</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statTrailers ?></p><p class="stat-label">With trailers</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statWatchlist ?></p><p class="stat-label">Watchlist rows</p></div>
        <div class="stat-card"><p class="stat-value"><?= $statHistory ?></p><p class="stat-label">Watch history rows</p></div>
    </div>

    <!-- ═══ USERS TABLE — subscriptions live HERE ═══ -->
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
                    <!-- ── SUBSCRIPTION MANAGER: on USER rows ── -->
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

    <!-- ═══ MOVIES TABLE — search + delete ONLY (no subscription forms here!) ═══ -->
    <h2>Movies</h2>
    <form method="get" action="admin.php" class="admin-movie-search">
        <input type="text" name="movie_search"
               value="<?= htmlspecialchars($movieTerm) ?>"
               placeholder="Find a movie to delete…">
        <button type="submit">Find</button>
    </form>

    <?php if ($movieTerm !== '' && empty($movieResults)): ?>
        <p>No movies match "<?= htmlspecialchars($movieTerm) ?>".</p>
    <?php elseif (!empty($movieResults)): ?>
        <table class="admin-table">
            <tr><th>Title</th><th>Released</th><th>Tier</th><th>Action</th></tr>
            <?php foreach ($movieResults as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['title']) ?></td>
                    <td><?= $m['release_date'] ? date('Y', strtotime($m['release_date'])) : 'TBA' ?></td>
                    <td><?= $m['is_premium'] ? '★ Premium' : 'Free' ?></td>
                    <td>
                        <form method="post" action="actions/admin_delete_movie.php"
                              onsubmit="return confirm('Delete this movie? All watchlist entries pointing at it are removed too.');">
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