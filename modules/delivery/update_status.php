<?php
/**
 * Update Pickup / Delivery Status Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/delivery/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/delivery/index.php');
}

$id        = (int)($_POST['id'] ?? 0);
$newStatus = strtolower(sanitize_input($_POST['status'] ?? ''));
$currentUser = current_user();

$validStatuses = ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'];

if ($id <= 0 || !in_array($newStatus, $validStatuses, true)) {
    set_flash_message('error', 'Invalid request or status specified.');
    redirect('modules/delivery/index.php');
}

$pdo = getDBConnection();

// Fetch request record
$stmt = $pdo->prepare('SELECT * FROM pickup_deliveries WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    set_flash_message('error', 'The requested record does not exist or has been deleted.');
    redirect('modules/delivery/index.php');
}

// Authorization check: Staff can only update their assigned or created requests
if (is_staff() && !is_admin() && !is_manager()) {
    $assignedToId = (int)($req['assigned_to'] ?? 0);
    $createdById  = (int)($req['created_by'] ?? 0);
    if ($assignedToId !== (int)$currentUser['id'] && $createdById !== (int)$currentUser['id']) {
        set_flash_message('error', 'You are only authorized to update requests assigned to you.');
        redirect('modules/delivery/index.php');
    }
}

try {
    $completedAt = $req['completed_at'];

    if ($newStatus === 'completed') {
        $completedAt = date('Y-m-d H:i:s');
    } elseif ($req['status'] === 'completed' && $newStatus !== 'completed') {
        // Reset completed timestamp if transitioning away from completed
        $completedAt = null;
    }

    $updateStmt = $pdo->prepare('
        UPDATE pickup_deliveries SET
            status       = :status,
            completed_at = :completed_at,
            updated_at   = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        'status'       => $newStatus,
        'completed_at' => $completedAt,
        'id'           => $id
    ]);

    log_activity(
        $currentUser['id'] ?? null,
        'delivery_status_updated',
        sprintf('Changed %s %s status from %s to %s', $req['type'], $req['reference_number'], $req['status'], $newStatus)
    );

    set_flash_message('success', sprintf(
        'Request <strong>%s</strong> status updated to <strong class="text-uppercase">%s</strong>.',
        e($req['reference_number']),
        e($newStatus)
    ));
    redirect('modules/delivery/show.php?id=' . $id);

} catch (PDOException $e) {
    error_log('Delivery Status Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update request status due to a database error.');
    redirect('modules/delivery/show.php?id=' . $id);
}
