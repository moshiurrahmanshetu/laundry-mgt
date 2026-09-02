<?php
/**
 * Expense Voucher Profile & Details View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$expenseId = (int)($_GET['id'] ?? 0);
if ($expenseId <= 0) {
    set_flash_message('error', 'Invalid expense ID provided.');
    redirect('modules/expenses/index.php');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('
    SELECT e.*, ec.name AS category_name, ec.description AS category_description, u.name AS creator_name
    FROM expenses e
    INNER JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = :id AND e.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $expenseId]);
$expense = $stmt->fetch();

if (!$expense) {
    set_flash_message('error', 'The requested expense record does not exist or has been deleted.');
    redirect('modules/expenses/index.php');
}

$pageTitle = 'Expense ' . $expense['reference_number'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/expenses/index.php') ?>">Expenses</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($expense['reference_number']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="h4 fw-bold text-dark mb-0 font-monospace"><?= e($expense['reference_number']) ?></h2>
            <span class="badge bg-light text-dark border fs-6"><?= e($expense['category_name']) ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('modules/expenses/print.php?id=' . (int)$expense['id']) ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Voucher
            </a>
            <a href="<?= base_url('modules/expenses/edit.php?id=' . (int)$expense['id']) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Expense
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteExpenseModal">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
            <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <!-- Expense Voucher Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-primary"></i>Operational Expense Voucher</h3>
                <span class="badge bg-primary text-uppercase font-monospace px-3 py-1">Incurred</span>
            </div>
            <div class="card-body p-4">
                <!-- Amount Banner -->
                <div class="p-3 bg-light rounded text-center mb-4 border">
                    <div class="text-muted small fw-semibold text-uppercase mb-1">Total Expense Incurred</div>
                    <div class="display-5 fw-bold text-dark font-monospace mb-0"><?= e(format_price($expense['amount'])) ?></div>
                    <div class="text-muted small mt-1">Paid via <?= e(payment_method_label($expense['payment_method'])) ?></div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Expense Reference #</label>
                        <span class="font-monospace fw-bold text-dark fs-6"><?= e($expense['reference_number']) ?></span>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Expense Category</label>
                        <span class="fw-semibold text-dark"><?= e($expense['category_name']) ?></span>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Incurred Date</label>
                        <span class="fw-semibold text-dark font-monospace"><?= e(format_datetime($expense['expense_date'], 'M d, Y (D)')) ?></span>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Payment Channel</label>
                        <span class="badge bg-light text-secondary border"><?= e(payment_method_label($expense['payment_method'])) ?></span>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Paid By / Payee Note</label>
                        <span class="text-dark"><?= e($expense['paid_by'] ?: 'Not specified') ?></span>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Recorded By Staff</label>
                        <span class="text-dark"><?= e($expense['creator_name'] ?: 'System') ?></span>
                    </div>

                    <?php if (!empty($expense['description'])): ?>
                        <div class="col-12">
                            <label class="text-muted small d-block">Description / Bill Memo</label>
                            <div class="p-3 bg-light rounded border text-dark small" style="white-space: pre-line;">
                                <?= e($expense['description']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center text-muted small">
                <span>Created: <?= e(format_datetime($expense['created_at'])) ?></span>
                <span>Last Updated: <?= e(format_datetime($expense['updated_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/expenses/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$expense['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold text-danger" id="deleteExpenseModalLabel">Delete Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3 text-center">
                    <i class="bi bi-exclamation-triangle fs-1 text-danger d-block mb-2"></i>
                    <p class="mb-1 small text-muted">Are you sure you want to delete expense <strong class="font-monospace text-dark"><?= e($expense['reference_number']) ?></strong>?</p>
                    <small class="text-muted">This record will be soft-deleted and removed from normal listings.</small>
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
require_once __DIR__ . '/../../includes/footer.php';
?>
