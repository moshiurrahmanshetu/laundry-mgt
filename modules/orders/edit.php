<?php
/**
 * Edit Laundry Order View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    set_flash_message('error', 'Invalid order ID provided.');
    redirect('modules/orders/index.php');
}

$pdo = getDBConnection();

// Fetch order details
$stmt = $pdo->prepare('
    SELECT o.*, c.name AS customer_name, c.phone AS customer_phone
    FROM orders o
    INNER JOIN customers c ON o.customer_id = c.id
    WHERE o.id = :id AND o.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash_message('error', 'The order you are trying to edit does not exist or has been deleted.');
    redirect('modules/orders/index.php');
}

// Restriction: Delivered orders can only be edited by Administrator
if ($order['status'] === 'delivered' && !is_admin()) {
    set_flash_message('error', 'Delivered orders are locked and can only be modified by an Administrator.');
    redirect('modules/orders/show.php?id=' . $orderId);
}

// Fetch active non-deleted customers
$custStmt = $pdo->query('
    SELECT id, name, phone, customer_code
    FROM customers
    WHERE deleted_at IS NULL
    ORDER BY name ASC
');
$customers = $custStmt->fetchAll();

// Fetch active non-deleted services
$svcStmt = $pdo->query('
    SELECT id, name, pricing_type
    FROM services
    WHERE deleted_at IS NULL
    ORDER BY name ASC
');
$services = $svcStmt->fetchAll();

// Fetch existing order items
$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
$itemStmt->execute(['order_id' => $orderId]);
$existingItems = $itemStmt->fetchAll();

$pageTitle = 'Edit Order ' . $order['order_number'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/orders/index.php') ?>">Orders</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>"><?= e($order['order_number']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-0">Edit Order: <span class="font-monospace text-primary"><?= e($order['order_number']) ?></span></h2>
            <span class="text-muted small">Customer: <strong><?= e($order['customer_name']) ?></strong></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i> View Order
            </a>
            <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    </div>
</div>

<form action="<?= base_url('modules/orders/update.php') ?>" method="POST" id="orderEditForm" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

    <div class="row g-4">
        <!-- Left 8 Columns: Customer, Dates, Dynamic Items, Notes -->
        <div class="col-12 col-lg-8">
            <!-- Customer & Schedule Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person-check me-2 text-primary"></i>Customer &amp; Schedule</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Customer -->
                        <div class="col-12 col-md-6">
                            <label for="customer_id" class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select" required>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $order['customer_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?> (<?= e($c['phone']) ?>) [<?= e($c['customer_code']) ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Expected Delivery Date -->
                        <div class="col-12 col-md-3">
                            <label for="expected_date" class="form-label fw-semibold">Delivery Date <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="expected_date" 
                                   name="expected_date" 
                                   value="<?= e($order['expected_date']) ?>" 
                                   required>
                        </div>

                        <!-- Order Intake Date -->
                        <div class="col-12 col-md-3">
                            <label for="order_date" class="form-label fw-semibold">Order Date</label>
                            <input type="datetime-local" 
                                   class="form-control text-muted" 
                                   id="order_date" 
                                   name="order_date" 
                                   value="<?= date('Y-m-d\TH:i', strtotime($order['order_date'])) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Laundry Items Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-basket me-2 text-primary"></i>Ordered Services &amp; Items</h3>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRowBtn">
                        <i class="bi bi-plus-lg me-1"></i> Add Item Row
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="orderItemsTable">
                            <thead class="table-light small">
                                <tr>
                                    <th style="min-width: 170px;">Service Category <span class="text-danger">*</span></th>
                                    <th style="min-width: 180px;">Item / Garment <span class="text-danger">*</span></th>
                                    <th style="width: 110px;">Qty / Weight <span class="text-danger">*</span></th>
                                    <th style="width: 110px;" class="text-end">Unit Price</th>
                                    <th style="width: 120px;" class="text-end">Line Total</th>
                                    <th style="width: 50px;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody id="orderItemsBody">
                                <!-- Dynamic rows rendered by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-2">
                    <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Prices will be authoritatively recalculated upon saving.</span>
                </div>
            </div>

            <!-- Order Notes Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-primary"></i>Order Handling Notes</h3>
                </div>
                <div class="card-body p-3">
                    <textarea class="form-control" 
                              name="notes" 
                              id="notes" 
                              rows="2"><?= e($order['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right 4 Columns: Financial Calculation Summary -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calculator me-2 text-primary"></i>Calculation Summary</h3>
                </div>
                <div class="card-body p-4">
                    <!-- Subtotal Display -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Subtotal</span>
                        <span class="font-monospace fw-semibold fs-5 text-dark" id="displaySubtotal"><?= e(format_price($order['subtotal'])) ?></span>
                    </div>

                    <!-- Discount Input -->
                    <div class="mb-3">
                        <label for="discount" class="form-label small text-muted d-flex justify-content-between">
                            <span>Discount ($)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">$</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control font-monospace text-end" 
                                   id="discount" 
                                   name="discount" 
                                   value="<?= e($order['discount']) ?>">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Total Payable -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-dark fs-6">Grand Total</span>
                        <span class="font-monospace fw-bold fs-4 text-primary" id="displayTotal"><?= e(format_price($order['total'])) ?></span>
                    </div>

                    <!-- Paid Amount -->
                    <div class="mb-3">
                        <label for="paid_amount" class="form-label small text-muted d-flex justify-content-between">
                            <span>Paid Amount ($)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">$</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control font-monospace text-end" 
                                   id="paid_amount" 
                                   name="paid_amount" 
                                   value="<?= e($order['paid_amount']) ?>">
                        </div>
                    </div>

                    <!-- Due Amount -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Due Balance</span>
                        <span class="font-monospace fw-bold text-danger fs-5" id="displayDue"><?= e(format_price($order['due_amount'])) ?></span>
                    </div>

                    <!-- Order Lifecycle Status Select -->
                    <div class="mb-4">
                        <label for="status" class="form-label small text-muted fw-semibold">Order Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="received" <?= $order['status'] === 'received' ? 'selected' : '' ?>>Received</option>
                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready for Pickup</option>
                            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <!-- Submit & Cancel -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
                        </button>
                        <a href="<?= base_url('modules/orders/show.php?id=' . (int)$order['id']) ?>" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$servicesJson = json_encode($services, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$existingItemsJson = json_encode($existingItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$getServiceItemsUrl = base_url('modules/orders/get_service_items.php');

$extraScripts = '
<script>
const SERVICES_DATA = ' . $servicesJson . ';
const EXISTING_ITEMS = ' . $existingItemsJson . ';
const GET_ITEMS_URL = "' . $getServiceItemsUrl . '";

document.addEventListener("DOMContentLoaded", function () {
    const orderItemsBody = document.getElementById("orderItemsBody");
    const addItemRowBtn = document.getElementById("addItemRowBtn");
    const discountInput = document.getElementById("discount");
    const paidInput = document.getElementById("paid_amount");

    const displaySubtotal = document.getElementById("displaySubtotal");
    const displayTotal = document.getElementById("displayTotal");
    const displayDue = document.getElementById("displayDue");

    let rowCounter = 0;

    function createItemRow(existing = null) {
        rowCounter++;
        const rowId = rowCounter;
        const tr = document.createElement("tr");
        tr.className = "order-item-row";
        tr.id = `row_${rowId}`;

        let serviceOptions = `<option value="">-- Choose Service --</option>`;
        SERVICES_DATA.forEach(svc => {
            const isSelected = existing && existing.service_id == svc.id ? "selected" : "";
            serviceOptions += `<option value="${svc.id}" data-type="${svc.pricing_type}" ${isSelected}>${svc.name}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowId}][service_id]" class="form-select form-select-sm service-select" required>
                    ${serviceOptions}
                </select>
            </td>
            <td>
                <select name="items[${rowId}][service_item_id]" class="form-select form-select-sm item-select" required>
                    <option value="">${existing ? existing.item_name : "-- Select Service First --"}</option>
                </select>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="any" min="0.01" name="items[${rowId}][quantity]" class="form-control form-control-sm text-center qty-input" value="${existing ? parseFloat(existing.quantity) : 1}" required>
                    <span class="input-group-text unit-badge bg-light small">item</span>
                </div>
            </td>
            <td class="text-end">
                <span class="font-monospace unit-price-display text-muted small">$${existing ? parseFloat(existing.unit_price).toFixed(2) : "0.00"}</span>
                <input type="hidden" class="unit-price-val" value="${existing ? parseFloat(existing.unit_price) : 0}">
            </td>
            <td class="text-end">
                <span class="font-monospace fw-bold line-total-display text-dark">$${existing ? parseFloat(existing.line_total).toFixed(2) : "0.00"}</span>
                <input type="hidden" class="line-total-val" value="${existing ? parseFloat(existing.line_total) : 0}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-remove-item" title="Remove row">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        `;

        orderItemsBody.appendChild(tr);

        const serviceSelect = tr.querySelector(".service-select");
        const itemSelect = tr.querySelector(".item-select");
        const qtyInput = tr.querySelector(".qty-input");
        const unitBadge = tr.querySelector(".unit-badge");
        const priceDisplay = tr.querySelector(".unit-price-display");
        const priceVal = tr.querySelector(".unit-price-val");
        const removeBtn = tr.querySelector(".btn-remove-item");

        function loadItemsForService(svcId, selectedItemId = null) {
            if (!svcId) {
                itemSelect.innerHTML = `<option value="">-- Select Service First --</option>`;
                itemSelect.disabled = true;
                return;
            }

            fetch(`${GET_ITEMS_URL}?service_id=${svcId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.items.length > 0) {
                        itemSelect.disabled = false;
                        itemSelect.innerHTML = `<option value="">-- Select Garment/Item --</option>`;
                        
                        data.items.forEach(it => {
                            const opt = document.createElement("option");
                            opt.value = it.id;
                            opt.textContent = `${it.item_name} (${it.formatted_price})`;
                            opt.setAttribute("data-price", it.price);
                            opt.setAttribute("data-unit", it.unit);
                            if (selectedItemId && selectedItemId == it.id) {
                                opt.selected = true;
                                priceVal.value = it.price;
                                priceDisplay.textContent = `$${parseFloat(it.price).toFixed(2)}`;
                                unitBadge.textContent = it.unit;
                            }
                            itemSelect.appendChild(opt);
                        });

                        if (!selectedItemId && data.items.length === 1) {
                            itemSelect.selectedIndex = 1;
                            itemSelect.dispatchEvent(new Event("change"));
                        }
                    }
                });
        }

        serviceSelect.addEventListener("change", function () {
            loadItemsForService(this.value);
        });

        itemSelect.addEventListener("change", function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                const price = parseFloat(selectedOpt.getAttribute("data-price") || 0);
                const unit = selectedOpt.getAttribute("data-unit") || "item";
                priceVal.value = price;
                priceDisplay.textContent = `$${price.toFixed(2)}`;
                unitBadge.textContent = unit;
            }
            calculateRow(tr);
        });

        qtyInput.addEventListener("input", function () {
            calculateRow(tr);
        });

        removeBtn.addEventListener("click", function () {
            if (document.querySelectorAll(".order-item-row").length > 1) {
                tr.remove();
                recalculateGrandTotals();
            }
        });

        if (existing && existing.service_id) {
            loadItemsForService(existing.service_id, existing.service_item_id);
        }
    }

    function calculateRow(tr) {
        const qtyInput = tr.querySelector(".qty-input");
        const priceVal = tr.querySelector(".unit-price-val");
        const lineTotalDisplay = tr.querySelector(".line-total-display");
        const lineTotalVal = tr.querySelector(".line-total-val");

        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceVal.value) || 0;
        const total = qty * price;

        lineTotalVal.value = total;
        lineTotalDisplay.textContent = `$${total.toFixed(2)}`;

        recalculateGrandTotals();
    }

    function recalculateGrandTotals() {
        let subtotal = 0;
        document.querySelectorAll(".line-total-val").forEach(el => {
            subtotal += parseFloat(el.value) || 0;
        });

        let discount = parseFloat(discountInput.value) || 0;
        if (discount < 0) discount = 0;
        if (discount > subtotal) discount = subtotal;

        const total = Math.max(0, subtotal - discount);

        let paid = parseFloat(paidInput.value) || 0;
        if (paid < 0) paid = 0;
        if (paid > total) paid = total;

        const due = Math.max(0, total - paid);

        displaySubtotal.textContent = `$${subtotal.toFixed(2)}`;
        displayTotal.textContent = `$${total.toFixed(2)}`;
        displayDue.textContent = `$${due.toFixed(2)}`;
    }

    if (addItemRowBtn) {
        addItemRowBtn.addEventListener("click", () => createItemRow());
    }

    if (discountInput) discountInput.addEventListener("input", recalculateGrandTotals);
    if (paidInput) paidInput.addEventListener("input", recalculateGrandTotals);

    if (EXISTING_ITEMS && EXISTING_ITEMS.length > 0) {
        EXISTING_ITEMS.forEach(it => createItemRow(it));
    } else {
        createItemRow();
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
