-- ==============================================================================
-- Database Schema: Phase 02 Customer Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- Table structure for `customers`
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_code` (`customer_code`),
  KEY `idx_customers_phone` (`phone`),
  KEY `idx_customers_email` (`email`),
  KEY `idx_customers_status` (`status`),
  KEY `idx_customers_created_by` (`created_by`),
  KEY `idx_customers_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Sample Customer Data for Development
-- ------------------------------------------------------------------------------
INSERT INTO `customers` (`customer_code`, `name`, `phone`, `email`, `address`, `city`, `postal_code`, `notes`, `status`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
('CUS-000001', 'Rahim Ahmed', '+880 1711 000001', 'rahim.ahmed@example.com', 'House 12, Road 5, Dhanmondi', 'Dhaka', '1205', 'Prefers gentle wash for cotton shirts', 'active', 1, NOW(), NOW(), NULL),
('CUS-000002', 'Fatima Begum', '+880 1819 000002', 'fatima.b@example.com', 'Flat 4A, Green Peace Apt, Banani', 'Dhaka', '1213', 'Express delivery on weekends', 'active', 1, NOW(), NOW(), NULL),
('CUS-000003', 'Karim Chowdhury', '+880 1912 000003', 'karim.c@example.com', 'Plot 45, Sector 7, Uttara', 'Dhaka', '1230', 'Starch on formal trousers', 'active', 1, NOW(), NOW(), NULL),
('CUS-000004', 'Nusrat Jahan', '+880 1613 000004', 'nusrat.j@example.com', 'House 88, Block C, Gulshan-2', 'Dhaka', '1212', 'Dry clean only for silk dresses', 'inactive', 1, NOW(), NOW(), NULL),
('CUS-000005', 'Tanvir Hasan', '+880 1514 000005', 'tanvir.h@example.com', '22/A Mirpur Road, Kallyanpur', 'Dhaka', '1207', 'Commercial client - restaurant linens', 'active', 1, NOW(), NOW(), NULL);
