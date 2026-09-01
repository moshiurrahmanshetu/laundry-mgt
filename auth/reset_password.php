<?php
/**
 * Password Reset Page
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../includes/guest_check.php';
$pageTitle = 'Set New Password';

$token = sanitize_input($_GET['token'] ?? ($_POST['token'] ?? ''));
$email = sanitize_input($_GET['email'] ?? ($_POST['email'] ?? ''));
$validToken = false;
$resetRecord = null;

if (!empty($token) && !empty($email)) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('
            SELECT id, email, token, expires_at 
            FROM password_resets 
            WHERE email = :email AND token = :token AND used_at IS NULL AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ');
        $stmt->execute(['email' => $email, 'token' => $token]);
        $resetRecord = $stmt->fetch();
        if ($resetRecord) {
            $validToken = true;
        }
    } catch (PDOException $e) {
        error_log('Reset token check error: ' . $e->getMessage());
    }
}

// Handle Password Reset Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!verify_csrf_token()) {
        set_flash_message('error', 'Security token expired. Please try again.');
    } else {
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (empty($newPassword) || empty($confirmPassword)) {
            set_flash_message('error', 'Please fill in all password fields.');
        } elseif (mb_strlen($newPassword) < 8) {
            set_flash_message('error', 'New password must be at least 8 characters long.');
        } elseif ($newPassword !== $confirmPassword) {
            set_flash_message('error', 'Passwords do not match.');
        } else {
            try {
                $pdo = getDBConnection();
                
                // Fetch user
                $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $userStmt->execute(['email' => $email]);
                $user = $userStmt->fetch();

                if ($user) {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

                    // Update user password
                    $updateStmt = $pdo->prepare('UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id');
                    $updateStmt->execute(['password' => $newHash, 'id' => $user['id']]);

                    // Mark reset token as used
                    $markStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
                    $markStmt->execute(['id' => $resetRecord['id']]);

                    log_activity($user['id'], 'password_reset_completed', 'Password reset successfully completed');

                    set_flash_message('success', 'Your password has been reset successfully. Please sign in with your new password.');
                    redirect('auth/login.php');
                }
            } catch (PDOException $e) {
                error_log('Password reset execution error: ' . $e->getMessage());
                set_flash_message('error', 'A system error occurred. Please try again.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset_url('css/style.css') ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="bg-light">

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-logo">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h2>Set New Password</h2>
            <p>Enter and confirm your new account password</p>
        </div>

        <?= render_flash_messages() ?>

        <?php if (!$validToken): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div>
                    This password reset link is invalid, expired, or has already been used.
                </div>
            </div>
            <div class="d-grid">
                <a href="<?= base_url('auth/forgot_password.php') ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i> Request New Reset Link
                </a>
            </div>
        <?php else: ?>
            <form action="<?= base_url('auth/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email)) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="email" value="<?= e($email) ?>">

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="At least 8 characters" minlength="8" required autofocus>
                        <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="new_password" title="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" minlength="8" required>
                        <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="confirm_password" title="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary py-2">
                        <i class="bi bi-check2-circle me-1"></i> Update Password &amp; Sign In
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('js/app.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
