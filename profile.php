<?php
// profile.php — account hub: avatar, username change, plan status

require_once 'includes/auth.php';
requireLogin();

require_once 'database/db.php';

 $userId = currentUserId();

// Fresh from the DB — profile pages should never trust the session cache
 $stmt = $pdo->prepare('SELECT name, email, tier, tier_expires_at, avatar_path, created_at
                       FROM users WHERE id = :id');
 $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
 $stmt->execute();
 $me = $stmt->fetch(PDO::FETCH_ASSOC);

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

<main>
<div class="wrap profile-page">

    <h1>Profile</h1>

    <?php if ($status === 'username'): ?>
        <p class="message message-success">Username updated.</p>
    <?php elseif ($status === 'avatar'): ?>
        <p class="message message-success">Profile picture updated.</p>
    <?php elseif ($status === 'error'): ?>
        <p class="message message-error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="profile-card">
        <div class="profile-avatar-large">
            <?php if ($me['avatar_path']): ?>
                <img src="<?= htmlspecialchars($me['avatar_path']) ?>" alt="Your avatar">
            <?php else: ?>
                <span><?= strtoupper(htmlspecialchars(mb_substr($me['name'], 0, 1))) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h2><?= htmlspecialchars($me['name']) ?></h2>
            <p class="profile-muted"><?= htmlspecialchars($me['email']) ?></p>
            <p class="profile-muted">
                <?= $me['tier'] === 'premium'
                    ? '★ Premium member — active until ' . htmlspecialchars($me['tier_expires_at'] ?? '—')
                    : 'Free member' ?>
                — joined <?= date('M j, Y', strtotime($me['created_at'])) ?>
            </p>
            <p><a href="upgrade.php">Manage subscription →</a></p>
        </div>
    </div>

    <h2>Change username</h2>
    <form method="post" action="actions/process_profile.php" class="profile-form">
        <label for="name">New username:</label><br>
        <input type="text" name="name" id="name"
               value="<?= htmlspecialchars($me['name']) ?>" maxlength="50" required>
        <button name="save-username" type="submit">Save Username</button>
    </form>

    <h2>Profile picture</h2>
    <!-- ⚠️ enctype IS THE WHOLE TRICK: without it $_FILES is EMPTY —
         a silent no-error failure (the cruelest bug family) -->
    <form method="post" action="actions/process_profile.php"
          enctype="multipart/form-data" class="profile-form">
        <label for="avatar">Choose an image (JPG, PNG or GIF, max 2 MB):</label><br>
        <input type="file" name="avatar" id="avatar" accept="image/*" required>
        <button name="upload-avatar" type="submit">Upload Picture</button>
    </form>

</div>
</main>

<?php include 'includes/footer.php'; ?>