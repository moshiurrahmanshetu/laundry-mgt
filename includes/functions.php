<?php
/**
 * Global Helper Functions & Security Baseline
 * Project: Laundry Management System (laundry-mgt)
 */

// Load config and flash message system
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/flash_message.php';

/**
 * Escape HTML output to prevent XSS
 *
 * @param mixed $string
 * @return string
 */
function e(mixed $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate full URL within the Laundry Management System
 *
 * @param string $path
 * @return string
 */
function base_url(string $path = ''): string {
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    return $path !== '' ? $base . '/' . $path : $base;
}

/**
 * Generate asset URL
 *
 * @param string $path
 * @return string
 */
function asset_url(string $path = ''): string {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Safe redirect to relative application path or external URL
 *
 * @param string $path
 * @return void
 */
function redirect(string $path): void {
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $path = base_url($path);
    }
    header('Location: ' . $path);
    exit;
}

/**
 * Get or create CSRF token
 *
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden CSRF form input field
 *
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST or passed parameter
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the user is authenticated
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user details with role join
 *
 * @param bool $fresh
 * @return array|null
 */
function current_user(bool $fresh = false): ?array {
    if (!is_logged_in()) {
        return null;
    }

    if (!$fresh && !empty($_SESSION['user_cache'])) {
        return $_SESSION['user_cache'];
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            SELECT u.id, u.role_id, r.name AS role_name, r.slug AS role_slug,
                   u.name, u.email, u.phone, u.avatar, u.status, u.last_login, u.created_at
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_cache'] = $user;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_name'];
            $_SESSION['role_slug'] = $user['role_slug'];
            return $user;
        }
    } catch (PDOException $e) {
        error_log('Error loading current user: ' . $e->getMessage());
    }

    return [
        'id'         => $_SESSION['user_id'] ?? 0,
        'role_id'    => $_SESSION['role_id'] ?? 1,
        'role_name'  => $_SESSION['user_role'] ?? 'Administrator',
        'role_slug'  => $_SESSION['role_slug'] ?? 'administrator',
        'name'       => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'phone'      => '',
        'avatar'     => null,
        'status'     => 'active',
        'last_login' => null
    ];
}

/**
 * Role authorization helper
 *
 * @param string|array $roles
 * @return bool
 */
function has_role(string|array $roles): bool {
    $user = current_user();
    if (!$user) return false;

    $userRoleSlug = $user['role_slug'] ?? '';
    $userRoleName = $user['role_name'] ?? '';

    if (is_string($roles)) {
        $roles = [$roles];
    }

    foreach ($roles as $r) {
        $rNorm = strtolower(trim($r));
        if ($rNorm === strtolower($userRoleSlug) || $rNorm === strtolower($userRoleName)) {
            return true;
        }
    }

    return false;
}

function is_admin(): bool {
    return has_role('administrator');
}

function is_manager(): bool {
    return has_role('manager');
}

function is_staff(): bool {
    return has_role('staff');
}

/**
 * Record activity/audit log
 *
 * @param int|null $userId
 * @param string $action
 * @param string|null $description
 * @return void
 */
function log_activity(?int $userId, string $action, ?string $description = null): void {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())
        ');
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * Sanitize text input
 *
 * @param mixed $data
 * @return string
 */
function sanitize_input(mixed $data): string {
    return trim((string)($data ?? ''));
}

/**
 * Format datetime cleanly
 *
 * @param string|null $datetime
 * @param string $format
 * @return string
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) {
        return 'Never';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$datetime;
    }
}

/**
 * Get avatar URL with fallback
 *
 * @param string|null $avatarFilename
 * @return string
 */
function user_avatar_url(?string $avatarFilename): string {
    if (!empty($avatarFilename)) {
        $fullPath = AVATAR_PATH . DIRECTORY_SEPARATOR . $avatarFilename;
        if (file_exists($fullPath)) {
            return AVATAR_URL . '/' . $avatarFilename;
        }
    }
    return asset_url('images/default-avatar.svg');
}

/**
 * Extract 2-letter uppercase initials from full name
 *
 * @param string $name
 * @return string
 */
function get_user_initials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
    }
    return $initials ?: 'U';
}
