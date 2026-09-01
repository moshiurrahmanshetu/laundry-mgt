-- ==============================================================================
-- Database Schema: Phase 04 Laundry Order Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- 1. Table structure for `orders`
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_date` DATETIME NOT NULL,
  `expected_date` DATE NOT NULL,
  `status` ENUM('received', 'processing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT 'received',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_order_number` (`order_number`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_order_date` (`order_date`),
  KEY `idx_orders_expected_date` (`expected_date`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `idx_orders_created_by` (`created_by`),
  KEY `idx_orders_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table structure for `order_items`
-- ------------------------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `service_item_id` INT UNSIGNED DEFAULT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `service_name` VARCHAR(150) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_service_id` (`service_id`),
  KEY `idx_order_items_service_item_id` (`service_item_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_service_item` FOREIGN KEY (`service_item_id`) REFERENCES `service_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Initial Orders for Demonstration
-- ------------------------------------------------------------------------------
-- Order 1: Rahim Ahmed (CUS-000001) - Dry Cleaning & Pressing
INSERT INTO `orders` (`id`, `order_number`, `customer_id`, `order_date`, `expected_date`, `status`, `subtotal`, `discount`, `total`, `paid_amount`, `due_amount`, `payment_status`, `notes`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'ORD-000001', 1, NOW() - INTERVAL 2 DAY, CURDATE() + INTERVAL 1 DAY, 'processing', 35.00, 5.00, 30.00, 30.00, 0.00, 'paid', 'Gentle dry clean on silk shirt; formal crease on suit trousers.', 1, NOW() - INTERVAL 2 DAY, NOW(), NULL),
(2, 'ORD-000002', 2, NOW() - INTERVAL 1 DAY, CURDATE() + INTERVAL 2 DAY, 'received', 18.00, 0.00, 18.00, 10.00, 8.00, 'partial', 'Express weekend turnaround requested.', 1, NOW() - INTERVAL 1 DAY, NOW(), NULL),
(3, 'ORD-000003', 3, NOW() - INTERVAL 3 DAY, CURDATE() - INTERVAL 1 DAY, 'ready', 22.50, 0.00, 22.50, 22.50, 0.00, 'paid', 'Ready for customer pickup at counter.', 1, NOW() - INTERVAL 3 DAY, NOW(), NULL);

-- Order Items for Order 1
INSERT INTO `order_items` (`order_id`, `service_id`, `service_item_id`, `item_name`, `service_name`, `quantity`, `unit_price`, `line_total`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'Suit (2-Piece)', 'Dry Cleaning', 1.00, 15.00, 15.00, 'Navy blue two piece suit', NOW() - INTERVAL 2 DAY, NOW()),
(1, 2, 3, 'Shirt / Blouse', 'Dry Cleaning', 2.00, 5.00, 10.00, 'White cotton dress shirts', NOW() - INTERVAL 2 DAY, NOW()),
(1, 3, 2, 'Trousers / Pants', 'Ironing & Pressing', 4.00, 2.50, 10.00, 'Crisp line crease', NOW() - INTERVAL 2 DAY, NOW());

-- Order Items for Order 2 (Wash & Fold per KG)
INSERT INTO `order_items` (`order_id`, `service_id`, `service_item_id`, `item_name`, `service_name`, `quantity`, `unit_price`, `line_total`, `notes`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 'Standard Wash & Fold', 'Wash & Fold', 4.00, 4.50, 18.00, '4 KG laundry load with lavender softener', NOW() - INTERVAL 1 DAY, NOW());

-- Order Items for Order 3 (Dry cleaning & Steam press)
INSERT INTO `order_items` (`order_id`, `service_id`, `service_item_id`, `item_name`, `service_name`, `quantity`, `unit_price`, `line_total`, `notes`, `created_at`, `updated_at`) VALUES
(3, 2, 2, 'Blazer / Coat', 'Dry Cleaning', 1.00, 10.00, 10.00, 'Winter wool coat', NOW() - INTERVAL 3 DAY, NOW()),
(3, 4, 3, 'Dress', 'Steam Press', 1.00, 6.50, 6.50, 'Evening dress steam press', NOW() - INTERVAL 3 DAY, NOW()),
(3, 5, 1, 'Minor Spot Treatment', 'Stain Removal', 2.00, 3.00, 6.00, 'Collar stain treatment', NOW() - INTERVAL 3 DAY, NOW());
