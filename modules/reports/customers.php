<?php
/**
 * Reports & Analytics — Customer Performance & Top Spenders Report
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Customer Report';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

// 1. Customer KPI Metrics
$c1 = $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
$totalCustomers = (int)$c1->fetchColumn();

$c2 = $pdo->query('SELECT COUNT(*) FROM customers WHERE status = "active" AND deleted_at IS NULL');
$activeCustomers = (int)$c2->fetchColumn();

$c3 = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN :start_date AND :end_date AND deleted_at IS NULL');
$c3->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$newCustomers = (int)$c3->fetchColumn();

$c4 = $pdo->prepare('SELECT COUNT(DISTINCT customer_id) FROM orders WHERE DATE(order_date) BETWEEN :start_date AND :end_date AND deleted_at IS NULL');
$c4->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$orderingCusts = (int)$c4->fetchColumn();


// 2. Top 10 Customers by Order Value in Date Range
$topCustSql = '
    SELECT 
        c.id, c.customer_code, c.name, c.phone, c.city, c.status,
        COUNT(o.id) AS period_orders,
        COALESCE(SUM(CASE WHEN o.status != "cancelled" THEN o.total ELSE 0 END), 0) AS total_spent,
        COALESCE(SUM(CASE WHEN o.status != "cancelled" THEN o.paid_amount ELSE 0 END), 0) AS total_paid,
        COALESCE(SUM(CASE WHEN o.status != "cancelled" THEN o.due_amount ELSE 0 END), 0) AS total_due
    FROM customers c
    INNER JOIN orders o ON c.id = o.customer_id
    WHERE DATE(o.order_date) BETWEEN :start_date AND :end_date
      AND o.deleted_at IS NULL
      AND c.deleted_at IS NULL
    GROUP BY c.id, c.customer_code, c.name, c.phone, c.city, c.status
    HAVING total_spent > 0
    ORDER BY total_spent DESC, period_orders DESC
    LIMIT 10
';
$topCustStmt = $pdo->prepare($topCustSql);
$topCustStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$topCustomers = $topCustStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Customer Performance Report</h2>
            <p class="text-muted small mb-0">Customer acquisition, order frequency, and top revenue-generating client profiles.</p>
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
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/orders.php?' . http_build_query($_GET)) ?>">
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
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/customers.php?' . http_build_query($_GET)) ?>">
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
            <form method="GET" action="<?= base_url('modules/reports/customers.php') ?>" class="row g-2 align-items-center">
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
                <div class="col-6 col-sm-3 col-md-3">
                    <label class="form-label small text-muted mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                </div>
                <div class="col-6 col-sm-3 col-md-3">
                    <label class="form-label small text-muted mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                </div>

                <div class="col-12 col-md-3 d-flex gap-2 align-self-end">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="<?= base_url('modules/reports/customers.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
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
            <p class="small text-muted mb-0">Customer Performance &amp; Top Spenders Report</p>
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

<!-- Customer Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Registered</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalCustomers) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= $activeCustomers ?> active accounts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">New Registrations</div>
                <div class="h3 fw-bold text-primary mb-0">+<?= number_format($newCustomers) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">in selected period</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Ordering Customers</div>
                <div class="h3 fw-bold text-success mb-0"><?= number_format($orderingCusts) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">placed orders in period</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Active Participation</div>
                <div class="h3 fw-bold text-dark mb-0">
                    <?= $totalCustomers > 0 ? round(($orderingCusts / $totalCustomers) * 100, 1) : 0 ?>%
                </div>
                <div class="small text-muted" style="font-size: 0.75rem;">active order ratio</div>
            </div>
        </div>
    </div>
</div>

<!-- Top 10 Customers Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-trophy me-2 text-primary"></i>Top 10 Customers by Order Value</h3>
        <span class="badge bg-light text-dark border font-monospace">Ranked by Total Value</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">#</th>
                        <th>Customer</th>
                        <th class="text-center" style="width: 120px;">Orders Placed</th>
                        <th class="text-end" style="width: 160px;">Total Value</th>
                        <th class="text-end" style="width: 160px;">Paid to Date</th>
                        <th class="text-end" style="width: 160px;">Outstanding Due</th>
                        <th class="text-end pe-3" style="width: 110px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topCustomers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No customer order activity found in this period</h5>
                                <p class="small mb-0">Adjust your date range or filter.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topCustomers as $idx => $tc): ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-bold <?= $idx < 3 ? 'text-primary' : 'text-muted' ?>">
                                    <?= $idx + 1 ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">
                                        <a href="<?= base_url('modules/customers/show.php?id=' . (int)$tc['id']) ?>" class="text-decoration-none text-dark">
                                            <?= e($tc['name']) ?>
                                        </a>
                                        <span class="badge bg-light text-secondary border font-monospace ms-1"><?= e($tc['customer_code']) ?></span>
                                    </div>
                                    <div class="text-muted font-monospace" style="font-size: 0.75rem;"><i class="bi bi-telephone me-1"></i><?= e($tc['phone']) ?></div>
                                </td>
                                <td class="text-center font-monospace">
                                    <span class="badge bg-light text-dark border"><?= (int)$tc['period_orders'] ?></span>
                                </td>
                                <td class="text-end font-monospace fw-bold text-primary">
                                    <?= e(format_price($tc['total_spent'])) ?>
                                </td>
                                <td class="text-end font-monospace text-success">
                                    <?= e(format_price($tc['total_paid'])) ?>
                                </td>
                                <td class="text-end font-monospace <?= (float)$tc['total_due'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= e(format_price($tc['total_due'])) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$tc['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" title="View Profile">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
