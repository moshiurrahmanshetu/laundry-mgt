# Laundry Management System (`laundry-mgt`) — Phase 06

A lightweight, professional, and secure Laundry Management System CMS built with **Raw PHP 8+**, **MySQL (PDO)**, **Bootstrap 5**, and **Bootstrap Icons**.

---

## 1. Project Purpose & Overview

The **Laundry Management System** is designed for commercial laundries, dry cleaners, and laundromats to manage customer accounts, services & item pricing, order workflows, pickups, deliveries, invoices, multi-installment payments, and staff permissions.

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
- **Phase 04: Laundry Order Management**
  - Full Order Intake Workflow (Customer selection, intake date, expected delivery date, handling notes)
  - Dynamic Garment & Rate Calculator (Per Item rates and Per KG weight calculations in real time)
  - Server-Side Authoritative Pricing Security
  - Historical Pricing Snapshots (`order_items` stores historical snapshots)
  - Order Lifecycle Management (`received` -> `processing` -> `ready` -> `delivered` / `cancelled`)
  - Professional Printable Order Invoice (`print.php`)
- **Phase 05: Payment Management**
  - Multi-Payment Transaction System (One order supports multiple installments/advances)
  - Payment Methods: **Cash**, **Credit/Debit Card**, **Mobile Banking**, **Bank Transfer**, and **Other**
  - Transaction Reference Tracking (TrxID, POS Auth codes, Check numbers)
  - Financial Concurrency Safety: Row-level locking via `SELECT ... FOR UPDATE` inside `PDO::beginTransaction()`
  - Authoritative Ledger Calculation: `recalculate_order_payment_summary()` dynamically syncs `orders.paid_amount`, `orders.due_amount`, and `orders.payment_status` (`unpaid`, `partial`, `paid`)
  - Safe Audit Voiding: Completed payments are never physically destroyed; Administrators can void payments (`status = 'voided'`), which restores order balances and logs audit trails in `activity_logs`
  - Integrated Payment History section on Order Details view with `[ + Receive Payment ]` quick actions
  - Printable Payment Receipt Voucher (`print.php`)
- **Phase 06: Laundry Pickup & Delivery Management (NEW)**
  - Consolidated Schedule System (`pickup_deliveries` table handling both `pickup` and `delivery`)
  - Unique Sequential Reference Generation (`PU-000001`, `DL-000001`...)
  - Permanent Location Snapshots (Service address, contact name, and contact phone are snapshotted in `pickup_deliveries` at creation time)
  - Duplicate Active Request Guard (Prevents scheduling multiple simultaneous active pickups or deliveries for the same order)
  - Dispatch Lifecycle Management: `pending` -> `assigned` -> `in_progress` -> `completed` / `cancelled` (with automated `completed_at` timestamping)
  - Staff Assignment Workflow: Assigning active operational staff automatically updates status to `assigned`
  - Role-Based Operational Security: Administrators & Managers can assign staff and edit/delete schedules; Staff can view assigned requests, update status, and print slips
  - Printable Pickup / Delivery Slip (`print.php` with itemized garments list and driver/recipient signature lines)
  - Integrated Schedule Section on Order Details view with quick `+ Pickup` and `+ Delivery` action buttons

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
│   ├── phase_05_payments.sql      # Phase 05 payments table & seed data
│   ├── phase_06_delivery.sql      # Phase 06 pickup_deliveries table & seed data
│   └── README.md                  # Database import guide
│
├── includes/
│   ├── auth_check.php             # Authentication guard (redirects guests & sets no-cache)
│   ├── guest_check.php            # Guest guard (redirects logged-in users to dashboard)
│   ├── header.php                 # HTML Head, Bootstrap 5, Icons, custom CSS
│   ├── footer.php                 # Footer layout, Bootstrap 5 JS & app.js
│   ├── sidebar.php                # Collapsible sidebar with active Pickup & Delivery navigation
│   ├── topbar.php                 # Top navigation bar & user profile menu
│   ├── flash_message.php          # Session-based flash alerts renderer
│   └── functions.php              # Global helpers (CSRF, numbering, reference badges, summary helpers)
│
├── modules/
│   ├── dashboard/
│   │   └── index.php              # Main Dashboard (User profile, customer, service, order, payment & delivery metrics)
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
│   │   ├── show.php               # Order details view, customer card, payment history table & delivery schedules
│   │   ├── edit.php               # Edit order form
│   │   ├── update.php             # Order update handler (Transaction-safe)
│   │   ├── update_status.php      # Order lifecycle status transition handler
│   │   ├── delete.php             # Soft delete handler (Admin/Manager only)
│   │   ├── print.php              # Printable invoice/receipt
│   │   └── get_service_items.php  # Secure AJAX helper for cascading item dropdowns
│   │
│   ├── payments/
│   │   ├── index.php              # Payments list with search, method/status/date filters & pagination
│   │   ├── create.php             # Receive payment form with live due preview & order dropdown
│   │   ├── store.php              # Concurrency-safe payment store with row-locking FOR UPDATE
│   │   ├── show.php               # Payment voucher details & order financial summary
│   │   ├── edit.php               # Edit payment metadata (Admin only)
│   │   ├── update.php             # Payment metadata update handler (Admin only)
│   │   ├── delete.php             # Void payment handler (Admin only, restores order balance)
│   │   └── print.php              # Printable payment receipt voucher
│   │
│   ├── delivery/
│   │   ├── index.php              # Pickup & delivery list with counters, search, filters & modals
│   │   ├── create.php             # Schedule request form with dynamic order snapshotting
│   │   ├── store.php              # Request store handler & duplicate active request validation
│   │   ├── show.php               # Request profile view, location card & associated order summary
│   │   ├── edit.php               # Edit request form (Admin/Manager)
│   │   ├── update.php             # Request update handler (Admin/Manager)
│   │   ├── assign.php             # Staff assignment handler (Admin/Manager)
│   │   ├── update_status.php      # Status transition handler (Records completed_at)
│   │   ├── delete.php             # Soft delete handler (Admin/Manager)
│   │   └── print.php              # Printable pickup/delivery slip with signature boxes
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
5. `database/phase_05_payments.sql`
6. `database/phase_06_delivery.sql`

