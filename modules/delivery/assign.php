<?php
/**
 * Assign Staff to Pickup / Delivery Request Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['administrator', 'manager'], 'modules/delivery/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/delivery/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/delivery/index.php');
}

$id         = (int)($_POST['id'] ?? 0);
$assignedTo = (int)($_POST['assigned_to'] ?? 0);
$currentUser = current_user();

if ($id <= 0) {
    set_flash_message('error', 'Invalid request ID.');
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

$resolvedAssignedTo = null;
$assignedStaffName = 'Unassigned';

if ($assignedTo > 0) {
    $uStmt = $pdo->prepare('SELECT id, name, status FROM users WHERE id = :id AND status = "active" LIMIT 1');
    $uStmt->execute(['id' => $assignedTo]);
    $assignedUser = $uStmt->fetch();
    if (!$assignedUser) {
        set_flash_message('error', 'Selected staff member is inactive or does not exist.');
        redirect('modules/delivery/show.php?id=' . $id);
    }
    $resolvedAssignedTo = (int)$assignedUser['id'];
    $assignedStaffName = $assignedUser['name'];
}

try {
    $newStatus = $req['status'];
    if ($resolvedAssignedTo !== null && $req['status'] === 'pending') {
        $newStatus = 'assigned';
    } elseif ($resolvedAssignedTo === null && $req['status'] === 'assigned') {
        $newStatus = 'pending';
    }

    $updateStmt = $pdo->prepare('
        UPDATE pickup_deliveries SET
            assigned_to = :assigned_to,
            status      = :status,
            updated_at  = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        'assigned_to' => $resolvedAssignedTo,
        'status'      => $newStatus,
        'id'          => $id
    ]);

    log_activity(
        $currentUser['id'] ?? null,
        'delivery_staff_assigned',
        sprintf('Assigned %s request %s to %s', $req['type'], $req['reference_number'], $assignedStaffName)
    );

    set_flash_message('success', sprintf('Request <strong>%s</strong> assignment updated to <strong>%s</strong>.', e($req['reference_number']), e($assignedStaffName)));
    redirect('modules/delivery/show.php?id=' . $id);

} catch (PDOException $e) {
    error_log('Delivery Assign Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update assignment due to a database error.');
    redirect('modules/delivery/show.php?id=' . $id);
}
