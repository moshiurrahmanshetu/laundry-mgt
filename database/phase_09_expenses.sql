-- ==============================================================================
-- Database Schema: Phase 09 Expense Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- 1. Table: expense_categories
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_expense_categories_status` (`status`),
    INDEX `idx_expense_categories_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: expenses
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `reference_number` VARCHAR(50) NOT NULL UNIQUE,
    `category_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `expense_date` DATE NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'cash',
    `description` TEXT NULL,
    `paid_by` VARCHAR(150) NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    INDEX `idx_expenses_ref` (`reference_number`),
    INDEX `idx_expenses_category_id` (`category_id`),
    INDEX `idx_expenses_date` (`expense_date`),
    INDEX `idx_expenses_payment_method` (`payment_method`),
    INDEX `idx_expenses_created_by` (`created_by`),
    INDEX `idx_expenses_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_expenses_category_id` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Default Operational Expense Categories Seed Data
-- ------------------------------------------------------------------------------
INSERT INTO `expense_categories` (`name`, `description`, `status`, `created_at`) VALUES
('Electricity', 'Monthly commercial electricity power utility bill', 'active', NOW()),
('Water & Drainage', 'Water supply and industrial drainage utility bill', 'active', NOW()),
('Shop & Facility Rent', 'Premises and facility lease payments', 'active', NOW()),
('Detergent & Cleaning Supplies', 'Washing powder, fabric softeners, stain removers, bleaching agents', 'active', NOW()),
('Transportation & Fuel', 'Delivery van fuel, parking fees, vehicle maintenance', 'active', NOW()),
('Equipment Maintenance', 'Washer, dryer, boiler, and steam iron repairs and servicing', 'active', NOW()),
('Utilities & Internet', 'Broadband internet, landline, and telephone bills', 'active', NOW()),
('Staff Expense & Meals', 'Staff daily allowances, operational refreshments, and safety gear', 'active', NOW()),
('Packaging & Poly Bags', 'Garment hanger bags, laundry wrapping materials, tags', 'active', NOW()),
('Miscellaneous Expense', 'Sundry operational and administrative expenses', 'active', NOW())
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
