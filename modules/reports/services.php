<?php
/**
 * Reports & Analytics — Services & Garment Demand Report
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Services Report';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

// 1. Overall Service & Item Volume in Period
$summaryStmt = $pdo->prepare('
    SELECT 
        COUNT(oi.id) AS total_item_rows,
        COALESCE(SUM(oi.quantity), 0) AS total_units_processed,
        COALESCE(SUM(oi.line_total), 0) AS total_service_revenue,
        COUNT(DISTINCT oi.order_id) AS total_serviced_orders
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.order_date) BETWEEN :start_date AND :end_date
      AND o.status != "cancelled"
      AND o.deleted_at IS NULL
');
$summaryStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$summary = $summaryStmt->fetch() ?: [];

$totalItemRows       = (int)($summary['total_item_rows'] ?? 0);
$totalUnitsProcessed = (float)($summary['total_units_processed'] ?? 0);
$totalServiceRevenue = (float)($summary['total_service_revenue'] ?? 0);
$totalServicedOrders = (int)($summary['total_serviced_orders'] ?? 0);

// 2. Service Category & Garment Items Aggregation
$servicesSql = '
    SELECT 
        oi.service_name,
        oi.item_name,
        COUNT(oi.id) AS times_ordered,
        COALESCE(SUM(oi.quantity), 0) AS total_quantity,
        COALESCE(SUM(oi.line_total), 0) AS item_revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.order_date) BETWEEN :start_date AND :end_date
      AND o.status != "cancelled"
      AND o.deleted_at IS NULL
    GROUP BY oi.service_name, oi.item_name
    ORDER BY item_revenue DESC, total_quantity DESC
';
$servicesStmt = $pdo->prepare($servicesSql);
$servicesStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$serviceRows = $servicesStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Services &amp; Item Demand Report</h2>
            <p class="text-muted small mb-0">Garment volume, popularity of laundry service categories, and itemized revenue.</p>
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
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/services.php?' . http_build_query($_GET)) ?>">
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
            <form method="GET" action="<?= base_url('modules/reports/services.php') ?>" class="row g-2 align-items-center">
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
                    <a href="<?= base_url('modules/reports/services.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
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
            <p class="small text-muted mb-0">Services &amp; Item Demand Analytics</p>
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

<!-- Service Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Service Revenue</div>
                <div class="h3 fw-bold text-primary mb-0"><?= e(format_price($totalServiceRevenue)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">excl. cancelled orders</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Units / Qty Processed</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalUnitsProcessed, 1) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">items &amp; KG processed</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Item Lines Handled</div>
                <div class="h3 fw-bold text-success mb-0"><?= number_format($totalItemRows) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">garment entries</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Serviced Orders</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalServicedOrders) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">active orders in period</div>
            </div>
        </div>
    </div>
</div>

<!-- Services & Garment Breakdown Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-tags me-2 text-primary"></i>Service &amp; Garment Popularity Breakdown</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= count($serviceRows) ?> item category/rate(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">#</th>
                        <th>Service Category</th>
                        <th>Garment / Item Name</th>
                        <th class="text-center" style="width: 130px;">Times Ordered</th>
                        <th class="text-center" style="width: 140px;">Total Qty / Weight</th>
                        <th class="text-end" style="width: 160px;">Revenue Generated</th>
                        <th class="text-end pe-3" style="width: 140px;">Share (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($serviceRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-tags fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No garment processing data found in this period</h5>
                                <p class="small mb-0">Adjust your date range or clear filters.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($serviceRows as $idx => $sr): 
                            $rev = (float)$sr['item_revenue'];
                            $sharePct = $totalServiceRevenue > 0 ? round(($rev / $totalServiceRevenue) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="ps-3 font-monospace text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($sr['service_name']) ?></span>
                                </td>
                                <td class="fw-semibold text-dark">
                                    <?= e($sr['item_name']) ?>
                                </td>
                                <td class="text-center font-monospace">
                                    <?= (int)$sr['times_ordered'] ?>
                                </td>
                                <td class="text-center font-monospace">
                                    <?= (float)$sr['total_quantity'] == (int)$sr['total_quantity'] ? (int)$sr['total_quantity'] : number_format((float)$sr['total_quantity'], 2) ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-primary">
                                    <?= e(format_price($rev)) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
                                            <div class="progress-bar bg-success" style="width: <?= $sharePct ?>%"></div>
                                        </div>
                                        <span class="font-monospace fw-semibold"><?= $sharePct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($serviceRows)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-3">Total:</td>
                            <td class="text-center font-monospace"><?= $totalItemRows ?></td>
                            <td class="text-center font-monospace"><?= number_format($totalUnitsProcessed, 1) ?></td>
                            <td class="text-end font-monospace text-primary"><?= e(format_price($totalServiceRevenue)) ?></td>
                            <td class="text-end pe-3 font-monospace">100.0%</td>
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
