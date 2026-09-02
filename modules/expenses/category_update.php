<?php
/**
 * Update Expense Category POST Handler
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

$id          = (int)($_POST['id'] ?? 0);
$name        = sanitize_input($_POST['name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');
$status      = strtolower(sanitize_input($_POST['status'] ?? 'active'));
$userId      = (int)($_SESSION['user_id'] ?? 0) ?: null;

if ($id <= 0 || empty($name)) {
    set_flash_message('error', 'Category ID and Name are required.');
    redirect('modules/expenses/categories.php');
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

$pdo = getDBConnection();

try {
    $checkStmt = $pdo->prepare('SELECT id, name FROM expense_categories WHERE id = :id AND deleted_at IS NULL');
    $checkStmt->execute(['id' => $id]);
    $existing = $checkStmt->fetch();

    if (!$existing) {
        set_flash_message('error', 'Category not found or has been deleted.');
        redirect('modules/expenses/categories.php');
    }

    // Check duplicate name on another category
    $dupStmt = $pdo->prepare('SELECT id FROM expense_categories WHERE name = :name AND id != :id AND deleted_at IS NULL');
    $dupStmt->execute(['name' => $name, 'id' => $id]);
    if ($dupStmt->fetch()) {
        set_flash_message('error', 'Another category already exists with this name.');
        redirect('modules/expenses/categories.php');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        UPDATE expense_categories SET
            name        = :name,
            description = :description,
            status      = :status,
            updated_at  = NOW()
        WHERE id = :id
    ');
    $stmt->execute([
        'name'        => $name,
        'description' => $description ?: null,
        'status'      => $status,
        'id'          => $id
    ]);

    log_activity($userId, 'expense_category_updated', sprintf('Updated expense category %s (status: %s)', $name, $status));

    $pdo->commit();

    set_flash_message('success', sprintf('Category <strong>%s</strong> updated successfully.', e($name)));
    redirect('modules/expenses/categories.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Category Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update category due to a database error.');
    redirect('modules/expenses/categories.php');
}
