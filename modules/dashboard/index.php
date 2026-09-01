<?php
/**
 * Dashboard View (Phase 01)
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Dashboard';
$currentUser = current_user(true); // Fetch fresh data

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
                <div>
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
    <!-- Account Information Card -->
    <div class="col-12 col-md-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <span class="fw-semibold"><i class="bi bi-person-badge text-primary me-2"></i>Account Profile</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small">
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Full Name</span>
                        <span class="fw-semibold text-dark"><?= e($currentUser['name']) ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Email</span>
                        <span class="fw-semibold text-dark"><?= e($currentUser['email']) ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Phone</span>
                        <span class="fw-semibold text-dark"><?= e($currentUser['phone'] ?: 'Not set') ?></span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted">System Role</span>
                        <span class="badge badge-solid-<?= strtolower($currentUser['role_slug'] ?? 'administrator') ?>"><?= e($currentUser['role_name']) ?></span>
                    </li>
                </ul>
            </div>
            <div class="card-footer bg-light">
                <a href="<?= base_url('modules/profile/index.php') ?>" class="small text-decoration-none fw-semibold">
                    Update Details <i class="bi bi-arrow-right ms-1"></i>
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
                        <span class="text-muted">Password Encryption</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>BCRYPT Hash</span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">CSRF Protection</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Token Verified</span>
                    </li>
                    <li class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Session Isolation</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>HTTPOnly / Strict</span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted">Audit Logging</span>
                        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-1"></i>Activity Logged</span>
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
                <span class="badge bg-info-subtle text-info border border-info-subtle">Phase 01</span>
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
                        <span class="text-muted">Database Engine</span>
                        <span class="fw-semibold text-dark">MySQL (<?= e(DB_NAME) ?>)</span>
                    </li>
                    <li class="d-flex justify-content-between py-1">
                        <span class="text-muted">Environment</span>
                        <span class="badge bg-secondary text-uppercase"><?= e(APP_ENV) ?></span>
                    </li>
                </ul>
            </div>
            <div class="card-footer bg-light">
                <span class="small text-muted">Ready for subsequent phases</span>
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
                    Phase 01 (Foundation &amp; Authentication System) is active. Core laundry operation modules will be enabled in subsequent phases:
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
                        <div class="p-3 border rounded">
                            <span class="badge bg-secondary mb-2">Phase 2</span>
                            <div class="fw-bold text-dark">Customer Management</div>
                            <small class="text-muted">Profiles, Contact, History</small>
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
