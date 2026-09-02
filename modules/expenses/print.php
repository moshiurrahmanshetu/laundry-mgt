<?php
/**
 * Printable Expense Payment Voucher
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$expenseId = (int)($_GET['id'] ?? 0);
if ($expenseId <= 0) {
    die('Invalid expense ID.');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('
    SELECT e.*, ec.name AS category_name, ec.description AS category_description, u.name AS creator_name
    FROM expenses e
    INNER JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = :id AND e.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $expenseId]);
$expense = $stmt->fetch();

if (!$expense) {
    die('Expense record not found or has been deleted.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($expense['reference_number']) ?> — Expense Voucher</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #212529;
            background-color: #f8f9fa;
        }
        .voucher-container {
            max-width: 750px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        .voucher-header {
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
            .voucher-container {
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
    <div class="no-print d-flex justify-content-between align-items-center mb-3 p-2 bg-white rounded border shadow-sm" style="max-width: 750px; margin: 0 auto;">
        <a href="<?= base_url('modules/expenses/show.php?id=' . (int)$expense['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Expense
        </a>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i> Print Voucher
            </button>
        </div>
    </div>

    <!-- Printable Document -->
    <div class="voucher-container">
        <!-- Header -->
        <div class="voucher-header d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h4 fw-bold text-dark mb-1"><i class="bi bi-droplet-half text-primary me-1"></i><?= e(APP_NAME) ?></h1>
                <p class="text-muted small mb-0">Business Operating Expense Voucher</p>
            </div>
            <div class="text-end">
                <span class="badge bg-dark text-uppercase fs-6 px-3 py-1">Payment Voucher</span>
                <div class="font-monospace fw-bold fs-5 text-dark mt-1"><?= e($expense['reference_number']) ?></div>
                <div class="text-muted small">Printed: <?= date('M d, Y h:i A') ?></div>
            </div>
        </div>

        <!-- Amount Box -->
        <div class="p-3 mb-4 rounded border text-center bg-light">
            <div class="text-muted small text-uppercase fw-semibold">Disbursed Expense Amount</div>
            <div class="display-6 fw-bold text-dark font-monospace mb-0"><?= e(format_price($expense['amount'])) ?></div>
            <div class="text-muted small">Channel: <?= e(payment_method_label($expense['payment_method'])) ?></div>
        </div>

        <!-- Details Grid -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="section-heading">Expense Information</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0" style="width: 120px;">Category:</td>
                        <td class="fw-bold text-dark"><?= e($expense['category_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Incurred Date:</td>
                        <td class="font-monospace fw-semibold text-dark"><?= e(format_datetime($expense['expense_date'], 'M d, Y')) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Paid By:</td>
                        <td class="text-dark"><?= e($expense['paid_by'] ?: 'Company Account') ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-6">
                <div class="section-heading">Audit &amp; Authorization</div>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted ps-0" style="width: 120px;">Recorded By:</td>
                        <td class="text-dark"><?= e($expense['creator_name'] ?: 'System') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Entry Timestamp:</td>
                        <td class="text-muted"><?= e(format_datetime($expense['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Status:</td>
                        <td class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>DISBURSED / COMPLETED</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Description / Memo -->
        <?php if (!empty($expense['description'])): ?>
            <div class="mb-4">
                <div class="section-heading">Description &amp; Billing Notes</div>
                <div class="p-3 border rounded small bg-light text-dark" style="white-space: pre-line;">
                    <?= e($expense['description']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Signature Boxes -->
        <div class="row pt-4">
            <div class="col-4">
                <div class="signature-box">
                    Disbursed / Paid By
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    Authorized Manager
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    Payee / Recipient Signature
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
