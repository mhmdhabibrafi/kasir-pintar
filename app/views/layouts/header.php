<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/format_helper.php';
require_once __DIR__ . '/../../helpers/i18n_helper.php';

send_security_headers();

$title = $title ?? 'KASPINDO';
$user = current_user();
$hideTopbar = $hideTopbar ?? false;
$forceAuthLayout = $forceAuthLayout ?? false;
$currentLang = i18n_current_lang();
$isAuthPage = $hideTopbar && (!$user || $forceAuthLayout);
$styleAssetPath = dirname(__DIR__, 3) . '/public/css/style.css';
$styleVersion = is_file($styleAssetPath) ? (string) filemtime($styleAssetPath) : '1';

$bodyClassTokens = [];
$existingBodyClass = trim((string) ($bodyClass ?? ''));
if ($existingBodyClass !== '') {
    $bodyClassTokens[] = $existingBodyClass;
}
$bodyClassTokens[] = $isAuthPage ? 'kp-auth-page' : 'kp-app-page';
if ($user) {
    $roleSlug = strtolower(trim((string) ($user['role'] ?? 'user')));
    if ($roleSlug !== '') {
        $bodyClassTokens[] = 'kp-role-' . preg_replace('/[^a-z0-9_-]+/i', '-', $roleSlug);
    }
}
$bodyClass = implode(' ', array_filter($bodyClassTokens, static fn ($value): bool => $value !== ''));
?>
<!doctype html>
<html lang="<?php echo e($currentLang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" type="image/jpeg" href="<?php echo e(base_url('assets/images/logo.jpg')); ?>">
    <link rel="manifest" href="<?php echo e(base_url('manifest.webmanifest')); ?>">
    <meta name="theme-color" content="#01696f">
    <link rel="apple-touch-icon" href="<?php echo e(base_url('assets/images/logo.jpg')); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="<?php echo e(base_url('css/style.css')); ?>?v=<?php echo e($styleVersion); ?>">
</head>
<body class="<?php echo e($bodyClass); ?>">

<?php if (!$isAuthPage): ?>
<div class="app-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/navbar/topbar.php'; ?>
        <main class="content-wrapper">
<?php else: ?>
<div class="auth-container">
    <main class="auth-main">
<?php endif; ?>
