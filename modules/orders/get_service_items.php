<?php
/**
 * AJAX Helper: Fetch Service Items & Current Pricing
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

header('Content-Type: application/json; charset=UTF-8');

$serviceId = (int)($_GET['service_id'] ?? ($_POST['service_id'] ?? 0));

if ($serviceId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid service ID provided.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Fetch service
    $svcStmt = $pdo->prepare('SELECT id, name, pricing_type, status FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $svcStmt->execute(['id' => $serviceId]);
    $service = $svcStmt->fetch();

    if (!$service || $service['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Service is inactive or unavailable.']);
        exit;
    }

    // Fetch active service items
    $itemStmt = $pdo->prepare('
        SELECT id, item_name, unit, price, description
        FROM service_items
        WHERE service_id = :service_id AND status = "active" AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ');
    $itemStmt->execute(['service_id' => $serviceId]);
    $items = $itemStmt->fetchAll();

    $formattedItems = [];
    foreach ($items as $item) {
        $formattedItems[] = [
            'id'              => (int)$item['id'],
            'item_name'       => $item['item_name'],
            'unit'            => $item['unit'] ?: ($service['pricing_type'] === 'per_kg' ? 'kg' : 'item'),
            'price'           => (float)$item['price'],
            'formatted_price' => format_price($item['price']),
            'description'     => $item['description'] ?? ''
        ];
    }

    echo json_encode([
        'success'      => true,
        'service_id'   => (int)$service['id'],
        'service_name' => $service['name'],
        'pricing_type' => $service['pricing_type'],
        'items'        => $formattedItems
    ]);
    exit;

} catch (PDOException $e) {
    error_log('get_service_items AJAX error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to fetch service item rates.']);
    exit;
}
