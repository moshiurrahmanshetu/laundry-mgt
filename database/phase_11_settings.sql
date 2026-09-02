-- ==========================================================
-- Laundry Management System (laundry-mgt)
-- Database Migration: Phase 11 - Settings / System Configuration
-- ==========================================================

USE `laundry_mgt`;

-- 1. Create settings key/value table
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed default business and system settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('business_name', 'Laundry Management System'),
('business_phone', '+880 1700 000000'),
('business_email', 'support@laundrymgt.com'),
('business_address', '123 Clean Street, Dhaka, Bangladesh'),
('business_website', 'https://laundrymgt.com'),
('business_description', 'Professional Laundry & Dry Cleaning Services'),
('business_logo', NULL),
('timezone', 'Asia/Dhaka'),
('date_format', 'd/m/Y'),
('currency', 'BDT'),
('currency_symbol', '$'),
('invoice_prefix', 'INV-'),
('receipt_prefix', 'REC-'),
('invoice_footer', 'Thank you for choosing our laundry services! Please keep this receipt for laundry pickup.'),
('show_business_logo', '1'),
('show_business_address', '1'),
('show_business_phone', '1')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- 3. Register Phase 11 Settings Permissions
INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `description`) VALUES
('Settings', 'View Settings', 'settings.view', 'View business profile and system configuration'),
('Settings', 'Manage Settings', 'settings.manage', 'Modify business profile, logo, general preferences, and invoice layouts');

-- 4. Assign Settings Permissions to Administrator Role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'administrator' AND p.slug IN ('settings.view', 'settings.manage');
