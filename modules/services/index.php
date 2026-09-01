<?php
/**
 * Laundry Services Listing View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Laundry Services';
$currentUser = current_user();
$pdo = getDBConnection();

$canManage = is_admin() || is_manager();

// Search & Filter parameters
$search = sanitize_input($_GET['q'] ?? '');
$statusFilter = sanitize_input($_GET['status'] ?? '');
if (!in_array($statusFilter, ['active', 'inactive'], true)) {
    $statusFilter = ''; // All
}

// Staff can only view active services by default if not managing
if (!empty($statusFilter)) {
    $currentStatusFilter = $statusFilter;
} else {
    $currentStatusFilter = '';
}

// Pagination setup
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Build WHERE clauses
$whereConditions = ['s.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(s.name LIKE :search OR s.description LIKE :search OR s.slug LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($currentStatusFilter)) {
    $whereConditions[] = 's.status = :status';
    $params['status'] = $currentStatusFilter;
}

$whereSql = implode(' AND ', $whereConditions);

// Count Total Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM services s WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Adjust page if out of bounds
if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Paginated Services with Item Counts
$listSql = "
    SELECT s.id, s.name, s.slug, s.description, s.pricing_type, s.status, s.created_at,
           COUNT(si.id) AS total_items
    FROM services s
    LEFT JOIN service_items si ON s.id = si.service_id AND si.deleted_at IS NULL
    WHERE {$whereSql}
    GROUP BY s.id
    ORDER BY s.id ASC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$services = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header & Add Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Laundry Services</h2>
        <p class="text-muted small mb-0">Manage laundry service categories, pricing models, and itemized garment rates.</p>
    </div>
    <?php if ($canManage): ?>
        <div>
            <a href="<?= base_url('modules/services/create.php') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Add Service
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Search & Filter Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/services/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query Input -->
            <div class="col-12 col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Search service name, description..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/services/index.php' . (!empty($currentStatusFilter) ? '?status=' . urlencode($currentStatusFilter) : '')) ?>" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-md-3 col-lg-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $currentStatusFilter === 'active' ? 'selected' : '' ?>>Active Services</option>
                    <option value="inactive" <?= $currentStatusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Services</option>
                </select>
            </div>

            <!-- Submit & Reset Buttons -->
            <div class="col-6 col-md-3 col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($currentStatusFilter)): ?>
                    <a href="<?= base_url('modules/services/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Services Data Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Service Name</th>
                        <th>Pricing Model</th>
                        <th>Itemized Rates</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 140px;">Created</th>
                        <th class="text-end pe-3" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-tags fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="fw-semibold mb-1">No laundry services found</h5>
                                    <p class="small mb-3">
                                        <?= (!empty($search) || !empty($currentStatusFilter)) ? 'Try adjusting your search query or filter options.' : 'Get started by creating your first laundry service.' ?>
                                    </p>
                                    <?php if ($canManage && empty($search) && empty($currentStatusFilter)): ?>
                                        <a href="<?= base_url('modules/services/create.php') ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-lg me-1"></i> Add Service
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <!-- Service Name -->
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="p-2 bg-primary-subtle text-primary rounded me-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi <?= $service['pricing_type'] === 'per_kg' ? 'bi-speedometer' : 'bi-tag' ?> fs-5"></i>
                                        </div>
                                        <div>
                                            <a href="<?= base_url('modules/services/show.php?id=' . (int)$service['id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                                <?= e($service['name']) ?>
                                            </a>
                                            <div class="font-monospace text-muted small" style="font-size: 0.75rem;">
                                                slug: <?= e($service['slug']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Pricing Model -->
                                <td>
                                    <?php if ($service['pricing_type'] === 'per_kg'): ?>
                                        <span class="badge bg-primary text-white">
                                            <i class="bi bi-speedometer me-1"></i>Per KG Weight
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-dark text-white">
                                            <i class="bi bi-grid me-1"></i>Per Item / Garment
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Number of Items -->
                                <td>
                                    <?php if ($service['pricing_type'] === 'per_kg'): ?>
                                        <span class="small text-muted">Base weight rate</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">
                                            <?= (int)$service['total_items'] ?> <?= (int)$service['total_items'] === 1 ? 'item' : 'items' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td>
                                    <?php if ($service['status'] === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Created Date -->
                                <td>
                                    <span class="small text-muted"><?= e(format_datetime($service['created_at'], 'M d, Y')) ?></span>
                                </td>

                                <!-- Actions -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View -->
                                        <a href="<?= base_url('modules/services/show.php?id=' . (int)$service['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <?php if ($canManage): ?>
                                            <!-- Edit -->
                                            <a href="<?= base_url('modules/services/edit.php?id=' . (int)$service['id']) ?>" 
                                               class="btn btn-outline-secondary" 
                                               title="Edit Service &amp; Rates"
                                               data-bs-toggle="tooltip">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <!-- Status Toggle -->
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-toggle-status" 
                                                    data-id="<?= (int)$service['id'] ?>"
                                                    data-name="<?= e($service['name']) ?>"
                                                    data-current-status="<?= e($service['status']) ?>"
                                                    title="<?= $service['status'] === 'active' ? 'Deactivate Service' : 'Activate Service' ?>"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi <?= $service['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                            </button>

                                            <!-- Delete -->
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-delete-service" 
                                                    data-id="<?= (int)$service['id'] ?>"
                                                    data-name="<?= e($service['name']) ?>"
                                                    title="Delete Service"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination & Summary Footer -->
    <?php if ($totalRecords > 0): ?>
        <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-muted small">
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> services
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Services list pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Prev Link -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/services/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/services/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Link -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/services/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Toggle Status Confirmation -->
<?php if ($canManage): ?>
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-labelledby="toggleStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/services/toggle_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="toggleStatusServiceId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="toggleStatusModalLabel">Change Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="mb-2 text-dark" id="toggleStatusMessage">Are you sure you want to change the status of this service?</p>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Soft Delete Confirmation (Admin & Manager Only) -->
<div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-labelledby="deleteServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/services/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteServiceId" value="">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="deleteServiceModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Laundry Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete service <strong id="deleteServiceName"></strong>?</p>
                    <p class="small text-muted mb-0">This service and its associated item rates will be soft-deleted from the active catalog.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Handle Toggle Status Modal
    const toggleButtons = document.querySelectorAll(".btn-toggle-status");
    const toggleModalEl = document.getElementById("toggleStatusModal");
    const toggleServiceId = document.getElementById("toggleStatusServiceId");
    const toggleStatusMessage = document.getElementById("toggleStatusMessage");

    if (toggleModalEl && typeof bootstrap !== "undefined") {
        const toggleModal = new bootstrap.Modal(toggleModalEl);
        toggleButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                const name = this.getAttribute("data-name");
                const currentStatus = this.getAttribute("data-current-status");
                const nextStatus = currentStatus === "active" ? "inactive" : "active";

                toggleServiceId.value = id;
                toggleStatusMessage.innerHTML = `Change status of <strong>${name}</strong> to <span class="badge ${nextStatus === "active" ? "bg-success" : "bg-secondary"} text-uppercase">${nextStatus}</span>?`;
                toggleModal.show();
            });
        });
    }

    // Handle Delete Modal
    const deleteButtons = document.querySelectorAll(".btn-delete-service");
    const deleteModalEl = document.getElementById("deleteServiceModal");
    const deleteServiceId = document.getElementById("deleteServiceId");
    const deleteServiceName = document.getElementById("deleteServiceName");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                const name = this.getAttribute("data-name");

                deleteServiceId.value = id;
                deleteServiceName.textContent = name;
                deleteModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
