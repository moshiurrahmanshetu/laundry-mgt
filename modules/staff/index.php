<?php
/**
 * Staff Management Dashboard & Directory View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

$pageTitle = 'Staff Management';
$currentUser = current_user();
$pdo = getDBConnection();

// Query Parameters
$search   = sanitize_input($_GET['q'] ?? '');
$roleId   = (int)($_GET['role_id'] ?? 0);
$status   = sanitize_input($_GET['status'] ?? '');

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = '';
}

// 1. Dynamic Metric Counters
$totalStaff   = 0;
$activeStaff  = 0;
$adminCount   = 0;
$managerCount = 0;

try {
    $sTotal = $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
    $totalStaff = (int)$sTotal->fetchColumn();

    $sActive = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL");
    $activeStaff = (int)$sActive->fetchColumn();

    $sAdmin = $pdo->query("
        SELECT COUNT(*) 
        FROM users u 
        INNER JOIN roles r ON u.role_id = r.id 
        WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $adminCount = (int)$sAdmin->fetchColumn();

    $sManager = $pdo->query("
        SELECT COUNT(*) 
        FROM users u 
        INNER JOIN roles r ON u.role_id = r.id 
        WHERE r.slug = 'manager' AND u.status = 'active' AND u.deleted_at IS NULL
    ");
    $managerCount = (int)$sManager->fetchColumn();
} catch (PDOException $e) {}

// 2. Fetch Active Roles for Filter
$rolesStmt = $pdo->query("SELECT id, name, slug FROM roles WHERE deleted_at IS NULL ORDER BY id ASC");
$filterRoles = $rolesStmt->fetchAll();

// 3. Query Construction with Filters
$whereConditions = ['u.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($roleId > 0) {
    $whereConditions[] = 'u.role_id = :role_id';
    $params['role_id'] = $roleId;
}

if (!empty($status)) {
    $whereConditions[] = 'u.status = :status';
    $params['status'] = $status;
}

$whereSql = implode(' AND ', $whereConditions);

// Pagination
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Staff List
$listSql = "
    SELECT u.*, r.name AS role_name, r.slug AS role_slug
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE {$whereSql}
    ORDER BY u.id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$staffMembers = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Staff &amp; Roles Management</h2>
        <p class="text-muted small mb-0">Manage user accounts, system roles, and authorization access.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('modules/staff/roles.php') ?>" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-shield-lock me-1"></i> Manage Roles &amp; Permissions
        </a>
        <a href="<?= base_url('modules/staff/create.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Add Staff Member
        </a>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Staff</div>
                <div class="h3 fw-bold text-dark mb-0"><?= $totalStaff ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">Registered accounts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Active Accounts</div>
                <div class="h3 fw-bold text-success mb-0"><?= $activeStaff ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">Able to sign in</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Administrators</div>
                <div class="h3 fw-bold text-dark mb-0"><?= $adminCount ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">Full system access</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Managers</div>
                <div class="h3 fw-bold text-primary mb-0"><?= $managerCount ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">Operational oversight</div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/staff/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query -->
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Search by name, email, phone..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/staff/index.php') ?>" class="btn btn-outline-secondary" title="Clear">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Role Filter -->
            <div class="col-6 col-md-3">
                <select name="role_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <?php foreach ($filterRoles as $fr): ?>
                        <option value="<?= (int)$fr['id'] ?>" <?= $roleId === (int)$fr['id'] ? 'selected' : '' ?>>
                            <?= e($fr['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-md-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="col-12 col-md-auto d-flex gap-2 ms-auto">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || $roleId > 0 || !empty($status)): ?>
                    <a href="<?= base_url('modules/staff/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Staff Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-people-fill me-2 text-primary"></i>Staff Members Directory</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= $totalRecords ?> user(s) found</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">#</th>
                        <th>User</th>
                        <th>Email &amp; Phone</th>
                        <th>Assigned Role</th>
                        <th class="text-center" style="width: 120px;">Status</th>
                        <th>Last Login</th>
                        <th>Created Date</th>
                        <th class="text-end pe-3" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffMembers)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No staff members found</h5>
                                <p class="small mb-3">Add a new user account to grant access to staff.</p>
                                <a href="<?= base_url('modules/staff/create.php') ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-person-plus-fill me-1"></i> Add Staff Member
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $idx => $st): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $offset + $idx + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= e(user_avatar_url($st['avatar'])) ?>" 
                                             alt="Avatar" 
                                             class="navbar-user-avatar border" 
                                             style="width: 34px; height: 34px;">
                                        <div>
                                            <a href="<?= base_url('modules/staff/show.php?id=' . (int)$st['id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                                <?= e($st['name']) ?>
                                            </a>
                                            <?php if ((int)$st['id'] === (int)$currentUser['id']): ?>
                                                <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem;">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark"><?= e($st['email']) ?></div>
                                    <div class="text-muted small"><?= e($st['phone'] ?: '—') ?></div>
                                </td>
                                <td>
                                    <?= role_badge($st['role_slug'], $st['role_name']) ?>
                                </td>
                                <td class="text-center">
                                    <?= user_status_badge($st['status']) ?>
                                </td>
                                <td class="text-muted">
                                    <?= $st['last_login'] ? e(format_datetime($st['last_login'])) : '<span class="text-muted fst-italic">Never</span>' ?>
                                </td>
                                <td class="text-muted font-monospace">
                                    <?= e(format_datetime($st['created_at'], 'M d, Y')) ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/staff/show.php?id=' . (int)$st['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= base_url('modules/staff/edit.php?id=' . (int)$st['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="Edit User"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Toggle Status Button -->
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-toggle-status"
                                                data-id="<?= (int)$st['id'] ?>"
                                                data-name="<?= e($st['name']) ?>"
                                                data-current-status="<?= e($st['status']) ?>"
                                                title="<?= $st['status'] === 'active' ? 'Deactivate User' : 'Activate User' ?>"
                                                data-bs-toggle="tooltip">
                                            <i class="bi <?= $st['status'] === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' ?>"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-delete-staff"
                                                data-id="<?= (int)$st['id'] ?>"
                                                data-name="<?= e($st['name']) ?>"
                                                title="Delete Staff"
                                                data-bs-toggle="tooltip">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalRecords > 0): ?>
        <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-muted small">
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> users
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Staff pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/staff/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/staff/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/staff/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Toggle Status -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/staff/toggle_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="toggleStatusUserId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="toggleStatusModalTitle">Change Account Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-question-circle fs-1 text-primary d-block mb-2"></i>
                    <p class="mb-1 small text-muted">Are you sure you want to change status for user <strong id="toggleStatusUserName" class="text-dark"></strong>?</p>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/staff/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteStaffId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold text-danger">Delete User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-danger d-block mb-2"></i>
                    <p class="mb-1 small text-muted">Are you sure you want to delete user <strong id="deleteStaffName" class="text-dark"></strong>?</p>
                    <small class="text-muted">This account will be soft-deleted. Audit logs and references will be preserved.</small>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Status Toggle Modal
    const toggleBtns = document.querySelectorAll(".btn-toggle-status");
    const toggleModalEl = document.getElementById("toggleStatusModal");
    const toggleUserId = document.getElementById("toggleStatusUserId");
    const toggleUserName = document.getElementById("toggleStatusUserName");

    if (toggleModalEl && typeof bootstrap !== "undefined") {
        const toggleModal = new bootstrap.Modal(toggleModalEl);
        toggleBtns.forEach(btn => {
            btn.addEventListener("click", function () {
                toggleUserId.value = this.getAttribute("data-id");
                toggleUserName.textContent = this.getAttribute("data-name");
                toggleModal.show();
            });
        });
    }

    // Delete Modal
    const deleteBtns = document.querySelectorAll(".btn-delete-staff");
    const deleteModalEl = document.getElementById("deleteStaffModal");
    const deleteId = document.getElementById("deleteStaffId");
    const deleteName = document.getElementById("deleteStaffName");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteBtns.forEach(btn => {
            btn.addEventListener("click", function () {
                deleteId.value = this.getAttribute("data-id");
                deleteName.textContent = this.getAttribute("data-name");
                deleteModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
