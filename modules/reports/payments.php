<?php
/**
 * Reports & Analytics — Payment Collections Report
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$pageTitle = 'Payments Report';
$currentUser = current_user();
$pdo = getDBConnection();

// Parse Date Range Filter
$dateRange = get_report_date_range($_GET, 'this_month');
$startDate = $dateRange['start_date'];
$endDate   = $dateRange['end_date'];
$rangePreset = $dateRange['preset'];
$rangeLabel = $dateRange['label'];

$methodFilter = sanitize_input($_GET['method'] ?? '');
$validMethods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'other'];
if (!in_array($methodFilter, $validMethods, true)) {
    $methodFilter = '';
}

// 1. Overall Completed Payment Summary in Range
$summaryStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total_transactions,
        COALESCE(SUM(amount), 0) AS total_collected,
        COALESCE(AVG(amount), 0) AS avg_transaction
    FROM payments
    WHERE DATE(payment_date) BETWEEN :start_date AND :end_date
      AND status = "completed"
      AND deleted_at IS NULL
');
$summaryStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$summary = $summaryStmt->fetch() ?: [];

$totalTransactions = (int)($summary['total_transactions'] ?? 0);
$totalCollected    = (float)($summary['total_collected'] ?? 0);
$avgTransaction    = (float)($summary['avg_transaction'] ?? 0);

// 2. Payment Method Breakdown SQL Aggregation
$methodStmt = $pdo->prepare('
    SELECT 
        payment_method,
        COUNT(id) AS method_transactions,
        COALESCE(SUM(amount), 0) AS method_total
    FROM payments
    WHERE DATE(payment_date) BETWEEN :start_date AND :end_date
      AND status = "completed"
      AND deleted_at IS NULL
    GROUP BY payment_method
    ORDER BY method_total DESC
');
$methodStmt->execute([
    'start_date' => $startDate,
    'end_date'   => $endDate
]);
$methodRows = $methodStmt->fetchAll();

// 3. Detailed Transactions Listing in Period
$txParams = [
    'start_date' => $startDate,
    'end_date'   => $endDate
];

$txSql = '
    SELECT 
        p.id, p.payment_number, p.order_id, p.amount, p.payment_method, 
        p.transaction_reference, p.payment_date, p.status,
        o.order_number,
        c.name AS customer_name, c.phone AS customer_phone,
        u.name AS receiver_name
    FROM payments p
    INNER JOIN orders o ON p.order_id = o.id
    INNER JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE DATE(p.payment_date) BETWEEN :start_date AND :end_date
      AND p.status = "completed"
      AND p.deleted_at IS NULL
';

if (!empty($methodFilter)) {
    $txSql .= ' AND p.payment_method = :method';
    $txParams['method'] = $methodFilter;
}

$txSql .= ' ORDER BY p.payment_date DESC, p.id DESC LIMIT 100';

$txStmt = $pdo->prepare($txSql);
$txStmt->execute($txParams);
$transactions = $txStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Non-Printable Header & Tabs -->
<div class="d-print-none mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Payment Collections Report</h2>
            <p class="text-muted small mb-0">Financial cash-flow analysis, transaction channels, and settlement logs.</p>
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
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/sales.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-cash-stack me-1"></i> Sales
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-dark py-1 px-3 small" href="<?= base_url('modules/reports/orders.php?' . http_build_query($_GET)) ?>">
                <i class="bi bi-basket me-1"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active py-1 px-3 fw-semibold small" href="<?= base_url('modules/reports/payments.php?' . http_build_query($_GET)) ?>">
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
            <form method="GET" action="<?= base_url('modules/reports/payments.php') ?>" class="row g-2 align-items-center">
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

                <!-- Method Filter -->
                <div class="col-6 col-sm-3 col-md-2">
                    <label class="form-label small text-muted mb-1">Payment Method</label>
                    <select name="method" class="form-select form-select-sm" onchange="this.form.submit();">
                        <option value="">All Channels</option>
                        <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= $methodFilter === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                        <option value="mobile_banking" <?= $methodFilter === 'mobile_banking' ? 'selected' : '' ?>>Mobile Banking</option>
                        <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="other" <?= $methodFilter === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex gap-2 align-self-end ms-auto">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="<?= base_url('modules/reports/payments.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
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
            <p class="small text-muted mb-0">Payment Collections &amp; Financial Settlement Report</p>
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

<!-- Payment Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Collected</div>
                <div class="h3 fw-bold text-success mb-0"><?= e(format_price($totalCollected)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">completed collections</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Transactions</div>
                <div class="h3 fw-bold text-dark mb-0"><?= number_format($totalTransactions) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">completed receipts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Average Ticket Size</div>
                <div class="h3 fw-bold text-primary mb-0"><?= e(format_price($avgTransaction)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">per payment receipt</div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Method Distribution Breakdown -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-pie-chart me-2 text-primary"></i>Collections by Payment Channel</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Payment Channel</th>
                        <th class="text-center" style="width: 130px;">Transactions</th>
                        <th class="text-end" style="width: 180px;">Total Collected</th>
                        <th class="text-end pe-3" style="width: 160px;">Share (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($methodRows)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No collections recorded in this period.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($methodRows as $mr): 
                            $mTotal = (float)$mr['method_total'];
                            $mPct = $totalCollected > 0 ? round(($mTotal / $totalCollected) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-dark">
                                    <?= e(payment_method_label($mr['payment_method'])) ?>
                                </td>
                                <td class="text-center font-monospace">
                                    <?= (int)$mr['method_transactions'] ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-success">
                                    <?= e(format_price($mTotal)) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 80px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $mPct ?>%"></div>
                                        </div>
                                        <span class="font-monospace fw-semibold"><?= $mPct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Transactions Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Completed Payment Transactions</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= count($transactions) ?> record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date &amp; Time</th>
                        <th>Receipt #</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Channel</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end pe-3">Received By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-credit-card-2-front fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No completed payment receipts found</h5>
                                <p class="small mb-0">Try selecting a different date range or channel filter.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="ps-3 font-monospace text-muted">
                                    <?= e(format_datetime($tx['payment_date'], 'M d, Y h:i A')) ?>
                                </td>
                                <td class="font-monospace fw-semibold">
                                    <a href="<?= base_url('modules/payments/show.php?id=' . (int)$tx['id']) ?>" class="text-decoration-none">
                                        <?= e($tx['payment_number']) ?>
                                    </a>
                                </td>
                                <td class="font-monospace">
                                    <a href="<?= base_url('modules/orders/show.php?id=' . (int)$tx['order_id']) ?>" class="text-decoration-none">
                                        <?= e($tx['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($tx['customer_name']) ?></div>
                                    <div class="text-muted font-monospace" style="font-size: 0.75rem;"><?= e($tx['customer_phone']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e(payment_method_label($tx['payment_method'])) ?></span>
                                    <?php if (!empty($tx['transaction_reference'])): ?>
                                        <div class="font-monospace text-muted" style="font-size: 0.7rem;">Ref: <?= e($tx['transaction_reference']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-success">
                                    <?= e(format_price($tx['amount'])) ?>
                                </td>
                                <td class="text-end pe-3 text-muted small">
                                    <?= e($tx['receiver_name'] ?: 'System') ?>
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
