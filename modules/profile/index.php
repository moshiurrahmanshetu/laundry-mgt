<?php
/**
 * User Profile Management View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'My Profile';
$currentUser = current_user(true); // Always fetch fresh data

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<div class="row g-4">
    <!-- Left Column: Avatar & Account Summary -->
    <div class="col-12 col-lg-4">
        <!-- Avatar Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-camera me-2 text-primary"></i>Profile Picture</h2>
            </div>
            <div class="card-body text-center p-4">
                <div class="mb-3 d-inline-block position-relative">
                    <img src="<?= e(user_avatar_url($currentUser['avatar'] ?? null)) ?>" 
                         alt="<?= e($currentUser['name']) ?>" 
                         id="avatarPreviewImg"
                         class="avatar-preview-box">
                </div>
                <h3 class="h5 fw-bold text-dark mb-1"><?= e($currentUser['name']) ?></h3>
                <span class="badge badge-solid-<?= strtolower($currentUser['role_slug'] ?? 'administrator') ?> text-uppercase mb-3">
                    <?= e($currentUser['role_name'] ?? 'Administrator') ?>
                </span>

                <!-- Avatar Upload Form -->
                <form action="<?= base_url('modules/profile/upload_avatar.php') ?>" method="POST" enctype="multipart/form-data" class="mt-2">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="avatarInput" class="form-label small text-muted">Upload new image (JPG, PNG, WebP &bull; Max 2MB)</label>
                        <input type="file" 
                               class="form-control form-control-sm" 
                               id="avatarInput" 
                               name="avatar" 
                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" 
                               required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Update Avatar
                    </button>
                </form>
            </div>
        </div>

        <!-- Account Metadata Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Account Details</h2>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Account ID</span>
                        <span class="fw-semibold">#<?= (int)$currentUser['id'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Role</span>
                        <span class="fw-semibold"><?= e($currentUser['role_name']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Status</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Registered</span>
                        <span class="fw-semibold"><?= e(format_datetime($currentUser['created_at'], 'M d, Y')) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Last Active</span>
                        <span class="fw-semibold"><?= e(format_datetime($currentUser['last_login'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Profile Edit & Password Change -->
    <div class="col-12 col-lg-8">
        <!-- Personal Information Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-person-gear me-2 text-primary"></i>Personal Information</h2>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/profile/update.php') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-12 col-md-12">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?= e($currentUser['name']) ?>" 
                                       required 
                                       maxlength="100">
                            </div>
                        </div>

                        <!-- Email Address -->
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?= e($currentUser['email']) ?>" 
                                       required 
                                       maxlength="191">
                            </div>
                            <div class="form-text small">Used for authentication and system alerts.</div>
                        </div>

                        <!-- Phone Number -->
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?= e($currentUser['phone'] ?? '') ?>" 
                                       placeholder="+1 234 567 890" 
                                       maxlength="20">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security & Password Change Form -->
        <div class="card shadow-sm" id="change-password-section">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-shield-lock me-2 text-primary"></i>Security &amp; Change Password</h2>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/profile/change_password.php') ?>" method="POST" autocomplete="off">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <!-- Current Password -->
                        <div class="col-12">
                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="current_password" 
                                       name="current_password" 
                                       placeholder="Enter your current password" 
                                       required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="current_password" title="Toggle password view">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="col-12 col-md-6">
                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password" 
                                       placeholder="At least 8 characters" 
                                       minlength="8" 
                                       required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="new_password" title="Toggle password view">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text small">Must be at least 8 characters long.</div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="col-12 col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Re-enter new password" 
                                       minlength="8" 
                                       required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="confirm_password" title="Toggle password view">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="bi bi-shield-check me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
