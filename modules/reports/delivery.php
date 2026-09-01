<?php
/**
 * Reports & Analytics — Pickup & Delivery Logistics Report
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Delivery Report';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

// 1. Overall Logistics Metrics in Period
$summaryStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total_dispatches,
        SUM(CASE WHEN type = "pickup" THEN 1 ELSE 0 END) AS total_pickups,
        SUM(CASE WHEN type = "pickup" AND status = "completed" THEN 1 ELSE 0 END) AS completed_pickups,
        SUM(CASE WHEN type = "pickup" AND status = "pending" THEN 1 ELSE 0 END) AS pending_pickups,
        SUM(CASE WHEN type = "delivery" THEN 1 ELSE 0 END) AS total_deliveries,
        SUM(CASE WHEN type = "delivery" AND status = "completed" THEN 1 ELSE 0 END) AS completed_deliveries,
        SUM(CASE WHEN type = "delivery" AND status = "pending" THEN 1 ELSE 0 END) AS pending_deliveries,
        SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) AS in_progress_dispatches,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cancelled_dispatches
    FROM pickup_deliveries
    WHERE scheduled_date BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
');
$summaryStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$summary = $summaryStmt->fetch() ?: [];

$totalDispatches     = (int)($summary['total_dispatches'] ?? 0);
$totalPickups        = (int)($summary['total_pickups'] ?? 0);
$completedPickups    = (int)($summary['completed_pickups'] ?? 0);
$pendingPickups      = (int)($summary['pending_pickups'] ?? 0);
$totalDeliveries     = (int)($summary['total_deliveries'] ?? 0);
$completedDeliveries = (int)($summary['completed_deliveries'] ?? 0);
$pendingDeliveries   = (int)($summary['pending_deliveries'] ?? 0);
$inProgress          = (int)($summary['in_progress_dispatches'] ?? 0);
$cancelled           = (int)($summary['cancelled_dispatches'] ?? 0);

// 2. Daily Logistics Breakdown SQL Aggregation
$dailySql = '
    SELECT 
        scheduled_date AS dispatch_date,
        COUNT(id) AS day_total,
        SUM(CASE WHEN type = "pickup" THEN 1 ELSE 0 END) AS day_pickups,
        SUM(CASE WHEN type = "pickup" AND status = "completed" THEN 1 ELSE 0 END) AS day_pickups_done,
        SUM(CASE WHEN type = "delivery" THEN 1 ELSE 0 END) AS day_deliveries,
        SUM(CASE WHEN type = "delivery" AND status = "completed" THEN 1 ELSE 0 END) AS day_deliveries_done,
        SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) AS day_in_progress,
        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS day_cancelled
    FROM pickup_deliveries
    WHERE scheduled_date BETWEEN :start_date AND :end_date
      AND deleted_at IS NULL
    GROUP BY scheduled_date
    ORDER BY dispatch_date DESC
';
$dailyStmt = $pdo->prepare($dailySql);
$dailyStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$dailyRows = $dailyStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Pickup &amp; Delivery Logistics Report</h2>
            <p class="text-muted small mb-0">Route fulfillment statistics, dispatch schedules, and on-time delivery efficiency.</p>
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
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/delivery.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-truck me-1"></i> Delivery
            </a>
        </li>
    </ul>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="<?= base_url('modules/reports/delivery.php') ?>" class="row g-2 align-items-center">
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
                    <a href="<?= base_url('modules/reports/delivery.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
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
            <p class="small text-muted mb-0">Pickup &amp; Delivery Logistics Report</p>
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

<!-- Logistics Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Schedules</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalDispatches) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">all dispatches</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Pickups Scheduled</div>
                <div class="h3 fw-bold text-info mb-0"><?= number_format($totalPickups) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= $completedPickups ?> fulfilled</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Deliveries Done</div>
                <div class="h3 fw-bold text-success mb-0"><?= number_format($completedDeliveries) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">of <?= $totalDeliveries ?> total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">In Progress</div>
                <div class="h3 fw-bold text-warning mb-0"><?= number_format($inProgress) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">on the road</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Pending Dispatch</div>
                <div class="h3 fw-bold text-secondary mb-0"><?= number_format($pendingPickups + $pendingDeliveries) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">awaiting pickup/drop</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Fulfilled Ratio</div>
                <div class="h3 fw-bold text-primary mb-0">
                    <?= $totalDispatches > 0 ? round((($completedPickups + $completedDeliveries) / $totalDispatches) * 100, 1) : 0 ?>%
                </div>
                <div class="small text-muted" style="font-size: 0.75rem;">completed efficiency</div>
            </div>
        </div>
    </div>
</div>

<!-- Daily Dispatch Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calendar2-range me-2 text-primary"></i>Daily Route &amp; Dispatch Flow</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= count($dailyRows) ?> dispatch date(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Scheduled Date</th>
                        <th class="text-center" style="width: 120px;">Total Dispatches</th>
                        <th class="text-center" style="width: 130px;">Pickups (Done / Total)</th>
                        <th class="text-center" style="width: 130px;">Deliveries (Done / Total)</th>
                        <th class="text-center" style="width: 110px;">In Progress</th>
                        <th class="text-center" style="width: 100px;">Cancelled</th>
                        <th class="text-end pe-3" style="width: 120px;">Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-truck fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No pickup or delivery schedules found in this period</h5>
                                <p class="small mb-0">Adjust your date range or clear your filter.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyRows as $row): 
                            $dTot = (int)$row['day_total'];
                            $dDone = (int)$row['day_pickups_done'] + (int)$row['day_deliveries_done'];
                            $rate = $dTot > 0 ? round(($dDone / $dTot) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-semibold text-dark">
                                    <?= e(format_datetime($row['dispatch_date'], 'M d, Y (D)')) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border font-monospace"><?= $dTot ?></span>
                                </td>
                                <td class="text-center font-monospace">
                                    <span class="text-success fw-bold"><?= (int)$row['day_pickups_done'] ?></span> / <span class="text-muted"><?= (int)$row['day_pickups'] ?></span>
                                </td>
                                <td class="text-center font-monospace">
                                    <span class="text-success fw-bold"><?= (int)$row['day_deliveries_done'] ?></span> / <span class="text-muted"><?= (int)$row['day_deliveries'] ?></span>
                                </td>
                                <td class="text-center font-monospace text-warning fw-semibold">
                                    <?= (int)$row['day_in_progress'] ?>
                                </td>
                                <td class="text-center font-monospace text-danger">
                                    <?= (int)$row['day_cancelled'] ?>
                                </td>
                                <td class="text-end pe-3 font-monospace fw-semibold <?= $rate >= 80 ? 'text-success' : ($rate >= 40 ? 'text-warning' : 'text-muted') ?>">
                                    <?= $rate ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($dailyRows)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="ps-3">Total:</td>
                            <td class="text-center font-monospace"><?= $totalDispatches ?></td>
                            <td class="text-center font-monospace text-info"><?= $completedPickups ?> / <?= $totalPickups ?></td>
                            <td class="text-center font-monospace text-success"><?= $completedDeliveries ?> / <?= $totalDeliveries ?></td>
                            <td class="text-center font-monospace text-warning"><?= $inProgress ?></td>
                            <td class="text-center font-monospace text-danger"><?= $cancelled ?></td>
                            <td class="text-end pe-3 font-monospace">
                                <?= $totalDispatches > 0 ? round((($completedPickups + $completedDeliveries) / $totalDispatches) * 100, 1) : 0 ?>%
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
