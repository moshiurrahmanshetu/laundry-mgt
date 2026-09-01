<?php
/**
 * Printable Pickup / Delivery Slip
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid request ID.');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('
    SELECT pd.*,
           o.order_number, o.order_date, o.expected_date, o.total, o.paid_amount, o.due_amount,
           c.name AS customer_name, c.customer_code, c.phone AS customer_profile_phone,
           u_assigned.name AS assigned_staff_name, u_assigned.phone AS assigned_staff_phone,
           u_created.name AS creator_name
    FROM pickup_deliveries pd
    INNER JOIN orders o ON pd.order_id = o.id
    INNER JOIN customers c ON pd.customer_id = c.id
    LEFT JOIN users u_assigned ON pd.assigned_to = u_assigned.id
    LEFT JOIN users u_created ON pd.created_by = u_created.id
    WHERE pd.id = :id AND pd.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    die('Record not found or has been deleted.');
}

// Fetch order items summary for driver reference
$itemStmt = $pdo->prepare('
    SELECT item_name, service_name, quantity, line_total
    FROM order_items
    WHERE order_id = :order_id
    ORDER BY id ASC
');
$itemStmt->execute(['order_id' => $req['order_id']]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($req['reference_number']) ?> — <?= ucfirst($req['type']) ?> Slip</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #212529;
            background-color: #f8f9fa;
        }
        .slip-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        .slip-header {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .section-title {
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
            margin-top: 50px;
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
            .slip-container {
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
        <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$req['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Details
        </a>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i> Print Slip
            </button>
        </div>
    </div>

    <!-- Printable Slip Document -->
    <div class="slip-container">
        <!-- Header -->
        <div class="slip-header d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h4 fw-bold text-dark mb-1"><i class="bi bi-droplet-half text-primary me-1"></i><?= e(APP_NAME) ?></h1>
                <p class="text-muted small mb-0">Professional Laundry &amp; Dry Cleaning Services</p>
            </div>
            <div class="text-end">
                <span class="badge <?= $req['type'] === 'pickup' ? 'bg-info' : 'bg-primary' ?> text-uppercase fs-6 px-3 py-1">
                    <?= ucfirst($req['type']) ?> Slip
                </span>
                <div class="font-monospace fw-bold fs-5 text-dark mt-1"><?= e($req['reference_number']) ?></div>
                <div class="text-muted small">Printed: <?= date('M d, Y h:i A') ?></div>
            </div>
        </div>

        <!-- Order & Schedule Overview -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="section-title">Schedule Information</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0" style="width: 120px;">Scheduled Date:</td>
                        <td class="fw-bold"><?= e(format_datetime($req['scheduled_date'], 'M d, Y')) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Estimated Time:</td>
                        <td class="fw-semibold font-monospace"><?= !empty($req['scheduled_time']) ? date('h:i A', strtotime($req['scheduled_time'])) : 'Any Time' ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Assigned Staff:</td>
                        <td class="fw-semibold text-dark"><?= e($req['assigned_staff_name'] ?: 'Unassigned') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Current Status:</td>
                        <td class="fw-bold text-uppercase"><?= e($req['status']) ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-6">
                <div class="section-title">Order Account</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0" style="width: 120px;">Order Number:</td>
                        <td class="font-monospace fw-bold"><?= e($req['order_number']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Order Total:</td>
                        <td class="font-monospace"><?= e(format_price($req['total'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Paid Amount:</td>
                        <td class="font-monospace text-success"><?= e(format_price($req['paid_amount'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Due Balance:</td>
                        <td class="font-monospace <?= (float)$req['due_amount'] > 0 ? 'fw-bold text-danger' : 'text-muted' ?>">
                            <?= e(format_price($req['due_amount'])) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Customer & Delivery Location -->
        <div class="mb-4">
            <div class="section-title">Customer Contact &amp; Service Address</div>
            <div class="p-3 bg-light rounded border">
                <div class="row g-2 small">
                    <div class="col-12 col-md-6">
                        <span class="text-muted d-block">Customer / Contact Person:</span>
                        <strong class="text-dark fs-6"><?= e($req['contact_name']) ?></strong> (<?= e($req['customer_code']) ?>)
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted d-block">Contact Phone:</span>
                        <strong class="font-monospace text-dark fs-6"><?= e($req['contact_phone']) ?></strong>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <span class="text-muted d-block">Full Service Address:</span>
                        <div class="fw-semibold text-dark"><?= e($req['address']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items Breakdown -->
        <div class="mb-4">
            <div class="section-title">Ordered Laundry Garments / Packages</div>
            <table class="table table-sm table-bordered align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Service</th>
                        <th>Item Description</th>
                        <th class="text-center" style="width: 100px;">Qty / Weight</th>
                        <th class="text-end" style="width: 120px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $idx + 1 ?></td>
                            <td><?= e($it['service_name']) ?></td>
                            <td class="fw-semibold"><?= e($it['item_name']) ?></td>
                            <td class="text-center font-monospace"><?= (float)$it['quantity'] == (int)$it['quantity'] ? (int)$it['quantity'] : number_format((float)$it['quantity'], 2) ?></td>
                            <td class="text-end font-monospace"><?= e(format_price($it['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Handling Instructions -->
        <?php if (!empty($req['notes'])): ?>
            <div class="mb-4">
                <div class="section-title">Special Handling Instructions</div>
                <div class="p-2 border rounded small bg-light">
                    <?= e($req['notes']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Signature Section -->
        <div class="row pt-4">
            <div class="col-6">
                <div class="signature-box">
                    Assigned Staff / Driver Signature
                </div>
            </div>
            <div class="col-6">
                <div class="signature-box">
                    Customer / Recipient Signature
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
