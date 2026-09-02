<?php
/**
 * Interactive Marketplace Web Installer
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/installer_helpers.php';

$installed = is_app_installed();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($step < 1 || $step > 5) {
    $step = 1;
}

$errorMessage   = $_SESSION['installer_error'] ?? null;
$successMessage = $_SESSION['installer_success'] ?? null;
unset($_SESSION['installer_error'], $_SESSION['installer_success']);

$csrfToken = installer_csrf_token();
$allReqs = get_system_requirements();
$canProceedFromStep1 = all_critical_requirements_pass();

$dbConfig = $_SESSION['installer_db'] ?? [
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'laundry_mgt',
    'user' => 'root',
    'pass' => ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer | <?= h(APP_NAME) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 15px;
        }
        .installer-container {
            max-width: 820px;
            margin: 0 auto;
            width: 100%;
        }
        .installer-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
            overflow: hidden;
        }
        .installer-header {
            background-color: #0284c7;
            color: #ffffff;
            padding: 24px 30px;
        }
        .step-pill {
            font-size: 0.8rem;
            padding: 6px 14px;
            border-radius: 20px;
            background-color: #e2e8f0;
            color: #475569;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .step-pill.active {
            background-color: #0284c7;
            color: #ffffff;
        }
        .step-pill.completed {
            background-color: #059669;
            color: #ffffff;
        }
        .table-req td, .table-req th {
            padding: 10px 14px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<div class="installer-container">

    <!-- App Header Branding -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center gap-2 mb-1">
            <i class="bi bi-droplet-half fs-2 text-primary"></i>
            <h1 class="h3 fw-bold text-dark mb-0"><?= h(APP_NAME) ?></h1>
        </div>
        <p class="text-muted small mb-0">Marketplace Installation Wizard</p>
    </div>

    <!-- ==================== IF ALREADY INSTALLED ==================== -->
    <?php if ($installed): ?>
        <div class="installer-card p-5 text-center">
            <div class="mb-3">
                <i class="bi bi-shield-lock-fill text-success" style="font-size: 4rem;"></i>
            </div>
            <h2 class="h4 fw-bold text-dark mb-2">Application is Already Installed</h2>
            <p class="text-muted mb-4" style="max-width: 540px; margin: 0 auto;">
                The Laundry Management System has already been installed and the installer is permanently locked for your security.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= h(BASE_URL) ?>/auth/login.php" class="btn btn-primary px-4 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Go to Admin Login
                </a>
                <a href="<?= h(BASE_URL) ?>/index.php" class="btn btn-outline-secondary px-4 py-2">
                    <i class="bi bi-house-door me-1"></i> Visit Website
                </a>
            </div>
            <div class="mt-4 pt-3 border-top text-muted small">
                To perform a fresh reinstall, refer to the <code>REINSTALLATION</code> section in the <code>README.md</code> documentation.
            </div>
        </div>
    <?php else: ?>

    <!-- ==================== INSTALLATION WIZARD ==================== -->
    <div class="installer-card">
        
        <!-- Header Banner -->
        <div class="installer-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 fw-bold mb-1">System Setup &amp; Configuration</h2>
                    <p class="small mb-0 opacity-75">Step <?= (int)$step ?> of 5</p>
                </div>
                <div>
                    <span class="badge bg-white text-dark fw-semibold px-3 py-2">v<?= h(APP_VERSION) ?></span>
                </div>
            </div>
        </div>

        <!-- Progress Tracker -->
        <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between gap-2">
            <span class="step-pill <?= $step === 1 ? 'active' : ($step > 1 ? 'completed' : '') ?>">
                <i class="bi <?= $step > 1 ? 'bi-check-circle-fill' : 'bi-1-circle-fill' ?>"></i> Requirements
            </span>
            <span class="step-pill <?= $step === 2 ? 'active' : ($step > 2 ? 'completed' : '') ?>">
                <i class="bi <?= $step > 2 ? 'bi-check-circle-fill' : 'bi-2-circle-fill' ?>"></i> Database
            </span>
            <span class="step-pill <?= $step === 3 ? 'active' : ($step > 3 ? 'completed' : '') ?>">
                <i class="bi <?= $step > 3 ? 'bi-check-circle-fill' : 'bi-3-circle-fill' ?>"></i> SQL Import
            </span>
            <span class="step-pill <?= $step === 4 ? 'active' : ($step > 4 ? 'completed' : '') ?>">
                <i class="bi <?= $step > 4 ? 'bi-check-circle-fill' : 'bi-4-circle-fill' ?>"></i> Administrator
            </span>
            <span class="step-pill <?= $step === 5 ? 'active' : '' ?>">
                <i class="bi bi-5-circle-fill"></i> Complete
            </span>
        </div>

        <div class="p-4 p-md-5">

            <!-- Alerts -->
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= $errorMessage ?></div>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div><?= h($successMessage) ?></div>
                </div>
            <?php endif; ?>

            <!-- ==================== STEP 1: REQUIREMENTS ==================== -->
            <?php if ($step === 1): ?>
                <h3 class="h5 fw-bold text-dark mb-2">Step 1: Check Server Requirements</h3>
                <p class="text-muted small mb-4">Please verify that your hosting server meets all essential PHP modules, configuration settings, and folder write permissions.</p>

                <div class="table-responsive border rounded mb-4">
                    <table class="table table-hover table-req mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Requirement</th>
                                <th>Required</th>
                                <th>Current</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allReqs as $r): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= h($r['name']) ?></td>
                                    <td><span class="text-muted small"><?= h($r['required']) ?></span></td>
                                    <td><span class="small font-monospace"><?= h($r['current']) ?></span></td>
                                    <td class="text-center">
                                        <?php if ($r['pass']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>PASS</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>FAIL</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <span class="text-muted small">
                        <?php if ($canProceedFromStep1): ?>
                            <i class="bi bi-check-circle text-success me-1"></i> All system requirements passed.
                        <?php else: ?>
                            <i class="bi bi-exclamation-circle text-danger me-1"></i> Critical requirements failed. Please adjust server config to proceed.
                        <?php endif; ?>
                    </span>
                    <a href="index.php?step=2" class="btn btn-primary px-4 <?= !$canProceedFromStep1 ? 'disabled' : '' ?>">
                        Next: Database Setup <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

            <!-- ==================== STEP 2: DATABASE CONFIGURATION ==================== -->
            <?php elseif ($step === 2): ?>
                <h3 class="h5 fw-bold text-dark mb-2">Step 2: Database Configuration</h3>
                <p class="text-muted small mb-4">Enter your MySQL database server connection credentials. You can click <strong>Test Connection</strong> to verify connectivity before saving.</p>

                <form action="process.php" method="POST" id="dbConfigForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_db">

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-8">
                            <label for="db_host" class="form-label small fw-semibold text-dark">Database Host <span class="text-danger">*</span></label>
                            <input type="text" name="db_host" id="db_host" class="form-control" value="<?= h($dbConfig['host']) ?>" required>
                            <div class="form-text">Usually <code>127.0.0.1</code> or <code>localhost</code>.</div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="db_port" class="form-label small fw-semibold text-dark">Port <span class="text-danger">*</span></label>
                            <input type="text" name="db_port" id="db_port" class="form-control" value="<?= h($dbConfig['port']) ?>" required>
                            <div class="form-text">Default MySQL port is <code>3306</code>.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="db_name" class="form-label small fw-semibold text-dark">Database Name <span class="text-danger">*</span></label>
                            <input type="text" name="db_name" id="db_name" class="form-control" value="<?= h($dbConfig['name']) ?>" required>
                            <div class="form-text">Target database for CMS tables.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="db_user" class="form-label small fw-semibold text-dark">Database Username <span class="text-danger">*</span></label>
                            <input type="text" name="db_user" id="db_user" class="form-control" value="<?= h($dbConfig['user']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="db_pass" class="form-label small fw-semibold text-dark">Database Password</label>
                            <input type="password" name="db_pass" id="db_pass" class="form-control" value="<?= h($dbConfig['pass']) ?>" placeholder="Leave blank if no password is set">
                        </div>
                    </div>

                    <!-- Connection Feedback Card -->
                    <div id="dbTestResult" class="p-3 border rounded bg-light mb-4 d-none"></div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                        <a href="index.php?step=1" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary px-3" id="btnTestDb">
                                <i class="bi bi-plug me-1"></i> Test Database Connection
                            </button>
                            <button type="submit" class="btn btn-primary px-4" id="btnSaveDb">
                                Save &amp; Proceed <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>
                </form>

            <!-- ==================== STEP 3: SQL FILE IMPORT ==================== -->
            <?php elseif ($step === 3): ?>
                <h3 class="h5 fw-bold text-dark mb-2">Step 3: Database Schema &amp; Seed Import</h3>
                <p class="text-muted small mb-4">Import the complete Laundry Management System database schema, core reference seed data, default services, and settings into database <code><?= h($dbConfig['name']) ?></code>.</p>

                <form action="process.php" method="POST" enctype="multipart/form-data" id="sqlImportForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="import_sql">

                    <div class="p-3 border rounded bg-light mb-4">
                        <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-file-earmark-code me-2 text-primary"></i>Choose SQL Source</h6>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="sql_source" id="sql_default" value="default" checked>
                            <label class="form-check-label fw-semibold text-dark" for="sql_default">
                                Use bundled default installation SQL (<code>database/install.sql</code>) <span class="badge bg-primary ms-1">Recommended</span>
                            </label>
                            <div class="text-muted small mt-1">Contains complete 16 tables, default laundry services, garment rates, expense categories, roles, and permissions.</div>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sql_source" id="sql_custom" value="custom">
                            <label class="form-check-label fw-semibold text-dark" for="sql_custom">
                                Upload a custom compatible SQL file
                            </label>
                            <div class="mt-2 d-none" id="customSqlUploadBox">
                                <input type="file" name="custom_sql_file" id="custom_sql_file" class="form-control form-control-sm" accept=".sql">
                                <div class="form-text">Select a clean <code>.sql</code> file (Max 10MB).</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info small d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
                        <div>
                            Upon successful import, your database configuration will be safely written to <code>config/db.php</code>.
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="index.php?step=2" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="btnRunImport">
                            <i class="bi bi-database-fill-down me-1"></i> Start Database Import
                        </button>
                    </div>
                </form>

            <!-- ==================== STEP 4: ADMINISTRATOR ACCOUNT & STORE CONFIG ==================== -->
            <?php elseif ($step === 4): ?>
                <h3 class="h5 fw-bold text-dark mb-2">Step 4: Create Administrator Account &amp; Store Setup</h3>
                <p class="text-muted small mb-4">Configure your primary super administrator account and initialize your store preferences.</p>

                <form action="process.php" method="POST" id="adminForm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_admin">

                    <!-- Admin Account Info -->
                    <div class="card mb-4 border shadow-none bg-light">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>Primary Administrator Account</h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="admin_name" class="form-label small fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="admin_name" id="admin_name" class="form-control" placeholder="e.g. System Administrator" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="admin_email" class="form-label small fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="admin_email" id="admin_email" class="form-control" placeholder="e.g. admin@yourdomain.com" required>
                                    <div class="form-text">Used for sign in and account recovery.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="admin_password" class="form-label small fw-semibold text-dark">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="admin_password" id="admin_password" class="form-control" placeholder="Min. 8 characters" required minlength="8">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="admin_password_confirm" class="form-label small fw-semibold text-dark">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="admin_password_confirm" id="admin_password_confirm" class="form-control" placeholder="Repeat password" required minlength="8">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Profile & Preferences -->
                    <div class="card mb-4 border shadow-none bg-light">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shop me-2 text-primary"></i>Store Identity &amp; Localization Defaults</h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="business_name" class="form-label small fw-semibold text-dark">Store / Business Name <span class="text-danger">*</span></label>
                                    <input type="text" name="business_name" id="business_name" class="form-control" value="Laundry Management System" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="business_phone" class="form-label small fw-semibold text-dark">Business Phone Number</label>
                                    <input type="text" name="business_phone" id="business_phone" class="form-control" placeholder="+1 (555) 000-0000">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="business_email" class="form-label small fw-semibold text-dark">Official Store Email</label>
                                    <input type="email" name="business_email" id="business_email" class="form-control" placeholder="support@yourdomain.com">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="business_address" class="form-label small fw-semibold text-dark">Store Physical Address</label>
                                    <input type="text" name="business_address" id="business_address" class="form-control" placeholder="123 Commercial St, City">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="timezone" class="form-label small fw-semibold text-dark">Timezone <span class="text-danger">*</span></label>
                                    <select name="timezone" id="timezone" class="form-select" required>
                                        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                                            <option value="<?= h($tz) ?>" <?= $tz === 'Asia/Dhaka' ? 'selected' : '' ?>><?= h($tz) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="currency" class="form-label small fw-semibold text-dark">Currency Code <span class="text-danger">*</span></label>
                                    <input type="text" name="currency" id="currency" class="form-control" value="BDT" maxlength="10" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="currency_symbol" class="form-label small fw-semibold text-dark">Currency Symbol <span class="text-danger">*</span></label>
                                    <input type="text" name="currency_symbol" id="currency_symbol" class="form-control font-monospace" value="$" maxlength="10" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="index.php?step=3" class="btn btn-outline-secondary px-3">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Complete Installation &amp; Lock System
                        </button>
                    </div>
                </form>

            <!-- ==================== STEP 5: INSTALLATION COMPLETE ==================== -->
            <?php elseif ($step === 5): ?>
                <?php $completedData = $_SESSION['installer_completed'] ?? []; ?>
                <div class="text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4.5rem;"></i>
                    </div>
                    <h3 class="h4 fw-bold text-dark mb-2">Congratulations! Installation Complete</h3>
                    <p class="text-muted mb-4" style="max-width: 580px; margin: 0 auto;">
                        <strong><?= h($completedData['business_name'] ?? APP_NAME) ?></strong> has been installed and configured successfully.
                        The installation has been permanently locked for your security.
                    </p>

                    <div class="card p-3 bg-light border text-start mb-4" style="max-width: 520px; margin: 0 auto;">
                        <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-info-circle me-1"></i> Installation Summary:</h6>
                        <div class="small text-muted mb-1">Application URL: <a href="<?= h(BASE_URL) ?>"><?= h(BASE_URL) ?></a></div>
                        <div class="small text-muted mb-1">Admin Sign In: <a href="<?= h(BASE_URL) ?>/auth/login.php"><?= h(BASE_URL) ?>/auth/login.php</a></div>
                        <?php if (!empty($completedData['admin_email'])): ?>
                            <div class="small text-muted mb-1">Administrator Email: <strong><?= h($completedData['admin_email']) ?></strong></div>
                        <?php endif; ?>
                        <div class="small text-muted">Lock File Created: <code>storage/install.lock</code></div>
                    </div>

                    <div>
                        <a href="<?= h(BASE_URL) ?>/auth/login.php" class="btn btn-primary btn-lg px-5 py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Go to Admin Sign In
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="text-center text-muted small mt-4">
        &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>. All rights reserved.
    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Interactive Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Custom SQL Radio Toggle
    const sqlDefault = document.getElementById('sql_default');
    const sqlCustom = document.getElementById('sql_custom');
    const customSqlUploadBox = document.getElementById('customSqlUploadBox');

    if (sqlDefault && sqlCustom && customSqlUploadBox) {
        sqlDefault.addEventListener('change', function () {
            customSqlUploadBox.classList.add('d-none');
        });
        sqlCustom.addEventListener('change', function () {
            customSqlUploadBox.classList.remove('d-none');
        });
    }

    // AJAX Database Test
    const btnTestDb = document.getElementById('btnTestDb');
    const dbTestResult = document.getElementById('dbTestResult');

    if (btnTestDb && dbTestResult) {
        btnTestDb.addEventListener('click', function () {
            btnTestDb.disabled = true;
            btnTestDb.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testing...';
            dbTestResult.className = 'p-3 border rounded bg-light mb-4';
            dbTestResult.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Connecting to database server...</div>';
            dbTestResult.classList.remove('d-none');

            const formData = new FormData();
            formData.append('action', 'test_db');
            formData.append('is_ajax', '1');
            formData.append('csrf_token', '<?= h($csrfToken) ?>');
            formData.append('db_host', document.getElementById('db_host').value);
            formData.append('db_port', document.getElementById('db_port').value);
            formData.append('db_name', document.getElementById('db_name').value);
            formData.append('db_user', document.getElementById('db_user').value);
            formData.append('db_pass', document.getElementById('db_pass').value);

            fetch('process.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnTestDb.disabled = false;
                btnTestDb.innerHTML = '<i class="bi bi-plug me-1"></i> Test Database Connection';

                if (data.success) {
                    let html = '<div class="text-success fw-semibold small mb-1"><i class="bi bi-check-circle-fill me-1"></i>' + data.message + '</div>';
                    if (!data.db_exists && data.can_create_db) {
                        html += '<div class="text-warning small mt-2">Database does not exist yet. <button type="button" class="btn btn-sm btn-outline-success ms-2" id="btnCreateDb">Create Database Now</button></div>';
                    } else if (data.has_laundry_tables) {
                        html += '<div class="alert alert-warning small py-2 px-3 mt-2 mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Notice:</strong> This database already contains Laundry Management System tables. Proceeding will overwrite or keep existing schema.</div>';
                    }
                    dbTestResult.className = 'p-3 border border-success-subtle rounded bg-success-subtle mb-4';
                    dbTestResult.innerHTML = html;

                    // Bind create database button if rendered
                    const btnCreateDb = document.getElementById('btnCreateDb');
                    if (btnCreateDb) {
                        btnCreateDb.addEventListener('click', function () {
                            btnCreateDb.disabled = true;
                            btnCreateDb.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating...';
                            const createData = new FormData();
                            createData.append('action', 'create_db');
                            createData.append('csrf_token', '<?= h($csrfToken) ?>');
                            createData.append('db_host', document.getElementById('db_host').value);
                            createData.append('db_port', document.getElementById('db_port').value);
                            createData.append('db_name', document.getElementById('db_name').value);
                            createData.append('db_user', document.getElementById('db_user').value);
                            createData.append('db_pass', document.getElementById('db_pass').value);

                            fetch('process.php', {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                body: createData
                            })
                            .then(r => r.json())
                            .then(cRes => {
                                alert(cRes.message);
                                btnTestDb.click();
                            });
                        });
                    }
                } else {
                    dbTestResult.className = 'p-3 border border-danger-subtle rounded bg-danger-subtle mb-4';
                    dbTestResult.innerHTML = '<div class="text-danger fw-semibold small"><i class="bi bi-x-circle-fill me-1"></i>' + data.message + '</div>';
                }
            })
            .catch(err => {
                btnTestDb.disabled = false;
                btnTestDb.innerHTML = '<i class="bi bi-plug me-1"></i> Test Database Connection';
                dbTestResult.className = 'p-3 border border-danger-subtle rounded bg-danger-subtle mb-4';
                dbTestResult.innerHTML = '<div class="text-danger small">Network or server error during connection test.</div>';
            });
        });
    }
});
</script>

</body>
</html>
