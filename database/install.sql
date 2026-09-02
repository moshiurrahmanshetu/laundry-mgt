-- ==============================================================================
-- Laundry Management System
-- Fresh Installation Database Schema & Core Reference Seed Data
-- Generated from all consolidated development phases (Phases 01 - 11)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. ROLES TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `deleted_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. PERMISSIONS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. ROLE_PERMISSIONS JUNCTION TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. USERS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `password` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. PASSWORD RESETS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `token` VARCHAR(100) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_email` (`email`),
    INDEX `idx_pr_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. ACTIVITY LOGS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. CUSTOMERS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NULL,
    `phone` VARCHAR(30) NOT NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL,
    `postal_code` VARCHAR(20) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_cus_phone` (`phone`),
    INDEX `idx_cus_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. SERVICES TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `pricing_type` ENUM('per_item', 'per_kg') NOT NULL DEFAULT 'per_item',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. SERVICE ITEMS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `service_id` INT NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'piece',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_si_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. ORDERS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `order_date` DATE NOT NULL,
    `expected_date` DATE NULL,
    `status` ENUM('received', 'processing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT 'received',
    `payment_status` ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_type` ENUM('none', 'percentage', 'fixed') NOT NULL DEFAULT 'none',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `due_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_ord_customer` (`customer_id`),
    INDEX `idx_ord_status` (`status`),
    INDEX `idx_ord_date` (`order_date`),
    CONSTRAINT `fk_ord_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ord_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. ORDER ITEMS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `service_id` INT NULL,
    `service_item_id` INT NULL,
    `service_name` VARCHAR(100) NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'piece',
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. PAYMENTS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_number` VARCHAR(50) NOT NULL UNIQUE,
    `order_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'card', 'mobile_banking', 'bank_transfer', 'other') NOT NULL DEFAULT 'cash',
    `transaction_reference` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `status` ENUM('completed', 'voided') NOT NULL DEFAULT 'completed',
    `received_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pay_order` (`order_id`),
    INDEX `idx_pay_date` (`payment_date`),
    CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pay_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. PICKUP & DELIVERIES TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pickup_deliveries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_number` VARCHAR(50) NOT NULL UNIQUE,
    `order_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `type` ENUM('pickup', 'delivery') NOT NULL DEFAULT 'pickup',
    `assigned_to` INT NULL,
    `service_address` TEXT NOT NULL,
    `contact_name` VARCHAR(100) NOT NULL,
    `contact_phone` VARCHAR(30) NOT NULL,
    `scheduled_date` DATE NOT NULL,
    `scheduled_time` TIME NULL,
    `status` ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `completed_at` DATETIME NULL,
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_pd_order` (`order_id`),
    INDEX `idx_pd_customer` (`customer_id`),
    INDEX `idx_pd_status` (`status`),
    INDEX `idx_pd_sched_date` (`scheduled_date`),
    CONSTRAINT `fk_pd_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_pd_staff` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pd_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 14. EXPENSE CATEGORIES TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 15. EXPENSES TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_number` VARCHAR(50) NOT NULL UNIQUE,
    `category_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `expense_date` DATE NOT NULL,
    `payment_method` ENUM('cash', 'card', 'mobile_banking', 'bank_transfer', 'other') NOT NULL DEFAULT 'cash',
    `description` TEXT NULL,
    `paid_by` VARCHAR(100) NULL,
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_exp_category` (`category_id`),
    INDEX `idx_exp_date` (`expense_date`),
    CONSTRAINT `fk_exp_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_exp_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 16. SYSTEM SETTINGS TABLE
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- CORE REFERENCE SEED DATA
-- ==============================================================================

