# Laundry Management System (`laundry-mgt`) — Phase 02

A lightweight, professional, and secure Laundry Management System CMS built with **Raw PHP 8+**, **MySQL (PDO)**, **Bootstrap 5**, and **Bootstrap Icons**.

---

## 1. Project Purpose & Overview

The **Laundry Management System** is designed for commercial laundries, dry cleaners, and laundromats to manage operations, customer accounts, orders, washing/ironing workflows, deliveries, invoices, and staff permissions.

### Implemented Phases
- **Phase 01: Foundation & Authentication System**
  - Authentication System (Login, Secure Session, Logout, Password Recovery)
  - Relational Role-Based Access Control (`roles` table: Administrator, Manager, Staff)
  - User Profile Management (Name, Email, Phone, Password Change)
  - Secure Avatar Uploads (JPG, PNG, WebP with MIME validation)
  - Security Audit & Activity Logging (`activity_logs` table)
  - Professional Business CMS UI (Solid color palette, no gradients)
  - Collapsible Desktop Sidebar with `localStorage` state persistence
  - Responsive Mobile Drawer Navigation
- **Phase 02: Customer Management (NEW)**
  - Customer Profile Management (Name, Phone, Email, Address, City, Postal Code, Notes)
  - Unique Sequential Customer Code Generation (`CUS-000001`, `CUS-000002`...)
  - Multi-field Search (`Customer Code`, `Name`, `Phone`, `Email`, `City`)
  - Status Filter (`All`, `Active`, `Inactive`)
  - Server-side Pagination (20 records per page) with query preservation
  - Soft-Delete Protection (`deleted_at` timestamp)
  - Duplicate Phone Validation
  - Role-based Action Restrictions (Staff restricted from deleting customer records)
  - Full Activity Logging on all Customer Actions

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
│   └── README.md                  # Database import guide
│
├── includes/
│   ├── auth_check.php             # Authentication guard (redirects guests & sets no-cache)
│   ├── guest_check.php            # Guest guard (redirects logged-in users to dashboard)
│   ├── header.php                 # HTML Head, Bootstrap 5, Icons, custom CSS
│   ├── footer.php                 # Footer layout, Bootstrap 5 JS & app.js
│   ├── sidebar.php                # Collapsible sidebar with active Customer navigation
│   ├── topbar.php                 # Top navigation bar & user profile menu
│   ├── flash_message.php          # Session-based flash alerts renderer
│   └── functions.php              # Global helpers (CSRF, escaping, URLs, customer codes)
│
├── modules/
│   ├── dashboard/
│   │   └── index.php              # Main Dashboard (User profile, customer metrics & roadmap)
│   │
│   ├── customers/
│   │   ├── index.php              # Customer listing with search, filter & pagination
│   │   ├── create.php             # Add customer form
│   │   ├── store.php              # Customer creation handler & validation
│   │   ├── show.php               # Customer detail view & order history placeholder
│   │   ├── edit.php               # Customer edit form
│   │   ├── update.php             # Customer update handler & validation
│   │   ├── delete.php             # Soft delete handler (Admin/Manager only)
│   │   └── toggle_status.php      # Customer status toggle handler
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

### Step 1: Create Database
Open phpMyAdmin (`http://localhost/phpmyadmin/`) or MySQL CLI:

```sql
CREATE DATABASE IF NOT EXISTS `laundry_mgt` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### Step 2: Import SQL Files in Order
1. Import `database/phase_01_authentication.sql`
2. Import `database/phase_02_customers.sql`

Via CLI:
```bash
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql
mysql -u root -p laundry_mgt < database/phase_02_customers.sql
```

---

## 6. Default Administrator Credentials

| Field | Value |
| :--- | :--- |
| **Login URL** | `http://localhost/laundry-mgt/auth/login.php` |
| **Email** | `admin@laundrymgt.com` |
| **Password** | `Password123!` |
| **Role** | `Administrator` |
| **Status** | `active` |

---

## 7. Customer Management Features

### Adding a Customer
1. Navigate to **Customers** in the sidebar or top menu.
2. Click **Add Customer**.
3. Fill in **Customer Name** and **Phone Number** (Required), plus optional Email, Address, City, Postal Code, Notes, and Status.
4. Click **Save Customer**. A unique `CUS-XXXXXX` code is automatically assigned.

### Searching & Filtering Customers
- Type any keyword in the search bar (`CUS-000001`, `Rahim`, `01711`, or `Dhaka`).
- Use the status dropdown to filter by `Active` or `Inactive`.
- Pagination preserves search and filter parameters across pages.

### Editing & Status Toggling
- Click the pencil icon on any customer row or the **Edit Customer** button on the details page.
- Toggle status quickly using the status switch button or modal dialog.

### Soft Deleting Customers
- Only **Administrator** and **Manager** roles can delete customer records.
- Deletion sets `deleted_at = NOW()`, preserving historical data integrity while removing the customer from active views.

---

## 8. Roles & Permissions (Phase 01 & 02)

| Role | View / Search | Create Customer | Edit Customer | Toggle Status | Delete Customer |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Administrator** | Yes | Yes | Yes | Yes | Yes |
| **Manager** | Yes | Yes | Yes | Yes | Yes |
| **Staff** | Yes | Yes | Yes | Yes | **No (403)** |

---

## 9. Development Roadmap

- **Phase 01 (Complete):** Authentication, Role Access, Security Baseline
- **Phase 02 (Complete):** Customer Management (Profiles, Search, Pagination, Soft Deletes)
- **Phase 03:** Laundry Services & Pricing (Dry Cleaning, Wash & Fold, Ironing)
- **Phase 04:** Laundry Orders & Tracking (Intake, Status, Pickup)
- **Phase 05:** Payments & Invoicing (POS, Receipts, Payment Status)
- **Phase 06:** Reports & Analytics (Revenue, Operations, Daily Intake)
- **Phase 07:** Staff Management & Role Permissions
- **Phase 08:** System Settings & Equipment Management