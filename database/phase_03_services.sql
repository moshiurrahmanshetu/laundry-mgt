-- ==============================================================================
-- Database Schema: Phase 03 Laundry Services & Pricing Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- 1. Table structure for `services`
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `service_items`;
DROP TABLE IF EXISTS `services`;

CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `pricing_type` ENUM('per_item', 'per_kg') NOT NULL DEFAULT 'per_item',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_services_slug` (`slug`),
  KEY `idx_services_pricing_type` (`pricing_type`),
  KEY `idx_services_status` (`status`),
  KEY `idx_services_created_by` (`created_by`),
  KEY `idx_services_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_services_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table structure for `service_items`
-- ------------------------------------------------------------------------------
CREATE TABLE `service_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `unit` VARCHAR(50) NOT NULL DEFAULT 'item',
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_items_service_id` (`service_id`),
  KEY `idx_service_items_item_name` (`item_name`),
  KEY `idx_service_items_status` (`status`),
  KEY `idx_service_items_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_service_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Default Laundry Services
-- ------------------------------------------------------------------------------
INSERT INTO `services` (`id`, `name`, `slug`, `description`, `pricing_type`, `status`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Wash & Fold', 'wash-fold', 'Standard machine washing, tumble drying, and neat folding for everyday clothing and linens.', 'per_kg', 'active', 1, NOW(), NOW(), NULL),
(2, 'Dry Cleaning', 'dry-cleaning', 'Specialized chemical solvent cleaning for delicate garments, suits, formal wear, and designer fabrics.', 'per_item', 'active', 1, NOW(), NOW(), NULL),
(3, 'Ironing & Pressing', 'ironing-pressing', 'Professional steam and hot-iron pressing for crisp, wrinkle-free clothes.', 'per_item', 'active', 1, NOW(), NOW(), NULL),
(4, 'Steam Press', 'steam-press', 'High-pressure vertical and flatbed steam pressing ideal for suits, jackets, and formal dresses.', 'per_item', 'active', 1, NOW(), NOW(), NULL),
(5, 'Stain Removal', 'stain-removal', 'Targeted spot and deep stain treatment for ink, wine, grease, oil, and organic stains.', 'per_item', 'active', 1, NOW(), NOW(), NULL);

-- ------------------------------------------------------------------------------
-- Seed Default Service Items & Pricing
-- ------------------------------------------------------------------------------
-- 1. Wash & Fold (Per KG base item)
INSERT INTO `service_items` (`service_id`, `item_name`, `description`, `unit`, `price`, `status`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Standard Wash & Fold', 'Machine wash and fold per kilogram', 'kg', 4.50, 'active', 1, NOW(), NOW(), NULL);

-- 2. Dry Cleaning Items
INSERT INTO `service_items` (`service_id`, `item_name`, `description`, `unit`, `price`, `status`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 'Suit (2-Piece)', 'Jacket and trousers/skirt dry cleaned and pressed', 'item', 15.00, 'active', 1, NOW(), NOW(), NULL),
(2, 'Blazer / Coat', 'Heavy blazer, overcoat, or trench coat', 'item', 10.00, 'active', 2, NOW(), NOW(), NULL),
(2, 'Shirt / Blouse', 'Formal or silk shirt/blouse', 'item', 5.00, 'active', 3, NOW(), NOW(), NULL),
(2, 'Evening Dress', 'Silk, satin, or embellished evening gown', 'item', 18.00, 'active', 4, NOW(), NOW(), NULL),
(2, 'Trousers / Pants', 'Wool, linen, or formal trousers', 'item', 6.00, 'active', 5, NOW(), NOW(), NULL);

-- 3. Ironing & Pressing Items
INSERT INTO `service_items` (`service_id`, `item_name`, `description`, `unit`, `price`, `status`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(3, 'Shirt', 'Casual or formal button-down shirt', 'item', 2.00, 'active', 1, NOW(), NOW(), NULL),
(3, 'Trousers / Pants', 'Pants with crease line pressing', 'item', 2.50, 'active', 2, NOW(), NOW(), NULL),
(3, 'T-Shirt / Polo', 'Light steam iron', 'item', 1.50, 'active', 3, NOW(), NOW(), NULL),
(3, 'Bed Sheet (Single/Double)', 'Linen and cotton bedsheet pressing', 'item', 4.00, 'active', 4, NOW(), NOW(), NULL);

-- 4. Steam Press Items
INSERT INTO `service_items` (`service_id`, `item_name`, `description`, `unit`, `price`, `status`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(4, 'Complete Suit', 'Full steam press restoration', 'item', 8.00, 'active', 1, NOW(), NOW(), NULL),
(4, 'Jacket / Blazer', 'Steam pressed jacket', 'item', 5.00, 'active', 2, NOW(), NOW(), NULL),
(4, 'Dress', 'Cocktail or formal dress steam press', 'item', 6.50, 'active', 3, NOW(), NOW(), NULL);

-- 5. Stain Removal Items
INSERT INTO `service_items` (`service_id`, `item_name`, `description`, `unit`, `price`, `status`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES
(5, 'Minor Spot Treatment', 'Light collar, cuff, or surface spot cleaning', 'item', 3.00, 'active', 1, NOW(), NOW(), NULL),
(5, 'Heavy Stain Treatment', 'Deep chemical spot treatment for ink, oil, or dye stains', 'item', 8.00, 'active', 2, NOW(), NOW(), NULL);
