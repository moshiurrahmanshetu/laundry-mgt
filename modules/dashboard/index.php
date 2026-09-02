<?php
/**
 * Real-Time Commercial Business Dashboard
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Dashboard';
$currentUser = current_user(true);
$pdo = getDBConnection();

$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

// 1. Orders Aggregate Metrics
$ordStats = [
    'total_orders'          => 0,
    'today_orders'          => 0,
    'count_received'        => 0,
    'count_processing'      => 0,
    'count_ready'           => 0,
    'count_delivered'       => 0,
    'count_cancelled'       => 0,
    'all_time_gross'        => 0.00,
    'today_gross'           => 0.00,
    'month_gross'           => 0.00,
    'total_receivables_due' => 0.00,
];

try {
    $ordStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN DATE(order_date) = CURDATE() THEN 1 ELSE 0 END), 0) AS today_orders,
            COALESCE(SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END), 0) AS count_received,
            COALESCE(SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END), 0) AS count_processing,
            COALESCE(SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END), 0) AS count_ready,
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) AS count_delivered,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS count_cancelled,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0.00) AS all_time_gross,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND DATE(order_date) = CURDATE() THEN total ELSE 0 END), 0.00) AS today_gross,
            COALESCE(SUM(CASE WHEN status != 'cancelled' AND DATE(order_date) BETWEEN '$monthStart' AND '$monthEnd' THEN total ELSE 0 END), 0.00) AS month_gross,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN due_amount ELSE 0 END), 0.00) AS total_receivables_due
        FROM orders
        WHERE deleted_at IS NULL
    ");
    $fetchedOrd = $ordStmt->fetch();
    if ($fetchedOrd) {
        $ordStats = array_merge($ordStats, $fetchedOrd);
    }
} catch (PDOException $e) {
    error_log('Dashboard Orders Error: ' . $e->getMessage());
}

$totalOrders      = (int)$ordStats['total_orders'];
$todayOrders      = (int)$ordStats['today_orders'];
$cntReceived      = (int)$ordStats['count_received'];
$cntProcessing    = (int)$ordStats['count_processing'];
$cntReady         = (int)$ordStats['count_ready'];
$cntDelivered     = (int)$ordStats['count_delivered'];
$cntCancelled     = (int)$ordStats['count_cancelled'];
$monthGross       = (float)$ordStats['month_gross'];
$totalDue         = (float)$ordStats['total_receivables_due'];

// Active orders currently in the facility
$activeFacilityOrders = $cntReceived + $cntProcessing;

// 2. Authoritative Payments Collections
$payStats = [
    'total_payments'       => 0,
    'today_collections'    => 0.00,
    'month_collections'    => 0.00,
    'all_time_collections' => 0.00
];

try {
    $payStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_payments,
            COALESCE(SUM(CASE WHEN DATE(payment_date) = CURDATE() THEN amount ELSE 0 END), 0.00) AS today_collections,
            COALESCE(SUM(CASE WHEN DATE(payment_date) BETWEEN '$monthStart' AND '$monthEnd' THEN amount ELSE 0 END), 0.00) AS month_collections,
            COALESCE(SUM(amount), 0.00) AS all_time_collections
        FROM payments
        WHERE status = 'completed'
    ");
    $fetchedPay = $payStmt->fetch();
    if ($fetchedPay) {
        $payStats = array_merge($payStats, $fetchedPay);
    }
} catch (PDOException $e) {
    error_log('Dashboard Payments Error: ' . $e->getMessage());
}

$todayCollections = (float)$payStats['today_collections'];
$monthCollections = (float)$payStats['month_collections'];
$totalPaymentsCount = (int)$payStats['total_payments'];

// 3. Operating Expenses
$expStats = [
    'today_expenses'    => 0.00,
    'month_expenses'    => 0.00,
    'all_time_expenses' => 0.00
];

try {
    $expStmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN expense_date = CURDATE() THEN amount ELSE 0 END), 0.00) AS today_expenses,
            COALESCE(SUM(CASE WHEN expense_date BETWEEN '$monthStart' AND '$monthEnd' THEN amount ELSE 0 END), 0.00) AS month_expenses,
            COALESCE(SUM(amount), 0.00) AS all_time_expenses
        FROM expenses
        WHERE deleted_at IS NULL
    ");
    $fetchedExp = $expStmt->fetch();
    if ($fetchedExp) {
        $expStats = array_merge($expStats, $fetchedExp);
    }
} catch (PDOException $e) {
    error_log('Dashboard Expenses Error: ' . $e->getMessage());
}

$todayExpenses = (float)$expStats['today_expenses'];
$monthExpenses = (float)$expStats['month_expenses'];

// 4. Logistics Summary
$delivStats = [
    'total_schedules'   => 0,
    'pending_pickups'   => 0,
    'pending_deliveries'=> 0,
    'in_transit'        => 0,
    'completed_today'   => 0
];

try {
    $delivStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_schedules,
            COALESCE(SUM(CASE WHEN type = 'pickup' AND status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_pickups,
            COALESCE(SUM(CASE WHEN type = 'delivery' AND status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_deliveries,
            COALESCE(SUM(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END), 0) AS in_transit,
            COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS completed_today
        FROM pickup_deliveries
        WHERE deleted_at IS NULL
    ");
    $fetchedDeliv = $delivStmt->fetch();
    if ($fetchedDeliv) {
        $delivStats = array_merge($delivStats, $fetchedDeliv);
    }
} catch (PDOException $e) {
    error_log('Dashboard Delivery Error: ' . $e->getMessage());
}

// 5. Top 5 Laundry Services
$topServices = [];
try {
    $topSvcStmt = $pdo->query("
        SELECT 
            COALESCE(s.name, oi.service_name) AS service_name,
            COUNT(DISTINCT oi.order_id) AS order_count,
            COALESCE(SUM(oi.quantity), 0) AS total_pieces,
            COALESCE(SUM(oi.line_total), 0.00) AS total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id AND o.deleted_at IS NULL AND o.status != 'cancelled'
        LEFT JOIN services s ON oi.service_id = s.id
        GROUP BY service_name
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $topServices = $topSvcStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Dashboard Top Services Error: ' . $e->getMessage());
}

// 6. Recent 6 Orders
$recentOrders = [];
try {
    $recentOrdStmt = $pdo->query("
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.deleted_at IS NULL
        ORDER BY o.id DESC
        LIMIT 6
    ");
    $recentOrders = $recentOrdStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Dashboard Recent Orders Error: ' . $e->getMessage());
}

// 7. Recent 6 Payments
$recentPayments = [];
try {
    $recentPayStmt = $pdo->query("
        SELECT p.*, o.order_number, c.name AS customer_name
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        JOIN customers c ON o.customer_id = c.id
        WHERE p.status = 'completed'
        ORDER BY p.id DESC
        LIMIT 6
    ");
    $recentPayments = $recentPayStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Dashboard Recent Payments Error: ' . $e->getMessage());
}

// 8. 7-Day Activity & Revenue Trend
$trend7 = [];
try {
    $trendStmt = $pdo->query("
        SELECT 
            d.dt AS order_date,
            DATE_FORMAT(d.dt, '%a (%d/%m)') AS formatted_day,
            COALESCE(COUNT(o.id), 0) AS order_count,
            COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total ELSE 0 END), 0.00) AS daily_revenue
        FROM (
            SELECT CURDATE() - INTERVAL 6 DAY AS dt UNION ALL
            SELECT CURDATE() - INTERVAL 5 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 4 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 3 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 2 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 1 DAY UNION ALL
            SELECT CURDATE()
        ) d
        LEFT JOIN orders o ON DATE(o.order_date) = d.dt AND o.deleted_at IS NULL
        GROUP BY d.dt
        ORDER BY d.dt ASC
    ");
    $trend7 = $trendStmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Dashboard Trend Error: ' . $e->getMessage());
}

// Find maximum value for SVG/Bar scale
$maxTrendOrders = 1;
$maxTrendRevenue = 1.00;
foreach ($trend7 as $tRow) {
    if ((int)$tRow['order_count'] > $maxTrendOrders) {
        $maxTrendOrders = (int)$tRow['order_count'];
    }
    if ((float)$tRow['daily_revenue'] > $maxTrendRevenue) {
        $maxTrendRevenue = (float)$tRow['daily_revenue'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header Greeting & Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 bg-white shadow-sm p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(user_avatar_url($currentUser['avatar'] ?? null)) ?>" 
                         alt="Avatar" 
                         class="rounded-circle border flex-shrink-0" 
                         style="width: 60px; height: 60px; object-fit: cover;">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h4 fw-bold text-dark mb-0">Welcome back, <?= e($currentUser['name']) ?>!</h2>
                            <?= role_badge($currentUser['role_slug'] ?? '', $currentUser['role_name'] ?? 'Staff') ?>
                        </div>
                        <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
                            <span><i class="bi bi-calendar3 me-1 text-primary"></i>Today: <strong><?= e(format_date(date('Y-m-d'))) ?></strong></span>
                            <span>&bull;</span>
                            <span><i class="bi bi-shop me-1 text-secondary"></i><strong><?= e(get_setting('business_name', APP_NAME)) ?></strong></span>
                            <span>&bull;</span>
                            <span><i class="bi bi-clock-history me-1"></i>Last login: <strong><?= e(format_datetime($currentUser['last_login'])) ?></strong></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary btn-sm px-3 shadow-sm">
                        <i class="bi bi-plus-lg me-1"></i> New Order
                    </a>
                    <a href="<?= base_url('modules/payments/create.php') ?>" class="btn btn-success btn-sm px-3 shadow-sm">
                        <i class="bi bi-cash-coin me-1"></i> Receive Payment
                    </a>
                    <a href="<?= base_url('modules/operations/index.php') ?>" class="btn btn-outline-primary btn-sm px-3">
                        <i class="bi bi-arrow-repeat me-1"></i> Operations Queue
                    </a>
                    <?php if (has_permission('expenses.manage') || has_role('administrator') || has_role('manager')): ?>
                        <a href="<?= base_url('modules/expenses/create.php') ?>" class="btn btn-outline-danger btn-sm px-3">
                            <i class="bi bi-wallet2 me-1"></i> Record Expense
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== 1. PRIMARY OPERATIONAL & FINANCIAL KPI CARDS ==================== -->
<div class="row g-3 mb-4">
    <!-- Total Orders -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Orders</span>
                    <div class="p-2 bg-primary-subtle text-primary rounded fs-5">
                        <i class="bi bi-basket3-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-1"><?= number_format($totalOrders) ?></div>
                <div class="small text-muted d-flex align-items-center gap-1">
                    <span class="badge bg-primary-subtle text-primary"><i class="bi bi-plus-circle me-1"></i><?= $todayOrders ?> Today</span>
                    <span class="text-muted">all-time orders</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Active in Facility (Received + Processing) -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Active Processing</span>
                    <div class="p-2 bg-warning-subtle text-warning rounded fs-5">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-warning mb-1"><?= number_format($activeFacilityOrders) ?></div>
                <div class="small text-muted d-flex justify-content-between">
                    <span><strong><?= $cntReceived ?></strong> received</span>
                    <span>&bull;</span>
                    <span><strong><?= $cntProcessing ?></strong> washing/press</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Ready for Collection / Delivery -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Ready for Pickup</span>
                    <div class="p-2 bg-success-subtle text-success rounded fs-5">
                        <i class="bi bi-bag-check-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-success mb-1"><?= number_format($cntReady) ?></div>
                <div class="small text-muted">
                    <span><i class="bi bi-check2-all text-success me-1"></i>Completed wash, awaiting client</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Collections -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Today's Collections</span>
                    <div class="p-2 bg-success-subtle text-success rounded fs-5">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-success mb-1 font-monospace"><?= e(format_price($todayCollections)) ?></div>
                <div class="small text-muted">
                    <span><i class="bi bi-receipt me-1"></i>Direct payment collections today</span>
                </div>
            </div>
        </div>
    </div>

    <!-- This Month's Gross Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Month's Gross Sales</span>
                    <div class="p-2 bg-info-subtle text-info rounded fs-5">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-primary mb-1 font-monospace"><?= e(format_price($monthGross)) ?></div>
                <div class="small text-muted">
                    <span><i class="bi bi-calendar-check me-1"></i>Booked orders for <?= date('F') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Outstanding Due / Receivables -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Receivables Due</span>
                    <div class="p-2 bg-danger-subtle text-danger rounded fs-5">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-danger mb-1 font-monospace"><?= e(format_price($totalDue)) ?></div>
                <div class="small text-muted">
                    <span><i class="bi bi-hourglass-split text-danger me-1"></i>Uncollected customer balances</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Operating Expenses (Month) -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Month's Expenses</span>
                    <div class="p-2 bg-danger-subtle text-danger rounded fs-5">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-danger mb-1 font-monospace"><?= e(format_price($monthExpenses)) ?></div>
                <div class="small text-muted">
                    <span><i class="bi bi-receipt-cutoff text-danger me-1"></i>Operational bills in <?= date('F') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Pickups & Deliveries -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Dispatch Logistics</span>
                    <div class="p-2 bg-warning-subtle text-dark rounded fs-5">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-1"><?= number_format($delivStats['in_transit'] + $delivStats['pending_pickups'] + $delivStats['pending_deliveries']) ?></div>
                <div class="small text-muted d-flex justify-content-between">
                    <span><strong><?= $delivStats['in_transit'] ?></strong> in-transit</span>
                    <span>&bull;</span>
                    <span><strong><?= $delivStats['completed_today'] ?></strong> done today</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== 2. CHARTS SECTION ==================== -->
<div class="row g-4 mb-4">
    <!-- Order Status Distribution Breakdown -->
    <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Order Stage Distribution</h6>
                <span class="badge bg-light text-dark border"><?= number_format($totalOrders) ?> Total</span>
            </div>
            <div class="card-body p-4">
                <?php if ($totalOrders > 0): 
                    $pctReceived   = round(($cntReceived / $totalOrders) * 100);
                    $pctProcessing = round(($cntProcessing / $totalOrders) * 100);
                    $pctReady      = round(($cntReady / $totalOrders) * 100);
                    $pctDelivered  = round(($cntDelivered / $totalOrders) * 100);
                    $pctCancelled  = round(($cntCancelled / $totalOrders) * 100);
                ?>
                    <!-- Horizontal Multi-Segment Distribution Bar (Solid Colors) -->
                    <div class="progress mb-4" style="height: 18px; border-radius: 6px; overflow: hidden;">
                        <?php if ($pctReceived > 0): ?>
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= $pctReceived ?>%" aria-valuenow="<?= $pctReceived ?>" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-title="Received: <?= $cntReceived ?>"></div>
                        <?php endif; ?>
                        <?php if ($pctProcessing > 0): ?>
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pctProcessing ?>%" aria-valuenow="<?= $pctProcessing ?>" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-title="Processing: <?= $cntProcessing ?>"></div>
                        <?php endif; ?>
                        <?php if ($pctReady > 0): ?>
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pctReady ?>%" aria-valuenow="<?= $pctReady ?>" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-title="Ready: <?= $cntReady ?>"></div>
                        <?php endif; ?>
                        <?php if ($pctDelivered > 0): ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pctDelivered ?>%" aria-valuenow="<?= $pctDelivered ?>" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-title="Delivered: <?= $cntDelivered ?>"></div>
                        <?php endif; ?>
                        <?php if ($pctCancelled > 0): ?>
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pctCancelled ?>%" aria-valuenow="<?= $pctCancelled ?>" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip" data-bs-title="Cancelled: <?= $cntCancelled ?>"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Status Breakdown List -->
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-info" style="width: 10px; height: 10px;"></span>
                                <span class="small fw-semibold text-dark">Received (Pending Wash)</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-white text-dark border"><?= $cntReceived ?></span>
                                <span class="small text-muted font-monospace" style="width: 40px; text-align: right;"><?= $pctReceived ?>%</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-warning" style="width: 10px; height: 10px;"></span>
                                <span class="small fw-semibold text-dark">In Processing (Washing/Ironing)</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-white text-dark border"><?= $cntProcessing ?></span>
                                <span class="small text-muted font-monospace" style="width: 40px; text-align: right;"><?= $pctProcessing ?>%</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-primary" style="width: 10px; height: 10px;"></span>
                                <span class="small fw-semibold text-dark">Ready for Pickup / Delivery</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-white text-dark border"><?= $cntReady ?></span>
                                <span class="small text-muted font-monospace" style="width: 40px; text-align: right;"><?= $pctReady ?>%</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-success" style="width: 10px; height: 10px;"></span>
                                <span class="small fw-semibold text-dark">Delivered &amp; Completed</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-white text-dark border"><?= $cntDelivered ?></span>
                                <span class="small text-muted font-monospace" style="width: 40px; text-align: right;"><?= $pctDelivered ?>%</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle bg-danger" style="width: 10px; height: 10px;"></span>
                                <span class="small fw-semibold text-dark">Cancelled Orders</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-white text-dark border"><?= $cntCancelled ?></span>
                                <span class="small text-muted font-monospace" style="width: 40px; text-align: right;"><?= $pctCancelled ?>%</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                        <div class="fw-semibold">No order data available yet</div>
                        <p class="small mb-3">Create your first laundry intake order to view distribution analytics.</p>
                        <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> Create First Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent 7-Day Order & Revenue Activity Trend -->
    <div class="col-12 col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>7-Day Business Intake &amp; Revenue Trend</h6>
                <span class="small text-muted">Past 7 Days</span>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($trend7)): ?>
                    <div class="row g-2 text-center align-items-end" style="min-height: 200px;">
                        <?php foreach ($trend7 as $t): 
                            $orderCnt = (int)$t['order_count'];
                            $revVal   = (float)$t['daily_revenue'];
                            $barHeightPct = $maxTrendRevenue > 0 ? max(12, min(100, round(($revVal / $maxTrendRevenue) * 100))) : 12;
                        ?>
                            <div class="col d-flex flex-column align-items-center justify-content-end h-100">
                                <div class="small fw-bold text-primary mb-1 font-monospace" style="font-size: 0.75rem;">
                                    <?= $orderCnt ?> <span class="text-muted fw-normal">ord</span>
                                </div>
                                <div class="w-100 rounded-top" 
                                     style="background-color: #0284c7; height: <?= $barHeightPct ?>%; min-height: 12px; transition: height 0.3s ease;"
                                     data-bs-toggle="tooltip" 
                                     data-bs-title="<?= e($t['formatted_day']) ?>: <?= $orderCnt ?> orders (<?= e(format_price($revVal)) ?>)">
                                </div>
                                <div class="small text-muted mt-2 font-monospace" style="font-size: 0.72rem; white-space: nowrap;">
                                    <?= e($t['formatted_day']) ?>
                                </div>
                                <div class="small fw-semibold text-success font-monospace" style="font-size: 0.7rem;">
                                    <?= e(format_price($revVal)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-center gap-4 mt-3 pt-3 border-top small text-muted">
                        <span><span class="d-inline-block rounded-circle bg-primary me-1" style="width: 8px; height: 8px;"></span> Daily Order Volume &amp; Booked Value</span>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-graph-up fs-1 d-block mb-2 text-secondary"></i>
                        <div class="fw-semibold">No recent activity</div>
                        <p class="small">Orders recorded in the past 7 days will chart here automatically.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== 3. TOP SERVICES & LOGISTICS ==================== -->
<div class="row g-4 mb-4">
    <!-- Top 5 Services -->
    <div class="col-12 col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-stars me-2 text-warning"></i>Top Performing Laundry Services</h6>
                <a href="<?= base_url('modules/services/index.php') ?>" class="small text-decoration-none fw-semibold">View Catalog</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($topServices)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 40px;">#</th>
                                    <th>Service Name</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-center">Volume</th>
                                    <th class="pe-3 text-end">Gross Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topServices as $idx => $svc): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge <?= $idx === 0 ? 'bg-warning text-dark' : 'bg-light text-dark border' ?> rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                                <?= $idx + 1 ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold text-dark">
                                            <?= e($svc['service_name']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border"><?= number_format($svc['order_count']) ?></span>
                                        </td>
                                        <td class="text-center small font-monospace">
                                            <?= number_format($svc['total_pieces']) ?> pcs/kg
                                        </td>
                                        <td class="pe-3 text-end fw-bold font-monospace text-success">
                                            <?= e(format_price($svc['total_revenue'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-tag fs-1 d-block mb-2 text-secondary"></i>
                        <div class="fw-semibold">No service statistics available</div>
                        <p class="small">Service volume will rank automatically as customers place orders.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logistics & Dispatch Status -->
    <div class="col-12 col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-truck me-2 text-primary"></i>Pickup &amp; Delivery Logistics</h6>
                <a href="<?= base_url('modules/delivery/index.php') ?>" class="small text-decoration-none fw-semibold">Dispatch Hub</a>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light text-center">
                            <div class="h4 fw-bold text-warning mb-1"><?= $delivStats['pending_pickups'] ?></div>
                            <div class="small text-muted fw-semibold"><i class="bi bi-arrow-down-left-circle me-1 text-warning"></i>Pending Pickups</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light text-center">
                            <div class="h4 fw-bold text-primary mb-1"><?= $delivStats['pending_deliveries'] ?></div>
                            <div class="small text-muted fw-semibold"><i class="bi bi-arrow-up-right-circle me-1 text-primary"></i>Pending Deliveries</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light text-center">
                            <div class="h4 fw-bold text-dark mb-1"><?= $delivStats['in_transit'] ?></div>
                            <div class="small text-muted fw-semibold"><i class="bi bi-bicycle me-1 text-dark"></i>Assigned / In Transit</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded bg-light text-center">
                            <div class="h4 fw-bold text-success mb-1"><?= $delivStats['completed_today'] ?></div>
                            <div class="small text-muted fw-semibold"><i class="bi bi-check-circle-fill me-1 text-success"></i>Fulfilled Today</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>Manage routes and driver assignments</span>
                    <a href="<?= base_url('modules/delivery/create.php') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Schedule Request
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== 4. RECENT ORDERS & RECENT PAYMENTS ==================== -->
<div class="row g-4 mb-4">
    <!-- Recent Orders Table -->
    <div class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Orders</h6>
                <a href="<?= base_url('modules/orders/index.php') ?>" class="small text-decoration-none fw-semibold">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentOrders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Order #</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th class="pe-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace">
                                            <a href="<?= base_url('modules/orders/show.php?id=' . (int)$o['id']) ?>" class="text-decoration-none">
                                                <?= e($o['order_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark small"><?= e($o['customer_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= e($o['customer_phone']) ?></div>
                                        </td>
                                        <td><?= order_status_badge($o['status']) ?></td>
                                        <td class="fw-bold font-monospace small"><?= e(format_price($o['total'])) ?></td>
                                        <td><?= payment_status_badge($o['payment_status']) ?></td>
                                        <td class="pe-3 text-end">
                                            <a href="<?= base_url('modules/orders/show.php?id=' . (int)$o['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-basket3 fs-1 d-block mb-2 text-secondary"></i>
                        <div class="fw-semibold">No recent orders</div>
                        <p class="small mb-3">Your latest customer laundry orders will appear here.</p>
                        <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> New Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Payments Table -->
    <div class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-cash-stack me-2 text-success"></i>Recent Payments</h6>
                <a href="<?= base_url('modules/payments/index.php') ?>" class="small text-decoration-none fw-semibold">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentPayments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Receipt #</th>
                                    <th>Customer / Order</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th class="pe-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $p): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace">
                                            <a href="<?= base_url('modules/payments/show.php?id=' . (int)$p['id']) ?>" class="text-decoration-none text-success">
                                                <?= e($p['payment_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark small"><?= e($p['customer_name']) ?></div>
                                            <div class="text-muted font-monospace" style="font-size: 0.75rem;">Order: <?= e($p['order_number']) ?></div>
                                        </td>
                                        <td class="fw-bold font-monospace text-success small"><?= e(format_price($p['amount'])) ?></td>
                                        <td><?= payment_method_badge($p['payment_method']) ?></td>
                                        <td class="small text-muted"><?= e(format_date($p['payment_date'])) ?></td>
                                        <td class="pe-3 text-end">
                                            <a href="<?= base_url('modules/payments/show.php?id=' . (int)$p['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" title="View Voucher">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-credit-card fs-1 d-block mb-2 text-secondary"></i>
                        <div class="fw-semibold">No payments recorded</div>
                        <p class="small mb-3">Collected customer payments and installments will list here.</p>
                        <a href="<?= base_url('modules/payments/create.php') ?>" class="btn btn-success btn-sm">
                            <i class="bi bi-cash-coin me-1"></i> Receive Payment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== 5. QUICK ACTIONS RIBBON ==================== -->
<div class="card shadow-sm border-0 mb-2">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small fw-semibold text-secondary text-uppercase">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Quick System Actions
            </span>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> New Order
                </a>
                <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Add Customer
                </a>
                <a href="<?= base_url('modules/payments/create.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-credit-card me-1"></i> Receive Payment
                </a>
                <?php if (has_permission('expenses.manage') || has_role('administrator') || has_role('manager')): ?>
                    <a href="<?= base_url('modules/expenses/create.php') ?>" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-wallet2 me-1"></i> Record Expense
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('modules/delivery/create.php') ?>" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-truck me-1"></i> Schedule Pickup/Delivery
                </a>
                <a href="<?= base_url('modules/operations/index.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i> Operations Board
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
