<?php
/**
 * Edit Laundry Service View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Only Administrator & Manager can edit services
require_role(['administrator', 'manager'], 'modules/services/index.php');

$serviceId = (int)($_GET['id'] ?? 0);
if ($serviceId <= 0) {
    set_flash_message('error', 'Invalid service ID provided.');
    redirect('modules/services/index.php');
}

$pdo = getDBConnection();

// Fetch service
$stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $serviceId]);
$service = $stmt->fetch();

if (!$service) {
    set_flash_message('error', 'The service you are trying to edit does not exist or has been deleted.');
    redirect('modules/services/index.php');
}

// Fetch active items
$itemStmt = $pdo->prepare('SELECT * FROM service_items WHERE service_id = :service_id AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC');
$itemStmt->execute(['service_id' => $serviceId]);
$existingItems = $itemStmt->fetchAll();

$pageTitle = 'Edit ' . $service['name'];

// Check for old input flashed in session on validation error
$old = $_SESSION['old_service_input'] ?? [];
unset($_SESSION['old_service_input']);

$currentPricingType = $old['pricing_type'] ?? $service['pricing_type'];
$currentStatus = $old['status'] ?? $service['status'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Breadcrumb & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/services/index.php') ?>">Services</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('modules/services/show.php?id=' . (int)$service['id']) ?>"><?= e($service['name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 fw-bold text-dark mb-0">Edit Service: <?= e($service['name']) ?></h2>
            <span class="text-muted small">Slug: <span class="font-monospace fw-semibold text-primary"><?= e($service['slug']) ?></span></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/services/show.php?id=' . (int)$service['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i> View Details
            </a>
            <a href="<?= base_url('modules/services/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<!-- Service Edit Form Card -->
<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Update Service &amp; Pricing Rates</h3>
            </div>
            <div class="card-body p-4">
                <form action="<?= base_url('modules/services/update.php') ?>" method="POST" id="serviceEditForm" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">

                    <!-- Primary Info -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-8">
                            <label for="name" class="form-label">Service Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   value="<?= e($old['name'] ?? $service['name']) ?>" 
                                   required 
                                   maxlength="150" 
                                   autofocus>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="2"><?= e($old['description'] ?? $service['description']) ?></textarea>
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
                                           <?= $currentPricingType === 'per_item' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="pricing_type_item">
                                        <i class="bi bi-grid me-1 text-primary"></i> Per Item / Garment
                                        <div class="text-muted fw-normal small">Price is assigned per clothing type</div>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="pricing_type" 
                                           id="pricing_type_kg" 
                                           value="per_kg" 
                                           <?= $currentPricingType === 'per_kg' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-dark" for="pricing_type_kg">
                                        <i class="bi bi-speedometer me-1 text-primary"></i> Per KG Weight
                                        <div class="text-muted fw-normal small">Base fixed price calculated per kilogram of wash load</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Per KG Rate (Visible only if per_kg selected) -->
                    <?php
                    $firstKgItem = ($service['pricing_type'] === 'per_kg' && !empty($existingItems)) ? $existingItems[0] : null;
                    $kgPriceVal = $old['price_per_kg'] ?? ($firstKgItem['price'] ?? '');
                    $kgDescVal  = $old['kg_item_description'] ?? ($firstKgItem['description'] ?? '');
                    $kgItemId   = $firstKgItem['id'] ?? 0;
                    ?>
                    <div id="perKgSection" class="mb-4 <?= $currentPricingType === 'per_kg' ? '' : 'd-none' ?>">
                        <input type="hidden" name="kg_item_id" value="<?= (int)$kgItemId ?>">
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
                                                   value="<?= e($kgPriceVal) ?>">
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
                                               value="<?= e($kgDescVal) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Per Item Clothing Rates (Visible only if per_item selected) -->
                    <div id="perItemSection" class="mb-4 <?= $currentPricingType === 'per_item' ? '' : 'd-none' ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="h6 fw-semibold text-dark mb-0">Itemized Clothing &amp; Garment Rates</h4>
                                <div class="text-muted small">Update item names, prices, or add new garment options.</div>
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
                                    $itemsToShow = !empty($old['items']) ? $old['items'] : $existingItems;
                                    if (empty($itemsToShow)) {
                                        $itemsToShow = [['id' => 0, 'item_name' => '', 'price' => '', 'unit' => 'item', 'description' => '']];
                                    }
                                    foreach ($itemsToShow as $idx => $item):
                                    ?>
                                        <tr class="item-row">
                                            <td>
                                                <input type="hidden" name="items[<?= $idx ?>][id]" value="<?= (int)($item['id'] ?? 0) ?>">
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
                        <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Removed items will be deactivated when saving.</div>
                    </div>

                    <hr class="my-4">

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= base_url('modules/services/show.php?id=' . (int)$service['id']) ?>" class="btn btn-outline-secondary px-4">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Save Changes
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

    let rowIndex = ' . count($itemsToShow) . ';

    if (addItemBtn && itemsTableBody) {
        addItemBtn.addEventListener("click", function () {
            const tr = document.createElement("tr");
            tr.className = "item-row";
            tr.innerHTML = `
                <td>
                    <input type="hidden" name="items[${rowIndex}][id]" value="0">
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
                    row.querySelectorAll("input").forEach(i => {
                        if (i.name.includes("[id]")) i.value = "0";
                        else if (i.name.includes("[unit]")) i.value = "item";
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
