<?php
/**
 * Authentication Guard
 * Included by protected pages to ensure only authenticated users can access.
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/functions.php';

if (!is_app_installed()) {
    redirect('install/index.php');
}

// Prevent browser caching of protected pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

if (!is_logged_in()) {
    set_flash_message('warning', 'Please sign in to access your account.');
    redirect('auth/login.php');
}
