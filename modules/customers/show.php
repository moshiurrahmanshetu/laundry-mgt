<?php
/**
 * Customer Profile / Detail View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    set_flash_message('error', 'Invalid customer ID provided.');
    redirect('modules/customers/index.php');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('
    SELECT c.*, u.name AS creator_name, u.email AS creator_email
    FROM customers c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = :id AND c.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash_message('error', 'The requested customer does not exist or has been deleted.');
    redirect('modules/customers/index.php');
}

$pageTitle = $customer['name'] . ' (' . $customer['customer_code'] . ')';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/customers/index.php') ?>">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($customer['customer_code']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="h4 fw-bold text-dark mb-0"><?= e($customer['name']) ?></h2>
            <span class="badge bg-light text-primary border font-monospace"><?= e($customer['customer_code']) ?></span>
            <?php if ($customer['status'] === 'active'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <!-- Edit -->
            <a href="<?= base_url('modules/customers/edit.php?id=' . (int)$customer['id']) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Customer
            </a>

            <!-- Toggle Status Button -->
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#toggleStatusModal">
                <i class="bi <?= $customer['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?> me-1"></i>
                <?= $customer['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
            </button>

            <!-- Delete Button (Admin & Manager Only) -->
            <?php if (is_admin() || is_manager()): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCustomerModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            <?php endif; ?>

            <!-- Back to List -->
            <a href="<?= base_url('modules/customers/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Customer Overview & Metadata -->
    <div class="col-12 col-lg-4">
        <!-- Overview Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person-badge me-2 text-primary"></i>Customer Profile</h3>
            </div>
            <div class="card-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle fw-bold fs-3 mb-3" style="width: 72px; height: 72px;">
                    <?= e(get_user_initials($customer['name'])) ?>
                </div>
                <h4 class="h5 fw-bold text-dark mb-1"><?= e($customer['name']) ?></h4>
                <div class="font-monospace text-muted small mb-2"><?= e($customer['customer_code']) ?></div>
                <div>
                    <?php if ($customer['status'] === 'active'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active Account</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border">Inactive Account</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-light p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Customer Code</span>
                        <span class="fw-semibold font-monospace"><?= e($customer['customer_code']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Primary Phone</span>
                        <span class="fw-semibold font-monospace text-dark"><?= e($customer['phone']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Email</span>
                        <span class="fw-semibold text-truncate" style="max-width: 170px;"><?= e($customer['email'] ?: 'Not provided') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">City / Region</span>
                        <span class="fw-semibold"><?= e($customer['city'] ?: 'Not provided') ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Audit & Metadata Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>System Record Details</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Registered On</span>
                        <span class="fw-semibold"><?= e(format_datetime($customer['created_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Last Updated</span>
                        <span class="fw-semibold"><?= e(format_datetime($customer['updated_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Registered By</span>
                        <span class="fw-semibold text-dark"><?= e($customer['creator_name'] ?: 'System / Unknown') ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Contact, Address, Notes & Order History Placeholder -->
    <div class="col-12 col-lg-8">
        <!-- Contact & Address Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-geo-alt me-2 text-primary"></i>Contact &amp; Delivery Address</h3>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="small text-muted d-block mb-1">Phone Number</label>
                        <div class="fw-semibold text-dark fs-6 font-monospace">
                            <i class="bi bi-telephone text-primary me-2"></i><?= e($customer['phone']) ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="small text-muted d-block mb-1">Email Address</label>
                        <div class="fw-semibold text-dark fs-6">
                            <?php if (!empty($customer['email'])): ?>
                                <i class="bi bi-envelope text-primary me-2"></i><a href="mailto:<?= e($customer['email']) ?>" class="text-decoration-none"><?= e($customer['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted fst-italic">No email provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted d-block mb-1">Street Address</label>
                        <div class="p-3 bg-light border rounded text-dark">
                            <?= !empty($customer['address']) ? nl2br(e($customer['address'])) : '<span class="text-muted fst-italic">No address registered</span>' ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="small text-muted d-block mb-1">City</label>
                        <div class="fw-semibold text-dark"><?= e($customer['city'] ?: '&mdash;') ?></div>
                    </div>
                    <div class="col-6">
                        <label class="small text-muted d-block mb-1">Postal Code</label>
                        <div class="fw-semibold text-dark font-monospace"><?= e($customer['postal_code'] ?: '&mdash;') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes / Washing Preferences Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-primary"></i>Customer Notes &amp; Washing Preferences</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($customer['notes'])): ?>
                    <div class="p-3 bg-light border rounded text-dark" style="white-space: pre-line;">
                        <?= e($customer['notes']) ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted fst-italic mb-0">No special washing preferences or notes on file.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order History Placeholder Card (Phase 04) -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-basket me-2 text-primary"></i>Order History</h3>
                <span class="badge bg-secondary-subtle text-secondary border">Phase 04 Feature</span>
            </div>
            <div class="card-body text-center py-5">
                <div class="text-muted">
                    <i class="bi bi-basket3 fs-1 d-block mb-2 text-secondary"></i>
                    <h5 class="fw-semibold mb-1">No laundry orders found for this customer</h5>
                    <p class="small text-muted mb-0">Laundry order intake, processing, and payment tracking will be enabled in Phase 04.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Toggle Status -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/customers/toggle_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$customer['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold">Change Customer Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="mb-2 text-dark">
                        Change status of <strong><?= e($customer['name']) ?></strong> to
                        <span class="badge <?= $customer['status'] === 'active' ? 'bg-secondary' : 'bg-success' ?> text-uppercase">
                            <?= $customer['status'] === 'active' ? 'Inactive' : 'Active' ?>
                        </span>?
                    </p>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Soft Delete (Admin & Manager Only) -->
<?php if (is_admin() || is_manager()): ?>
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/customers/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$customer['id'] ?>">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Customer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete customer <strong><?= e($customer['name']) ?></strong> (<span class="font-monospace"><?= e($customer['customer_code']) ?></span>)?</p>
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
require_once __DIR__ . '/../../includes/footer.php';
?>
