<?php
/**
 * Create Staff Member Form
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

$pageTitle = 'Add Staff Member';
$currentUser = current_user();
$pdo = getDBConnection();

// Fetch active non-deleted roles
$rolesStmt = $pdo->query("SELECT id, name, slug, description FROM roles WHERE (status = 'active' OR status IS NULL) AND deleted_at IS NULL ORDER BY id ASC");
$roles = $rolesStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/staff/index.php') ?>">Staff &amp; Roles</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add Staff Member</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Add New Staff Member</h2>
            <p class="text-muted small mb-0">Create a user account and assign system access permissions.</p>
        </div>
        <a href="<?= base_url('modules/staff/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Staff List
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person-plus me-2 text-primary"></i>Account Information</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/staff/store.php') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <!-- Full Name -->
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label small fw-semibold text-dark">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   class="form-control" 
                                   placeholder="e.g. John Doe" 
                                   required>
                        </div>

                        <!-- Email -->
                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label small fw-semibold text-dark">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   class="form-control" 
                                   placeholder="e.g. john@laundrymgt.com" 
                                   required>
                            <div class="form-text">Used as the primary login credential.</div>
                        </div>

                        <!-- Phone -->
                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label small fw-semibold text-dark">
                                Phone Number <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <input type="text" 
                                   name="phone" 
                                   id="phone" 
                                   class="form-control" 
                                   placeholder="e.g. +1 555-0199">
                        </div>

                        <!-- Role Selection -->
                        <div class="col-12 col-md-6">
                            <label for="role_id" class="form-label small fw-semibold text-dark">
                                Assign System Role <span class="text-danger">*</span>
                            </label>
                            <select name="role_id" id="role_id" class="form-select" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= $r['slug'] === 'staff' ? 'selected' : '' ?>>
                                        <?= e($r['name']) ?> (<?= e($r['slug']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Controls system permissions and authorization access.</div>
                        </div>

                        <!-- Status -->
                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label small fw-semibold text-dark">
                                Account Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" selected>Active (Can Sign In)</option>
                                <option value="inactive">Inactive (Access Suspended)</option>
                            </select>
                        </div>

                        <!-- Avatar Photo -->
                        <div class="col-12 col-md-6">
                            <label for="avatar" class="form-label small fw-semibold text-dark">
                                Profile Photo / Avatar <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <input type="file" 
                                   name="avatar" 
                                   id="avatar" 
                                   class="form-control" 
                                   accept="image/png, image/jpeg, image/webp">
                            <div class="form-text">JPG, PNG, or WebP up to 2MB.</div>
                        </div>

                        <!-- Password -->
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label small fw-semibold text-dark">
                                Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   class="form-control" 
                                   minlength="6" 
                                   placeholder="Minimum 6 characters" 
                                   required>
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-12 col-md-6">
                            <label for="confirm_password" class="form-label small fw-semibold text-dark">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   class="form-control" 
                                   minlength="6" 
                                   placeholder="Re-enter password" 
                                   required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('modules/staff/index.php') ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Create Staff Account
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
