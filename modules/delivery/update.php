<?php
/**
 * Update Pickup / Delivery Request Action Handler
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

$id            = (int)($_POST['id'] ?? 0);
$contactName   = sanitize_input($_POST['contact_name'] ?? '');
$contactPhone  = sanitize_input($_POST['contact_phone'] ?? '');
$address       = sanitize_input($_POST['address'] ?? '');
$scheduledDate = sanitize_input($_POST['scheduled_date'] ?? '');
$scheduledTime = sanitize_input($_POST['scheduled_time'] ?? '');
$assignedTo    = (int)($_POST['assigned_to'] ?? 0);
$notes         = sanitize_input($_POST['notes'] ?? '');
$currentUser   = current_user();

if ($id <= 0) {
    set_flash_message('error', 'Invalid request ID provided.');
    redirect('modules/delivery/index.php');
}

$pdo = getDBConnection();

// Fetch existing record
$stmt = $pdo->prepare('SELECT * FROM pickup_deliveries WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    set_flash_message('error', 'The requested record does not exist or has been deleted.');
    redirect('modules/delivery/index.php');
}

$errors = [];

if (empty($contactName)) {
    $errors[] = 'Contact person name is required.';
}
if (empty($contactPhone)) {
    $errors[] = 'Contact phone number is required.';
}
if (empty($address)) {
    $errors[] = 'Pickup / delivery address is required.';
}
if (empty($scheduledDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
    $errors[] = 'Valid scheduled date is required.';
}

$formattedTime = null;
if (!empty($scheduledTime)) {
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $scheduledTime)) {
        $formattedTime = date('H:i:s', strtotime($scheduledTime));
    } else {
        $errors[] = 'Invalid scheduled time format.';
    }
}

$resolvedAssignedTo = null;
if ($assignedTo > 0) {
    $uStmt = $pdo->prepare('SELECT id, name, status FROM users WHERE id = :id AND status = "active" LIMIT 1');
    $uStmt->execute(['id' => $assignedTo]);
    $assignedUser = $uStmt->fetch();
    if ($assignedUser) {
        $resolvedAssignedTo = (int)$assignedUser['id'];
    } else {
        $errors[] = 'Selected staff member is inactive or does not exist.';
    }
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/delivery/edit.php?id=' . $id);
}

try {
    $newStatus = $req['status'];
    if ($req['status'] === 'pending' && $resolvedAssignedTo !== null) {
        $newStatus = 'assigned';
    }

    $updateStmt = $pdo->prepare('
        UPDATE pickup_deliveries SET
            contact_name   = :contact_name,
            contact_phone  = :contact_phone,
            address        = :address,
            scheduled_date = :scheduled_date,
            scheduled_time = :scheduled_time,
            assigned_to    = :assigned_to,
            status         = :status,
            notes          = :notes,
            updated_at     = NOW()
        WHERE id = :id
    ');

    $updateStmt->execute([
        'contact_name'   => $contactName,
        'contact_phone'  => $contactPhone,
        'address'        => $address,
        'scheduled_date' => $scheduledDate,
        'scheduled_time' => $formattedTime,
        'assigned_to'    => $resolvedAssignedTo,
        'status'         => $newStatus,
        'notes'          => !empty($notes) ? $notes : null,
        'id'             => $id
    ]);

    log_activity(
        $currentUser['id'] ?? null,
        'delivery_request_updated',
        sprintf('Updated %s request %s schedule/contact details', $req['type'], $req['reference_number'])
    );

    set_flash_message('success', sprintf('Request <strong>%s</strong> updated successfully.', e($req['reference_number'])));
    redirect('modules/delivery/show.php?id=' . $id);

} catch (PDOException $e) {
    error_log('Delivery Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update request due to a database error.');
    redirect('modules/delivery/edit.php?id=' . $id);
}
