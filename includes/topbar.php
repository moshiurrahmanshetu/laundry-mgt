<?php
/**
 * Topbar Navigation Component
 * Project: Laundry Management System (laundry-mgt)
 */
?>
<header id="top-navbar">
    <!-- Left: Sidebar Toggle & Page Title -->
    <div class="d-flex align-items-center">
        <button id="sidebarToggle" class="sidebar-toggle-btn" title="Toggle Sidebar" aria-label="Toggle Sidebar">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="page-heading-title fs-5 mb-0"><?= e($pageTitle ?? 'Dashboard') ?></h1>
    </div>

    <!-- Right: User Profile Menu -->
    <div class="ms-auto d-flex align-items-center">
        <div class="dropdown">
            <button class="navbar-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?= e(user_avatar_url($currentUser['avatar'] ?? null)) ?>" 
                     alt="Avatar" 
                     class="navbar-user-avatar">
                <div class="d-none d-md-block text-start">
                    <span class="d-block fw-semibold text-dark text-truncate" style="max-width: 150px; font-size: 0.875rem;">
                        <?= e($currentUser['name'] ?? 'User') ?>
                    </span>
                    <span class="badge badge-solid-<?= strtolower($currentUser['role_slug'] ?? 'administrator') ?> text-uppercase" style="font-size: 0.65rem;">
                        <?= e($currentUser['role_name'] ?? 'Administrator') ?>
                    </span>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2">
                <li class="px-3 py-2 border-bottom d-md-none">
                    <div class="fw-semibold text-dark"><?= e($currentUser['name'] ?? 'User') ?></div>
                    <div class="small text-muted"><?= e($currentUser['email'] ?? '') ?></div>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= base_url('modules/profile/index.php') ?>">
                        <i class="bi bi-person-gear me-2 text-muted"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= base_url('modules/profile/index.php#change-password-section') ?>">
                        <i class="bi bi-shield-lock me-2 text-muted"></i> Change Password
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= base_url('auth/logout.php') ?>">
                        <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Content Body Start -->
<main class="content-body">
    <?= render_flash_messages() ?>
