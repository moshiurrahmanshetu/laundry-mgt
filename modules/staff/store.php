<?php
/**
 * Create Staff Member POST Handler
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
    redirect('modules/staff/create.php');
}

$name            = sanitize_input($_POST['name'] ?? '');
$email           = strtolower(sanitize_input($_POST['email'] ?? ''));
$phone           = sanitize_input($_POST['phone'] ?? '');
$roleId          = (int)($_POST['role_id'] ?? 0);
$status          = strtolower(sanitize_input($_POST['status'] ?? 'active'));
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$currentUserId   = (int)($_SESSION['user_id'] ?? 0) ?: null;

$errors = [];

// 2. Server-side Validations
if (empty($name)) {
    $errors[] = 'Full Name is required.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($roleId <= 0) {
    $errors[] = 'Please select a valid system role.';
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

if (empty($password) || mb_strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long.';
}

if ($password !== $confirmPassword) {
    $errors[] = 'Password confirmation does not match.';
}

$pdo = getDBConnection();

// Check unique email among active accounts
if (!empty($email)) {
    $dupStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL');
    $dupStmt->execute(['email' => $email]);
    if ($dupStmt->fetch()) {
        $errors[] = 'A user account with this email address already exists.';
    }
}

// Check role exists
if ($roleId > 0) {
    $roleStmt = $pdo->prepare('SELECT id, name, slug FROM roles WHERE id = :id AND deleted_at IS NULL');
    $roleStmt->execute(['id' => $roleId]);
    $role = $roleStmt->fetch();
    if (!$role) {
        $errors[] = 'Selected system role is invalid or has been removed.';
    }
}

// Handle avatar upload if provided
$avatarFilename = null;
if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath   = $_FILES['avatar']['tmp_name'];
    $fileName      = $_FILES['avatar']['name'];
    $fileSize      = $_FILES['avatar']['size'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes      = ['image/jpeg', 'image/png', 'image/webp'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileTmpPath);

    if (!in_array($fileExtension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimes, true)) {
        $errors[] = 'Invalid avatar format. Allowed: JPG, PNG, and WebP.';
    } elseif ($fileSize > 2 * 1024 * 1024) {
        $errors[] = 'Avatar image must not exceed 2MB in size.';
    } else {
        $uploadDir = __DIR__ . '/../../uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $avatarFilename = 'avatar_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
        $destPath = $uploadDir . $avatarFilename;
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            $errors[] = 'Failed to save avatar image to server storage.';
            $avatarFilename = null;
        }
    }
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/staff/create.php');
}

try {
    $pdo->beginTransaction();

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $insertStmt = $pdo->prepare('
        INSERT INTO users (
            role_id, name, email, phone, password, avatar, status, created_at, updated_at
        ) VALUES (
            :role_id, :name, :email, :phone, :password, :avatar, :status, NOW(), NOW()
        )
    ');
    $insertStmt->execute([
        'role_id'  => $roleId,
        'name'     => $name,
        'email'    => $email,
        'phone'    => $phone ?: null,
        'password' => $hashedPassword,
        'avatar'   => $avatarFilename,
        'status'   => $status
    ]);

    $newUserId = (int)$pdo->lastInsertId();

    // Audit Log
    log_activity(
        $currentUserId,
        'staff_created',
        sprintf('Created staff account %s (%s) with role %s', $name, $email, $role['name'])
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Staff account for <strong>%s</strong> created successfully.', e($name)));
    redirect('modules/staff/show.php?id=' . $newUserId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff Store Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to create user account due to a database error.');
    redirect('modules/staff/create.php');
}
