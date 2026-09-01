<?php
/**
 * User Password Change Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/profile/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/profile/index.php#change-password-section');
}

$userId          = (int)$_SESSION['user_id'];
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword     = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

// 2. Validate Inputs
if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    set_flash_message('error', 'All password fields are required.');
    redirect('modules/profile/index.php#change-password-section');
}

if (mb_strlen($newPassword) < 8) {
    set_flash_message('error', 'New password must be at least 8 characters long.');
    redirect('modules/profile/index.php#change-password-section');
}

if ($newPassword !== $confirmPassword) {
    set_flash_message('error', 'New password and confirmation password do not match.');
    redirect('modules/profile/index.php#change-password-section');
}

try {
    $pdo = getDBConnection();

    // 3. Fetch stored password hash
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        set_flash_message('error', 'Your current password was entered incorrectly.');
        redirect('modules/profile/index.php#change-password-section');
    }

    // 4. Hash new password securely
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    // 5. Update user password
    $updateStmt = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'password' => $newHash,
        'id'       => $userId
    ]);

    log_activity($userId, 'password_changed', 'User successfully changed password');

    set_flash_message('success', 'Your password has been changed successfully.');
    redirect('modules/profile/index.php#change-password-section');

} catch (PDOException $e) {
    error_log('Password change database error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to change password due to a system error. Please try again.');
    redirect('modules/profile/index.php#change-password-section');
}
