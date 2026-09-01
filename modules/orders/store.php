<?php
/**
 * Laundry Order Store Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/orders/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    $_SESSION['old_order_input'] = $_POST;
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/orders/create.php');
}

// 2. Extract & Sanitize Primary Fields
$customerId   = (int)($_POST['customer_id'] ?? 0);
$expectedDate = sanitize_input($_POST['expected_date'] ?? '');
$orderDate    = sanitize_input($_POST['order_date'] ?? '');
$notes        = sanitize_input($_POST['notes'] ?? '');
$rawDiscount  = sanitize_input($_POST['discount'] ?? '0');
$rawPaid      = sanitize_input($_POST['paid_amount'] ?? '0');
$rawItems     = $_POST['items'] ?? [];

$errors = [];

// 3. Validate Customer
$pdo = getDBConnection();
if ($customerId <= 0) {
    $errors[] = 'Please select a customer for this order.';
} else {
    $custStmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE id = :id AND status = "active" AND deleted_at IS NULL LIMIT 1');
    $custStmt->execute(['id' => $customerId]);
    $customer = $custStmt->fetch();
    if (!$customer) {
        $errors[] = 'Selected customer does not exist, is inactive, or has been deleted.';
    }
}

// 4. Validate Dates
if (empty($expectedDate)) {
    $errors[] = 'Expected Delivery Date is required.';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedDate)) {
    $errors[] = 'Invalid expected delivery date format.';
}

if (empty($orderDate)) {
    $orderDate = date('Y-m-d H:i:s');
} else {
    $parsedOrderDate = date('Y-m-d H:i:s', strtotime($orderDate));
    if ($parsedOrderDate) {
        $orderDate = $parsedOrderDate;
    } else {
        $orderDate = date('Y-m-d H:i:s');
    }
}

// 5. Authoritative Pricing Recalculation (Server is the single source of truth)
if (!is_array($rawItems) || empty($rawItems)) {
    $errors[] = 'Please add at least one laundry service/item to the order.';
}

$processedItems = [];
$subtotal = 0.00;

if (empty($errors)) {
    foreach ($rawItems as $itemRow) {
        $svcId     = (int)($itemRow['service_id'] ?? 0);
        $svcItemId = (int)($itemRow['service_item_id'] ?? 0);
        $qty       = (float)($itemRow['quantity'] ?? 0);

        if ($svcId <= 0) {
            continue; // Skip unfilled row
        }

        if ($qty <= 0) {
            $errors[] = 'Item quantity/weight must be greater than zero.';
            break;
        }

        // Fetch Service from Database
        $svcStmt = $pdo->prepare('SELECT id, name, pricing_type, status FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $svcStmt->execute(['id' => $svcId]);
        $service = $svcStmt->fetch();

        if (!$service || $service['status'] !== 'active') {
            $errors[] = 'One or more selected services are inactive or unavailable.';
            break;
        }

        $serviceName = $service['name'];
        $itemName = '';
        $unitPrice = 0.00;
        $resolvedItemId = null;

        if ($service['pricing_type'] === 'per_item') {
            if ($svcItemId <= 0) {
                $errors[] = sprintf('Please select a specific garment for service "%s".', e($serviceName));
                break;
            }

            // Fetch Service Item Rate
            $itStmt = $pdo->prepare('
                SELECT id, item_name, price, unit, status
                FROM service_items
                WHERE id = :id AND service_id = :service_id AND deleted_at IS NULL LIMIT 1
            ');
            $itStmt->execute(['id' => $svcItemId, 'service_id' => $svcId]);
            $serviceItem = $itStmt->fetch();

            if (!$serviceItem || $serviceItem['status'] !== 'active') {
                $errors[] = sprintf('Selected item rate for service "%s" is inactive or unavailable.', e($serviceName));
                break;
            }

            $itemName       = $serviceItem['item_name'];
            $unitPrice      = (float)$serviceItem['price'];
            $resolvedItemId = (int)$serviceItem['id'];

        } else {
            // Per KG pricing
            $itStmt = $pdo->prepare('
                SELECT id, item_name, price, unit
                FROM service_items
                WHERE service_id = :service_id AND deleted_at IS NULL
                ORDER BY sort_order ASC, id ASC LIMIT 1
            ');
            $itStmt->execute(['service_id' => $svcId]);
            $serviceItem = $itStmt->fetch();

            if ($serviceItem) {
                $itemName       = $serviceItem['item_name'];
                $unitPrice      = (float)$serviceItem['price'];
                $resolvedItemId = (int)$serviceItem['id'];
            } else {
                $itemName       = $serviceName . ' (Per KG)';
                $unitPrice      = 0.00;
                $resolvedItemId = null;
            }
        }

        $lineTotal = round($qty * $unitPrice, 2);
        $subtotal += $lineTotal;

        $processedItems[] = [
            'service_id'      => $svcId,
            'service_item_id' => $resolvedItemId,
            'service_name'    => $serviceName,
            'item_name'       => $itemName,
            'quantity'        => $qty,
            'unit_price'      => $unitPrice,
            'line_total'      => $lineTotal,
            'notes'           => null
        ];
    }

    if (empty($errors) && empty($processedItems)) {
        $errors[] = 'Please add at least one valid laundry item to complete the order.';
    }
}

// 6. Validate Discount & Payments
$subtotal = round($subtotal, 2);
$discount = is_numeric($rawDiscount) ? round(max(0, (float)$rawDiscount), 2) : 0.00;

if ($discount > $subtotal) {
    $discount = $subtotal;
}

$total = round(max(0, $subtotal - $discount), 2);

$paidAmount = is_numeric($rawPaid) ? round(max(0, (float)$rawPaid), 2) : 0.00;
if ($paidAmount > $total) {
    $paidAmount = $total;
}

$dueAmount = round(max(0, $total - $paidAmount), 2);

if ($total == 0 || $paidAmount >= $total) {
    $paymentStatus = 'paid';
} elseif ($paidAmount > 0) {
    $paymentStatus = 'partial';
} else {
    $paymentStatus = 'unpaid';
}

if (!empty($errors)) {
    $_SESSION['old_order_input'] = $_POST;
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/orders/create.php');
}

// 7. Atomic Database Transaction
try {
    $pdo->beginTransaction();

    $orderNumber = generate_order_number($pdo);
    $createdBy   = (int)($_SESSION['user_id'] ?? 0) ?: null;

    // Insert Order Master
    $insertOrderStmt = $pdo->prepare('
        INSERT INTO orders (
            order_number, customer_id, order_date, expected_date, status,
            subtotal, discount, total, paid_amount, due_amount, payment_status,
            notes, created_by, created_at, updated_at
        ) VALUES (
            :order_number, :customer_id, :order_date, :expected_date, "received",
            :subtotal, :discount, :total, :paid_amount, :due_amount, :payment_status,
            :notes, :created_by, NOW(), NOW()
        )
    ');

    $insertOrderStmt->execute([
        'order_number'   => $orderNumber,
        'customer_id'    => $customerId,
        'order_date'     => $orderDate,
        'expected_date'  => $expectedDate,
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'total'          => $total,
        'paid_amount'    => $paidAmount,
        'due_amount'     => $dueAmount,
        'payment_status' => $paymentStatus,
        'notes'          => !empty($notes) ? $notes : null,
        'created_by'     => $createdBy
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // Insert Order Items Snapshots
    $insertItemStmt = $pdo->prepare('
        INSERT INTO order_items (
            order_id, service_id, service_item_id, item_name, service_name,
            quantity, unit_price, line_total, notes, created_at, updated_at
        ) VALUES (
            :order_id, :service_id, :service_item_id, :item_name, :service_name,
            :quantity, :unit_price, :line_total, :notes, NOW(), NOW()
        )
    ');

    foreach ($processedItems as $item) {
        $insertItemStmt->execute([
            'order_id'        => $orderId,
            'service_id'      => $item['service_id'],
            'service_item_id' => $item['service_item_id'],
            'item_name'       => $item['item_name'],
            'service_name'    => $item['service_name'],
            'quantity'        => $item['quantity'],
            'unit_price'      => $item['unit_price'],
            'line_total'      => $item['line_total'],
            'notes'           => $item['notes']
        ]);
    }

    $pdo->commit();

    // 8. Log Activity
    log_activity(
        $createdBy,
        'order_created',
        sprintf('Created order %s for customer %s (Total: %s, Paid: %s)', $orderNumber, $customer['name'], format_price($total), format_price($paidAmount))
    );

    set_flash_message('success', sprintf('Laundry order <strong>%s</strong> was created successfully for <strong>%s</strong>.', e($orderNumber), e($customer['name'])));
    redirect('modules/orders/show.php?id=' . $orderId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order Store Error: ' . $e->getMessage());
    $_SESSION['old_order_input'] = $_POST;
    set_flash_message('error', 'Failed to save laundry order due to a database error. Please try again.');
    redirect('modules/orders/create.php');
}
