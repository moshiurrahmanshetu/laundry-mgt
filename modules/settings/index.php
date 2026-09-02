<?php
/**
 * System Settings & Business Profile Configuration View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators / Settings Managers
if (!has_permission('settings.manage') && !has_role('administrator')) {
    http_response_code(403);
    set_flash_message('error', 'Access Denied: You do not have permission to modify system settings.');
    redirect('modules/dashboard/index.php');
}

$pageTitle = 'System Settings';
$currentUser = current_user();
$settings = get_all_settings(true);

// Determine active tab
$activeTab = sanitize_input($_GET['tab'] ?? 'profile');
if (!in_array($activeTab, ['profile', 'general', 'invoice'], true)) {
    $activeTab = 'profile';
}

// Prepare Timezones List
$currentTimezone = get_setting('timezone', 'Asia/Dhaka');
$allTimezones = DateTimeZone::listIdentifiers();

// Prepare Date Formats
$currentDateFormat = get_setting('date_format', 'd/m/Y');
$sampleDate = new DateTime();
$dateFormats = [
    'd/m/Y' => 'DD/MM/YYYY (' . $sampleDate->format('d/m/Y') . ')',
    'm/d/Y' => 'MM/DD/YYYY (' . $sampleDate->format('m/d/Y') . ')',
    'Y-m-d' => 'YYYY-MM-DD (' . $sampleDate->format('Y-m-d') . ')',
    'd M Y' => 'DD Mon YYYY (' . $sampleDate->format('d M Y') . ')',
    'M d, Y' => 'Mon DD, YYYY (' . $sampleDate->format('M d, Y') . ')'
];

// Current Logo
$currentLogo = get_setting('business_logo');
$currentLogoUrl = business_logo_url($currentLogo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/dashboard/index.php') ?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Settings</li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-1">System Settings &amp; Configuration</h2>
            <p class="text-muted small mb-0">Manage business identity, general application defaults, and invoice layout options.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/dashboard/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Settings Card Container -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs card-header-tabs" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'profile' ? 'active fw-semibold' : '' ?>" 
                        id="profile-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#profile-pane" 
                        type="button" 
                        role="tab" 
                        aria-controls="profile-pane" 
                        aria-selected="<?= $activeTab === 'profile' ? 'true' : 'false' ?>">
                    <i class="bi bi-shop me-1 text-primary"></i> Business Profile
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'general' ? 'active fw-semibold' : '' ?>" 
                        id="general-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#general-pane" 
                        type="button" 
                        role="tab" 
                        aria-controls="general-pane" 
                        aria-selected="<?= $activeTab === 'general' ? 'true' : 'false' ?>">
                    <i class="bi bi-sliders me-1 text-primary"></i> General Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'invoice' ? 'active fw-semibold' : '' ?>" 
                        id="invoice-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#invoice-pane" 
                        type="button" 
                        role="tab" 
                        aria-controls="invoice-pane" 
                        aria-selected="<?= $activeTab === 'invoice' ? 'true' : 'false' ?>">
                    <i class="bi bi-receipt me-1 text-primary"></i> Invoice &amp; Receipts
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-4">
        <form action="<?= base_url('modules/settings/save.php') ?>" method="POST" enctype="multipart/form-data" id="settingsForm">
            <?= csrf_field() ?>
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?= e($activeTab) ?>">

            <div class="tab-content" id="settingsTabContent">
                
                <!-- ================= TAB 1: BUSINESS PROFILE ================= -->
                <div class="tab-pane fade <?= $activeTab === 'profile' ? 'show active' : '' ?>" 
                     id="profile-pane" 
                     role="tabpanel" 
                     aria-labelledby="profile-tab" 
                     tabindex="0">
                    
                    <div class="row g-4">
                        <div class="col-12 col-lg-8">
                            <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-building me-2 text-primary"></i>Business Identity &amp; Contact Details</h6>
                            
                            <div class="row g-3">
                                <!-- Business Name -->
                                <div class="col-12 col-md-6">
                                    <label for="business_name" class="form-label small fw-semibold text-dark">
                                        Business Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           name="business_name" 
                                           id="business_name" 
                                           class="form-control" 
                                           value="<?= e(get_setting('business_name', 'Laundry Management System')) ?>" 
                                           required>
                                    <div class="form-text">Displayed on invoices, top navigation, and printable documents.</div>
                                </div>

                                <!-- Business Phone -->
                                <div class="col-12 col-md-6">
                                    <label for="business_phone" class="form-label small fw-semibold text-dark">
                                        Business Phone Number
                                    </label>
                                    <input type="text" 
                                           name="business_phone" 
                                           id="business_phone" 
                                           class="form-control" 
                                           placeholder="e.g. +880 1700 000000"
                                           value="<?= e(get_setting('business_phone', '')) ?>">
                                </div>

                                <!-- Business Email -->
                                <div class="col-12 col-md-6">
                                    <label for="business_email" class="form-label small fw-semibold text-dark">
                                        Official Email Address
                                    </label>
                                    <input type="email" 
                                           name="business_email" 
                                           id="business_email" 
                                           class="form-control" 
                                           placeholder="e.g. support@laundrymgt.com"
                                           value="<?= e(get_setting('business_email', '')) ?>">
                                </div>

                                <!-- Website -->
                                <div class="col-12 col-md-6">
                                    <label for="business_website" class="form-label small fw-semibold text-dark">
                                        Business Website
                                    </label>
                                    <input type="url" 
                                           name="business_website" 
                                           id="business_website" 
                                           class="form-control" 
                                           placeholder="https://example.com"
                                           value="<?= e(get_setting('business_website', '')) ?>">
                                </div>

                                <!-- Short Description -->
                                <div class="col-12">
                                    <label for="business_description" class="form-label small fw-semibold text-dark">
                                        Tagline / Business Description
                                    </label>
                                    <input type="text" 
                                           name="business_description" 
                                           id="business_description" 
                                           class="form-control" 
                                           placeholder="e.g. Professional Laundry & Dry Cleaning Services"
                                           value="<?= e(get_setting('business_description', '')) ?>">
                                </div>

                                <!-- Physical Address -->
                                <div class="col-12">
                                    <label for="business_address" class="form-label small fw-semibold text-dark">
                                        Physical Store Address
                                    </label>
                                    <textarea name="business_address" 
                                              id="business_address" 
                                              class="form-control" 
                                              rows="2" 
                                              placeholder="Street address, City, Country"><?= e(get_setting('business_address', '')) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Logo Branding Column -->
                        <div class="col-12 col-lg-4">
                            <div class="p-3 border rounded bg-light">
                                <h6 class="fw-semibold text-dark mb-2"><i class="bi bi-image me-2 text-primary"></i>Store Brand Logo</h6>
                                <p class="text-muted small mb-3">Upload your store logo for invoice headers and receipts. Supported formats: PNG, JPG, WebP (Max 2MB).</p>

                                <div class="text-center p-3 bg-white border rounded mb-3">
                                    <?php if ($currentLogoUrl): ?>
                                        <img src="<?= e($currentLogoUrl) ?>" 
                                             alt="Store Logo" 
                                             class="img-fluid rounded mb-2" 
                                             style="max-height: 80px; object-fit: contain;">
                                        <div class="small text-muted font-monospace"><?= e($currentLogo) ?></div>
                                    <?php else: ?>
                                        <div class="py-3 text-muted">
                                            <i class="bi bi-image text-secondary fs-1 d-block mb-1"></i>
                                            <span class="small">No custom logo uploaded</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-2">
                                    <label for="business_logo" class="form-label small fw-semibold text-dark">Upload New Logo</label>
                                    <input type="file" 
                                           name="business_logo" 
                                           id="business_logo" 
                                           class="form-control form-control-sm" 
                                           accept="image/png, image/jpeg, image/webp">
                                </div>

                                <?php if ($currentLogo): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
                                        <label class="form-check-label small text-danger fw-semibold" for="removeLogo">
                                            Remove current store logo
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: GENERAL SETTINGS ================= -->
                <div class="tab-pane fade <?= $activeTab === 'general' ? 'show active' : '' ?>" 
                     id="general-pane" 
                     role="tabpanel" 
                     aria-labelledby="general-tab" 
                     tabindex="0">
                    
                    <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-globe me-2 text-primary"></i>Regional &amp; Localization Preferences</h6>
                    
                    <div class="row g-3">
                        <!-- Timezone -->
                        <div class="col-12 col-md-6">
                            <label for="timezone" class="form-label small fw-semibold text-dark">
                                System Timezone <span class="text-danger">*</span>
                            </label>
                            <select name="timezone" id="timezone" class="form-select" required>
                                <?php foreach ($allTimezones as $tz): ?>
                                    <option value="<?= e($tz) ?>" <?= $currentTimezone === $tz ? 'selected' : '' ?>>
                                        <?= e($tz) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Controls timestamp rendering for orders, payments, and activity logs.</div>
                        </div>

                        <!-- Date Format -->
                        <div class="col-12 col-md-6">
                            <label for="date_format" class="form-label small fw-semibold text-dark">
                                Date Display Format <span class="text-danger">*</span>
                            </label>
                            <select name="date_format" id="date_format" class="form-select" required>
                                <?php foreach ($dateFormats as $fmtKey => $fmtLabel): ?>
                                    <option value="<?= e($fmtKey) ?>" <?= $currentDateFormat === $fmtKey ? 'selected' : '' ?>>
                                        <?= e($fmtLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Presentation format across user tables and printable slips.</div>
                        </div>

                        <!-- Currency Code -->
                        <div class="col-12 col-md-6">
                            <label for="currency" class="form-label small fw-semibold text-dark">
                                Currency Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="currency" 
                                   id="currency" 
                                   class="form-control" 
                                   placeholder="e.g. BDT, USD, EUR, GBP"
                                   value="<?= e(get_setting('currency', 'BDT')) ?>" 
                                   maxlength="10"
                                   required>
                            <div class="form-text">Standard ISO currency code (e.g. BDT, USD).</div>
                        </div>

                        <!-- Currency Symbol -->
                        <div class="col-12 col-md-6">
                            <label for="currency_symbol" class="form-label small fw-semibold text-dark">
                                Currency Symbol <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="currency_symbol" 
                                   id="currency_symbol" 
                                   class="form-control font-monospace" 
                                   placeholder="e.g. ৳, $, €, £"
                                   value="<?= e(get_setting('currency_symbol', '$')) ?>" 
                                   maxlength="10"
                                   required>
                            <div class="form-text">Displayed beside prices throughout reports, order intake, and invoices.</div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 mt-4 small d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
                        <div>
                            <strong>Financial Safety:</strong> Changing the currency code or symbol updates presentation formatting only. Historical database transaction amounts remain unaltered and secure.
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 3: INVOICE & RECEIPTS ================= -->
                <div class="tab-pane fade <?= $activeTab === 'invoice' ? 'show active' : '' ?>" 
                     id="invoice-pane" 
                     role="tabpanel" 
                     aria-labelledby="invoice-tab" 
                     tabindex="0">
                    
                    <h6 class="fw-semibold text-dark mb-3"><i class="bi bi-printer me-2 text-primary"></i>Printable Invoices, Vouchers &amp; Receipts Layout</h6>
                    
                    <div class="row g-3">
                        <!-- Invoice Prefix -->
                        <div class="col-12 col-md-6">
                            <label for="invoice_prefix" class="form-label small fw-semibold text-dark">
                                Order / Invoice Prefix
                            </label>
                            <input type="text" 
                                   name="invoice_prefix" 
                                   id="invoice_prefix" 
                                   class="form-control font-monospace" 
                                   placeholder="e.g. INV-, ORD-"
                                   value="<?= e(get_setting('invoice_prefix', 'INV-')) ?>" 
                                   maxlength="15">
                            <div class="form-text">Reference prefix used on invoice headers.</div>
                        </div>

                        <!-- Receipt Prefix -->
                        <div class="col-12 col-md-6">
                            <label for="receipt_prefix" class="form-label small fw-semibold text-dark">
                                Payment Receipt Prefix
                            </label>
                            <input type="text" 
                                   name="receipt_prefix" 
                                   id="receipt_prefix" 
                                   class="form-control font-monospace" 
                                   placeholder="e.g. REC-, PAY-"
                                   value="<?= e(get_setting('receipt_prefix', 'REC-')) ?>" 
                                   maxlength="15">
                            <div class="form-text">Reference prefix used on payment receipts.</div>
                        </div>

                        <!-- Footer Text -->
                        <div class="col-12">
                            <label for="invoice_footer" class="form-label small fw-semibold text-dark">
                                Printable Terms &amp; Footer Note
                            </label>
                            <textarea name="invoice_footer" 
                                      id="invoice_footer" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="Thank you message, terms of service, or pickup policy"><?= e(get_setting('invoice_footer', 'Thank you for choosing our laundry services! Please keep this receipt for laundry pickup.')) ?></textarea>
                            <div class="form-text">Printed at the bottom of customer order invoices and payment vouchers.</div>
                        </div>

                        <!-- Display Options Toggles -->
                        <div class="col-12 mt-3">
                            <h6 class="small fw-semibold text-dark mb-2">Print Header Display Options</h6>
                            <div class="p-3 border rounded bg-light d-flex flex-column gap-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           role="switch" 
                                           name="show_business_logo" 
                                           id="show_business_logo" 
                                           value="1" 
                                           <?= get_setting('show_business_logo', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold text-dark" for="show_business_logo">
                                        Show Brand Logo in print headers (if uploaded)
                                    </label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           role="switch" 
                                           name="show_business_address" 
                                           id="show_business_address" 
                                           value="1" 
                                           <?= get_setting('show_business_address', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold text-dark" for="show_business_address">
                                        Show Store Physical Address in print headers
                                    </label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           role="switch" 
                                           name="show_business_phone" 
                                           id="show_business_phone" 
                                           value="1" 
                                           <?= get_setting('show_business_phone', '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-semibold text-dark" for="show_business_phone">
                                        Show Phone &amp; Email Contact Info in print headers
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <!-- Action Toolbar -->
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= base_url('modules/dashboard/index.php') ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check2-circle me-1"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    const tabInput = document.getElementById("activeTabInput");
    const tabButtons = document.querySelectorAll("#settingsTab button[data-bs-toggle=\"tab\"]");

    tabButtons.forEach(btn => {
        btn.addEventListener("shown.bs.tab", function (event) {
            const target = event.target.getAttribute("data-bs-target");
            if (target === "#profile-pane") {
                tabInput.value = "profile";
            } else if (target === "#general-pane") {
                tabInput.value = "general";
            } else if (target === "#invoice-pane") {
                tabInput.value = "invoice";
            }
        });
    });
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
