# Laundry Management System (`laundry-mgt`) — Phase 11

A lightweight, professional, and secure Laundry Management System CMS built with **Raw PHP 8+**, **MySQL (PDO)**, **Bootstrap 5**, and **Bootstrap Icons**.

---

## 1. Project Purpose & Overview

The **Laundry Management System** is designed for commercial laundries, dry cleaners, and laundromats to manage operations, customer accounts, services & item pricing, order workflows, pickups, deliveries, invoices, multi-installment payments, operating business expenses, business reports, staff & role permissions, and full system configuration.

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
- **Phase 06: Laundry Pickup & Delivery Management**
  - Consolidated Schedule System (`pickup_deliveries` table handling both `pickup` and `delivery`)
  - Unique Sequential Reference Generation (`PU-000001`, `DL-000001`...)
  - Permanent Location Snapshots (Service address, contact name, and contact phone are snapshotted in `pickup_deliveries` at creation time)
  - Duplicate Active Request Guard (Prevents scheduling multiple simultaneous active pickups or deliveries for the same order)
  - Dispatch Lifecycle Management: `pending` -> `assigned` -> `in_progress` -> `completed` / `cancelled` (with automated `completed_at` timestamping)
  - Staff Assignment Workflow: Assigning active operational staff automatically updates status to `assigned`
  - Role-Based Operational Security: Administrators & Managers can assign staff and edit/delete schedules; Staff can view assigned requests, update status, and print slips
  - Printable Pickup / Delivery Slip (`print.php` with itemized garments list and signature lines)
- **Phase 07: Laundry Operations & Workflow Management**
  - Dedicated Operational Workflow Queue (`modules/operations/index.php`)
  - Dynamic Real-Time Counters (Received, Processing, Ready, Out for Delivery, Delivered, Cancelled)
  - Context-Sensitive Quick Action Transitions (`[ Start Wash ]` -> `[ Mark Ready ]` -> `[ Deliver ]`)
  - Concurrency & Race Condition Protection: Row-level locking `SELECT ... FOR UPDATE` inside database transactions guarantees stale or conflicting updates are rejected safely
  - Visual Step Timeline on Operations Details (`show.php` with highlighted stages)
  - Audit Trail Tracking: Real-time operational transition events recorded and displayed from `activity_logs`
  - Printable Operations Work Order (`print.php` with garment checklist and quality assurance signature boxes)
- **Phase 08: Laundry Reports & Analytics**
  - Comprehensive Business Overview Report (`modules/reports/index.php`) with real-time KPI metrics (Gross Sales, Direct Collections, Receivables Due, Order Volume, Customer Growth, Deliveries).
  - Pure CSS Horizontal Distribution Bars (Order Stage Breakdown, Payment Settlement Breakdown) without any external chart libraries or gradients.
  - Sales & Revenue Report (`modules/reports/sales.php`): Daily sales aggregation, order counts, gross volume, collections, and customer balances.
  - Order Operations Report (`modules/reports/orders.php`): Stage throughput flow and daily completion rates.
  - Payment Collections Report (`modules/reports/payments.php`): Multi-channel payment analysis with receipt transactions log.
  - Customer Performance Report (`modules/reports/customers.php`): Acquisition velocity and Top 10 client rankings.
  - Services Demand Report (`modules/reports/services.php`): Garment volume and category revenue generation.
  - Logistics Fulfillment Report (`modules/reports/delivery.php`): Dispatch volume and route fulfillment success rates.
- **Phase 09: Expense Management**
  - Operational Expense Tracking (`modules/expenses/index.php`) with dynamic summary cards calculated strictly on `expense_date` (Today, This Month, Current Year, All-Time Total).
  - Unique Sequential Expense Reference Generation (`EXP-000001`, `EXP-000002`...).
  - Expense Category Directory (`modules/expenses/categories.php`) with add, inline edit, active/inactive status toggle, and foreign-key delete protection.
  - Database Integrity & Soft Deletes: Expenses and Categories utilize soft deletion (`deleted_at IS NOT NULL`) with `ON DELETE RESTRICT` foreign keys preventing accidental deletion of referenced categories.
  - Printable Expense Payment Voucher (`modules/expenses/print.php`) with disbursement details and authorized signature boxes.
