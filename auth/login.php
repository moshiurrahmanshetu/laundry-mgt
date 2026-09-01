<?php
/**
 * User Sign In Page
 * Project: Laundry Management System (laundry-mgt)
 */

require_once __DIR__ . '/../includes/guest_check.php';
$pageTitle = 'Sign In';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/style.css') ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="bg-light">

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Brand Header -->
        <div class="auth-brand">
            <div class="brand-logo">
                <i class="bi bi-droplet-half"></i>
            </div>
            <h2><?= e(APP_NAME) ?></h2>
            <p>Enter your credentials to access your account</p>
        </div>

        <!-- Flash Messages -->
        <?= render_flash_messages() ?>

        <!-- Login Form -->
        <form action="<?= base_url('auth/authenticate.php') ?>" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-envelope"></i></span>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           placeholder="name@example.com" 
                           value="<?= e($_SESSION['old_email'] ?? '') ?>"
                           required 
                           autofocus>
                </div>
            </div>
            <?php unset($_SESSION['old_email']); ?>

            <!-- Password -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label mb-0">Password</label>
                    <a href="<?= base_url('auth/forgot_password.php') ?>" class="small text-decoration-none">Forgot Password?</a>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="••••••••" 
                           required>
                    <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="password" title="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                <label class="form-check-label text-secondary small" for="remember">Keep me signed in on this device</label>
            </div>

            <!-- Submit Button -->
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-lg fs-6 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </div>
        </form>

        <!-- Development Default Credentials Hint -->
        <?php if (APP_ENV === 'development'): ?>
        <div class="mt-4 p-3 bg-light border rounded text-start" style="font-size: 0.8rem;">
            <div class="fw-bold text-secondary mb-1"><i class="bi bi-info-circle me-1"></i> Default Administrator Credentials:</div>
            <div class="text-muted">Email: <code class="text-dark">admin@laundrymgt.com</code></div>
            <div class="text-muted">Password: <code class="text-dark">Password123!</code></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= asset_url('js/app.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

</body>
</html>
