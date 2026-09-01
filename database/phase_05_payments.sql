-- ==============================================================================
-- Database Schema: Phase 05 Payment Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- 1. Table structure for `payments`
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;

CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number` VARCHAR(50) NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` ENUM('cash', 'card', 'mobile_banking', 'bank_transfer', 'other') NOT NULL DEFAULT 'cash',
  `transaction_reference` VARCHAR(150) DEFAULT NULL,
  `payment_date` DATETIME NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `received_by` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('completed', 'voided') NOT NULL DEFAULT 'completed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payments_payment_number` (`payment_number`),
  KEY `idx_payments_order_id` (`order_id`),
  KEY `idx_payments_payment_date` (`payment_date`),
  KEY `idx_payments_status` (`status`),
  KEY `idx_payments_payment_method` (`payment_method`),
  KEY `idx_payments_received_by` (`received_by`),
  KEY `idx_payments_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- Seed Sample Initial Payments (Matching demo orders created in Phase 04)
-- ------------------------------------------------------------------------------
INSERT INTO `payments` (`id`, `payment_number`, `order_id`, `amount`, `payment_method`, `transaction_reference`, `payment_date`, `notes`, `received_by`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'PAY-000001', 1, 30.00, 'cash', NULL, NOW() - INTERVAL 2 DAY, 'Full payment received at counter intake.', 1, 'completed', NOW() - INTERVAL 2 DAY, NOW(), NULL),
(2, 'PAY-000002', 2, 10.00, 'mobile_banking', 'TRX-94821034', NOW() - INTERVAL 1 DAY, 'Advance partial payment via bKash.', 1, 'completed', NOW() - INTERVAL 1 DAY, NOW(), NULL),
(3, 'PAY-000003', 3, 22.50, 'card', 'POS-AUTH-77219', NOW() - INTERVAL 3 DAY, 'Paid in full via Visa debit card.', 1, 'completed', NOW() - INTERVAL 3 DAY, NOW(), NULL);
