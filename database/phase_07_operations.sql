-- ==============================================================================
-- Database Schema: Phase 07 Laundry Operations & Workflow Management
-- Project: Laundry Management System (laundry-mgt)
-- Database: laundry_mgt
-- ==============================================================================

USE `laundry_mgt`;

-- ------------------------------------------------------------------------------
-- Note on Phase 07 Schema Architecture:
-- ------------------------------------------------------------------------------
-- Phase 07 (Laundry Operations & Workflow Management) leverages the existing
-- normalized relational database architecture established in previous phases:
--
-- 1. `orders` table:
--    - Uses existing `status` ENUM('received', 'processing', 'ready', 'delivered', 'cancelled')
--    - Uses existing `payment_status` ENUM('unpaid', 'partial', 'paid')
--    - Uses existing `total`, `paid_amount`, and `due_amount`
--
-- 2. `order_items` table:
--    - Provides garment and laundry service line items for operational work orders
--
-- 3. `pickup_deliveries` table:
--    - Provides logistics dispatch tracking (pickup and delivery schedules)
--
-- 4. `activity_logs` table:
--    - Records complete audit trails for operational stage transitions
--
-- No new database tables are required for Phase 07, preventing duplicate data.
-- ------------------------------------------------------------------------------
