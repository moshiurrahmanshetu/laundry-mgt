<?php
/**
 * Laundry Operations Order Profile & Visual Timeline View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    set_flash_message('error', 'Invalid order ID provided.');
    redirect('modules/operations/index.php');
}

$pdo = getDBConnection();

// Fetch order details with customer and creator join
$stmt = $pdo->prepare('
    SELECT o.*, 
           c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           c.address AS customer_address, c.city AS customer_city, c.customer_code,
           u.name AS creator_name
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON o.created_by = u.id
    WHERE o.id = :id AND o.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash_message('error', 'The requested order does not exist or has been deleted.');
    redirect('modules/operations/index.php');
}

// Fetch order line items
$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
$itemStmt->execute(['order_id' => $orderId]);
$items = $itemStmt->fetchAll();

// Fetch payment transactions
$paymentsStmt = $pdo->prepare('
    SELECT p.*, u.name AS receiver_name
    FROM payments p
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.order_id = :order_id AND p.deleted_at IS NULL
    ORDER BY p.id ASC
');
$paymentsStmt->execute(['order_id' => $orderId]);
$orderPayments = $paymentsStmt->fetchAll();

// Fetch pickup & delivery records
$deliveryStmt = $pdo->prepare('
    SELECT pd.*, u.name AS assigned_staff_name
    FROM pickup_deliveries pd
    LEFT JOIN users u ON pd.assigned_to = u.id
    WHERE pd.order_id = :order_id AND pd.deleted_at IS NULL
    ORDER BY pd.scheduled_date ASC, pd.id ASC
');
$deliveryStmt->execute(['order_id' => $orderId]);
$orderDeliveries = $deliveryStmt->fetchAll();

// Fetch activity log audit history for this order
$logStmt = $pdo->prepare('
    SELECT al.*, u.name AS actor_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.description LIKE :order_match
    ORDER BY al.id DESC
    LIMIT 10
');
$logStmt->execute(['order_match' => '%' . $order['order_number'] . '%']);
$statusLogs = $logStmt->fetchAll();

$pageTitle = 'Operations ' . $order['order_number'];

// Timeline Steps Setup
$timelineSteps = [
    'received'   => ['label' => 'Received', 'icon' => 'bi-inbox-fill'],
    'processing' => ['label' => 'Processing', 'icon' => 'bi-gear-fill'],
    'ready'      => ['label' => 'Ready for Pickup', 'icon' => 'bi-check2-circle'],
    'delivered'  => ['label' => 'Delivered', 'icon' => 'bi-bag-check-fill']
];

$stepKeys = array_keys($timelineSteps);
$currentStepIndex = array_search($order['status'], $stepKeys, true);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Top Bar -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/operations/index.php') ?>">Operations</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($order['order_number']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h2 class="h4 fw-bold text-dark mb-0 font-monospace"><?= e($order['order_number']) ?></h2>
            <?= order_status_badge($order['status']) ?>
            <?= payment_status_badge($order['payment_status']) ?>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2">
            <!-- Contextual Next Step Button -->
            <?php if ($order['status'] === 'received'): ?>
                <form action="<?= base_url('modules/operations/update_status.php') ?>" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                    <input type="hidden" name="status" value="processing">
                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                        <i class="bi bi-gear-fill me-1"></i> Start Washing / Processing
                    </button>
                </form>
            <?php elseif ($order['status'] === 'processing'): ?>
                <form action="<?= base_url('modules/operations/update_status.php') ?>" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                    <input type="hidden" name="status" value="ready">
                    <button type="submit" class="btn btn-success btn-sm fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Mark Ready for Pickup
                    </button>
                </form>
            <?php elseif ($order['status'] === 'ready'): ?>
                <form action="<?= base_url('modules/operations/update_status.php') ?>" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                    <input type="hidden" name="status" value="delivered">
                    <button type="submit" class="btn btn-dark btn-sm fw-semibold">
                        <i class="bi bi-bag-check me-1"></i> Mark Delivered
                    </button>
                </form>
            <?php endif; ?>

            <!-- Change Stage Modal -->
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changeStageModal">
                <i class="bi bi-arrow-repeat me-1"></i> Change Stage
            </button>

            <!-- Print Work Order -->
            <a href="<?= base_url('modules/operations/print.php?id=' . (int)$order['id']) ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Work Order
            </a>

            <!-- View Full Order in Orders Module -->
            <a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-receipt me-1"></i> Order Profile
            </a>

            <!-- Back to Operations -->
            <a href="<?= base_url('modules/operations/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<!-- Visual Status Timeline Card -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white py-3">
        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-bezier2 me-2 text-primary"></i>Operational Workflow Stage</h3>
    </div>
    <div class="card-body p-4">
        <?php if ($order['status'] === 'cancelled'): ?>
            <div class="alert alert-danger d-flex align-items-center mb-0">
                <i class="bi bi-x-circle-fill fs-4 me-2"></i>
                <div>
                    <strong>Order Cancelled:</strong> This order was cancelled and is no longer moving through active laundry stages.
                </div>
            </div>
        <?php else: ?>
            <div class="row g-2 text-center align-items-center">
                <?php foreach ($timelineSteps as $stepKey => $stepMeta): 
                    $stepIdx = array_search($stepKey, $stepKeys, true);
                    $isPassed = $currentStepIndex !== false && $stepIdx < $currentStepIndex;
                    $isCurrent = $currentStepIndex !== false && $stepIdx === $currentStepIndex;
                ?>
                    <div class="col-12 col-md-3">
                        <div class="p-3 rounded border <?= $isCurrent ? 'bg-primary text-white border-primary shadow-sm' : ($isPassed ? 'bg-success-subtle text-success border-success-subtle' : 'bg-light text-muted border-secondary-subtle') ?>">
                            <i class="bi <?= $stepMeta['icon'] ?> fs-3 d-block mb-1"></i>
                            <div class="fw-bold <?= $isCurrent ? 'text-white' : 'text-dark' ?> small"><?= e($stepMeta['label']) ?></div>
                            <div style="font-size: 0.7rem;" class="<?= $isCurrent ? 'text-white-50' : 'text-muted' ?>">
                                <?= $isCurrent ? 'CURRENT STAGE' : ($isPassed ? 'COMPLETED' : 'UPCOMING') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Customer Details, Checklist & Payment Breakdown -->
    <div class="col-12 col-lg-8">
        <!-- Ordered Laundry Garments Checklist Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-list-check me-2 text-primary"></i>Garment Processing Checklist</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= count($items) ?> item(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3" style="width: 40px;">#</th>
                                <th>Service Category</th>
                                <th>Item / Garment Description</th>
                                <th class="text-center" style="width: 110px;">Qty / Weight</th>
                                <th class="text-end pe-3" style="width: 120px;">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $it): ?>
                                <tr>
                                    <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($it['service_name']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($it['item_name']) ?></div>
                                        <?php if (!empty($it['notes'])): ?>
                                            <div class="small text-muted fst-italic"><?= e($it['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center font-monospace">
                                        <?= (float)$it['quantity'] == (int)$it['quantity'] ? (int)$it['quantity'] : number_format((float)$it['quantity'], 2) ?>
                                    </td>
                                    <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                        <?= e(format_price($it['line_total'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pickup & Delivery Dispatch Schedules Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-truck me-2 text-primary"></i>Dispatch &amp; Logistics Tracking</h3>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('modules/delivery/create.php?order_id=' . (int)$order['id'] . '&type=delivery') ?>" class="btn btn-outline-primary btn-sm py-0 px-2">
                        <i class="bi bi-plus-lg me-1"></i> Schedule Delivery
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orderDeliveries)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-geo-alt fs-2 d-block mb-1 text-secondary"></i>
                        <p class="small mb-0">No pickup or delivery requests scheduled for this order.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th class="ps-3">Reference #</th>
                                    <th>Type</th>
                                    <th>Scheduled Date</th>
                                    <th>Assigned Staff</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderDeliveries as $od): ?>
                                    <tr>
                                        <td class="ps-3 font-monospace fw-semibold">
                                            <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$od['id']) ?>" class="text-decoration-none">
                                                <?= e($od['reference_number']) ?>
                                            </a>
                                        </td>
                                        <td><?= delivery_type_badge($od['type']) ?></td>
                                        <td class="small text-dark">
                                            <?= e(format_datetime($od['scheduled_date'], 'M d, Y')) ?>
                                            <?php if (!empty($od['scheduled_time'])): ?>
                                                <span class="text-muted font-monospace">(<?= date('h:i A', strtotime($od['scheduled_time'])) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-dark"><?= e($od['assigned_staff_name'] ?: 'Unassigned') ?></td>
                                        <td><?= delivery_status_badge($od['status']) ?></td>
                                        <td class="text-end pe-3">
                                            <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$od['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Operational Status Change Audit Trail -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>Operational Status History &amp; Audit Log</h3>
            </div>
            <div class="card-body p-0">
                <?php if (empty($statusLogs)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-journal-text fs-2 d-block mb-1 text-secondary"></i>
                        <p class="small mb-0">No recorded status change events found for this order.</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($statusLogs as $log): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <span class="badge bg-light text-dark border me-2 font-monospace"><?= e($log['action']) ?></span>
                                    <span class="text-dark"><?= e($log['description']) ?></span>
                                </div>
                                <div class="text-muted text-end" style="min-width: 140px;">
                                    <div><?= e(format_datetime($log['created_at'])) ?></div>
                                    <small class="text-muted"><?= e($log['actor_name'] ?: 'System') ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Customer Details, Schedule & Financial Summary -->
    <div class="col-12 col-lg-4">
        <!-- Customer Details Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Customer Information</h3>
                <a href="<?= base_url('modules/customers/show.php?id=' . (int)$order['customer_id']) ?>" class="small text-decoration-none">
                    View Profile <i class="bi bi-arrow-up-right"></i>
                </a>
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle fw-bold fs-4" style="width: 48px; height: 48px;">
                        <?= e(get_user_initials($order['customer_name'])) ?>
                    </div>
                    <div>
                        <h4 class="h6 fw-bold text-dark mb-0"><?= e($order['customer_name']) ?></h4>
                        <span class="badge bg-light text-secondary border font-monospace"><?= e($order['customer_code']) ?></span>
                    </div>
                </div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Phone Number</span>
                        <span class="font-monospace fw-semibold text-dark"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></span>
                    </li>
                    <li class="list-group-item px-0 py-2">
                        <span class="text-muted d-block mb-1">Service Address</span>
                        <span class="text-dark"><?= e($order['customer_address'] ?: 'No address on file') ?><?= !empty($order['customer_city']) ? ', ' . e($order['customer_city']) : '' ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Schedule & Metadata Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i>Schedule Tracking</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Intake Date</span>
                        <span class="fw-semibold"><?= e(format_datetime($order['order_date'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Expected Delivery</span>
                        <span class="fw-bold <?= strtotime($order['expected_date']) < strtotime('today') && $order['status'] !== 'delivered' ? 'text-danger' : 'text-dark' ?>">
                            <?= e(format_datetime($order['expected_date'], 'M d, Y')) ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Order Intake By</span>
                        <span class="fw-semibold text-dark"><?= e($order['creator_name'] ?: 'Staff') ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Financial Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calculator me-2 text-primary"></i>Account Balance</h3>
                <?= payment_status_badge($order['payment_status']) ?>
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Order Total:</span>
                    <span class="font-monospace fw-bold fs-6 text-dark"><?= e(format_price($order['total'])) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Total Paid to Date:</span>
                    <span class="font-monospace text-success fw-semibold"><?= e(format_price($order['paid_amount'])) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Remaining Balance:</span>
                    <span class="font-monospace <?= (float)$order['due_amount'] > 0 ? 'text-danger fw-bold fs-6' : 'text-muted' ?>">
                        <?= e(format_price($order['due_amount'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Handling Notes -->
        <?php if (!empty($order['notes'])): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-chat-left-text me-2 text-primary"></i>Handling Notes</h3>
                </div>
                <div class="card-body p-3">
                    <p class="text-dark mb-0 small" style="white-space: pre-line;"><?= e($order['notes']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Change Stage Modal -->
<div class="modal fade" id="changeStageModal" tabindex="-1" aria-labelledby="changeStageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/operations/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="changeStageModalLabel">Change Order Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Order: <strong class="text-dark font-monospace"><?= e($order['order_number']) ?></strong></p>
                    <label for="modal_stage_select" class="form-label small text-muted">Select Workflow Stage</label>
                    <select name="status" id="modal_stage_select" class="form-select">
                        <option value="received" <?= $order['status'] === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready for Pickup</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
