<?php
/**
 * Role Permissions Configuration Matrix View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

$roleId = (int)($_GET['id'] ?? 0);
if ($roleId <= 0) {
    set_flash_message('error', 'Invalid role ID provided.');
    redirect('modules/staff/roles.php');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('SELECT * FROM roles WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $roleId]);
$role = $stmt->fetch();

if (!$role) {
    set_flash_message('error', 'The requested role does not exist or has been deleted.');
    redirect('modules/staff/roles.php');
}

$pageTitle = 'Permissions: ' . $role['name'];

// Fetch all available permissions grouped by module
$permsStmt = $pdo->query('SELECT * FROM permissions ORDER BY module ASC, id ASC');
$allPermissions = $permsStmt->fetchAll();

$groupedPermissions = [];
foreach ($allPermissions as $p) {
    $groupedPermissions[$p['module']][] = $p;
}

// Fetch current assigned permission IDs for this role
$assignedStmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id');
$assignedStmt->execute(['role_id' => $roleId]);
$assignedPermissionIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$isAdministratorRole = ($role['slug'] === 'administrator');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/staff/index.php') ?>">Staff &amp; Roles</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/staff/roles.php') ?>">Roles</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($role['name']) ?> Permissions</li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Role Permissions: <span class="text-primary"><?= e($role['name']) ?></span></h2>
            <p class="text-muted small mb-0">Configure module-level granular access rights and operational capabilities.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/staff/roles.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Roles
            </a>
        </div>
    </div>
</div>

<?php if ($isAdministratorRole): ?>
    <div class="alert alert-info py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
        <div>
            <strong>Administrator Access:</strong> Accounts with the Administrator role always possess full, unrestricted system access across all modules by design.
        </div>
    </div>
<?php endif; ?>

<form action="<?= base_url('modules/staff/role_permissions_update.php') ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="role_id" value="<?= (int)$role['id'] ?>">

    <div class="row g-4 mb-4">
        <?php foreach ($groupedPermissions as $moduleName => $permissions): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                        <h4 class="h6 mb-0 fw-semibold text-dark"><i class="bi bi-folder2 me-1 text-primary"></i><?= e($moduleName) ?></h4>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small text-muted btn-select-module" data-target="module-<?= strtolower(preg_replace('/[^a-z0-9]+/i', '-', $moduleName)) ?>">
                            Toggle All
                        </button>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex flex-column gap-2 module-group" id="module-<?= strtolower(preg_replace('/[^a-z0-9]+/i', '-', $moduleName)) ?>">
                            <?php foreach ($permissions as $perm): 
                                $checked = in_array((int)$perm['id'], $assignedPermissionIds, true);
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input perm-checkbox" 
                                           type="checkbox" 
                                           name="permission_ids[]" 
                                           value="<?= (int)$perm['id'] ?>" 
                                           id="perm_<?= (int)$perm['id'] ?>"
                                           <?= $checked ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold text-dark" for="perm_<?= (int)$perm['id'] ?>">
                                        <?= e($perm['name']) ?>
                                    </label>
                                    <?php if (!empty($perm['description'])): ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?= e($perm['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Sticky Action Toolbar -->
    <div class="card shadow-sm border-0 position-sticky bottom-0 bg-white py-3 px-4 z-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
                <button type="button" id="btnSelectAll" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-check-all me-1"></i> Select All
                </button>
                <button type="button" id="btnDeselectAll" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle me-1"></i> Deselect All
                </button>
            </div>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-shield-check me-1"></i> Save Permissions
            </button>
        </div>
    </div>
</form>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Toggle module checkboxes
    document.querySelectorAll(".btn-select-module").forEach(btn => {
        btn.addEventListener("click", function () {
            const targetId = this.getAttribute("data-target");
            const container = document.getElementById(targetId);
            if (container) {
                const checkboxes = container.querySelectorAll(".perm-checkbox");
                const anyUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
                checkboxes.forEach(cb => { cb.checked = anyUnchecked; });
            }
        });
    });

    // Select All
    const btnAll = document.getElementById("btnSelectAll");
    if (btnAll) {
        btnAll.addEventListener("click", function () {
            document.querySelectorAll(".perm-checkbox").forEach(cb => { cb.checked = true; });
        });
    }

    // Deselect All
    const btnNone = document.getElementById("btnDeselectAll");
    if (btnNone) {
        btnNone.addEventListener("click", function () {
            document.querySelectorAll(".perm-checkbox").forEach(cb => { cb.checked = false; });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
