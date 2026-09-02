<?php
/**
 * Add Custom Role POST Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/staff/roles.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/staff/roles.php');
}

$name        = sanitize_input($_POST['name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');
$status      = strtolower(sanitize_input($_POST['status'] ?? 'active'));
$userId      = (int)($_SESSION['user_id'] ?? 0) ?: null;

if (empty($name)) {
    set_flash_message('error', 'Role name is required.');
    redirect('modules/staff/roles.php');
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

// Generate unique slug
$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
$slug = trim($slug, '_');

if (empty($slug)) {
    $slug = 'role_' . time();
}

$pdo = getDBConnection();

try {
    // Check name and slug uniqueness
    $checkStmt = $pdo->prepare('SELECT id FROM roles WHERE (name = :name OR slug = :slug) AND deleted_at IS NULL');
    $checkStmt->execute(['name' => $name, 'slug' => $slug]);
    if ($checkStmt->fetch()) {
        set_flash_message('error', 'A role with this name or identifier already exists.');
        redirect('modules/staff/roles.php');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO roles (name, slug, description, status, created_at, updated_at)
        VALUES (:name, :slug, :description, :status, NOW(), NOW())
    ');
    $stmt->execute([
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description ?: null,
        'status'      => $status
    ]);

    $newRoleId = (int)$pdo->lastInsertId();

    log_activity($userId, 'role_created', sprintf('Created custom role %s (%s)', $name, $slug));

    $pdo->commit();

    set_flash_message('success', sprintf('Role <strong>%s</strong> created successfully. You can now configure its permissions.', e($name)));
    redirect('modules/staff/role_permissions.php?id=' . $newRoleId);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Role Store Error: ' . $e->getMessage());
    set_flash_message('error', 'Failed to create role due to a database error.');
    redirect('modules/staff/roles.php');
}
