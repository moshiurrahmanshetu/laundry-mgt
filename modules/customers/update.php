<?php
/**
 * Customer Update Action Handler
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/customers/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash_message('error', 'Invalid customer ID provided.');
    redirect('modules/customers/index.php');
}

// 1. Verify CSRF Token
if (!verify_csrf_token()) {
    $_SESSION['old_customer_input'] = $_POST;
    set_flash_message('error', 'Security token expired or invalid. Please try again.');
    redirect('modules/customers/edit.php?id=' . $id);
}

// 2. Fetch existing customer
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, customer_code FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash_message('error', 'The customer you are trying to edit does not exist or has been deleted.');
    redirect('modules/customers/index.php');
}

// 3. Extract & Sanitize Inputs
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

// 4. Validation
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
    redirect('modules/customers/edit.php?id=' . $id);
}

try {
    // 5. Duplicate Check: Ensure phone number is not used by another customer
    $checkStmt = $pdo->prepare('SELECT id, customer_code, name FROM customers WHERE phone = :phone AND id != :id AND deleted_at IS NULL LIMIT 1');
    $checkStmt->execute([
        'phone' => $phone,
        'id'    => $id
    ]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $_SESSION['old_customer_input'] = $_POST;
        set_flash_message('error', sprintf('Phone number "%s" is already assigned to customer %s (%s).', e($phone), e($existing['name']), e($existing['customer_code'])));
        redirect('modules/customers/edit.php?id=' . $id);
    }

    // 6. Update Customer Record
    $updateStmt = $pdo->prepare('
        UPDATE customers SET
            name = :name,
            phone = :phone,
            email = :email,
            address = :address,
            city = :city,
            postal_code = :postal_code,
            notes = :notes,
            status = :status,
            updated_at = NOW()
        WHERE id = :id
    ');

    $updateStmt->execute([
        'name'        => $name,
        'phone'       => $phone,
        'email'       => !empty($email) ? $email : null,
        'address'     => !empty($address) ? $address : null,
        'city'        => !empty($city) ? $city : null,
        'postal_code' => !empty($postalCode) ? $postalCode : null,
        'notes'       => !empty($notes) ? $notes : null,
        'status'      => $status,
        'id'          => $id
    ]);

    // 7. Log Activity
    log_activity(
        (int)($_SESSION['user_id'] ?? 0) ?: null,
        'customer_updated',
        sprintf('Updated customer %s - %s', $customer['customer_code'], $name)
    );

    set_flash_message('success', sprintf('Customer <strong>%s</strong> (%s) details updated successfully.', e($name), e($customer['customer_code'])));
    redirect('modules/customers/show.php?id=' . $id);

} catch (PDOException $e) {
    error_log('Customer Update Error: ' . $e->getMessage());
    $_SESSION['old_customer_input'] = $_POST;
    set_flash_message('error', 'Failed to update customer due to a database error. Please try again.');
    redirect('modules/customers/edit.php?id=' . $id);
}
