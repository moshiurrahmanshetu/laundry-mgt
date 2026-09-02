<?php
/**
 * Global Helper Functions & Security Baseline
 * Project: Laundry Management System (laundry-mgt)
 */

// Load config and flash message system
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/flash_message.php';

/**
 * Escape HTML output to prevent XSS
 *
 * @param mixed $string
 * @return string
 */
function e(mixed $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate full URL within the Laundry Management System
 *
 * @param string $path
 * @return string
 */
function base_url(string $path = ''): string {
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    return $path !== '' ? $base . '/' . $path : $base;
}

/**
 * Generate asset URL
 *
 * @param string $path
 * @return string
 */
function asset_url(string $path = ''): string {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Safe redirect to relative application path or external URL
 *
 * @param string $path
 * @return void
 */
function redirect(string $path): void {
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $path = base_url($path);
    }
    header('Location: ' . $path);
    exit;
}

/**
 * Get or create CSRF token
 *
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden CSRF form input field
 *
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST or passed parameter
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the user is authenticated
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user details with role join
 *
 * @param bool $fresh
 * @return array|null
 */
function current_user(bool $fresh = false): ?array {
    if (!is_logged_in()) {
        return null;
    }

    if (!$fresh && !empty($_SESSION['user_cache'])) {
        return $_SESSION['user_cache'];
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            SELECT u.id, u.role_id, r.name AS role_name, r.slug AS role_slug,
                   u.name, u.email, u.phone, u.avatar, u.status, u.last_login, u.created_at
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_cache'] = $user;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role_name'];
            $_SESSION['role_slug'] = $user['role_slug'];
            return $user;
        }
    } catch (PDOException $e) {
        error_log('Error loading current user: ' . $e->getMessage());
    }

    return [
        'id'         => $_SESSION['user_id'] ?? 0,
        'role_id'    => $_SESSION['role_id'] ?? 1,
        'role_name'  => $_SESSION['user_role'] ?? 'Administrator',
        'role_slug'  => $_SESSION['role_slug'] ?? 'administrator',
        'name'       => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'phone'      => '',
        'avatar'     => null,
        'status'     => 'active',
        'last_login' => null
    ];
}

/**
 * Role authorization helper
 *
 * @param string|array $roles
 * @return bool
 */
function has_role(string|array $roles): bool {
    $user = current_user();
    if (!$user) return false;

    $userRoleSlug = $user['role_slug'] ?? '';
    $userRoleName = $user['role_name'] ?? '';

    if (is_string($roles)) {
        $roles = [$roles];
    }

    foreach ($roles as $r) {
        $rNorm = strtolower(trim($r));
        if ($rNorm === strtolower($userRoleSlug) || $rNorm === strtolower($userRoleName)) {
            return true;
        }
    }

    return false;
}

function is_admin(): bool {
    return has_role('administrator');
}

function is_manager(): bool {
    return has_role('manager');
}

function is_staff(): bool {
    return has_role('staff');
}

/**
 * Require specific role or deny access
 *
 * @param string|array $roles
 * @param string|null $redirectPath
 * @return void
 */
function require_role(string|array $roles, ?string $redirectPath = null): void {
    if (!has_role($roles)) {
        http_response_code(403);
        set_flash_message('error', 'Access Denied: You do not have permission to perform this action.');
        redirect($redirectPath ?? 'modules/dashboard/index.php');
    }
}

/**
 * Record activity/audit log
 *
 * @param int|null $userId
 * @param string $action
 * @param string|null $description
 * @return void
 */
function log_activity(?int $userId, string $action, ?string $description = null): void {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())
        ');
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * Generate unique sequential customer code (e.g. CUS-000001)
 *
 * @param PDO|null $pdo
 * @return string
 */
