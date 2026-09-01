# Database Setup & Import Guide

This folder contains the database schema and seed data for the **Laundry Management System** (`laundry_mgt`).

## Database Information
- **Database Name:** `laundry_mgt`
- **Character Set:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci`

---

## Phase SQL Files

### Phase 01: Authentication & Access Control
File: [`phase_01_authentication.sql`](file:///c:/xampp/htdocs/laundry-mgt/database/phase_01_authentication.sql)
- `roles` — System roles (Administrator, Manager, Staff)
- `users` — User accounts with relational `role_id`
- `password_resets` — Secure password recovery tokens
- `activity_logs` — System security audit trails

### Phase 02: Customer Management
File: [`phase_02_customers.sql`](file:///c:/xampp/htdocs/laundry-mgt/database/phase_02_customers.sql)
- `customers` — Customer contact profiles, address, preferences, and soft-delete tracking

### Phase 03: Laundry Services & Pricing Management
File: [`phase_03_services.sql`](file:///c:/xampp/htdocs/laundry-mgt/database/phase_03_services.sql)
- `services` — Master laundry service categories (e.g. Wash & Fold, Dry Cleaning, Ironing, Steam Press, Stain Removal)
- `service_items` — Itemized clothing and garment pricing under each service category

### Phase 04: Laundry Order Management
File: [`phase_04_orders.sql`](file:///c:/xampp/htdocs/laundry-mgt/database/phase_04_orders.sql)
- `orders` — Laundry orders with customer linkage, delivery date, status lifecycle, discounts, and payment totals
- `order_items` — Itemized line items storing historical price and service snapshots

---

## How to Import

### Option 1: Using phpMyAdmin
1. Open your browser and navigate to `http://localhost/phpmyadmin/`.
2. Select or create the `laundry_mgt` database (collation `utf8mb4_unicode_ci`).
3. Click the **Import** tab.
4. Choose and import files in this exact sequence:
   - 1st: `database/phase_01_authentication.sql`
   - 2nd: `database/phase_02_customers.sql`
   - 3rd: `database/phase_03_services.sql`
   - 4th: `database/phase_04_orders.sql`
5. Click **Import**.

### Option 2: Using MySQL CLI
```bash
# Import in sequential order
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql
mysql -u root -p laundry_mgt < database/phase_02_customers.sql
mysql -u root -p laundry_mgt < database/phase_03_services.sql
mysql -u root -p laundry_mgt < database/phase_04_orders.sql
```

---

## Default Administrator Credentials
- **Email:** `admin@laundrymgt.com`
- **Password:** `Password123!`
- **Role:** `Administrator`
- **Status:** `active`
