<?php
/**
 * Pickup & Delivery Requests Listing View
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pageTitle = 'Pickup & Delivery';
$currentUser = current_user();
$pdo = getDBConnection();

$canAssign = is_admin() || is_manager();
$canDelete = is_admin() || is_manager();

// Search & Filter parameters
$search       = sanitize_input($_GET['q'] ?? '');
$typeFilter   = sanitize_input($_GET['type'] ?? '');
$statusFilter = sanitize_input($_GET['status'] ?? '');
$dateFilter   = sanitize_input($_GET['date_filter'] ?? '');
$customDate   = sanitize_input($_GET['custom_date'] ?? '');
$staffFilter  = (int)($_GET['staff_id'] ?? 0);

$validTypes    = ['pickup', 'delivery'];
$validStatuses = ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'];

if (!in_array($typeFilter, $validTypes, true)) {
    $typeFilter = '';
}
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

// Fetch operational users for assignment filters & modals
$staffStmt = $pdo->query('
    SELECT u.id, u.name, r.name AS role_name
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.status = "active"
    ORDER BY u.name ASC
');
$assignableStaff = $staffStmt->fetchAll();

// Pagination setup
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Build WHERE conditions
$whereConditions = ['pd.deleted_at IS NULL'];
$params = [];

// Staff Role Visibility Restriction: Staff normally sees requests assigned to them or created by them
if (is_staff() && !is_admin() && !is_manager()) {
    $whereConditions[] = '(pd.assigned_to = :current_user_id OR pd.created_by = :current_user_id)';
    $params['current_user_id'] = $currentUser['id'];
}

if (!empty($search)) {
    $whereConditions[] = '(pd.reference_number LIKE :search OR o.order_number LIKE :search OR c.name LIKE :search OR pd.contact_phone LIKE :search OR pd.contact_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($typeFilter)) {
    $whereConditions[] = 'pd.type = :type';
    $params['type'] = $typeFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = 'pd.status = :status';
    $params['status'] = $statusFilter;
}

if ($staffFilter > 0) {
    $whereConditions[] = 'pd.assigned_to = :staff_id';
    $params['staff_id'] = $staffFilter;
}

// Quick Date Filtering Logic
if ($dateFilter === 'today') {
    $whereConditions[] = 'pd.scheduled_date = CURDATE()';
} elseif ($dateFilter === 'tomorrow') {
    $whereConditions[] = 'pd.scheduled_date = CURDATE() + INTERVAL 1 DAY';
} elseif ($dateFilter === 'week') {
    $whereConditions[] = 'pd.scheduled_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY)';
} elseif (!empty($customDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
    $whereConditions[] = 'pd.scheduled_date = :custom_date';
    $params['custom_date'] = $customDate;
}

$whereSql = implode(' AND ', $whereConditions);

// Quick Operational Counter Cards (Scoped to visibility)
$countPending = 0;
$countAssigned = 0;
$countInProgress = 0;
$countCompletedToday = 0;

try {
    $visFilter = is_staff() && !is_admin() && !is_manager() ? 'AND (assigned_to = ' . (int)$currentUser['id'] . ' OR created_by = ' . (int)$currentUser['id'] . ')' : '';

    $cStmt1 = $pdo->query("SELECT COUNT(*) FROM pickup_deliveries WHERE status = 'pending' AND deleted_at IS NULL {$visFilter}");
    $countPending = (int)$cStmt1->fetchColumn();

    $cStmt2 = $pdo->query("SELECT COUNT(*) FROM pickup_deliveries WHERE status = 'assigned' AND deleted_at IS NULL {$visFilter}");
    $countAssigned = (int)$cStmt2->fetchColumn();

    $cStmt3 = $pdo->query("SELECT COUNT(*) FROM pickup_deliveries WHERE status = 'in_progress' AND deleted_at IS NULL {$visFilter}");
    $countInProgress = (int)$cStmt3->fetchColumn();

    $cStmt4 = $pdo->query("SELECT COUNT(*) FROM pickup_deliveries WHERE status = 'completed' AND DATE(completed_at) = CURDATE() AND deleted_at IS NULL {$visFilter}");
    $countCompletedToday = (int)$cStmt4->fetchColumn();
} catch (PDOException $e) {}

// Count Total Filtered Records
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pickup_deliveries pd
    INNER JOIN orders o ON pd.order_id = o.id
    INNER JOIN customers c ON pd.customer_id = c.id
    LEFT JOIN users u ON pd.assigned_to = u.id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

if ($page > $totalPages && $totalRecords > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Fetch Paginated Records
$listSql = "
    SELECT pd.id, pd.reference_number, pd.order_id, pd.customer_id, pd.type,
           pd.address, pd.contact_name, pd.contact_phone, pd.scheduled_date,
           pd.scheduled_time, pd.status, pd.assigned_to, pd.completed_at,
           o.order_number, c.name AS customer_name, c.customer_code,
           u.name AS assigned_staff_name
    FROM pickup_deliveries pd
    INNER JOIN orders o ON pd.order_id = o.id
    INNER JOIN customers c ON pd.customer_id = c.id
    LEFT JOIN users u ON pd.assigned_to = u.id
    WHERE {$whereSql}
    ORDER BY pd.scheduled_date ASC, pd.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->execute();
$records = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/topbar.php';
?>

<!-- Header & Add Request Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Pickup &amp; Delivery</h2>
        <p class="text-muted small mb-0">Manage customer laundry pickups, delivery dispatch, and operational schedules.</p>
    </div>
    <div>
        <a href="<?= base_url('modules/delivery/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Request
        </a>
    </div>
</div>

<!-- Operational Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Pending Requests</div>
                    <div class="h4 fw-bold text-secondary mb-0"><?= $countPending ?></div>
                </div>
                <div class="p-2 bg-secondary-subtle text-secondary rounded fs-5">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Assigned Staff</div>
                    <div class="h4 fw-bold text-primary mb-0"><?= $countAssigned ?></div>
                </div>
                <div class="p-2 bg-primary-subtle text-primary rounded fs-5">
                    <i class="bi bi-person-badge"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">In Progress</div>
                    <div class="h4 fw-bold text-warning mb-0"><?= $countInProgress ?></div>
                </div>
                <div class="p-2 bg-warning-subtle text-warning rounded fs-5">
                    <i class="bi bi-truck"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 bg-white">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Completed Today</div>
                    <div class="h4 fw-bold text-success mb-0"><?= $countCompletedToday ?></div>
                </div>
                <div class="p-2 bg-success-subtle text-success rounded fs-5">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filters Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('modules/delivery/index.php') ?>" class="row g-2 align-items-center">
            <!-- Search Query -->
            <div class="col-12 col-md-3 col-lg-3">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           placeholder="Ref #, Order #, Customer..." 
                           value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                        <a href="<?= base_url('modules/delivery/index.php') ?>" class="btn btn-outline-secondary" title="Clear search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Type Filter -->
            <div class="col-6 col-md-2 col-lg-2">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="pickup" <?= $typeFilter === 'pickup' ? 'selected' : '' ?>>Pickup</option>
                    <option value="delivery" <?= $typeFilter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-6 col-md-2 col-lg-2">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="assigned" <?= $statusFilter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Date Filter Selector -->
            <div class="col-6 col-md-2 col-lg-2">
                <select name="date_filter" class="form-select" onchange="this.form.submit()">
                    <option value="">Any Schedule</option>
                    <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="tomorrow" <?= $dateFilter === 'tomorrow' ? 'selected' : '' ?>>Tomorrow</option>
                    <option value="week" <?= $dateFilter === 'week' ? 'selected' : '' ?>>Next 7 Days</option>
                </select>
            </div>

            <!-- Assigned Staff Filter (Admin & Manager) -->
            <?php if ($canAssign): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <select name="staff_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Staff</option>
                        <?php foreach ($assignableStaff as $as): ?>
                            <option value="<?= (int)$as['id'] ?>" <?= $staffFilter === (int)$as['id'] ? 'selected' : '' ?>>
                                <?= e($as['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Submit & Reset Buttons -->
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary px-3">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($typeFilter) || !empty($statusFilter) || !empty($dateFilter) || !empty($customDate) || $staffFilter > 0): ?>
                    <a href="<?= base_url('modules/delivery/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Requests Data Table Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 130px;">Reference #</th>
                        <th style="width: 100px;">Type</th>
                        <th style="width: 130px;">Order #</th>
                        <th>Customer / Contact</th>
                        <th>Address</th>
                        <th style="width: 150px;">Scheduled Date</th>
                        <th style="width: 140px;">Assigned To</th>
                        <th style="width: 120px;">Status</th>
                        <th class="text-end pe-3" style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-truck fs-1 d-block mb-2 text-secondary"></i>
                                    <h5 class="fw-semibold mb-1">No pickup or delivery requests found</h5>
                                    <p class="small mb-3">
                                        <?= (!empty($search) || !empty($typeFilter) || !empty($statusFilter) || !empty($dateFilter) || $staffFilter > 0) ? 'Try adjusting your search criteria or filter options.' : 'Create your first laundry pickup or delivery schedule.' ?>
                                    </p>
                                    <?php if (empty($search) && empty($typeFilter) && empty($statusFilter) && empty($dateFilter) && $staffFilter === 0): ?>
                                        <a href="<?= base_url('modules/delivery/create.php') ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-lg me-1"></i> New Request
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <!-- Reference Number -->
                                <td class="ps-3 font-monospace fw-semibold">
                                    <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$r['id']) ?>" class="text-decoration-none">
                                        <?= e($r['reference_number']) ?>
                                    </a>
                                </td>

                                <!-- Type -->
                                <td>
                                    <?= delivery_type_badge($r['type']) ?>
                                </td>

                                <!-- Order Number -->
                                <td class="font-monospace small">
                                    <a href="<?= base_url('modules/orders/show.php?id=' . (int)$r['order_id']) ?>" class="text-decoration-none">
                                        <?= e($r['order_number']) ?>
                                    </a>
                                </td>

                                <!-- Customer & Contact -->
                                <td>
                                    <a href="<?= base_url('modules/customers/show.php?id=' . (int)$r['customer_id']) ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                        <?= e($r['customer_name']) ?>
                                    </a>
                                    <span class="small text-muted font-monospace"><i class="bi bi-telephone me-1"></i><?= e($r['contact_phone']) ?></span>
                                </td>

                                <!-- Address -->
                                <td>
                                    <span class="small text-muted d-inline-block text-truncate" style="max-width: 220px;" title="<?= e($r['address']) ?>">
                                        <i class="bi bi-geo-alt me-1"></i><?= e($r['address']) ?>
                                    </span>
                                </td>

                                <!-- Scheduled Date & Time -->
                                <td>
                                    <div class="small fw-semibold <?= strtotime($r['scheduled_date']) < strtotime('today') && $r['status'] !== 'completed' ? 'text-danger' : 'text-dark' ?>">
                                        <?= e(format_datetime($r['scheduled_date'], 'M d, Y')) ?>
                                    </div>
                                    <?php if (!empty($r['scheduled_time'])): ?>
                                        <span class="small text-muted font-monospace"><?= date('h:i A', strtotime($r['scheduled_time'])) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Assigned Staff -->
                                <td>
                                    <?php if (!empty($r['assigned_staff_name'])): ?>
                                        <span class="small text-dark fw-semibold"><i class="bi bi-person me-1"></i><?= e($r['assigned_staff_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border">Unassigned</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td>
                                    <?= delivery_status_badge($r['status']) ?>
                                </td>

                                <!-- Actions -->
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- View -->
                                        <a href="<?= base_url('modules/delivery/show.php?id=' . (int)$r['id']) ?>" 
                                           class="btn btn-outline-secondary" 
                                           title="View Details"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <!-- Update Status Modal Button -->
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-update-status" 
                                                data-id="<?= (int)$r['id'] ?>"
                                                data-ref="<?= e($r['reference_number']) ?>"
                                                data-current-status="<?= e($r['status']) ?>"
                                                title="Update Status"
                                                data-bs-toggle="tooltip">
                                            <i class="bi bi-arrow-repeat text-primary"></i>
                                        </button>

                                        <!-- Assign Modal Button (Admin & Manager) -->
                                        <?php if ($canAssign): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-assign-staff" 
                                                    data-id="<?= (int)$r['id'] ?>"
                                                    data-ref="<?= e($r['reference_number']) ?>"
                                                    data-assigned="<?= (int)($r['assigned_to'] ?? 0) ?>"
                                                    title="Assign Staff"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-person-check text-success"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- Print Slip -->
                                        <a href="<?= base_url('modules/delivery/print.php?id=' . (int)$r['id']) ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-secondary" 
                                           title="Print Slip"
                                           data-bs-toggle="tooltip">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        <!-- Delete (Admin Only) -->
                                        <?php if ($canDelete): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-delete-record" 
                                                    data-id="<?= (int)$r['id'] ?>"
                                                    data-ref="<?= e($r['reference_number']) ?>"
                                                    data-type="<?= e(ucfirst($r['type'])) ?>"
                                                    title="Delete Request"
                                                    data-bs-toggle="tooltip">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination & Summary Footer -->
    <?php if ($totalRecords > 0): ?>
        <div class="card-footer bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-muted small">
                Showing <strong class="text-dark"><?= $offset + 1 ?></strong> to <strong class="text-dark"><?= min($offset + $perPage, $totalRecords) ?></strong> of <strong class="text-dark"><?= $totalRecords ?></strong> requests
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Requests list pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/delivery/index.php?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage   = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('modules/delivery/index.php?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= base_url('modules/delivery/index.php?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Update Status -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/delivery/update_status.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="statusRecordId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="updateStatusModalLabel">Update Request Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Request: <strong id="statusRecordRef" class="text-dark font-monospace"></strong></p>
                    <label for="new_status" class="form-label small text-muted">Select Status</label>
                    <select name="status" id="new_status" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="assigned">Assigned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Assign Staff (Admin & Manager) -->
<?php if ($canAssign): ?>
<div class="modal fade" id="assignStaffModal" tabindex="-1" aria-labelledby="assignStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= base_url('modules/delivery/assign.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="assignRecordId" value="">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6 fw-semibold" id="assignStaffModalLabel">Assign Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Request: <strong id="assignRecordRef" class="text-dark font-monospace"></strong></p>
                    <label for="assigned_to_select" class="form-label small text-muted">Select Staff Member</label>
                    <select name="assigned_to" id="assigned_to_select" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($assignableStaff as $as): ?>
                            <option value="<?= (int)$as['id'] ?>"><?= e($as['name']) ?> (<?= e($as['role_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Soft Delete Confirmation (Admin Only) -->
<?php if ($canDelete): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('modules/delivery/delete.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deleteRecordId" value="">
                <div class="modal-header py-3 bg-danger-subtle text-danger">
                    <h5 class="modal-title fs-6 fw-semibold" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="mb-2">Are you sure you want to delete <span id="deleteRecordType" class="text-lowercase"></span> request <strong id="deleteRecordRef" class="font-monospace"></strong>?</p>
                    <p class="small text-muted mb-0">This record will be soft-deleted from active schedules.</p>
                </div>
                <div class="modal-footer py-2 justify-content-between bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Status Modal
    const statusButtons = document.querySelectorAll(".btn-update-status");
    const statusModalEl = document.getElementById("updateStatusModal");
    const statusRecordId = document.getElementById("statusRecordId");
    const statusRecordRef = document.getElementById("statusRecordRef");
    const newStatusSelect = document.getElementById("new_status");

    if (statusModalEl && typeof bootstrap !== "undefined") {
        const statusModal = new bootstrap.Modal(statusModalEl);
        statusButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                statusRecordId.value = this.getAttribute("data-id");
                statusRecordRef.textContent = this.getAttribute("data-ref");
                newStatusSelect.value = this.getAttribute("data-current-status");
                statusModal.show();
            });
        });
    }

    // Assign Modal
    const assignButtons = document.querySelectorAll(".btn-assign-staff");
    const assignModalEl = document.getElementById("assignStaffModal");
    const assignRecordId = document.getElementById("assignRecordId");
    const assignRecordRef = document.getElementById("assignRecordRef");
    const assignSelect = document.getElementById("assigned_to_select");

    if (assignModalEl && typeof bootstrap !== "undefined") {
        const assignModal = new bootstrap.Modal(assignModalEl);
        assignButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                assignRecordId.value = this.getAttribute("data-id");
                assignRecordRef.textContent = this.getAttribute("data-ref");
                assignSelect.value = this.getAttribute("data-assigned") || "";
                assignModal.show();
            });
        });
    }

    // Delete Modal
    const deleteButtons = document.querySelectorAll(".btn-delete-record");
    const deleteModalEl = document.getElementById("deleteModal");
    const deleteRecordId = document.getElementById("deleteRecordId");
    const deleteRecordRef = document.getElementById("deleteRecordRef");
    const deleteRecordType = document.getElementById("deleteRecordType");

    if (deleteModalEl && typeof bootstrap !== "undefined") {
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteButtons.forEach(btn => {
            btn.addEventListener("click", function () {
                deleteRecordId.value = this.getAttribute("data-id");
                deleteRecordRef.textContent = this.getAttribute("data-ref");
                deleteRecordType.textContent = this.getAttribute("data-type");
                deleteModal.show();
            });
        });
    }
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';
?>
