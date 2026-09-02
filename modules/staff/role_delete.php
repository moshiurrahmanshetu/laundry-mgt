<?php
/**
 * Soft Delete Custom Role POST Handler
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

$id     = (int)($_POST['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0) ?: null;

if ($id <= 0) {
    set_flash_message('error', 'Invalid role ID.');
    redirect('modules/staff/roles.php');
}

$pdo = getDBConnection();

try {
    $checkStmt = $pdo->prepare('SELECT id, name, slug FROM roles WHERE id = :id AND deleted_at IS NULL');
    $checkStmt->execute(['id' => $id]);
    $role = $checkStmt->fetch();

    if (!$role) {
        set_flash_message('error', 'Role not found or has already been deleted.');
        redirect('modules/staff/roles.php');
    }

    // 2. Prevent deleting system roles
    if (in_array($role['slug'], ['administrator', 'manager', 'staff'], true)) {
        set_flash_message('error', sprintf('Security Protection: The core system role <strong>%s</strong> cannot be deleted.', e($role['name'])));
        redirect('modules/staff/roles.php');
    }

    // 3. Check active user assignments
    $usageStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id AND deleted_at IS NULL');
    $usageStmt->execute(['id' => $id]);
    $usageCount = (int)$usageStmt->fetchColumn();

    if ($usageCount > 0) {
        set_flash_message('error', sprintf(
            'Role <strong>%s</strong> cannot be deleted because it is currently assigned to %d active user(s). Please reassign those users first.',
            e($role['name']),
            $usageCount
        ));
        redirect('modules/staff/roles.php');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE roles SET deleted_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $id]);

    log_activity($userId, 'role_deleted', sprintf('Soft deleted custom role %s (%s)', $role['name'], $role['slug']));

    $pdo->commit();

    set_flash_message('success', sprintf('Role <strong>%s</strong> was deleted successfully.', e($role['name'])));
    redirect('modules/staff/roles.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Role Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete role due to a database error.');
    redirect('modules/staff/roles.php');
}
