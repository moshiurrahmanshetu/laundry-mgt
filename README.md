# Laundry Management System (`laundry-mgt`) — Marketplace CMS Package

A complete, production-grade, professional Laundry Management System CMS built with **Raw PHP 8+**, **MySQL (PDO)**, **Bootstrap 5**, and **Bootstrap Icons**.

Includes a complete **WordPress-like web installer** for effortless first-run deployment on any web hosting environment, domain, or subdomain.

---

## 1. System Features & Modules

- **First-Run Web Installer (`/install/`):** Guided 5-step setup wizard with requirements verification, live database connection testing, automated schema import, administrator onboarding, and permanent server-side lock protection.
- **Authentication & Security:** Secure session handling, CSRF token verification, brute-force mitigation, bcrypt password hashing, and audit activity logging (`activity_logs`).
- **Customer Management:** Comprehensive customer directory, search, status filters, contact history, and soft-delete safeguards.
- **Laundry Services & Pricing:** Configurable service categories with support for dual pricing models: **Per Item / Garment** and **Per KG Weight**.
- **Laundry Order Intake & Lifecycle:** Interactive real-time calculator, customer order assignment, discount models, order tracking (`received` -> `processing` -> `ready` -> `delivered` / `cancelled`), and printable receipts.
- **Multi-Payment Transaction Ledger:** Partial payments, advances, receipt vouchers, concurrency-safe row locking, and financial voiding.
- **Pickup & Delivery Logistics:** Schedule customer pickups and deliveries, assign drivers/staff, permanent address snapshots, and printable dispatch slips.
- **Operations Workflow Queue:** Dedicated operational board for wash staff with visual step timelines, real-time stage counters, and work order printing.
- **Business Reports & Analytics:** Pure CSS KPI distribution bars, sales & revenue aggregation, payment methods breakdown, top customers, and service demand analytics.
- **Operating Expense Tracking:** Daily business expenses, custom categories, soft deletes, and printable payment vouchers.
- **Staff & Custom Roles Management:** Staff directory, custom roles, and a 25-point granular permission matrix with administrator lockout protection.
- **Settings & Business Configuration:** Dynamic business branding, logo uploads, system timezone, regional date formatting, currency code & symbol customization.

---

## 2. Server Requirements

Before installing, ensure your web hosting environment meets the following specifications:

| Requirement | Minimum Required | Recommended |
| :--- | :---: | :---: |
| **PHP Version** | **8.0.0** or higher | **8.2+** |
| **Database Server** | MySQL **5.7+** or MariaDB **10.3+** | MySQL **8.0+** / MariaDB **10.6+** |
| **Web Server** | Apache (with `mod_rewrite` enabled) | Apache / Nginx |
| **PDO Extension** | `pdo` & `pdo_mysql` | Enabled |
| **Core PHP Extensions** | `mbstring`, `openssl`, `fileinfo`, `json`, `session` | Enabled |
| **File Uploads** | `file_uploads = On` (`upload_max_filesize >= 2M`) | `10M` |
| **Writable Permissions** | `config/`, `storage/`, `uploads/`, `uploads/avatars/`, `uploads/logos/` | `0755` (Writable) |

---

## 3. First-Run Installation Instructions

### Step 1: Upload & Extract
1. Download the `laundry-management-system.zip` marketplace archive.
2. Extract all files into your web server's root directory (e.g. `public_html/` or a subfolder such as `public_html/laundry/`).

### Step 2: Open Website in Browser
1. Navigate to your website URL in any web browser:
   - Example: `https://yourdomain.com/` or `http://localhost/laundry-mgt/`
2. The system will automatically detect the uninstalled state and redirect to the **Installation Wizard** (`/install/index.php`).

### Step 3: Run the 5-Step Setup Wizard
1. **Step 1 (Requirements Check):** The installer will inspect your PHP version, required extensions, and directory write permissions. Click **Next: Database Setup**.
2. **Step 2 (Database Configuration):** Enter your MySQL Database Host (usually `127.0.0.1` or `localhost`), Port (`3306`), Database Name, Username, and Password. Click **Test Database Connection** to verify connectivity, then click **Save & Proceed**.
3. **Step 3 (SQL Schema Import):** Select the default installation SQL (`database/install.sql`) and click **Start Database Import**. The system will import all 16 tables, default laundry services, item rates, expense categories, and permissions, then write the database configuration to `config/db.php`.
4. **Step 4 (Administrator & Store Setup):** Enter your Administrator Full Name, Email Address, and Password (min 8 characters). Configure your Store Name, Phone, Address, Timezone, Currency Code, and Symbol. Click **Complete Installation & Lock System**.
5. **Step 5 (Complete):** The installation lock (`storage/install.lock`) will be created and the installer will be permanently locked. Click **Go to Admin Sign In**.

