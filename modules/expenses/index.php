<?php
/**
 * Expense Management Dashboard & Listings View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$pageTitle = 'Expense Management';
$currentUser = current_user();
$pdo = getDBConnection();

// Query Parameters
$search        = sanitize_input($_GET['q'] ?? '');
$categoryId    = (int)($_GET['category_id'] ?? 0);
$paymentMethod = sanitize_input($_GET['payment_method'] ?? '');
$startDate     = sanitize_input($_GET['start_date'] ?? '');
$endDate       = sanitize_input($_GET['end_date'] ?? '');

$validMethods = ['cash', 'card', 'mobile_banking', 'bank_transfer', 'other'];
if (!in_array($paymentMethod, $validMethods, true)) {
    $paymentMethod = '';
}

// 1. Dynamic Summary Metrics (Based strictly on expense_date)
$todayExpenses = 0.00;
$monthExpenses = 0.00;
$yearExpenses  = 0.00;
$totalExpenses = 0.00;

try {
    // Today
    $sToday = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURDATE() AND deleted_at IS NULL");
    $todayExpenses = (float)$sToday->fetchColumn();

    // This Month
    $sMonth = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01') AND LAST_DAY(NOW()) AND deleted_at IS NULL");
    $monthExpenses = (float)$sMonth->fetchColumn();

    // This Year
    $sYear = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) AND deleted_at IS NULL");
    $yearExpenses = (float)$sYear->fetchColumn();

    // All-time Total
    $sTotal = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE deleted_at IS NULL");
    $totalExpenses = (float)$sTotal->fetchColumn();
} catch (PDOException $e) {}

// 2. Fetch Active Categories for Filter Dropdown
$catStmt = $pdo->query("SELECT id, name FROM expense_categories WHERE deleted_at IS NULL ORDER BY name ASC");
$filterCategories = $catStmt->fetchAll();

// 3. Query Construction with Filters
$whereConditions = ['e.deleted_at IS NULL'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(e.reference_number LIKE :search OR ec.name LIKE :search OR e.description LIKE :search OR e.paid_by LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($categoryId > 0) {
    $whereConditions[] = 'e.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if (!empty($paymentMethod)) {
    $whereConditions[] = 'e.payment_method = :payment_method';
    $params['payment_method'] = $paymentMethod;
}

if (!empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $whereConditions[] = 'e.expense_date >= :start_date';
    $params['start_date'] = $startDate;
}

if (!empty($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $whereConditions[] = 'e.expense_date <= :end_date';
    $params['end_date'] = $endDate;
}

$whereSql = implode(' AND ', $whereConditions);

// Pagination
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM expenses e
    INNER JOIN expense_categories ec ON e.category_id = ec.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Expenses List
$listSql = "
    SELECT e.*, ec.name AS category_name, u.name AS creator_name
    FROM expenses e
    INNER JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE {$whereSql}
    ORDER BY e.expense_date DESC, e.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$expenses = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Expense Management</h2>
        <p class="text-muted small mb-0">Track, organize, and monitor laundry business operational expenses.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('modules/expenses/categories.php') ?>" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-folder me-1"></i> Manage Categories
        </a>
        <a href="<?= base_url('modules/expenses/create.php') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Record Expense
        </a>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Today -->
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Today's Expenses</div>
                <div class="h3 fw-bold text-dark mb-0"><?= e(format_price($todayExpenses)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= date('M d, Y') ?></div>
            </div>
        </div>
    </div>
    <!-- This Month -->
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">This Month</div>
                <div class="h3 fw-bold text-primary mb-0"><?= e(format_price($monthExpenses)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;"><?= date('F Y') ?></div>
            </div>
        </div>
    </div>
    <!-- Current Year -->
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Current Year</div>
                <div class="h3 fw-bold text-dark mb-0"><?= e(format_price($yearExpenses)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">Year <?= date('Y') ?></div>
            </div>
        </div>
    </div>
    <!-- Total All-Time -->
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold mb-1">Total Expenses</div>
                <div class="h3 fw-bold text-danger mb-0"><?= e(format_price($totalExpenses)) ?></div>
                <div class="small text-muted" style="font-size: 0.75rem;">all historical records</div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/expenses/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query -->
            <div class="col-12 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Ref #, description, paid by..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-outline-secondary" title="Clear">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category Filter -->
            <div class="col-6 col-md-2">
                <select name="category_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($filterCategories as $fc): ?>
                        <option value="<?= (int)$fc['id'] ?>" <?= $categoryId === (int)$fc['id'] ? 'selected' : '' ?>>
                            <?= e($fc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment Method Filter -->
            <div class="col-6 col-md-2">
                <select name="payment_method" class="form-select" onchange="this.form.submit()">
                    <option value="">All Channels</option>
                    <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                    <option value="mobile_banking" <?= $paymentMethod === 'mobile_banking' ? 'selected' : '' ?>>Mobile Banking</option>
                    <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="other" <?= $paymentMethod === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <!-- Start Date -->
            <div class="col-6 col-md-2">
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>" placeholder="From">
            </div>

            <!-- End Date -->
            <div class="col-6 col-md-2">
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>" placeholder="To">
            </div>

            <!-- Actions -->
            <div class="col-12 col-md-auto d-flex gap-2 ms-auto">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || $categoryId > 0 || !empty($paymentMethod) || !empty($startDate) || !empty($endDate)): ?>
                    <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Operational Expense Records</h3>
        <span class="badge bg-light text-dark border font-monospace"><?= $totalRecords ?> record(s) found</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 130px;">Ref #</th>
                        <th>Category</th>
                        <th style="width: 140px;">Expense Date</th>
                        <th class="text-end" style="width: 130px;">Amount</th>
                        <th>Payment Channel</th>
                        <th>Paid By</th>
                        <th>Recorded By</th>
                        <th class="text-end pe-3" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-wallet2 fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-semibold mb-1">No expense records found</h5>
                                <p class="small mb-3">Record your first business operational expense to start tracking.</p>
                                <a href="<?= base_url('modules/expenses/create.php') ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Record Expense
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td class="ps-3 font-monospace fw-semibold">
                                    <a href="<?= base_url('modules/expenses/show.php?id=' . (int)$exp['id']) ?>" class="text-decoration-none">
                                        <?= e($exp['reference_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($exp['category_name']) ?></span>
                                    <?php if (!empty($exp['description'])): ?>
                                        <div class="text-muted text-truncate" style="max-width: 180px; font-size: 0.75rem;" title="<?= e($exp['description']) ?>">
                                            <?= e($exp['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="font-monospace text-dark">
                                    <?= e(format_datetime($exp['expense_date'], 'M d, Y')) ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark fs-6">
                                    <?= e(format_price($exp['amount'])) ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border"><?= e(payment_method_label($exp['payment_method'])) ?></span>
                                </td>
                                <td class="text-dark">
                                    <?= e($exp['paid_by'] ?: '—') ?>
                                </td>
                                <td class="text-muted">
                                    <?= e($exp['creator_name'] ?: 'System') ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/expenses/show.php?id=' . (int)$exp['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= base_url('modules/expenses/edit.php?id=' . (int)$exp['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="Edit Expense"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?= base_url('modules/expenses/print.php?id=' . (int)$exp['id']) ?>" 
                                           target="_blank"
                                           class="btn btn-outline-secondary" 
                                           title="Print Voucher"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (is_admin() || is_manager()): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-delete-expense"
                                                    data-id="<?= (int)$exp['id'] ?>"
                                                    data-ref="<?= e($exp['reference_number']) ?>"
                                                    title="Delete Expense"
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

    <!-- Pagination Footer -->
    <?php if ($totalRecords > 0): ?>
        <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-muted small">
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> records
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Expenses pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/expenses/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/expenses/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/expenses/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/expenses/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteExpenseId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold text-danger" id="deleteExpenseModalLabel">Delete Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-danger d-block mb-2"></i>
                    <p class="mb-1 small text-muted">Are you sure you want to delete expense <strong id="deleteExpenseRef" class="font-monospace text-dark"></strong>?</p>
                    <small class="text-muted">This record will be soft-deleted and removed from normal reports.</small>
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
    const deleteButtons = document.querySelectorAll(".btn-delete-expense");
    const modalEl = document.getElementById("deleteExpenseModal");
    const idInput = document.getElementById("deleteExpenseId");
    const refText = document.getElementById("deleteExpenseRef");

    if (modalEl && typeof bootstrap !== "undefined") {
        const modal = new bootstrap.Modal(modalEl);
        deleteButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                idInput.value = this.getAttribute("data-id");
                refText.textContent = this.getAttribute("data-ref");
                modal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
