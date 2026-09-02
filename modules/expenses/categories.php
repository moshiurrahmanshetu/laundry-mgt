<?php
/**
 * Expense Category Management View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$pageTitle = 'Expense Categories';
$currentUser = current_user();
$pdo = getDBConnection();

// Fetch categories with active expense usage counts
$catSql = '
    SELECT ec.*, 
           (SELECT COUNT(*) FROM expenses WHERE category_id = ec.id AND deleted_at IS NULL) AS expenses_count,
           (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE category_id = ec.id AND deleted_at IS NULL) AS total_spent
    FROM expense_categories ec
    WHERE ec.deleted_at IS NULL
    ORDER BY ec.status ASC, ec.name ASC
';
$categories = $pdo->query($catSql)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/expenses/index.php') ?>">Expenses</a></li>
            <li class="breadcrumb-item active" aria-current="page">Categories</li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Expense Categories</h2>
            <p class="text-muted small mb-0">Organize and manage operating expense categories for tracking.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Expenses
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-lg me-1"></i> Add Category
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Categories List Table -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-folder2-open me-2 text-primary"></i>Category Directory</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= count($categories) ?> categories</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 50px;">#</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th class="text-center" style="width: 130px;">Active Expenses</th>
                                <th class="text-end" style="width: 150px;">Total Spent</th>
                                <th class="text-center" style="width: 120px;">Status</th>
                                <th class="text-end pe-3" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                                        <h5 class="fw-semibold mb-1">No expense categories found</h5>
                                        <p class="small mb-3">Add a new category to organize your operational expenses.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $idx => $cat): ?>
                                    <tr>
                                        <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                                        <td class="fw-semibold text-dark">
                                            <?= e($cat['name']) ?>
                                        </td>
                                        <td class="text-muted">
                                            <?= e($cat['description'] ?: '—') ?>
                                        </td>
                                        <td class="text-center font-monospace">
                                            <span class="badge bg-light text-dark border"><?= (int)$cat['expenses_count'] ?></span>
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-dark">
                                            <?= e(format_price($cat['total_spent'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <?= expense_category_status_badge($cat['status']) ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <!-- Edit Category Button -->
                                                <button type="button" 
                                                        class="btn btn-outline-secondary btn-edit-category"
                                                        data-id="<?= (int)$cat['id'] ?>"
                                                        data-name="<?= e($cat['name']) ?>"
                                                        data-description="<?= e($cat['description'] ?? '') ?>"
                                                        data-status="<?= e($cat['status']) ?>"
                                                        title="Edit Category"
                                                        data-bs-toggle="tooltip">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <!-- Delete Category Button -->
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-delete-category"
                                                        data-id="<?= (int)$cat['id'] ?>"
                                                        data-name="<?= e($cat['name']) ?>"
                                                        data-count="<?= (int)$cat['expenses_count'] ?>"
                                                        title="Delete Category"
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
        </div>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/expenses/category_store.php') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="addCategoryModalLabel">Add Expense Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="new_cat_name" class="form-label small fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="new_cat_name" class="form-control" placeholder="e.g. Electricity, Cleaning Supplies" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_cat_desc" class="form-label small fw-semibold text-dark">Description</label>
                        <textarea name="description" id="new_cat_desc" class="form-control" rows="2" placeholder="Brief explanation of expenses grouped under this category"></textarea>
                    </div>
                    <div>
                        <label for="new_cat_status" class="form-label small fw-semibold text-dark">Status</label>
                        <select name="status" id="new_cat_status" class="form-select">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/expenses/category_update.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="editCatId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="editCategoryModalLabel">Edit Expense Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="edit_cat_name" class="form-label small fw-semibold text-dark">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_cat_name" class="form-control" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_cat_desc" class="form-label small fw-semibold text-dark">Description</label>
                        <textarea name="description" id="edit_cat_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div>
                        <label for="edit_cat_status" class="form-label small fw-semibold text-dark">Status</label>
                        <select name="status" id="edit_cat_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Category -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/expenses/category_delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteCatId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold text-danger" id="deleteCategoryModalLabel">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-danger d-block mb-2"></i>
                    <p class="mb-2 small text-muted">Are you sure you want to delete category <strong id="deleteCatName" class="text-dark"></strong>?</p>
                    <div id="deleteCatWarning" class="alert alert-warning small py-2 px-3 text-start d-none">
                        <i class="bi bi-info-circle me-1"></i> This category is currently used by <strong id="deleteCatCount">0</strong> active expense(s). It cannot be deleted. You can deactivate it instead.
                    </div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnConfirmDeleteCat" class="btn btn-sm btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Edit Category Modal
    const editButtons = document.querySelectorAll(".btn-edit-category");
    const editModalEl = document.getElementById("editCategoryModal");
    const editId = document.getElementById("editCatId");
    const editName = document.getElementById("edit_cat_name");
    const editDesc = document.getElementById("edit_cat_desc");
    const editStatus = document.getElementById("edit_cat_status");

    if (editModalEl && typeof bootstrap !== "undefined") {
        const editModal = new bootstrap.Modal(editModalEl);
        editButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                editId.value = this.getAttribute("data-id");
                editName.value = this.getAttribute("data-name");
                editDesc.value = this.getAttribute("data-description");
                editStatus.value = this.getAttribute("data-status");
                editModal.show();
            });
        });
    }

    // Delete Category Modal
    const deleteButtons = document.querySelectorAll(".btn-delete-category");
    const deleteModalEl = document.getElementById("deleteCategoryModal");
    const deleteId = document.getElementById("deleteCatId");
    const deleteName = document.getElementById("deleteCatName");
    const deleteWarning = document.getElementById("deleteCatWarning");
    const deleteCount = document.getElementById("deleteCatCount");
    const btnConfirm = document.getElementById("btnConfirmDeleteCat");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteButtons.forEach(btn => {
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
