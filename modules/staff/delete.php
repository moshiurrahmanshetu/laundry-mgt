<?php
/**
 * Soft Delete Staff Member POST Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/staff/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/staff/index.php');
}

$id            = (int)($_POST['id'] ?? 0);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if ($id <= 0) {
    set_flash_message('error', 'Invalid staff account ID.');
    redirect('modules/staff/index.php');
}

// Prevent self-deletion
if ($id === $currentUserId) {
    set_flash_message('error', 'Security Protection: You cannot delete your own logged-in administrator account.');
    redirect('modules/staff/index.php');
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare('
        SELECT u.*, r.slug AS role_slug, r.name AS role_name 
        FROM users u 
        INNER JOIN roles r ON u.role_id = r.id 
        WHERE u.id = :id AND u.deleted_at IS NULL
    ');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash_message('error', 'The user account does not exist or has already been deleted.');
        redirect('modules/staff/index.php');
    }

    // Check last administrator protection
    if ($user['role_slug'] === 'administrator' && $user['status'] === 'active') {
        $otherAdminsStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL AND u.id != :id
        ");
        $otherAdminsStmt->execute(['id' => $id]);
        $remainingAdmins = (int)$otherAdminsStmt->fetchColumn();

        if ($remainingAdmins <= 0) {
            set_flash_message('error', 'Security Protection: Cannot delete the only remaining active Administrator account.');
            redirect('modules/staff/index.php');
        }
    }

    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = :id');
    $deleteStmt->execute(['id' => $id]);

    log_activity(
        $currentUserId,
        'staff_deleted',
        sprintf('Soft deleted staff account %s (%s)', $user['name'], $user['email'])
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Staff account for <strong>%s</strong> was deleted successfully.', e($user['name'])));
    redirect('modules/staff/index.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff Delete Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to delete user account due to a database error.');
    redirect('modules/staff/index.php');
}
