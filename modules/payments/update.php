<?php
/**
 * Payment Update Action Handler (Admin Only)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Role Guard: Administrator only
require_role('administrator', 'modules/payments/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/payments/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid payment ID provided.');
    redirect('modules/payments/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/payments/edit.php?id=' . $id);
}

// 2. Fetch existing payment
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, payment_number, status FROM payments WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$payment = $stmt->fetch();

if (!$payment) {
    set_flash_message('error', 'The payment transaction does not exist or has been deleted.');
    redirect('modules/payments/index.php');
}

if ($payment['status'] === 'voided') {
    set_flash_message('error', 'Voided payments are locked and cannot be edited.');
    redirect('modules/payments/show.php?id=' . $id);
}

// 3. Extract & Sanitize Input Fields
$paymentMethod        = sanitize_input($_POST['payment_method'] ?? 'cash');
$transactionReference = sanitize_input($_POST['transaction_reference'] ?? '');
$paymentDate          = sanitize_input($_POST['payment_date'] ?? '');
$notes                = sanitize_input($_POST['notes'] ?? '');

$validMethods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'other'];
if (!in_array($paymentMethod, $validMethods, true)) {
    $paymentMethod = 'cash';
}

if (empty($paymentDate)) {
    $paymentDate = date('Y-m-d H:i:s');
} else {
    $parsedDate = date('Y-m-d H:i:s', strtotime($paymentDate));
    $paymentDate = $parsedDate ?: date('Y-m-d H:i:s');
}

// 4. Update Payment Record
try {
    $updateStmt = $pdo->prepare('
        UPDATE payments SET
            payment_method        = :payment_method,
            transaction_reference = :transaction_reference,
            payment_date          = :payment_date,
            notes                 = :notes,
            updated_at            = NOW()
        WHERE id = :id
    ');

    $updateStmt->execute([
        'payment_method'        => $paymentMethod,
        'transaction_reference' => !empty($transactionReference) ? $transactionReference : null,
        'payment_date'          => $paymentDate,
        'notes'                 => !empty($notes) ? $notes : null,
        'id'                    => $id
    ]);

    // Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'payment_updated',
        sprintf('Updated metadata for payment %s.', $payment['payment_number'])
    );

    set_flash_message('success', sprintf('Payment <strong>%s</strong> updated successfully.', e($payment['payment_number'])));
    redirect('modules/payments/show.php?id=' . $id);

} catch (PDOException $e) {
    error_log('Payment Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update payment due to a database error.');
    redirect('modules/payments/edit.php?id=' . $id);
}
