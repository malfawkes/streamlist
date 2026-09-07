<?php
// actions/process_profile.php — username change + avatar upload

require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../validation.php';

// Guard: which button? (the two-button pattern)
if (!isset($_POST['save-username']) && !isset($_POST['upload-avatar'])) {
    header('Location: ../profile.php');
    exit;
}

 $userId = currentUserId();   // identity from session — never from the form

// ── 1. USERNAME CHANGE ───────────────────────────────────────────
if (isset($_POST['save-username'])) {

    $name = trim($_POST['name'] ?? '');

    $errors = [];
    if ($e = validateRequired($name, 'Username')) { $errors[] = $e; }
    if (strlen($name) > 50) { $errors[] = 'Username must be 50 characters or fewer.'; }

    if (!empty($errors)) {
        header('Location: ../profile.php?status=error&message=' . urlencode(implode(' ', $errors)));
        exit;
    }

    try {
        // ⭐ THE lesson of this feature: update the DB (source of truth)…
        $stmt = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
        $stmt->bindValue(':name', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // …AND the session (the cache) — or the navbar shows the old
        // name until re-login. Same discipline as tier changes.
        $_SESSION['user_name'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        header('Location: ../profile.php?status=username');
        exit;

    } catch (PDOException $e) {
        error_log('Profile username error: ' . $e->getMessage());
        header('Location: ../profile.php?status=error&message='
             . urlencode('Could not save username. Please try again.'));
        exit;
    }
}

// ── 2. AVATAR UPLOAD — the file-handling security chain ──────────
if (isset($_POST['upload-avatar'])) {

    // 1. Did a file arrive at all, and cleanly?
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        header('Location: ../profile.php?status=error&message='
             . urlencode('Upload failed — try a different image.'));
        exit;
    }

    $file = $_FILES['avatar'];

    // 2. Size limit (checked BEFORE any processing — 2 MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        header('Location: ../profile.php?status=error&message='
             . urlencode('Image must be under 2 MB.'));
        exit;
    }

    // 3. Content check: getimagesize() reads the file's ACTUAL bytes and
    //    reports its true type — a renamed .txt can't fake it.
    //    This is the difference between "extension says jpg" and "IS jpg".
    $info = @getimagesize($file['tmp_name']);
    $allowedTypes = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_GIF => '.gif'];

    if ($info === false || !isset($allowedTypes[$info[2]])) {
        header('Location: ../profile.php?status=error&message='
             . urlencode('File is not a valid JPG, PNG or GIF image.'));
        exit;
    }

    // 4. Extension comes from the DETECTED type, never the user's filename.
    //    5. Random filename — the user controls nothing about the path.
    $newName  = bin2hex(random_bytes(16)) . $allowedTypes[$info[2]];
    $dest     = __DIR__ . '/../uploads/avatars/' . $newName;
    $dbPath   = 'uploads/avatars/' . $newName;

    // 6. move_uploaded_file — the ONLY safe way to place an upload
    //    (it verifies the file really came from a POST upload)
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        header('Location: ../profile.php?status=error&message='
             . urlencode('Could not save the image.'));
        exit;
    }

    try {
        // remove the old avatar file (if any) — no orphans on disk
        $old = $pdo->prepare('SELECT avatar_path FROM users WHERE id = :id');
        $old->bindValue(':id', $userId, PDO::PARAM_INT);
        $old->execute();
        $oldPath = $old->fetchColumn();
        if ($oldPath && is_file(__DIR__ . '/../' . $oldPath)) {
            unlink(__DIR__ . '/../' . $oldPath);
        }

        $stmt = $pdo->prepare('UPDATE users SET avatar_path = :p WHERE id = :id');
        $stmt->bindValue(':p', $dbPath);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // cache sync, one more time
        $_SESSION['user_avatar'] = $dbPath;

        header('Location: ../profile.php?status=avatar');
        exit;

    } catch (PDOException $e) {
        error_log('Avatar DB error: ' . $e->getMessage());
        header('Location: ../profile.php?status=error&message='
             . urlencode('Could not update your picture.'));
        exit;
    }
}