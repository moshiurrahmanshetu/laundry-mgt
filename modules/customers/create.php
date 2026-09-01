<?php
/**
 * Add New Customer View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Add Customer';
$old = $_SESSION['old_customer_input'] ?? [];
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
            <li class="breadcrumb-item active" aria-current="page">Add Customer</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 fw-bold text-dark mb-0">Add New Customer</h2>
        <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<!-- Customer Form Card -->
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person-plus me-2 text-primary"></i>Customer Information</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/customers/store.php') ?>" method="POST" autocomplete="off">
                    <?= csrf_field() ?>

                    <!-- Primary Contact Info Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       placeholder="e.g. Rahim Ahmed" 
                                       value="<?= e($old['name'] ?? '') ?>" 
                                       required 
                                       maxlength="100" 
                                       autofocus>
                            </div>
                            <div class="form-text small">Full name of the individual or organization.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="e.g. +880 1711 000000" 
                                       value="<?= e($old['phone'] ?? '') ?>" 
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
                                       placeholder="customer@example.com" 
                                       value="<?= e($old['email'] ?? '') ?>" 
                                       maxlength="191">
                            </div>
                            <div class="form-text small">Optional for email receipts and notifications.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label">Account Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <div class="form-text small">Inactive customers cannot be assigned to new laundry orders.</div>
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
                                      rows="2" 
                                      placeholder="e.g. House 12, Road 5, Dhanmondi"><?= e($old['address'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="city" class="form-label">City</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="city" 
                                   name="city" 
                                   placeholder="e.g. Dhaka" 
                                   value="<?= e($old['city'] ?? '') ?>" 
                                   maxlength="100">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="postal_code" class="form-label">Postal / Zip Code</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   placeholder="e.g. 1205" 
                                   value="<?= e($old['postal_code'] ?? '') ?>" 
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
                                      rows="3" 
                                      placeholder="e.g. Sensitive fabric preferences, starch requests, delivery gate notes..."><?= e($old['notes'] ?? '') ?></textarea>
                            <div class="form-text small">Internal notes visible to staff when processing orders.</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-outline-secondary px-4">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Customer
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
