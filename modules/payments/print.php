<?php
/**
 * Printable Payment Receipt / Voucher
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$paymentId = (int)($_GET['id'] ?? 0);
if ($paymentId <= 0) {
    redirect('modules/payments/index.php');
}

$pdo = getDBConnection();

// Fetch payment details
$stmt = $pdo->prepare('
    SELECT p.*,
           o.order_number, o.total AS order_total, o.paid_amount AS order_paid_amount,
           o.due_amount AS order_due_amount, o.payment_status AS order_payment_status,
           c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
           c.city AS customer_city, c.customer_code,
           u.name AS receiver_name
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.id
    INNER JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.id = :id AND p.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    set_flash_message('error', 'Payment not found.');
    redirect('modules/payments/index.php');
}

// Business Settings
$bizName     = get_setting('business_name', APP_NAME);
$bizPhone    = get_setting('business_phone', '');
$bizEmail    = get_setting('business_email', '');
$bizAddress  = get_setting('business_address', '');
$bizDesc     = get_setting('business_description', 'Official Laundry Payment Voucher');
$bizLogo     = get_setting('business_logo');
$bizLogoUrl  = business_logo_url($bizLogo);
$showLogo    = get_setting('show_business_logo', '1') === '1';
$showAddress = get_setting('show_business_address', '1') === '1';
$showPhone   = get_setting('show_business_phone', '1') === '1';
$footerNote  = get_setting('invoice_footer', 'Thank you for choosing our laundry services! Please keep this receipt for laundry pickup.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?= e($payment['payment_number']) ?> - <?= e($bizName) ?></title>
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
            max-width: 680px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 35px;
        }
        .receipt-brand-icon {
            font-size: 1.8rem;
            color: #0284c7;
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
            <i class="bi bi-printer me-1"></i> Print Receipt Voucher
        </button>
        <a href="<?= base_url('modules/payments/show.php?id=' . (int)$payment['id']) ?>" class="btn btn-outline-secondary px-4 shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Payment
        </a>
    </div>

    <!-- Printable Receipt Card -->
    <div class="receipt-card">
        <!-- Header & Company Info -->
        <div class="row align-items-center mb-4 pb-3 border-bottom">
            <div class="col-7">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <?php if ($showLogo && $bizLogoUrl): ?>
                        <img src="<?= e($bizLogoUrl) ?>" alt="Logo" style="max-height: 44px; max-width: 130px; object-fit: contain;">
                    <?php else: ?>
                        <i class="bi bi-droplet-half receipt-brand-icon"></i>
                    <?php endif; ?>
                    <h1 class="h4 fw-bold text-dark mb-0"><?= e($bizName) ?></h1>
                </div>
                <?php if (!empty($bizDesc)): ?>
                    <p class="text-muted small mb-0"><?= e($bizDesc) ?></p>
                <?php endif; ?>
                <?php if ($showAddress && !empty($bizAddress)): ?>
                    <p class="text-muted small mb-0"><?= e($bizAddress) ?></p>
                <?php endif; ?>
                <?php if ($showPhone && (!empty($bizPhone) || !empty($bizEmail))): ?>
                    <p class="text-muted small mb-0">
                        <?= !empty($bizPhone) ? 'Phone: ' . e($bizPhone) : '' ?>
                        <?= (!empty($bizPhone) && !empty($bizEmail)) ? ' | ' : '' ?>
                        <?= !empty($bizEmail) ? 'Email: ' . e($bizEmail) : '' ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-5 text-end">
                <h2 class="h5 fw-bold text-primary mb-1 font-monospace"><?= e($payment['payment_number']) ?></h2>
                <div class="small text-muted mb-1">Date: <strong><?= e(format_date($payment['payment_date'])) ?></strong></div>
                <div>
                    <?= payment_record_status_badge($payment['status']) ?>
                </div>
            </div>
        </div>

        <!-- Customer & Order Information -->
        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-uppercase text-secondary small fw-bold mb-2">Customer Details</h6>
                <div class="fw-bold text-dark fs-6"><?= e($payment['customer_name']) ?></div>
                <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($payment['customer_phone']) ?></div>
                <div class="text-muted small">Code: <span class="font-monospace"><?= e($payment['customer_code']) ?></span></div>
                <div class="text-muted small"><?= e($payment['customer_address'] ?: 'Counter pickup') ?><?= !empty($payment['customer_city']) ? ', ' . e($payment['customer_city']) : '' ?></div>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase text-secondary small fw-bold mb-2">Order Information</h6>
                <div class="small text-muted mb-1">Order Number: <strong class="text-dark font-monospace"><?= e($payment['order_number']) ?></strong></div>
                <div class="small text-muted mb-1">Payment Method: <strong class="text-dark"><?= e(payment_method_label($payment['payment_method'])) ?></strong></div>
                <?php if (!empty($payment['transaction_reference'])): ?>
                    <div class="small text-muted mb-1">Reference: <strong class="text-dark font-monospace"><?= e($payment['transaction_reference']) ?></strong></div>
                <?php endif; ?>
                <div class="small text-muted">Received By: <strong class="text-dark"><?= e($payment['receiver_name'] ?: 'Staff') ?></strong></div>
            </div>
        </div>

        <!-- Payment Highlight Box -->
        <div class="p-3 bg-light border rounded text-center mb-4">
            <div class="text-muted small text-uppercase fw-semibold mb-1">Amount Paid in this Transaction</div>
            <div class="display-6 fw-bold text-success font-monospace mb-0">
                <?= e(format_price($payment['amount'])) ?>
            </div>
        </div>

        <!-- Order Account Summary Table -->
        <div class="card border mb-4">
            <div class="card-header bg-white py-2">
                <h6 class="mb-0 small fw-bold text-uppercase text-secondary">Updated Order Account Summary</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-borderless mb-0">
                    <tr class="border-bottom">
                        <td class="ps-3 py-2 text-muted">Total Order Amount:</td>
                        <td class="pe-3 py-2 text-end font-monospace fw-semibold"><?= e(format_price($payment['order_total'])) ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="ps-3 py-2 text-muted">Total Payments Recorded:</td>
                        <td class="pe-3 py-2 text-end font-monospace text-success fw-bold"><?= e(format_price($payment['order_paid_amount'])) ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="ps-3 py-2 fw-bold text-dark">Remaining Due Balance:</td>
                        <td class="pe-3 py-2 text-end font-monospace fw-bold <?= (float)$payment['order_due_amount'] > 0 ? 'text-danger fs-6' : 'text-muted' ?>">
                            <?= e(format_price($payment['order_due_amount'])) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="ps-3 py-2 text-muted">Order Payment Status:</td>
                        <td class="pe-3 py-2 text-end"><?= payment_status_badge($payment['order_payment_status']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notes / Remarks -->
        <?php if (!empty($payment['notes'])): ?>
            <div class="p-3 bg-light border rounded mb-4 small">
                <h6 class="small text-uppercase fw-bold text-secondary mb-1">Transaction Remarks:</h6>
                <div class="text-dark"><?= nl2br(e($payment['notes'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center pt-3 border-top text-muted small">
            <p class="mb-1 fw-semibold text-dark"><?= nl2br(e($footerNote)) ?></p>
        </div>
    </div>

</body>
</html>