function generate_customer_code(?PDO $pdo = null): string {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->query('SELECT customer_code FROM customers ORDER BY id DESC LIMIT 1');
        $lastCode = $stmt->fetchColumn();

        $nextNumber = 1;
        if ($lastCode && preg_match('/^CUS-(\d+)$/', $lastCode, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        do {
            $candidateCode = sprintf('CUS-%06d', $nextNumber);
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE customer_code = :code');
            $checkStmt->execute(['code' => $candidateCode]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateCode;
    } catch (PDOException $e) {
        error_log('Failed to generate customer code: ' . $e->getMessage());
        return 'CUS-' . date('ymd') . rand(100, 999);
    }
}

/**
 * Generate unique URL slug for laundry services
 *
 * @param string $name
 * @param int|null $excludeId
 * @param PDO|null $pdo
 * @return string
 */
function generate_service_slug(string $name, ?int $excludeId = null, ?PDO $pdo = null): string {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) {
        $slug = 'service-' . time();
    }

    $baseSlug = $slug;
    $counter = 1;

    do {
        $sql = 'SELECT COUNT(*) FROM services WHERE slug = :slug AND deleted_at IS NULL';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }
    } while ($exists);

    return $slug;
}

/**
 * Generate unique sequential order number (e.g. ORD-000001)
 *
 * @param PDO|null $pdo
 * @return string
 */
function generate_order_number(?PDO $pdo = null): string {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->query('SELECT order_number FROM orders ORDER BY id DESC LIMIT 1');
        $lastNumber = $stmt->fetchColumn();

        $nextNumber = 1;
        if ($lastNumber && preg_match('/^ORD-(\d+)$/', $lastNumber, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        do {
            $candidateNumber = sprintf('ORD-%06d', $nextNumber);
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_number = :num');
            $checkStmt->execute(['num' => $candidateNumber]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateNumber;
    } catch (PDOException $e) {
        error_log('Failed to generate order number: ' . $e->getMessage());
        return 'ORD-' . date('ymd') . rand(100, 999);
    }
}

/**
 * Generate unique sequential payment number (e.g. PAY-000001)
 *
 * @param PDO|null $pdo
 * @return string
 */
function generate_payment_number(?PDO $pdo = null): string {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->query('SELECT payment_number FROM payments ORDER BY id DESC LIMIT 1');
        $lastNumber = $stmt->fetchColumn();

        $nextNumber = 1;
        if ($lastNumber && preg_match('/^PAY-(\d+)$/', $lastNumber, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        do {
            $candidateNumber = sprintf('PAY-%06d', $nextNumber);
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE payment_number = :num');
            $checkStmt->execute(['num' => $candidateNumber]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateNumber;
    } catch (PDOException $e) {
        error_log('Failed to generate payment number: ' . $e->getMessage());
        return 'PAY-' . date('ymd') . rand(100, 999);
    }
}

/**
 * Generate unique sequential pickup or delivery reference number (e.g. PU-000001 or DL-000001)
 *
 * @param string $type
 * @param PDO|null $pdo
 * @return string
 */
function generate_pickup_delivery_reference(string $type, ?PDO $pdo = null): string {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    $prefix = strtolower($type) === 'pickup' ? 'PU' : 'DL';

    try {
        $stmt = $pdo->prepare('
            SELECT reference_number 
            FROM pickup_deliveries 
            WHERE reference_number LIKE :prefix 
            ORDER BY id DESC 
            LIMIT 1
        ');
        $stmt->execute(['prefix' => $prefix . '-%']);
        $lastRef = $stmt->fetchColumn();

        $nextNumber = 1;
        if ($lastRef && preg_match('/^' . $prefix . '-(\d+)$/', $lastRef, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        do {
            $candidateRef = sprintf('%s-%06d', $prefix, $nextNumber);
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM pickup_deliveries WHERE reference_number = :ref');
            $checkStmt->execute(['ref' => $candidateRef]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateRef;
    } catch (PDOException $e) {
        error_log('Failed to generate pickup/delivery reference: ' . $e->getMessage());
        return $prefix . '-' . date('ymd') . rand(100, 999);
    }
}

/**
 * Render Bootstrap badge for pickup/delivery type
 *
 * @param string $type
 * @return string
 */
function delivery_type_badge(string $type): string {
    $type = strtolower(trim($type));
    return match ($type) {
        'pickup'   => '<span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase"><i class="bi bi-box-arrow-in-down-left me-1"></i>Pickup</span>',
        'delivery' => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase"><i class="bi bi-truck me-1"></i>Delivery</span>',
        default    => '<span class="badge bg-light text-dark border text-uppercase">' . e($type) . '</span>'
    };
}

/**
 * Render Bootstrap badge for pickup/delivery status
 *
 * @param string $status
 * @return string
 */
function delivery_status_badge(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'pending'     => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase"><i class="bi bi-hourglass-split me-1"></i>Pending</span>',
        'assigned'    => '<span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase"><i class="bi bi-person-badge me-1"></i>Assigned</span>',
        'in_progress' => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase"><i class="bi bi-truck me-1"></i>In Progress</span>',
        'completed'   => '<span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase"><i class="bi bi-check-circle me-1"></i>Completed</span>',
        'cancelled'   => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase"><i class="bi bi-x-circle me-1"></i>Cancelled</span>',
        default       => '<span class="badge bg-light text-dark border text-uppercase">' . e($status) . '</span>'
    };
}

/**
 * Get clean human-readable label for payment methods
 *
 * @param string $method
 * @return string
 */
function payment_method_label(string $method): string {
    $method = strtolower(trim($method));
    return match ($method) {
        'cash'           => 'Cash',
        'card'           => 'Credit/Debit Card',
        'mobile_banking' => 'Mobile Banking',
        'bank_transfer'  => 'Bank Transfer',
        'other'          => 'Other',
        default          => ucfirst(str_replace('_', ' ', $method))
    };
}

/**
 * Render Bootstrap badge for payment method
 *
 * @param string $method
 * @return string
 */
function payment_method_badge(string $method): string {
    $label = payment_method_label($method);
    $icon = match (strtolower(trim($method))) {
        'cash'           => 'bi-cash',
        'card'           => 'bi-credit-card',
        'mobile_banking' => 'bi-phone',
        'bank_transfer'  => 'bi-bank',
        default          => 'bi-wallet2'
    };
    return '<span class="badge bg-light text-dark border"><i class="bi ' . $icon . ' me-1 text-secondary"></i>' . e($label) . '</span>';
}

/**
 * Render Bootstrap badge for payment transaction status
 *
 * @param string $status
 * @return string
 */
function payment_record_status_badge(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'completed' => '<span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase"><i class="bi bi-check-circle me-1"></i>Completed</span>',
        'voided'    => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase"><i class="bi bi-slash-circle me-1"></i>Voided</span>',
        default     => '<span class="badge bg-light text-dark border text-uppercase">' . e($status) . '</span>'
    };
}

/**
 * Recalculate order payment summary authoritatively from completed payments in DB
 *
 * @param int $orderId
 * @param PDO|null $pdo
 * @return array
 */
function recalculate_order_payment_summary(int $orderId, ?PDO $pdo = null): array {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    // 1. Fetch current order total
    $ordStmt = $pdo->prepare('SELECT id, total FROM orders WHERE id = :id LIMIT 1');
    $ordStmt->execute(['id' => $orderId]);
    $order = $ordStmt->fetch();

    if (!$order) {
        return [
            'total'          => 0.00,
            'paid_amount'    => 0.00,
            'due_amount'     => 0.00,
            'payment_status' => 'unpaid'
        ];
    }

    $total = (float)$order['total'];

    // 2. Sum valid completed non-deleted payments
    $sumStmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
        WHERE order_id = :order_id AND status = "completed" AND deleted_at IS NULL
    ');
    $sumStmt->execute(['order_id' => $orderId]);
    $paidAmount = round((float)$sumStmt->fetchColumn(), 2);

    // 3. Calculate due amount and payment status
    $dueAmount = round(max(0, $total - $paidAmount), 2);

    if ($total == 0 || $paidAmount >= $total) {
        $paymentStatus = 'paid';
    } elseif ($paidAmount > 0) {
        $paymentStatus = 'partial';
    } else {
        $paymentStatus = 'unpaid';
    }

    // 4. Update order summary record
    $updateStmt = $pdo->prepare('
        UPDATE orders SET
            paid_amount    = :paid_amount,
            due_amount     = :due_amount,
            payment_status = :payment_status,
            updated_at     = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        'paid_amount'    => $paidAmount,
        'due_amount'     => $dueAmount,
        'payment_status' => $paymentStatus,
        'id'             => $orderId
    ]);

    return [
        'total'          => $total,
        'paid_amount'    => $paidAmount,
        'due_amount'     => $dueAmount,
        'payment_status' => $paymentStatus
    ];
}

/**
 * Render Bootstrap badge HTML for order status
 *
 * @param string $status
 * @return string
 */
function order_status_badge(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'received'   => '<span class="badge bg-info-subtle text-info border border-info-subtle text-uppercase"><i class="bi bi-inbox me-1"></i>Received</span>',
        'processing' => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase"><i class="bi bi-gear me-1"></i>Processing</span>',
        'ready'      => '<span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase"><i class="bi bi-check2-circle me-1"></i>Ready</span>',
        'delivered'  => '<span class="badge bg-dark text-white border text-uppercase"><i class="bi bi-bag-check me-1"></i>Delivered</span>',
        'cancelled'  => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase"><i class="bi bi-x-circle me-1"></i>Cancelled</span>',
        default      => '<span class="badge bg-secondary-subtle text-secondary border text-uppercase">' . e($status) . '</span>',
    };
}

/**
 * Render Bootstrap badge HTML for payment status
 *
 * @param string $status
 * @return string
 */
function payment_status_badge(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'paid'    => '<span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase"><i class="bi bi-check-circle me-1"></i>Paid</span>',
        'partial' => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle text-uppercase"><i class="bi bi-pie-chart me-1"></i>Partial</span>',
        'unpaid'  => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle text-uppercase"><i class="bi bi-exclamation-circle me-1"></i>Unpaid</span>',
        default   => '<span class="badge bg-secondary-subtle text-secondary border text-uppercase">' . e($status) . '</span>',
    };
}

/**
 * Format currency price
 *
 * @param float|string|null $price
 * @param string|null $currency
 * @return string
 */
function format_price(float|string|null $price, ?string $currency = null): string {
    if ($currency === null) {
        $currency = (string)get_setting('currency_symbol', '$');
    }
    return $currency . number_format((float)($price ?? 0), 2);
}

/**
 * Sanitize text input
 *
 * @param mixed $data
 * @return string
 */
function sanitize_input(mixed $data): string {
    return trim((string)($data ?? ''));
}

/**
 * Format datetime cleanly
 *
 * @param string|null $datetime
 * @param string $format
 * @return string
 */
function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) {
        return 'Never';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$datetime;
    }
}

/**
 * Get avatar URL with fallback
 *
 * @param string|null $avatarFilename
 * @return string
 */
function user_avatar_url(?string $avatarFilename): string {
    if (!empty($avatarFilename)) {
        $fullPath = AVATAR_PATH . DIRECTORY_SEPARATOR . $avatarFilename;
        if (file_exists($fullPath)) {
            return AVATAR_URL . '/' . $avatarFilename;
        }
    }
    return asset_url('images/default-avatar.svg');
}

/**
 * Extract 2-letter uppercase initials from full name
 *
 * @param string $name
 * @return string
 */
function get_user_initials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
    }
    return $initials ?: 'U';
}

/**
 * Parse and validate standard report date range parameters
 *
 * @param array $queryParams
 * @param string $defaultPreset
 * @return array ['preset', 'start_date', 'end_date', 'label']
 */
function get_report_date_range(array $queryParams = [], string $defaultPreset = 'this_month'): array {
    $preset = sanitize_input($queryParams['range'] ?? $defaultPreset);
    $startDate = sanitize_input($queryParams['start_date'] ?? '');
    $endDate = sanitize_input($queryParams['end_date'] ?? '');

    $today = date('Y-m-d');
    $label = 'This Month';

    switch ($preset) {
        case 'today':
            $startDate = $today;
            $endDate = $today;
            $label = 'Today (' . date('M d, Y') . ')';
            break;
        case 'yesterday':
            $startDate = date('Y-m-d', strtotime('-1 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
            $label = 'Yesterday (' . date('M d, Y', strtotime('-1 day')) . ')';
            break;
        case 'this_week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = $today;
            $label = 'This Week (' . date('M d', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
            break;
        case 'last_month':
            $startDate = date('Y-m-01', strtotime('first day of last month'));
            $endDate = date('Y-m-t', strtotime('last day of last month'));
            $label = 'Last Month (' . date('F Y', strtotime($startDate)) . ')';
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = $today;
            $label = 'This Year (' . date('Y') . ')';
            break;
        case 'all_time':
            $startDate = '2020-01-01';
            $endDate = $today;
            $label = 'All Time';
            break;
        case 'custom':
            if (empty($startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                $startDate = date('Y-m-01');
            }
            if (empty($endDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $endDate = $today;
            }
            if ($startDate > $endDate) {
                $temp = $startDate;
                $startDate = $endDate;
                $endDate = $temp;
            }
            $label = 'Custom (' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate)) . ')';
            break;
        case 'this_month':
        default:
            $preset = 'this_month';
            $startDate = date('Y-m-01');
            $endDate = $today;
            $label = 'This Month (' . date('F Y') . ')';
            break;
    }

    return [
        'preset'     => $preset,
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'label'      => $label
    ];
}

/**
 * Generate unique sequential expense reference number (e.g. EXP-000001, EXP-000002)
 *
 * @param PDO|null $pdo
 * @return string
 */
function generate_expense_reference(?PDO $pdo = null): string {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->query('
            SELECT reference_number 
            FROM expenses 
            WHERE reference_number LIKE "EXP-%" 
            ORDER BY id DESC 
            LIMIT 1
        ');
        $lastRef = $stmt->fetchColumn();

        $nextNumber = 1;
        if ($lastRef && preg_match('/^EXP-(\d+)$/', $lastRef, $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }

        do {
            $candidateRef = sprintf('EXP-%06d', $nextNumber);
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE reference_number = :ref');
            $checkStmt->execute(['ref' => $candidateRef]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return $candidateRef;
    } catch (PDOException $e) {
        error_log('Failed to generate expense reference: ' . $e->getMessage());
        return 'EXP-' . date('ymd') . rand(100, 999);
    }
}

/**
 * Render Bootstrap badge for expense category status
 *
 * @param string $status
 * @return string
 */
function expense_category_status_badge(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'active'   => '<span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase"><i class="bi bi-check-circle me-1"></i>Active</span>',
        'inactive' => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase"><i class="bi bi-pause-circle me-1"></i>Inactive</span>',
        default    => '<span class="badge bg-light text-dark border text-uppercase">' . e($status) . '</span>'
    };
}

/**
 * Check if the current logged in user has a specific permission
 *
 * @param string $permissionSlug
 * @return bool
 */
function has_permission(string $permissionSlug): bool {
    if (!is_logged_in()) {
        return false;
    }

    $user = current_user();
    if (!$user) {
        return false;
    }

    // Administrators always have all permissions
    if (($user['role_slug'] ?? '') === 'administrator') {
        return true;
    }

    $roleId = (int)($user['role_id'] ?? 0);
    if ($roleId <= 0) {
        return false;
    }

    // Check cached permissions in session
    if (!isset($_SESSION['user_permissions']) || !is_array($_SESSION['user_permissions'])) {
        $_SESSION['user_permissions'] = get_role_permissions($roleId);
    }

    return in_array($permissionSlug, $_SESSION['user_permissions'], true);
}

/**
 * Require specific permission or abort with 403
 *
 * @param string $permissionSlug
 * @param string|null $redirectPath
 * @return void
 */
function require_permission(string $permissionSlug, ?string $redirectPath = null): void {
    if (!has_permission($permissionSlug)) {
        http_response_code(403);
        set_flash_message('error', 'Access Denied: You do not have permission to access this resource.');
        redirect($redirectPath ?? 'modules/dashboard/index.php');
    }
}

/**
 * Retrieve array of permission slugs assigned to a role ID
 *
 * @param int $roleId
 * @param PDO|null $pdo
 * @return array
 */
function get_role_permissions(int $roleId, ?PDO $pdo = null): array {
    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->prepare('
            SELECT p.slug
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
        ');
        $stmt->execute(['role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        error_log('Failed to fetch role permissions: ' . $e->getMessage());
        return [];
    }
}

/**
 * Render Bootstrap badge for user account status
 *
 * @param string $status
 * @return string
 */
function user_status_badge(string $status): string {
    $status = strtolower(trim($status));
    return match ($status) {
        'active'   => '<span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase"><i class="bi bi-check-circle me-1"></i>Active</span>',
        'inactive' => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle text-uppercase"><i class="bi bi-slash-circle me-1"></i>Inactive</span>',
        default    => '<span class="badge bg-light text-dark border text-uppercase">' . e($status) . '</span>'
    };
}

/**
 * Render solid badge for role
 *
 * @param string $roleSlug
 * @param string $roleName
 * @return string
 */
function role_badge(string $roleSlug, string $roleName): string {
    $roleSlug = strtolower(trim($roleSlug));
    return match ($roleSlug) {
        'administrator' => '<span class="badge bg-dark text-white border"><i class="bi bi-shield-fill me-1 text-danger"></i>' . e($roleName) . '</span>',
        'manager'       => '<span class="badge bg-primary text-white border"><i class="bi bi-person-badge-fill me-1"></i>' . e($roleName) . '</span>',
        'staff'         => '<span class="badge bg-info-subtle text-dark border"><i class="bi bi-person-fill me-1"></i>' . e($roleName) . '</span>',
        default         => '<span class="badge bg-secondary text-white border">' . e($roleName) . '</span>'
    };
}

/**
 * In-memory runtime settings cache
 * @var array<string, string|null>|null
 */
global $appSettingsCache;
$appSettingsCache = null;

/**
 * Retrieve all settings from database with runtime in-memory caching
 *
 * @param bool $fresh
 * @param PDO|null $pdo
 * @return array<string, string|null>
 */
function get_all_settings(bool $fresh = false, ?PDO $pdo = null): array {
    global $appSettingsCache;

    if ($appSettingsCache !== null && !$fresh) {
        return $appSettingsCache;
    }

    $appSettingsCache = [];

    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (is_array($rows)) {
            $appSettingsCache = $rows;
        }
    } catch (PDOException $e) {
        error_log('Failed to fetch settings: ' . $e->getMessage());
    }

    return $appSettingsCache;
}

/**
 * Retrieve a specific setting value by key with optional fallback default
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_setting(string $key, mixed $default = null): mixed {
    $settings = get_all_settings();
    return array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== ''
        ? $settings[$key]
        : $default;
}

/**
 * Save or update a setting in the database and refresh in-memory cache
 *
 * @param string $key
 * @param mixed $value
 * @param PDO|null $pdo
 * @return bool
 */
function set_setting(string $key, mixed $value, ?PDO $pdo = null): bool {
    global $appSettingsCache;

    if ($pdo === null) {
        $pdo = getDBConnection();
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
            VALUES (:key, :val, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ');
        $strValue = $value !== null ? (string)$value : null;
        $stmt->execute(['key' => $key, 'val' => $strValue]);

        if ($appSettingsCache !== null) {
            $appSettingsCache[$key] = $strValue;
        }

        return true;
    } catch (PDOException $e) {
        error_log("Failed to save setting '{$key}': " . $e->getMessage());
        return false;
    }
}

/**
 * Get business logo URL with optional fallback
 *
 * @param string|null $logoFilename
 * @return string|null
 */
function business_logo_url(?string $logoFilename = null): ?string {
    if ($logoFilename === null) {
        $logoFilename = get_setting('business_logo');
    }

    if (!empty($logoFilename)) {
        $fullPath = LOGO_PATH . DIRECTORY_SEPARATOR . $logoFilename;
        if (file_exists($fullPath)) {
            return LOGO_URL . '/' . $logoFilename;
        }
    }

    return null;
}

/**
 * Format date using active system date format setting
 *
 * @param string|null $date
 * @param string|null $format
 * @return string
 */
function format_date(?string $date, ?string $format = null): string {
    if (empty($date)) {
        return '—';
    }
    if ($format === null) {
        $format = (string)get_setting('date_format', 'd/m/Y');
    }
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$date;
    }
}




