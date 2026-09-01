<?php
/**
 * Printable Laundry Operations Work Order & Summary Slip
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    die('Invalid order ID.');
}

$pdo = getDBConnection();

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
    die('Order not found or has been deleted.');
}

// Fetch line items
$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
$itemStmt->execute(['order_id' => $orderId]);
$items = $itemStmt->fetchAll();

// Fetch delivery records
$delStmt = $pdo->prepare('
    SELECT pd.*, u.name AS assigned_staff_name
    FROM pickup_deliveries pd
    LEFT JOIN users u ON pd.assigned_to = u.id
    WHERE pd.order_id = :order_id AND pd.deleted_at IS NULL
    ORDER BY pd.scheduled_date ASC
');
$delStmt->execute(['order_id' => $orderId]);
$deliveries = $delStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($order['order_number']) ?> — Operations Work Order</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #212529;
            background-color: #f8f9fa;
        }
        .work-order-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        .order-header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .section-heading {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            font-weight: 700;
            margin-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 4px;
        }
        .signature-box {
            border-top: 1px dashed #adb5bd;
            padding-top: 5px;
            margin-top: 40px;
            text-align: center;
            font-size: 0.85rem;
            color: #495057;
        }
        @media print {
            body {
                background: #ffffff;
                margin: 0;
                padding: 0;
            }
            .work-order-container {
                border: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container py-3">
    <!-- Non-printable Toolbar -->
    <div class="no-print d-flex justify-content-between align-items-center mb-3 p-2 bg-white rounded border shadow-sm" style="max-width: 800px; margin: 0 auto;">
        <a href="<?= base_url('modules/operations/show.php?id=' . (int)$order['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Operations
        </a>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i> Print Work Order
            </button>
        </div>
    </div>

    <!-- Printable Document -->
    <div class="work-order-container">
        <!-- Header -->
        <div class="order-header d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h4 fw-bold text-dark mb-1"><i class="bi bi-droplet-half text-primary me-1"></i><?= e(APP_NAME) ?></h1>
                <p class="text-muted small mb-0">Laundry Operations Work Order &amp; Processing Slip</p>
            </div>
            <div class="text-end">
                <span class="badge bg-primary text-uppercase fs-6 px-3 py-1">Work Order</span>
                <div class="font-monospace fw-bold fs-5 text-dark mt-1"><?= e($order['order_number']) ?></div>
                <div class="text-muted small">Printed: <?= date('M d, Y h:i A') ?></div>
            </div>
        </div>

        <!-- Order & Customer Overview Grid -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="section-heading">Customer Information</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0" style="width: 110px;">Customer:</td>
                        <td class="fw-bold text-dark"><?= e($order['customer_name']) ?> <span class="badge bg-light text-secondary border font-monospace"><?= e($order['customer_code']) ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Phone:</td>
                        <td class="font-monospace fw-semibold text-dark"><?= e($order['customer_phone']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Address:</td>
                        <td class="text-dark"><?= e($order['customer_address'] ?: 'In-store drop off') ?><?= !empty($order['customer_city']) ? ', ' . e($order['customer_city']) : '' ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-6">
                <div class="section-heading">Operational Tracking</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0" style="width: 120px;">Intake Date:</td>
                        <td class="fw-semibold"><?= e(format_datetime($order['order_date'], 'M d, Y h:i A')) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Expected Delivery:</td>
                        <td class="fw-bold text-dark"><?= e(format_datetime($order['expected_date'], 'M d, Y')) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Current Stage:</td>
                        <td class="fw-bold text-uppercase"><?= e($order['status']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Payment Balance:</td>
                        <td class="font-monospace <?= (float)$order['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            <?= e(format_price($order['due_amount'])) ?> (<?= strtoupper($order['payment_status']) ?>)
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Garments & Services Checklist -->
        <div class="mb-4">
            <div class="section-heading">Garments &amp; Laundry Service Checklist</div>
            <table class="table table-sm table-bordered align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Service Category</th>
                        <th>Garment / Item Description</th>
                        <th class="text-center" style="width: 100px;">Qty / Weight</th>
                        <th class="text-end" style="width: 110px;">Unit Price</th>
                        <th class="text-end" style="width: 110px;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $idx + 1 ?></td>
                            <td><?= e($it['service_name']) ?></td>
                            <td>
                                <span class="fw-semibold"><?= e($it['item_name']) ?></span>
                                <?php if (!empty($it['notes'])): ?>
                                    <div class="text-muted fst-italic" style="font-size: 0.75rem;"><?= e($it['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center font-monospace">
                                <?= (float)$it['quantity'] == (int)$it['quantity'] ? (int)$it['quantity'] : number_format((float)$it['quantity'], 2) ?>
                            </td>
                            <td class="text-end font-monospace text-muted"><?= e(format_price($it['unit_price'])) ?></td>
                            <td class="text-end font-monospace fw-bold text-dark"><?= e(format_price($it['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-bold">Grand Total:</td>
                        <td class="text-end font-monospace fw-bold text-primary"><?= e(format_price($order['total'])) ?></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end text-muted">Paid to Date:</td>
                        <td class="text-end font-monospace text-success"><?= e(format_price($order['paid_amount'])) ?></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end text-muted">Remaining Due:</td>
                        <td class="text-end font-monospace <?= (float)$order['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                            <?= e(format_price($order['due_amount'])) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Logistics Tracking (if any) -->
        <?php if (!empty($deliveries)): ?>
            <div class="mb-4">
                <div class="section-heading">Logistics &amp; Dispatch Schedules</div>
                <table class="table table-sm table-bordered small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ref #</th>
                            <th>Type</th>
                            <th>Scheduled Date</th>
                            <th>Assigned Staff</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveries as $del): ?>
                            <tr>
                                <td class="font-monospace"><?= e($del['reference_number']) ?></td>
                                <td class="text-uppercase"><?= e($del['type']) ?></td>
                                <td><?= e(format_datetime($del['scheduled_date'], 'M d, Y')) ?> <?= !empty($del['scheduled_time']) ? '(' . date('h:i A', strtotime($del['scheduled_time'])) . ')' : '' ?></td>
                                <td><?= e($del['assigned_staff_name'] ?: 'Unassigned') ?></td>
                                <td class="text-uppercase"><?= e($del['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Handling Instructions -->
        <?php if (!empty($order['notes'])): ?>
            <div class="mb-4">
                <div class="section-heading">Handling &amp; Special Processing Notes</div>
                <div class="p-2 border rounded small bg-light">
                    <?= e($order['notes']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="row pt-4">
            <div class="col-6">
                <div class="signature-box">
                    Laundry Operator / Quality Inspector
                </div>
            </div>
            <div class="col-6">
                <div class="signature-box">
                    Customer / Delivery Recipient
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
