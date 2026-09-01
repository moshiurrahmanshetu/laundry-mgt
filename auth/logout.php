<?php
/**
 * User Logout Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Log activity before destroying session
if (!empty($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())
        ');
        $stmt->execute([
            'user_id'     => $_SESSION['user_id'],
            'action'      => 'user_logout',
            'description' => 'User signed out of session',
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log logout activity: ' . $e->getMessage());
    }
}

// Unset all session values
$_SESSION = [];

// Destroy session cookie in browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session on server
session_destroy();

// Start a fresh new session to carry the flash message
session_start();
$_SESSION['flash_messages'][] = [
    'type'    => 'info',
    'message' => 'You have been signed out successfully.'
];

header('Location: ' . BASE_URL . '/auth/login.php');
exit;
