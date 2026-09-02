<?php
/**
 * Application Entry Point
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/config/constants.php';

// First-run installer check
if (!is_app_installed()) {
    header('Location: ' . BASE_URL . '/install/index.php');
    exit;
}

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
} else {
    redirect('auth/login.php');
}
