<?php
/**
 * Update Role POST Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/staff/roles.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/staff/roles.php');
}

$id          = (int)($_POST['id'] ?? 0);
$name        = sanitize_input($_POST['name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');
$status      = strtolower(sanitize_input($_POST['status'] ?? 'active'));
$userId      = (int)($_SESSION['user_id'] ?? 0) ?: null;

if ($id <= 0 || empty($name)) {
    set_flash_message('error', 'Role ID and Name are required.');
    redirect('modules/staff/roles.php');
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

$pdo = getDBConnection();

try {
    $checkStmt = $pdo->prepare('SELECT id, name, slug FROM roles WHERE id = :id AND deleted_at IS NULL');
    $checkStmt->execute(['id' => $id]);
    $role = $checkStmt->fetch();

    if (!$role) {
        set_flash_message('error', 'Role not found or has been deleted.');
        redirect('modules/staff/roles.php');
    }

    // Protect Administrator role from being deactivated
    if ($role['slug'] === 'administrator') {
        $status = 'active';
    }

    // Check duplicate name on another role
    $dupStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name AND id != :id AND deleted_at IS NULL');
    $dupStmt->execute(['name' => $name, 'id' => $id]);
    if ($dupStmt->fetch()) {
        set_flash_message('error', 'Another role already exists with this name.');
        redirect('modules/staff/roles.php');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        UPDATE roles SET
            name        = :name,
            description = :description,
            status      = :status,
            updated_at  = NOW()
        WHERE id = :id
    ');
    $stmt->execute([
        'name'        => $name,
        'description' => $description ?: null,
        'status'      => $status,
        'id'          => $id
    ]);

    log_activity($userId, 'role_updated', sprintf('Updated role %s (status: %s)', $name, $status));

    $pdo->commit();

    set_flash_message('success', sprintf('Role <strong>%s</strong> updated successfully.', e($name)));
    redirect('modules/staff/roles.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Role Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update role due to a database error.');
    redirect('modules/staff/roles.php');
}
