# Laundry Management System (`laundry-mgt`) — Phase 01

A lightweight, professional, and secure Laundry Management System CMS built with **Raw PHP 8+**, **MySQL (PDO)**, **Bootstrap 5**, and **Bootstrap Icons**.

---

## 1. Project Purpose & Overview

The **Laundry Management System** is designed for commercial laundries, dry cleaners, and laundromats to manage operations, customer accounts, orders, washing/ironing workflows, deliveries, invoices, and staff permissions.

**Phase 01 (Foundation & Authentication System)** establishes:
- Complete Authentication System (Login, Secure Session, Logout, Password Recovery)
- Relational Role-Based Access Control (`roles` table: Administrator, Manager, Staff)
- User Profile Management (Name, Email, Phone)
- Secure Avatar Uploads (JPG, PNG, WebP with MIME validation)
- Password Change & Verification
- Security Audit & Activity Logging (`activity_logs` table)
- Professional Business CMS UI (Solid color palette, no gradients)
- Collapsible Desktop Sidebar with `localStorage` state persistence
- Responsive Mobile Drawer Navigation
- Comprehensive Security Baseline (CSRF, PDO Prepared Statements, Timing-Safe Password Hashing, XSS Escaping, Upload Guards)

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
│   └── README.md                  # Database import guide
│
├── includes/
│   ├── auth_check.php             # Authentication guard (redirects guests & sets no-cache)
│   ├── guest_check.php            # Guest guard (redirects logged-in users to dashboard)
│   ├── header.php                 # HTML Head, Bootstrap 5, Icons, custom CSS
│   ├── footer.php                 # Footer layout, Bootstrap 5 JS & app.js
│   ├── sidebar.php                # Collapsible sidebar with navigation items
│   ├── topbar.php                 # Top navigation bar & user profile menu
│   ├── flash_message.php          # Session-based flash alerts renderer
│   └── functions.php              # Global helpers (CSRF, escaping, URLs, auth & role checks)
│
├── modules/
│   ├── dashboard/
│   │   └── index.php              # Main Dashboard (User profile, security & roadmap)
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
Open phpMyAdmin (`http://localhost/phpmyadmin/`) or MySQL CLI and run:

```sql
CREATE DATABASE IF NOT EXISTS `laundry_mgt` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

### Step 2: Import `database/phase_01_authentication.sql`
In phpMyAdmin:
1. Select the `laundry_mgt` database.
2. Click the **Import** tab.
3. Choose `database/phase_01_authentication.sql` and click **Import**.

Or via Command Prompt / Terminal:
```bash
mysql -u root -p laundry_mgt < database/phase_01_authentication.sql
```

---

## 6. Configuration

### Database Credentials (`config/database.php`)
Configured for standard XAMPP environments:
```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'laundry_mgt');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Application URL (`config/constants.php`)
The application automatically detects the base URL dynamically (`http://localhost/laundry-mgt/`). If needed, you can configure a manual URL:
```php
define('CUSTOM_BASE_URL', 'http://localhost/laundry-mgt');
```

---

## 7. Default Administrator Credentials

Use these credentials to sign in after importing the database:

| Field | Value |
| :--- | :--- |
| **Login URL** | `http://localhost/laundry-mgt/auth/login.php` |
| **Email** | `admin@laundrymgt.com` |
| **Password** | `Password123!` |
| **Role** | `Administrator` |
| **Status** | `active` |

---

## 8. User Guides

### How to Sign In
1. Navigate to `http://localhost/laundry-mgt/` (automatically routes to `/auth/login.php`).
2. Enter your email (`admin@laundrymgt.com`) and password (`Password123!`).
3. Click **Sign In**. On success, you are redirected to the Dashboard.

### How to Edit Profile
1. Click your name/avatar in the top-right navbar and select **My Profile** (or click **My Profile** in the sidebar).
2. Update your **Full Name**, **Email Address**, and **Phone Number**.
3. Click **Save Changes**.

### How to Upload an Avatar
1. Navigate to **My Profile**.
2. Under the **Profile Picture** card, click **Choose File** and select an image (`.jpg`, `.jpeg`, `.png`, `.webp` up to 2MB).
3. Click **Update Avatar**.

### How to Change Password
1. Navigate to **My Profile** and scroll to the **Security & Change Password** section.
2. Enter your **Current Password**, **New Password**, and **Confirm New Password**.
3. Click **Update Password**.

### How to Sign Out
1. Click the profile dropdown in the top navbar and choose **Sign Out** (or navigate to `/auth/logout.php`).
2. Your session is securely destroyed, an audit log is recorded, and you are returned to the login page.

---

## 9. Roles & Permissions (Phase 01)

| Role ID | Role Name | Slug | Description |
| :--- | :--- | :--- | :--- |
| `1` | **Administrator** | `administrator` | Full system access and administrative control |
| `2` | **Manager** | `manager` | Operational management, order oversight, and reporting |
| `3` | **Staff** | `staff` | Laundry operations, order processing, and tracking |

---

## 10. Development Roadmap

- **Phase 01 (Active):** Project Foundation, Authentication & Access Control, Security Baseline
- **Phase 02:** Customer Management (Profiles, Contact, History)
- **Phase 03:** Laundry Services & Pricing (Dry Cleaning, Wash & Fold, Ironing)
- **Phase 04:** Laundry Orders & Tracking (Intake, Status, Pickup)
- **Phase 05:** Payments & Invoicing (POS, Receipts, Payment Status)
- **Phase 06:** Reports & Analytics (Revenue, Operations, Daily Intake)
- **Phase 07:** Staff Management & Role Permissions
- **Phase 08:** System Settings & Machine/Equipment Management