- **Phase 10: Staff & Roles Management & Sidebar UI Fix**
  - Staff Accounts Directory (`modules/staff/index.php`) with metrics, search, role/status filters, and soft deletion.
  - Staff Intake & Editing (`modules/staff/create.php`, `edit.php`, `show.php`) with secure bcrypt password hashing, avatar upload, and immutable account integrity.
  - Role Assignment Bug Prevention: Form select elements and update statements strictly enforce correct `role_id` relational foreign keys without hardcoded role fallbacks.
  - Administrator Lockout Protection: Prevents deactivating, deleting, or reassigning the last active Administrator account.
  - Custom Role Management (`modules/staff/roles.php`): Role directory with add/edit/delete and system role protection (`administrator`, `manager`, `staff`).
  - Granular Permissions Matrix (`modules/staff/role_permissions.php`): 22 module-level permissions across all system modules.
  - Sidebar User Profile UI Fix: Complete layout refactoring with scrollable navigation (`overflow-y: auto`) and fixed, non-clipped bottom user footer (`flex-shrink: 0`, min-height: 64px) for desktop, mobile, expanded, and collapsed sidebar states.
- **Phase 11: Settings / System Configuration (NEW)**
  - Dynamic System Configuration (`settings` key/value database table with unique key constraints).
  - In-Memory Runtime Caching: `get_all_settings()`, `get_setting()`, and `set_setting()` helpers prevent redundant database queries per page execution.
  - Business Profile Management (`modules/settings/index.php` Tab 1): Business Name, Phone, Email, Address, Website, Short Description, and Store Logo upload with safe replacement and removal options.
  - General Preferences (`modules/settings/index.php` Tab 2): System Timezone (validated against `DateTimeZone::listIdentifiers()`), Date Display Format (`d/m/Y`, `m/d/Y`, `Y-m-d`, `d M Y`), Currency Code, and Currency Symbol.
  - Invoice & Receipt Layout (`modules/settings/index.php` Tab 3): Customizable Invoice Prefix, Receipt Prefix, Printable Footer Terms, and Header Display Toggles (Show Logo, Show Address, Show Phone).
  - Print Document Integration: Dynamic business branding in order receipts, payment vouchers, delivery slips, operations work orders, and expense vouchers.
  - Financial Data Safety: Currency and formatting adjustments update presentation layers only without altering stored financial records or historical transactions.

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
│   │   └── style.css              # Custom styling, solid color palette, sidebar flex layout
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
│   └── constants.php              # Application constants & dynamic Base URL, Upload & Logo paths
│
├── database/
│   ├── phase_01_authentication.sql # Phase 01 roles, users, resets & audit logs schema
│   ├── phase_02_customers.sql     # Phase 02 customers table & sample seed data
│   ├── phase_03_services.sql      # Phase 03 services & service_items schema & seed data
│   ├── phase_04_orders.sql        # Phase 04 orders & order_items schema & seed data
│   ├── phase_05_payments.sql      # Phase 05 payments table & seed data
│   ├── phase_06_delivery.sql      # Phase 06 pickup_deliveries table & seed data
│   ├── phase_07_operations.sql    # Phase 07 operations & workflow documentation
│   ├── phase_08_reports.sql       # Phase 08 reports & analytics documentation
│   ├── phase_09_expenses.sql      # Phase 09 expense_categories & expenses schema
│   ├── phase_10_staff_roles.sql   # Phase 10 permissions, role_permissions & schema updates
│   ├── phase_11_settings.sql      # Phase 11 settings key/value schema & seed defaults
│   └── README.md                  # Database import guide
│
├── includes/
│   ├── auth_check.php             # Authentication guard (redirects guests & sets no-cache)
│   ├── guest_check.php            # Guest guard (redirects logged-in users to dashboard)
│   ├── header.php                 # HTML Head, Bootstrap 5, Icons, custom CSS
│   ├── footer.php                 # Footer layout, Bootstrap 5 JS & app.js
│   ├── sidebar.php                # Collapsible sidebar with active Settings link
│   ├── topbar.php                 # Top navigation bar & user profile menu
│   ├── flash_message.php          # Session-based flash alerts renderer
│   └── functions.php              # Global helpers (CSRF, settings, permissions, formatting)
│
├── modules/
│   ├── dashboard/
│   │   └── index.php              # Main Dashboard (User profile, operations, reports, expenses & metrics)
│   │
│   ├── settings/
│   │   ├── index.php              # System settings tabbed dashboard (Profile, General, Invoice)
│   │   └── save.php               # Settings update POST handler (Validation, logo upload, audit log)
│   │
│   ├── staff/
│   │   ├── index.php              # Staff directory, filters, pagination, delete & status modals
│   │   ├── create.php             # Staff creation form
│   │   ├── store.php              # Staff store handler (Validation, bcrypt, avatar upload)
│   │   ├── show.php               # Staff profile view & user activity history
│   │   ├── edit.php               # Staff edit form (Role selection, optional password change)
│   │   ├── update.php             # Staff update handler (Admin lockout protection)
│   │   ├── delete.php             # Soft delete handler (Self-delete & last admin protection)
│   │   ├── toggle_status.php      # Account status toggle handler (Admin lockout protection)
│   │   ├── roles.php              # Roles directory, Add/Edit/Delete modals
│   │   ├── role_store.php         # Add role POST handler
│   │   ├── role_update.php        # Update role POST handler
│   │   ├── role_delete.php        # Soft delete role POST handler (System role protection)
│   │   ├── role_permissions.php   # Module-level granular permissions matrix
│   │   └── role_permissions_update.php # Save permissions POST handler
│   │
│   ├── expenses/
│   │   ├── index.php              # Expenses dashboard, date metrics, search, filters & table
│   │   ├── create.php             # Record expense form
│   │   ├── store.php              # Expense store POST handler & transaction
│   │   ├── show.php               # Expense voucher profile card
│   │   ├── edit.php               # Edit expense form
│   │   ├── update.php             # Expense update POST handler
│   │   ├── delete.php             # Soft delete POST handler
│   │   ├── print.php              # Printable expense voucher slip with signatures
│   │   ├── categories.php         # Expense category directory & management
│   │   ├── category_store.php     # Add category POST handler
│   │   ├── category_update.php    # Update category POST handler
│   │   └── category_delete.php    # Soft delete category POST handler (RESTRICT guarded)
│   │
│   ├── reports/
│   │   ├── index.php              # Overview report dashboard, KPI cards & CSS distribution bars
│   │   ├── sales.php              # Sales & revenue report with daily aggregation (Admin/Manager)
│   │   ├── orders.php             # Orders report with stage breakdown & daily intake flow
│   │   ├── payments.php           # Payment collections & payment channel breakdown (Admin/Manager)
│   │   ├── customers.php          # Customer performance report & top 10 spenders
│   │   ├── services.php           # Service category & garment demand analytics
│   │   └── delivery.php           # Pickup & delivery fulfillment logistics report
│   │
│   ├── operations/
│   │   ├── index.php              # Operations dashboard, stage filters, quick transitions & orders table
│   │   ├── show.php               # Operations order profile with visual stage timeline & activity logs
│   │   ├── update_status.php      # Concurrency-safe status advance handler (FOR UPDATE row locking)
│   │   └── print.php              # Printable operations work order with garment checklist & QA signatures
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
│   │   ├── print.php              # Printable invoice/receipt with dynamic branding
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
│   │   └── print.php              # Printable payment receipt voucher with dynamic branding
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
│   ├── logos/                     # Uploaded business store logos
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
7. `database/phase_07_operations.sql`
8. `database/phase_08_reports.sql`
9. `database/phase_09_expenses.sql`
10. `database/phase_10_staff_roles.sql`
11. `database/phase_11_settings.sql`

