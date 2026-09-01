<?php
/**
 * User Avatar Upload Handler
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

// 2. Validate File Upload Presence
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    set_flash_message('error', 'Please select an image file to upload.');
    redirect('modules/profile/index.php');
}

$file = $_FILES['avatar'];

// Check upload error status
if ($file['error'] !== UPLOAD_ERR_OK) {
    set_flash_message('error', 'An error occurred during file upload. (Code: ' . (int)$file['error'] . ')');
    redirect('modules/profile/index.php');
}

// 3. Validate File Size (Max 2MB)
$maxSizeBytes = 2 * 1024 * 1024;
if ($file['size'] > $maxSizeBytes) {
    set_flash_message('error', 'File size exceeds maximum limit of 2MB.');
    redirect('modules/profile/index.php');
}

// 4. Validate File Extension
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
$originalExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($originalExtension, $allowedExtensions, true)) {
    set_flash_message('error', 'Invalid file type. Only JPG, PNG, and WebP images are allowed.');
    redirect('modules/profile/index.php');
}

// 5. Validate Genuine MIME Type using FileInfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = [
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png'  => ['png'],
    'image/webp' => ['webp']
];

if (!array_key_exists($mimeType, $allowedMimes)) {
    set_flash_message('error', 'Invalid image file content detected.');
    redirect('modules/profile/index.php');
}

$finalExtension = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg'
};

// 6. Generate Unique, Secure Server-side Filename
$randomHex = bin2hex(random_bytes(8));
$newFilename = sprintf('avatar_%d_%d_%s.%s', $userId, time(), $randomHex, $finalExtension);
$targetDirectory = AVATAR_PATH;

if (!is_dir($targetDirectory)) {
    if (!mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
        set_flash_message('error', 'Server error: Upload directory could not be created.');
        redirect('modules/profile/index.php');
    }
}

$destination = $targetDirectory . DIRECTORY_SEPARATOR . $newFilename;

// 7. Move File
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    set_flash_message('error', 'Failed to save uploaded image. Please try again.');
    redirect('modules/profile/index.php');
}

try {
    $pdo = getDBConnection();

    // 8. Fetch and delete old avatar file if present
    $oldStmt = $pdo->prepare('SELECT avatar FROM users WHERE id = :id LIMIT 1');
    $oldStmt->execute(['id' => $userId]);
    $oldUser = $oldStmt->fetch();

    if ($oldUser && !empty($oldUser['avatar'])) {
        $oldFilePath = $targetDirectory . DIRECTORY_SEPARATOR . basename($oldUser['avatar']);
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    // 9. Update database with new avatar filename
    $updateStmt = $pdo->prepare('UPDATE users SET avatar = :avatar, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        'avatar' => $newFilename,
        'id'     => $userId
    ]);

    // 10. Invalidate session cache
    unset($_SESSION['user_cache']);

    log_activity($userId, 'avatar_updated', 'User uploaded new profile avatar');

    set_flash_message('success', 'Profile picture updated successfully.');
    redirect('modules/profile/index.php');

} catch (PDOException $e) {
    error_log('Avatar database update error: ' . $e->getMessage());
    if (file_exists($destination)) {
        @unlink($destination);
    }
    set_flash_message('error', 'Failed to update avatar in database.');
    redirect('modules/profile/index.php');
}