-- Seed Core Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'administrator', 'Full system access and administration privileges', 'active', NOW(), NOW()),
(2, 'Manager', 'manager', 'Operational and staff management access', 'active', NOW(), NOW()),
(3, 'Staff', 'staff', 'Order handling, operations, and fulfillment access', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Granular Module Permissions
INSERT INTO `permissions` (`module`, `name`, `slug`, `description`, `created_at`) VALUES
('dashboard', 'View Dashboard', 'dashboard.view', 'View dashboard metrics and analytics overview', NOW()),
('customers', 'View Customers', 'customers.view', 'View customer listings and profile details', NOW()),
('customers', 'Manage Customers', 'customers.manage', 'Create, update, and toggle customer accounts', NOW()),
('customers', 'Delete Customers', 'customers.delete', 'Soft delete customer records', NOW()),
('services', 'View Services', 'services.view', 'View laundry service catalog and item rates', NOW()),
('services', 'Manage Services', 'services.manage', 'Create, update, and manage service categories and rates', NOW()),
('orders', 'View Orders', 'orders.view', 'View laundry orders and invoice details', NOW()),
('orders', 'Create Orders', 'orders.create', 'Create new laundry intake orders', NOW()),
('orders', 'Manage Orders', 'orders.manage', 'Edit order items, rates, and schedule details', NOW()),
('orders', 'Delete Orders', 'orders.delete', 'Soft delete order records', NOW()),
('payments', 'View Payments', 'payments.view', 'View payment transaction ledger and vouchers', NOW()),
('payments', 'Collect Payments', 'payments.collect', 'Record new payment installments against orders', NOW()),
('payments', 'Void Payments', 'payments.void', 'Void completed payment transactions', NOW()),
('delivery', 'View Deliveries', 'delivery.view', 'View pickup and delivery dispatch schedules', NOW()),
('delivery', 'Manage Deliveries', 'delivery.manage', 'Create, reschedule, and assign pickup/delivery requests', NOW()),
('delivery', 'Update Status', 'delivery.status', 'Update logistics delivery completion status', NOW()),
('operations', 'View Operations', 'operations.view', 'View operational laundry queue and workflow board', NOW()),
('operations', 'Update Workflow', 'operations.update', 'Advance laundry orders across washing and processing stages', NOW()),
('reports', 'View Operational Reports', 'reports.view', 'View general, orders, services, and logistics reports', NOW()),
('reports', 'View Financial Reports', 'reports.financial', 'View sales, revenue, and payment collections reports', NOW()),
('expenses', 'View Expenses', 'expenses.view', 'View operating expenses and expense categories', NOW()),
('expenses', 'Manage Expenses', 'expenses.manage', 'Record, edit, and manage expenses and categories', NOW()),
('staff', 'Manage Staff & Roles', 'staff.manage', 'Manage staff accounts, custom roles, and permission matrix', NOW()),
('settings', 'View Settings', 'settings.view', 'View system settings and business configuration', NOW()),
('settings', 'Manage Settings', 'settings.manage', 'Update business profile, regional defaults, and layout options', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Map Permissions to Roles
-- 1. Administrator gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

-- 2. Manager Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug IN (
    'dashboard.view',
    'customers.view', 'customers.manage',
    'services.view', 'services.manage',
    'orders.view', 'orders.create', 'orders.manage',
    'payments.view', 'payments.collect',
    'delivery.view', 'delivery.manage', 'delivery.status',
    'operations.view', 'operations.update',
    'reports.view', 'reports.financial',
    'expenses.view', 'expenses.manage'
)
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

-- 3. Staff Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, p.id FROM `permissions` p
WHERE p.slug IN (
    'dashboard.view',
    'customers.view',
    'services.view',
    'orders.view', 'orders.create',
    'payments.view', 'payments.collect',
    'delivery.view', 'delivery.status',
    'operations.view', 'operations.update',
    'reports.view'
)
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);

-- Seed Default Service Categories
INSERT INTO `services` (`id`, `name`, `slug`, `description`, `pricing_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Wash & Fold', 'wash-and-fold', 'Standard machine washing, tumble drying, and neat folding for everyday clothing.', 'per_item', 'active', NOW(), NOW()),
(2, 'Dry Cleaning', 'dry-cleaning', 'Specialized solvent-based cleaning for delicate, formal, and structured garments.', 'per_item', 'active', NOW(), NOW()),
(3, 'Steam Press & Ironing', 'steam-press-ironing', 'Professional crisp steam pressing and wrinkle removal.', 'per_item', 'active', NOW(), NOW()),
(4, 'Bulk Washing (By Weight)', 'bulk-washing-by-weight', 'Economical bulk laundry washed and dried calculated by total kilogram weight.', 'per_kg', 'active', NOW(), NOW()),
(5, 'Stain Removal & Treatment', 'stain-removal-treatment', 'Intensive spot and stain pretreatment for stubborn blemishes.', 'per_item', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Default Service Pricing Items
INSERT INTO `service_items` (`service_id`, `item_name`, `unit`, `price`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Shirt / T-Shirt', 'piece', 30.00, 'active', 1, NOW(), NOW()),
(1, 'Pants / Trousers / Jeans', 'piece', 40.00, 'active', 2, NOW(), NOW()),
(1, 'Bedsheet (Single / Double)', 'piece', 80.00, 'active', 3, NOW(), NOW()),
(1, 'Undergarments / Socks', 'pair', 15.00, 'active', 4, NOW(), NOW()),
(2, 'Suit (2-Piece)', 'piece', 250.00, 'active', 1, NOW(), NOW()),
(2, 'Blazer / Coat', 'piece', 180.00, 'active', 2, NOW(), NOW()),
(2, 'Dress / Gown', 'piece', 200.00, 'active', 3, NOW(), NOW()),
(2, 'Curtain / Drapes', 'sq_ft', 25.00, 'active', 4, NOW(), NOW()),
(3, 'Shirt / Kurta Ironing', 'piece', 15.00, 'active', 1, NOW(), NOW()),
(3, 'Pants / Trouser Ironing', 'piece', 15.00, 'active', 2, NOW(), NOW()),
(3, 'Saree Steam Press', 'piece', 70.00, 'active', 3, NOW(), NOW()),
(4, 'Regular Mixed Laundry (Min 3 KG)', 'kg', 60.00, 'active', 1, NOW(), NOW()),
(4, 'Heavy Towels & Linens', 'kg', 75.00, 'active', 2, NOW(), NOW()),
(5, 'Spot & Stain Removal Treatment', 'piece', 50.00, 'active', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `item_name` = VALUES(`item_name`);

-- Seed Default Expense Categories
INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Electricity & Utilities', 'Electricity, gas, and power bills for laundry machines and irons', 'active', NOW(), NOW()),
(2, 'Water & Sewage', 'Water supply and municipal wastewater charges', 'active', NOW(), NOW()),
(3, 'Rent & Facility', 'Monthly shop, branch, or factory premises lease', 'active', NOW(), NOW()),
(4, 'Cleaning Chemicals & Detergent', 'Industrial detergents, softeners, bleach, stain removers, and solvents', 'active', NOW(), NOW()),
(5, 'Transportation & Fuel', 'Delivery van/bike fuel, driver allowances, and vehicle tolls', 'active', NOW(), NOW()),
(6, 'Maintenance & Repairs', 'Washing machine, dryer, and boiler servicing and spare parts', 'active', NOW(), NOW()),
(7, 'Staff Salaries & Wages', 'Monthly payroll, operational bonuses, and staff allowances', 'active', NOW(), NOW()),
(8, 'Packaging Materials', 'Plastic poly bags, hangers, garment tags, and packaging supplies', 'active', NOW(), NOW()),
(9, 'Other Operational Expenses', 'Miscellaneous day-to-day laundry shop operating costs', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Default System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
('business_name', 'Laundry Management System', NOW(), NOW()),
('business_phone', '+880 1700 000000', NOW(), NOW()),
('business_email', 'support@laundrymgt.com', NOW(), NOW()),
('business_address', '123 Commercial Avenue, Downtown', NOW(), NOW()),
('business_website', 'https://example.com', NOW(), NOW()),
('business_description', 'Professional Laundry & Dry Cleaning Services', NOW(), NOW()),
('business_logo', NULL, NOW(), NOW()),
('timezone', 'Asia/Dhaka', NOW(), NOW()),
('date_format', 'd/m/Y', NOW(), NOW()),
('currency', 'BDT', NOW(), NOW()),
('currency_symbol', '$', NOW(), NOW()),
('invoice_prefix', 'INV-', NOW(), NOW()),
('receipt_prefix', 'REC-', NOW(), NOW()),
('invoice_footer', 'Thank you for choosing our laundry services! Please keep this receipt for laundry pickup.', NOW(), NOW()),
('show_business_logo', '1', NOW(), NOW()),
('show_business_address', '1', NOW(), NOW()),
('show_business_phone', '1', NOW(), NOW())
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);

SET FOREIGN_KEY_CHECKS = 1;
