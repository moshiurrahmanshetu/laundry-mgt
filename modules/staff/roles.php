<?php
/**
 * Role Management View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

$pageTitle = 'Role Management';
$currentUser = current_user();
$pdo = getDBConnection();

// Fetch roles with user count and permission count
$rolesSql = '
    SELECT r.*,
           (SELECT COUNT(*) FROM users WHERE role_id = r.id AND deleted_at IS NULL) AS users_count,
           (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) AS permissions_count
    FROM roles r
    WHERE r.deleted_at IS NULL
    ORDER BY r.id ASC
';
$roles = $pdo->query($rolesSql)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/staff/index.php') ?>">Staff &amp; Roles</a></li>
            <li class="breadcrumb-item active" aria-current="page">Roles &amp; Permissions</li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">System Roles &amp; Permissions</h2>
            <p class="text-muted small mb-0">Configure user access levels, authorization roles, and module permissions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/staff/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Staff List
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="bi bi-plus-lg me-1"></i> Add Custom Role
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Roles List Table -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>System Roles Directory</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= count($roles) ?> roles</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 50px;">#</th>
                                <th>Role Name</th>
                                <th>Slug Identifier</th>
                                <th>Description</th>
                                <th class="text-center" style="width: 120px;">Assigned Users</th>
                                <th class="text-center" style="width: 140px;">Permissions</th>
                                <th class="text-center" style="width: 110px;">Status</th>
                                <th class="text-end pe-3" style="width: 170px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $idx => $r): 
                                $isSystemRole = in_array($r['slug'], ['administrator', 'manager', 'staff'], true);
                            ?>
                                <tr>
                                    <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-6">
                                            <?= e($r['name']) ?>
                                            <?php if ($isSystemRole): ?>
                                                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem;">System</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="font-monospace text-muted">
                                        <?= e($r['slug']) ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= e($r['description'] ?: '—') ?>
                                    </td>
                                    <td class="text-center font-monospace">
                                        <span class="badge bg-light text-dark border"><?= (int)$r['users_count'] ?></span>
                                    </td>
                                    <td class="text-center font-monospace">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <?= (int)$r['permissions_count'] ?> permissions
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?= user_status_badge($r['status'] ?? 'active') ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Manage Permissions -->
                                            <a href="<?= base_url('modules/staff/role_permissions.php?id=' . (int)$r['id']) ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Configure Permissions"
                                               data-bs-toggle="tooltip">
                                                <i class="bi bi-shield-lock"></i>
                                            </a>

                                            <!-- Edit Role -->
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-edit-role"
                                                    data-id="<?= (int)$r['id'] ?>"
                                                    data-name="<?= e($r['name']) ?>"
                                                    data-slug="<?= e($r['slug']) ?>"
                                                    data-description="<?= e($r['description'] ?? '') ?>"
                                                    data-status="<?= e($r['status'] ?? 'active') ?>"
                                                    data-system="<?= $isSystemRole ? '1' : '0' ?>"
                                                    title="Edit Role"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <!-- Delete Role -->
                                            <?php if (!$isSystemRole): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-delete-role"
                                                        data-id="<?= (int)$r['id'] ?>"
                                                        data-name="<?= e($r['name']) ?>"
                                                        data-count="<?= (int)$r['users_count'] ?>"
                                                        title="Delete Role"
                                                        data-bs-toggle="tooltip">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Role -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/staff/role_store.php') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold">Add Custom Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="new_role_name" class="form-label small fw-semibold text-dark">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="new_role_name" class="form-control" placeholder="e.g. Quality Inspector, Cashier" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_role_desc" class="form-label small fw-semibold text-dark">Description</label>
                        <textarea name="description" id="new_role_desc" class="form-control" rows="2" placeholder="Responsibilities and scope of this role"></textarea>
                    </div>
                    <div>
                        <label for="new_role_status" class="form-label small fw-semibold text-dark">Status</label>
                        <select name="status" id="new_role_status" class="form-select">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Role -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/staff/role_update.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="editRoleId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="edit_role_name" class="form-label small fw-semibold text-dark">Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_role_name" class="form-control" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role_desc" class="form-label small fw-semibold text-dark">Description</label>
                        <textarea name="description" id="edit_role_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div id="editRoleStatusContainer">
                        <label for="edit_role_status" class="form-label small fw-semibold text-dark">Status</label>
                        <select name="status" id="edit_role_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Role -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/staff/role_delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteRoleId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold text-danger">Delete Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-danger d-block mb-2"></i>
                    <p class="mb-2 small text-muted">Are you sure you want to delete role <strong id="deleteRoleName" class="text-dark"></strong>?</p>
                    <div id="deleteRoleWarning" class="alert alert-warning small py-2 px-3 text-start d-none">
                        <i class="bi bi-info-circle me-1"></i> This role is currently assigned to <strong id="deleteRoleCount">0</strong> active user(s). It cannot be deleted until users are reassigned.
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnConfirmDeleteRole" class="btn btn-sm btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Edit Role
    const editBtns = document.querySelectorAll(".btn-edit-role");
    const editModalEl = document.getElementById("editRoleModal");
    const editId = document.getElementById("editRoleId");
    const editName = document.getElementById("edit_role_name");
    const editDesc = document.getElementById("edit_role_desc");
    const editStatus = document.getElementById("edit_role_status");
    const statusContainer = document.getElementById("editRoleStatusContainer");

    if (editModalEl && typeof bootstrap !== "undefined") {
        const editModal = new bootstrap.Modal(editModalEl);
        editBtns.forEach(btn => {
            btn.addEventListener("click", function () {
                const isSystem = this.getAttribute("data-system") === "1";
                editId.value = this.getAttribute("data-id");
                editName.value = this.getAttribute("data-name");
                editDesc.value = this.getAttribute("data-description");
                editStatus.value = this.getAttribute("data-status");

                if (isSystem && this.getAttribute("data-slug") === "administrator") {
                    statusContainer.classList.add("d-none");
                } else {
                    statusContainer.classList.remove("d-none");
                }

                editModal.show();
            });
        });
    }

    // Delete Role
    const deleteBtns = document.querySelectorAll(".btn-delete-role");
    const deleteModalEl = document.getElementById("deleteRoleModal");
    const deleteId = document.getElementById("deleteRoleId");
    const deleteName = document.getElementById("deleteRoleName");
    const deleteWarning = document.getElementById("deleteRoleWarning");
    const deleteCount = document.getElementById("deleteRoleCount");
    const btnConfirm = document.getElementById("btnConfirmDeleteRole");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteBtns.forEach(btn => {
            btn.addEventListener("click", function () {
                const count = parseInt(this.getAttribute("data-count") || 0, 10);
                deleteId.value = this.getAttribute("data-id");
                deleteName.textContent = this.getAttribute("data-name");
                deleteCount.textContent = count;

                if (count > 0) {
                    deleteWarning.classList.remove("d-none");
                    btnConfirm.setAttribute("disabled", "disabled");
                } else {
                    deleteWarning.classList.add("d-none");
                    btnConfirm.removeAttribute("disabled");
                }

                deleteModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
