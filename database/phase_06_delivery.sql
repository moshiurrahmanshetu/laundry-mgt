-- ==============================================================================
-- Database Schema: Phase 06 Laundry Pickup & Delivery Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- 1. Table structure for `pickup_deliveries`
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pickup_deliveries`;

CREATE TABLE `pickup_deliveries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_number` VARCHAR(50) NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `type` ENUM('pickup', 'delivery') NOT NULL,
  `address` TEXT NOT NULL,
  `contact_name` VARCHAR(150) NOT NULL,
  `contact_phone` VARCHAR(30) NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `scheduled_time` TIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `status` ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pickup_deliveries_reference` (`reference_number`),
  KEY `idx_pd_order_id` (`order_id`),
  KEY `idx_pd_customer_id` (`customer_id`),
  KEY `idx_pd_type` (`type`),
  KEY `idx_pd_scheduled_date` (`scheduled_date`),
  KEY `idx_pd_status` (`status`),
  KEY `idx_pd_assigned_to` (`assigned_to`),
  KEY `idx_pd_created_by` (`created_by`),
  KEY `idx_pd_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_pd_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pd_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pd_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Initial Demonstrations (Linked to demo orders created in Phase 04)
-- ------------------------------------------------------------------------------
INSERT INTO `pickup_deliveries` (`id`, `reference_number`, `order_id`, `customer_id`, `type`, `address`, `contact_name`, `contact_phone`, `scheduled_date`, `scheduled_time`, `completed_at`, `status`, `assigned_to`, `notes`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'PU-000001', 1, 1, 'pickup', 'House 14, Road 5, Dhanmondi, Dhaka', 'Rahim Ahmed', '+8801711111111', CURDATE() - INTERVAL 2 DAY, '10:00:00', NOW() - INTERVAL 2 DAY, 'completed', 1, 'Silk shirt and suit trousers for dry clean.', 1, NOW() - INTERVAL 2 DAY, NOW(), NULL),
(2, 'DL-000001', 1, 1, 'delivery', 'House 14, Road 5, Dhanmondi, Dhaka', 'Rahim Ahmed', '+8801711111111', CURDATE() + INTERVAL 1 DAY, '16:00:00', NULL, 'assigned', 1, 'Delivery after 4 PM requested. Call before reaching.', 1, NOW() - INTERVAL 1 DAY, NOW(), NULL),
(3, 'PU-000002', 2, 2, 'pickup', 'Flat 4B, Green Tower, Gulshan-2, Dhaka', 'Fatema Begum', '+8801722222222', CURDATE() - INTERVAL 1 DAY, '14:30:00', NOW() - INTERVAL 1 DAY, 'completed', 1, 'Wash & fold laundry bag pickup.', 1, NOW() - INTERVAL 1 DAY, NOW(), NULL),
(4, 'DL-000002', 3, 3, 'delivery', 'Apartment 7A, Lake View, Uttara, Dhaka', 'Tanvir Hossain', '+8801733333333', CURDATE(), '11:00:00', NULL, 'in_progress', 1, 'Ready order out for delivery.', 1, NOW(), NOW(), NULL);
