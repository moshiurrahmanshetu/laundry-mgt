<?php
/**
 * Soft Delete Expense Category POST Handler
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

$id     = (int)($_POST['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0) ?: null;

if ($id <= 0) {
    set_flash_message('error', 'Invalid category ID.');
    redirect('modules/expenses/categories.php');
}

$pdo = getDBConnection();

try {
    $checkStmt = $pdo->prepare('SELECT id, name FROM expense_categories WHERE id = :id AND deleted_at IS NULL');
    $checkStmt->execute(['id' => $id]);
    $category = $checkStmt->fetch();

    if (!$category) {
        set_flash_message('error', 'Category not found or has already been deleted.');
        redirect('modules/expenses/categories.php');
    }

    // 2. Check FK Usage in expenses table
    $usageStmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE category_id = :id AND deleted_at IS NULL');
    $usageStmt->execute(['id' => $id]);
    $usageCount = (int)$usageStmt->fetchColumn();

    if ($usageCount > 0) {
        set_flash_message('error', sprintf(
            'Category <strong>%s</strong> cannot be deleted because it is used by %d existing expense(s). You can deactivate it instead.',
            e($category['name']),
            $usageCount
        ));
        redirect('modules/expenses/categories.php');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE expense_categories SET deleted_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $id]);

    log_activity($userId, 'expense_category_deleted', sprintf('Soft deleted expense category %s', $category['name']));

    $pdo->commit();

    set_flash_message('success', sprintf('Category <strong>%s</strong> was deleted successfully.', e($category['name'])));
    redirect('modules/expenses/categories.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Category Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete category due to a database error.');
    redirect('modules/expenses/categories.php');
}