---

## 4. Reinstallation Procedure

Once installed, the installer is permanently locked server-side to prevent unauthorized modifications or reinstallation attempts.

To perform a clean reinstall:

> [!IMPORTANT]
> 1. **Backup Data:** Backup any existing database data or uploaded files if needed.
> 2. **Prepare a Fresh Database:** Create an empty MySQL database in your hosting control panel (cPanel / phpMyAdmin).
> 3. **Remove Lock & Generated Config Files:** On the server, delete only the following two files:
>    - `storage/install.lock`
>    - `config/db.php`
> 4. **Do NOT Delete Project Code:** Do not delete any core project files (`assets/`, `auth/`, `config/database.php`, `includes/`, `install/`, `modules/`, `database/install.sql`).
> 5. **Run the Installer:** Open your website URL in the browser. The installer will open automatically. Enter the new database credentials and complete the 5 steps.

---

## 5. File & Directory Structure

```text
laundry-mgt/
│
├── assets/                        # CSS stylesheets, JavaScript, brand icons, SVG assets
├── auth/                          # Login, logout, authenticate, password recovery
├── config/
│   ├── config.php                 # Master configuration & session bootstrap
│   ├── constants.php              # Global path constants, dynamic Base URL & is_app_installed()
│   ├── database.php               # Centralized PDO connection loader
│   ├── db.php                     # [Generated by Installer] Live database credentials
│   └── .htaccess                  # Directory browsing protection
│
├── database/
│   ├── install.sql                # [Master SQL] Consolidated fresh installation schema & seed data
│   ├── phase_01_authentication.sql ... phase_11_settings.sql # Phase migration archives
│   └── README.md                  # Database documentation
│
├── includes/
│   ├── auth_check.php             # Authentication guard with install check
│   ├── guest_check.php            # Guest guard with install check
│   ├── header.php                 # HTML Head, Bootstrap 5, Icons
│   ├── footer.php                 # Page footer & scripts
│   ├── sidebar.php                # Collapsible navigation drawer
│   ├── topbar.php                 # Top navigation & user profile dropdown
│   ├── flash_message.php          # Session alert notifications
│   └── functions.php              # Global business helpers, CSRF & permissions
│
├── install/                       # Web Installation Wizard
│   ├── index.php                  # Multi-step wizard UI (Requirements, DB, SQL, Admin, Complete)
│   ├── installer_helpers.php      # Requirement checks, DB tester, SQL parser, lock generator
│   └── process.php                # AJAX & POST handler for installation steps
│
├── modules/
│   ├── dashboard/                 # Overview dashboard & KPI cards
│   ├── customers/                 # Customer directory & profiles
│   ├── services/                  # Laundry service catalog & garment pricing
│   ├── orders/                    # Order intake, status tracking & invoice printing
│   ├── payments/                  # Multi-payment transactions & receipt vouchers
│   ├── delivery/                  # Pickup & delivery dispatch logistics
│   ├── operations/                # Laundry workflow queue & work orders
│   ├── reports/                   # Sales, payments, orders & customer analytics
│   ├── expenses/                  # Operating expense records & categories
│   ├── staff/                     # Staff accounts, custom roles & permission matrix
│   ├── settings/                  # Store branding, logo upload, timezone & currency
│   └── profile/                   # Personal user profile & password management
│
├── storage/
│   ├── install.lock               # [Generated by Installer] Permanent installation lock
│   └── .htaccess                  # Access denied protection
│
├── uploads/
│   ├── avatars/                   # User profile avatar images
│   ├── logos/                     # Store brand logo images
│   └── .htaccess                  # PHP execution disabled
│
├── .htaccess                      # Security rewrite rules & sensitive file protection
├── index.php                      # Application root entry point with install check
└── README.md                      # Marketplace documentation
```

---

## 6. Security Features

- **Permanent Server-Side Lock:** `storage/install.lock` prevents any access to the installer after completion. No query parameter bypass is possible.
- **Protected File Access:** `.htaccess` rules prevent direct web downloads of `.sql`, `.env`, `.log`, `.lock`, `.json`, `config/`, `storage/`, and `database/` files.
- **SQL Upload Security:** Uploaded SQL files are validated for `.sql` extensions, MIME types, and 10MB file size limits, and temporary files are purged immediately after execution.
- **Bcrypt Password Security:** All user and administrator passwords are encrypted using `password_hash()` with BCRYPT cost factor.
- **Strict Role & Permission Enforcement:** Every administrative action is guarded by `has_permission()` checks against the relational database permissions matrix.