<?php
/**
 * Add New Laundry Service View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Staff cannot create services
require_role(['administrator', 'manager'], 'modules/services/index.php');

$pageTitle = 'Add Laundry Service';
$old = $_SESSION['old_service_input'] ?? [];
unset($_SESSION['old_service_input']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/services/index.php') ?>">Services</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add Service</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="h4 fw-bold text-dark mb-0">Add New Laundry Service</h2>
        <a href="<?= base_url('modules/services/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<!-- Service Form Card -->
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-tags me-2 text-primary"></i>Service Details &amp; Pricing Model</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/services/store.php') ?>" method="POST" id="serviceForm" autocomplete="off">
                    <?= csrf_field() ?>

                    <!-- Primary Service Information -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-8">
                            <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   placeholder="e.g. Wash &amp; Fold, Dry Cleaning, Steam Press" 
                                   value="<?= e($old['name'] ?? '') ?>" 
                                   required 
                                   maxlength="150" 
                                   autofocus>
                            <div class="form-text small">Unique display name for this laundry service category.</div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" <?= ($old['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <div class="form-text small">Inactive services cannot be selected in new orders.</div>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="2" 
                                      placeholder="Briefly describe what this laundry service includes..."><?= e($old['description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Pricing Model Selection -->
                    <div class="card border bg-light mb-4">
                        <div class="card-body p-3">
                            <label class="form-label fw-semibold text-dark d-block mb-2">
                                Pricing Model <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="pricing_type" 
                                           id="pricing_type_item" 
                                           value="per_item" 
                                           <?= ($old['pricing_type'] ?? 'per_item') === 'per_item' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="pricing_type_item">
                                        <i class="bi bi-grid me-1 text-primary"></i> Per Item / Garment
                                        <div class="text-muted fw-normal small">Price is assigned per clothing type (e.g. Shirt $3.00, Suit $15.00)</div>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="pricing_type" 
                                           id="pricing_type_kg" 
                                           value="per_kg" 
                                           <?= ($old['pricing_type'] ?? '') === 'per_kg' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="pricing_type_kg">
                                        <i class="bi bi-speedometer me-1 text-primary"></i> Per KG Weight
                                        <div class="text-muted fw-normal small">Base fixed price calculated per kilogram of total wash load</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Per KG Rate (Visible only if per_kg selected) -->
                    <div id="perKgSection" class="mb-4 <?= ($old['pricing_type'] ?? 'per_item') === 'per_kg' ? '' : 'd-none' ?>">
                        <div class="card border-primary-subtle bg-primary-subtle bg-opacity-10">
                            <div class="card-body p-3">
                                <h4 class="h6 fw-semibold text-dark mb-2">Base Weight Rate</h4>
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-4">
                                        <label for="price_per_kg" class="form-label small text-muted">Price Per Kilogram ($/KG) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0" 
                                                   class="form-control font-monospace" 
                                                   id="price_per_kg" 
                                                   name="price_per_kg" 
                                                   placeholder="0.00" 
                                                   value="<?= e($old['price_per_kg'] ?? '') ?>">
                                            <span class="input-group-text bg-white">/ KG</span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-8">
                                        <label for="kg_item_description" class="form-label small text-muted">Rate Note / Label (Optional)</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="kg_item_description" 
                                               name="kg_item_description" 
                                               placeholder="e.g. Standard Wash &amp; Tumble Dry" 
                                               value="<?= e($old['kg_item_description'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Per Item Clothing Rates (Visible only if per_item selected) -->
                    <div id="perItemSection" class="mb-4 <?= ($old['pricing_type'] ?? 'per_item') === 'per_item' ? '' : 'd-none' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="h6 fw-semibold text-dark mb-0">Itemized Clothing &amp; Garment Rates</h4>
                                <div class="text-muted small">Define clothing items and prices under this service category.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addItemBtn">
                                <i class="bi bi-plus-lg me-1"></i> Add Item Row
                            </button>
                        </div>

                        <div class="table-responsive border rounded mb-2">
                            <table class="table table-sm align-middle mb-0" id="itemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 200px;">Item / Garment Name <span class="text-danger">*</span></th>
                                        <th style="width: 140px;">Price ($) <span class="text-danger">*</span></th>
                                        <th style="width: 120px;">Unit</th>
                                        <th style="min-width: 180px;">Description (Optional)</th>
                                        <th style="width: 60px;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <?php
                                    $items = $old['items'] ?? [
                                        ['item_name' => '', 'price' => '', 'unit' => 'item', 'description' => '']
                                    ];
                                    foreach ($items as $idx => $item):
                                    ?>
                                        <tr class="item-row">
                                            <td>
                                                <input type="text" 
                                                       name="items[<?= $idx ?>][item_name]" 
                                                       class="form-control form-control-sm item-name-input" 
                                                       placeholder="e.g. Shirt, Suit, Bed Sheet" 
                                                       value="<?= e($item['item_name'] ?? '') ?>" 
                                                       required>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-white">$</span>
                                                    <input type="number" 
                                                           step="0.01" 
                                                           min="0" 
                                                           name="items[<?= $idx ?>][price]" 
                                                           class="form-control form-control-sm font-monospace item-price-input" 
                                                           placeholder="0.00" 
                                                           value="<?= e($item['price'] ?? '') ?>" 
                                                           required>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="items[<?= $idx ?>][unit]" 
                                                       class="form-control form-control-sm text-muted" 
                                                       value="<?= e($item['unit'] ?? 'item') ?>" 
                                                       placeholder="item">
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="items[<?= $idx ?>][description]" 
                                                       class="form-control form-control-sm" 
                                                       placeholder="Optional note" 
                                                       value="<?= e($item['description'] ?? '') ?>">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-remove-row" title="Remove item">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>You can add or remove items anytime. Item names must be unique within this service.</div>
                    </div>

                    <hr class="my-4">

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= base_url('modules/services/index.php') ?>" class="btn btn-outline-secondary px-4">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const radioItem = document.getElementById("pricing_type_item");
    const radioKg = document.getElementById("pricing_type_kg");
    const perItemSection = document.getElementById("perItemSection");
    const perKgSection = document.getElementById("perKgSection");
    const pricePerKgInput = document.getElementById("price_per_kg");
    const itemsTableBody = document.getElementById("itemsTableBody");
    const addItemBtn = document.getElementById("addItemBtn");

    function togglePricingSections() {
        if (radioKg && radioKg.checked) {
            perKgSection.classList.remove("d-none");
            perItemSection.classList.add("d-none");
            pricePerKgInput.setAttribute("required", "required");
            // Remove required attribute from per-item rows so form submits cleanly
            document.querySelectorAll(".item-name-input, .item-price-input").forEach(el => el.removeAttribute("required"));
        } else {
            perItemSection.classList.remove("d-none");
            perKgSection.classList.add("d-none");
            pricePerKgInput.removeAttribute("required");
            document.querySelectorAll(".item-name-input, .item-price-input").forEach(el => el.setAttribute("required", "required"));
        }
    }

    if (radioItem && radioKg) {
        radioItem.addEventListener("change", togglePricingSections);
        radioKg.addEventListener("change", togglePricingSections);
    }

    let rowIndex = ' . (count($items) ?? 1) . ';

    if (addItemBtn && itemsTableBody) {
        addItemBtn.addEventListener("click", function () {
            const tr = document.createElement("tr");
            tr.className = "item-row";
            tr.innerHTML = `
                <td>
                    <input type="text" name="items[${rowIndex}][item_name]" class="form-control form-control-sm item-name-input" placeholder="e.g. Shirt, Suit, Bed Sheet" required>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">$</span>
                        <input type="number" step="0.01" min="0" name="items[${rowIndex}][price]" class="form-control form-control-sm font-monospace item-price-input" placeholder="0.00" required>
                    </div>
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][unit]" class="form-control form-control-sm text-muted" value="item" placeholder="item">
                </td>
                <td>
                    <input type="text" name="items[${rowIndex}][description]" class="form-control form-control-sm" placeholder="Optional note">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-remove-row" title="Remove item">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>
            `;
            itemsTableBody.appendChild(tr);
            rowIndex++;
            attachRemoveListeners();
        });
    }

    function attachRemoveListeners() {
        document.querySelectorAll(".btn-remove-row").forEach(btn => {
            btn.onclick = function () {
                const row = this.closest("tr");
                const totalRows = itemsTableBody.querySelectorAll("tr").length;
                if (totalRows > 1) {
                    row.remove();
                } else {
                    // Just clear values if it is the only row
                    row.querySelectorAll("input").forEach(i => {
                        if (i.name.includes("[unit]")) i.value = "item";
                        else i.value = "";
                    });
                }
            };
        });
    }

    attachRemoveListeners();
    togglePricingSections();
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
