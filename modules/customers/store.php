<?php
/**
 * Customer Store Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/customers/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    $_SESSION['old_customer_input'] = $_POST;
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/customers/create.php');
}

// 2. Extract & Sanitize Inputs
$name       = sanitize_input($_POST['name'] ?? '');
$phone      = sanitize_input($_POST['phone'] ?? '');
$email      = sanitize_input($_POST['email'] ?? '');
$address    = sanitize_input($_POST['address'] ?? '');
$city       = sanitize_input($_POST['city'] ?? '');
$postalCode = sanitize_input($_POST['postal_code'] ?? '');
$notes      = sanitize_input($_POST['notes'] ?? '');
$status     = sanitize_input($_POST['status'] ?? 'active');

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

// 3. Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Customer Name is required.';
} elseif (mb_strlen($name) > 100) {
    $errors[] = 'Customer Name cannot exceed 100 characters.';
}

if (empty($phone)) {
    $errors[] = 'Phone Number is required.';
} elseif (mb_strlen($phone) > 30) {
    $errors[] = 'Phone Number cannot exceed 30 characters.';
}

if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    } elseif (mb_strlen($email) > 191) {
        $errors[] = 'Email address is too long.';
    }
}

if (!empty($postalCode) && mb_strlen($postalCode) > 20) {
    $errors[] = 'Postal code cannot exceed 20 characters.';
}

if (!empty($city) && mb_strlen($city) > 100) {
    $errors[] = 'City name cannot exceed 100 characters.';
}

if (!empty($errors)) {
    $_SESSION['old_customer_input'] = $_POST;
    set_flash_message('error', implode('<br>', $errors));
    redirect('modules/customers/create.php');
}

try {
    $pdo = getDBConnection();

    // 4. Duplicate Check: Ensure phone is not already in use by another active/non-deleted customer
    $checkStmt = $pdo->prepare('SELECT id, customer_code, name FROM customers WHERE phone = :phone AND deleted_at IS NULL LIMIT 1');
    $checkStmt->execute(['phone' => $phone]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $_SESSION['old_customer_input'] = $_POST;
        set_flash_message('error', sprintf('A customer with phone number "%s" already exists (%s - %s).', e($phone), e($existing['customer_code']), e($existing['name'])));
        redirect('modules/customers/create.php');
    }

    // 5. Generate Unique Customer Code
    $customerCode = generate_customer_code($pdo);
    $createdBy = (int)($_SESSION['user_id'] ?? 0) ?: null;

    // 6. Insert Record
    $insertStmt = $pdo->prepare('
        INSERT INTO customers (
            customer_code, name, phone, email, address, city, postal_code, notes, status, created_by, created_at, updated_at
        ) VALUES (
            :customer_code, :name, :phone, :email, :address, :city, :postal_code, :notes, :status, :created_by, NOW(), NOW()
        )
    ');

    $insertStmt->execute([
        'customer_code' => $customerCode,
        'name'          => $name,
        'phone'         => $phone,
        'email'         => !empty($email) ? $email : null,
        'address'       => !empty($address) ? $address : null,
        'city'          => !empty($city) ? $city : null,
        'postal_code'   => !empty($postalCode) ? $postalCode : null,
        'notes'         => !empty($notes) ? $notes : null,
        'status'        => $status,
        'created_by'    => $createdBy
    ]);

    $customerId = (int)$pdo->lastInsertId();

    // 7. Log Activity
    log_activity(
        $createdBy,
        'customer_created',
        sprintf('Created customer %s - %s (Phone: %s)', $customerCode, $name, $phone)
    );

    set_flash_message('success', sprintf('Customer <strong>%s</strong> (%s) was created successfully.', e($name), e($customerCode)));
    redirect('modules/customers/show.php?id=' . $customerId);

} catch (PDOException $e) {
    error_log('Customer Store Error: ' . $e->getMessage());
    $_SESSION['old_customer_input'] = $_POST;
    set_flash_message('error', 'Failed to save customer due to a database error. Please try again.');
    redirect('modules/customers/create.php');
}
