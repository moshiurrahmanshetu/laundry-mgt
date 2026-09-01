<?php
/**
 * Reports & Analytics — Sales & Revenue Report
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$pageTitle = 'Sales Report';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

// 1. Sales Summary Metrics
$summaryStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status != "cancelled" THEN total ELSE 0 END) AS gross_sales,
        SUM(CASE WHEN status != "cancelled" THEN paid_amount ELSE 0 END) AS order_paid,
        SUM(CASE WHEN status != "cancelled" THEN due_amount ELSE 0 END) AS total_due
    FROM orders
    WHERE DATE(order_date) BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
');
$summaryStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$summary = $summaryStmt->fetch() ?: [];

$grossSales   = (float)($summary['gross_sales'] ?? 0);
$totalDue     = (float)($summary['total_due'] ?? 0);
$totalOrders  = (int)($summary['total_orders'] ?? 0);

// Authoritative actual payments collected in date range
$paySumStmt = $pdo->prepare('
    SELECT COALESCE(SUM(amount), 0) AS total_collected
    FROM payments
    WHERE DATE(payment_date) BETWEEN :start_date AND :end_date
      AND status = "completed"
      AND deleted_at IS NULL
');
$paySumStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$totalCollected = (float)$paySumStmt->fetchColumn();

// 2. Daily Sales Breakdown SQL Aggregation
$dailySql = '
    SELECT 
        DATE(o.order_date) AS sales_date,
        COUNT(o.id) AS daily_orders,
        SUM(CASE WHEN o.status != "cancelled" THEN o.total ELSE 0 END) AS daily_sales,
        SUM(CASE WHEN o.status != "cancelled" THEN o.paid_amount ELSE 0 END) AS daily_order_paid,
        SUM(CASE WHEN o.status != "cancelled" THEN o.due_amount ELSE 0 END) AS daily_due,
        SUM(CASE WHEN o.status = "delivered" THEN 1 ELSE 0 END) AS count_delivered,
        SUM(CASE WHEN o.status = "cancelled" THEN 1 ELSE 0 END) AS count_cancelled
    FROM orders o
    WHERE DATE(o.order_date) BETWEEN :start_date AND :end_date
      AND o.deleted_at IS NULL
    GROUP BY DATE(o.order_date)
    ORDER BY sales_date DESC
';
$dailyStmt = $pdo->prepare($dailySql);
$dailyStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$dailyRecords = $dailyStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Sales &amp; Revenue Report</h2>
            <p class="text-muted small mb-0">Daily sales aggregates, order revenue, customer receivables, and collection performance.</p>
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
        <li class="nav-item">
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/sales.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-cash-stack me-1"></i> Sales
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/orders.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-basket me-1"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/payments.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-credit-card me-1"></i> Payments
            </a>
        </li>
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
            <form method="GET" action="<?= base_url('modules/reports/sales.php') ?>" class="row g-2 align-items-center">
                <!-- Preset Selector -->
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
                    <a href="<?= base_url('modules/reports/sales.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
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
            <p class="small text-muted mb-0">Sales &amp; Revenue Report</p>
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

<!-- Sales Top Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Gross Sales</div>
                <div class="h3 fw-bold text-primary mb-0"><?= e(format_price($grossSales)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">excl. cancelled orders</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Collected Payments</div>
                <div class="h3 fw-bold text-success mb-0"><?= e(format_price($totalCollected)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">direct payments in period</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Outstanding Balance</div>
                <div class="h3 fw-bold <?= $totalDue > 0 ? 'text-danger' : 'text-muted' ?> mb-0"><?= e(format_price($totalDue)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">uncollected receivables</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Orders Count</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalOrders) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">orders in period</div>
            </div>
        </div>
    </div>
</div>

<!-- Daily Sales Aggregation Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-table me-2 text-primary"></i>Daily Sales Breakdown</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= count($dailyRecords) ?> active date(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3">Date</th>
                        <th class="text-center" style="width: 100px;">Orders</th>
                        <th class="text-end" style="width: 150px;">Gross Sales</th>
                        <th class="text-end" style="width: 150px;">Paid to Date</th>
                        <th class="text-end" style="width: 150px;">Outstanding Due</th>
                        <th class="text-center" style="width: 120px;">Delivered</th>
                        <th class="text-center" style="width: 120px;">Cancelled</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyRecords)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No sales records found for the selected period</h5>
                                <p class="small mb-0">Try selecting a different date range or clear your filters.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyRecords as $rec): ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-semibold text-dark">
                                    <?= e(format_datetime($rec['sales_date'], 'M d, Y (D)')) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border font-monospace"><?= (int)$rec['daily_orders'] ?></span>
                                </td>
                                <td class="text-end font-monospace fw-bold text-primary">
                                    <?= e(format_price($rec['daily_sales'])) ?>
                                </td>
                                <td class="text-end font-monospace text-success">
                                    <?= e(format_price($rec['daily_order_paid'])) ?>
                                </td>
                                <td class="text-end font-monospace <?= (float)$rec['daily_due'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= e(format_price($rec['daily_due'])) ?>
                                </td>
                                <td class="text-center small">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle"><?= (int)$rec['count_delivered'] ?></span>
                                </td>
                                <td class="text-center small">
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= (int)$rec['count_cancelled'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($dailyRecords)): ?>
                    <tfoot class="table-light small fw-bold">
                        <tr>
                            <td class="ps-3">Total Summary:</td>
                            <td class="text-center font-monospace"><?= $totalOrders ?></td>
                            <td class="text-end font-monospace text-primary"><?= e(format_price($grossSales)) ?></td>
                            <td class="text-end font-monospace text-success"><?= e(format_price(array_sum(array_column($dailyRecords, 'daily_order_paid')))) ?></td>
                            <td class="text-end font-monospace text-danger"><?= e(format_price($totalDue)) ?></td>
                            <td class="text-center font-monospace"><?= array_sum(array_column($dailyRecords, 'count_delivered')) ?></td>
                            <td class="text-center font-monospace"><?= array_sum(array_column($dailyRecords, 'count_cancelled')) ?></td>
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
