# Laundry Management System (`laundry-mgt`) — Phase 04

A lightweight, professional, and secure Laundry Management System CMS built with **Raw PHP 8+**, **MySQL (PDO)**, **Bootstrap 5**, and **Bootstrap Icons**.

---

## 1. Project Purpose & Overview

The **Laundry Management System** is designed for commercial laundries, dry cleaners, and laundromats to manage operations, customer accounts, services & item pricing, order workflows, deliveries, invoices, and staff permissions.

### Implemented Phases
- **Phase 01: Foundation & Authentication System**
  - Authentication System (Login, Secure Session, Logout, Password Recovery)
  - Relational Role-Based Access Control (`roles` table: Administrator, Manager, Staff)
  - User Profile Management (Name, Email, Phone, Password Change)
  - Secure Avatar Uploads (JPG, PNG, WebP with MIME validation)
  - Security Audit & Activity Logging (`activity_logs` table)
  - Collapsible Desktop Sidebar with `localStorage` state persistence
  - Responsive Mobile Drawer Navigation
- **Phase 02: Customer Management**
  - Customer Profiles (Name, Phone, Email, Address, City, Postal Code, Notes)
  - Unique Sequential Customer Code Generation (`CUS-000001`, `CUS-000002`...)
  - Multi-field Search, Status Filter, and Server-side Pagination
  - Soft-Delete Protection (`deleted_at` timestamp)
  - Active Order History Integration inside Customer profile
- **Phase 03: Laundry Services & Pricing Management**
  - Service Category Management (`services` table: name, slug, description, pricing_type, status)
  - Itemized Clothing & Garment Pricing (`service_items` table: item_name, unit, price, status, sort_order)
  - Dual Pricing Models: **Per Item / Garment** and **Per KG Weight**
  - Dynamic JavaScript Row Manager
  - Database Transaction Safety (Atomically saves services and itemized rates)
- **Phase 04: Laundry Order Management (NEW)**
  - Full Order Intake Workflow (Customer selection, intake date, expected delivery date, handling notes)
  - Dynamic Garment & Rate Calculator (Per Item rates and Per KG weight calculations in real time)
  - **Server-Side Authoritative Pricing Security** (Client calculations provide instant UI feedback; server strictly recalculates all line totals, subtotals, discounts, totals, and balances from database rates)
  - **Historical Pricing Snapshots** (`order_items` stores historical snapshots of service names, item names, and unit prices)
  - Order Lifecycle Management (`received` -> `processing` -> `ready` -> `delivered` / `cancelled`)
  - Order-Level Discounts and Initial Advance Payments with due balance calculation
  - Professional Printable Receipt/Invoice (`print.php` with `@media print` styling)
  - Server-Side Role Enforcement (Staff restricted from deleting orders)

---

## 2. Technology Stack

* **Backend:** Raw PHP 8+ (No frameworks, pure native PHP)
* **Database:** MySQL (via PDO with Prepared Statements)
* **Frontend:** HTML5, CSS3, JavaScript (Vanilla JS, no frameworks)
* **UI Components & Grid:** Bootstrap 5.3.3
* **Iconography:** Bootstrap Icons 1.11.3
* **Web Server:** Apache (XAMPP compatible)

---

## 3. Requirements

- **PHP:** 8.0 or higher (with `pdo_mysql`, `fileinfo`, `mbstring`, `openssl` extensions enabled)
- **MySQL / MariaDB:** 5.7+ / 10.3+
- **Web Server:** Apache (with `mod_rewrite` enabled) or XAMPP

---

## 4. Project Directory Structure

