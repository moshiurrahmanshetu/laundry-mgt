<?php
/**
 * Record New Operational Expense Form
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrator and Manager
require_role(['administrator', 'manager']);

$pageTitle = 'Record Expense';
$currentUser = current_user();
$pdo = getDBConnection();

// Fetch active non-deleted categories
$catStmt = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC");
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
            <li class="breadcrumb-item active" aria-current="page">Record Expense</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">Record Operational Expense</h2>
            <p class="text-muted small mb-0">Record a new business operational or utility expense payment.</p>
        </div>
        <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Expenses
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-wallet2 me-2 text-primary"></i>Expense Details</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/expenses/store.php') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <!-- Expense Category -->
                        <div class="col-12 col-md-6">
                            <label for="category_id" class="form-label small fw-semibold text-dark">
                                Expense Category <span class="text-danger">*</span>
                            </label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">-- Select Expense Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>">
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Don't see your category? <a href="<?= base_url('modules/expenses/categories.php') ?>" target="_blank">Add new category</a>
                            </div>
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
                                       placeholder="0.00" 
                                       required>
                            </div>
                        </div>

                        <!-- Expense Date -->
                        <div class="col-12 col-md-6">
                            <label for="expense_date" class="form-label small fw-semibold text-dark">
                                Expense Incurred Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   name="expense_date" 
                                   id="expense_date" 
                                   class="form-control" 
                                   value="<?= date('Y-m-d') ?>" 
                                   required>
                            <div class="form-text">Date the bill or operational cost was incurred.</div>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-12 col-md-6">
                            <label for="payment_method" class="form-label small fw-semibold text-dark">
                                Payment Channel <span class="text-danger">*</span>
                            </label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="cash" selected>Cash</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="mobile_banking">Mobile Banking</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Paid By (Descriptive text) -->
                        <div class="col-12">
                            <label for="paid_by" class="form-label small fw-semibold text-dark">
                                Paid By / Payee Note <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <input type="text" 
                                   name="paid_by" 
                                   id="paid_by" 
                                   class="form-control" 
                                   placeholder="e.g. Store Manager, Cash Register, Admin John" 
                                   maxlength="150">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold text-dark">
                                Expense Description / Bill Memo <span class="text-muted small fw-normal">(Optional)</span>
                            </label>
                            <textarea name="description" 
                                      id="description" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="e.g. Monthly electricity bill for laundry plant, invoice #EL-9821"></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= base_url('modules/expenses/index.php') ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Expense Record
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
