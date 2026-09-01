<?php
/**
 * Void Payment Action Handler (Admin Only)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Role Guard: Only Administrator can void financial records
require_role('administrator', 'modules/payments/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/payments/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/payments/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid payment ID provided.');
    redirect('modules/payments/index.php');
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // 2. Fetch and verify payment
    $stmt = $pdo->prepare('SELECT id, payment_number, order_id, amount, status FROM payments WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        $pdo->rollBack();
        set_flash_message('error', 'Payment not found or has already been deleted.');
        redirect('modules/payments/index.php');
    }

    if ($payment['status'] === 'voided') {
        $pdo->rollBack();
        set_flash_message('error', 'This payment has already been voided.');
        redirect('modules/payments/show.php?id=' . $id);
    }

    $orderId = (int)$payment['order_id'];

    // Lock associated order row
    $ordStmt = $pdo->prepare('SELECT id, order_number FROM orders WHERE id = :id FOR UPDATE');
    $ordStmt->execute(['id' => $orderId]);
    $order = $ordStmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        set_flash_message('error', 'Associated order not found.');
        redirect('modules/payments/index.php');
    }

    // 3. Mark Payment as Voided
    $voidStmt = $pdo->prepare('UPDATE payments SET status = "voided", updated_at = NOW() WHERE id = :id');
    $voidStmt->execute(['id' => $id]);

    // 4. Recalculate order payment summary authoritatively
    recalculate_order_payment_summary($orderId, $pdo);

    // 5. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'payment_voided',
        sprintf('Voided payment %s of %s for order %s.', $payment['payment_number'], format_price($payment['amount']), $order['order_number'])
    );

    $pdo->commit();

    set_flash_message('success', sprintf(
        'Payment <strong>%s</strong> of <strong>%s</strong> was voided successfully. Order <strong>%s</strong> due balance has been updated.',
        e($payment['payment_number']),
        e(format_price($payment['amount'])),
        e($order['order_number'])
    ));

    redirect('modules/payments/show.php?id=' . $id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Payment Void Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to void payment due to a database error.');
    redirect('modules/payments/show.php?id=' . $id);
}
