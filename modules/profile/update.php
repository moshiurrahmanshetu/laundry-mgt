<?php
/**
 * Profile Details Update Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/profile/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/profile/index.php');
}

$userId = (int)$_SESSION['user_id'];
$name   = sanitize_input($_POST['name'] ?? '');
$email  = sanitize_input($_POST['email'] ?? '');
$phone  = sanitize_input($_POST['phone'] ?? '');

// 2. Validate Inputs
if (empty($name)) {
    set_flash_message('error', 'Full Name is required.');
    redirect('modules/profile/index.php');
}

if (mb_strlen($name) > 100) {
    set_flash_message('error', 'Full Name cannot exceed 100 characters.');
    redirect('modules/profile/index.php');
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash_message('error', 'A valid email address is required.');
    redirect('modules/profile/index.php');
}

if (mb_strlen($email) > 191) {
    set_flash_message('error', 'Email address is too long.');
    redirect('modules/profile/index.php');
}

if (!empty($phone) && mb_strlen($phone) > 20) {
    set_flash_message('error', 'Phone number cannot exceed 20 characters.');
    redirect('modules/profile/index.php');
}

try {
    $pdo = getDBConnection();

    // 3. Ensure email uniqueness across all other user accounts
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
    $checkStmt->execute([
        'email' => $email,
        'id'    => $userId
    ]);

    if ($checkStmt->fetch()) {
        set_flash_message('error', 'That email address is already in use by another account.');
        redirect('modules/profile/index.php');
    }

    // 4. Update user record
    $updateStmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'name'  => $name,
        'email' => $email,
        'phone' => !empty($phone) ? $phone : null,
        'id'    => $userId
    ]);

    // 5. Update session information
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    unset($_SESSION['user_cache']); // Invalidate cached user record

    log_activity($userId, 'profile_updated', 'User updated personal profile details');

    set_flash_message('success', 'Your profile details have been updated successfully.');
    redirect('modules/profile/index.php');

} catch (PDOException $e) {
    error_log('Profile update database error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update profile due to a system error. Please try again.');
    redirect('modules/profile/index.php');
}
