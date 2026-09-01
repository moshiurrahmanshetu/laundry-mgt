<?php
/**
 * Laundry Operations & Workflow Dashboard View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Laundry Operations';
$currentUser = current_user();
$pdo = getDBConnection();

// Query Parameters
$search         = sanitize_input($_GET['q'] ?? '');
$statusFilter   = sanitize_input($_GET['status'] ?? '');
$paymentFilter  = sanitize_input($_GET['payment_status'] ?? '');
$dateFilter     = sanitize_input($_GET['date_filter'] ?? '');
$customDate     = sanitize_input($_GET['custom_date'] ?? '');
$specialFilter  = sanitize_input($_GET['special'] ?? ''); // e.g. out_for_delivery

$validStatuses = ['received', 'processing', 'ready', 'delivered', 'cancelled'];
$validPaymentStatuses = ['unpaid', 'partial', 'paid'];

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
if (!in_array($paymentFilter, $validPaymentStatuses, true)) {
    $paymentFilter = '';
}

// 1. Dynamic Workflow Counters
$cntReceived    = 0;
$cntProcessing  = 0;
$cntReady       = 0;
$cntOutDelivery = 0;
$cntDelivered   = 0;
$cntCancelled   = 0;

try {
    $s1 = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'received' AND deleted_at IS NULL");
    $cntReceived = (int)$s1->fetchColumn();

    $s2 = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing' AND deleted_at IS NULL");
    $cntProcessing = (int)$s2->fetchColumn();

    $s3 = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'ready' AND deleted_at IS NULL");
    $cntReady = (int)$s3->fetchColumn();

    $s4 = $pdo->query("SELECT COUNT(DISTINCT order_id) FROM pickup_deliveries WHERE type = 'delivery' AND status = 'in_progress' AND deleted_at IS NULL");
    $cntOutDelivery = (int)$s4->fetchColumn();

    $s5 = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND deleted_at IS NULL");
    $cntDelivered = (int)$s5->fetchColumn();

    $s6 = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled' AND deleted_at IS NULL");
    $cntCancelled = (int)$s6->fetchColumn();
} catch (PDOException $e) {}

// 2. Pagination & Search Query Building
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

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

if ($specialFilter === 'out_for_delivery') {
    $whereConditions[] = 'o.id IN (SELECT DISTINCT order_id FROM pickup_deliveries WHERE type = "delivery" AND status = "in_progress" AND deleted_at IS NULL)';
}

if ($dateFilter === 'today') {
    $whereConditions[] = 'DATE(o.order_date) = CURDATE()';
} elseif ($dateFilter === 'yesterday') {
    $whereConditions[] = 'DATE(o.order_date) = CURDATE() - INTERVAL 1 DAY';
} elseif ($dateFilter === 'week') {
    $whereConditions[] = 'o.order_date >= CURDATE() - INTERVAL 7 DAY';
} elseif (!empty($customDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
    $whereConditions[] = 'DATE(o.order_date) = :custom_date';
    $params['custom_date'] = $customDate;
}

$whereSql = implode(' AND ', $whereConditions);

// Total matching records
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders o
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

// Fetch Paginated Operational Orders
$listSql = "
    SELECT o.id, o.order_number, o.customer_id, o.order_date, o.expected_date,
           o.status, o.subtotal, o.discount, o.total, o.paid_amount, o.due_amount,
           o.payment_status, o.created_at,
           c.name AS customer_name, c.phone AS customer_phone, c.customer_code,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count,
           (SELECT GROUP_CONCAT(DISTINCT service_name SEPARATOR ', ') FROM order_items WHERE order_id = o.id) AS services_summary,
           (SELECT pd.status FROM pickup_deliveries pd WHERE pd.order_id = o.id AND pd.type = 'delivery' AND pd.deleted_at IS NULL ORDER BY pd.id DESC LIMIT 1) AS last_delivery_status,
           (SELECT pd.status FROM pickup_deliveries pd WHERE pd.order_id = o.id AND pd.type = 'pickup' AND pd.deleted_at IS NULL ORDER BY pd.id DESC LIMIT 1) AS last_pickup_status
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE {$whereSql}
    ORDER BY 
        CASE o.status
            WHEN 'received' THEN 1
            WHEN 'processing' THEN 2
            WHEN 'ready' THEN 3
            WHEN 'delivered' THEN 4
            WHEN 'cancelled' THEN 5
            ELSE 6
        END,
        o.expected_date ASC,
        o.id DESC
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

<!-- Header & Quick Overview -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Laundry Operations</h2>
        <p class="text-muted small mb-0">Live stage management and operational processing of laundry orders.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> New Order
        </a>
        <a href="<?= base_url('modules/delivery/create.php') ?>" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-truck me-1"></i> Schedule Dispatch
        </a>
    </div>
</div>

<!-- Dynamic Operational Stage Cards -->
<div class="row g-3 mb-4">
    <!-- Received -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= base_url('modules/operations/index.php?status=received') ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 <?= $statusFilter === 'received' ? 'border border-2 border-info' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Received</span>
                        <i class="bi bi-inbox text-info fs-5"></i>
                    </div>
                    <div class="h3 fw-bold text-dark mb-0"><?= $cntReceived ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Awaiting wash</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Processing -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= base_url('modules/operations/index.php?status=processing') ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 <?= $statusFilter === 'processing' ? 'border border-2 border-warning' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Processing</span>
                        <i class="bi bi-gear text-warning fs-5"></i>
                    </div>
                    <div class="h3 fw-bold text-warning mb-0"><?= $cntProcessing ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Wash / Ironing</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Ready -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= base_url('modules/operations/index.php?status=ready') ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 <?= $statusFilter === 'ready' ? 'border border-2 border-success' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Ready</span>
                        <i class="bi bi-check2-circle text-success fs-5"></i>
                    </div>
                    <div class="h3 fw-bold text-success mb-0"><?= $cntReady ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Ready for pickup</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Out for Delivery -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= base_url('modules/operations/index.php?special=out_for_delivery') ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 <?= $specialFilter === 'out_for_delivery' ? 'border border-2 border-primary' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Out for Delivery</span>
                        <i class="bi bi-truck text-primary fs-5"></i>
                    </div>
                    <div class="h3 fw-bold text-primary mb-0"><?= $cntOutDelivery ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Active dispatch</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Delivered -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= base_url('modules/operations/index.php?status=delivered') ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 <?= $statusFilter === 'delivered' ? 'border border-2 border-dark' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Delivered</span>
                        <i class="bi bi-bag-check text-dark fs-5"></i>
                    </div>
                    <div class="h3 fw-bold text-dark mb-0"><?= $cntDelivered ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Completed orders</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Cancelled -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= base_url('modules/operations/index.php?status=cancelled') ?>" class="text-decoration-none">
            <div class="card shadow-sm border-0 h-100 <?= $statusFilter === 'cancelled' ? 'border border-2 border-danger' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold">Cancelled</span>
                        <i class="bi bi-x-circle text-danger fs-5"></i>
                    </div>
                    <div class="h3 fw-bold text-danger mb-0"><?= $cntCancelled ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;">Terminated</div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Search & Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/operations/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query -->
            <div class="col-12 col-md-4 col-lg-3">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Order #, Customer Name, Phone..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/operations/index.php') ?>" class="btn btn-outline-secondary" title="Clear">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Status Filter -->
            <div class="col-6 col-md-2 col-lg-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Stages</option>
                    <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Payment Status Filter -->
            <div class="col-6 col-md-2 col-lg-2">
                <select name="payment_status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Payments</option>
                    <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $paymentFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>

            <!-- Date Filter Selector -->
            <div class="col-6 col-md-2 col-lg-2">
                <select name="date_filter" class="form-select" onchange="this.form.submit()">
                    <option value="">All Dates</option>
                    <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $dateFilter === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="week" <?= $dateFilter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                </select>
            </div>

            <!-- Filter & Reset Buttons -->
            <div class="col-12 col-md-auto d-flex gap-2 ms-auto">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($paymentFilter) || !empty($dateFilter) || !empty($customDate) || !empty($specialFilter)): ?>
                    <a href="<?= base_url('modules/operations/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Operational Orders Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-kanban me-2 text-primary"></i>Operational Workflow Queue</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= $totalRecords ?> order(s) found</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 130px;">Order #</th>
                        <th>Customer</th>
                        <th style="width: 140px;">Dates</th>
                        <th>Laundry Items</th>
                        <th style="width: 130px;">Payment Balance</th>
                        <th style="width: 110px;">Workflow Stage</th>
                        <th style="width: 120px;">Dispatch</th>
                        <th class="text-center" style="width: 150px;">Next Action</th>
                        <th class="text-end pe-3" style="width: 110px;">Tools</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-basket3 fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="fw-semibold mb-1">No orders match the current operational criteria</h5>
                                    <p class="small mb-3">Try adjusting your filters or search terms.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $ord): ?>
                            <tr>
                                <!-- Order Number -->
                                <td class="ps-3 font-monospace fw-semibold">
                                    <a href="<?= base_url('modules/operations/show.php?id=' . (int)$ord['id']) ?>" class="text-decoration-none">
                                        <?= e($ord['order_number']) ?>
                                    </a>
                                </td>

                                <!-- Customer -->
                                <td>
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$ord['customer_id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                        <?= e($ord['customer_name']) ?>
                                    </a>
                                    <span class="small text-muted font-monospace"><i class="bi bi-telephone me-1"></i><?= e($ord['customer_phone']) ?></span>
                                </td>

                                <!-- Dates -->
                                <td class="small">
                                    <div class="text-muted">In: <?= e(format_datetime($ord['order_date'], 'M d')) ?></div>
                                    <div class="fw-semibold <?= strtotime($ord['expected_date']) < strtotime('today') && $ord['status'] !== 'delivered' ? 'text-danger' : 'text-dark' ?>">
                                        Exp: <?= e(format_datetime($ord['expected_date'], 'M d')) ?>
                                    </div>
                                </td>

                                <!-- Items -->
                                <td>
                                    <div class="small fw-semibold text-dark"><?= (int)$ord['items_count'] ?> item(s)</div>
                                    <div class="small text-muted text-truncate" style="max-width: 180px;" title="<?= e($ord['services_summary'] ?: 'No items') ?>">
                                        <?= e($ord['services_summary'] ?: '—') ?>
                                    </div>
                                </td>

                                <!-- Payment Balance -->
                                <td>
                                    <div class="font-monospace fw-bold small text-dark"><?= e(format_price($ord['total'])) ?></div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="small font-monospace <?= (float)$ord['due_amount'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>">
                                            Due: <?= e(format_price($ord['due_amount'])) ?>
                                        </span>
                                        <?= payment_status_badge($ord['payment_status']) ?>
                                    </div>
                                </td>

                                <!-- Order Status Badge -->
                                <td>
                                    <?= order_status_badge($ord['status']) ?>
                                </td>

                                <!-- Logistics / Dispatch Badge -->
                                <td>
                                    <?php if (!empty($ord['last_delivery_status'])): ?>
                                        <div class="small">
                                            <span class="text-muted">Deliv:</span> <?= delivery_status_badge($ord['last_delivery_status']) ?>
                                        </div>
                                    <?php elseif (!empty($ord['last_pickup_status'])): ?>
                                        <div class="small">
                                            <span class="text-muted">Pickup:</span> <?= delivery_status_badge($ord['last_pickup_status']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">In-store</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Contextual Next Operational Action Button -->
                                <td class="text-center">
                                    <?php if ($ord['status'] === 'received'): ?>
                                        <button type="button" 
                                                class="btn btn-warning btn-sm py-1 px-2 fw-semibold btn-quick-transition"
                                                data-id="<?= (int)$ord['id'] ?>"
                                                data-order="<?= e($ord['order_number']) ?>"
                                                data-next="processing"
                                                data-label="Start Processing">
                                            <i class="bi bi-gear-fill me-1"></i> Start Wash
                                        </button>
                                    <?php elseif ($ord['status'] === 'processing'): ?>
                                        <button type="button" 
                                                class="btn btn-success btn-sm py-1 px-2 fw-semibold btn-quick-transition"
                                                data-id="<?= (int)$ord['id'] ?>"
                                                data-order="<?= e($ord['order_number']) ?>"
                                                data-next="ready"
                                                data-label="Mark Ready for Pickup">
                                            <i class="bi bi-check2-circle me-1"></i> Mark Ready
                                        </button>
                                    <?php elseif ($ord['status'] === 'ready'): ?>
                                        <button type="button" 
                                                class="btn btn-dark btn-sm py-1 px-2 fw-semibold btn-quick-transition"
                                                data-id="<?= (int)$ord['id'] ?>"
                                                data-order="<?= e($ord['order_number']) ?>"
                                                data-next="delivered"
                                                data-label="Mark Order Delivered">
                                            <i class="bi bi-bag-check me-1"></i> Deliver
                                        </button>
                                    <?php elseif ($ord['status'] === 'delivered'): ?>
                                        <span class="badge bg-light text-success border"><i class="bi bi-check-all me-1"></i>Finished</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-danger border"><i class="bi bi-x-circle me-1"></i>Cancelled</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Tools & Print -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/operations/show.php?id=' . (int)$ord['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="Operations Profile"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-open-status-modal"
                                                data-id="<?= (int)$ord['id'] ?>"
                                                data-order="<?= e($ord['order_number']) ?>"
                                                data-status="<?= e($ord['status']) ?>"
                                                title="Change Stage"
                                                data-bs-toggle="tooltip">
                                            <i class="bi bi-arrow-repeat text-primary"></i>
                                        </button>
                                        <a href="<?= base_url('modules/operations/print.php?id=' . (int)$ord['id']) ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-secondary" 
                                           title="Print Work Order"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-printer"></i>
                                        </a>
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
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> operational orders
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Operations pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/operations/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/operations/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/operations/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Quick Step Transition Confirmation -->
<div class="modal fade" id="quickTransitionModal" tabindex="-1" aria-labelledby="quickTransitionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/operations/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="quickOrderId" value="">
                <input type="hidden" name="status" id="quickNextStatus" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="quickTransitionModalLabel">Confirm Stage Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-arrow-right-circle fs-1 text-primary d-block mb-2"></i>
                    <p class="mb-1 small text-muted">Advance order <strong id="quickOrderNumber" class="font-monospace text-dark"></strong> to:</p>
                    <div id="quickNextLabel" class="fw-bold fs-6 text-uppercase text-primary"></div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Confirm Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Full Status Select Modal -->
<div class="modal fade" id="fullStatusModal" tabindex="-1" aria-labelledby="fullStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/operations/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="fullStatusOrderId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="fullStatusModalLabel">Change Order Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Order: <strong id="fullStatusOrderNumber" class="text-dark font-monospace"></strong></p>
                    <label for="select_stage" class="form-label small text-muted">Select Workflow Stage</label>
                    <select name="status" id="select_stage" class="form-select">
                        <option value="received">Received</option>
                        <option value="processing">Processing</option>
                        <option value="ready">Ready for Pickup</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Quick Transition Modal
    const quickButtons = document.querySelectorAll(".btn-quick-transition");
    const quickModalEl = document.getElementById("quickTransitionModal");
    const quickOrderId = document.getElementById("quickOrderId");
    const quickOrderNumber = document.getElementById("quickOrderNumber");
    const quickNextStatus = document.getElementById("quickNextStatus");
    const quickNextLabel = document.getElementById("quickNextLabel");

    if (quickModalEl && typeof bootstrap !== "undefined") {
        const quickModal = new bootstrap.Modal(quickModalEl);
        quickButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                quickOrderId.value = this.getAttribute("data-id");
                quickOrderNumber.textContent = this.getAttribute("data-order");
                quickNextStatus.value = this.getAttribute("data-next");
                quickNextLabel.textContent = this.getAttribute("data-label");
                quickModal.show();
            });
        });
    }

    // Full Status Modal
    const fullButtons = document.querySelectorAll(".btn-open-status-modal");
    const fullModalEl = document.getElementById("fullStatusModal");
    const fullOrderId = document.getElementById("fullStatusOrderId");
    const fullOrderNumber = document.getElementById("fullStatusOrderNumber");
    const fullSelectStage = document.getElementById("select_stage");

    if (fullModalEl && typeof bootstrap !== "undefined") {
        const fullModal = new bootstrap.Modal(fullModalEl);
        fullButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                fullOrderId.value = this.getAttribute("data-id");
                fullOrderNumber.textContent = this.getAttribute("data-order");
                fullSelectStage.value = this.getAttribute("data-status");
                fullModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
