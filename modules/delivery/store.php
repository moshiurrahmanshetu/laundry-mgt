<?php
/**
 * Store Pickup / Delivery Request Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/delivery/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/delivery/create.php');
}

// 2. Extract & Sanitize Input Fields
$type          = strtolower(sanitize_input($_POST['type'] ?? 'delivery'));
$orderId       = (int)($_POST['order_id'] ?? 0);
$contactName   = sanitize_input($_POST['contact_name'] ?? '');
$contactPhone  = sanitize_input($_POST['contact_phone'] ?? '');
$address       = sanitize_input($_POST['address'] ?? '');
$scheduledDate = sanitize_input($_POST['scheduled_date'] ?? '');
$scheduledTime = sanitize_input($_POST['scheduled_time'] ?? '');
$assignedTo    = (int)($_POST['assigned_to'] ?? 0);
$notes         = sanitize_input($_POST['notes'] ?? '');
$createdBy     = (int)($_SESSION['user_id'] ?? 0) ?: null;

$errors = [];

// 3. Validate Type
if (!in_array($type, ['pickup', 'delivery'], true)) {
    $errors[] = 'Invalid request type specified. Must be pickup or delivery.';
}

// 4. Validate Order & Derive Customer
$pdo = getDBConnection();
$order = null;

if ($orderId <= 0) {
    $errors[] = 'Please select a valid order.';
} else {
    $ordStmt = $pdo->prepare('
        SELECT o.id, o.order_number, o.customer_id, o.status, o.order_date,
               c.name AS customer_name
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :id AND o.deleted_at IS NULL
        LIMIT 1
    ');
    $ordStmt->execute(['id' => $orderId]);
    $order = $ordStmt->fetch();

    if (!$order) {
        $errors[] = 'The selected order does not exist or has been deleted.';
    } elseif ($order['status'] === 'cancelled') {
        $errors[] = 'Cannot schedule pickup or delivery for a cancelled order.';
    }
}

// 5. Validate Contact & Address Snapshots
if (empty($contactName)) {
    $errors[] = 'Contact person name is required.';
}
if (empty($contactPhone)) {
    $errors[] = 'Contact phone number is required.';
}
if (empty($address)) {
    $errors[] = 'Pickup / delivery address is required.';
}

// 6. Validate Schedule Date & Time
if (empty($scheduledDate)) {
    $errors[] = 'Scheduled date is required.';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
    $errors[] = 'Invalid scheduled date format.';
}

$formattedTime = null;
if (!empty($scheduledTime)) {
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $scheduledTime)) {
        $formattedTime = date('H:i:s', strtotime($scheduledTime));
    } else {
        $errors[] = 'Invalid scheduled time format.';
    }
}

// 7. Validate Assigned User if provided
$resolvedAssignedTo = null;
if ($assignedTo > 0) {
    // Check permission to assign (Admin & Manager)
    if (!is_admin() && !is_manager()) {
        $resolvedAssignedTo = null; // Ignore unauthorized staff assignment
    } else {
        $uStmt = $pdo->prepare('SELECT id, name, status FROM users WHERE id = :id AND status = "active" LIMIT 1');
        $uStmt->execute(['id' => $assignedTo]);
        $assignedUser = $uStmt->fetch();
        if ($assignedUser) {
            $resolvedAssignedTo = (int)$assignedUser['id'];
        } else {
            $errors[] = 'Selected staff member is inactive or does not exist.';
        }
    }
}

// 8. Prevent Duplicate Active Request for Same Order & Type
if (empty($errors) && $order) {
    $dupStmt = $pdo->prepare('
        SELECT reference_number 
        FROM pickup_deliveries 
        WHERE order_id = :order_id AND type = :type 
          AND status IN ("pending", "assigned", "in_progress") 
          AND deleted_at IS NULL 
        LIMIT 1
    ');
    $dupStmt->execute(['order_id' => $orderId, 'type' => $type]);
    $existingRef = $dupStmt->fetchColumn();

    if ($existingRef) {
        $errors[] = sprintf(
            'An active %s request (<strong>%s</strong>) is already in progress for order <strong>%s</strong>.',
            e($type),
            e($existingRef),
            e($order['order_number'])
        );
    }
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/delivery/create.php?order_id=' . $orderId . '&type=' . $type);
}

// 9. Atomic Database Insertion
try {
    $pdo->beginTransaction();

    $customerId = (int)$order['customer_id'];
    $referenceNumber = generate_pickup_delivery_reference($type, $pdo);
    $initialStatus = ($resolvedAssignedTo !== null) ? 'assigned' : 'pending';

    $insertStmt = $pdo->prepare('
        INSERT INTO pickup_deliveries (
            reference_number, order_id, customer_id, type, address,
            contact_name, contact_phone, scheduled_date, scheduled_time,
            status, assigned_to, notes, created_by, created_at, updated_at
        ) VALUES (
            :reference_number, :order_id, :customer_id, :type, :address,
            :contact_name, :contact_phone, :scheduled_date, :scheduled_time,
            :status, :assigned_to, :notes, :created_by, NOW(), NOW()
        )
    ');

    $insertStmt->execute([
        'reference_number' => $referenceNumber,
        'order_id'         => $orderId,
        'customer_id'      => $customerId,
        'type'             => $type,
        'address'          => $address,
        'contact_name'     => $contactName,
        'contact_phone'    => $contactPhone,
        'scheduled_date'   => $scheduledDate,
        'scheduled_time'   => $formattedTime,
        'status'           => $initialStatus,
        'assigned_to'      => $resolvedAssignedTo,
        'notes'            => !empty($notes) ? $notes : null,
        'created_by'       => $createdBy
    ]);

    $newId = (int)$pdo->lastInsertId();

    $pdo->commit();

    // 10. Log Activity
    log_activity(
        $createdBy,
        'delivery_request_created',
        sprintf('Created %s request %s for order %s', $type, $referenceNumber, $order['order_number'])
    );

    set_flash_message('success', sprintf(
        '%s request <strong>%s</strong> scheduled successfully for order <strong>%s</strong>.',
        ucfirst($type),
        e($referenceNumber),
        e($order['order_number'])
    ));
    redirect('modules/delivery/show.php?id=' . $newId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delivery Store Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to save request due to a database error. Please try again.');
    redirect('modules/delivery/create.php?order_id=' . $orderId . '&type=' . $type);
}
