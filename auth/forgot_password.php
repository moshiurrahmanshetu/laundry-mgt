<?php
/**
 * Forgot Password Page
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../includes/guest_check.php';
$pageTitle = 'Password Recovery';

$resetToken = null;
$messageSent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token()) {
        $email = sanitize_input($_POST['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();

                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                    $insertStmt = $pdo->prepare('
                        INSERT INTO password_resets (email, token, expires_at, created_at)
                        VALUES (:email, :token, :expires_at, NOW())
                    ');
                    $insertStmt->execute([
                        'email'      => $email,
                        'token'      => $token,
                        'expires_at' => $expiresAt
                    ]);

                    log_activity($user['id'], 'password_reset_request', 'Password reset requested');
                    $resetToken = $token;
                }

                $messageSent = true;
            } catch (PDOException $e) {
                error_log('Forgot password error: ' . $e->getMessage());
                set_flash_message('error', 'A system error occurred. Please try again.');
            }
        } else {
            set_flash_message('error', 'Please provide a valid email address.');
        }
    } else {
        set_flash_message('error', 'Invalid security token.');
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
                <i class="bi bi-key"></i>
            </div>
            <h2>Password Recovery</h2>
            <p>Reset access to your Laundry Management account</p>
        </div>

        <?= render_flash_messages() ?>

        <?php if ($messageSent): ?>
            <div class="alert alert-info d-flex align-items-center mb-4">
                <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0"></i>
                <div>
                    If an account with that email exists, password recovery instructions have been initiated.
                </div>
            </div>

            <?php if (APP_ENV === 'development' && !empty($resetToken)): ?>
                <div class="mb-4 p-3 bg-light border rounded">
                    <small class="fw-bold text-secondary d-block mb-1"><i class="bi bi-code-slash me-1"></i> Development Reset Link:</small>
                    <a href="<?= base_url('auth/reset_password.php?token=' . urlencode($resetToken) . '&email=' . urlencode($email)) ?>" class="small text-break">
                        Click here to reset your password
                    </a>
                </div>
            <?php endif; ?>

            <div class="d-grid">
                <a href="<?= base_url('auth/login.php') ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Sign In
                </a>
            </div>
        <?php else: ?>
            <form action="<?= base_url('auth/forgot_password.php') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Registered Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autofocus>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary py-2">
                        <i class="bi bi-send me-1"></i> Request Password Reset
                    </button>
                </div>

                <div class="text-center">
                    <a href="<?= base_url('auth/login.php') ?>" class="small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Sign In
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
