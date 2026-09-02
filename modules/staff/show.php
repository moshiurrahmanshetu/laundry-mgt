<?php
/**
 * Staff Member Profile & Activity History View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Restricted to Administrators
require_role(['administrator']);

$staffId = (int)($_GET['id'] ?? 0);
if ($staffId <= 0) {
    set_flash_message('error', 'Invalid staff ID provided.');
    redirect('modules/staff/index.php');
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('
    SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.description AS role_description
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.id = :id AND u.deleted_at IS NULL
    LIMIT 1
');
$stmt->execute(['id' => $staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash_message('error', 'The requested staff account does not exist or has been deleted.');
    redirect('modules/staff/index.php');
}

$pageTitle = 'Staff: ' . $staff['name'];

// Fetch recent activity logs for this user
$logsStmt = $pdo->prepare('
    SELECT action, description, ip_address, created_at
    FROM activity_logs
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 10
');
$logsStmt->execute(['user_id' => $staffId]);
$activityLogs = $logsStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-1">
            <li class="breadcrumb-item"><a href="<?= base_url('modules/staff/index.php') ?>">Staff &amp; Roles</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($staff['name']) ?></li>
        </ol>
    </nav>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <img src="<?= e(user_avatar_url($staff['avatar'])) ?>" 
                 alt="Avatar" 
                 class="navbar-user-avatar border shadow-sm" 
                 style="width: 52px; height: 52px;">
            <div>
                <h2 class="h4 fw-bold text-dark mb-0"><?= e($staff['name']) ?></h2>
                <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                    <?= role_badge($staff['role_slug'], $staff['role_name']) ?>
                    <span>&bull;</span>
                    <?= user_status_badge($staff['status']) ?>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('modules/staff/edit.php?id=' . (int)$staff['id']) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i> Edit Account
            </a>
            <a href="<?= base_url('modules/staff/index.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- User Details Column -->
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Account Information</h3>
            </div>
            <div class="card-body p-3">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted" style="width: 120px;">Email:</td>
                        <td class="fw-semibold text-dark"><?= e($staff['email']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Phone:</td>
                        <td class="text-dark"><?= e($staff['phone'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Role:</td>
                        <td><?= role_badge($staff['role_slug'], $staff['role_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td><?= user_status_badge($staff['status']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Last Login:</td>
                        <td class="text-dark">
                            <?= $staff['last_login'] ? e(format_datetime($staff['last_login'])) : '<span class="text-muted fst-italic">Never</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created:</td>
                        <td class="text-muted"><?= e(format_datetime($staff['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Updated:</td>
                        <td class="text-muted"><?= e(format_datetime($staff['updated_at'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Role Overview -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-shield-check me-2 text-primary"></i>Assigned Role Description</h3>
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="fw-bold text-dark fs-6"><?= e($staff['role_name']) ?></span>
                    <span class="badge bg-light text-muted border font-monospace"><?= e($staff['role_slug']) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    <?= e($staff['role_description'] ?: 'No role description provided.') ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Recent Activity Column -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent User Activity History</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 140px;">Action</th>
                                <th>Description</th>
                                <th style="width: 110px;">IP Address</th>
                                <th class="text-end pe-3" style="width: 140px;">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activityLogs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-journal-x fs-2 d-block mb-1 text-secondary"></i>
                                        No recent activity recorded for this user.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activityLogs as $log): ?>
                                    <tr>
                                        <td class="ps-3 font-monospace">
                                            <span class="badge bg-light text-dark border"><?= e($log['action']) ?></span>
                                        </td>
                                        <td class="text-dark">
                                            <?= e($log['description'] ?: '—') ?>
                                        </td>
                                        <td class="text-muted font-monospace" style="font-size: 0.75rem;">
                                            <?= e($log['ip_address'] ?: '—') ?>
                                        </td>
                                        <td class="text-end pe-3 text-muted font-monospace" style="font-size: 0.75rem;">
                                            <?= e(format_datetime($log['created_at'], 'M d, H:i')) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
