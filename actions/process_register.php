<?php
// action to process register - receives form, validates, inserts, redirects

require_once __DIR__ . '/../validation.php';
require_once __DIR__ . '/../database/db.php';

// Check if create button clicked
if (!isset($_POST['create-account'])) {
    header('Location: ../register.php');
    exit;
}

// Validate
$result = validateRegisterInput($_POST);

if (!empty($result['errors'])) {
    $message = implode(' ', $result['errors']);
    header('Location: ../register.php?status=error&message=' . urlencode($message));
    exit;
}

// Hash password
$hash = password_hash($result['data']['password'], PASSWORD_DEFAULT);

// SQL query
$sql = "INSERT INTO users (name, email, password_hash)
        VALUES (:name, :email, :password_hash)";

// Insert user
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':name', $result['data']['name']);
    $stmt->bindValue(':email', $result['data']['email']);
    $stmt->bindValue(':password_hash', $hash);
    $stmt->execute();

    // Success
    header('Location: ../login.php?status=registered');
    exit;
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $friendly = 'That email is already registered. Try logging in instead.';
    } else {
        error_log('Register error: ' . $e->getMessage());
        $friendly = 'Something went wrong creating your account. Please try again.';
    }
    
    header('Location: ../register.php?status=error&message=' . urlencode($friendly));
    exit;
}
