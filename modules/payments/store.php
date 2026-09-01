<?php
/**
 * Payment Store Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/payments/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/payments/create.php');
}

// 2. Extract & Sanitize Primary Input Fields
$orderId              = (int)($_POST['order_id'] ?? 0);
$rawAmount            = sanitize_input($_POST['amount'] ?? '');
$paymentMethod        = sanitize_input($_POST['payment_method'] ?? 'cash');
$transactionReference = sanitize_input($_POST['transaction_reference'] ?? '');
$paymentDate          = sanitize_input($_POST['payment_date'] ?? '');
$notes                = sanitize_input($_POST['notes'] ?? '');

$validMethods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'other'];

$errors = [];

if ($orderId <= 0) {
    $errors[] = 'Please select a valid order to receive payment.';
}

if (!is_numeric($rawAmount) || (float)$rawAmount <= 0) {
    $errors[] = 'Payment amount must be a positive number greater than zero.';
}

$amount = round((float)$rawAmount, 2);

if (!in_array($paymentMethod, $validMethods, true)) {
    $errors[] = 'Invalid payment method selected.';
}

if (empty($paymentDate)) {
    $paymentDate = date('Y-m-d H:i:s');
} else {
    $parsedDate = date('Y-m-d H:i:s', strtotime($paymentDate));
    $paymentDate = $parsedDate ?: date('Y-m-d H:i:s');
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/payments/create.php' . ($orderId > 0 ? '?order_id=' . $orderId : ''));
}

// 3. Concurrency-Safe Transaction with Row Locking
$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Lock the order row to prevent simultaneous race conditions & overpayments
    $ordStmt = $pdo->prepare('
        SELECT id, order_number, total, status, deleted_at
        FROM orders
        WHERE id = :id
        FOR UPDATE
    ');
    $ordStmt->execute(['id' => $orderId]);
    $order = $ordStmt->fetch();

    if (!$order || $order['deleted_at'] !== null) {
        $pdo->rollBack();
        set_flash_message('error', 'The requested order does not exist or has been deleted.');
        redirect('modules/payments/index.php');
    }

    if ($order['status'] === 'cancelled') {
        $pdo->rollBack();
        set_flash_message('error', 'Cannot accept payments for cancelled orders.');
        redirect('modules/orders/show.php?id=' . $orderId);
    }

    $orderTotal = (float)$order['total'];

    // Calculate existing valid completed payments for this order
    $sumStmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
        WHERE order_id = :order_id AND status = "completed" AND deleted_at IS NULL
    ');
    $sumStmt->execute(['order_id' => $orderId]);
    $existingPaid = round((float)$sumStmt->fetchColumn(), 2);

    $currentDue = round(max(0, $orderTotal - $existingPaid), 2);

    if ($currentDue <= 0) {
        $pdo->rollBack();
        set_flash_message('error', sprintf('Order %s is already fully paid. No further payments can be accepted.', e($order['order_number'])));
        redirect('modules/orders/show.php?id=' . $orderId);
    }

    if ($amount > $currentDue) {
        $pdo->rollBack();
        set_flash_message('error', sprintf(
            'Payment amount (%s) exceeds the current outstanding due balance of %s for order %s.',
            format_price($amount),
            format_price($currentDue),
            e($order['order_number'])
        ));
        redirect('modules/payments/create.php?order_id=' . $orderId);
    }

    // Generate unique sequential payment number
    $paymentNumber = generate_payment_number($pdo);
    $receivedBy    = (int)($_SESSION['user_id'] ?? 0) ?: null;

    // Insert Payment Record
    $insertStmt = $pdo->prepare('
        INSERT INTO payments (
            payment_number, order_id, amount, payment_method,
            transaction_reference, payment_date, notes, received_by,
            status, created_at, updated_at
        ) VALUES (
            :payment_number, :order_id, :amount, :payment_method,
            :transaction_reference, :payment_date, :notes, :received_by,
            "completed", NOW(), NOW()
        )
    ');

    $insertStmt->execute([
        'payment_number'        => $paymentNumber,
        'order_id'              => $orderId,
        'amount'                => $amount,
        'payment_method'        => $paymentMethod,
        'transaction_reference' => !empty($transactionReference) ? $transactionReference : null,
        'payment_date'          => $paymentDate,
        'notes'                 => !empty($notes) ? $notes : null,
        'received_by'           => $receivedBy
    ]);

    $paymentId = (int)$pdo->lastInsertId();

    // Recalculate and update order totals and payment status authoritatively
    recalculate_order_payment_summary($orderId, $pdo);

    // Log Activity
    log_activity(
        $receivedBy,
        'payment_created',
        sprintf('Created payment %s of %s for order %s.', $paymentNumber, format_price($amount), $order['order_number'])
    );

    $pdo->commit();

    set_flash_message('success', sprintf(
        'Payment <strong>%s</strong> of <strong>%s</strong> was recorded successfully for order <strong>%s</strong>.',
        e($paymentNumber),
        e(format_price($amount)),
        e($order['order_number'])
    ));

    redirect('modules/payments/show.php?id=' . $paymentId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Payment Store Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to save payment due to a database error. Please try again.');
    redirect('modules/payments/create.php' . ($orderId > 0 ? '?order_id=' . $orderId : ''));
}
