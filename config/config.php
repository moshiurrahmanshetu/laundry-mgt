<?php
/**
 * Master Application Configuration & Session Initializer
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';

// Safe Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    session_set_cookie_params([
        'lifetime' => 0,               // Until browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,           // Protect from XSS cookie access
        'samesite' => 'Lax'           // Protect from CSRF
    ]);

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_start();
}
