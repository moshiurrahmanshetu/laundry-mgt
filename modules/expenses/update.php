<?php
/**
 * Update Operational Expense POST Handler
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

$id            = (int)($_POST['id'] ?? 0);
$categoryId    = (int)($_POST['category_id'] ?? 0);
$amount        = (float)($_POST['amount'] ?? 0);
$expenseDate   = sanitize_input($_POST['expense_date'] ?? '');
$paymentMethod = strtolower(sanitize_input($_POST['payment_method'] ?? 'cash'));
$paidBy        = sanitize_input($_POST['paid_by'] ?? '');
$description   = sanitize_input($_POST['description'] ?? '');
$userId        = (int)($_SESSION['user_id'] ?? 0) ?: null;

$errors = [];

if ($id <= 0) {
    set_flash_message('error', 'Invalid expense record ID.');
    redirect('modules/expenses/index.php');
}

if ($categoryId <= 0) {
    $errors[] = 'Please select a valid expense category.';
}

if ($amount <= 0) {
    $errors[] = 'Expense amount must be greater than zero.';
}

if (empty($expenseDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
    $errors[] = 'Please provide a valid expense date in YYYY-MM-DD format.';
}

$validMethods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'other'];
if (!in_array($paymentMethod, $validMethods, true)) {
    $errors[] = 'Please select a valid payment channel.';
}

$pdo = getDBConnection();

// Check existing expense
$stmt = $pdo->prepare('SELECT id, reference_number, category_id, amount FROM expenses WHERE id = :id AND deleted_at IS NULL');
$stmt->execute(['id' => $id]);
$existingExpense = $stmt->fetch();

if (!$existingExpense) {
    set_flash_message('error', 'The requested expense record does not exist or has been deleted.');
    redirect('modules/expenses/index.php');
}

// Verify category
$catCheckStmt = $pdo->prepare('SELECT id, name FROM expense_categories WHERE id = :id AND deleted_at IS NULL');
$catCheckStmt->execute(['id' => $categoryId]);
$category = $catCheckStmt->fetch();
if (!$category) {
    $errors[] = 'The selected expense category is invalid or deleted.';
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/expenses/edit.php?id=' . $id);
}

try {
    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare('
        UPDATE expenses SET
            category_id    = :cat_id,
            amount         = :amount,
            expense_date   = :expense_date,
            payment_method = :payment_method,
            description    = :description,
            paid_by        = :paid_by,
            updated_at     = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        'cat_id'         => $categoryId,
        'amount'         => $amount,
        'expense_date'   => $expenseDate,
        'payment_method' => $paymentMethod,
        'description'    => $description ?: null,
        'paid_by'        => $paidBy ?: null,
        'id'             => $id
    ]);

    // Audit Log
    log_activity(
        $userId,
        'expense_updated',
        sprintf('Updated expense %s (%s, %s)', $existingExpense['reference_number'], $category['name'], format_price($amount))
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Expense <strong>%s</strong> updated successfully.', e($existingExpense['reference_number'])));
    redirect('modules/expenses/show.php?id=' . $id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Expense Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update expense record due to a database error.');
    redirect('modules/expenses/edit.php?id=' . $id);
}
