<?php
/**
 * Dashboard View (Phase 01 through Phase 06 Active)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Dashboard';
$currentUser = current_user(true); // Fetch fresh data
$pdo = getDBConnection();

// Fetch metrics
$totalCustomers = 0;
$activeCustomers = 0;
$totalServices = 0;
$activeServices = 0;
$totalOrders = 0;
$activeOrders = 0;
$readyOrders = 0;
$totalRevenue = 0.00;
$totalPaymentsCount = 0;
$pendingDeliveries = 0;

try {
    $custStmt = $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
    $totalCustomers = (int)$custStmt->fetchColumn();

    $activeCustStmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active' AND deleted_at IS NULL");
    $activeCustomers = (int)$activeCustStmt->fetchColumn();
} catch (PDOException $e) {}

try {
    $svcStmt = $pdo->query('SELECT COUNT(*) FROM services WHERE deleted_at IS NULL');
    $totalServices = (int)$svcStmt->fetchColumn();

    $activeSvcStmt = $pdo->query("SELECT COUNT(*) FROM services WHERE status = 'active' AND deleted_at IS NULL");
    $activeServices = (int)$activeSvcStmt->fetchColumn();
} catch (PDOException $e) {}

try {
    $ordStmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL');
    $totalOrders = (int)$ordStmt->fetchColumn();

    $activeOrdStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('received', 'processing') AND deleted_at IS NULL");
    $activeOrders = (int)$activeOrdStmt->fetchColumn();

    $readyOrdStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'ready' AND deleted_at IS NULL");
    $readyOrders = (int)$readyOrdStmt->fetchColumn();
} catch (PDOException $e) {}

try {
    // Authoritative Revenue from Payments Table
    $revStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND deleted_at IS NULL");
    $totalRevenue = (float)$revStmt->fetchColumn();

    $payCountStmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'completed' AND deleted_at IS NULL");
    $totalPaymentsCount = (int)$payCountStmt->fetchColumn();
} catch (PDOException $e) {}

try {
    // Phase 06 Active Pickup & Delivery requests
    $delStmt = $pdo->query("SELECT COUNT(*) FROM pickup_deliveries WHERE status IN ('pending', 'assigned', 'in_progress') AND deleted_at IS NULL");
    $pendingDeliveries = (int)$delStmt->fetchColumn();
} catch (PDOException $e) {}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 bg-white shadow-sm p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e(user_avatar_url($currentUser['avatar'] ?? null)) ?>" 
                         alt="Avatar" 
                         class="rounded-circle border" 
                         style="width: 64px; height: 64px; object-fit: cover;">
                    <div>
                        <h2 class="h4 fw-bold mb-1">Welcome, <?= e($currentUser['name']) ?>!</h2>
                        <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
                            <span><i class="bi bi-shield-check text-success me-1"></i>Role: <strong class="text-uppercase"><?= e($currentUser['role_name']) ?></strong></span>
                            <span>&bull;</span>
                            <span><i class="bi bi-clock me-1"></i>Last Login: <strong><?= e(format_datetime($currentUser['last_login'])) ?></strong></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= base_url('modules/delivery/create.php') ?>" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-truck me-1"></i> Dispatch Request
                    </a>
                    <a href="<?= base_url('modules/payments/create.php') ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-credit-card-fill me-1"></i> Receive Payment
                    </a>
                    <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> New Order
                    </a>
                    <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Add Customer
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Key Business Metrics Grid -->
<div class="row g-4 mb-4">
    <!-- Active Laundry Orders Metric Card -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="display-6 fw-bold text-dark mb-0"><?= $totalOrders ?></div>
                        <div class="text-muted small">Total Laundry Orders</div>
                    </div>
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle fs-4">
                        <i class="bi bi-basket3-fill"></i>
                    </div>
                </div>
                <div class="small text-muted d-flex justify-content-between">
                    <span><i class="bi bi-gear text-warning me-1"></i><strong><?= $activeOrders ?></strong> in wash/press</span>
                    <span><i class="bi bi-check2-circle text-success me-1"></i><strong><?= $readyOrders ?></strong> ready</span>
                </div>
            </div>
            <div class="card-footer bg-light py-2">
                <a href="<?= base_url('modules/orders/index.php') ?>" class="small text-decoration-none fw-semibold">
                    View Orders <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Collected Revenue Metric Card -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="display-6 fw-bold text-success mb-0"><?= format_price($totalRevenue) ?></div>
                        <div class="text-muted small">Collected Revenue</div>
                    </div>
                    <div class="p-3 bg-success-subtle text-success rounded-circle fs-4">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-receipt text-success me-1"></i><strong><?= $totalPaymentsCount ?></strong> payment transactions
                </div>
            </div>
            <div class="card-footer bg-light py-2">
                <a href="<?= base_url('modules/payments/index.php') ?>" class="small text-decoration-none fw-semibold text-success">
                    View Payments <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Active Pickups & Deliveries (Phase 06 Metric Card) -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="display-6 fw-bold text-dark mb-0"><?= $pendingDeliveries ?></div>
                        <div class="text-muted small">Active Schedules</div>
                    </div>
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle fs-4">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-clock-history text-warning me-1"></i><strong><?= $pendingDeliveries ?></strong> pickups/deliveries scheduled
                </div>
            </div>
            <div class="card-footer bg-light py-2">
                <a href="<?= base_url('modules/delivery/index.php') ?>" class="small text-decoration-none fw-semibold text-dark">
                    Dispatch Schedules <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Registered Customers Metric Card -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="display-6 fw-bold text-dark mb-0"><?= $totalCustomers ?></div>
                        <div class="text-muted small">Registered Customers</div>
                    </div>
                    <div class="p-3 bg-info-subtle text-info rounded-circle fs-4">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-check-circle text-success me-1"></i><strong><?= $activeCustomers ?></strong> active accounts
                </div>
            </div>
            <div class="card-footer bg-light py-2">
                <a href="<?= base_url('modules/customers/index.php') ?>" class="small text-decoration-none fw-semibold">
                    Manage Customers <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Laundry Management Development Roadmap -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2 text-primary"></i>Laundry Management System Roadmap</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Phases 01 through 06 are active in production mode:
                </p>
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 1 (Active)</span>
                            <div class="fw-bold text-dark small">Auth &amp; Foundation</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Users, Security</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 2 (Active)</span>
                            <div class="fw-bold text-dark small">Customers</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Profiles, Search</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 3 (Active)</span>
                            <div class="fw-bold text-dark small">Services &amp; Pricing</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Per Item / Per KG</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 4 (Active)</span>
                            <div class="fw-bold text-dark small">Order Intake</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Lifecycle, Orders</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 5 (Active)</span>
                            <div class="fw-bold text-dark small">Payments</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Multi-pay, Vouchers</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 6 (Active)</span>
                            <div class="fw-bold text-dark small">Pickup &amp; Delivery</div>
                            <small class="text-muted" style="font-size: 0.75rem;">Dispatch &amp; Slips</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
