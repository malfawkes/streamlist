<?php
// actions/admin_manage_plans.php — add / remove subscription offerings

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../database/db.php';

if (!isset($_POST['add-plan']) && !isset($_POST['delete-plan'])) {
    header('Location: ../admin.php');
    exit;
}

try {
    if (isset($_POST['add-plan'])) {
        $label  = trim($_POST['label'] ?? '');
        $months = filter_var($_POST['months'] ?? '', FILTER_VALIDATE_INT);
        $price  = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);

        // Validate everything
        $errors = [];
        if ($label === '')                          { $errors[] = 'Label is required.'; }
        if ($months === false || $months < 1 || $months > 120) { $errors[] = 'Months must be 1–120.'; }
        if ($price === false || $price <= 0 || $price > 9999)  { $errors[] = 'Price must be a positive amount.'; }
        if (!empty($errors)) {
            header('Location: ../admin.php?status=error&message=' . urlencode(implode(' ', $errors)));
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO plans (label, months, price) VALUES (:l, :m, :p)');
        $stmt->bindValue(':l', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
        $stmt->bindValue(':m', $months, PDO::PARAM_INT);
        $stmt->bindValue(':p', number_format($price, 2, '.', ''));   // clean decimal string
        $stmt->execute();

        header('Location: ../admin.php?status=plan-added');
        exit;
    }

    // ── delete-plan ──
    $planId = filter_var($_POST['plan_id'] ?? '', FILTER_VALIDATE_INT);
    if ($planId === false || $planId < 1) {
        header('Location: ../admin.php?status=error&message=' . urlencode('Invalid plan.'));
        exit;
    }
    // Safe to delete: users store tier + expiry, not plan references — no FK touches this table
    $stmt = $pdo->prepare('DELETE FROM plans WHERE id = :id');
    $stmt->bindValue(':id', $planId, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: ../admin.php?status=plan-deleted');
    exit;

} catch (PDOException $e) {
    error_log('Plan management error: ' . $e->getMessage());
    header('Location: ../admin.php?status=error&message=' . urlencode('Plan operation failed.'));
    exit;
}