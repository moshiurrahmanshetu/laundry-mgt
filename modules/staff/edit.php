<?php
/**
 * Edit Staff Member Form
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

$staffId = (int)($_GET['id'] ?? 0);
if ($staffId <= 0) {
    set_flash_message('error', 'Invalid staff ID provided.');
    redirect('modules/staff/index.php');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('
    SELECT u.*, r.name AS role_name, r.slug AS role_slug
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.id = :id AND u.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash_message('error', 'The requested staff account does not exist or has been deleted.');
    redirect('modules/staff/index.php');
}

$pageTitle = 'Edit: ' . $staff['name'];

// Fetch all active non-deleted roles (plus user's current role if inactive)
$rolesStmt = $pdo->prepare('
    SELECT id, name, slug 
    FROM roles 
    WHERE (status = "active" OR id = :current_role_id OR status IS NULL) AND deleted_at IS NULL 
    ORDER BY id ASC
');
$rolesStmt->execute(['current_role_id' => $staff['role_id']]);
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
            <li class="breadcrumb-item"><a href="<?= base_url('modules/staff/show.php?id=' . (int)$staff['id']) ?>"><?= e($staff['name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Edit Staff Account: <span class="text-primary"><?= e($staff['name']) ?></span></h2>
            <p class="text-muted small mb-0">Update account credentials, system role, and access status.</p>
        </div>
        <a href="<?= base_url('modules/staff/show.php?id=' . (int)$staff['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Cancel
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Account Details</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/staff/update.php') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$staff['id'] ?>">

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
                                   value="<?= e($staff['name']) ?>" 
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
                                   value="<?= e($staff['email']) ?>" 
                                   required>
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
                                   value="<?= e($staff['phone'] ?? '') ?>">
                        </div>

                        <!-- Role Selection -->
                        <div class="col-12 col-md-6">
                            <label for="role_id" class="form-label small fw-semibold text-dark">
                                System Role <span class="text-danger">*</span>
                            </label>
                            <select name="role_id" id="role_id" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === (int)$staff['role_id'] ? 'selected' : '' ?>>
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
                                <option value="active" <?= $staff['status'] === 'active' ? 'selected' : '' ?>>Active (Can Sign In)</option>
                                <option value="inactive" <?= $staff['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Access Suspended)</option>
                            </select>
                        </div>

                        <!-- Avatar Photo -->
                        <div class="col-12 col-md-6">
                            <label for="avatar" class="form-label small fw-semibold text-dark">
                                Update Avatar Photo <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <input type="file" 
                                   name="avatar" 
                                   id="avatar" 
                                   class="form-control" 
                                   accept="image/png, image/jpeg, image/webp">
                            <?php if (!empty($staff['avatar'])): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="removeAvatar">
                                    <label class="form-check-label small text-muted" for="removeAvatar">
                                        Remove current profile avatar
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-key me-2 text-primary"></i>Change Password (Optional)</h6>
                    <p class="text-muted small">Leave password fields blank to retain the current password.</p>

                    <div class="row g-3 mb-3">
                        <!-- Password -->
                        <div class="col-12 col-md-6">
                            <label for="password" class="form-label small fw-semibold text-dark">
                                New Password
                            </label>
                            <input type="password" 
                                   name="password" 
                                   id="password" 
                                   class="form-control" 
                                   minlength="6" 
                                   placeholder="Leave blank to keep current">
                        </div>

                        <!-- Confirm Password -->
                        <div class="col-12 col-md-6">
                            <label for="confirm_password" class="form-label small fw-semibold text-dark">
                                Confirm New Password
                            </label>
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   class="form-control" 
                                   minlength="6" 
                                   placeholder="Leave blank to keep current">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('modules/staff/show.php?id=' . (int)$staff['id']) ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Update Staff Account
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
