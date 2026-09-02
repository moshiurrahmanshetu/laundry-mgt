<?php
/**
 * Add Expense Category POST Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/expenses/categories.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/expenses/categories.php');
}

$name        = sanitize_input($_POST['name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');
$status      = strtolower(sanitize_input($_POST['status'] ?? 'active'));
$userId      = (int)($_SESSION['user_id'] ?? 0) ?: null;

if (empty($name)) {
    set_flash_message('error', 'Category name is required.');
    redirect('modules/expenses/categories.php');
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

$pdo = getDBConnection();

try {
    // Check name uniqueness (active / non-deleted)
    $checkStmt = $pdo->prepare('SELECT id FROM expense_categories WHERE name = :name AND deleted_at IS NULL');
    $checkStmt->execute(['name' => $name]);
    if ($checkStmt->fetch()) {
        set_flash_message('error', 'An expense category with this name already exists.');
        redirect('modules/expenses/categories.php');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO expense_categories (name, description, status, created_at, updated_at)
        VALUES (:name, :description, :status, NOW(), NOW())
    ');
    $stmt->execute([
        'name'        => $name,
        'description' => $description ?: null,
        'status'      => $status
    ]);

    log_activity($userId, 'expense_category_created', sprintf('Created expense category %s', $name));

    $pdo->commit();

    set_flash_message('success', sprintf('Expense category <strong>%s</strong> created successfully.', e($name)));
    redirect('modules/expenses/categories.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Category Store Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to create category due to a database error.');
    redirect('modules/expenses/categories.php');
}
