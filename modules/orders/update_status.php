<?php
/**
 * Laundry Order Status Update Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/orders/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/orders/index.php');
}

$id        = (int)($_POST['id'] ?? 0);
$newStatus = sanitize_input($_POST['status'] ?? '');

$validStatuses = ['received', 'processing', 'ready', 'delivered', 'cancelled'];

if ($id <= 0 || !in_array($newStatus, $validStatuses, true)) {
    set_flash_message('error', 'Invalid order status transition provided.');
    redirect('modules/orders/index.php');
}

try {
    $pdo = getDBConnection();

    // 2. Fetch current order
    $stmt = $pdo->prepare('SELECT id, order_number, status FROM orders WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash_message('error', 'Order not found or has been deleted.');
        redirect('modules/orders/index.php');
    }

    $oldStatus = $order['status'];

    // If delivered or cancelled, only Admin/Manager should reopen
    if (($oldStatus === 'delivered' || $oldStatus === 'cancelled') && $newStatus !== $oldStatus && !is_admin() && !is_manager()) {
        set_flash_message('error', 'Only Administrators or Managers can reopen delivered or cancelled orders.');
        redirect('modules/orders/show.php?id=' . $id);
    }

    // 3. Update Status
    $updateStmt = $pdo->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    // 4. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'order_status_changed',
        sprintf('Order %s status changed from %s to %s', $order['order_number'], $oldStatus, $newStatus)
    );

    set_flash_message('success', sprintf('Order <strong>%s</strong> status updated to <span class="badge bg-secondary text-uppercase">%s</span>.', e($order['order_number']), e($newStatus)));

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && str_contains($referer, 'modules/orders/')) {
        header('Location: ' . $referer);
        exit;
    }

    redirect('modules/orders/show.php?id=' . $id);

} catch (PDOException $e) {
    error_log('Order Status Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update order status due to a database error.');
    redirect('modules/orders/index.php');
}
