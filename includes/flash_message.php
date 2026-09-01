<?php
/**
 * Session Flash Message System
 * Project: Laundry Management System (laundry-mgt)
 */

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Set a flash message
 *
 * @param string $type success | error | warning | info
 * @param string $message
 * @return void
 */
function set_flash_message(string $type, string $message): void {
    $type = strtolower($type);
    if ($type === 'danger') {
        $type = 'error';
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

// Alias for convenience
function set_flash(string $type, string $message): void {
    set_flash_message($type, $message);
}

/**
 * Check if flash messages exist
 *
 * @param string|null $type
 * @return bool
 */
function has_flash_message(?string $type = null): bool {
    if (empty($_SESSION['flash_messages'])) {
        return false;
    }
    if ($type === null) {
        return true;
    }
    $type = strtolower($type);
    if ($type === 'danger') $type = 'error';

    foreach ($_SESSION['flash_messages'] as $msg) {
        if ($msg['type'] === $type) {
            return true;
        }
    }
    return false;
}

function has_flash(?string $type = null): bool {
    return has_flash_message($type);
}

/**
 * Retrieve and clear all flash messages
 *
 * @return array
 */
function get_flash_messages(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function get_flash(): array {
    return get_flash_messages();
}

/**
 * Render flash messages as Bootstrap 5 alerts
 *
 * @return string HTML output
 */
function render_flash_messages(): string {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return '';
    }

    $html = '<div class="flash-messages mb-4">';
    foreach ($messages as $item) {
        $type = $item['type'];
        $message = $item['message'];

        $bootstrapClass = match($type) {
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
            default   => 'alert-secondary'
        };

        $icon = match($type) {
            'success' => 'bi-check-circle-fill',
            'error'   => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info'    => 'bi-info-circle-fill',
            default   => 'bi-bell-fill'
        };

        $html .= sprintf(
            '<div class="alert %s alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">' .
            '  <i class="bi %s fs-5 me-2 flex-shrink-0"></i>' .
            '  <div class="flex-grow-1">%s</div>' .
            '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
            '</div>',
            htmlspecialchars($bootstrapClass, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );
    }
    $html .= '</div>';

    return $html;
}

function render_flash(): string {
    return render_flash_messages();
}
