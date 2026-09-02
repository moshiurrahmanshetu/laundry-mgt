<?php
/**
 * Toggle Staff Account Status POST Handler
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

// Prevent self-deactivation
if ($id === $currentUserId) {
    set_flash_message('error', 'Security Protection: You cannot deactivate your own logged-in administrator account.');
    redirect('modules/staff/index.php');
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare('
        SELECT u.*, r.slug AS role_slug 
        FROM users u 
        INNER JOIN roles r ON u.role_id = r.id 
        WHERE u.id = :id AND u.deleted_at IS NULL
    ');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        set_flash_message('error', 'User account not found or has been deleted.');
        redirect('modules/staff/index.php');
    }

    $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';

    // Lockout protection if deactivating an admin
    if ($user['role_slug'] === 'administrator' && $newStatus === 'inactive') {
        $otherAdminsStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL AND u.id != :id
        ");
        $otherAdminsStmt->execute(['id' => $id]);
        $remainingAdmins = (int)$otherAdminsStmt->fetchColumn();

        if ($remainingAdmins <= 0) {
            set_flash_message('error', 'Security Protection: Cannot deactivate the only remaining active Administrator account.');
            redirect('modules/staff/index.php');
        }
    }

    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare('UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute(['status' => $newStatus, 'id' => $id]);

    log_activity(
        $currentUserId,
        'staff_status_changed',
        sprintf('Changed account status of %s to %s', $user['name'], $newStatus)
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Status for <strong>%s</strong> changed to <strong>%s</strong>.', e($user['name']), strtoupper($newStatus)));
    redirect('modules/staff/index.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Toggle Status Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update user status due to a database error.');
    redirect('modules/staff/index.php');
}