---

## 6. Roles & Permissions (Phase 01 — 11)

| Module Action | Administrator | Manager | Staff |
| :--- | :---: | :---: | :---: |
| **System Settings (Business Profile, General, Invoice)** | **Yes** | **No (403)** | **No (403)** |
| **Staff & Roles Management** | **Yes** | **No (403)** | **No (403)** |
| **Manage Expenses (View, Add, Edit, Delete, Print)** | Yes | Yes | **No (403)** |
| **Manage Expense Categories** | Yes | Yes | **No (403)** |
| **View Financial Reports (Sales, Payments)** | Yes | Yes | **No (403)** |
| **View Operational Reports (Overview, Orders, Services, Delivery, Customers)** | Yes | Yes | Yes |
| **Advance Next Workflow Stage** | Yes | Yes | Yes |
| **Reopen Delivered/Cancelled Order** | Yes | Yes | **No (403)** |
| **Assign Delivery Staff** | Yes | Yes | **No (403)** |
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
- **Phase 07 (Complete):** Laundry Operations (Workflow Queue, Visual Timeline, Work Orders)
- **Phase 08 (Complete):** Reports & Analytics (Sales, Payments, Delivery, Customers, Services)
- **Phase 09 (Complete):** Expense Management (Operating Expenses, Categories, Vouchers)
- **Phase 10 (Complete):** Staff Management & Role Permissions (Staff Directory, Roles, Permissions, Lockout Protection)
- **Phase 11 (Complete):** Settings / System Configuration (Business Profile, Logo Branding, Regional Defaults, Print Layouts)