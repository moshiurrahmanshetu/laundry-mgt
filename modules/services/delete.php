<?php
/**
 * Laundry Service Soft Delete Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Only Administrator & Manager can delete services
require_role(['administrator', 'manager'], 'modules/services/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/services/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/services/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid service ID provided.');
    redirect('modules/services/index.php');
}

try {
    $pdo = getDBConnection();

    // 2. Fetch service details
    $stmt = $pdo->prepare('SELECT id, name FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $service = $stmt->fetch();

    if (!$service) {
        set_flash_message('error', 'The service you are trying to delete does not exist or has already been deleted.');
        redirect('modules/services/index.php');
    }

    // 3. Perform Soft Delete
    $deleteStmt = $pdo->prepare('UPDATE services SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
    $deleteStmt->execute(['id' => $id]);

    // 4. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'service_deleted',
        sprintf('Deleted laundry service %s (ID: %d)', $service['name'], $id)
    );

    set_flash_message('success', sprintf('Laundry service <strong>%s</strong> has been successfully deleted.', e($service['name'])));
    redirect('modules/services/index.php');

} catch (PDOException $e) {
    error_log('Service Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete laundry service due to a database error.');
    redirect('modules/services/index.php');
}
