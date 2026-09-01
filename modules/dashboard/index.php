<?php
/**
 * Dashboard View (Phase 01 & Phase 02)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Dashboard';
$currentUser = current_user(true); // Fetch fresh data
$pdo = getDBConnection();

// Fetch quick counts
$totalCustomers = 0;
$activeCustomers = 0;
try {
    $custStmt = $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
    $totalCustomers = (int)$custStmt->fetchColumn();

    $activeCustStmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active' AND deleted_at IS NULL");
    $activeCustomers = (int)$activeCustStmt->fetchColumn();
} catch (PDOException $e) {
    // Table may not be migrated yet in testing
}

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
                <div class="d-flex gap-2">
                    <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-plus me-1"></i> Add Customer
                    </a>
                    <a href="<?= base_url('modules/profile/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-person-gear me-1"></i> Manage Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Info Cards Grid -->
<div class="row g-4 mb-4">
    <!-- Customer Management Metric Card -->
    <div class="col-12 col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-people text-primary me-2"></i>Customers (Phase 02)</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Module Active</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="display-6 fw-bold text-dark mb-0"><?= $totalCustomers ?></div>
                        <div class="text-muted small">Total Registered Customers</div>
                    </div>
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle fs-3">
                        <i class="bi bi-person-lines-fill"></i>
                    </div>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-check-circle text-success me-1"></i><strong><?= $activeCustomers ?></strong> active accounts
                </div>
            </div>
            <div class="card-footer bg-light">
                <a href="<?= base_url('modules/customers/index.php') ?>" class="small text-decoration-none fw-semibold">
                    Manage Customers <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Security & Auth Status Card -->
    <div class="col-12 col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-shield-lock text-primary me-2"></i>Security Baseline</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Secure</span>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small">
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Role Authorization</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i><?= e($currentUser['role_name']) ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">CSRF Protection</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Tokens Active</span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Audit Trail</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Activity Logged</span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted">Soft Delete System</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Safe Deletes</span>
                    </li>
                </ul>
            </div>
            <div class="card-footer bg-light">
                <a href="<?= base_url('modules/profile/index.php#change-password-section') ?>" class="small text-decoration-none fw-semibold">
                    Change Password <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- System Status & Environment -->
    <div class="col-12 col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-cpu text-primary me-2"></i>System Status</span>
                <span class="badge bg-info-subtle text-info border border-info-subtle">Phase 02</span>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small">
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Application</span>
                        <span class="fw-semibold text-dark"><?= e(APP_SHORT_NAME) ?> v<?= e(APP_VERSION) ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">PHP Engine</span>
                        <span class="fw-semibold text-dark">PHP <?= phpversion() ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Database</span>
                        <span class="fw-semibold text-dark">MySQL (<?= e(DB_NAME) ?>)</span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted">Environment</span>
                        <span class="badge bg-secondary text-uppercase"><?= e(APP_ENV) ?></span>
                    </li>
                </ul>
            </div>
            <div class="card-footer bg-light">
                <span class="small text-muted">Ready for Phase 03</span>
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
                    Phase 01 (Authentication) and Phase 02 (Customer Management) are currently active. Core laundry operation modules will be enabled in subsequent phases:
                </p>
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 1 (Active)</span>
                            <div class="fw-bold text-dark">Auth &amp; Foundation</div>
                            <small class="text-muted">Roles, Users, Security</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded bg-light">
                            <span class="badge bg-success mb-2">Phase 2 (Active)</span>
                            <div class="fw-bold text-dark">Customer Management</div>
                            <small class="text-muted">Profiles, Search, Filters</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded">
                            <span class="badge bg-secondary mb-2">Phase 3</span>
                            <div class="fw-bold text-dark">Services &amp; Pricing</div>
                            <small class="text-muted">Dry Cleaning, Wash &amp; Fold</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 border rounded">
                            <span class="badge bg-secondary mb-2">Phase 4</span>
                            <div class="fw-bold text-dark">Laundry Orders</div>
                            <small class="text-muted">Intake, Status, Pickup</small>
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
