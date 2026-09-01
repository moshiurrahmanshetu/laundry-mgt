<?php
/**
 * Authentication Processing Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('auth/login.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Session expired or invalid security token. Please try again.');
    redirect('auth/login.php');
}

// 2. Extract & sanitize inputs
$email = sanitize_input($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

// Basic validation
if (empty($email) || empty($password)) {
    $_SESSION['old_email'] = $email;
    set_flash_message('error', 'Please enter both your email address and password.');
    redirect('auth/login.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['old_email'] = $email;
    set_flash_message('error', 'Invalid email or password.');
    redirect('auth/login.php');
}

try {
    $pdo = getDBConnection();
    
    // 3. Find user with prepared statement joining roles table
    $stmt = $pdo->prepare('
        SELECT u.id, u.role_id, r.name AS role_name, r.slug AS role_slug,
               u.name, u.email, u.phone, u.password, u.avatar, u.status, u.last_login, u.created_at
        FROM users u
        INNER JOIN roles r ON u.role_id = r.id
        WHERE u.email = :email
        LIMIT 1
    ');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // 4. Verify password and active status
    if ($user && password_verify($password, $user['password'])) {
        // Check if account is active
        if ($user['status'] !== 'active') {
            set_flash_message('error', 'Your account has been deactivated. Please contact an administrator.');
            log_activity($user['id'], 'login_failed', 'Attempted login with deactivated account');
            redirect('auth/login.php');
        }

        // 5. Regenerate Session ID to prevent fixation attacks
        session_regenerate_id(true);

        // 6. Store authenticated session data
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['role_id']   = (int)$user['role_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['role_slug'] = $user['role_slug'];
        $_SESSION['logged_in'] = true;
        
        // Remove password hash from cache
        unset($user['password']);
        $_SESSION['user_cache'] = $user;

        // 7. Update last_login timestamp
        try {
            $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            $updateStmt->execute(['id' => $user['id']]);
        } catch (PDOException $e) {
            error_log('Failed to update last_login: ' . $e->getMessage());
        }

        // 8. Log authentication activity
        log_activity($user['id'], 'user_login', 'User logged in successfully');

        set_flash_message('success', 'Welcome back, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!');
        redirect('modules/dashboard/index.php');
    } else {
        // Generic failure message to prevent email enumeration
        $_SESSION['old_email'] = $email;
        log_activity(null, 'login_failed', 'Failed login attempt for email: ' . $email);
        set_flash_message('error', 'Invalid email or password.');
        redirect('auth/login.php');
    }

} catch (PDOException $e) {
    error_log('Authentication DB error: ' . $e->getMessage());
    set_flash_message('error', 'An error occurred during sign in. Please try again later.');
    redirect('auth/login.php');
}
