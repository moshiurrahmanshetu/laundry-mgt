<?php
/**
 * Reports & Analytics — Overview Dashboard
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Reports & Analytics';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

// 1. Core Period Metrics
// Orders Summary
$orderStatsStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status != "cancelled" THEN total ELSE 0 END) AS gross_sales,
        SUM(CASE WHEN status != "cancelled" THEN due_amount ELSE 0 END) AS total_due,
        SUM(CASE WHEN status = "received" THEN 1 ELSE 0 END) AS count_received,
        SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) AS count_processing,
        SUM(CASE WHEN status = "ready" THEN 1 ELSE 0 END) AS count_ready,
        SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) AS count_delivered,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS count_cancelled,
        SUM(CASE WHEN payment_status = "paid" AND status != "cancelled" THEN 1 ELSE 0 END) AS count_paid,
        SUM(CASE WHEN payment_status = "partial" AND status != "cancelled" THEN 1 ELSE 0 END) AS count_partial,
        SUM(CASE WHEN payment_status = "unpaid" AND status != "cancelled" THEN 1 ELSE 0 END) AS count_unpaid
    FROM orders
    WHERE DATE(order_date) BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
');
$orderStatsStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$orderStats = $orderStatsStmt->fetch() ?: [];

$totalOrders      = (int)($orderStats['total_orders'] ?? 0);
$grossSales       = (float)($orderStats['gross_sales'] ?? 0);
$totalDue         = (float)($orderStats['total_due'] ?? 0);
$cntReceived      = (int)($orderStats['count_received'] ?? 0);
$cntProcessing    = (int)($orderStats['count_processing'] ?? 0);
$cntReady         = (int)($orderStats['count_ready'] ?? 0);
$cntDelivered     = (int)($orderStats['count_delivered'] ?? 0);
$cntCancelled     = (int)($orderStats['count_cancelled'] ?? 0);
$cntPayPaid       = (int)($orderStats['count_paid'] ?? 0);
$cntPayPartial    = (int)($orderStats['count_partial'] ?? 0);
$cntPayUnpaid     = (int)($orderStats['count_unpaid'] ?? 0);

// Total Collected Payments in Period
$payStmt = $pdo->prepare('
    SELECT COALESCE(SUM(amount), 0) AS total_collected, COUNT(*) AS count_payments
    FROM payments
    WHERE DATE(payment_date) BETWEEN :start_date AND :end_date
      AND status = "completed"
      AND deleted_at IS NULL
');
$payStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$payStats = $payStmt->fetch() ?: [];
$totalCollected = (float)($payStats['total_collected'] ?? 0);
$totalPayments  = (int)($payStats['count_payments'] ?? 0);

// Customers in Period
$c1 = $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
$totalCustomers = (int)$c1->fetchColumn();

$c2 = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN :start_date AND :end_date AND deleted_at IS NULL');
$c2->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$newCustomers = (int)$c2->fetchColumn();

$c3 = $pdo->prepare('SELECT COUNT(DISTINCT customer_id) FROM orders WHERE DATE(order_date) BETWEEN :start_date AND :end_date AND deleted_at IS NULL');
$c3->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$orderingCusts = (int)$c3->fetchColumn();


// Logistics in Period
$delivStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total_schedules,
        SUM(CASE WHEN type = "pickup" THEN 1 ELSE 0 END) AS total_pickups,
        SUM(CASE WHEN type = "pickup" AND status = "completed" THEN 1 ELSE 0 END) AS completed_pickups,
        SUM(CASE WHEN type = "delivery" THEN 1 ELSE 0 END) AS total_deliveries,
        SUM(CASE WHEN type = "delivery" AND status = "completed" THEN 1 ELSE 0 END) AS completed_deliveries,
        SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) AS in_progress_schedules
    FROM pickup_deliveries
    WHERE scheduled_date BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
');
$delivStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$delivStats = $delivStmt->fetch() ?: [];
$totalDeliveriesCompleted = (int)($delivStats['completed_deliveries'] ?? 0);
$totalPickupsCompleted    = (int)($delivStats['completed_pickups'] ?? 0);
$totalSchedules           = (int)($delivStats['total_schedules'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Reports &amp; Analytics</h2>
            <p class="text-muted small mb-0">Comprehensive business intelligence, sales figures, and operational reports.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Report Sub-Navigation Tabs -->
    <ul class="nav nav-pills mb-3 border-bottom pb-2 gap-1">
        <li class="nav-item">
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/index.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-grid-fill me-1"></i> Overview
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
            <form method="GET" action="<?= base_url('modules/reports/index.php') ?>" class="row g-2 align-items-center">
                <!-- Preset Range Selector -->
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

                <!-- Custom Start Date -->
                <div class="col-6 col-sm-3 col-md-3">
                    <label class="form-label small text-muted mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                </div>

                <!-- Custom End Date -->
                <div class="col-6 col-sm-3 col-md-3">
                    <label class="form-label small text-muted mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                </div>

                <!-- Filter Actions -->
                <div class="col-12 col-md-3 d-flex gap-2 align-self-end">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i> Apply
                    </button>
                    <a href="<?= base_url('modules/reports/index.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset to This Month">
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
            <p class="small text-muted mb-0">Business Overview Report</p>
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

<!-- Key Performance Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Total Orders -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Orders</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalOrders) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= $cntDelivered ?> completed</div>
            </div>
        </div>
    </div>

    <!-- Total Gross Sales -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Gross Sales</div>
                <div class="h3 fw-bold text-primary mb-0"><?= e(format_price($grossSales)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">excl. cancelled</div>
            </div>
        </div>
    </div>

    <!-- Total Collected -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Paid</div>
                <div class="h3 fw-bold text-success mb-0"><?= e(format_price($totalCollected)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= $totalPayments ?> payment(s)</div>
            </div>
        </div>
    </div>

    <!-- Total Due -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Due</div>
                <div class="h3 fw-bold <?= $totalDue > 0 ? 'text-danger' : 'text-muted' ?> mb-0"><?= e(format_price($totalDue)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">outstanding balance</div>
            </div>
        </div>
    </div>

    <!-- Total Customers -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Customers</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalCustomers) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">+<?= $newCustomers ?> in period</div>
            </div>
        </div>
    </div>

    <!-- Completed Deliveries -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Deliveries Done</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalDeliveriesCompleted) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= $totalPickupsCompleted ?> pickups done</div>
            </div>
        </div>
    </div>
</div>

<!-- Visual Distribution Bars (Zero External Libraries, Pure Solid Colors) -->
<div class="row g-4 mb-4">
    <!-- Order Stage Distribution -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-diagram-2 me-2 text-primary"></i>Order Stage Distribution</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= $totalOrders ?> Total</span>
            </div>
            <div class="card-body p-3">
                <?php if ($totalOrders === 0): ?>
                    <p class="text-muted small mb-0 text-center py-3">No orders found for this period.</p>
                <?php else: 
                    $pctRec = round(($cntReceived / $totalOrders) * 100, 1);
                    $pctPrc = round(($cntProcessing / $totalOrders) * 100, 1);
                    $pctRdy = round(($cntReady / $totalOrders) * 100, 1);
                    $pctDel = round(($cntDelivered / $totalOrders) * 100, 1);
                    $pctCan = round(($cntCancelled / $totalOrders) * 100, 1);
                ?>
                    <!-- Multi-segment Progress Bar -->
                    <div class="progress mb-3" style="height: 18px;">
                        <div class="progress-bar bg-info" style="width: <?= $pctRec ?>%" title="Received: <?= $cntReceived ?>"></div>
                        <div class="progress-bar bg-warning text-dark" style="width: <?= $pctPrc ?>%" title="Processing: <?= $cntProcessing ?>"></div>
                        <div class="progress-bar bg-success" style="width: <?= $pctRdy ?>%" title="Ready: <?= $cntReady ?>"></div>
                        <div class="progress-bar bg-dark" style="width: <?= $pctDel ?>%" title="Delivered: <?= $cntDelivered ?>"></div>
                        <div class="progress-bar bg-danger" style="width: <?= $pctCan ?>%" title="Cancelled: <?= $cntCancelled ?>"></div>
                    </div>

                    <!-- Legend Items -->
                    <div class="row g-2 small">
                        <div class="col-6 col-md-4">
                            <span class="d-inline-block rounded-circle bg-info me-1" style="width: 10px; height: 10px;"></span>
                            Received: <strong><?= $cntReceived ?></strong> <span class="text-muted">(<?= $pctRec ?>%)</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="d-inline-block rounded-circle bg-warning me-1" style="width: 10px; height: 10px;"></span>
                            Processing: <strong><?= $cntProcessing ?></strong> <span class="text-muted">(<?= $pctPrc ?>%)</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="d-inline-block rounded-circle bg-success me-1" style="width: 10px; height: 10px;"></span>
                            Ready: <strong><?= $cntReady ?></strong> <span class="text-muted">(<?= $pctRdy ?>%)</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="d-inline-block rounded-circle bg-dark me-1" style="width: 10px; height: 10px;"></span>
                            Delivered: <strong><?= $cntDelivered ?></strong> <span class="text-muted">(<?= $pctDel ?>%)</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="d-inline-block rounded-circle bg-danger me-1" style="width: 10px; height: 10px;"></span>
                            Cancelled: <strong><?= $cntCancelled ?></strong> <span class="text-muted">(<?= $pctCan ?>%)</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Status Breakdown -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-primary"></i>Payment Status Distribution</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= $totalOrders - $cntCancelled ?> Active Orders</span>
            </div>
            <div class="card-body p-3">
                <?php 
                $activeOrderCount = $totalOrders - $cntCancelled;
                if ($activeOrderCount <= 0): 
                ?>
                    <p class="text-muted small mb-0 text-center py-3">No active orders found for this period.</p>
                <?php else: 
                    $pctPaid = round(($cntPayPaid / $activeOrderCount) * 100, 1);
                    $pctPart = round(($cntPayPartial / $activeOrderCount) * 100, 1);
                    $pctUnpd = round(($cntPayUnpaid / $activeOrderCount) * 100, 1);
                ?>
                    <!-- Multi-segment Progress Bar -->
                    <div class="progress mb-3" style="height: 18px;">
                        <div class="progress-bar bg-success" style="width: <?= $pctPaid ?>%" title="Paid: <?= $cntPayPaid ?>"></div>
                        <div class="progress-bar bg-warning text-dark" style="width: <?= $pctPart ?>%" title="Partial: <?= $cntPayPartial ?>"></div>
                        <div class="progress-bar bg-danger" style="width: <?= $pctUnpd ?>%" title="Unpaid: <?= $cntPayUnpaid ?>"></div>
                    </div>

                    <!-- Legend Items -->
                    <div class="row g-2 small">
                        <div class="col-4">
                            <span class="d-inline-block rounded-circle bg-success me-1" style="width: 10px; height: 10px;"></span>
                            Paid in Full: <strong><?= $cntPayPaid ?></strong> <span class="text-muted">(<?= $pctPaid ?>%)</span>
                        </div>
                        <div class="col-4">
                            <span class="d-inline-block rounded-circle bg-warning me-1" style="width: 10px; height: 10px;"></span>
                            Partially Paid: <strong><?= $cntPayPartial ?></strong> <span class="text-muted">(<?= $pctPart ?>%)</span>
                        </div>
                        <div class="col-4">
                            <span class="d-inline-block rounded-circle bg-danger me-1" style="width: 10px; height: 10px;"></span>
                            Unpaid: <strong><?= $cntPayUnpaid ?></strong> <span class="text-muted">(<?= $pctUnpd ?>%)</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Sectional Summaries -->
<div class="row g-4">
    <!-- Financial Overview Table -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-cash me-2 text-primary"></i>Financial Summary</h3>
                <?php if (is_admin() || is_manager()): ?>
                    <a href="<?= base_url('modules/reports/sales.php?' . http_build_query($_GET)) ?>" class="small text-decoration-none">
                        View Sales Report <i class="bi bi-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">Gross Order Value (Excl. Cancelled):</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-dark"><?= e(format_price($grossSales)) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Direct Collections Received in Period:</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-success"><?= e(format_price($totalCollected)) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Outstanding Customer Receivables:</td>
                            <td class="text-end pe-3 font-monospace fw-bold <?= $totalDue > 0 ? 'text-danger' : 'text-muted' ?>"><?= e(format_price($totalDue)) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Collection Rate:</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                <?= $grossSales > 0 ? round(($totalCollected / $grossSales) * 100, 1) : 0 ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Logistics & Dispatch Summary Table -->
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-truck me-2 text-primary"></i>Logistics &amp; Dispatch Summary</h3>
                <a href="<?= base_url('modules/reports/delivery.php?' . http_build_query($_GET)) ?>" class="small text-decoration-none">
                    View Delivery Report <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 small">
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">Total Pickup Requests Scheduled:</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-dark"><?= (int)($delivStats['total_pickups'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Pickups Completed Successfully:</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-success"><?= $totalPickupsCompleted ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Total Delivery Dispatches Scheduled:</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-dark"><?= (int)($delivStats['total_deliveries'] ?? 0) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Deliveries Completed to Customers:</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-success"><?= $totalDeliveriesCompleted ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
