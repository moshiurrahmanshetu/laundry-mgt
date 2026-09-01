<?php
/**
 * Edit Pickup / Delivery Request View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['administrator', 'manager'], 'modules/delivery/index.php');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid request ID.');
    redirect('modules/delivery/index.php');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('
    SELECT pd.*,
           o.order_number, c.name AS customer_name, c.customer_code
    FROM pickup_deliveries pd
    INNER JOIN orders o ON pd.order_id = o.id
    INNER JOIN customers c ON pd.customer_id = c.id
    WHERE pd.id = :id AND pd.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    set_flash_message('error', 'The requested record does not exist or has been deleted.');
    redirect('modules/delivery/index.php');
}

// Fetch assignable staff
$staffStmt = $pdo->query('
    SELECT u.id, u.name, r.name AS role_name
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.status = "active"
    ORDER BY u.name ASC
');
$assignableStaff = $staffStmt->fetchAll();

$pageTitle = 'Edit ' . $req['reference_number'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/delivery/index.php') ?>">Pickup &amp; Delivery</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/delivery/show.php?id=' . (int)$req['id']) ?>"><?= e($req['reference_number']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 fw-bold text-dark mb-0">Edit <?= ucfirst($req['type']) ?> Schedule</h2>
        <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$req['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Details
        </a>
    </div>
</div>

<?php if ($req['status'] === 'completed'): ?>
    <div class="alert alert-info d-flex align-items-center shadow-sm mb-4">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        <div>
            This request has been marked as <strong>Completed</strong>. Update schedule or contact details with care.
        </div>
    </div>
<?php endif; ?>

<form action="<?= base_url('modules/delivery/update.php') ?>" method="POST" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">

    <div class="row g-4">
        <!-- Left Column: Request Profile & Address -->
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-card-checklist me-2 text-primary"></i>Request Information</h3>
                    <span class="badge bg-light text-dark border font-monospace"><?= e($req['reference_number']) ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Readonly Info -->
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Request Type</label>
                            <div><?= delivery_type_badge($req['type']) ?></div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Associated Order</label>
                            <div class="font-monospace fw-semibold text-primary">
                                <?= e($req['order_number']) ?> (<?= e($req['customer_name']) ?>)
                            </div>
                        </div>

                        <!-- Contact Name -->
                        <div class="col-12 col-md-6">
                            <label for="contact_name" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="contact_name" 
                                   name="contact_name" 
                                   value="<?= e($req['contact_name']) ?>" 
                                   required>
                        </div>

                        <!-- Contact Phone -->
                        <div class="col-12 col-md-6">
                            <label for="contact_phone" class="form-label fw-semibold">Contact Phone <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control font-monospace" 
                                   id="contact_phone" 
                                   name="contact_phone" 
                                   value="<?= e($req['contact_phone']) ?>" 
                                   required>
                        </div>

                        <!-- Service Address -->
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Pickup / Delivery Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="2" 
                                      required><?= e($req['address']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-chat-left-text me-2 text-primary"></i>Handling &amp; Driver Instructions</h3>
                </div>
                <div class="card-body p-3">
                    <textarea class="form-control" 
                              name="notes" 
                              id="notes" 
                              rows="3"><?= e($req['notes']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right Column: Schedule & Staff -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>Schedule &amp; Assignment</h3>
                </div>
                <div class="card-body p-4">
                    <!-- Scheduled Date -->
                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label fw-semibold">Scheduled Date <span class="text-danger">*</span></label>
                        <input type="date" 
                               class="form-control" 
                               id="scheduled_date" 
                               name="scheduled_date" 
                               value="<?= e($req['scheduled_date']) ?>" 
                               required>
                    </div>

                    <!-- Scheduled Time -->
                    <div class="mb-3">
                        <label for="scheduled_time" class="form-label small text-muted">Estimated Time (Optional)</label>
                        <input type="time" 
                               class="form-control" 
                               id="scheduled_time" 
                               name="scheduled_time" 
                               value="<?= !empty($req['scheduled_time']) ? date('H:i', strtotime($req['scheduled_time'])) : '' ?>">
                    </div>

                    <!-- Staff Assignment -->
                    <div class="mb-4">
                        <label for="assigned_to" class="form-label small text-muted fw-semibold">Assign Staff Member</label>
                        <select name="assigned_to" id="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($assignableStaff as $as): ?>
                                <option value="<?= (int)$as['id'] ?>" <?= ((int)$req['assigned_to'] === (int)$as['id']) ? 'selected' : '' ?>>
                                    <?= e($as['name']) ?> (<?= e($as['role_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Submit & Cancel -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                        <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$req['id']) ?>" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