```text
laundry-mgt/
│
├── assets/
│   ├── css/
│   │   └── style.css              # Custom styling, solid color palette, animations
│   ├── js/
│   │   └── app.js                 # Collapsible sidebar, localStorage state, mobile drawer
│   ├── images/
│   │   └── default-avatar.svg     # Built-in SVG default user avatar
│   └── vendor/                    # Local vendor assets
│
├── auth/
│   ├── login.php                  # User Sign In interface
│   ├── logout.php                 # Secure session destruction & sign out
│   ├── authenticate.php           # POST authentication processor
│   ├── forgot_password.php        # Password recovery request interface
│   ├── reset_password.php         # Password reset token handler
│   └── change_password.php        # Password change route handler
│
├── config/
│   ├── config.php                 # Master configuration & session initialization
│   ├── database.php               # Centralized PDO MySQL connection
│   └── constants.php              # Application constants & dynamic Base URL
│
├── database/
│   ├── phase_01_authentication.sql # Phase 01 roles, users, resets & audit logs schema
│   ├── phase_02_customers.sql     # Phase 02 customers table & sample seed data
│   ├── phase_03_services.sql      # Phase 03 services & service_items schema & seed data
│   ├── phase_04_orders.sql        # Phase 04 orders & order_items schema & seed data
│   └── README.md                  # Database import guide
│
├── includes/
│   ├── auth_check.php             # Authentication guard (redirects guests & sets no-cache)
│   ├── guest_check.php            # Guest guard (redirects logged-in users to dashboard)
│   ├── header.php                 # HTML Head, Bootstrap 5, Icons, custom CSS
│   ├── footer.php                 # Footer layout, Bootstrap 5 JS & app.js
│   ├── sidebar.php                # Collapsible sidebar with active Orders navigation
│   ├── topbar.php                 # Top navigation bar & user profile menu
│   ├── flash_message.php          # Session-based flash alerts renderer
│   └── functions.php              # Global helpers (CSRF, order number generator, badges)
│
├── modules/
│   ├── dashboard/
│   │   └── index.php              # Main Dashboard (User profile, customer, service & order metrics)
│   │
│   ├── customers/
│   │   ├── index.php              # Customer listing with search, filter & pagination
│   │   ├── create.php             # Add customer form
│   │   ├── store.php              # Customer creation handler & validation
│   │   ├── show.php               # Customer detail view & real order history list
│   │   ├── edit.php               # Customer edit form
│   │   ├── update.php             # Customer update handler & validation
│   │   ├── delete.php             # Soft delete handler (Admin/Manager only)
│   │   └── toggle_status.php      # Customer status toggle handler
│   │
│   ├── services/
│   │   ├── index.php              # Service catalog listing with search, filter & pagination
│   │   ├── create.php             # Add service & item rates form
│   │   ├── store.php              # Service store handler (Transaction-safe)
│   │   ├── show.php               # Service details & itemized pricing table
│   │   ├── edit.php               # Edit service & dynamic item rates form
│   │   ├── update.php             # Service update handler (Transaction-safe)
│   │   ├── delete.php             # Soft delete handler (Admin/Manager only)
│   │   └── toggle_status.php      # Service status toggle handler
│   │
│   ├── orders/
│   │   ├── index.php              # Orders list with search, status/payment filters & pagination
│   │   ├── create.php             # New order intake with dynamic JS item rows
│   │   ├── store.php              # Transaction-safe order store & authoritative price verification
│   │   ├── show.php               # Order details profile, garment list & balance breakdown
│   │   ├── edit.php               # Edit order form
│   │   ├── update.php             # Order update handler (Transaction-safe)
│   │   ├── update_status.php      # Order lifecycle status transition handler
│   │   ├── delete.php             # Soft delete handler (Admin/Manager only)
│   │   ├── print.php              # Printable invoice/receipt
│   │   └── get_service_items.php  # Secure AJAX helper for cascading item dropdowns
│   │
│   └── profile/
│       ├── index.php              # Profile management & settings view
│       ├── update.php             # Updates Name, Email, Phone
│       ├── change_password.php    # Verifies old password and sets new password
│       └── upload_avatar.php      # Secure avatar upload processor
│
├── uploads/
│   ├── avatars/                   # Uploaded profile photos
│   └── .htaccess                  # Disables PHP script execution in uploads
│
├── .htaccess                      # Root security & directory browsing protection
├── index.php                      # Root entry point (redirects to dashboard or login)
└── README.md                      # Comprehensive documentation
```

---

## 5. Database Setup & Installation

### Import SQL Files in Sequential Order:
1. `database/phase_01_authentication.sql`
2. `database/phase_02_customers.sql`
3. `database/phase_03_services.sql`
4. `database/phase_04_orders.sql`

Via CLI:
```bash
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql
mysql -u root -p laundry_mgt < database/phase_02_customers.sql
mysql -u root -p laundry_mgt < database/phase_03_services.sql
mysql -u root -p laundry_mgt < database/phase_04_orders.sql
```

---

## 6. Roles & Permissions (Phase 01, 02, 03 & 04)

| Role | View / Search Orders | Create Order | Edit Order | Update Order Status | Print Receipt | Delete Order |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Administrator** | Yes | Yes | Yes | Yes | Yes | Yes |
| **Manager** | Yes | Yes | Yes | Yes | Yes | Yes |
| **Staff** | Yes | Yes | Yes | Yes | Yes | **No (403)** |

---

## 7. Development Roadmap

- **Phase 01 (Complete):** Authentication, Role Access, Security Baseline
- **Phase 02 (Complete):** Customer Management (Profiles, Search, Pagination, History)
- **Phase 03 (Complete):** Laundry Services & Pricing (Item Rates, Per KG, Dynamic JS Rows)
- **Phase 04 (Complete):** Laundry Order Management (Intake, Real-time Calculator, Receipts)
- **Phase 05:** Payments & Invoicing (POS, Payment Gateways, Payment History)
- **Phase 06:** Reports & Analytics (Revenue, Operations, Daily Intake)
- **Phase 07:** Staff Management & Role Permissions
- **Phase 08:** System Settings & Equipment Management