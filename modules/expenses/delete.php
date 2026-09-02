<?php
/**
 * Soft Delete Operational Expense POST Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/expenses/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/expenses/index.php');
}

$id     = (int)($_POST['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0) ?: null;

if ($id <= 0) {
    set_flash_message('error', 'Invalid expense ID provided.');
    redirect('modules/expenses/index.php');
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare('SELECT id, reference_number, amount FROM expenses WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute(['id' => $id]);
    $expense = $stmt->fetch();

    if (!$expense) {
        set_flash_message('error', 'The requested expense record does not exist or has already been deleted.');
        redirect('modules/expenses/index.php');
    }

    $pdo->beginTransaction();

    // 2. Soft Delete Record
    $deleteStmt = $pdo->prepare('UPDATE expenses SET deleted_at = NOW() WHERE id = :id');
    $deleteStmt->execute(['id' => $id]);

    // 3. Audit Log
    log_activity(
        $userId,
        'expense_deleted',
        sprintf('Soft deleted expense %s (%s)', $expense['reference_number'], format_price($expense['amount']))
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Expense <strong>%s</strong> was deleted successfully.', e($expense['reference_number'])));
    redirect('modules/expenses/index.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Expense Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete expense due to a database error.');
    redirect('modules/expenses/index.php');
}
