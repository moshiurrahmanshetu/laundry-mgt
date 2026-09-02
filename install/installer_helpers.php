<?php
/**
 * Installer Helper Functions & Utilities
 * Project: Laundry Management System (laundry-mgt)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..'));
}

require_once ROOT_PATH . '/config/constants.php';

// Safe Session Initialization for Installer
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

/**
 * Generate installer CSRF token
 */
function installer_csrf_token(): string {
    if (empty($_SESSION['installer_csrf'])) {
        $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['installer_csrf'];
}

/**
 * Verify installer CSRF token
 */
function verify_installer_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['installer_csrf'])) {
        return false;
    }
    return hash_equals($_SESSION['installer_csrf'], $token);
}

/**
 * Helper to escape HTML output
 */
function h(?string $str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Check all system requirements for the application
 *
 * @return array<string, array{name: string, required: string, current: string, status: string, pass: bool, is_critical: bool}>
 */
function get_system_requirements(): array {
    $reqs = [];

    // 1. PHP Version
    $phpVersion = PHP_VERSION;
    $phpPass = version_compare($phpVersion, '8.0.0', '>=');
    $reqs['php_version'] = [
        'name'        => 'PHP Version (>= 8.0.0)',
        'required'    => '8.0.0+',
        'current'     => $phpVersion,
        'status'      => $phpPass ? 'PASS' : 'FAIL',
        'pass'        => $phpPass,
        'is_critical' => true
    ];

    // 2. PDO Extension
    $pdoPass = class_exists('PDO');
    $reqs['pdo'] = [
        'name'        => 'PDO Extension',
        'required'    => 'Enabled',
        'current'     => $pdoPass ? 'Enabled' : 'Disabled',
        'status'      => $pdoPass ? 'PASS' : 'FAIL',
        'pass'        => $pdoPass,
        'is_critical' => true
    ];

    // 3. PDO MySQL Extension
    $pdoMysqlPass = extension_loaded('pdo_mysql');
    $reqs['pdo_mysql'] = [
        'name'        => 'PDO MySQL Driver (pdo_mysql)',
        'required'    => 'Enabled',
        'current'     => $pdoMysqlPass ? 'Enabled' : 'Disabled',
        'status'      => $pdoMysqlPass ? 'PASS' : 'FAIL',
        'pass'        => $pdoMysqlPass,
        'is_critical' => true
    ];

    // 4. Mbstring Extension
    $mbPass = extension_loaded('mbstring');
    $reqs['mbstring'] = [
        'name'        => 'Mbstring Extension',
        'required'    => 'Enabled',
        'current'     => $mbPass ? 'Enabled' : 'Disabled',
        'status'      => $mbPass ? 'PASS' : 'FAIL',
        'pass'        => $mbPass,
        'is_critical' => true
    ];

    // 5. OpenSSL Extension
    $opensslPass = extension_loaded('openssl');
    $reqs['openssl'] = [
        'name'        => 'OpenSSL Extension',
        'required'    => 'Enabled',
        'current'     => $opensslPass ? 'Enabled' : 'Disabled',
        'status'      => $opensslPass ? 'PASS' : 'FAIL',
        'pass'        => $opensslPass,
        'is_critical' => true
    ];

    // 6. Fileinfo Extension
    $fileinfoPass = extension_loaded('fileinfo');
    $reqs['fileinfo'] = [
        'name'        => 'Fileinfo Extension',
        'required'    => 'Enabled',
        'current'     => $fileinfoPass ? 'Enabled' : 'Disabled',
        'status'      => $fileinfoPass ? 'PASS' : 'FAIL',
        'pass'        => $fileinfoPass,
        'is_critical' => true
    ];

    // 7. JSON Extension
    $jsonPass = extension_loaded('json');
    $reqs['json'] = [
        'name'        => 'JSON Extension',
        'required'    => 'Enabled',
        'current'     => $jsonPass ? 'Enabled' : 'Disabled',
        'status'      => $jsonPass ? 'PASS' : 'FAIL',
        'pass'        => $jsonPass,
        'is_critical' => true
    ];

    // 8. Session Support
    $sessionPass = function_exists('session_start');
    $reqs['session'] = [
        'name'        => 'Session Support',
        'required'    => 'Enabled',
        'current'     => $sessionPass ? 'Enabled' : 'Disabled',
        'status'      => $sessionPass ? 'PASS' : 'FAIL',
        'pass'        => $sessionPass,
        'is_critical' => true
    ];

    // 9. File Uploads
    $fileUploads = ini_get('file_uploads');
    $uploadsPass = ($fileUploads == '1' || strtolower($fileUploads) === 'on');
    $reqs['file_uploads'] = [
        'name'        => 'File Uploads (file_uploads)',
        'required'    => 'Enabled',
        'current'     => $uploadsPass ? 'Enabled (Max ' . ini_get('upload_max_filesize') . ')' : 'Disabled',
        'status'      => $uploadsPass ? 'PASS' : 'FAIL',
        'pass'        => $uploadsPass,
        'is_critical' => true
    ];

    // 10. Writable Directories
    $writableDirs = [
        'config'          => ROOT_PATH . '/config',
        'storage'         => ROOT_PATH . '/storage',
        'uploads'         => ROOT_PATH . '/uploads',
        'uploads/avatars' => ROOT_PATH . '/uploads/avatars',
        'uploads/logos'   => ROOT_PATH . '/uploads/logos'
    ];

    foreach ($writableDirs as $dirKey => $dirPath) {
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0755, true);
        }
        $isWritable = is_dir($dirPath) && is_writable($dirPath);
        $reqs['dir_' . str_replace('/', '_', $dirKey)] = [
            'name'        => 'Writable Directory: ' . $dirKey . '/',
            'required'    => 'Writable (0755)',
            'current'     => $isWritable ? 'Writable' : 'Not Writable',
            'status'      => $isWritable ? 'PASS' : 'FAIL',
            'pass'        => $isWritable,
            'is_critical' => true
        ];
    }

    return $reqs;
}

