<?php
/**
 * Laundry Service Store Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Only Administrator & Manager can create services
require_role(['administrator', 'manager'], 'modules/services/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/services/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    $_SESSION['old_service_input'] = $_POST;
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/services/create.php');
}

// 2. Extract & Sanitize Service Info
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

// 3. Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Service Name is required.';
} elseif (mb_strlen($name) > 150) {
    $errors[] = 'Service Name cannot exceed 150 characters.';
}

$pdo = getDBConnection();

// Check for duplicate active service name
try {
    $dupStmt = $pdo->prepare('SELECT id FROM services WHERE LOWER(name) = LOWER(:name) AND deleted_at IS NULL LIMIT 1');
    $dupStmt->execute(['name' => $name]);
    if ($dupStmt->fetch()) {
        $errors[] = sprintf('A service with the name "%s" already exists.', e($name));
    }
} catch (PDOException $e) {
    error_log('Duplicate check error: ' . $e->getMessage());
}

// 4. Validate Pricing & Items
$itemsToInsert = [];

if ($pricingType === 'per_kg') {
    $pricePerKg = sanitize_input($_POST['price_per_kg'] ?? '');
    $kgDesc = sanitize_input($_POST['kg_item_description'] ?? '');

    if ($pricePerKg === '' || !is_numeric($pricePerKg) || (float)$pricePerKg < 0) {
        $errors[] = 'Please provide a valid non-negative Price Per Kilogram ($/KG).';
    } else {
        $itemsToInsert[] = [
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
        $errors[] = 'Please add at least one clothing/garment item rate.';
    } else {
        foreach ($rawItems as $item) {
            $itemName = sanitize_input($item['item_name'] ?? '');
            $itemPrice = sanitize_input($item['price'] ?? '');
            $itemUnit = sanitize_input($item['unit'] ?? 'item');
            $itemDesc = sanitize_input($item['description'] ?? '');

            if (empty($itemName) && $itemPrice === '') {
                continue; // Skip completely empty row
            }

            if (empty($itemName)) {
                $errors[] = 'Item / Garment Name is required for all item rows.';
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

            $itemsToInsert[] = [
                'item_name'   => $itemName,
                'description' => !empty($itemDesc) ? $itemDesc : null,
                'unit'        => !empty($itemUnit) ? $itemUnit : 'item',
                'price'       => (float)$itemPrice,
                'status'      => 'active',
                'sort_order'  => $sortOrder++
            ];
        }

        if (empty($errors) && empty($itemsToInsert)) {
            $errors[] = 'Please add at least one item rate for this service.';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['old_service_input'] = $_POST;
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/services/create.php');
}

// 5. Database Transaction to Save Service & Items
try {
    $pdo->beginTransaction();

    $slug = generate_service_slug($name, null, $pdo);
    $createdBy = (int)($_SESSION['user_id'] ?? 0) ?: null;

    // Insert Service
    $svcStmt = $pdo->prepare('
        INSERT INTO services (name, slug, description, pricing_type, status, created_by, created_at, updated_at)
        VALUES (:name, :slug, :description, :pricing_type, :status, :created_by, NOW(), NOW())
    ');
    $svcStmt->execute([
        'name'         => $name,
        'slug'         => $slug,
        'description'  => !empty($description) ? $description : null,
        'pricing_type' => $pricingType,
        'status'       => $status,
        'created_by'   => $createdBy
    ]);

    $serviceId = (int)$pdo->lastInsertId();

    // Insert Items
    $itemStmt = $pdo->prepare('
        INSERT INTO service_items (service_id, item_name, description, unit, price, status, sort_order, created_at, updated_at)
        VALUES (:service_id, :item_name, :description, :unit, :price, :status, :sort_order, NOW(), NOW())
    ');

    foreach ($itemsToInsert as $item) {
        $itemStmt->execute([
            'service_id'  => $serviceId,
            'item_name'   => $item['item_name'],
            'description' => $item['description'],
            'unit'        => $item['unit'],
            'price'       => $item['price'],
            'status'      => $item['status'],
            'sort_order'  => $item['sort_order']
        ]);
    }

    $pdo->commit();

    // 6. Log Activity
    log_activity(
        $createdBy,
        'service_created',
        sprintf('Created laundry service %s (%s, %d items)', $name, $pricingType, count($itemsToInsert))
    );

    set_flash_message('success', sprintf('Laundry service <strong>%s</strong> was created successfully.', e($name)));
    redirect('modules/services/show.php?id=' . $serviceId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Service Store Error: ' . $e->getMessage());
    $_SESSION['old_service_input'] = $_POST;
    set_flash_message('error', 'Failed to create laundry service due to a database error. Please try again.');
    redirect('modules/services/create.php');
}
