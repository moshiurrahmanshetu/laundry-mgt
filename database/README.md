# Database Setup & Import Guide (Phase 01)

This folder contains the database schema and seed data for **Phase 01 (Authentication & Access Control)** of the **Laundry Management System**.

## Database Information
- **Database Name:** `laundry_mgt`
- **Character Set:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci`

## Included Tables
1. `roles` — System roles (Administrator, Manager, Staff)
2. `users` — User credentials, profile data, and relational `role_id`
3. `password_resets` — Secure password recovery tokens
4. `activity_logs` — Authentication and security audit trails

## How to Import

### Option 1: Using phpMyAdmin
1. Open your browser and navigate to `http://localhost/phpmyadmin/`.
2. Click **New** in the left sidebar to create a database named `laundry_mgt` (collation `utf8mb4_unicode_ci`), or select it if already created.
3. Click the **Import** tab.
4. Click **Choose File** and select `database/phase_01_authentication.sql`.
5. Click **Import** at the bottom of the page.

### Option 2: Using MySQL CLI (Command Prompt / Terminal)
```bash
# Login to MySQL
mysql -u root -p

# Create database and import
CREATE DATABASE IF NOT EXISTS laundry_mgt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE laundry_mgt;
SOURCE database/phase_01_authentication.sql;
```
Or in a single command from your project root:
```bash
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql
```

## Default Administrator Credentials
- **Email:** `admin@laundrymgt.com`
- **Password:** `Password123!`
- **Role:** `Administrator`
- **Status:** `active`
