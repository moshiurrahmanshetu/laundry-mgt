<?php
/**
 * Guest Guard
 * Included by guest pages (e.g. login) to redirect already logged-in users.
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    redirect('modules/dashboard/index.php');
}
