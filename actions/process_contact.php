<?php
// actions/process_contact.php — validate + store a contact message

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../validation.php';

if (!isset($_POST['send-message'])) {
    header('Location: ../contact.php');
    exit;
}

if (trim($_POST['website'] ?? '') !== '') {
    header('Location: ../contact.php?status=sent');
    exit;
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['message'] ?? '');

// Your validator library, reused as-is (DRY paying off)
$errors = [];
if ($e = validateRequired($name, 'Name'))    { $errors[] = $e; }
if ($e = validateRequired($email, 'Email'))  { $errors[] = $e; }
if ($e = validateEmailFormat($email))        { $errors[] = $e; }
if ($e = validateRequired($body, 'Message')) { $errors[] = $e; }
if ($body !== '' && strlen($body) < 10)      { $errors[] = 'Message must be at least 10 characters.'; }
if (strlen($subject) > 150)                  { $errors[] = 'Subject must be under 150 characters.'; }

if (!empty($errors)) {
    header('Location: ../contact.php?status=error&message=' . urlencode(implode(' ', $errors)));
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message)
                           VALUES (:name, :email, :subject, :message)');
    $stmt->bindValue(':name',    htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
    $stmt->bindValue(':email',   htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
    $stmt->bindValue(':subject', $subject !== '' ? htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') : null);
    $stmt->bindValue(':message', htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $stmt->execute();

    header('Location: ../contact.php?status=sent');
    exit;

} catch (PDOException $e) {
    error_log('Contact form error: ' . $e->getMessage());
    header('Location: ../contact.php?status=error&message='
        . urlencode('Could not send your message. Please try again.'));
    exit;
}
