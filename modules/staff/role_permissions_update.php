<?php
/**
 * Update Role Permissions POST Handler
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

$roleId        = (int)($_POST['role_id'] ?? 0);
$permissionIds = array_map('intval', (array)($_POST['permission_ids'] ?? []));
$userId        = (int)($_SESSION['user_id'] ?? 0) ?: null;

if ($roleId <= 0) {
    set_flash_message('error', 'Invalid role ID.');
    redirect('modules/staff/roles.php');
}

$pdo = getDBConnection();

try {
    $roleStmt = $pdo->prepare('SELECT id, name, slug FROM roles WHERE id = :id AND deleted_at IS NULL');
    $roleStmt->execute(['id' => $roleId]);
    $role = $roleStmt->fetch();

    if (!$role) {
        set_flash_message('error', 'Role not found or has been deleted.');
        redirect('modules/staff/roles.php');
    }

    $pdo->beginTransaction();

    // 1. Clear existing assigned permissions
    $deleteStmt = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
    $deleteStmt->execute(['role_id' => $roleId]);

    // 2. Insert selected permissions
    if (!empty($permissionIds)) {
        $insertStmt = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
        foreach ($permissionIds as $permId) {
            if ($permId > 0) {
                $insertStmt->execute([
                    'role_id'       => $roleId,
                    'permission_id' => $permId
                ]);
            }
        }
    }

    // 3. Clear session permission cache
    unset($_SESSION['user_permissions']);

    log_activity(
        $userId,
        'role_permissions_updated',
        sprintf('Updated permissions matrix for role %s (%d permissions assigned)', $role['name'], count($permissionIds))
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Permissions for role <strong>%s</strong> updated successfully.', e($role['name'])));
    redirect('modules/staff/role_permissions.php?id=' . $roleId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Permissions Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update role permissions due to a database error.');
    redirect('modules/staff/role_permissions.php?id=' . $roleId);
}
