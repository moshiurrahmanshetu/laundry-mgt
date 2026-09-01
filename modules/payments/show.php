<?php
/**
 * Payment Transaction Details View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$paymentId = (int)($_GET['id'] ?? 0);
if ($paymentId <= 0) {
    set_flash_message('error', 'Invalid payment ID provided.');
    redirect('modules/payments/index.php');
}

$pdo = getDBConnection();
$canVoid = is_admin();

// Fetch payment details with order, customer, and receiver join
$stmt = $pdo->prepare('
    SELECT p.*,
           o.order_number, o.total AS order_total, o.paid_amount AS order_paid_amount,
           o.due_amount AS order_due_amount, o.payment_status AS order_payment_status,
           o.status AS order_lifecycle_status,
           c.id AS customer_id, c.name AS customer_name, c.phone AS customer_phone,
           c.email AS customer_email, c.customer_code,
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
    set_flash_message('error', 'The requested payment transaction does not exist or has been deleted.');
    redirect('modules/payments/index.php');
}

$pageTitle = 'Payment ' . $payment['payment_number'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/payments/index.php') ?>">Payments</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($payment['payment_number']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h2 class="h4 fw-bold text-dark mb-0 font-monospace"><?= e($payment['payment_number']) ?></h2>
            <?= payment_record_status_badge($payment['status']) ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <!-- Print Receipt Voucher -->
            <a href="<?= base_url('modules/payments/print.php?id=' . (int)$payment['id']) ?>" 
               target="_blank" 
               class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </a>

            <!-- View Associated Order -->
            <a href="<?= base_url('modules/orders/show.php?id=' . (int)$payment['order_id']) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-basket me-1"></i> View Order
            </a>

            <!-- Edit Metadata (Admin Only) -->
            <?php if (is_admin()): ?>
                <a href="<?= base_url('modules/payments/edit.php?id=' . (int)$payment['id']) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Notes/Ref
                </a>
            <?php endif; ?>

            <!-- Void Payment (Admin Only, if status is completed) -->
            <?php if ($canVoid && $payment['status'] === 'completed'): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidPaymentModal">
                    <i class="bi bi-slash-circle me-1"></i> Void Payment
                </button>
            <?php endif; ?>

            <!-- Back to Payments -->
            <a href="<?= base_url('modules/payments/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Payment Details Card -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Transaction Summary</h3>
            </div>
            <div class="card-body p-4">
                <div class="text-center p-3 mb-4 bg-light rounded border">
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Amount Paid</div>
                    <div class="display-6 fw-bold font-monospace <?= $payment['status'] === 'completed' ? 'text-success' : 'text-muted text-decoration-line-through' ?>">
                        <?= e(format_price($payment['amount'])) ?>
                    </div>
                    <div class="mt-2">
                        <?= payment_record_status_badge($payment['status']) ?>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-0">
                        <span class="text-muted">Payment Number</span>
                        <span class="font-monospace fw-bold text-dark"><?= e($payment['payment_number']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-0">
                        <span class="text-muted">Payment Method</span>
                        <span class="badge bg-light text-dark border"><?= e(payment_method_label($payment['payment_method'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-0">
                        <span class="text-muted">Transaction Reference</span>
                        <span class="font-monospace text-dark"><?= e($payment['transaction_reference'] ?: '— (None)') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-0">
                        <span class="text-muted">Payment Date &amp; Time</span>
                        <span class="fw-semibold text-dark"><?= e(format_datetime($payment['payment_date'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-0">
                        <span class="text-muted">Received By</span>
                        <span class="fw-semibold text-dark"><?= e($payment['receiver_name'] ?: 'System') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-0">
                        <span class="text-muted">Recorded In System</span>
                        <span class="text-muted"><?= e(format_datetime($payment['created_at'])) ?></span>
                    </li>
                </ul>

                <?php if (!empty($payment['notes'])): ?>
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="small text-uppercase fw-bold text-secondary mb-2">Payment Notes &amp; Remarks:</h6>
                        <div class="p-3 bg-light border rounded text-dark small" style="white-space: pre-line;">
                            <?= e($payment['notes']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Associated Order Summary Card -->
    <div class="col-12 col-lg-5">
        <!-- Order Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-basket me-2 text-primary"></i>Associated Order</h3>
                <a href="<?= base_url('modules/orders/show.php?id=' . (int)$payment['order_id']) ?>" class="small text-decoration-none">
                    View Full Order <i class="bi bi-arrow-up-right"></i>
                </a>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="font-monospace fw-bold fs-5 text-dark"><?= e($payment['order_number']) ?></span>
                    <?= order_status_badge($payment['order_lifecycle_status']) ?>
                </div>

                <div class="small text-muted mb-1">Customer:</div>
                <div class="fw-bold text-dark fs-6 mb-1"><?= e($payment['customer_name']) ?></div>
                <div class="small text-muted mb-3">
                    <i class="bi bi-telephone me-1"></i><?= e($payment['customer_phone']) ?>
                    <span class="badge bg-light text-secondary border font-monospace ms-2"><?= e($payment['customer_code']) ?></span>
                </div>

                <hr class="my-3">

                <!-- Financial Status Breakdown -->
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Order Total Amount:</span>
                    <span class="font-monospace fw-bold text-dark"><?= e(format_price($payment['order_total'])) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Total Paid to Date:</span>
                    <span class="font-monospace text-success fw-bold"><?= e(format_price($payment['order_paid_amount'])) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3 small">
                    <span class="text-muted">Remaining Due Balance:</span>
                    <span class="font-monospace <?= (float)$payment['order_due_amount'] > 0 ? 'text-danger fw-bold fs-6' : 'text-muted' ?>">
                        <?= e(format_price($payment['order_due_amount'])) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded border">
                    <span class="small text-muted">Order Payment Status:</span>
                    <?= payment_status_badge($payment['order_payment_status']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Void Payment Confirmation (Admin Only) -->
<?php if ($canVoid && $payment['status'] === 'completed'): ?>
<div class="modal fade" id="voidPaymentModal" tabindex="-1" aria-labelledby="voidPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/payments/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$payment['id'] ?>">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="voidPaymentModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Void Payment Transaction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to void payment <strong class="font-monospace"><?= e($payment['payment_number']) ?></strong> of <strong class="text-dark font-monospace"><?= e(format_price($payment['amount'])) ?></strong>?</p>
                    <p class="small text-muted mb-0">This payment will be marked as voided. The order due balance will automatically be updated.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-slash-circle me-1"></i> Confirm Void
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
