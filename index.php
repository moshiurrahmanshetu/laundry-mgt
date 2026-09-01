<?php
/**
 * Application Entry Point
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
} else {
    redirect('auth/login.php');
}
