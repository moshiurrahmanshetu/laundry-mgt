<?php
/**
 * Customer Listing View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Customers';
$currentUser = current_user();
$pdo = getDBConnection();

// Search & Filter parameters
$search = sanitize_input($_GET['q'] ?? '');
$statusFilter = sanitize_input($_GET['status'] ?? '');
if (!in_array($statusFilter, ['active', 'inactive'], true)) {
    $statusFilter = ''; // All
}

// Pagination setup
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Build WHERE clauses
$whereConditions = ['deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(customer_code LIKE :search OR name LIKE :search OR phone LIKE :search OR email LIKE :search OR city LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $whereConditions[] = 'status = :status';
    $params['status'] = $statusFilter;
}

$whereSql = implode(' AND ', $whereConditions);

// Count Total Records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Adjust page if out of bounds
if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Paginated Customers
$listSql = "
    SELECT id, customer_code, name, phone, email, address, city, status, created_at
    FROM customers
    WHERE {$whereSql}
    ORDER BY id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header & Add Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Customers</h2>
        <p class="text-muted small mb-0">Manage laundry customers, contact details, and preferences.</p>
    </div>
    <div>
        <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add Customer
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/customers/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query Input -->
            <div class="col-12 col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Search by code, name, phone, email..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/customers/index.php' . (!empty($statusFilter) ? '?status=' . urlencode($statusFilter) : '')) ?>" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-md-3 col-lg-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Customers</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Customers</option>
                </select>
            </div>

            <!-- Submit & Reset Buttons -->
            <div class="col-6 col-md-3 col-lg-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Customer Data Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 130px;">Code</th>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>City</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 130px;">Created</th>
                        <th class="text-end pe-3" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="fw-semibold mb-1">No customers found</h5>
                                    <p class="small mb-3">
                                        <?= (!empty($search) || !empty($statusFilter)) ? 'Try adjusting your search criteria or filter options.' : 'Get started by creating your first laundry customer.' ?>
                                    </p>
                                    <?php if (empty($search) && empty($statusFilter)): ?>
                                        <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-lg me-1"></i> Add Customer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <!-- Code -->
                                <td class="ps-3 font-monospace fw-semibold text-primary">
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$customer['id']) ?>" class="text-decoration-none">
                                        <?= e($customer['customer_code']) ?>
                                    </a>
                                </td>

                                <!-- Name -->
                                <td>
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$customer['id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                        <?= e($customer['name']) ?>
                                    </a>
                                </td>

                                <!-- Phone -->
                                <td>
                                    <span class="text-dark font-monospace small">
                                        <i class="bi bi-telephone text-muted me-1"></i><?= e($customer['phone']) ?>
                                    </span>
                                </td>

                                <!-- Email -->
                                <td>
                                    <?php if (!empty($customer['email'])): ?>
                                        <span class="small text-muted">
                                            <i class="bi bi-envelope me-1"></i><?= e($customer['email']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>

                                <!-- City -->
                                <td>
                                    <span class="small text-muted"><?= e($customer['city'] ?: '&mdash;') ?></span>
                                </td>

                                <!-- Status Badge -->
                                <td>
                                    <?php if ($customer['status'] === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Created Date -->
                                <td>
                                    <span class="small text-muted"><?= e(format_datetime($customer['created_at'], 'M d, Y')) ?></span>
                                </td>

                                <!-- Actions -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View -->
                                        <a href="<?= base_url('modules/customers/show.php?id=' . (int)$customer['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Edit -->
                                        <a href="<?= base_url('modules/customers/edit.php?id=' . (int)$customer['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="Edit Customer"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Status Toggle -->
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-toggle-status" 
                                                data-id="<?= (int)$customer['id'] ?>"
                                                data-name="<?= e($customer['name']) ?>"
                                                data-current-status="<?= e($customer['status']) ?>"
                                                title="<?= $customer['status'] === 'active' ? 'Deactivate Customer' : 'Activate Customer' ?>"
                                                data-bs-toggle="tooltip">
                                            <i class="bi <?= $customer['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </button>

                                        <!-- Delete (Admin & Manager Only) -->
                                        <?php if (is_admin() || is_manager()): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-delete-customer" 
                                                    data-id="<?= (int)$customer['id'] ?>"
                                                    data-name="<?= e($customer['name']) ?>"
                                                    data-code="<?= e($customer['customer_code']) ?>"
                                                    title="Delete Customer"
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
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> customers
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Customer list pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Prev Link -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/customers/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
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
                                <a class="page-link" href="<?= base_url('modules/customers/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Link -->
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/customers/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
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
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-labelledby="toggleStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/customers/toggle_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="toggleStatusCustomerId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="toggleStatusModalLabel">Change Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="mb-2 text-dark" id="toggleStatusMessage">Are you sure you want to change the status of this customer?</p>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Soft Delete Confirmation (Admin/Manager Only) -->
<?php if (is_admin() || is_manager()): ?>
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-labelledby="deleteCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/customers/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteCustomerId" value="">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="deleteCustomerModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Customer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete customer <strong id="deleteCustomerName"></strong> (<span id="deleteCustomerCode" class="font-monospace"></span>)?</p>
                    <p class="small text-muted mb-0">This customer will be soft-deleted and removed from the active system.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Customer
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
    const toggleCustomerId = document.getElementById("toggleStatusCustomerId");
    const toggleStatusMessage = document.getElementById("toggleStatusMessage");

    if (toggleModalEl && typeof bootstrap !== "undefined") {
        const toggleModal = new bootstrap.Modal(toggleModalEl);
        toggleButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                const name = this.getAttribute("data-name");
                const currentStatus = this.getAttribute("data-current-status");
                const nextStatus = currentStatus === "active" ? "inactive" : "active";

                toggleCustomerId.value = id;
                toggleStatusMessage.innerHTML = `Change status of <strong>${name}</strong> to <span class="badge ${nextStatus === "active" ? "bg-success" : "bg-secondary"} text-uppercase">${nextStatus}</span>?`;
                toggleModal.show();
            });
        });
    }

    // Handle Delete Modal
    const deleteButtons = document.querySelectorAll(".btn-delete-customer");
    const deleteModalEl = document.getElementById("deleteCustomerModal");
    const deleteCustomerId = document.getElementById("deleteCustomerId");
    const deleteCustomerName = document.getElementById("deleteCustomerName");
    const deleteCustomerCode = document.getElementById("deleteCustomerCode");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                const name = this.getAttribute("data-name");
                const code = this.getAttribute("data-code");

                deleteCustomerId.value = id;
                deleteCustomerName.textContent = name;
                deleteCustomerCode.textContent = code;
                deleteModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
