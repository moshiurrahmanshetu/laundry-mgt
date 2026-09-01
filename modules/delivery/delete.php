<?php
/**
 * Soft Delete Pickup / Delivery Request Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['administrator', 'manager'], 'modules/delivery/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/delivery/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/delivery/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$currentUser = current_user();

if ($id <= 0) {
    set_flash_message('error', 'Invalid request ID.');
    redirect('modules/delivery/index.php');
}

$pdo = getDBConnection();

// Fetch request record
$stmt = $pdo->prepare('SELECT * FROM pickup_deliveries WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$req = $stmt->fetch();

if (!$req) {
    set_flash_message('error', 'The requested record does not exist or has already been deleted.');
    redirect('modules/delivery/index.php');
}

try {
    // Soft Delete
    $delStmt = $pdo->prepare('
        UPDATE pickup_deliveries SET
            deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ');
    $delStmt->execute(['id' => $id]);

    log_activity(
        $currentUser['id'] ?? null,
        'delivery_request_deleted',
        sprintf('Soft deleted %s request %s for order ID %d', $req['type'], $req['reference_number'], (int)$req['order_id'])
    );

    set_flash_message('success', sprintf(
        '%s request <strong>%s</strong> was deleted successfully.',
        ucfirst($req['type']),
        e($req['reference_number'])
    ));
    redirect('modules/delivery/index.php');

} catch (PDOException $e) {
    error_log('Delivery Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete request due to a database error.');
    redirect('modules/delivery/index.php');
}
