<?php
/**
 * Installer POST & AJAX Request Processor
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/installer_helpers.php';

// Permanent Lock Protection
if (is_app_installed()) {
    http_response_code(403);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Application is already installed and locked.']);
        exit;
    }
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

$action = trim($_POST['action'] ?? '');
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
       || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

// Verify CSRF
if (!verify_installer_csrf($_POST['csrf_token'] ?? '')) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token expired or invalid. Please refresh the page.']);
        exit;
    }
    $_SESSION['installer_error'] = 'Security token expired. Please try again.';
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

// -----------------------------------------------------------------------------
// 1. ACTION: TEST DATABASE CONNECTION
// -----------------------------------------------------------------------------
if ($action === 'test_db') {
    header('Content-Type: application/json');

    $host   = trim($_POST['db_host'] ?? '127.0.0.1');
    $port   = trim($_POST['db_port'] ?? '3306');
    $name   = trim($_POST['db_name'] ?? '');
    $user   = trim($_POST['db_user'] ?? '');
    $pass   = $_POST['db_pass'] ?? '';

    $testRes = test_database_connection($host, $port, $name, $user, $pass);

    if (!$testRes['success']) {
        echo json_encode([
            'success'       => false,
            'message'       => $testRes['message'],
            'db_exists'     => false,
            'can_create_db' => false
        ]);
        exit;
    }

    $hasTables = false;
    $hasLaundryTables = false;

    if ($testRes['db_exists'] && $testRes['pdo']) {
        $tableInfo = check_existing_tables($testRes['pdo']);
        $hasTables = $tableInfo['has_tables'];
        $hasLaundryTables = $tableInfo['has_laundry_tables'];
    }

    echo json_encode([
        'success'            => true,
        'message'            => $testRes['message'],
        'db_exists'          => $testRes['db_exists'],
        'can_create_db'      => $testRes['can_create_db'],
        'has_tables'         => $hasTables,
        'has_laundry_tables' => $hasLaundryTables
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 2. ACTION: CREATE DATABASE
// -----------------------------------------------------------------------------
if ($action === 'create_db') {
    header('Content-Type: application/json');

    $host   = trim($_POST['db_host'] ?? '127.0.0.1');
    $port   = trim($_POST['db_port'] ?? '3306');
    $name   = trim($_POST['db_name'] ?? '');
    $user   = trim($_POST['db_user'] ?? '');
    $pass   = $_POST['db_pass'] ?? '';

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Database name is required.']);
        exit;
    }

    $res = try_create_database($host, $port, $name, $user, $pass);
    echo json_encode($res);
    exit;
}

// -----------------------------------------------------------------------------
// 3. ACTION: SAVE DATABASE CONFIGURATION & PROCEED TO STEP 3
// -----------------------------------------------------------------------------
if ($action === 'save_db') {
    $host   = trim($_POST['db_host'] ?? '127.0.0.1');
    $port   = trim($_POST['db_port'] ?? '3306');
    $name   = trim($_POST['db_name'] ?? '');
    $user   = trim($_POST['db_user'] ?? '');
    $pass   = $_POST['db_pass'] ?? '';

    if (empty($name) || empty($user)) {
        $_SESSION['installer_error'] = 'Database Name and Username are required.';
        header('Location: ' . BASE_URL . '/install/index.php?step=2');
        exit;
    }

    // Verify connection one more time
    $test = test_database_connection($host, $port, $name, $user, $pass);
    if (!$test['success']) {
        $_SESSION['installer_error'] = $test['message'];
        header('Location: ' . BASE_URL . '/install/index.php?step=2');
        exit;
    }

    // Save in session
    $_SESSION['installer_db'] = [
        'host' => $host,
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'pass' => $pass
    ];

    header('Location: ' . BASE_URL . '/install/index.php?step=3');
    exit;
}

// -----------------------------------------------------------------------------
// 4. ACTION: IMPORT SQL FILE
// -----------------------------------------------------------------------------
if ($action === 'import_sql') {
    $dbConfig = $_SESSION['installer_db'] ?? null;
    if (empty($dbConfig)) {
        $_SESSION['installer_error'] = 'Database connection details not found. Please start from Step 2.';
        header('Location: ' . BASE_URL . '/install/index.php?step=2');
        exit;
    }

    $test = test_database_connection(
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name'],
        $dbConfig['user'],
        $dbConfig['pass']
    );

    if (!$test['success'] || !$test['pdo']) {
        $_SESSION['installer_error'] = 'Failed to connect to database for import: ' . $test['message'];
        header('Location: ' . BASE_URL . '/install/index.php?step=3');
        exit;
    }

    $pdo = $test['pdo'];
    $sqlType = $_POST['sql_source'] ?? 'default';
    $sqlPath = ROOT_PATH . '/database/install.sql';
    $tempUploadedFile = null;

    if ($sqlType === 'custom' && !empty($_FILES['custom_sql_file']['name'])) {
        $file = $_FILES['custom_sql_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['installer_error'] = 'File upload failed with error code: ' . $file['error'];
            header('Location: ' . BASE_URL . '/install/index.php?step=3');
            exit;
        }

        if ($ext !== 'sql') {
            $_SESSION['installer_error'] = 'Invalid file format. Only .sql files are permitted.';
            header('Location: ' . BASE_URL . '/install/index.php?step=3');
            exit;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            $_SESSION['installer_error'] = 'SQL file size exceeds 10MB limit.';
            header('Location: ' . BASE_URL . '/install/index.php?step=3');
            exit;
        }

        $tempUploadedFile = ROOT_PATH . '/storage/temp_' . time() . '.sql';
        if (!move_uploaded_file($file['tmp_name'], $tempUploadedFile)) {
            $_SESSION['installer_error'] = 'Failed to process uploaded SQL file on server storage.';
            header('Location: ' . BASE_URL . '/install/index.php?step=3');
            exit;
        }

        $sqlPath = $tempUploadedFile;
    }

    // Execute Import
    $importRes = import_sql_file($pdo, $sqlPath);

    // Clean up temporary file
    if ($tempUploadedFile && file_exists($tempUploadedFile)) {
        @unlink($tempUploadedFile);
    }

    if (!$importRes['success']) {
        $_SESSION['installer_error'] = $importRes['message'];
        header('Location: ' . BASE_URL . '/install/index.php?step=3');
        exit;
    }

    // Write config/db.php
    $writeOk = write_database_config(
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name'],
        $dbConfig['user'],
        $dbConfig['pass']
    );

    if (!$writeOk) {
        $_SESSION['installer_error'] = 'Database imported successfully, but failed to write configuration to config/db.php. Please check write permissions on the config/ directory.';
        header('Location: ' . BASE_URL . '/install/index.php?step=3');
        exit;
    }

    $_SESSION['installer_imported'] = true;
    $_SESSION['installer_success'] = 'Database tables and seed data imported successfully!';
    header('Location: ' . BASE_URL . '/install/index.php?step=4');
    exit;
}

// -----------------------------------------------------------------------------
// 5. ACTION: CREATE ADMINISTRATOR & FINALIZE INSTALLATION
// -----------------------------------------------------------------------------
if ($action === 'create_admin') {
    $dbConfig = $_SESSION['installer_db'] ?? null;
    if (empty($dbConfig)) {
        $_SESSION['installer_error'] = 'Database connection expired. Please start installation again.';
        header('Location: ' . BASE_URL . '/install/index.php?step=2');
        exit;
    }

    $test = test_database_connection(
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['name'],
        $dbConfig['user'],
        $dbConfig['pass']
    );

    if (!$test['success'] || !$test['pdo']) {
        $_SESSION['installer_error'] = 'Database connection error: ' . $test['message'];
        header('Location: ' . BASE_URL . '/install/index.php?step=4');
        exit;
    }

    $pdo = $test['pdo'];

    // Inputs
    $adminName        = trim($_POST['admin_name'] ?? '');
    $adminEmail       = strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPass        = $_POST['admin_password'] ?? '';
    $adminPassConfirm = $_POST['admin_password_confirm'] ?? '';

    $bizName    = trim($_POST['business_name'] ?? 'Laundry Management System') ?: 'Laundry Management System';
    $bizPhone   = trim($_POST['business_phone'] ?? '');
    $bizEmail   = strtolower(trim($_POST['business_email'] ?? ''));
    $bizAddress = trim($_POST['business_address'] ?? '');
    $timezone   = trim($_POST['timezone'] ?? 'Asia/Dhaka');
    $currency   = strtoupper(trim($_POST['currency'] ?? 'BDT')) ?: 'BDT';
    $currSymbol = trim($_POST['currency_symbol'] ?? '$') ?: '$';
    $dateFormat = trim($_POST['date_format'] ?? 'd/m/Y') ?: 'd/m/Y';

    $errors = [];

    if (empty($adminName)) {
        $errors[] = 'Administrator Full Name is required.';
    }
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid Administrator Email Address is required.';
    }
    if (strlen($adminPass) < 8) {
        $errors[] = 'Administrator Password must be at least 8 characters long.';
    }
    if ($adminPass !== $adminPassConfirm) {
        $errors[] = 'Administrator Password and Confirmation do not match.';
    }
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        $timezone = 'Asia/Dhaka';
    }

    if (!empty($errors)) {
        $_SESSION['installer_error'] = implode('<br>', $errors);
        header('Location: ' . BASE_URL . '/install/index.php?step=4');
        exit;
    }

    // 1. Create Administrator Account
    $adminRes = create_admin_account($pdo, $adminName, $adminEmail, $adminPass);
    if (!$adminRes['success']) {
        $_SESSION['installer_error'] = $adminRes['message'];
        header('Location: ' . BASE_URL . '/install/index.php?step=4');
        exit;
    }

    // 2. Update System Settings
    $settingsToUpdate = [
        'business_name'    => $bizName,
        'business_phone'   => $bizPhone,
        'business_email'   => $bizEmail,
        'business_address' => $bizAddress,
        'timezone'         => $timezone,
        'currency'         => $currency,
        'currency_symbol'  => $currSymbol,
        'date_format'      => $dateFormat
    ];
    update_system_settings($pdo, $settingsToUpdate);

    // 3. Create Installation Lock
    $lockOk = create_installation_lock([
        'installed_by' => $adminEmail,
        'db_name'      => $dbConfig['name']
    ]);

    if (!$lockOk) {
        $_SESSION['installer_error'] = 'Administrator created, but failed to write installation lock to storage/install.lock. Please check write permissions on the storage/ directory.';
        header('Location: ' . BASE_URL . '/install/index.php?step=4');
        exit;
    }

    // 4. Save Final Summary in Session & Clear Secrets
    $_SESSION['installer_completed'] = [
        'admin_name'     => $adminName,
        'admin_email'    => $adminEmail,
        'business_name'  => $bizName,
        'installed_date' => date('M d, Y H:i')
    ];

    unset($_SESSION['installer_db'], $_SESSION['installer_imported'], $_SESSION['installer_csrf'], $_SESSION['installer_error']);

    header('Location: ' . BASE_URL . '/install/index.php?step=5');
    exit;
}

// Fallback
header('Location: ' . BASE_URL . '/install/index.php');
exit;
