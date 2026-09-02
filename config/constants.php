<?php
/**
 * Application Constants
 * Project: Laundry Management System (laundry-mgt)
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Laundry Management System');
}

if (!defined('APP_SHORT_NAME')) {
    define('APP_SHORT_NAME', 'LaundryMgt');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // 'development' or 'production'
}

// File System Paths
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/..'));
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
}

if (!defined('AVATAR_PATH')) {
    define('AVATAR_PATH', UPLOAD_PATH . DIRECTORY_SEPARATOR . 'avatars');
}

if (!defined('LOGO_PATH')) {
    define('LOGO_PATH', UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logos');
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
}

if (!defined('INSTALL_LOCK_FILE')) {
    define('INSTALL_LOCK_FILE', STORAGE_PATH . DIRECTORY_SEPARATOR . 'install.lock');
}

// Ensure required runtime directories exist
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(AVATAR_PATH)) {
    @mkdir(AVATAR_PATH, 0755, true);
}
if (!is_dir(LOGO_PATH)) {
    @mkdir(LOGO_PATH, 0755, true);
}
if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0755, true);
}
if (is_dir(STORAGE_PATH) && !file_exists(STORAGE_PATH . DIRECTORY_SEPARATOR . '.htaccess')) {
    @file_put_contents(
        STORAGE_PATH . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\nDeny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
    );
}

/**
 * Check if the application has completed first-run installation
 */
function is_app_installed(): bool {
    return defined('INSTALL_LOCK_FILE') && file_exists(INSTALL_LOCK_FILE);
}

/**
 * Determine dynamic Base URL for laundry-mgt
 */
function get_base_url(): string {
    if (defined('CUSTOM_BASE_URL') && !empty(CUSTOM_BASE_URL)) {
        return rtrim(CUSTOM_BASE_URL, '/');
    }

    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $projectRoot = ROOT_PATH;

        if ($docRoot && str_starts_with($projectRoot, $docRoot)) {
            $relativeDir = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
            $relativeDir = '/' . trim($relativeDir, '/');
            if ($relativeDir === '/') {
                $relativeDir = '';
            }
            return $protocol . $host . $relativeDir;
        }

        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $parts = explode('/', trim($scriptDir, '/'));
        if (!empty($parts[0])) {
            return $protocol . $host . '/' . $parts[0];
        }
        return $protocol . $host;
    }

    return 'http://localhost/laundry-mgt';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', get_base_url());
}

if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/uploads');
}

if (!defined('AVATAR_URL')) {
    define('AVATAR_URL', UPLOAD_URL . '/avatars');
}

if (!defined('LOGO_URL')) {
    define('LOGO_URL', UPLOAD_URL . '/logos');
}

