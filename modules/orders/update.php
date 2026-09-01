<?php
/**
 * Laundry Order Update Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/orders/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid order ID provided.');
    redirect('modules/orders/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/orders/edit.php?id=' . $id);
}

// 2. Fetch existing order
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash_message('error', 'The order you are trying to edit does not exist or has been deleted.');
    redirect('modules/orders/index.php');
}

// Admin only restriction on delivered orders
if ($order['status'] === 'delivered' && !is_admin()) {
    set_flash_message('error', 'Delivered orders are locked and can only be modified by an Administrator.');
    redirect('modules/orders/show.php?id=' . $id);
}

// 3. Extract & Sanitize Primary Fields
$customerId   = (int)($_POST['customer_id'] ?? 0);
$expectedDate = sanitize_input($_POST['expected_date'] ?? '');
$orderDate    = sanitize_input($_POST['order_date'] ?? '');
$status       = sanitize_input($_POST['status'] ?? $order['status']);
$notes        = sanitize_input($_POST['notes'] ?? '');
$rawDiscount  = sanitize_input($_POST['discount'] ?? '0');
$rawPaid      = sanitize_input($_POST['paid_amount'] ?? '0');
$rawItems     = $_POST['items'] ?? [];

$validStatuses = ['received', 'processing', 'ready', 'delivered', 'cancelled'];
if (!in_array($status, $validStatuses, true)) {
    $status = $order['status'];
}

$errors = [];

// 4. Validate Customer
if ($customerId <= 0) {
    $errors[] = 'Please select a customer for this order.';
} else {
    $custStmt = $pdo->prepare('SELECT id, name FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $custStmt->execute(['id' => $customerId]);
    $customer = $custStmt->fetch();
    if (!$customer) {
        $errors[] = 'Selected customer does not exist or has been deleted.';
    }
}

// 5. Validate Dates
if (empty($expectedDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedDate)) {
    $errors[] = 'Invalid expected delivery date format.';
}

if (empty($orderDate)) {
    $orderDate = $order['order_date'];
} else {
    $parsedOrderDate = date('Y-m-d H:i:s', strtotime($orderDate));
    if ($parsedOrderDate) {
        $orderDate = $parsedOrderDate;
    } else {
        $orderDate = $order['order_date'];
    }
}

// 6. Authoritative Pricing Recalculation
if (!is_array($rawItems) || empty($rawItems)) {
    $errors[] = 'Please add at least one laundry item to the order.';
}

$processedItems = [];
$subtotal = 0.00;

if (empty($errors)) {
    foreach ($rawItems as $itemRow) {
        $svcId     = (int)($itemRow['service_id'] ?? 0);
        $svcItemId = (int)($itemRow['service_item_id'] ?? 0);
        $qty       = (float)($itemRow['quantity'] ?? 0);

        if ($svcId <= 0) {
            continue;
        }

        if ($qty <= 0) {
            $errors[] = 'Item quantity/weight must be greater than zero.';
            break;
        }

        // Fetch Service from Database
        $svcStmt = $pdo->prepare('SELECT id, name, pricing_type FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $svcStmt->execute(['id' => $svcId]);
        $service = $svcStmt->fetch();

        if (!$service) {
            $errors[] = 'Selected service is unavailable.';
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

            $itStmt = $pdo->prepare('
                SELECT id, item_name, price, unit
                FROM service_items
                WHERE id = :id AND service_id = :service_id AND deleted_at IS NULL LIMIT 1
            ');
            $itStmt->execute(['id' => $svcItemId, 'service_id' => $svcId]);
            $serviceItem = $itStmt->fetch();

            if (!$serviceItem) {
                $errors[] = sprintf('Selected item rate for service "%s" is unavailable.', e($serviceName));
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
        $errors[] = 'Please add at least one valid laundry item.';
    }
}

// 7. Validate Discount & Payments
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
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/orders/edit.php?id=' . $id);
}

// 8. Atomic Database Transaction
try {
    $pdo->beginTransaction();

    // Update Order Master
    $updateOrderStmt = $pdo->prepare('
        UPDATE orders SET
            customer_id    = :customer_id,
            order_date     = :order_date,
            expected_date  = :expected_date,
            status         = :status,
            subtotal       = :subtotal,
            discount       = :discount,
            total          = :total,
            paid_amount    = :paid_amount,
            due_amount     = :due_amount,
            payment_status = :payment_status,
            notes          = :notes,
            updated_at     = NOW()
        WHERE id = :id
    ');

    $updateOrderStmt->execute([
        'customer_id'    => $customerId,
        'order_date'     => $orderDate,
        'expected_date'  => $expectedDate,
        'status'         => $status,
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'total'          => $total,
        'paid_amount'    => $paidAmount,
        'due_amount'     => $dueAmount,
        'payment_status' => $paymentStatus,
        'notes'          => !empty($notes) ? $notes : null,
        'id'             => $id
    ]);

    // Replace Order Items safely
    $deleteItemsStmt = $pdo->prepare('DELETE FROM order_items WHERE order_id = :order_id');
    $deleteItemsStmt->execute(['order_id' => $id]);

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
            'order_id'        => $id,
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

    // 9. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'order_updated',
        sprintf('Updated order %s (Total: %s, Status: %s)', $order['order_number'], format_price($total), $status)
    );

    set_flash_message('success', sprintf('Order <strong>%s</strong> updated successfully.', e($order['order_number'])));
    redirect('modules/orders/show.php?id=' . $id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Order Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update order due to a database error.');
    redirect('modules/orders/edit.php?id=' . $id);
}
