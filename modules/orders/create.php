<?php
/**
 * Create New Laundry Order View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'New Laundry Order';
$pdo = getDBConnection();

// Pre-selected customer if passed from Customer Profile
$preselectedCustomerId = (int)($_GET['customer_id'] ?? 0);

// Fetch active non-deleted customers
$custStmt = $pdo->query('
    SELECT id, name, phone, customer_code
    FROM customers
    WHERE status = "active" AND deleted_at IS NULL
    ORDER BY name ASC
');
$customers = $custStmt->fetchAll();

// Fetch active non-deleted services
$svcStmt = $pdo->query('
    SELECT id, name, pricing_type
    FROM services
    WHERE status = "active" AND deleted_at IS NULL
    ORDER BY name ASC
');
$services = $svcStmt->fetchAll();

$old = $_SESSION['old_order_input'] ?? [];
unset($_SESSION['old_order_input']);

$defaultOrderDate = date('Y-m-d\TH:i');
$defaultExpectedDate = date('Y-m-d', strtotime('+2 days'));

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/orders/index.php') ?>">Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">New Order</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 fw-bold text-dark mb-0">Create Laundry Order</h2>
        <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Orders
        </a>
    </div>
</div>

<?php if (empty($customers)): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between">
        <div>
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <strong>No active customers found!</strong> Please register an active customer before creating laundry orders.
        </div>
        <a href="<?= base_url('modules/customers/create.php') ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-person-plus me-1"></i> Add Customer
        </a>
    </div>
<?php endif; ?>

<?php if (empty($services)): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between">
        <div>
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <strong>No active laundry services found!</strong> Please configure services and item pricing first.
        </div>
        <?php if (is_admin() || is_manager()): ?>
            <a href="<?= base_url('modules/services/create.php') ?>" class="btn btn-warning btn-sm">
                <i class="bi bi-tag me-1"></i> Add Service
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form action="<?= base_url('modules/orders/store.php') ?>" method="POST" id="orderForm" autocomplete="off">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left 8 Columns: Customer, Dates, Dynamic Laundry Items, Notes -->
        <div class="col-12 col-lg-8">
            <!-- Customer & Dates Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person-check me-2 text-primary"></i>Customer &amp; Schedule</h3>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <!-- Select Customer -->
                        <div class="col-12 col-md-6">
                            <label for="customer_id" class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select" required autofocus>
                                <option value="">-- Choose Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= ($old['customer_id'] ?? $preselectedCustomerId) == $c['id'] ? 'selected' : '' ?>>
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
                                   value="<?= e($old['expected_date'] ?? $defaultExpectedDate) ?>" 
                                   required>
                        </div>

                        <!-- Order Intake Date -->
                        <div class="col-12 col-md-3">
                            <label for="order_date" class="form-label fw-semibold">Intake Date &amp; Time</label>
                            <input type="datetime-local" 
                                   class="form-control text-muted" 
                                   id="order_date" 
                                   name="order_date" 
                                   value="<?= e($old['order_date'] ?? $defaultOrderDate) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Laundry Items Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-basket me-2 text-primary"></i>Laundry Services &amp; Items</h3>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRowBtn">
                        <i class="bi bi-plus-lg me-1"></i> Add Garment / Item
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="orderItemsTable">
                            <thead class="table-light small">
                                <tr>
                                    <th style="min-width: 170px;">Service Category <span class="text-danger">*</span></th>
                                    <th style="min-width: 180px;">Item / Garment <span class="text-danger">*</span></th>
                                    <th style="width: 130px;">Qty / KG <span class="text-danger">*</span></th>
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
                    <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Select a service category and garment to auto-populate unit rates. Enter quantity/weight to recalculate.</span>
                </div>
            </div>

            <!-- Order Notes Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-primary"></i>Order Notes &amp; Special Handling Instructions</h3>
                </div>
                <div class="card-body p-3">
                    <textarea class="form-control" 
                              name="notes" 
                              id="notes" 
                              rows="2" 
                              placeholder="e.g. Special stain removal on white shirt, deliver before 5 PM, starch collar..."><?= e($old['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right 4 Columns: Financial Calculation Summary & Submit Button -->
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 80px;">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-calculator me-2 text-primary"></i>Payment &amp; Calculation Summary</h3>
                </div>
                <div class="card-body p-4">
                    <!-- Subtotal Display -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Subtotal</span>
                        <span class="font-monospace fw-semibold fs-5 text-dark" id="displaySubtotal">$0.00</span>
                    </div>

                    <!-- Discount Input -->
                    <div class="mb-3">
                        <label for="discount" class="form-label small text-muted d-flex justify-content-between">
                            <span>Discount ($)</span>
                            <span class="small text-secondary">Optional</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">$</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control font-monospace text-end" 
                                   id="discount" 
                                   name="discount" 
                                   value="<?= e($old['discount'] ?? '0.00') ?>" 
                                   placeholder="0.00">
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Total Payable -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold text-dark fs-6">Total Amount</span>
                        <span class="font-monospace fw-bold fs-4 text-primary" id="displayTotal">$0.00</span>
                    </div>

                    <!-- Initial Paid Amount -->
                    <div class="mb-3">
                        <label for="paid_amount" class="form-label small text-muted d-flex justify-content-between">
                            <span>Initial Paid Amount ($)</span>
                            <span class="small text-secondary">Optional advance</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">$</span>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   class="form-control font-monospace text-end" 
                                   id="paid_amount" 
                                   name="paid_amount" 
                                   value="<?= e($old['paid_amount'] ?? '0.00') ?>" 
                                   placeholder="0.00">
                        </div>
                    </div>

                    <!-- Due Amount -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Due Balance</span>
                        <span class="font-monospace fw-bold text-danger fs-5" id="displayDue">$0.00</span>
                    </div>

                    <!-- Payment Status Preview -->
                    <div class="d-flex justify-content-between align-items-center mb-4 p-2 bg-light rounded border">
                        <span class="small text-muted">Payment Status:</span>
                        <span id="displayPaymentBadge" class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase">Unpaid</span>
                    </div>

                    <!-- Submit & Cancel -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Complete &amp; Save Order
                        </button>
                        <a href="<?= base_url('modules/orders/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
// Prepare services JSON for client-side dynamic rendering
$servicesJson = json_encode($services, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$getServiceItemsUrl = base_url('modules/orders/get_service_items.php');

$extraScripts = '
<script>
const SERVICES_DATA = ' . $servicesJson . ';
const GET_ITEMS_URL = "' . $getServiceItemsUrl . '";

document.addEventListener("DOMContentLoaded", function () {
    const orderItemsBody = document.getElementById("orderItemsBody");
    const addItemRowBtn = document.getElementById("addItemRowBtn");
    const discountInput = document.getElementById("discount");
    const paidInput = document.getElementById("paid_amount");

    const displaySubtotal = document.getElementById("displaySubtotal");
    const displayTotal = document.getElementById("displayTotal");
    const displayDue = document.getElementById("displayDue");
    const displayPaymentBadge = document.getElementById("displayPaymentBadge");

    let rowCounter = 0;

    function createItemRow(existingData = null) {
        rowCounter++;
        const rowId = rowCounter;
        const tr = document.createElement("tr");
        tr.className = "order-item-row";
        tr.id = `row_${rowId}`;

        let serviceOptions = `<option value="">-- Choose Service --</option>`;
        SERVICES_DATA.forEach(svc => {
            serviceOptions += `<option value="${svc.id}" data-type="${svc.pricing_type}">${svc.name} (${svc.pricing_type === "per_kg" ? "Per KG" : "Per Item"})</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowId}][service_id]" class="form-select form-select-sm service-select" required>
                    ${serviceOptions}
                </select>
            </td>
            <td>
                <select name="items[${rowId}][service_item_id]" class="form-select form-select-sm item-select" required disabled>
                    <option value="">-- Select Service First --</option>
                </select>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="any" min="0.01" name="items[${rowId}][quantity]" class="form-control form-control-sm text-center qty-input" value="1" required>
                    <span class="input-group-text unit-badge bg-light small">item</span>
                </div>
            </td>
            <td class="text-end">
                <span class="font-monospace unit-price-display text-muted small">$0.00</span>
                <input type="hidden" class="unit-price-val" value="0">
            </td>
            <td class="text-end">
                <span class="font-monospace fw-bold line-total-display text-dark">$0.00</span>
                <input type="hidden" class="line-total-val" value="0">
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
        const lineTotalDisplay = tr.querySelector(".line-total-display");
        const lineTotalVal = tr.querySelector(".line-total-val");
        const removeBtn = tr.querySelector(".btn-remove-item");

        // When service changes -> load items via AJAX
        serviceSelect.addEventListener("change", function () {
            const svcId = this.value;
            itemSelect.innerHTML = `<option value="">Loading rates...</option>`;
            itemSelect.disabled = true;

            if (!svcId) {
                itemSelect.innerHTML = `<option value="">-- Select Service First --</option>`;
                priceDisplay.textContent = "$0.00";
                priceVal.value = "0";
                calculateRow(tr);
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
                            itemSelect.appendChild(opt);
                        });

                        // If only 1 item (e.g. Per KG), auto-select it
                        if (data.items.length === 1) {
                            itemSelect.selectedIndex = 1;
                            itemSelect.dispatchEvent(new Event("change"));
                        }
                    } else {
                        itemSelect.innerHTML = `<option value="">No active rates found</option>`;
                        itemSelect.disabled = true;
                    }
                })
                .catch(err => {
                    console.error("Failed to load service items", err);
                    itemSelect.innerHTML = `<option value="">Error loading rates</option>`;
                });
        });

        // When item changes -> update price & unit
        itemSelect.addEventListener("change", function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                const price = parseFloat(selectedOpt.getAttribute("data-price") || 0);
                const unit = selectedOpt.getAttribute("data-unit") || "item";
                priceVal.value = price;
                priceDisplay.textContent = `$${price.toFixed(2)}`;
                unitBadge.textContent = unit;
            } else {
                priceVal.value = 0;
                priceDisplay.textContent = "$0.00";
            }
            calculateRow(tr);
        });

        // When quantity changes -> recalculate row
        qtyInput.addEventListener("input", function () {
            calculateRow(tr);
        });

        // Remove row
        removeBtn.addEventListener("click", function () {
            if (document.querySelectorAll(".order-item-row").length > 1) {
                tr.remove();
                recalculateGrandTotals();
            } else {
                serviceSelect.value = "";
                itemSelect.innerHTML = `<option value="">-- Select Service First --</option>`;
                itemSelect.disabled = true;
                qtyInput.value = "1";
                priceVal.value = 0;
                priceDisplay.textContent = "$0.00";
                calculateRow(tr);
            }
        });
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

        // Update payment status badge
        if (total === 0) {
            displayPaymentBadge.className = "badge bg-secondary-subtle text-secondary border text-uppercase";
            displayPaymentBadge.textContent = "No Charge";
        } else if (paid === 0) {
            displayPaymentBadge.className = "badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase";
            displayPaymentBadge.textContent = "Unpaid";
        } else if (paid >= total) {
            displayPaymentBadge.className = "badge bg-success-subtle text-success border border-success-subtle text-uppercase";
            displayPaymentBadge.textContent = "Fully Paid";
        } else {
            displayPaymentBadge.className = "badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase";
            displayPaymentBadge.textContent = "Partially Paid";
        }
    }

    if (addItemRowBtn) {
        addItemRowBtn.addEventListener("click", () => createItemRow());
    }

    if (discountInput) {
        discountInput.addEventListener("input", recalculateGrandTotals);
    }

    if (paidInput) {
        paidInput.addEventListener("input", recalculateGrandTotals);
    }

    // Initialize with 1 empty row
    createItemRow();
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
