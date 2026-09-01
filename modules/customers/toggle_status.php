<?php
/**
 * Customer Status Toggle Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/customers/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/customers/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid customer ID provided.');
    redirect('modules/customers/index.php');
}

try {
    $pdo = getDBConnection();

    // 2. Fetch customer details
    $stmt = $pdo->prepare('SELECT id, customer_code, name, status FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        set_flash_message('error', 'Customer not found or has been deleted.');
        redirect('modules/customers/index.php');
    }

    // 3. Toggle Status
    $newStatus = ($customer['status'] === 'active') ? 'inactive' : 'active';

    $updateStmt = $pdo->prepare('UPDATE customers SET status = :status, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    // 4. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'customer_status_changed',
        sprintf('Changed customer %s - %s status from %s to %s', $customer['customer_code'], $customer['name'], $customer['status'], $newStatus)
    );

    set_flash_message('success', sprintf('Status for customer <strong>%s</strong> (%s) changed to <span class="badge %s text-uppercase">%s</span>.', e($customer['name']), e($customer['customer_code']), $newStatus === 'active' ? 'bg-success' : 'bg-secondary', $newStatus));

    // Redirect to referer if within app, otherwise to index
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($referer) && str_contains($referer, 'modules/customers/')) {
        header('Location: ' . $referer);
        exit;
    }

    redirect('modules/customers/index.php');

} catch (PDOException $e) {
    error_log('Customer Status Toggle Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to change customer status due to a database error.');
    redirect('modules/customers/index.php');
}
