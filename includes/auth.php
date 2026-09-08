<?php
// includes/auth.php — boots the session + login-check helpers.
// Include at the VERY TOP of every page (before header.php outputs HTML).

require_once __DIR__ . '/../database/configHidden.php';   // for SITE_URL

// Start the session unless one is already running
// (prevents "session already started" notices when several files load this)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    // "Logged in" is defined as: a user_id exists in this session
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        header('Location: ' . SITE_URL . '/movies.php');
        exit;
    }
}


function isAdmin(): bool
{
    return ($_SESSION['is_admin'] ?? 0) === 1;
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        die('Forbidden: Administrators only.');
    }
}