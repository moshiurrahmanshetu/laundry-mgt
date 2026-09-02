<?php
/**
 * Edit Operational Expense Form
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

$stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $expenseId]);
$expense = $stmt->fetch();

if (!$expense) {
    set_flash_message('error', 'The requested expense record does not exist or has been deleted.');
    redirect('modules/expenses/index.php');
}

$pageTitle = 'Edit ' . $expense['reference_number'];

// Fetch categories (active or current category)
$catStmt = $pdo->prepare('
    SELECT id, name, status 
    FROM expense_categories 
    WHERE (status = "active" OR id = :current_id) AND deleted_at IS NULL 
    ORDER BY name ASC
');
$catStmt->execute(['current_id' => $expense['category_id']]);
$categories = $catStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/expenses/index.php') ?>">Expenses</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/expenses/show.php?id=' . (int)$expense['id']) ?>"><?= e($expense['reference_number']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Edit Expense: <span class="font-monospace text-primary"><?= e($expense['reference_number']) ?></span></h2>
            <p class="text-muted small mb-0">Modify expense categorization, amount, or billing notes.</p>
        </div>
        <a href="<?= base_url('modules/expenses/show.php?id=' . (int)$expense['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Cancel
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Update Expense Information</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/expenses/update.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$expense['id'] ?>">

                    <div class="row g-3 mb-3">
                        <!-- Immutable Reference -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold text-dark">Expense Reference #</label>
                            <input type="text" class="form-control font-monospace bg-light" value="<?= e($expense['reference_number']) ?>" readonly disabled>
                            <div class="form-text">Reference numbers are permanently fixed for audit integrity.</div>
                        </div>

                        <!-- Category -->
                        <div class="col-12 col-md-6">
                            <label for="category_id" class="form-label small fw-semibold text-dark">
                                Expense Category <span class="text-danger">*</span>
                            </label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$expense['category_id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?> <?= $cat['status'] === 'inactive' ? '(Inactive)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Amount -->
                        <div class="col-12 col-md-6">
                            <label for="amount" class="form-label small fw-semibold text-dark">
                                Expense Amount ($) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" 
                                       name="amount" 
                                       id="amount" 
                                       class="form-control font-monospace" 
                                       step="0.01" 
                                       min="0.01" 
                                       value="<?= htmlspecialchars(number_format((float)$expense['amount'], 2, '.', '')) ?>" 
                                       required>
                            </div>
                        </div>

                        <!-- Expense Date -->
                        <div class="col-12 col-md-6">
                            <label for="expense_date" class="form-label small fw-semibold text-dark">
                                Incurred Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   name="expense_date" 
                                   id="expense_date" 
                                   class="form-control" 
                                   value="<?= e($expense['expense_date']) ?>" 
                                   required>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-12 col-md-6">
                            <label for="payment_method" class="form-label small fw-semibold text-dark">
                                Payment Channel <span class="text-danger">*</span>
                            </label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="cash" <?= $expense['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="card" <?= $expense['payment_method'] === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                                <option value="mobile_banking" <?= $expense['payment_method'] === 'mobile_banking' ? 'selected' : '' ?>>Mobile Banking</option>
                                <option value="bank_transfer" <?= $expense['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                <option value="other" <?= $expense['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <!-- Paid By -->
                        <div class="col-12 col-md-6">
                            <label for="paid_by" class="form-label small fw-semibold text-dark">
                                Paid By / Payee Note <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <input type="text" 
                                   name="paid_by" 
                                   id="paid_by" 
                                   class="form-control" 
                                   value="<?= e($expense['paid_by'] ?? '') ?>" 
                                   maxlength="150">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold text-dark">
                                Description / Bill Memo <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <textarea name="description" 
                                      id="description" 
                                      class="form-control" 
                                      rows="3"><?= e($expense['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('modules/expenses/show.php?id=' . (int)$expense['id']) ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Update Expense
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
