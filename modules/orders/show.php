<?php
/**
 * Laundry Order Profile / Details View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    set_flash_message('error', 'Invalid order ID provided.');
    redirect('modules/orders/index.php');
}

$pdo = getDBConnection();
$canDelete = is_admin() || is_manager();

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
    set_flash_message('error', 'The requested laundry order does not exist or has been deleted.');
    redirect('modules/orders/index.php');
}

// Fetch order line items
$itemStmt = $pdo->prepare('
    SELECT *
    FROM order_items
    WHERE order_id = :order_id
    ORDER BY id ASC
');
$itemStmt->execute(['order_id' => $orderId]);
$items = $itemStmt->fetchAll();

$pageTitle = 'Order ' . $order['order_number'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/orders/index.php') ?>">Orders</a></li>
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
            <!-- Print Receipt -->
            <a href="<?= base_url('modules/orders/print.php?id=' . (int)$order['id']) ?>" 
               target="_blank" 
               class="btn btn-outline-dark btn-sm">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </a>

            <!-- Update Status -->
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                <i class="bi bi-arrow-repeat me-1"></i> Update Status
            </button>

            <!-- Edit Order -->
            <a href="<?= base_url('modules/orders/edit.php?id=' . (int)$order['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Order
            </a>

            <!-- Delete Button (Admin & Manager Only) -->
            <?php if ($canDelete): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteOrderModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            <?php endif; ?>

            <!-- Back to List -->
            <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Customer & Schedule Summary -->
    <div class="col-12 col-lg-4">
        <!-- Customer Info Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Customer Information</h3>
                <a href="<?= base_url('modules/customers/show.php?id=' . (int)$order['customer_id']) ?>" class="small text-decoration-none">
                    View Profile <i class="bi bi-arrow-up-right"></i>
                </a>
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle fw-bold fs-4" style="width: 50px; height: 50px;">
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
                    <li class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">Email</span>
                        <span class="text-truncate" style="max-width: 170px;"><?= e($order['customer_email'] ?: '—') ?></span>
                    </li>
                    <li class="list-group-item px-0 py-2">
                        <span class="text-muted d-block mb-1">Delivery Address</span>
                        <span class="text-dark"><?= e($order['customer_address'] ?: 'No address on file') ?><?= !empty($order['customer_city']) ? ', ' . e($order['customer_city']) : '' ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Schedule & Metadata Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i>Schedule &amp; Tracking</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Intake Date &amp; Time</span>
                        <span class="fw-semibold"><?= e(format_datetime($order['order_date'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Expected Delivery</span>
                        <span class="fw-bold <?= strtotime($order['expected_date']) < strtotime('today') && $order['status'] !== 'delivered' ? 'text-danger' : 'text-dark' ?>">
                            <?= e(format_datetime($order['expected_date'], 'M d, Y')) ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Processed By</span>
                        <span class="fw-semibold text-dark"><?= e($order['creator_name'] ?: 'System / Unknown') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-2 px-3">
                        <span class="text-muted">Last System Update</span>
                        <span class="fw-semibold"><?= e(format_datetime($order['updated_at'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Order Items, Financial Summary & Notes -->
    <div class="col-12 col-lg-8">
        <!-- Order Items Table Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-basket3 me-2 text-primary"></i>Ordered Laundry Services &amp; Garments</h3>
                <span class="badge bg-light text-dark border font-monospace"><?= count($items) ?> item(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3" style="width: 40px;">#</th>
                                <th>Service Category</th>
                                <th>Garment / Item</th>
                                <th class="text-center" style="width: 100px;">Qty / Weight</th>
                                <th class="text-end" style="width: 110px;">Unit Rate</th>
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
                                    <td class="text-end font-monospace text-muted small">
                                        <?= e(format_price($it['unit_price'])) ?>
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

        <!-- Financial Calculation Summary Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card me-2 text-primary"></i>Billing &amp; Payment Summary</h3>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6 border-end-md">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Subtotal (Items Total):</span>
                            <span class="font-monospace fw-semibold"><?= e(format_price($order['subtotal'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Discount Applied:</span>
                            <span class="font-monospace text-success">- <?= e(format_price($order['discount'])) ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-dark fs-6">Grand Total:</span>
                            <span class="font-monospace fw-bold text-primary fs-5"><?= e(format_price($order['total'])) ?></span>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 ps-md-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Paid Amount:</span>
                            <span class="font-monospace text-success fw-bold"><?= e(format_price($order['paid_amount'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Due Balance:</span>
                            <span class="font-monospace <?= (float)$order['due_amount'] > 0 ? 'text-danger fw-bold fs-6' : 'text-muted' ?>">
                                <?= e(format_price($order['due_amount'])) ?>
                            </span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Payment Status:</span>
                            <?= payment_status_badge($order['payment_status']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Notes Card -->
        <?php if (!empty($order['notes'])): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-chat-left-text me-2 text-primary"></i>Handling Notes</h3>
                </div>
                <div class="card-body p-4">
                    <p class="text-dark mb-0" style="white-space: pre-line;"><?= e($order['notes']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Update Order Status -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/orders/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="updateStatusModalLabel">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Order: <strong class="text-dark font-monospace"><?= e($order['order_number']) ?></strong></p>
                    <label for="status_select" class="form-label small text-muted">Select Lifecycle Stage</label>
                    <select name="status" id="status_select" class="form-select">
                        <option value="received" <?= $order['status'] === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready for Pickup</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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

<!-- Modal: Soft Delete Confirmation (Admin & Manager Only) -->
<?php if ($canDelete): ?>
<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/orders/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="deleteOrderModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Laundry Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete order <strong class="font-monospace"><?= e($order['order_number']) ?></strong> for customer <strong><?= e($order['customer_name']) ?></strong>?</p>
                    <p class="small text-muted mb-0">This order will be soft-deleted. Historical order items remain safely recorded in the database.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Order
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
