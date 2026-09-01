<?php
/**
 * Create Pickup / Delivery Request View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'New Pickup / Delivery Request';
$pdo = getDBConnection();

$preselectedOrderId = (int)($_GET['order_id'] ?? 0);
$preselectedType    = sanitize_input($_GET['type'] ?? 'delivery');
if (!in_array($preselectedType, ['pickup', 'delivery'], true)) {
    $preselectedType = 'delivery';
}

$canAssign = is_admin() || is_manager();

// Fetch active orders
$ordersStmt = $pdo->query('
    SELECT o.id, o.order_number, o.customer_id, o.order_date, o.expected_date,
           c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
           c.city AS customer_city, c.customer_code
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE o.status != "cancelled" AND o.deleted_at IS NULL
    ORDER BY o.id DESC
');
$orders = $ordersStmt->fetchAll();

// Fetch assignable staff
$staffStmt = $pdo->query('
    SELECT u.id, u.name, r.name AS role_name
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.status = "active"
    ORDER BY u.name ASC
');
$assignableStaff = $staffStmt->fetchAll();

$defaultDate = date('Y-m-d');
$defaultTime = date('H:i');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/delivery/index.php') ?>">Pickup &amp; Delivery</a></li>
            <li class="breadcrumb-item active" aria-current="page">New Request</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 fw-bold text-dark mb-0">Schedule Pickup / Delivery</h2>
        <a href="<?= base_url('modules/delivery/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between shadow-sm">
        <div>
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <strong>No active orders available.</strong> Create a customer laundry order before scheduling a pickup or delivery.
        </div>
        <a href="<?= base_url('modules/orders/create.php') ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-plus-lg me-1"></i> Create Order
        </a>
    </div>
<?php else: ?>

<form action="<?= base_url('modules/delivery/store.php') ?>" method="POST" id="deliveryForm" autocomplete="off">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left 8 Columns: Request Type, Order, Schedule & Address -->
        <div class="col-12 col-lg-8">
            <!-- Request Type & Order Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-card-checklist me-2 text-primary"></i>Request Details</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Request Type Selection -->
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Request Type <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="type" id="type_pickup" value="pickup" <?= $preselectedType === 'pickup' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-info py-2 fw-semibold" for="type_pickup">
                                    <i class="bi bi-box-arrow-in-down-left me-2"></i>Laundry Pickup
                                </label>

                                <input type="radio" class="btn-check" name="type" id="type_delivery" value="delivery" <?= $preselectedType === 'delivery' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary py-2 fw-semibold" for="type_delivery">
                                    <i class="bi bi-truck me-2"></i>Laundry Delivery
                                </label>
                            </div>
                        </div>

                        <!-- Order Selection -->
                        <div class="col-12">
                            <label for="order_id" class="form-label fw-semibold">Select Order <span class="text-danger">*</span></label>
                            <select name="order_id" id="order_id" class="form-select" required autofocus>
                                <option value="">-- Choose Order --</option>
                                <?php foreach ($orders as $ord): ?>
                                    <option value="<?= (int)$ord['id'] ?>"
                                            data-customer-name="<?= e($ord['customer_name']) ?>"
                                            data-customer-phone="<?= e($ord['customer_phone']) ?>"
                                            data-customer-address="<?= e($ord['customer_address']) ?><?= !empty($ord['customer_city']) ? ', ' . e($ord['customer_city']) : '' ?>"
                                            data-expected-date="<?= e($ord['expected_date']) ?>"
                                            <?= ($preselectedOrderId === (int)$ord['id']) ? 'selected' : '' ?>>
                                        <?= e($ord['order_number']) ?> — <?= e($ord['customer_name']) ?> (<?= e($ord['customer_phone']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Customer details and default address will be automatically filled from the selected order.</div>
                        </div>

                        <!-- Contact Name -->
                        <div class="col-12 col-md-6">
                            <label for="contact_name" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="contact_name" 
                                   name="contact_name" 
                                   placeholder="Full Name" 
                                   required>
                        </div>

                        <!-- Contact Phone -->
                        <div class="col-12 col-md-6">
                            <label for="contact_phone" class="form-label fw-semibold">Contact Phone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="text" 
                                       class="form-control font-monospace" 
                                       id="contact_phone" 
                                       name="contact_phone" 
                                       placeholder="+8801700000000" 
                                       required>
                            </div>
                        </div>

                        <!-- Snapshot Address -->
                        <div class="col-12">
                            <label for="address" class="form-label fw-semibold">Pickup / Delivery Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="2" 
                                      placeholder="Full street address, apartment / flat number, landmarks..." 
                                      required></textarea>
                            <div class="form-text small">This address will be saved as a permanent snapshot for this request.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-primary"></i>Handling &amp; Driver Instructions</h3>
                </div>
                <div class="card-body p-3">
                    <textarea class="form-control" 
                              name="notes" 
                              id="notes" 
                              rows="2" 
                              placeholder="e.g. Ring bell 4B, call before arriving, deliver to security guard..."></textarea>
                </div>
            </div>
        </div>

        <!-- Right 4 Columns: Schedule & Staff Assignment -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>Schedule &amp; Dispatch</h3>
                </div>
                <div class="card-body p-4">
                    <!-- Scheduled Date -->
                    <div class="mb-3">
                        <label for="scheduled_date" class="form-label fw-semibold">Scheduled Date <span class="text-danger">*</span></label>
                        <input type="date" 
                               class="form-control" 
                               id="scheduled_date" 
                               name="scheduled_date" 
                               value="<?= e($defaultDate) ?>" 
                               required>
                    </div>

                    <!-- Scheduled Time -->
                    <div class="mb-3">
                        <label for="scheduled_time" class="form-label small text-muted">Estimated Time (Optional)</label>
                        <input type="time" 
                               class="form-control" 
                               id="scheduled_time" 
                               name="scheduled_time" 
                               value="<?= e($defaultTime) ?>">
                    </div>

                    <!-- Staff Assignment (Admin & Manager) -->
                    <?php if ($canAssign): ?>
                        <div class="mb-4">
                            <label for="assigned_to" class="form-label small text-muted fw-semibold">Assign Staff Member</label>
                            <select name="assigned_to" id="assigned_to" class="form-select">
                                <option value="">-- Unassigned (Pending) --</option>
                                <?php foreach ($assignableStaff as $as): ?>
                                    <option value="<?= (int)$as['id'] ?>">
                                        <?= e($as['name']) ?> (<?= e($as['role_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Assigning staff will set request status to <strong>Assigned</strong>.</div>
                        </div>
                    <?php endif; ?>

                    <!-- Submit & Cancel -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Schedule Request
                        </button>
                        <a href="<?= base_url('modules/delivery/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$ordersJson = json_encode($orders, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$extraScripts = '
<script>
const ORDERS_DATA = ' . ($ordersJson ?: '[]') . ';

document.addEventListener("DOMContentLoaded", function () {
    const orderSelect = document.getElementById("order_id");
    const contactName = document.getElementById("contact_name");
    const contactPhone = document.getElementById("contact_phone");
    const addressInput = document.getElementById("address");
    const scheduledDate = document.getElementById("scheduled_date");

    function updateFromSelectedOrder() {
        if (!orderSelect) return;
        const selectedId = parseInt(orderSelect.value, 10);
        const order = ORDERS_DATA.find(o => parseInt(o.id, 10) === selectedId);

        if (order) {
            contactName.value = order.customer_name || "";
            contactPhone.value = order.customer_phone || "";
            let fullAddress = order.customer_address || "";
            if (order.customer_city && !fullAddress.includes(order.customer_city)) {
                fullAddress += (fullAddress ? ", " : "") + order.customer_city;
            }
            addressInput.value = fullAddress;

            if (order.expected_date) {
                // If delivery, prefill scheduled date with order expected date
                const isDelivery = document.getElementById("type_delivery").checked;
                if (isDelivery && order.expected_date) {
                    scheduledDate.value = order.expected_date;
                }
            }
        }
    }

    orderSelect.addEventListener("change", updateFromSelectedOrder);

    // If preselected order, trigger update
    if (orderSelect.value) {
        updateFromSelectedOrder();
    }
});
</script>
';
?>

<?php endif; ?>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
