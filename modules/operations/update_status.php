<?php
/**
 * Laundry Operations Status Update Action Handler (Concurrency-safe)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/operations/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/operations/index.php');
}

$id        = (int)($_POST['id'] ?? 0);
$newStatus = strtolower(sanitize_input($_POST['status'] ?? ''));
$userId    = (int)($_SESSION['user_id'] ?? 0) ?: null;

$validStatuses = ['received', 'processing', 'ready', 'delivered', 'cancelled'];

if ($id <= 0 || !in_array($newStatus, $validStatuses, true)) {
    set_flash_message('error', 'Invalid order status transition provided.');
    redirect('modules/operations/index.php');
}

$pdo = getDBConnection();

try {
    // 2. Atomic Transaction with Row Locking
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        SELECT id, order_number, status, total, paid_amount, due_amount, payment_status
        FROM orders
        WHERE id = :id AND deleted_at IS NULL
        FOR UPDATE
    ');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        set_flash_message('error', 'The requested order does not exist or has been deleted.');
        redirect('modules/operations/index.php');
    }

    $oldStatus = $order['status'];

    // If status is already the requested status, commit and return
    if ($oldStatus === $newStatus) {
        $pdo->commit();
        set_flash_message('info', sprintf('Order <strong>%s</strong> is already in <span class="badge bg-secondary text-uppercase">%s</span> stage.', e($order['order_number']), e($newStatus)));
        redirect('modules/operations/show.php?id=' . $id);
    }

    // 3. Workflow Transition Validation
    // Terminal States: Delivered or Cancelled orders require Administrator or Manager to alter
    if (($oldStatus === 'delivered' || $oldStatus === 'cancelled') && !is_admin() && !is_manager()) {
        $pdo->rollBack();
        set_flash_message('error', 'Only Administrators or Managers are authorized to reopen or modify completed or cancelled orders.');
        redirect('modules/operations/show.php?id=' . $id);
    }

    // Standard Staff Workflow validation: cannot jump from received directly to delivered
    if (is_staff() && !is_admin() && !is_manager()) {
        if ($oldStatus === 'received' && $newStatus === 'delivered') {
            $pdo->rollBack();
            set_flash_message('error', 'Orders in Received stage must be processed and marked Ready before delivery.');
            redirect('modules/operations/show.php?id=' . $id);
        }
    }

    // 4. Update Status in Database
    $updateStmt = $pdo->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    // 5. Record Activity Log
    log_activity(
        $userId,
        'order_status_changed',
        sprintf('Order %s status changed from %s to %s', $order['order_number'], $oldStatus, $newStatus)
    );

    $pdo->commit();

    set_flash_message('success', sprintf(
        'Order <strong>%s</strong> advanced from <span class="badge bg-light text-dark border text-uppercase">%s</span> to <span class="badge bg-primary text-uppercase">%s</span>.',
        e($order['order_number']),
        e($oldStatus),
        e($newStatus)
    ));

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && str_contains($referer, 'modules/operations/')) {
        header('Location: ' . $referer);
        exit;
    }

    redirect('modules/operations/show.php?id=' . $id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Operations Status Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update order status due to a database error.');
    redirect('modules/operations/index.php');
}
