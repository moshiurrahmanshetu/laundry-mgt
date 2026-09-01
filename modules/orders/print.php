<?php
/**
 * Printable Laundry Order Receipt / Invoice
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    redirect('modules/orders/index.php');
}

$pdo = getDBConnection();

// Fetch order details with customer
$stmt = $pdo->prepare('
    SELECT o.*, 
           c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           c.address AS customer_address, c.city AS customer_city, c.customer_code,
           u.name AS creator_name
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON o.created_by = u.id
    WHERE o.id = :id AND o.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash_message('error', 'Order not found.');
    redirect('modules/orders/index.php');
}

// Fetch order line items
$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
$itemStmt->execute(['order_id' => $orderId]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?= e($order['order_number']) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 30px 15px;
        }
        .receipt-card {
            background: #ffffff;
            max-width: 780px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }
        .receipt-brand-icon {
            font-size: 2rem;
            color: #0284c7;
        }
        .table-receipt th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .receipt-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Action Toolbar (Hidden during Print) -->
    <div class="no-print text-center mb-4">
        <button type="button" class="btn btn-primary px-4 me-2 shadow-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Invoice / Receipt
        </button>
        <a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>" class="btn btn-outline-secondary px-4 shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Order
        </a>
    </div>

    <!-- Printable Receipt Card -->
    <div class="receipt-card">
        <!-- Header & Store Info -->
        <div class="row align-items-center mb-4 pb-3 border-bottom">
            <div class="col-7">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-droplet-half receipt-brand-icon"></i>
                    <h1 class="h4 fw-bold text-dark mb-0"><?= e(APP_NAME) ?></h1>
                </div>
                <p class="text-muted small mb-0">Professional Laundry &amp; Dry Cleaning Services</p>
                <p class="text-muted small mb-0">Phone: +880 1700 000000 | Email: support@laundrymgt.com</p>
            </div>
            <div class="col-5 text-end">
                <h2 class="h5 fw-bold text-primary mb-1 font-monospace"><?= e($order['order_number']) ?></h2>
                <div class="small text-muted mb-1">Date: <strong><?= date('M d, Y', strtotime($order['order_date'])) ?></strong></div>
                <div>
                    <?= order_status_badge($order['status']) ?>
                    <?= payment_status_badge($order['payment_status']) ?>
                </div>
            </div>
        </div>

        <!-- Customer & Schedule Information -->
        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-uppercase text-secondary small fw-bold mb-2">Customer Details</h6>
                <div class="fw-bold text-dark fs-6"><?= e($order['customer_name']) ?></div>
                <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></div>
                <?php if (!empty($order['customer_email'])): ?>
                    <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= e($order['customer_email']) ?></div>
                <?php endif; ?>
                <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($order['customer_address'] ?: 'Counter pickup') ?><?= !empty($order['customer_city']) ? ', ' . e($order['customer_city']) : '' ?></div>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase text-secondary small fw-bold mb-2">Order Information</h6>
                <div class="small text-muted mb-1">Intake Time: <strong class="text-dark"><?= date('h:i A', strtotime($order['order_date'])) ?></strong></div>
                <div class="small text-muted mb-1">Expected Ready: <strong class="text-dark fs-6"><?= date('M d, Y', strtotime($order['expected_date'])) ?></strong></div>
                <div class="small text-muted">Handled By: <strong class="text-dark"><?= e($order['creator_name'] ?: 'Staff') ?></strong></div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-receipt align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Service / Garment Description</th>
                        <th class="text-center" style="width: 100px;">Qty / Wt</th>
                        <th class="text-end" style="width: 120px;">Unit Rate</th>
                        <th class="text-end" style="width: 130px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td class="text-muted small"><?= $idx + 1 ?></td>
                            <td>
                                <strong class="text-dark"><?= e($it['item_name']) ?></strong>
                                <span class="badge bg-light text-muted border ms-1 font-monospace"><?= e($it['service_name']) ?></span>
                                <?php if (!empty($it['notes'])): ?>
                                    <div class="small text-muted fst-italic"><?= e($it['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center font-monospace">
                                <?= (float)$it['quantity'] == (int)$it['quantity'] ? (int)$it['quantity'] : number_format((float)$it['quantity'], 2) ?>
                            </td>
                            <td class="text-end font-monospace text-muted small">
                                <?= e(format_price($it['unit_price'])) ?>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                <?= e(format_price($it['line_total'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Financial Summary -->
        <div class="row justify-content-end mb-4">
            <div class="col-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Subtotal:</td>
                        <td class="text-end font-monospace fw-semibold"><?= e(format_price($order['subtotal'])) ?></td>
                    </tr>
                    <?php if ((float)$order['discount'] > 0): ?>
                        <tr>
                            <td class="text-muted">Discount Applied:</td>
                            <td class="text-end font-monospace text-success">- <?= e(format_price($order['discount'])) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="border-top">
                        <td class="fw-bold text-dark fs-6">Grand Total:</td>
                        <td class="text-end font-monospace fw-bold text-primary fs-5"><?= e(format_price($order['total'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Paid Amount:</td>
                        <td class="text-end font-monospace text-success fw-semibold"><?= e(format_price($order['paid_amount'])) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold text-danger">Due Balance:</td>
                        <td class="text-end font-monospace fw-bold text-danger fs-6"><?= e(format_price($order['due_amount'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notes / Special Instructions -->
        <?php if (!empty($order['notes'])): ?>
            <div class="p-3 bg-light border rounded mb-4">
                <h6 class="small text-uppercase fw-bold text-secondary mb-1">Special Handling Instructions:</h6>
                <div class="small text-dark"><?= nl2br(e($order['notes'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center pt-3 border-top text-muted small">
            <p class="mb-1 fw-semibold text-dark">Thank you for choosing <?= e(APP_NAME) ?>!</p>
            <p class="mb-0">Please present this receipt when collecting your laundry. Claim within 30 days of ready date.</p>
        </div>
    </div>

</body>
</html>
