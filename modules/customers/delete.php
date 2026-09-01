<?php
/**
 * Customer Soft Delete Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/customers/index.php');
}

// 1. Role Permission Check: Only Administrator & Manager can delete customers
if (!is_admin() && !is_manager()) {
    http_response_code(403);
    set_flash_message('error', 'Access Denied: Only Administrators and Managers are authorized to delete customers.');
    redirect('modules/customers/index.php');
}

// 2. Verify CSRF Token
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

    // 3. Fetch customer details
    $stmt = $pdo->prepare('SELECT id, customer_code, name FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        set_flash_message('error', 'The customer you are trying to delete does not exist or has already been deleted.');
        redirect('modules/customers/index.php');
    }

    // 4. Perform Soft Delete
    $deleteStmt = $pdo->prepare('UPDATE customers SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
    $deleteStmt->execute(['id' => $id]);

    // 5. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'customer_deleted',
        sprintf('Deleted customer %s - %s', $customer['customer_code'], $customer['name'])
    );

    set_flash_message('success', sprintf('Customer <strong>%s</strong> (%s) has been successfully deleted.', e($customer['name']), e($customer['customer_code'])));
    redirect('modules/customers/index.php');

} catch (PDOException $e) {
    error_log('Customer Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete customer due to a database error.');
    redirect('modules/customers/index.php');
}
