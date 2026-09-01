<?php
/**
 * Edit Payment Metadata View (Admin Only)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Role Guard: Administrator only
require_role('administrator', 'modules/payments/index.php');

$paymentId = (int)($_GET['id'] ?? 0);
if ($paymentId <= 0) {
    set_flash_message('error', 'Invalid payment ID provided.');
    redirect('modules/payments/index.php');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('
    SELECT p.*, o.order_number, c.name AS customer_name
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.id
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE p.id = :id AND p.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    set_flash_message('error', 'The payment transaction does not exist or has been deleted.');
    redirect('modules/payments/index.php');
}

if ($payment['status'] === 'voided') {
    set_flash_message('error', 'Voided payments are locked and cannot be edited.');
    redirect('modules/payments/show.php?id=' . $paymentId);
}

$pageTitle = 'Edit Payment ' . $payment['payment_number'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/payments/index.php') ?>">Payments</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/payments/show.php?id=' . (int)$payment['id']) ?>"><?= e($payment['payment_number']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-0">Edit Payment: <span class="font-monospace text-primary"><?= e($payment['payment_number']) ?></span></h2>
            <span class="text-muted small">Customer: <strong><?= e($payment['customer_name']) ?></strong> (Order: <span class="font-monospace"><?= e($payment['order_number']) ?></span>)</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/payments/show.php?id=' . (int)$payment['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i> View Payment
            </a>
            <a href="<?= base_url('modules/payments/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Update Payment Metadata</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/payments/update.php') ?>" method="POST" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$payment['id'] ?>">

                    <!-- Readonly Financial Fields -->
                    <div class="row g-3 mb-3 p-3 bg-light rounded border">
                        <div class="col-6">
                            <label class="small text-muted d-block">Payment Amount</label>
                            <span class="font-monospace fw-bold text-success fs-5"><?= e(format_price($payment['amount'])) ?></span>
                            <div class="small text-muted" style="font-size: 0.75rem;">(Immutable financial record)</div>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted d-block">Linked Order</label>
                            <span class="font-monospace fw-bold text-dark fs-6"><?= e($payment['order_number']) ?></span>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-3">
                        <label for="payment_method" class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="cash" <?= $payment['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="card" <?= $payment['payment_method'] === 'card' ? 'selected' : '' ?>>Credit / Debit Card</option>
                            <option value="mobile_banking" <?= $payment['payment_method'] === 'mobile_banking' ? 'selected' : '' ?>>Mobile Banking</option>
                            <option value="bank_transfer" <?= $payment['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="other" <?= $payment['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <!-- Transaction Reference -->
                    <div class="mb-3">
                        <label for="transaction_reference" class="form-label small text-muted">Transaction Reference / ID</label>
                        <input type="text" 
                               class="form-control font-monospace" 
                               id="transaction_reference" 
                               name="transaction_reference" 
                               value="<?= e($payment['transaction_reference'] ?? '') ?>">
                    </div>

                    <!-- Payment Date & Time -->
                    <div class="mb-3">
                        <label for="payment_date" class="form-label small text-muted">Payment Date &amp; Time</label>
                        <input type="datetime-local" 
                               class="form-control" 
                               id="payment_date" 
                               name="payment_date" 
                               value="<?= date('Y-m-d\TH:i', strtotime($payment['payment_date'])) ?>" 
                               required>
                    </div>

                    <!-- Payment Notes -->
                    <div class="mb-4">
                        <label for="notes" class="form-label small text-muted">Payment Notes</label>
                        <textarea class="form-control" 
                                  id="notes" 
                                  name="notes" 
                                  rows="3"><?= e($payment['notes'] ?? '') ?></textarea>
                    </div>

                    <!-- Submit & Cancel -->
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('modules/payments/show.php?id=' . (int)$payment['id']) ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
