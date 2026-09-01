<?php
/**
 * Laundry Orders Listing View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Laundry Orders';
$currentUser = current_user();
$pdo = getDBConnection();

$canDelete = is_admin() || is_manager();

// Search & Filter parameters
$search = sanitize_input($_GET['q'] ?? '');
$statusFilter = sanitize_input($_GET['status'] ?? '');
$paymentFilter = sanitize_input($_GET['payment_status'] ?? '');
$expectedDateFilter = sanitize_input($_GET['expected_date'] ?? '');

$validStatuses = ['received', 'processing', 'ready', 'delivered', 'cancelled'];
$validPaymentStatuses = ['unpaid', 'partial', 'paid'];

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
if (!in_array($paymentFilter, $validPaymentStatuses, true)) {
    $paymentFilter = '';
}

// Pagination setup
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Build WHERE clauses
$whereConditions = ['o.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(o.order_number LIKE :search OR c.name LIKE :search OR c.phone LIKE :search OR c.customer_code LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereConditions[] = 'o.status = :status';
    $params['status'] = $statusFilter;
}

if (!empty($paymentFilter)) {
    $whereConditions[] = 'o.payment_status = :payment_status';
    $params['payment_status'] = $paymentFilter;
}

if (!empty($expectedDateFilter)) {
    $whereConditions[] = 'o.expected_date = :expected_date';
    $params['expected_date'] = $expectedDateFilter;
}

$whereSql = implode(' AND ', $whereConditions);

// Count Total Records
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Adjust page if out of bounds
if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Paginated Orders
$listSql = "
    SELECT o.id, o.order_number, o.customer_id, o.order_date, o.expected_date,
           o.status, o.subtotal, o.discount, o.total, o.paid_amount, o.due_amount, o.payment_status,
           c.name AS customer_name, c.phone AS customer_phone, c.customer_code
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE {$whereSql}
    ORDER BY o.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header & Add Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Laundry Orders</h2>
        <p class="text-muted small mb-0">Manage customer laundry orders, processing stages, payment balances, and deliveries.</p>
    </div>
    <div>
        <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Order
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/orders/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query Input -->
            <div class="col-12 col-md-4 col-lg-3">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Order #, Customer, Phone..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Status Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready for Pickup</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Payment Status Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <select name="payment_status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Payments</option>
                    <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="partial" <?= $paymentFilter === 'partial' ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Fully Paid</option>
                </select>
            </div>

            <!-- Expected Delivery Date Filter -->
            <div class="col-6 col-md-3 col-lg-2">
                <input type="date" 
                       name="expected_date" 
                       class="form-control" 
                       value="<?= e($expectedDateFilter) ?>" 
                       title="Expected Delivery Date" 
                       onchange="this.form.submit()">
            </div>

            <!-- Submit & Reset Buttons -->
            <div class="col-6 col-md-3 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($paymentFilter) || !empty($expectedDateFilter)): ?>
                    <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Orders Data Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Order #</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Expected Delivery</th>
                        <th class="text-end" style="width: 110px;">Total</th>
                        <th class="text-end" style="width: 100px;">Paid</th>
                        <th class="text-end" style="width: 100px;">Due</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 110px;">Payment</th>
                        <th class="text-end pe-3" style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-basket3 fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="fw-semibold mb-1">No laundry orders found</h5>
                                    <p class="small mb-3">
                                        <?= (!empty($search) || !empty($statusFilter) || !empty($paymentFilter) || !empty($expectedDateFilter)) ? 'Try adjusting your search criteria or filter options.' : 'Get started by creating your first customer laundry order.' ?>
                                    </p>
                                    <?php if (empty($search) && empty($statusFilter) && empty($paymentFilter) && empty($expectedDateFilter)): ?>
                                        <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-lg me-1"></i> New Order
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <!-- Order Number -->
                                <td class="ps-3 font-monospace fw-semibold">
                                    <a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>" class="text-decoration-none">
                                        <?= e($order['order_number']) ?>
                                    </a>
                                </td>

                                <!-- Customer -->
                                <td>
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$order['customer_id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                        <?= e($order['customer_name']) ?>
                                    </a>
                                    <span class="small text-muted font-monospace">
                                        <i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?>
                                    </span>
                                </td>

                                <!-- Order Date -->
                                <td>
                                    <span class="small text-muted"><?= e(format_datetime($order['order_date'], 'M d, Y')) ?></span>
                                </td>

                                <!-- Expected Delivery -->
                                <td>
                                    <span class="small fw-semibold <?= strtotime($order['expected_date']) < strtotime('today') && $order['status'] !== 'delivered' ? 'text-danger' : 'text-dark' ?>">
                                        <?= e(format_datetime($order['expected_date'], 'M d, Y')) ?>
                                    </span>
                                </td>

                                <!-- Total -->
                                <td class="text-end font-monospace fw-bold text-dark">
                                    <?= e(format_price($order['total'])) ?>
                                </td>

                                <!-- Paid -->
                                <td class="text-end font-monospace text-success small">
                                    <?= e(format_price($order['paid_amount'])) ?>
                                </td>

                                <!-- Due -->
                                <td class="text-end font-monospace <?= (float)$order['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?> small">
                                    <?= e(format_price($order['due_amount'])) ?>
                                </td>

                                <!-- Order Status -->
                                <td>
                                    <?= order_status_badge($order['status']) ?>
                                </td>

                                <!-- Payment Status -->
                                <td>
                                    <?= payment_status_badge($order['payment_status']) ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View -->
                                        <a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Order Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Edit -->
                                        <a href="<?= base_url('modules/orders/edit.php?id=' . (int)$order['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="Edit Order"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Update Status Quick Action -->
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-update-status" 
                                                data-id="<?= (int)$order['id'] ?>"
                                                data-number="<?= e($order['order_number']) ?>"
                                                data-current-status="<?= e($order['status']) ?>"
                                                title="Change Status"
                                                data-bs-toggle="tooltip">
                                            <i class="bi bi-arrow-repeat text-primary"></i>
                                        </button>

                                        <!-- Print Invoice Receipt -->
                                        <a href="<?= base_url('modules/orders/print.php?id=' . (int)$order['id']) ?>" 
                                           target="_blank"
                                           class="btn btn-outline-secondary" 
                                           title="Print Receipt"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        <!-- Delete (Admin & Manager Only) -->
                                        <?php if ($canDelete): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-delete-order" 
                                                    data-id="<?= (int)$order['id'] ?>"
                                                    data-number="<?= e($order['order_number']) ?>"
                                                    data-customer="<?= e($order['customer_name']) ?>"
                                                    title="Delete Order"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-trash"></i>
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
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> orders
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Orders list pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Prev Link -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/orders/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/orders/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Link -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/orders/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Update Order Status -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/orders/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="statusOrderId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="updateStatusModalLabel">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Order: <strong id="statusOrderNumber" class="text-dark font-monospace"></strong></p>
                    <label for="new_status" class="form-label small text-muted">Select Lifecycle Status</label>
                    <select name="status" id="new_status" class="form-select">
                        <option value="received">Received</option>
                        <option value="processing">Processing</option>
                        <option value="ready">Ready for Pickup</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Soft Delete Confirmation (Admin & Manager Only) -->
<?php if ($canDelete): ?>
<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/orders/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteOrderId" value="">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="deleteOrderModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Laundry Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete order <strong id="deleteOrderNumber" class="font-monospace"></strong> for customer <strong id="deleteOrderCustomer"></strong>?</p>
                    <p class="small text-muted mb-0">This order will be soft-deleted. Historical order items remain safely recorded in the database.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Order
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
    // Handle Update Status Modal
    const statusButtons = document.querySelectorAll(".btn-update-status");
    const statusModalEl = document.getElementById("updateStatusModal");
    const statusOrderId = document.getElementById("statusOrderId");
    const statusOrderNumber = document.getElementById("statusOrderNumber");
    const newStatusSelect = document.getElementById("new_status");

    if (statusModalEl && typeof bootstrap !== "undefined") {
        const statusModal = new bootstrap.Modal(statusModalEl);
        statusButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                const number = this.getAttribute("data-number");
                const currentStatus = this.getAttribute("data-current-status");

                statusOrderId.value = id;
                statusOrderNumber.textContent = number;
                if (newStatusSelect) {
                    newStatusSelect.value = currentStatus;
                }
                statusModal.show();
            });
        });
    }

    // Handle Delete Modal
    const deleteButtons = document.querySelectorAll(".btn-delete-order");
    const deleteModalEl = document.getElementById("deleteOrderModal");
    const deleteOrderId = document.getElementById("deleteOrderId");
    const deleteOrderNumber = document.getElementById("deleteOrderNumber");
    const deleteOrderCustomer = document.getElementById("deleteOrderCustomer");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                const number = this.getAttribute("data-number");
                const customer = this.getAttribute("data-customer");

                deleteOrderId.value = id;
                deleteOrderNumber.textContent = number;
                deleteOrderCustomer.textContent = customer;
                deleteModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