Via CLI:
```bash
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql
mysql -u root -p laundry_mgt < database/phase_02_customers.sql
mysql -u root -p laundry_mgt < database/phase_03_services.sql
mysql -u root -p laundry_mgt < database/phase_04_orders.sql
mysql -u root -p laundry_mgt < database/phase_05_payments.sql
mysql -u root -p laundry_mgt < database/phase_06_delivery.sql
```

---

## 6. Roles & Permissions (Phase 01 — 06)

| Module Action | Administrator | Manager | Staff |
| :--- | :---: | :---: | :---: |
| **View / Search Schedules** | All | All | Assigned / Created |
| **Create Schedule Request** | Yes | Yes | Yes |
| **Print Pickup/Delivery Slip** | Yes | Yes | Yes |
| **Update Status** | Yes | Yes | Assigned Requests |
| **Assign / Reassign Staff** | Yes | Yes | **No (403)** |
| **Edit Schedule Details** | Yes | Yes | **No (403)** |
| **Delete Schedule Request** | Yes | Yes | **No (403)** |
| **Receive Payment** | Yes | Yes | Yes |
| **Void Payment** | Yes | **No (403)** | **No (403)** |
| **Manage Services & Pricing** | Yes | Yes | **No (403)** |

---

## 7. Development Roadmap

- **Phase 01 (Complete):** Authentication, Role Access, Security Baseline
- **Phase 02 (Complete):** Customer Management (Profiles, Search, Pagination, History)
- **Phase 03 (Complete):** Laundry Services & Pricing (Item Rates, Per KG, Dynamic JS Rows)
- **Phase 04 (Complete):** Laundry Order Management (Intake, Real-time Calculator, Invoices)
- **Phase 05 (Complete):** Payment Management (Multi-pay, Vouchers, Concurrency, Voiding)
- **Phase 06 (Complete):** Laundry Pickup & Delivery (Dispatch, Address Snapshots, Slips)
- **Phase 07:** Reports & Analytics (Revenue, Operations, Daily Intake)
- **Phase 08:** Staff Management & Role Permissions
- **Phase 09:** System Settings & Equipment Management