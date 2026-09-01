<?php
/**
 * Reports & Analytics — Orders Report
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Orders Report';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

$statusFilter = sanitize_input($_GET['status'] ?? '');
$validStatuses = ['received', 'processing', 'ready', 'delivered', 'cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

// 1. Overall Status Stage Breakdown in Date Range
$statusCountsStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = "received" THEN 1 ELSE 0 END) AS cnt_received,
        SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) AS cnt_processing,
        SUM(CASE WHEN status = "ready" THEN 1 ELSE 0 END) AS cnt_ready,
        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) AS cnt_delivered,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cnt_cancelled
    FROM orders
    WHERE DATE(order_date) BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
');
$statusCountsStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$counts = $statusCountsStmt->fetch() ?: [];

$totalOrders  = (int)($counts['total_orders'] ?? 0);
$cntReceived  = (int)($counts['cnt_received'] ?? 0);
$cntProcessing= (int)($counts['cnt_processing'] ?? 0);
$cntReady     = (int)($counts['cnt_ready'] ?? 0);
$cntDelivered = (int)($counts['cnt_delivered'] ?? 0);
$cntCancelled = (int)($counts['cnt_cancelled'] ?? 0);

// 2. Daily Orders Aggregate Breakdown
$params = [
    'start_date' => $startDate,
    'end_date'   => $endDate
];

$dailySql = '
    SELECT 
        DATE(order_date) AS order_day,
        COUNT(id) AS total_day_orders,
        SUM(CASE WHEN status = "received" THEN 1 ELSE 0 END) AS day_received,
        SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) AS day_processing,
        SUM(CASE WHEN status = "ready" THEN 1 ELSE 0 END) AS day_ready,
        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) AS day_delivered,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS day_cancelled
    FROM orders
    WHERE DATE(order_date) BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
';

if (!empty($statusFilter)) {
    $dailySql .= ' AND status = :status';
    $params['status'] = $statusFilter;
}

$dailySql .= ' GROUP BY DATE(order_date) ORDER BY order_day DESC';

$dailyStmt = $pdo->prepare($dailySql);
$dailyStmt->execute($params);
$dailyRows = $dailyStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Orders Report</h2>
            <p class="text-muted small mb-0">Daily intake velocity, operational stage distributions, and completion rates.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Sub-navigation Tabs -->
    <ul class="nav nav-pills mb-3 border-bottom pb-2 gap-1">
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/index.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-grid me-1"></i> Overview
            </a>
        </li>
        <?php if (is_admin() || is_manager()): ?>
            <li class="nav-item">
                <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/sales.php?' . http_build_query($_GET)) ?>">
                    <i class="bi bi-cash-stack me-1"></i> Sales
                </a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/orders.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-basket me-1"></i> Orders
            </a>
        </li>
        <?php if (is_admin() || is_manager()): ?>
            <li class="nav-item">
                <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/payments.php?' . http_build_query($_GET)) ?>">
                    <i class="bi bi-credit-card me-1"></i> Payments
                </a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/customers.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-people me-1"></i> Customers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/services.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-tags me-1"></i> Services
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/delivery.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-truck me-1"></i> Delivery
            </a>
        </li>
    </ul>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= base_url('modules/reports/orders.php') ?>" class="row g-2 align-items-center">
                <!-- Preset Range -->
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Time Period</label>
                    <select name="range" class="form-select form-select-sm" onchange="if(this.value !== 'custom') this.form.submit();">
                        <option value="today" <?= $rangePreset === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="yesterday" <?= $rangePreset === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="this_week" <?= $rangePreset === 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="this_month" <?= $rangePreset === 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $rangePreset === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="this_year" <?= $rangePreset === 'this_year' ? 'selected' : '' ?>>This Year</option>
                        <option value="all_time" <?= $rangePreset === 'all_time' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $rangePreset === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>

                <!-- Custom Dates -->
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small text-muted mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small text-muted mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small text-muted mb-1">Filter Stage</label>
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit();">
                        <option value="">All Stages</option>
                        <option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex gap-2 align-self-end ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="<?= base_url('modules/reports/orders.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Print-Only Header -->
<div class="d-none d-print-block mb-4 border-bottom pb-2">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 fw-bold text-dark mb-0"><?= e(APP_NAME) ?></h1>
            <p class="small text-muted mb-0">Orders &amp; Operational Report</p>
        </div>
        <div class="text-end">
            <div class="small fw-semibold">Period: <?= e($rangeLabel) ?></div>
            <div class="small text-muted">Printed on: <?= date('M d, Y h:i A') ?></div>
        </div>
    </div>
</div>

<!-- Active Filter Label Banner -->
<div class="alert alert-light border d-flex justify-content-between align-items-center py-2 px-3 mb-4 shadow-sm">
    <div class="small">
        <i class="bi bi-calendar3 me-1 text-primary"></i> Report Period: <strong class="text-dark"><?= e($rangeLabel) ?></strong>
        <span class="text-muted ms-2">(<?= e(format_datetime($startDate, 'M d, Y')) ?> to <?= e(format_datetime($endDate, 'M d, Y')) ?>)</span>
    </div>
    <span class="badge bg-primary text-uppercase font-monospace"><?= e($rangePreset) ?></span>
</div>

<!-- Order Stage Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Orders</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalOrders) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">intake in period</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Received</div>
                <div class="h3 fw-bold text-info mb-0"><?= number_format($cntReceived) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">awaiting wash</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Processing</div>
                <div class="h3 fw-bold text-warning mb-0"><?= number_format($cntProcessing) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">washing / press</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Ready</div>
                <div class="h3 fw-bold text-success mb-0"><?= number_format($cntReady) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">ready for pickup</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Delivered</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($cntDelivered) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">completed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Cancelled</div>
                <div class="h3 fw-bold text-danger mb-0"><?= number_format($cntCancelled) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">voided / cancelled</div>
            </div>
        </div>
    </div>
</div>

<!-- Daily Orders Breakdown Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calendar-check me-2 text-primary"></i>Daily Order Intake &amp; Workflow Flow</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= count($dailyRows) ?> day(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th class="text-center" style="width: 110px;">Total Placed</th>
                        <th class="text-center" style="width: 100px;">Received</th>
                        <th class="text-center" style="width: 100px;">Processing</th>
                        <th class="text-center" style="width: 100px;">Ready</th>
                        <th class="text-center" style="width: 100px;">Delivered</th>
                        <th class="text-center" style="width: 100px;">Cancelled</th>
                        <th class="text-end pe-3" style="width: 120px;">Completion %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyRows)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No orders found for the selected criteria</h5>
                                <p class="small mb-0">Adjust your date range or clear your filter.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyRows as $row): 
                            $tot = (int)$row['total_day_orders'];
                            $del = (int)$row['day_delivered'];
                            $compPct = $tot > 0 ? round(($del / $tot) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-semibold text-dark">
                                    <?= e(format_datetime($row['order_day'], 'M d, Y (D)')) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-2 py-1"><?= $tot ?></span>
                                </td>
                                <td class="text-center small font-monospace"><?= (int)$row['day_received'] ?></td>
                                <td class="text-center small font-monospace text-warning fw-semibold"><?= (int)$row['day_processing'] ?></td>
                                <td class="text-center small font-monospace text-success fw-semibold"><?= (int)$row['day_ready'] ?></td>
                                <td class="text-center small font-monospace text-dark fw-bold"><?= (int)$row['day_delivered'] ?></td>
                                <td class="text-center small font-monospace text-danger"><?= (int)$row['day_cancelled'] ?></td>
                                <td class="text-end pe-3 font-monospace fw-semibold <?= $compPct >= 80 ? 'text-success' : ($compPct >= 40 ? 'text-warning' : 'text-muted') ?>">
                                    <?= $compPct ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($dailyRows)): ?>
                    <tfoot class="table-light small fw-bold">
                        <tr>
                            <td class="ps-3">Total:</td>
                            <td class="text-center font-monospace text-primary"><?= $totalOrders ?></td>
                            <td class="text-center font-monospace"><?= $cntReceived ?></td>
                            <td class="text-center font-monospace text-warning"><?= $cntProcessing ?></td>
                            <td class="text-center font-monospace text-success"><?= $cntReady ?></td>
                            <td class="text-center font-monospace text-dark"><?= $cntDelivered ?></td>
                            <td class="text-center font-monospace text-danger"><?= $cntCancelled ?></td>
                            <td class="text-end pe-3 font-monospace">
                                <?= $totalOrders > 0 ? round(($cntDelivered / $totalOrders) * 100, 1) : 0 ?>%
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
