<?php
/**
 * Record New Operational Expense POST Handler
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
    redirect('modules/expenses/create.php');
}

$categoryId    = (int)($_POST['category_id'] ?? 0);
$amount        = (float)($_POST['amount'] ?? 0);
$expenseDate   = sanitize_input($_POST['expense_date'] ?? '');
$paymentMethod = strtolower(sanitize_input($_POST['payment_method'] ?? 'cash'));
$paidBy        = sanitize_input($_POST['paid_by'] ?? '');
$description   = sanitize_input($_POST['description'] ?? '');
$userId        = (int)($_SESSION['user_id'] ?? 0) ?: null;

$errors = [];

// 2. Server-side Validations
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

// Verify category exists and is active
if ($categoryId > 0) {
    $catCheckStmt = $pdo->prepare('SELECT id, name FROM expense_categories WHERE id = :id AND status = "active" AND deleted_at IS NULL');
    $catCheckStmt->execute(['id' => $categoryId]);
    $category = $catCheckStmt->fetch();
    if (!$category) {
        $errors[] = 'The selected expense category is invalid, inactive, or has been deleted.';
    }
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/expenses/create.php');
}

try {
    // 3. Concurrency-Safe Transaction
    $pdo->beginTransaction();

    $referenceNumber = generate_expense_reference($pdo);

    $insertStmt = $pdo->prepare('
        INSERT INTO expenses (
            reference_number, category_id, amount, expense_date, 
            payment_method, description, paid_by, created_by, created_at, updated_at
        ) VALUES (
            :ref, :cat_id, :amount, :expense_date, 
            :payment_method, :description, :paid_by, :created_by, NOW(), NOW()
        )
    ');
    $insertStmt->execute([
        'ref'            => $referenceNumber,
        'cat_id'         => $categoryId,
        'amount'         => $amount,
        'expense_date'   => $expenseDate,
        'payment_method' => $paymentMethod,
        'description'    => $description ?: null,
        'paid_by'        => $paidBy ?: null,
        'created_by'     => $userId
    ]);

    $newExpenseId = (int)$pdo->lastInsertId();

    // 4. Audit Log
    log_activity(
        $userId,
        'expense_created',
        sprintf('Created expense %s in category %s for %s', $referenceNumber, $category['name'], format_price($amount))
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Expense <strong>%s</strong> recorded successfully.', e($referenceNumber)));
    redirect('modules/expenses/show.php?id=' . $newExpenseId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Expense Store Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to save expense record due to a database error.');
    redirect('modules/expenses/create.php');
}