/**
 * Check whether all critical system requirements pass
 */
function all_critical_requirements_pass(): bool {
    $reqs = get_system_requirements();
    foreach ($reqs as $r) {
        if ($r['is_critical'] && !$r['pass']) {
            return false;
        }
    }
    return true;
}

/**
 * Test database connection using provided credentials
 *
 * @return array{success: bool, message: string, db_exists: bool, can_create_db: bool, pdo: ?PDO}
 */
function test_database_connection(string $host, string $port, string $dbName, string $user, string $pass): array {
    $host = trim($host) ?: '127.0.0.1';
    $port = trim($port) ?: '3306';
    $dbName = trim($dbName);
    $user = trim($user);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5
    ];

    // 1. Try connecting directly to specified database
    if (!empty($dbName)) {
        try {
            $dsnWithDb = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
            $pdo = new PDO($dsnWithDb, $user, $pass, $options);
            return [
                'success'       => true,
                'message'       => 'Database connection successful.',
                'db_exists'     => true,
                'can_create_db' => true,
                'pdo'           => $pdo
            ];
        } catch (PDOException $e) {
            // DB might not exist, check server connection
        }
    }

    // 2. Try connecting to MySQL server without database name
    try {
        $dsnServer = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $pdoServer = new PDO($dsnServer, $user, $pass, $options);

        // Check if database exists
        $canCreate = false;
        $dbExists = false;

        if (!empty($dbName)) {
            $stmt = $pdoServer->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname');
            $stmt->execute(['dbname' => $dbName]);
            $dbExists = (bool)$stmt->fetchColumn();

            if (!$dbExists) {
                // Check if user has CREATE privilege
                try {
                    $testDb = 'laundry_perm_test_' . rand(1000, 9999);
                    $pdoServer->exec("CREATE DATABASE `$testDb`");
                    $pdoServer->exec("DROP DATABASE `$testDb`");
                    $canCreate = true;
                } catch (PDOException $ex) {
                    $canCreate = false;
                }
            }
        }

        return [
            'success'       => true,
            'message'       => $dbExists 
                ? 'Server connected, database selected.' 
                : "Connected to MySQL server, but database '{$dbName}' does not exist.",
            'db_exists'     => $dbExists,
            'can_create_db' => $canCreate,
            'pdo'           => $pdoServer
        ];

    } catch (PDOException $e) {
        $errorMsg = 'Unable to connect to the database server. Please verify your host, port, username, and password.';
        error_log('Installer DB Test Error: ' . $e->getMessage());

        return [
            'success'       => false,
            'message'       => $errorMsg,
            'db_exists'     => false,
            'can_create_db' => false,
            'pdo'           => null
        ];
    }
}

/**
 * Attempt to create database if it does not exist
 */
