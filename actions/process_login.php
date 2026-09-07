<?php
// actions/process_login.php — guard → validate → fetch → verify → SESSION → redirect

require_once __DIR__ . '/../includes/auth.php';   // boots session
require_once __DIR__ . '/../database/db.php';     // brings $pdo
require_once __DIR__ . '/../validation.php';      // reuse OUR validators!

// 1. GUARD — the login button was pressed?
if (!isset($_POST['login'])) {
    header('Location: ../index.php');
    exit;
}

// 2. VALIDATE — reuse the same library (DRY in action)
 $email    = trim($_POST['email'] ?? '');
 $password = $_POST['password'] ?? '';             // never trim passwords

 $errors = array_values(array_filter([
    validateRequired($email, 'Email'),
    validateEmailFormat($email),
    validateRequired($password, 'Password'),
]));

if (!empty($errors)) {
    header('Location: ../login.php?status=error&message=' . urlencode(implode(' ', $errors)));
    exit;
}

// 3. FETCH — one user by email
try {
     $stmt = $pdo->prepare('SELECT id, name, email, password_hash, tier, is_admin, avatar_path, tier_expires_at
                            FROM users WHERE email = :email');
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);  

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: ../login.php?status=error&message='
             . urlencode('Wrong email or password.'));
        exit;
    }

    // ── Subscription expiry: premium whose date passed → downgrade NOW ──
    // DB write + local fix, so the session below stores the corrected tier
    if ($user['tier'] === 'premium'
        && $user['tier_expires_at'] !== null
        && $user['tier_expires_at'] < date('Y-m-d')) {
        $down = $pdo->prepare("UPDATE users SET tier = 'free', tier_expires_at = NULL
                            WHERE id = :id");
        $down->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $down->execute();
        $user['tier'] = 'free';
        $user['tier_expires_at'] = null;
    }

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_tier'] = $user['tier'];
    $_SESSION['is_admin']  = (int) ($user['is_admin'] ?? 0);
    $_SESSION['user_tier'] = $user['tier'];
    $_SESSION['user_avatar'] = $user['avatar_path'];

    header('Location: ../movies.php');
    exit;

} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: ../login.php?status=error&message='
         . urlencode('Something went wrong. Please try again.'));
    exit;
}