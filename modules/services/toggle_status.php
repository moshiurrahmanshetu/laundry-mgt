<?php
/**
 * Laundry Service Status Toggle Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Role Guard: Only Administrator & Manager can change service status
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
    $stmt = $pdo->prepare('SELECT id, name, status FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $service = $stmt->fetch();

    if (!$service) {
        set_flash_message('error', 'Service not found or has been deleted.');
        redirect('modules/services/index.php');
    }

    // 3. Toggle Status
    $newStatus = ($service['status'] === 'active') ? 'inactive' : 'active';

    $updateStmt = $pdo->prepare('UPDATE services SET status = :status, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    // 4. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'service_status_changed',
        sprintf('Changed service %s status from %s to %s', $service['name'], $service['status'], $newStatus)
    );

    set_flash_message('success', sprintf('Status for service <strong>%s</strong> changed to <span class="badge %s text-uppercase">%s</span>.', e($service['name']), $newStatus === 'active' ? 'bg-success' : 'bg-secondary', $newStatus));

    // Redirect to referer if within app, otherwise to index
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && str_contains($referer, 'modules/services/')) {
        header('Location: ' . $referer);
        exit;
    }

    redirect('modules/services/index.php');

} catch (PDOException $e) {
    error_log('Service Status Toggle Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to change service status due to a database error.');
    redirect('modules/services/index.php');
}
