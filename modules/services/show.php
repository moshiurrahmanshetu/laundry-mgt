<?php
/**
 * Laundry Service Profile / Details View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$serviceId = (int)($_GET['id'] ?? 0);
if ($serviceId <= 0) {
    set_flash_message('error', 'Invalid service ID provided.');
    redirect('modules/services/index.php');
}

$pdo = getDBConnection();
$canManage = is_admin() || is_manager();

// Fetch service details
$stmt = $pdo->prepare('
    SELECT s.*, u.name AS creator_name
    FROM services s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.id = :id AND s.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $serviceId]);
$service = $stmt->fetch();

if (!$service) {
    set_flash_message('error', 'The requested laundry service does not exist or has been deleted.');
    redirect('modules/services/index.php');
}

// Fetch active item rates
$itemStmt = $pdo->prepare('
    SELECT *
    FROM service_items
    WHERE service_id = :service_id AND deleted_at IS NULL
    ORDER BY sort_order ASC, id ASC
');
$itemStmt->execute(['service_id' => $serviceId]);
$items = $itemStmt->fetchAll();

$pageTitle = $service['name'] . ' — Service Details';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/services/index.php') ?>">Services</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($service['name']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <h2 class="h4 fw-bold text-dark mb-0"><?= e($service['name']) ?></h2>
            <?php if ($service['pricing_type'] === 'per_kg'): ?>
                <span class="badge bg-primary text-white"><i class="bi bi-speedometer me-1"></i>Per KG</span>
            <?php else: ?>
                <span class="badge bg-dark text-white"><i class="bi bi-grid me-1"></i>Per Item</span>
            <?php endif; ?>

            <?php if ($service['status'] === 'active'): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border">Inactive</span>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <?php if ($canManage): ?>
                <!-- Edit -->
                <a href="<?= base_url('modules/services/edit.php?id=' . (int)$service['id']) ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit Service &amp; Rates
                </a>

                <!-- Toggle Status Button -->
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#toggleStatusModal">
                    <i class="bi <?= $service['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?> me-1"></i>
                    <?= $service['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>

                <!-- Delete Button (Admin & Manager Only) -->
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteServiceModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            <?php endif; ?>

            <!-- Back to List -->
            <a href="<?= base_url('modules/services/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Service Overview & Metadata -->
    <div class="col-12 col-lg-4">
        <!-- Overview Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Service Summary</h3>
            </div>
            <div class="card-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle fw-bold fs-2 mb-3" style="width: 72px; height: 72px;">
                    <i class="bi <?= $service['pricing_type'] === 'per_kg' ? 'bi-speedometer' : 'bi-tag' ?>"></i>
                </div>
                <h4 class="h5 fw-bold text-dark mb-1"><?= e($service['name']) ?></h4>
                <div class="font-monospace text-muted small mb-2">slug: <?= e($service['slug']) ?></div>
                <div>
                    <?php if ($service['status'] === 'active'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active in Order Catalog</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border">Inactive in Order Catalog</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-light p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Pricing Model</span>
                        <span class="fw-semibold"><?= $service['pricing_type'] === 'per_kg' ? 'Per KG Weight' : 'Per Item Garment' ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Active Item Rates</span>
                        <span class="fw-semibold font-monospace"><?= count($items) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Audit & Metadata Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>System Record</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Created On</span>
                        <span class="fw-semibold"><?= e(format_datetime($service['created_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Last Updated</span>
                        <span class="fw-semibold"><?= e(format_datetime($service['updated_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Created By</span>
                        <span class="fw-semibold text-dark"><?= e($service['creator_name'] ?: 'System / Unknown') ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Description & Itemized Pricing Rates -->
    <div class="col-12 col-lg-8">
        <!-- Service Description Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-card-text me-2 text-primary"></i>Service Description</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($service['description'])): ?>
                    <p class="text-dark mb-0" style="white-space: pre-line;"><?= e($service['description']) ?></p>
                <?php else: ?>
                    <p class="text-muted fst-italic mb-0">No description provided for this service category.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Itemized Pricing Table Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h3 class="h6 mb-0 fw-semibold">
                    <i class="bi <?= $service['pricing_type'] === 'per_kg' ? 'bi-speedometer' : 'bi-tags' ?> me-2 text-primary"></i>
                    <?= $service['pricing_type'] === 'per_kg' ? 'Weight-Based Pricing Rate' : 'Itemized Garment Rates' ?>
                </h3>
                <span class="badge bg-light text-dark border font-monospace"><?= count($items) ?> rate(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-tag fs-1 d-block mb-2 text-secondary"></i>
                        <h5 class="fw-semibold mb-1">No pricing rates configured</h5>
                        <p class="small mb-0">Edit this service to add clothing items and price rates.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">#</th>
                                    <th>Item / Garment Name</th>
                                    <th>Description / Notes</th>
                                    <th style="width: 100px;">Unit</th>
                                    <th class="text-end pe-3" style="width: 140px;">Price Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $idx => $item): ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="small text-muted"><?= e($item['description'] ?: '—') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace text-uppercase"><?= e($item['unit']) ?></span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <span class="fw-bold font-monospace text-primary fs-6">
                                                <?= e(format_price($item['price'])) ?>
                                                <?php if ($item['unit'] === 'kg'): ?>
                                                    <span class="small text-muted fw-normal">/ KG</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Toggle Status (Admin/Manager Only) -->
<?php if ($canManage): ?>
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/services/toggle_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold">Change Service Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="mb-2 text-dark">
                        Change status of <strong><?= e($service['name']) ?></strong> to
                        <span class="badge <?= $service['status'] === 'active' ? 'bg-secondary' : 'bg-success' ?> text-uppercase">
                            <?= $service['status'] === 'active' ? 'Inactive' : 'Active' ?>
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
<div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/services/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Laundry Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete service <strong><?= e($service['name']) ?></strong>?</p>
                    <p class="small text-muted mb-0">This service will be soft-deleted and removed from the active catalog.</p>
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
require_once __DIR__ . '/../../includes/footer.php';
?>