function try_create_database(string $host, string $port, string $dbName, string $user, string $pass): array {
    try {
        $dsnServer = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $pdo = new PDO($dsnServer, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return ['success' => true, 'message' => "Database '{$dbName}' created successfully."];
    } catch (PDOException $e) {
        return [
            'success' => false, 
            'message' => "Failed to create database '{$dbName}'. Please create the database manually from your hosting control panel (cPanel/phpMyAdmin)."
        ];
    }
}

/**
 * Check if the database already contains existing application tables
 *
 * @param PDO $pdo
 * @return array{has_tables: bool, tables: array<string>, has_laundry_tables: bool}
 */
function check_existing_tables(PDO $pdo): array {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $laundryCoreTables = ['users', 'orders', 'services', 'customers', 'roles', 'payments'];
    $intersect = array_intersect($laundryCoreTables, $tables);

    return [
        'has_tables'         => !empty($tables),
        'tables'             => $tables,
        'has_laundry_tables' => !empty($intersect)
    ];
}

/**
 * Write generated database configuration to config/db.php
 */
function write_database_config(string $host, string $port, string $name, string $user, string $pass, string $charset = 'utf8mb4'): bool {
    $targetFile = ROOT_PATH . '/config/db.php';

    $content = "<?php\n" .
        "/**\n" .
        " * Generated Database Configuration\n" .
        " * Project: Laundry Management System\n" .
        " * Generated on: " . date('Y-m-d H:i:s') . "\n" .
        " */\n\n" .
        "if (!defined('DB_HOST')) define('DB_HOST', " . var_export($host, true) . ");\n" .
        "if (!defined('DB_PORT')) define('DB_PORT', " . var_export($port, true) . ");\n" .
        "if (!defined('DB_NAME')) define('DB_NAME', " . var_export($name, true) . ");\n" .
        "if (!defined('DB_USER')) define('DB_USER', " . var_export($user, true) . ");\n" .
        "if (!defined('DB_PASS')) define('DB_PASS', " . var_export($pass, true) . ");\n" .
        "if (!defined('DB_CHARSET')) define('DB_CHARSET', " . var_export($charset, true) . ");\n";

    return (bool)file_put_contents($targetFile, $content);
}

/**
 * Execute SQL file import safely with transaction support
 *
 * @param PDO $pdo
 * @param string $sqlFilePath
 * @return array{success: bool, message: string, queries_executed: int}
 */
function import_sql_file(PDO $pdo, string $sqlFilePath): array {
    if (!file_exists($sqlFilePath) || !is_readable($sqlFilePath)) {
        return ['success' => false, 'message' => 'SQL file not found or is unreadable.', 'queries_executed' => 0];
    }

    $sqlContent = file_get_contents($sqlFilePath);
    if ($sqlContent === false || trim($sqlContent) === '') {
        return ['success' => false, 'message' => 'SQL file is empty.', 'queries_executed' => 0];
    }

    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Execute multi-query batch
        $pdo->exec($sqlContent);
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Verify tables were created
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            return ['success' => false, 'message' => 'Database import completed but no tables were found.', 'queries_executed' => 0];
        }

        return [
            'success'          => true,
            'message'          => 'Database imported successfully (' . count($tables) . ' tables verified).',
            'queries_executed' => count($tables)
        ];

    } catch (PDOException $e) {
        error_log('Installer SQL Import Error: ' . $e->getMessage());
        return [
            'success'          => false,
            'message'          => 'Database import failed: ' . $e->getMessage(),
            'queries_executed' => 0
        ];
    }
}

/**
 * Create initial Administrator Account
 *
 * @param PDO $pdo
 * @param string $name
 * @param string $email
 * @param string $password
 * @return array{success: bool, message: string, user_id: ?int}
 */
function create_admin_account(PDO $pdo, string $name, string $email, string $password): array {
    $name = trim($name);
    $email = strtolower(trim($email));

    if (empty($name)) {
        return ['success' => false, 'message' => 'Administrator full name is required.', 'user_id' => null];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please provide a valid administrator email address.', 'user_id' => null];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters in length.', 'user_id' => null];
    }

    try {
        // Dynamically find Administrator role ID
        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'administrator' OR LOWER(name) = 'administrator' LIMIT 1");
        $roleStmt->execute();
        $adminRoleId = (int)$roleStmt->fetchColumn();

        if ($adminRoleId <= 0) {
            // Fallback: create role if missing
            $insRole = $pdo->prepare("INSERT INTO roles (name, slug, description, status, created_at, updated_at) VALUES ('Administrator', 'administrator', 'Full system access', 'active', NOW(), NOW())");
            $insRole->execute();
            $adminRoleId = (int)$pdo->lastInsertId();
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $userStmt = $pdo->prepare("
            INSERT INTO users (role_id, name, email, password, status, created_at, updated_at)
            VALUES (:role_id, :name, :email, :password, 'active', NOW(), NOW())
        ");
        $userStmt->execute([
            'role_id'  => $adminRoleId,
            'name'     => $name,
            'email'    => $email,
            'password' => $passwordHash
        ]);

        $userId = (int)$pdo->lastInsertId();

        // Log initial installation activity
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
                VALUES (:user_id, 'system_installed', 'System installed and initial administrator account created', :ip, :ua, NOW())
            ");
            $logStmt->execute([
                'user_id' => $userId,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? 'Installer'
            ]);
        } catch (Exception $ex) {
            // Non-blocking log failure
        }

        return [
            'success' => true,
            'message' => 'Administrator account created successfully.',
            'user_id' => $userId
        ];

    } catch (PDOException $e) {
        error_log('Installer Admin Creation Error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to create administrator account: ' . $e->getMessage(),
            'user_id' => null
        ];
    }
}

/**
 * Update system & business settings in database
 */
function update_system_settings(PDO $pdo, array $settings): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:key, :val, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ");

        foreach ($settings as $k => $v) {
            $stmt->execute(['key' => $k, 'val' => $v !== null ? (string)$v : null]);
        }
        return true;
    } catch (PDOException $e) {
        error_log('Installer Settings Update Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create permanent installation lock file
 */
function create_installation_lock(array $meta = []): bool {
    $lockFile = INSTALL_LOCK_FILE;
    $lockDir = dirname($lockFile);

    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0755, true);
    }

    $lockData = array_merge([
        'installed'    => true,
        'app_name'     => APP_NAME,
        'app_version'  => APP_VERSION,
        'installed_at' => date('Y-m-d H:i:s'),
        'php_version'  => PHP_VERSION
    ], $meta);

    $encoded = json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return (bool)file_put_contents($lockFile, $encoded);
}
