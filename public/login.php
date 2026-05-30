<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/login.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';

$title = 'Login KASPINDO';
$hideTopbar = true;
require_once __DIR__ . '/../app/views/layouts/header.php';
?>

<div class="kp-auth">
    <div class="kp-auth-shell row justify-content-center w-100 m-0">
        <div class="col-12">
            <div class="kp-card kp-auth-card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="kp-logo-wrap mx-auto">
                            <img src="<?php echo e(base_url('assets/images/logo.jpg')); ?>" alt="KASPINDO">
                        </div>
                        <div class="kp-brand-title mt-2">KASPINDO</div>
                        <p class="kp-auth-subtitle">Masuk untuk lanjut ke dashboard kasir.</p>
                    </div>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo e($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-control kp-input" value="<?php echo e($oldUsername ?? ''); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control kp-input" required>
                        </div>
                        <button class="btn kp-btn-primary kp-btn-auth w-100" type="submit">
                            <span class="material-icons-outlined">login</span>
                            Masuk
                        </button>
                        <div class="mt-3">
                            <a class="btn kp-btn-ghost kp-auth-register-link w-100" href="<?php echo e(base_url('daftar-mitra')); ?>">
                                <span class="material-icons-outlined">storefront</span>
                                Daftar Mitra KASPINDO
                            </a>
                        </div>
                    </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../app/views/layouts/footer.php';
?>
