<?php
/**
 * Laundry Service Update Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Only Administrator & Manager can edit services
require_role(['administrator', 'manager'], 'modules/services/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/services/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid service ID provided.');
    redirect('modules/services/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    $_SESSION['old_service_input'] = $_POST;
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/services/edit.php?id=' . $id);
}

// 2. Fetch existing service
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, name, slug FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$service = $stmt->fetch();

if (!$service) {
    set_flash_message('error', 'The service you are trying to edit does not exist or has been deleted.');
    redirect('modules/services/index.php');
}

// 3. Extract & Sanitize Service Info
$name        = sanitize_input($_POST['name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');
$pricingType = sanitize_input($_POST['pricing_type'] ?? 'per_item');
$status      = sanitize_input($_POST['status'] ?? 'active');

if (!in_array($pricingType, ['per_item', 'per_kg'], true)) {
    $pricingType = 'per_item';
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

// 4. Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Service Name is required.';
} elseif (mb_strlen($name) > 150) {
    $errors[] = 'Service Name cannot exceed 150 characters.';
}

// Duplicate name check (excluding current service)
try {
    $dupStmt = $pdo->prepare('SELECT id FROM services WHERE LOWER(name) = LOWER(:name) AND id != :id AND deleted_at IS NULL LIMIT 1');
    $dupStmt->execute(['name' => $name, 'id' => $id]);
    if ($dupStmt->fetch()) {
        $errors[] = sprintf('Another service with the name "%s" already exists.', e($name));
    }
} catch (PDOException $e) {
    error_log('Duplicate check error: ' . $e->getMessage());
}

// 5. Validate Pricing & Items
$itemsToProcess = [];

if ($pricingType === 'per_kg') {
    $pricePerKg = sanitize_input($_POST['price_per_kg'] ?? '');
    $kgDesc     = sanitize_input($_POST['kg_item_description'] ?? '');
    $kgItemId   = (int)($_POST['kg_item_id'] ?? 0);

    if ($pricePerKg === '' || !is_numeric($pricePerKg) || (float)$pricePerKg < 0) {
        $errors[] = 'Please provide a valid non-negative Price Per Kilogram ($/KG).';
    } else {
        $itemsToProcess[] = [
            'id'          => $kgItemId,
            'item_name'   => 'Base Wash & Fold Rate',
            'description' => !empty($kgDesc) ? $kgDesc : 'Standard per KG weight rate',
            'unit'        => 'kg',
            'price'       => (float)$pricePerKg,
            'status'      => 'active',
            'sort_order'  => 1
        ];
    }
} else {
    // Per Item mode
    $rawItems = $_POST['items'] ?? [];
    $seenItemNames = [];
    $sortOrder = 1;

    if (!is_array($rawItems) || empty($rawItems)) {
        $errors[] = 'Please configure at least one item rate.';
    } else {
        foreach ($rawItems as $item) {
            $itemId    = (int)($item['id'] ?? 0);
            $itemName  = sanitize_input($item['item_name'] ?? '');
            $itemPrice = sanitize_input($item['price'] ?? '');
            $itemUnit  = sanitize_input($item['unit'] ?? 'item');
            $itemDesc  = sanitize_input($item['description'] ?? '');

            if (empty($itemName) && $itemPrice === '') {
                continue;
            }

            if (empty($itemName)) {
                $errors[] = 'Item / Garment Name is required for all configured item rows.';
                break;
            }

            if ($itemPrice === '' || !is_numeric($itemPrice) || (float)$itemPrice < 0) {
                $errors[] = sprintf('Please provide a valid non-negative price for item "%s".', e($itemName));
                break;
            }

            $lowerName = mb_strtolower($itemName);
            if (in_array($lowerName, $seenItemNames, true)) {
                $errors[] = sprintf('Duplicate item name "%s" detected in this service. Item names must be unique.', e($itemName));
                break;
            }
            $seenItemNames[] = $lowerName;

            $itemsToProcess[] = [
                'id'          => $itemId,
                'item_name'   => $itemName,
                'description' => !empty($itemDesc) ? $itemDesc : null,
                'unit'        => !empty($itemUnit) ? $itemUnit : 'item',
                'price'       => (float)$itemPrice,
                'status'      => 'active',
                'sort_order'  => $sortOrder++
            ];
        }

        if (empty($errors) && empty($itemsToProcess)) {
            $errors[] = 'Please configure at least one active item rate for this service.';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['old_service_input'] = $_POST;
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/services/edit.php?id=' . $id);
}

// 6. Database Transaction to Update Service & Items
try {
    $pdo->beginTransaction();

    $newSlug = generate_service_slug($name, $id, $pdo);

    // Update Service
    $updateStmt = $pdo->prepare('
        UPDATE services SET
            name = :name,
            slug = :slug,
            description = :description,
            pricing_type = :pricing_type,
            status = :status,
            updated_at = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        'name'         => $name,
        'slug'         => $newSlug,
        'description'  => !empty($description) ? $description : null,
        'pricing_type' => $pricingType,
        'status'       => $status,
        'id'           => $id
    ]);

    // Gather submitted valid item IDs to preserve
    $submittedIds = [];

    $insertItemStmt = $pdo->prepare('
        INSERT INTO service_items (service_id, item_name, description, unit, price, status, sort_order, created_at, updated_at)
        VALUES (:service_id, :item_name, :description, :unit, :price, :status, :sort_order, NOW(), NOW())
    ');

    $updateItemStmt = $pdo->prepare('
        UPDATE service_items SET
            item_name = :item_name,
            description = :description,
            unit = :unit,
            price = :price,
            status = :status,
            sort_order = :sort_order,
            deleted_at = NULL,
            updated_at = NOW()
        WHERE id = :id AND service_id = :service_id
    ');

    foreach ($itemsToProcess as $item) {
        if ($item['id'] > 0) {
            // Update existing item
            $updateItemStmt->execute([
                'item_name'   => $item['item_name'],
                'description' => $item['description'],
                'unit'        => $item['unit'],
                'price'       => $item['price'],
                'status'      => $item['status'],
                'sort_order'  => $item['sort_order'],
                'id'          => $item['id'],
                'service_id'  => $id
            ]);
            $submittedIds[] = $item['id'];
        } else {
            // Insert new item
            $insertItemStmt->execute([
                'service_id'  => $id,
                'item_name'   => $item['item_name'],
                'description' => $item['description'],
                'unit'        => $item['unit'],
                'price'       => $item['price'],
                'status'      => $item['status'],
                'sort_order'  => $item['sort_order']
            ]);
            $submittedIds[] = (int)$pdo->lastInsertId();
        }
    }

    // Soft-delete items belonging to this service that were removed from the form
    if (!empty($submittedIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($submittedIds), '?'));
        $softDeleteStmt = $pdo->prepare("
            UPDATE service_items 
            SET deleted_at = NOW(), updated_at = NOW() 
            WHERE service_id = ? AND id NOT IN ($inPlaceholders) AND deleted_at IS NULL
        ");
        $softDeleteStmt->execute(array_merge([$id], $submittedIds));
    }

    $pdo->commit();

    // 7. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'service_updated',
        sprintf('Updated laundry service %s (%s, %d items)', $name, $pricingType, count($itemsToProcess))
    );

    set_flash_message('success', sprintf('Laundry service <strong>%s</strong> was updated successfully.', e($name)));
    redirect('modules/services/show.php?id=' . $id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Service Update Error: ' . $e->getMessage());
    $_SESSION['old_service_input'] = $_POST;
    set_flash_message('error', 'Failed to update laundry service due to a database error. Please try again.');
    redirect('modules/services/edit.php?id=' . $id);
}
