-- ==============================================================================
-- Database Schema: Phase 10 Staff & Roles Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- 1. Safely Alter `users` table to add soft-delete tracking
-- ------------------------------------------------------------------------------
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'laundry_mgt' 
  AND TABLE_NAME = 'users' 
  AND COLUMN_NAME = 'deleted_at';

SET @stmt = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`, ADD INDEX `idx_users_deleted_at` (`deleted_at`);', 'SELECT 1;');
PREPARE stmt_exec FROM @stmt;
EXECUTE stmt_exec;
DEALLOCATE PREPARE stmt_exec;

-- ------------------------------------------------------------------------------
-- 2. Safely Alter `roles` table to add status and soft-delete tracking
-- ------------------------------------------------------------------------------
SET @status_exists = 0;
SELECT COUNT(*) INTO @status_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'laundry_mgt' 
  AND TABLE_NAME = 'roles' 
  AND COLUMN_NAME = 'status';

SET @stmt = IF(@status_exists = 0, 'ALTER TABLE `roles` ADD COLUMN `status` ENUM(\'active\', \'inactive\') NOT NULL DEFAULT \'active\' AFTER `description`, ADD INDEX `idx_roles_status` (`status`);', 'SELECT 1;');
PREPARE stmt_exec FROM @stmt;
EXECUTE stmt_exec;
DEALLOCATE PREPARE stmt_exec;

SET @del_exists = 0;
SELECT COUNT(*) INTO @del_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'laundry_mgt' 
  AND TABLE_NAME = 'roles' 
  AND COLUMN_NAME = 'deleted_at';

SET @stmt = IF(@del_exists = 0, 'ALTER TABLE `roles` ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`, ADD INDEX `idx_roles_deleted_at` (`deleted_at`);', 'SELECT 1;');
PREPARE stmt_exec FROM @stmt;
EXECUTE stmt_exec;
DEALLOCATE PREPARE stmt_exec;

-- ------------------------------------------------------------------------------
-- 3. Table: permissions
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_permissions_module` (`module`),
    INDEX `idx_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: role_permissions (Junction Table)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_role_permissions_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Seed Module-Oriented Permissions
-- ------------------------------------------------------------------------------
INSERT INTO `permissions` (`module`, `name`, `slug`, `description`) VALUES
-- Customers
('Customers', 'View Customers', 'customers.view', 'View customer profiles, contacts and history'),
('Customers', 'Create Customer', 'customers.create', 'Add new customer accounts'),
('Customers', 'Edit Customer', 'customers.edit', 'Update customer details and preferences'),
('Customers', 'Delete Customer', 'customers.delete', 'Soft-delete customer records'),

-- Services & Pricing
('Services', 'View Services', 'services.view', 'View service catalog and item pricing'),
('Services', 'Manage Services', 'services.manage', 'Create, edit, toggle and delete services and items'),

-- Orders
('Orders', 'View Orders', 'orders.view', 'View laundry order lists and details'),
('Orders', 'Create Order', 'orders.create', 'Intake new laundry orders and calculate prices'),
('Orders', 'Edit Order', 'orders.edit', 'Edit order metadata, items, and instructions'),
('Orders', 'Delete Order', 'orders.delete', 'Soft-delete order records'),

-- Payments
('Payments', 'View Payments', 'payments.view', 'View payment transaction history and vouchers'),
('Payments', 'Manage Payments', 'payments.manage', 'Collect payments and manage financial records'),
('Payments', 'Void Payments', 'payments.void', 'Void existing payment transactions and restore balances'),

-- Pickup & Delivery
('Delivery', 'View Schedules', 'delivery.view', 'View pickup and delivery logistics schedules'),
('Delivery', 'Manage Schedules', 'delivery.manage', 'Create, update, assign drivers and dispatch orders'),

-- Operations
('Operations', 'View Operations Queue', 'operations.view', 'View operational laundry stages and workflow'),
('Operations', 'Manage Operations', 'operations.manage', 'Advance wash/dry/iron stages and manage work orders'),

-- Reports
('Reports', 'View Operational Reports', 'reports.view', 'View overview, orders, services, and delivery reports'),
('Reports', 'View Financial Reports', 'reports.financial', 'View sales, revenue, and payment analytics'),

-- Expenses
('Expenses', 'View Expenses', 'expenses.view', 'View business operational expenses and vouchers'),
('Expenses', 'Manage Expenses', 'expenses.manage', 'Record, edit, delete expenses and manage categories'),

-- Staff & Roles
('Staff', 'View Staff', 'staff.view', 'View employee and user accounts directory'),
('Staff', 'Manage Staff', 'staff.manage', 'Create, edit, activate/deactivate, and delete staff accounts'),
('Roles', 'Manage Roles & Permissions', 'roles.manage', 'Create roles, configure role permissions, and assign access')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- ------------------------------------------------------------------------------
-- 6. Assign Default Permissions to Roles
-- Administrator gets ALL permissions.
-- Manager gets operational, reporting, orders, payments, delivery, expenses.
-- Staff gets operational intake, processing, delivery, and basic customer view.
-- ------------------------------------------------------------------------------

-- Administrator (Role ID = 1) -> All Permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Manager (Role ID = 2)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions`
WHERE `slug` IN (
    'customers.view', 'customers.create', 'customers.edit',
    'services.view', 'services.manage',
    'orders.view', 'orders.create', 'orders.edit',
    'payments.view', 'payments.manage',
    'delivery.view', 'delivery.manage',
    'operations.view', 'operations.manage',
    'reports.view', 'reports.financial',
    'expenses.view', 'expenses.manage',
    'staff.view'
);

-- Staff (Role ID = 3)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions`
WHERE `slug` IN (
    'customers.view', 'customers.create',
    'services.view',
    'orders.view', 'orders.create',
    'delivery.view',
    'operations.view', 'operations.manage',
    'reports.view'
);
