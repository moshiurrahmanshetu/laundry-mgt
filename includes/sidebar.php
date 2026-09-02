<?php
/**
 * Common Sidebar Component (Collapsible Desktop & Mobile Drawer)
 * Project: Laundry Management System (laundry-mgt)
 */

$currentScript      = $_SERVER['SCRIPT_NAME'] ?? '';
$isDashboardActive  = str_contains($currentScript, 'dashboard');
$isOperationsActive = str_contains($currentScript, 'operations');
$isOrdersActive     = str_contains($currentScript, 'orders');
$isPaymentsActive   = str_contains($currentScript, 'payments');
$isExpensesActive   = str_contains($currentScript, 'expenses');
$isDeliveryActive   = str_contains($currentScript, 'delivery');
$isCustomersActive  = str_contains($currentScript, 'customers');
$isServicesActive   = str_contains($currentScript, 'services');
$isReportsActive    = str_contains($currentScript, 'reports');
$isProfileActive    = str_contains($currentScript, 'profile');
$isStaffActive      = str_contains($currentScript, 'staff');
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

        <!-- Laundry Operations (Active in Phase 07) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/operations/index.php') ?>" 
               class="nav-link <?= $isOperationsActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Laundry Operations">
                <i class="bi bi-arrow-repeat"></i>
                <span class="link-text">Operations</span>
            </a>
        </li>

        <li class="nav-header">Laundry Management</li>

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

        <!-- Payments (Active in Phase 05) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/payments/index.php') ?>" 
               class="nav-link <?= $isPaymentsActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Payments">
                <i class="bi bi-credit-card-fill"></i>
                <span class="link-text">Payments</span>
            </a>
        </li>

        <!-- Expenses (Active in Phase 09) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/expenses/index.php') ?>" 
               class="nav-link <?= $isExpensesActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Expense Management">
                <i class="bi bi-wallet2"></i>
                <span class="link-text">Expenses</span>
            </a>
        </li>

        <!-- Pickup & Delivery (Active in Phase 06) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/delivery/index.php') ?>" 
               class="nav-link <?= $isDeliveryActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Pickup & Delivery">
                <i class="bi bi-truck"></i>
                <span class="link-text">Pickup &amp; Delivery</span>
            </a>
        </li>

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

        <!-- Reports & Analytics (Active in Phase 08) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/reports/index.php') ?>" 
               class="nav-link <?= $isReportsActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Reports & Analytics">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span class="link-text">Reports</span>
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

        <!-- Staff & Roles (Active in Phase 10) -->
        <li class="nav-item">
            <a href="<?= base_url('modules/staff/index.php') ?>" 
               class="nav-link <?= $isStaffActive ? 'active' : '' ?>"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Staff & Roles">
                <i class="bi bi-shield-lock-fill"></i>
                <span class="link-text">Staff &amp; Roles</span>
            </a>
        </li>

        <!-- Settings (Phase 11) -->
        <li class="nav-item">
            <a href="javascript:void(0);" 
               class="nav-link disabled" 
               aria-disabled="true"
               data-bs-toggle="tooltip" 
               data-bs-placement="right" 
               data-bs-title="Settings (Phase 11)">
                <i class="bi bi-gear-fill"></i>
                <span class="link-text">Settings</span>
                <span class="badge bg-secondary">Phase 11</span>
            </a>
        </li>
    </ul>

    <!-- Sidebar User Footer -->
    <div class="sidebar-footer">
        <img src="<?= e(user_avatar_url($currentUser['avatar'] ?? null)) ?>" 
             alt="User Avatar" 
             class="navbar-user-avatar">
        <div class="user-details overflow-hidden">
            <div class="text-white text-truncate fw-semibold" style="font-size: 0.85rem;" title="<?= e($currentUser['name'] ?? 'User') ?>">
                <?= e($currentUser['name'] ?? 'User') ?>
            </div>
            <div class="text-muted-500 text-truncate text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;" title="<?= e($currentUser['role_name'] ?? 'Staff') ?>">
                <?= e($currentUser['role_name'] ?? 'Staff') ?>
            </div>
        </div>
    </div>
</nav>

<!-- Main Page Wrapper Start -->
<div id="main-wrapper">
