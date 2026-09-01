-- ==============================================================================
-- Database Schema: Phase 08 Laundry Reports & Analytics
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- Note on Phase 08 Architecture:
-- ------------------------------------------------------------------------------
-- Phase 08 (Laundry Reports & Analytics) generates real-time business intelligence
-- directly from the existing relational database tables:
--
-- 1. `orders` & `order_items`: Sales performance, order volume, garment demand.
-- 2. `payments`: Cash collections, payment method distribution, outstanding balance.
-- 3. `customers`: Customer retention, acquisition rate, top customer rankings.
-- 4. `services` & `service_items`: Service popularity, revenue by service category.
-- 5. `pickup_deliveries`: Logistics dispatch volume, completion efficiency.
--
-- No new database tables are required for Phase 08, ensuring optimal performance
-- and complete data consistency with zero data redundancy.
-- ------------------------------------------------------------------------------
