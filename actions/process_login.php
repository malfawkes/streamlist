<?php
// actions/process_login.php — guard → validate → fetch → verify → SESSION → redirect

require_once __DIR__ . '/../includes/auth.php';   // boots session
require_once __DIR__ . '/../database/db.php';     // brings $pdo
require_once __DIR__ . '/../validation.php';      // reuse OUR validators!

// 1. GUARD — the login button was pressed?
if (!isset($_POST['login'])) {
    header('Location: ../login.php');
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
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, tier
                           FROM users WHERE email = :email');
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);   // fetch() = ONE row, or false

    // 4. VERIFY — both failures get ONE generic message.
    //    "Email not found" would tell attackers which emails exist —
    //    never confirm/deny which part was wrong.
    //    password_verify(typed, stored_hash) — the hash NEVER un-hashes;
    //    PHP re-hashes your input and compares.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: ../login.php?status=error&message='
             . urlencode('Wrong email or password.'));
        exit;
    }

    // 5. SUCCESS — THE login moment. These values now live in the session
    //    and every page can read them.
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_tier'] = $user['tier'];

    // Temporary landing page — we'll point this at movies.php next file
    header('Location: ../index.php');
    exit;

} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    header('Location: ../login.php?status=error&message='
         . urlencode('Something went wrong. Please try again.'));
    exit;
}