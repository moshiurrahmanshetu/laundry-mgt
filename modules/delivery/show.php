<?php
/**
 * Pickup & Delivery Request Details View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid request ID.');
    redirect('modules/delivery/index.php');
}

$pdo = getDBConnection();
$currentUser = current_user();
$canAssign = is_admin() || is_manager();
$canDelete = is_admin() || is_manager();

// Fetch request details with order, customer, assigned staff, and creator joins
$stmt = $pdo->prepare('
    SELECT pd.*,
           o.order_number, o.order_date, o.expected_date, o.subtotal, o.discount,
           o.total, o.paid_amount, o.due_amount, o.payment_status, o.status AS order_status,
           c.name AS customer_name, c.customer_code, c.phone AS customer_profile_phone,
           c.email AS customer_email,
           u_assigned.name AS assigned_staff_name, u_assigned.phone AS assigned_staff_phone,
           u_created.name AS creator_name
    FROM pickup_deliveries pd
    INNER JOIN orders o ON pd.order_id = o.id
    INNER JOIN customers c ON pd.customer_id = c.id
    LEFT JOIN users u_assigned ON pd.assigned_to = u_assigned.id
    LEFT JOIN users u_created ON pd.created_by = u_created.id
    WHERE pd.id = :id AND pd.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    set_flash_message('error', 'The requested pickup/delivery record does not exist or has been deleted.');
    redirect('modules/delivery/index.php');
}

// Fetch staff for assignment modal
$staffStmt = $pdo->query('
    SELECT u.id, u.name, r.name AS role_name
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.status = "active"
    ORDER BY u.name ASC
');
$assignableStaff = $staffStmt->fetchAll();

$pageTitle = $req['reference_number'] . ' Details';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/delivery/index.php') ?>">Pickup &amp; Delivery</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($req['reference_number']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h2 class="h4 fw-bold text-dark mb-0 font-monospace"><?= e($req['reference_number']) ?></h2>
            <?= delivery_type_badge($req['type']) ?>
            <?= delivery_status_badge($req['status']) ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <!-- Update Status Button -->
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                <i class="bi bi-arrow-repeat me-1"></i> Update Status
            </button>

            <!-- Assign Staff Button (Admin & Manager) -->
            <?php if ($canAssign): ?>
                <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#assignStaffModal">
                    <i class="bi bi-person-check me-1"></i> Assign Staff
                </button>
            <?php endif; ?>

            <!-- Print Slip -->
            <a href="<?= base_url('modules/delivery/print.php?id=' . (int)$req['id']) ?>" 
               target="_blank" 
               class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Slip
            </a>

            <!-- Edit (Admin & Manager) -->
            <?php if ($canAssign): ?>
                <a href="<?= base_url('modules/delivery/edit.php?id=' . (int)$req['id']) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            <?php endif; ?>

            <!-- Delete Button (Admin Only) -->
            <?php if ($canDelete): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            <?php endif; ?>

            <!-- Back to List -->
            <a href="<?= base_url('modules/delivery/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Request Profile & Address Snapshot -->
    <div class="col-12 col-lg-8">
        <!-- Request Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-geo-alt-fill me-2 text-primary"></i><?= ucfirst($req['type']) ?> Destination &amp; Contact Snapshot</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= e($req['reference_number']) ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Contact Person</label>
                        <div class="fw-bold text-dark fs-6"><?= e($req['contact_name']) ?></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Contact Phone</label>
                        <div class="font-monospace fw-semibold text-primary fs-6">
                            <i class="bi bi-telephone me-1"></i><?= e($req['contact_phone']) ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small d-block">Full Service Address</label>
                        <div class="p-3 bg-light rounded border text-dark">
                            <i class="bi bi-pin-map-fill text-danger me-2"></i><?= e($req['address']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule & Assignment Details Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calendar-event me-2 text-primary"></i>Schedule &amp; Dispatch Status</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Scheduled Date</span>
                        <span class="fw-bold <?= strtotime($req['scheduled_date']) < strtotime('today') && $req['status'] !== 'completed' ? 'text-danger' : 'text-dark' ?>">
                            <?= e(format_datetime($req['scheduled_date'], 'M d, Y')) ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Scheduled Time</span>
                        <span class="fw-semibold font-monospace">
                            <?= !empty($req['scheduled_time']) ? date('h:i A', strtotime($req['scheduled_time'])) : 'Flexible / Any time' ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Assigned Staff</span>
                        <span class="fw-semibold text-dark">
                            <?php if (!empty($req['assigned_staff_name'])): ?>
                                <i class="bi bi-person-badge text-success me-1"></i><?= e($req['assigned_staff_name']) ?>
                                <?php if (!empty($req['assigned_staff_phone'])): ?>
                                    <span class="text-muted font-monospace small ms-1">(<?= e($req['assigned_staff_phone']) ?>)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border">Unassigned</span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Execution Status</span>
                        <span><?= delivery_status_badge($req['status']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Completion Timestamp</span>
                        <span class="fw-semibold text-dark font-monospace">
                            <?= !empty($req['completed_at']) ? e(format_datetime($req['completed_at'])) : '—' ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Handling Instructions & Notes Card -->
        <?php if (!empty($req['notes'])): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-chat-left-text me-2 text-primary"></i>Handling &amp; Driver Instructions</h3>
                </div>
                <div class="card-body p-4">
                    <p class="text-dark mb-0" style="white-space: pre-line;"><?= e($req['notes']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Associated Order Summary & Metadata -->
    <div class="col-12 col-lg-4">
        <!-- Associated Order Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-basket3 me-2 text-primary"></i>Associated Order</h3>
                <a href="<?= base_url('modules/orders/show.php?id=' . (int)$req['order_id']) ?>" class="small text-decoration-none">
                    View Full Order <i class="bi bi-arrow-up-right"></i>
                </a>
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="font-monospace fw-bold fs-6 text-primary"><?= e($req['order_number']) ?></span>
                    <?= order_status_badge($req['order_status']) ?>
                </div>

                <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle fw-bold" style="width: 36px; height: 36px;">
                        <?= e(get_user_initials($req['customer_name'])) ?>
                    </div>
                    <div>
                        <div class="fw-bold text-dark small"><?= e($req['customer_name']) ?></div>
                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;"><?= e($req['customer_code']) ?></span>
                    </div>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Order Total</span>
                        <span class="font-monospace fw-bold"><?= e(format_price($req['total'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Total Paid</span>
                        <span class="font-monospace text-success fw-semibold"><?= e(format_price($req['paid_amount'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Due Balance</span>
                        <span class="font-monospace <?= (float)$req['due_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                            <?= e(format_price($req['due_amount'])) ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span class="text-muted">Payment Status</span>
                        <?= payment_status_badge($req['payment_status']) ?>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Audit & Tracking Metadata Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock me-2 text-primary"></i>System Audit</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Created By</span>
                        <span class="fw-semibold text-dark"><?= e($req['creator_name'] ?: 'System / Staff') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Created At</span>
                        <span class="fw-semibold"><?= e(format_datetime($req['created_at'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Last Updated</span>
                        <span class="fw-semibold"><?= e(format_datetime($req['updated_at'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Update Status -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/delivery/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="updateStatusModalLabel">Update Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Request: <strong class="text-dark font-monospace"><?= e($req['reference_number']) ?></strong></p>
                    <label for="status_select" class="form-label small text-muted">Select Status</label>
                    <select name="status" id="status_select" class="form-select">
                        <option value="pending" <?= $req['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="assigned" <?= $req['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="in_progress" <?= $req['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $req['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $req['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Assign Staff (Admin & Manager) -->
<?php if ($canAssign): ?>
<div class="modal fade" id="assignStaffModal" tabindex="-1" aria-labelledby="assignStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/delivery/assign.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="assignStaffModalLabel">Assign Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Request: <strong class="text-dark font-monospace"><?= e($req['reference_number']) ?></strong></p>
                    <label for="assign_select" class="form-label small text-muted">Select Staff Member</label>
                    <select name="assigned_to" id="assign_select" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($assignableStaff as $as): ?>
                            <option value="<?= (int)$as['id'] ?>" <?= ((int)$req['assigned_to'] === (int)$as['id']) ? 'selected' : '' ?>>
                                <?= e($as['name']) ?> (<?= e($as['role_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Soft Delete Confirmation (Admin Only) -->
<?php if ($canDelete): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/delivery/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete <?= e($req['type']) ?> request <strong class="font-monospace"><?= e($req['reference_number']) ?></strong>?</p>
                    <p class="small text-muted mb-0">This record will be soft-deleted.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Request
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
