<?php
/**
 * Common Sidebar Component (Collapsible Desktop & Mobile Drawer)
 * Project: Laundry Management System (laundry-mgt)
 */

$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$isDashboardActive = str_contains($currentScript, 'dashboard');
$isCustomersActive = str_contains($currentScript, 'customers');
$isServicesActive  = str_contains($currentScript, 'services');
$isOrdersActive    = str_contains($currentScript, 'orders');
$isProfileActive   = str_contains($currentScript, 'profile');
?>
<nav id="sidebar">
    <!-- Brand Logo -->
    <a href="<?= base_url('modules/dashboard/index.php') ?>" class="sidebar-brand">
        <i class="bi bi-droplet-half brand-icon"></i>
        <span class="brand-text"><?= e(APP_SHORT_NAME) ?></span>
    </a>

    <!-- Navigation Menu -->
    <ul class="sidebar-nav">
        <li class="nav-header">Main Navigation</li>

        <!-- Dashboard (Active in Phase 01) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/dashboard/index.php') ?>" 
               class="nav-link <?= $isDashboardActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Dashboard">
                <i class="bi bi-grid-1x2-fill"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>

        <li class="nav-header">Laundry Operations</li>

        <!-- Customers (Active in Phase 02) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/customers/index.php') ?>" 
               class="nav-link <?= $isCustomersActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Customers">
                <i class="bi bi-people-fill"></i>
                <span class="link-text">Customers</span>
            </a>
        </li>

        <!-- Laundry Services (Active in Phase 03) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/services/index.php') ?>" 
               class="nav-link <?= $isServicesActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Services">
                <i class="bi bi-tags-fill"></i>
                <span class="link-text">Services</span>
            </a>
        </li>

        <!-- Orders (Active in Phase 04) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/orders/index.php') ?>" 
               class="nav-link <?= $isOrdersActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Orders">
                <i class="bi bi-basket-fill"></i>
                <span class="link-text">Orders</span>
            </a>
        </li>

        <!-- Payments (Phase 05) -->
        <li class="nav-item">
            <a href="javascript:void(0);" 
               class="nav-link disabled" 
               aria-disabled="true"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Payments (Phase 05)">
                <i class="bi bi-credit-card-fill"></i>
                <span class="link-text">Payments</span>
                <span class="badge bg-secondary">Phase 5</span>
            </a>
        </li>

        <!-- Reports (Phase 06) -->
        <li class="nav-item">
            <a href="javascript:void(0);" 
               class="nav-link disabled" 
               aria-disabled="true"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Reports (Phase 06)">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span class="link-text">Reports</span>
                <span class="badge bg-secondary">Phase 6</span>
            </a>
        </li>

        <li class="nav-header">Administration</li>

        <!-- My Profile (Active in Phase 01) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/profile/index.php') ?>" 
               class="nav-link <?= $isProfileActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="My Profile">
                <i class="bi bi-person-circle"></i>
                <span class="link-text">My Profile</span>
            </a>
        </li>

        <!-- Staff & Roles (Phase 07) -->
        <li class="nav-item">
            <a href="javascript:void(0);" 
               class="nav-link disabled" 
               aria-disabled="true"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Staff & Roles (Phase 07)">
                <i class="bi bi-shield-lock-fill"></i>
                <span class="link-text">Staff &amp; Roles</span>
                <span class="badge bg-secondary">Phase 7</span>
            </a>
        </li>

        <!-- Settings (Phase 08) -->
        <li class="nav-item">
            <a href="javascript:void(0);" 
               class="nav-link disabled" 
               aria-disabled="true"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Settings (Phase 08)">
                <i class="bi bi-gear-fill"></i>
                <span class="link-text">Settings</span>
                <span class="badge bg-secondary">Phase 8</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar User Footer -->
    <div class="sidebar-footer">
        <img src="<?= e(user_avatar_url($currentUser['avatar'] ?? null)) ?>" 
             alt="User Avatar" 
             class="navbar-user-avatar me-2 flex-shrink-0">
        <div class="user-details overflow-hidden">
            <div class="text-white text-truncate fw-semibold" style="font-size: 0.85rem;">
                <?= e($currentUser['name'] ?? 'User') ?>
            </div>
            <div class="text-muted text-truncate text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                <?= e($currentUser['role_name'] ?? 'Staff') ?>
            </div>
        </div>
    </div>
</nav>

<!-- Main Page Wrapper Start -->
<div id="main-wrapper">
