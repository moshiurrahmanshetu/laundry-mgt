<?php
/**
 * Payment Transactions Listing View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Payments';
$currentUser = current_user();
$pdo = getDBConnection();

$canVoid = is_admin();

// Search & Filter parameters
$search = sanitize_input($_GET['q'] ?? '');
$methodFilter = sanitize_input($_GET['payment_method'] ?? '');
$statusFilter = sanitize_input($_GET['status'] ?? '');
$dateFilter = sanitize_input($_GET['payment_date'] ?? '');

$validMethods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'other'];
$validStatuses = ['completed', 'voided'];

if (!in_array($methodFilter, $validMethods, true)) {
    $methodFilter = '';
}
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

// Pagination setup
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Build WHERE conditions
$whereConditions = ['p.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(p.payment_number LIKE :search OR o.order_number LIKE :search OR c.name LIKE :search OR p.transaction_reference LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($methodFilter)) {
    $whereConditions[] = 'p.payment_method = :payment_method';
    $params['payment_method'] = $methodFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = 'p.status = :status';
    $params['status'] = $statusFilter;
}

if (!empty($dateFilter)) {
    $whereConditions[] = 'DATE(p.payment_date) = :payment_date';
    $params['payment_date'] = $dateFilter;
}

$whereSql = implode(' AND ', $whereConditions);

// Count Total Records
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.id
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Paginated Payments
$listSql = "
    SELECT p.id, p.payment_number, p.order_id, p.amount, p.payment_method,
           p.transaction_reference, p.payment_date, p.status, p.notes,
           o.order_number, o.customer_id,
           c.name AS customer_name, c.phone AS customer_phone,
           u.name AS receiver_name
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.id
    INNER JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE {$whereSql}
    ORDER BY p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header & New Payment Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Payments</h2>
        <p class="text-muted small mb-0">Manage customer payment transactions, receipts, and payment history.</p>
    </div>
    <div>
        <a href="<?= base_url('modules/payments/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Receive Payment
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/payments/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query Input -->
            <div class="col-12 col-md-4 col-lg-3">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Payment #, Order #, Customer, Ref..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/payments/index.php') ?>" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Method Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <select name="payment_method" class="form-select" onchange="this.form.submit()">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $methodFilter === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                    <option value="mobile_banking" <?= $methodFilter === 'mobile_banking' ? 'selected' : '' ?>>Mobile Banking</option>
                    <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="other" <?= $methodFilter === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <!-- Transaction Status Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="voided" <?= $statusFilter === 'voided' ? 'selected' : '' ?>>Voided</option>
                </select>
            </div>

            <!-- Payment Date Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <input type="date" 
                       name="payment_date" 
                       class="form-control" 
                       value="<?= e($dateFilter) ?>" 
                       title="Filter by payment date" 
                       onchange="this.form.submit()">
            </div>

            <!-- Submit & Reset Buttons -->
            <div class="col-6 col-md-3 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($methodFilter) || !empty($statusFilter) || !empty($dateFilter)): ?>
                    <a href="<?= base_url('modules/payments/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Payments Data Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Payment #</th>
                        <th style="width: 130px;">Order #</th>
                        <th>Customer</th>
                        <th class="text-end" style="width: 120px;">Amount</th>
                        <th style="width: 140px;">Method</th>
                        <th style="width: 150px;">Payment Date</th>
                        <th style="width: 130px;">Received By</th>
                        <th style="width: 110px;">Status</th>
                        <th class="text-end pe-3" style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-credit-card-2-back fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="fw-semibold mb-1">No payment transactions found</h5>
                                    <p class="small mb-3">
                                        <?= (!empty($search) || !empty($methodFilter) || !empty($statusFilter) || !empty($dateFilter)) ? 'Try adjusting your search criteria or filter options.' : 'Record your first customer payment for an active order.' ?>
                                    </p>
                                    <?php if (empty($search) && empty($methodFilter) && empty($statusFilter) && empty($dateFilter)): ?>
                                        <a href="<?= base_url('modules/payments/create.php') ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-lg me-1"></i> Receive Payment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <!-- Payment Number -->
                                <td class="ps-3 font-monospace fw-semibold">
                                    <a href="<?= base_url('modules/payments/show.php?id=' . (int)$pay['id']) ?>" class="text-decoration-none">
                                        <?= e($pay['payment_number']) ?>
                                    </a>
                                </td>

                                <!-- Order Number -->
                                <td class="font-monospace small">
                                    <a href="<?= base_url('modules/orders/show.php?id=' . (int)$pay['order_id']) ?>" class="text-decoration-none">
                                        <?= e($pay['order_number']) ?>
                                    </a>
                                </td>

                                <!-- Customer -->
                                <td>
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$pay['customer_id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                        <?= e($pay['customer_name']) ?>
                                    </a>
                                    <span class="small text-muted font-monospace"><i class="bi bi-telephone me-1"></i><?= e($pay['customer_phone']) ?></span>
                                </td>

                                <!-- Amount -->
                                <td class="text-end font-monospace fw-bold <?= $pay['status'] === 'completed' ? 'text-success' : 'text-muted text-decoration-line-through' ?>">
                                    <?= e(format_price($pay['amount'])) ?>
                                </td>

                                <!-- Payment Method -->
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e(payment_method_label($pay['payment_method'])) ?>
                                    </span>
                                    <?php if (!empty($pay['transaction_reference'])): ?>
                                        <div class="small text-muted font-monospace mt-1" title="Transaction Ref">
                                            Ref: <?= e($pay['transaction_reference']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Payment Date -->
                                <td class="small text-muted">
                                    <?= e(format_datetime($pay['payment_date'], 'M d, Y h:i A')) ?>
                                </td>

                                <!-- Received By -->
                                <td class="small text-dark">
                                    <?= e($pay['receiver_name'] ?: 'System') ?>
                                </td>

                                <!-- Status -->
                                <td>
                                    <?= payment_record_status_badge($pay['status']) ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View -->
                                        <a href="<?= base_url('modules/payments/show.php?id=' . (int)$pay['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Payment Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Print Voucher -->
                                        <a href="<?= base_url('modules/payments/print.php?id=' . (int)$pay['id']) ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-secondary" 
                                           title="Print Receipt"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        <!-- Void Button (Admin Only, if status is completed) -->
                                        <?php if ($canVoid && $pay['status'] === 'completed'): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-void-payment" 
                                                    data-id="<?= (int)$pay['id'] ?>"
                                                    data-number="<?= e($pay['payment_number']) ?>"
                                                    data-amount="<?= e(format_price($pay['amount'])) ?>"
                                                    data-order="<?= e($pay['order_number']) ?>"
                                                    title="Void Payment"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination & Summary Footer -->
    <?php if ($totalRecords > 0): ?>
        <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-muted small">
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> payments
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Payments list pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/payments/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/payments/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/payments/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Void Payment Confirmation (Admin Only) -->
<?php if ($canVoid): ?>
<div class="modal fade" id="voidPaymentModal" tabindex="-1" aria-labelledby="voidPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/payments/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="voidPaymentId" value="">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="voidPaymentModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Void Payment Transaction
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to void payment <strong id="voidPaymentNumber" class="font-monospace"></strong> (<span id="voidPaymentAmount" class="font-monospace fw-bold"></span>) for order <strong id="voidPaymentOrder" class="font-monospace"></strong>?</p>
                    <p class="small text-muted mb-0">The payment record will be marked as <strong>voided</strong> in the audit log and the associated order due balance will be automatically restored.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-slash-circle me-1"></i> Void Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const voidButtons = document.querySelectorAll(".btn-void-payment");
    const voidModalEl = document.getElementById("voidPaymentModal");
    const voidPaymentId = document.getElementById("voidPaymentId");
    const voidPaymentNumber = document.getElementById("voidPaymentNumber");
    const voidPaymentAmount = document.getElementById("voidPaymentAmount");
    const voidPaymentOrder = document.getElementById("voidPaymentOrder");

    if (voidModalEl && typeof bootstrap !== "undefined") {
        const voidModal = new bootstrap.Modal(voidModalEl);
        voidButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                voidPaymentId.value = this.getAttribute("data-id");
                voidPaymentNumber.textContent = this.getAttribute("data-number");
                voidPaymentAmount.textContent = this.getAttribute("data-amount");
                voidPaymentOrder.textContent = this.getAttribute("data-order");
                voidModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
