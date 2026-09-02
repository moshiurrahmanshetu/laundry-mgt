<?php
/**
 * Save System Settings POST Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators / Settings Managers
if (!has_permission('settings.manage') && !has_role('administrator')) {
    http_response_code(403);
    set_flash_message('error', 'Access Denied: You do not have permission to modify system settings.');
    redirect('modules/dashboard/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/settings/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/settings/index.php');
}

$activeTab = sanitize_input($_POST['active_tab'] ?? 'profile');
if (!in_array($activeTab, ['profile', 'general', 'invoice'], true)) {
    $activeTab = 'profile';
}

$userId = (int)($_SESSION['user_id'] ?? 0) ?: null;
$errors = [];

// 2. Sanitize and Validate Inputs
$businessName        = sanitize_input($_POST['business_name'] ?? '');
$businessPhone       = sanitize_input($_POST['business_phone'] ?? '');
$businessEmail       = strtolower(sanitize_input($_POST['business_email'] ?? ''));
$businessWebsite     = sanitize_input($_POST['business_website'] ?? '');
$businessDescription = sanitize_input($_POST['business_description'] ?? '');
$businessAddress     = sanitize_input($_POST['business_address'] ?? '');

$timezone       = sanitize_input($_POST['timezone'] ?? 'Asia/Dhaka');
$dateFormat     = sanitize_input($_POST['date_format'] ?? 'd/m/Y');
$currency       = strtoupper(sanitize_input($_POST['currency'] ?? 'BDT'));
$currencySymbol = sanitize_input($_POST['currency_symbol'] ?? '$');

$invoicePrefix       = sanitize_input($_POST['invoice_prefix'] ?? 'INV-');
$receiptPrefix       = sanitize_input($_POST['receipt_prefix'] ?? 'REC-');
$invoiceFooter       = sanitize_input($_POST['invoice_footer'] ?? '');
$showBusinessLogo    = !empty($_POST['show_business_logo']) ? '1' : '0';
$showBusinessAddress = !empty($_POST['show_business_address']) ? '1' : '0';
$showBusinessPhone   = !empty($_POST['show_business_phone']) ? '1' : '0';
$removeLogo          = !empty($_POST['remove_logo']);

if (empty($businessName)) {
    $errors[] = 'Business Name is required.';
}

if (!empty($businessEmail) && !filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid business email address.';
}

if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
    $errors[] = 'Invalid timezone selected. Please choose a recognized PHP timezone identifier.';
}

if (empty($currency)) {
    $currency = 'BDT';
}

if (empty($currencySymbol)) {
    $currencySymbol = '$';
}

// 3. Handle Logo Upload / Removal
$existingLogo = get_setting('business_logo');
$newLogoFilename = $existingLogo;

if ($removeLogo && !empty($existingLogo)) {
    $oldFilePath = LOGO_PATH . DIRECTORY_SEPARATOR . $existingLogo;
    if (file_exists($oldFilePath) && is_file($oldFilePath)) {
        @unlink($oldFilePath);
    }
    $newLogoFilename = null;
}

if (!empty($_FILES['business_logo']['name']) && $_FILES['business_logo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath   = $_FILES['business_logo']['tmp_name'];
    $fileName      = $_FILES['business_logo']['name'];
    $fileSize      = $_FILES['business_logo']['size'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes      = ['image/jpeg', 'image/png', 'image/webp'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($fileTmpPath);

    if (!in_array($fileExtension, $allowedExtensions, true) || !in_array($mimeType, $allowedMimes, true)) {
        $errors[] = 'Invalid logo format. Allowed image types: JPG, PNG, and WebP.';
    } elseif ($fileSize > 2 * 1024 * 1024) {
        $errors[] = 'Logo image must not exceed 2MB in file size.';
    } else {
        if (!is_dir(LOGO_PATH)) {
            mkdir(LOGO_PATH, 0755, true);
        }

        $generatedName = 'logo_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
        $destPath = LOGO_PATH . DIRECTORY_SEPARATOR . $generatedName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Delete old logo file if it existed
            if (!empty($existingLogo) && $existingLogo !== $generatedName) {
                $oldFilePath = LOGO_PATH . DIRECTORY_SEPARATOR . $existingLogo;
                if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }
            $newLogoFilename = $generatedName;
        } else {
            $errors[] = 'Failed to upload logo image to server storage.';
        }
    }
}

if (!empty($errors)) {
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/settings/index.php?tab=' . urlencode($activeTab));
}

// 4. Save Settings to Database
$pdo = getDBConnection();

$settingsToSave = [
    'business_name'        => $businessName,
    'business_phone'       => $businessPhone,
    'business_email'       => $businessEmail,
    'business_website'     => $businessWebsite,
    'business_description' => $businessDescription,
    'business_address'     => $businessAddress,
    'business_logo'        => $newLogoFilename,
    'timezone'             => $timezone,
    'date_format'          => $dateFormat,
    'currency'             => $currency,
    'currency_symbol'      => $currencySymbol,
    'invoice_prefix'       => $invoicePrefix,
    'receipt_prefix'       => $receiptPrefix,
    'invoice_footer'       => $invoiceFooter,
    'show_business_logo'   => $showBusinessLogo,
    'show_business_address'=> $showBusinessAddress,
    'show_business_phone'  => $showBusinessPhone,
];

try {
    $pdo->beginTransaction();

    foreach ($settingsToSave as $key => $val) {
        set_setting($key, $val, $pdo);
    }

    // Flush runtime cache
    get_all_settings(true, $pdo);

    // Audit Log
    log_activity(
        $userId,
        'settings_updated',
        'Updated business profile and system configuration settings'
    );

    $pdo->commit();

    set_flash_message('success', 'System settings updated successfully.');
    redirect('modules/settings/index.php?tab=' . urlencode($activeTab));

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Settings Save Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to save system settings due to a database error.');
    redirect('modules/settings/index.php?tab=' . urlencode($activeTab));
}
