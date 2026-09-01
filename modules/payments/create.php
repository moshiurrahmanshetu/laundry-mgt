<?php
/**
 * Receive / Record Payment View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Receive Payment';
$pdo = getDBConnection();

$preselectedOrderId = (int)($_GET['order_id'] ?? 0);
$selectedOrder = null;

// If specific order ID requested
if ($preselectedOrderId > 0) {
    $stmt = $pdo->prepare('
        SELECT o.id, o.order_number, o.total, o.paid_amount, o.due_amount, o.payment_status, o.status,
               c.name AS customer_name, c.phone AS customer_phone, c.customer_code
        FROM orders o
        INNER JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :id AND o.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->execute(['id' => $preselectedOrderId]);
    $selectedOrder = $stmt->fetch();

    if (!$selectedOrder) {
        set_flash_message('error', 'The requested order does not exist or has been deleted.');
        redirect('modules/payments/index.php');
    }

    if ($selectedOrder['status'] === 'cancelled') {
        set_flash_message('error', 'Cannot accept payments for cancelled orders.');
        redirect('modules/orders/show.php?id=' . $preselectedOrderId);
    }
}

// Fetch all unpaid or partially paid active orders
$ordersStmt = $pdo->query('
    SELECT o.id, o.order_number, o.total, o.paid_amount, o.due_amount, o.payment_status,
           c.name AS customer_name, c.phone AS customer_phone, c.customer_code
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE o.due_amount > 0 AND o.status != "cancelled" AND o.deleted_at IS NULL
    ORDER BY o.id DESC
');
$payableOrders = $ordersStmt->fetchAll();

$defaultPaymentDate = date('Y-m-d\TH:i');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/payments/index.php') ?>">Payments</a></li>
            <li class="breadcrumb-item active" aria-current="page">Receive Payment</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 fw-bold text-dark mb-0">Record Payment</h2>
        <a href="<?= $selectedOrder ? base_url('modules/orders/show.php?id=' . (int)$selectedOrder['id']) : base_url('modules/payments/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<?php if ($selectedOrder && (float)$selectedOrder['due_amount'] <= 0): ?>
    <div class="alert alert-success d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <strong>This order is already fully paid!</strong> Order <span class="font-monospace fw-bold"><?= e($selectedOrder['order_number']) ?></span> has a zero due balance.
        </div>
        <a href="<?= base_url('modules/orders/show.php?id=' . (int)$selectedOrder['id']) ?>" class="btn btn-success btn-sm">
            <i class="bi bi-eye me-1"></i> View Order
        </a>
    </div>
<?php elseif (empty($payableOrders) && !$selectedOrder): ?>
    <div class="alert alert-info d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <strong>No outstanding payments due.</strong> All active customer orders are currently fully paid.
        </div>
        <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-basket me-1"></i> View Orders
        </a>
    </div>
<?php else: ?>

<form action="<?= base_url('modules/payments/store.php') ?>" method="POST" id="paymentForm" autocomplete="off">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left 7 Columns: Order Selection, Amount, Method & Reference -->
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Payment Transaction Details</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Select Order -->
                        <div class="col-12">
                            <label for="order_id" class="form-label fw-semibold">Select Order <span class="text-danger">*</span></label>
                            <?php if ($selectedOrder): ?>
                                <input type="hidden" name="order_id" id="order_id" value="<?= (int)$selectedOrder['id'] ?>">
                                <div class="p-3 bg-light border rounded d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="font-monospace fw-bold text-dark fs-6"><?= e($selectedOrder['order_number']) ?></div>
                                        <div class="small text-muted"><?= e($selectedOrder['customer_name']) ?> (<?= e($selectedOrder['customer_phone']) ?>)</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Current Due</div>
                                        <div class="font-monospace fw-bold text-danger fs-5"><?= e(format_price($selectedOrder['due_amount'])) ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <select name="order_id" id="order_id" class="form-select" required autofocus>
                                    <option value="">-- Choose Order to Pay --</option>
                                    <?php foreach ($payableOrders as $po): ?>
                                        <option value="<?= (int)$po['id'] ?>"
                                                data-total="<?= (float)$po['total'] ?>"
                                                data-paid="<?= (float)$po['paid_amount'] ?>"
                                                data-due="<?= (float)$po['due_amount'] ?>"
                                                data-customer="<?= e($po['customer_name']) ?>"
                                                data-number="<?= e($po['order_number']) ?>">
                                            <?= e($po['order_number']) ?> — <?= e($po['customer_name']) ?> (Due: <?= e(format_price($po['due_amount'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <!-- Payment Amount -->
                        <div class="col-12 col-md-6">
                            <label for="amount" class="form-label fw-semibold">Payment Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted">$</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="0.01" 
                                       class="form-control font-monospace text-end fs-5 fw-bold" 
                                       id="amount" 
                                       name="amount" 
                                       value="<?= $selectedOrder ? e($selectedOrder['due_amount']) : '' ?>" 
                                       placeholder="0.00" 
                                       required>
                            </div>
                            <div class="form-text small" id="amountHelp">Enter the collected payment amount.</div>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-12 col-md-6">
                            <label for="payment_method" class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="cash" selected>Cash</option>
                                <option value="card">Credit / Debit Card</option>
                                <option value="mobile_banking">Mobile Banking (bKash / Nagad / Rocket)</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Transaction Reference -->
                        <div class="col-12 col-md-6">
                            <label for="transaction_reference" class="form-label small text-muted">
                                Transaction Reference / ID
                            </label>
                            <input type="text" 
                                   class="form-control font-monospace" 
                                   id="transaction_reference" 
                                   name="transaction_reference" 
                                   placeholder="e.g. TrxID, Auth Code, Check #">
                        </div>

                        <!-- Payment Date & Time -->
                        <div class="col-12 col-md-6">
                            <label for="payment_date" class="form-label small text-muted">Payment Date &amp; Time</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="payment_date" 
                                   name="payment_date" 
                                   value="<?= e($defaultPaymentDate) ?>" 
                                   required>
                        </div>

                        <!-- Payment Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label small text-muted">Payment Notes</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="2" 
                                      placeholder="Optional remarks (e.g. advance payment, counter collection)..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right 5 Columns: Real-Time Order Balance Preview Card & Submit -->
        <div class="col-12 col-lg-5">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calculator me-2 text-primary"></i>Order Balance Preview</h3>
                </div>
                <div class="card-body p-4">
                    <!-- Order Total -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Order Total:</span>
                        <span class="font-monospace fw-semibold text-dark fs-6" id="previewOrderTotal">
                            <?= $selectedOrder ? e(format_price($selectedOrder['total'])) : '$0.00' ?>
                        </span>
                    </div>

                    <!-- Previously Paid -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Previously Paid:</span>
                        <span class="font-monospace text-success small" id="previewPrevPaid">
                            <?= $selectedOrder ? e(format_price($selectedOrder['paid_amount'])) : '$0.00' ?>
                        </span>
                    </div>

                    <!-- Current Due Before Payment -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Current Due Balance:</span>
                        <span class="font-monospace text-danger fw-bold fs-6" id="previewCurrentDue">
                            <?= $selectedOrder ? e(format_price($selectedOrder['due_amount'])) : '$0.00' ?>
                        </span>
                    </div>

                    <hr class="my-3">

                    <!-- New Payment Being Recorded -->
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded border">
                        <span class="fw-semibold text-dark small">This Payment:</span>
                        <span class="font-monospace fw-bold text-success fs-5" id="previewThisPayment">$0.00</span>
                    </div>

                    <!-- Remaining Due After Payment -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-dark small">Remaining Due Balance:</span>
                        <span class="font-monospace fw-bold fs-5 text-danger" id="previewRemainingDue">$0.00</span>
                    </div>

                    <!-- Payment Status Result -->
                    <div class="d-flex justify-content-between align-items-center mb-4 p-2 bg-light rounded border">
                        <span class="small text-muted">Resulting Status:</span>
                        <span id="previewPaymentBadge" class="badge bg-secondary-subtle text-secondary border text-uppercase">Unpaid</span>
                    </div>

                    <!-- Submit & Cancel Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold" id="submitPaymentBtn">
                            <i class="bi bi-check2-circle me-1"></i> Confirm &amp; Save Payment
                        </button>
                        <a href="<?= base_url('modules/payments/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$selectedOrderJson = json_encode($selectedOrder, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$extraScripts = '
<script>
const SELECTED_ORDER = ' . ($selectedOrderJson ?: 'null') . ';

document.addEventListener("DOMContentLoaded", function () {
    const orderSelect = document.getElementById("order_id");
    const amountInput = document.getElementById("amount");
    const amountHelp = document.getElementById("amountHelp");
    const submitBtn = document.getElementById("submitPaymentBtn");

    const previewOrderTotal = document.getElementById("previewOrderTotal");
    const previewPrevPaid = document.getElementById("previewPrevPaid");
    const previewCurrentDue = document.getElementById("previewCurrentDue");
    const previewThisPayment = document.getElementById("previewThisPayment");
    const previewRemainingDue = document.getElementById("previewRemainingDue");
    const previewPaymentBadge = document.getElementById("previewPaymentBadge");

    function getSelectedOrderInfo() {
        if (SELECTED_ORDER) {
            return {
                total: parseFloat(SELECTED_ORDER.total) || 0,
                paid: parseFloat(SELECTED_ORDER.paid_amount) || 0,
                due: parseFloat(SELECTED_ORDER.due_amount) || 0
            };
        }

        if (orderSelect && orderSelect.tagName === "SELECT") {
            const opt = orderSelect.options[orderSelect.selectedIndex];
            if (opt && opt.value) {
                return {
                    total: parseFloat(opt.getAttribute("data-total")) || 0,
                    paid: parseFloat(opt.getAttribute("data-paid")) || 0,
                    due: parseFloat(opt.getAttribute("data-due")) || 0
                };
            }
        }

        return { total: 0, paid: 0, due: 0 };
    }

    function updatePreview() {
        const ord = getSelectedOrderInfo();
        const payAmount = parseFloat(amountInput.value) || 0;

        previewOrderTotal.textContent = `$${ord.total.toFixed(2)}`;
        previewPrevPaid.textContent = `$${ord.paid.toFixed(2)}`;
        previewCurrentDue.textContent = `$${ord.due.toFixed(2)}`;
        previewThisPayment.textContent = `$${payAmount.toFixed(2)}`;

        const remainingDue = Math.max(0, ord.due - payAmount);
        previewRemainingDue.textContent = `$${remainingDue.toFixed(2)}`;

        const totalPaidAfter = ord.paid + payAmount;

        if (ord.total === 0 || totalPaidAfter >= ord.total) {
            previewPaymentBadge.className = "badge bg-success-subtle text-success border border-success-subtle text-uppercase";
            previewPaymentBadge.textContent = "Fully Paid";
        } else if (totalPaidAfter > 0) {
            previewPaymentBadge.className = "badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase";
            previewPaymentBadge.textContent = "Partially Paid";
        } else {
            previewPaymentBadge.className = "badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase";
            previewPaymentBadge.textContent = "Unpaid";
        }

        // Overpayment check
        if (payAmount > ord.due && ord.due > 0) {
            amountInput.classList.add("is-invalid");
            amountHelp.className = "form-text text-danger small";
            amountHelp.textContent = `Warning: Payment amount ($${payAmount.toFixed(2)}) exceeds current due ($${ord.due.toFixed(2)}).`;
            submitBtn.disabled = true;
        } else {
            amountInput.classList.remove("is-invalid");
            amountHelp.className = "form-text text-muted small";
            amountHelp.textContent = `Maximum payable amount: $${ord.due.toFixed(2)}`;
            submitBtn.disabled = false;
        }
    }

    if (orderSelect && orderSelect.tagName === "SELECT") {
        orderSelect.addEventListener("change", function () {
            const ord = getSelectedOrderInfo();
            if (ord.due > 0) {
                amountInput.value = ord.due.toFixed(2);
                amountInput.max = ord.due;
            } else {
                amountInput.value = "";
            }
            updatePreview();
        });
    }

    if (amountInput) {
        amountInput.addEventListener("input", updatePreview);
    }

    updatePreview();
});
</script>
';
?>

<?php endif; ?>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
