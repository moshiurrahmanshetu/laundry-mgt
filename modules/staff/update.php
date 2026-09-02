<?php
/**
 * Update Staff Member POST Handler
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

$id              = (int)($_POST['id'] ?? 0);
$name            = sanitize_input($_POST['name'] ?? '');
$email           = strtolower(sanitize_input($_POST['email'] ?? ''));
$phone           = sanitize_input($_POST['phone'] ?? '');
$roleId          = (int)($_POST['role_id'] ?? 0);
$status          = strtolower(sanitize_input($_POST['status'] ?? 'active'));
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$removeAvatar    = !empty($_POST['remove_avatar']);
$currentUserId   = (int)($_SESSION['user_id'] ?? 0) ?: null;

$errors = [];

if ($id <= 0) {
    set_flash_message('error', 'Invalid staff account ID.');
    redirect('modules/staff/index.php');
}

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

$pdo = getDBConnection();

// Check existing user
$stmt = $pdo->prepare('
    SELECT u.*, r.slug AS current_role_slug, r.name AS current_role_name 
    FROM users u 
    INNER JOIN roles r ON u.role_id = r.id 
    WHERE u.id = :id AND u.deleted_at IS NULL
');
$stmt->execute(['id' => $id]);
$existingStaff = $stmt->fetch();

if (!$existingStaff) {
    set_flash_message('error', 'The requested staff account does not exist or has been deleted.');
    redirect('modules/staff/index.php');
}

// Check email uniqueness excluding current record
$dupStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id AND deleted_at IS NULL');
$dupStmt->execute(['email' => $email, 'id' => $id]);
if ($dupStmt->fetch()) {
    $errors[] = 'Another user account already uses this email address.';
}

// Check target role
$targetRoleStmt = $pdo->prepare('SELECT id, name, slug FROM roles WHERE id = :id AND deleted_at IS NULL');
$targetRoleStmt->execute(['id' => $roleId]);
$targetRole = $targetRoleStmt->fetch();
if (!$targetRole) {
    $errors[] = 'Selected system role is invalid.';
}

// 2. Critical Administrator Lockout Protection
// If this user is currently an active administrator, check if changing role or deactivating would leave 0 active administrators
if ($existingStaff['current_role_slug'] === 'administrator' && $existingStaff['status'] === 'active') {
    $isDemotingOrDeactivating = ($targetRole && $targetRole['slug'] !== 'administrator') || ($status === 'inactive');
    if ($isDemotingOrDeactivating) {
        $otherAdminsStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL AND u.id != :id
        ");
        $otherAdminsStmt->execute(['id' => $id]);
        $remainingAdmins = (int)$otherAdminsStmt->fetchColumn();

        if ($remainingAdmins <= 0) {
            $errors[] = 'Security Protection: You cannot change the role or deactivate the only remaining active Administrator account.';
        }
    }
}

// Optional password validation
$updatePassword = false;
$hashedPassword = null;
if (!empty($password)) {
    if (mb_strlen($password) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    } else {
        $updatePassword = true;
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    }
}

// Optional avatar upload
$newAvatarFilename = $existingStaff['avatar'];
if ($removeAvatar) {
    $newAvatarFilename = null;
}

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
        $newAvatarFilename = 'avatar_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
        $destPath = $uploadDir . $newAvatarFilename;
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            $errors[] = 'Failed to save avatar image.';
            $newAvatarFilename = $existingStaff['avatar'];
        }
    }
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/staff/edit.php?id=' . $id);
}

try {
    $pdo->beginTransaction();

    $sql = '
        UPDATE users SET
            role_id    = :role_id,
            name       = :name,
            email      = :email,
            phone      = :phone,
            avatar     = :avatar,
            status     = :status,
            updated_at = NOW()
    ';

    if ($updatePassword) {
        $sql .= ', password = :password';
    }

    $sql .= ' WHERE id = :id';

    $updateStmt = $pdo->prepare($sql);
    $updateParams = [
        'role_id' => $roleId,
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone ?: null,
        'avatar'  => $newAvatarFilename,
        'status'  => $status,
        'id'      => $id
    ];

    if ($updatePassword) {
        $updateParams['password'] = $hashedPassword;
    }

    $updateStmt->execute($updateParams);

    // If current logged-in user edited themselves, refresh session cache
    if ($id === $currentUserId) {
        current_user(true);
    }

    // Audit Log
    log_activity(
        $currentUserId,
        'staff_updated',
        sprintf('Updated staff account %s (Role: %s, Status: %s)', $name, $targetRole['name'], $status)
    );

    $pdo->commit();

    set_flash_message('success', sprintf('Staff account for <strong>%s</strong> updated successfully.', e($name)));
    redirect('modules/staff/show.php?id=' . $id);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff Update Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to update user account due to a database error.');
    redirect('modules/staff/edit.php?id=' . $id);
}
