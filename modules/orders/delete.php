<?php
/**
 * Laundry Order Soft Delete Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Only Administrator & Manager can delete orders
require_role(['administrator', 'manager'], 'modules/orders/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/orders/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/orders/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid order ID provided.');
    redirect('modules/orders/index.php');
}

try {
    $pdo = getDBConnection();

    // 2. Fetch order details
    $stmt = $pdo->prepare('SELECT id, order_number FROM orders WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash_message('error', 'The order you are trying to delete does not exist or has already been deleted.');
        redirect('modules/orders/index.php');
    }

    // 3. Perform Soft Delete on Order
    $deleteStmt = $pdo->prepare('UPDATE orders SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
    $deleteStmt->execute(['id' => $id]);

    // 4. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'order_deleted',
        sprintf('Deleted order %s (ID: %d)', $order['order_number'], $id)
    );

    set_flash_message('success', sprintf('Laundry order <strong>%s</strong> has been soft-deleted.', e($order['order_number'])));
    redirect('modules/orders/index.php');

} catch (PDOException $e) {
    error_log('Order Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete order due to a database error.');
    redirect('modules/orders/index.php');
}
