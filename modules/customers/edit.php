<?php
/**
 * Edit Customer View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    set_flash_message('error', 'Invalid customer ID provided.');
    redirect('modules/customers/index.php');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash_message('error', 'The requested customer does not exist or has been deleted.');
    redirect('modules/customers/index.php');
}

$pageTitle = 'Edit ' . $customer['name'];

// Check for old input flashed in session on validation error
$old = $_SESSION['old_customer_input'] ?? $customer;
unset($_SESSION['old_customer_input']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/customers/index.php') ?>">Customers</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/customers/show.php?id=' . (int)$customer['id']) ?>"><?= e($customer['customer_code']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-0">Edit Customer: <?= e($customer['name']) ?></h2>
            <span class="text-muted small">Code: <span class="font-monospace fw-semibold text-primary"><?= e($customer['customer_code']) ?></span></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/customers/show.php?id=' . (int)$customer['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i> View Profile
            </a>
            <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<!-- Edit Form Card -->
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Update Customer Information</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/customers/update.php') ?>" method="POST" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$customer['id'] ?>">

                    <!-- Customer Code (Readonly) & Primary Info -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Customer Code</label>
                            <input type="text" class="form-control bg-light font-monospace text-muted" value="<?= e($customer['customer_code']) ?>" readonly>
                            <div class="form-text small">System-assigned unique identifier.</div>
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?= e($old['name'] ?? $customer['name']) ?>" 
                                       required 
                                       maxlength="100" 
                                       autofocus>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?= e($old['phone'] ?? $customer['phone']) ?>" 
                                       required 
                                       maxlength="30">
                            </div>
                            <div class="form-text small">Primary contact phone (must be unique).</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?= e($old['email'] ?? $customer['email']) ?>" 
                                       maxlength="191">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label">Account Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" <?= ($old['status'] ?? $customer['status']) === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($old['status'] ?? $customer['status']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <h4 class="h6 fw-semibold text-secondary mb-3 border-top pt-3"><i class="bi bi-geo-alt me-2 text-primary"></i>Address &amp; Location Details</h4>

                    <!-- Address Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="address" class="form-label">Street / House Address</label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="2"><?= e($old['address'] ?? $customer['address']) ?></textarea>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="city" class="form-label">City</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="city" 
                                   name="city" 
                                   value="<?= e($old['city'] ?? $customer['city']) ?>" 
                                   maxlength="100">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="postal_code" class="form-label">Postal / Zip Code</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   value="<?= e($old['postal_code'] ?? $customer['postal_code']) ?>" 
                                   maxlength="20">
                        </div>
                    </div>

                    <h4 class="h6 fw-semibold text-secondary mb-3 border-top pt-3"><i class="bi bi-journal-text me-2 text-primary"></i>Customer Notes &amp; Special Preferences</h4>

                    <!-- Notes Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes / Washing Preferences</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3"><?= e($old['notes'] ?? $customer['notes']) ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= base_url('modules/customers/show.php?id=' . (int)$customer['id']) ?>" class="btn btn-outline-secondary px-4">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
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
