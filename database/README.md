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

---

## How to Import

### Option 1: Using phpMyAdmin
1. Open your browser and navigate to `http://localhost/phpmyadmin/`.
2. Click **New** in the left sidebar to create `laundry_mgt` (collation `utf8mb4_unicode_ci`), or select it if already created.
3. Click the **Import** tab.
4. Choose and import files in this exact order:
   - First: `database/phase_01_authentication.sql`
   - Second: `database/phase_02_customers.sql`
5. Click **Import**.

### Option 2: Using MySQL CLI
```bash
# Import Phase 01 (Authentication)
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql

# Import Phase 02 (Customers)
mysql -u root -p laundry_mgt < database/phase_02_customers.sql
```

---

## Default Administrator Credentials
- **Email:** `admin@laundrymgt.com`
- **Password:** `Password123!`
- **Role:** `Administrator`
- **Status:** `active`
