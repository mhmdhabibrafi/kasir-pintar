<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/format_helper.php';
require_once __DIR__ . '/../../helpers/i18n_helper.php';

$title = $title ?? 'MY KASPIN';
$user = current_user();
$hideTopbar = $hideTopbar ?? false;
$currentLang = i18n_current_lang();
$isAuthPage = $hideTopbar && !$user;
$bodyClass = 'bg-slate-50 text-slate-800 antialiased' . ($isAuthPage ? ' kp-auth-page' : '');
$contentClass = $isAuthPage
    ? 'kp-content kp-content-auth'
    : 'kp-content w-full max-w-6xl mx-auto px-4 md:px-6';
?>
<!doctype html>
<html lang="<?php echo e($currentLang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="<?php echo e(base_url('assets/images/logo.png')); ?>">
    <link rel="icon" type="image/jpeg" href="<?php echo e(base_url('assets/images/logo.jpg')); ?>">
    <link rel="manifest" href="<?php echo e(base_url('manifest.webmanifest')); ?>">
    <meta name="theme-color" content="#00bf63">
    <link rel="apple-touch-icon" href="<?php echo e(base_url('assets/images/logo.jpg')); ?>">
    <script>
        tailwind.config = {
            corePlugins: { preflight: false },
            theme: {
                extend: {
                    colors: {
                        brand: '#00bf63',
                        'brand-dark': '#00bf63',
                        ink: '#0f172a',
                    },
                },
            },
        };
    </script>
        <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            color-scheme: light;
            --kp-bg: #f7fafc;
            --kp-surface: #ffffff;
            --kp-primary: #00bf63;
            --kp-primary-dark: #0a9f55;
            --kp-primary-soft: rgba(0, 191, 99, 0.16);
            --kp-primary-weak: rgba(0, 191, 99, 0.08);
            --kp-accent: #00bf63;
            --kp-text: #111827;
            --kp-muted: #6b7280;
            --kp-border: #e2e8f0;
            --kp-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            background: var(--kp-bg);
            color: var(--kp-text);
            line-height: 1.5;
            font-size: 14px;
            font-weight: 400;
            text-rendering: optimizeLegibility;
        }
        a {
            color: inherit;
        }
        .btn,
        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn .material-icons-outlined,
        .btn-sm .material-icons-outlined {
            font-size: 18px;
        }
        .form-control,
        .form-select,
        .input-group-text {
            border-radius: 12px;
            border-color: var(--kp-border);
        }
        .input-group .form-control {
            border-left: 0;
        }
        .input-group-text {
            border-right: 0;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--kp-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
        }
        .input-group-text {
            background: #fff;
        }
        .badge {
            font-weight: 600;
            letter-spacing: 0.2px;
        }
        .badge.text-bg-light {
            background: #f3f4f6 !important;
            color: #374151 !important;
            border: 1px solid var(--kp-border);
        }
        .text-bg-success {
            background-color: var(--kp-primary) !important;
            color: #fff !important;
        }
        .text-bg-secondary {
            background-color: #e2e8f0 !important;
            color: #475569 !important;
        }
        .alert {
            border-radius: 14px;
            border: 1px solid var(--kp-border);
        }
        .alert-success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }
        .alert-danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .alert-warning {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }
        .table-responsive {
            border-radius: 16px;
            border: 1px solid var(--kp-border);
            background: #fff;
        }
        .kp-card-flat .table-responsive {
            border: none;
            background: transparent;
        }
        .table-responsive .kp-table {
            margin-bottom: 0;
        }
        .table thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--kp-muted);
            border-bottom: 0;
        }
        .table tbody td {
            border-color: var(--kp-border);
            vertical-align: middle;
        }
        .table tbody tr:hover td {
            background: #f8fafc;
        }
        h1,
        h2,
        h3,
        h4,
        h5 {
            line-height: 1.3;
            letter-spacing: -0.01em;
        }
        .container {
            max-width: 1200px;
        }
        .kp-app {
            display: flex;
            min-height: 100vh;
            background: var(--kp-bg);
        }
        .kp-sidebar {
            width: 260px;
            background: var(--kp-surface);
            border-right: 1px solid var(--kp-border);
            padding: 24px 18px;
            position: sticky;
            top: 0;
            height: 100vh;
            box-shadow: 10px 0 30px rgba(15, 23, 42, 0.03);
        }
        .kp-sidebar-drawer {
            width: 260px;
        }
        .kp-sidebar-drawer .offcanvas-header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--kp-border);
        }
        .kp-sidebar-drawer .offcanvas-body {
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .kp-sidebar-drawer .kp-logo {
            margin: 0;
            align-items: flex-start;
            text-align: left;
        }
        .kp-sidebar .kp-logo {
            font-weight: 600;
            letter-spacing: 0.3px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            text-align: center;
        }
        .kp-sidebar-footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid var(--kp-border);
            font-size: 12px;
            color: var(--kp-muted);
            text-align: center;
            line-height: 1.4;
        }
        .kp-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .kp-nav a {
            text-decoration: none;
            color: var(--kp-text);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            font-weight: 500;
        }
        .kp-nav a.active {
            background: var(--kp-primary-soft);
            color: var(--kp-primary);
        }
        .kp-nav a:hover {
            background: var(--kp-primary-weak);
        }
        .kp-main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .kp-content {
            width: 100%;
            max-width: 1280px;
            padding: 24px;
            margin: 0 auto;
        }
        .kp-topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--kp-border);
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
        }
        .kp-topbar .container-fluid {
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .kp-topbar-layout {
            gap: 16px;
        }
        .kp-topbar-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .kp-topbar-brand {
            min-width: 0;
        }
        .kp-topbar-user {
            min-width: 0;
        }
        .kp-user-block {
            min-width: 0;
        }
        .kp-brand {
            font-weight: 600;
            letter-spacing: 0.4px;
        }
        .kp-brand-title {
            font-weight: 600;
            letter-spacing: 0.2px;
            white-space: nowrap;
            font-size: 13px;
        }
        .kp-user-meta {
            text-align: left;
        }
        .kp-user-meta .badge {
            font-weight: 600;
        }
        .kp-logo-img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
            object-position: center;
            background: transparent;
            display: block;
        }
        .kp-logo-img.sm {
            width: 28px;
            height: 28px;
            border-radius: 8px;
        }
        .kp-logo-img.lg {
            width: 56px;
            height: 56px;
            border-radius: 14px;
        }
        .kp-card {
            border: 1px solid var(--kp-border);
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
            background: #fff;
            overflow: hidden;
        }
        .kp-card-flat {
            border: 1px solid var(--kp-border);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .kp-transaction-card {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .kp-transaction-header,
        .kp-transaction-total {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .kp-transaction-total {
            align-items: center;
        }
        .kp-transaction-section {
            padding: 10px 12px;
            border: 1px dashed var(--kp-border);
            border-radius: 12px;
            background: #f8fafc;
        }
        .kp-transaction-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .kp-transaction-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .kp-item-name {
            font-weight: 500;
            max-width: 70%;
            word-break: break-word;
        }
        .kp-item-qty {
            margin-left: 6px;
            color: var(--kp-muted);
            font-weight: 500;
        }
        .kp-item-amount {
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
        }
        .kp-transaction-summary {
            display: flex;
            flex-direction: column;
            gap: 6px;
            border-top: 1px solid var(--kp-border);
            padding-top: 10px;
            font-size: 13px;
        }
        .kp-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .kp-muted {
            color: var(--kp-muted);
            letter-spacing: 0.1px;
        }
        .kp-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--kp-primary-weak);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--kp-primary);
        }
        .kp-badge {
            background: var(--kp-primary-soft);
            color: var(--kp-primary-dark);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .kp-btn-primary {
            background: var(--kp-primary);
            border: 1px solid transparent;
            color: #fff;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(0, 191, 99, 0.22);
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }
        .kp-btn-primary:hover {
            background: var(--kp-primary-dark);
            color: #fff;
            box-shadow: 0 10px 18px rgba(0, 191, 99, 0.28);
        }
        .kp-btn-ghost {
            border: 1px solid var(--kp-border);
            border-radius: 12px;
            background: #fff;
            color: var(--kp-text);
            transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
        }
        .kp-btn-ghost:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
            color: var(--kp-text);
        }
        .btn.w-100,
        .btn.btn-block {
            justify-content: center;
        }
        @media (max-width: 575.98px) {
            .kp-transaction-header,
            .kp-transaction-total {
                flex-direction: column;
                align-items: flex-start;
            }
            .kp-transaction-item {
                flex-direction: column;
            }
            .kp-item-amount {
                width: 100%;
                text-align: left;
            }
        }
        .kp-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--kp-border);
            background: #fff;
        }
        .kp-chip {
            border: 1px solid var(--kp-border);
            background: #fff;
            color: var(--kp-muted);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .kp-chip.active {
            background: var(--kp-primary);
            color: #fff;
            border-color: transparent;
        }
        .kp-grid {
            display: grid;
            gap: 16px;
            align-items: stretch;
        }
        .kp-grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .kp-grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .kp-cart-list {
            display: grid;
            gap: 12px;
            grid-template-columns: 1fr;
            min-height: 140px;
        }
        .kp-cart-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid var(--kp-border);
            background: #fff;
        }
        .kp-void-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            background: transparent;
            color: #ef4444;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 0;
            cursor: pointer;
        }
        .kp-hold-list {
            display: grid;
            gap: 10px;
        }
        .kp-hold-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--kp-border);
            background: #fff;
        }
        .kp-product-card.active {
            border-color: rgba(16, 185, 129, 0.6);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.18);
        }
        .kp-cart-sidebar {
            position: sticky;
            top: 96px;
        }
        .kp-toast-container {
            position: fixed;
            bottom: 96px;
            right: 16px;
            display: grid;
            gap: 10px;
            z-index: 1060;
        }
        .kp-toast {
            background: #fff;
            border: 1px solid var(--kp-border);
            border-radius: 12px;
            padding: 10px 14px;
            box-shadow: var(--kp-shadow);
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            font-size: 13px;
            font-weight: 600;
        }
        .kp-toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .kp-toast-success {
            border-color: rgba(16, 185, 129, 0.4);
            color: var(--kp-primary-dark);
        }
        .kp-toast-error {
            border-color: rgba(239, 68, 68, 0.4);
            color: #b91c1c;
        }
        .kp-summary-panel {
            display: grid;
            gap: 12px;
            grid-template-columns: 1fr;
        }
        .kp-summary-total {
            border-color: rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.08);
        }
        .kp-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: #fff;
            border-top: 1px solid var(--kp-border);
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
        }
        .kp-cart-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 72px;
            z-index: 1025;
            padding: 8px 16px;
        }
        .kp-bottom-nav a {
            text-decoration: none;
            color: var(--kp-muted);
            font-size: 12px;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            min-width: 56px;
            padding: 6px 8px;
            border-radius: 12px;
            flex: 1;
        }
        .kp-bottom-nav a.active {
            color: var(--kp-primary);
            background: rgba(16, 185, 129, 0.12);
        }
        .kp-bottom-nav .material-icons-outlined {
            font-size: 22px;
        }
        .material-icons-outlined {
            vertical-align: middle;
            font-size: 20px;
        }
        .border-dashed {
            border-style: dashed !important;
        }
        .kp-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 140px;
        }
        .kp-line-chart {
            width: 100%;
        }
        .kp-line-chart svg {
            width: 100%;
            height: 160px;
        }
        .kp-line-area {
            fill: rgba(16, 185, 129, 0.12);
        }
        .kp-line-stroke {
            fill: none;
            stroke: var(--kp-primary);
            stroke-width: 3;
        }
        .kp-line-point {
            fill: #fff;
            stroke: var(--kp-primary);
            stroke-width: 2;
        }
        .kp-line-labels {
            display: flex;
            justify-content: space-between;
            gap: 6px;
            font-size: 11px;
            color: var(--kp-muted);
            margin-top: 6px;
        }
        .kp-pay-bars {
            display: grid;
            gap: 12px;
        }
        .kp-pay-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 10px;
        }
        .kp-pay-label {
            font-weight: 600;
            font-size: 12px;
            color: var(--kp-text);
        }
        .kp-pay-value {
            font-weight: 600;
            font-size: 12px;
            color: var(--kp-text);
        }
        .kp-pay-bar {
            height: 10px;
            background: rgba(15, 23, 42, 0.06);
            border-radius: 999px;
            overflow: hidden;
        }
        .kp-pay-bar span {
            display: block;
            height: 100%;
            background: var(--kp-primary);
            border-radius: 999px;
        }
        .kp-top-list {
            display: grid;
            gap: 10px;
        }
        .kp-top-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.04);
        }
        .kp-alert-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .kp-alert-badge.active {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .kp-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #cbd5e1;
        }
        .kp-status-dot.online {
            background: #22c55e;
        }
        .kp-status-dot.offline {
            background: #ef4444;
        }
        .kp-bar {
            flex: 1;
            background: rgba(16, 185, 129, 0.16);
            border-radius: 12px 12px 6px 6px;
            position: relative;
        }
        .kp-bar span {
            position: absolute;
            bottom: -22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: var(--kp-muted);
        }
        .kp-offcanvas {
            height: min(94vh, 820px);
            max-height: 100vh;
            border-radius: 24px 24px 0 0;
        }
        .kp-offcanvas .offcanvas-body {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 20px 20px 0;
            gap: 16px;
        }
        .kp-offcanvas .offcanvas-header {
            justify-content: space-between;
        }
        .kp-offcanvas .btn-close {
            width: 16px;
            height: 16px;
        }
        .kp-offcanvas .offcanvas-title {
            font-size: 20px;
        }
        .kp-offcanvas #cartTotal {
            font-size: 28px;
        }
        @media (min-width: 992px) {
            .kp-offcanvas.offcanvas-bottom {
                width: min(980px, 92vw);
                height: min(88vh, 720px);
                max-height: 88vh;
                left: 50%;
                right: auto;
                top: 50%;
                bottom: auto;
                transform: translate(-50%, 100%);
                box-shadow: 0 35px 90px rgba(15, 23, 42, 0.25);
            }
            .kp-offcanvas.offcanvas-bottom.showing,
            .kp-offcanvas.offcanvas-bottom.show {
                transform: translate(-50%, -50%);
            }
            .kp-offcanvas.offcanvas-bottom.hiding {
                transform: translate(-50%, 100%);
            }
            .kp-offcanvas .offcanvas-header {
                padding: 20px 24px 8px;
            }
            .kp-offcanvas .offcanvas-body {
                padding: 20px 24px 0;
            }
            .kp-offcanvas-footer {
                padding: 12px 0 24px;
            }
            .kp-summary-panel {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            #productGrid.kp-grid-3 {
                grid-template-columns: repeat(4, minmax(220px, 1fr));
            }
        }
        .kp-offcanvas-scroll {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 16px;
            min-height: 200px;
        }
        .kp-offcanvas-footer {
            padding: 12px 0 20px;
            border-top: 1px solid var(--kp-border);
            background: var(--kp-surface);
        }
        .kp-auth {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 12px 48px;
        }
        .kp-auth-card {
            background: #fff;
            border: 1px solid var(--kp-border);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.1);
        }
        .kp-auth-logo,
        .kp-logo-wrap {
            width: 96px;
            height: 96px;
            border-radius: 20px;
            border: 0;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            overflow: hidden;
        }
        .kp-auth-logo-img,
        .kp-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            border-radius: inherit;
            display: block;
        }
        .kp-logo-wrap.sm {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            box-shadow: none;
            border: 0;
        }
        .kp-logo-wrap.sm img {
            width: 100%;
            height: 100%;
        }
        .kp-sidebar .kp-logo-wrap {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            box-shadow: none;
        }
        .kp-input {
            height: 48px;
            border-radius: 12px;
        }
        .kp-btn-auth {
            height: 48px;
            border-radius: 14px;
            font-weight: 600;
        }
        .kp-kpi-label {
            font-size: 12px;
            color: var(--kp-muted);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .kp-kpi-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--kp-text);
            letter-spacing: -0.01em;
        }
        .kp-kpi-meta {
            font-size: 12px;
            font-weight: 600;
            color: var(--kp-text);
            opacity: 0.75;
        }
        .kp-dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .kp-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--kp-border);
        }
        .kp-page-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        .kp-page-subtitle {
            margin: 0;
            color: var(--kp-muted);
            font-size: 13px;
        }
        .kp-page-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .kp-page-actions .btn {
            white-space: nowrap;
        }
        .kp-section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.01em;
        }
        .kp-section-subtitle {
            margin: 0;
            color: var(--kp-muted);
            font-size: 13px;
        }
        .kp-stat-grid {
            display: grid;
            gap: 14px;
        }
        .kp-stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            height: 100%;
        }
        .kp-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            background: var(--kp-primary-weak);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--kp-primary);
        }
        .kp-filter-card {
            background: #fff;
            border: 1px solid var(--kp-border);
            border-radius: 16px;
            padding: 16px;
        }
        .kp-filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
        .kp-filter-form > div {
            flex: 1 1 160px;
            min-width: 160px;
        }
        .kp-filter-form > div:last-child {
            flex: 0 0 auto;
            min-width: auto;
        }
        .kp-form-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            align-items: start;
        }
        .kp-form-full {
            grid-column: 1 / -1;
        }
        .kp-form-actions {
            display: flex;
            justify-content: flex-start;
        }
        .kp-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        .kp-table thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--kp-muted);
            border: none;
            padding: 0 12px 6px;
        }
        .kp-table tbody td {
            padding: 12px;
            border-top: 1px solid var(--kp-border);
            border-bottom: 1px solid var(--kp-border);
            background: #fff;
            vertical-align: top;
        }
        .kp-table tbody tr:hover td {
            background: #f8fafc;
        }
        .kp-table tbody tr td:first-child {
            border-left: 1px solid var(--kp-border);
            border-radius: 12px 0 0 12px;
        }
        .kp-table tbody tr td:last-child {
            border-right: 1px solid var(--kp-border);
            border-radius: 0 12px 12px 0;
        }
        .kp-admin-product-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .kp-admin-product-actions {
            margin-top: auto;
            justify-content: center;
            flex-wrap: wrap;
        }
        @media (min-width: 992px) {
            .kp-cart-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 991px) {
            .kp-grid-2,
            .kp-grid-3 {
                grid-template-columns: 1fr;
            }
            .kp-card.p-4,
            .kp-card-flat.p-4 {
                padding: 16px !important;
            }
            .kp-topbar .container-fluid {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
        @media (max-width: 575px) {
            .kp-page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .kp-page-title {
                font-size: 20px;
            }
            .kp-page-actions {
                width: 100%;
            }
            .kp-page-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .kp-dashboard-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .kp-dashboard-actions > * {
                width: 100%;
            }
            .kp-dashboard-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .kp-filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .kp-filter-form > * {
                width: 100%;
            }
            .kp-filter-form .btn {
                width: 100%;
                justify-content: center;
            }
            .kp-form-actions {
                justify-content: center;
            }
            .kp-topbar-layout {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .kp-topbar-left {
                flex-direction: row;
                align-items: center;
                gap: 8px;
                min-width: 0;
                flex: 0 1 auto;
            }
            .kp-topbar-title {
                display: none;
            }
            .kp-topbar-date {
                display: none;
            }
            .kp-topbar-brand {
                width: auto;
                max-width: 40px;
                flex: 0 0 auto;
            }
            .kp-topbar .kp-brand {
                display: none;
            }
            .kp-topbar .kp-logo-img.sm {
                width: 32px;
                height: 32px;
                border-radius: 10px;
            }
            .kp-topbar-user {
                width: auto;
                margin-left: auto;
                justify-content: flex-end;
                gap: 10px;
                flex: 0 0 auto;
            }
            .kp-user-block {
                margin-left: auto;
                flex-direction: row-reverse;
            }
            .kp-logout-btn {
                display: none;
            }
            .kp-avatar {
                width: 34px;
                height: 34px;
            }
            .kp-user-meta {
                max-width: 140px;
                text-align: right;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 2px;
                line-height: 1.1;
                min-width: 0;
            }
            .kp-user-meta .d-flex {
                flex-wrap: nowrap;
                gap: 6px;
                justify-content: flex-end;
                min-width: 0;
            }
            .kp-user-meta .fw-semibold {
                font-size: 12px;
                max-width: 96px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .kp-user-block {
                margin-left: auto;
                flex-direction: row-reverse;
            }
            .kp-user-meta .badge {
                font-size: 10px;
                padding: 2px 8px;
            }
            .kp-content {
                padding: 12px 12px 150px;
            }
            .kp-card,
            .kp-card-flat {
                border-radius: 12px;
            }
            .kp-grid {
                gap: 12px;
                justify-items: stretch;
            }
            .kp-card,
            .kp-card-flat {
                width: 100%;
                max-width: 100%;
            }
            .row {
                margin-left: 0;
                margin-right: 0;
            }
            .row > * {
                padding-left: 0;
                padding-right: 0;
            }
            .kp-topbar .container-fluid {
                padding-left: 12px;
                padding-right: 12px;
            }
            .kp-brand-title {
                font-size: 14px;
            }
            .kp-btn-primary,
            .kp-btn-ghost {
                padding: 10px 14px;
                font-size: 13px;
            }
            .kp-chip {
                font-size: 11px;
                padding: 6px 10px;
            }
            #productGrid.kp-grid-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .kp-product-card {
                padding: 12px !important;
            }
            .kp-product-card .fw-semibold {
                font-size: 13px;
            }
            .kp-product-card .kp-muted.small {
                font-size: 11px;
            }
            .kp-chart {
                height: 150px;
                gap: 10px;
                padding-bottom: 18px;
                overflow-x: auto;
            }
            .kp-line-chart svg {
                height: 150px;
            }
            .kp-line-labels {
                font-size: 10px;
            }
            .kp-pay-row {
                grid-template-columns: 1fr;
                gap: 6px;
            }
            .kp-pay-bar {
                height: 12px;
            }
            .kp-bar span {
                font-size: 10px;
                bottom: -18px;
                white-space: nowrap;
            }
            .kp-bar {
                flex: 0 0 32px;
            }
            .kp-offcanvas {
                height: 100vh;
                max-height: 100vh;
                border-radius: 18px 18px 0 0;
            }
            .kp-offcanvas.offcanvas-bottom {
                width: min(520px, 92vw);
                height: min(90vh, 720px);
                max-height: 90vh;
                left: 50%;
                right: auto;
                top: 50%;
                bottom: auto;
                transform: translate(-50%, 100%);
                box-shadow: 0 32px 80px rgba(15, 23, 42, 0.2);
            }
            .kp-offcanvas.offcanvas-bottom.showing,
            .kp-offcanvas.offcanvas-bottom.show {
                transform: translate(-50%, -50%);
            }
            .kp-offcanvas.offcanvas-bottom.hiding {
                transform: translate(-50%, 100%);
            }
            .kp-offcanvas .offcanvas-header {
                padding: 16px;
            }
            .kp-offcanvas .offcanvas-title {
                font-size: 22px;
            }
            .kp-offcanvas #cartTotal {
                font-size: 32px;
            }
            .kp-offcanvas .offcanvas-body {
                padding: 16px 16px 0;
                gap: 12px;
            }
            .kp-offcanvas-footer {
                padding: 12px 0 16px;
            }
            .kp-cart-bar {
                bottom: 62px;
                padding: 6px 10px;
            }
            .kp-cart-bar {
                display: flex;
                justify-content: center;
            }
            .kp-cart-bar .container {
                width: min(520px, 100%);
                padding-left: 8px;
                padding-right: 8px;
            }
            .kp-cart-bar .kp-card-flat {
                padding: 12px;
            }
            .kp-icon-btn,
            .kp-logout-btn {
                width: 36px;
                height: 36px;
                border-radius: 999px;
            }
            .kp-cart-bar .container {
                display: flex;
                justify-content: center;
            }
            .kp-cart-bar .kp-card-flat {
                width: min(520px, 100%);
            }
            .kp-bottom-nav {
                padding: 6px 12px calc(6px + env(safe-area-inset-bottom));
            }
            .kp-bottom-nav a {
                font-size: 11px;
            }
            .kp-bottom-nav .material-icons-outlined {
                font-size: 20px;
            }
            .kp-toast-container {
                left: 50%;
                right: auto;
                transform: translateX(-50%);
                bottom: 110px;
            }
        }
        @media (max-width: 991px) {
            .kp-sidebar {
                display: none;
            }
            .kp-content {
                padding: 16px 16px 140px;
            }
        }

    </style>
    <style id="kp-modern-overrides">
        :root {
            --kp-bg: #eef4f5;
            --kp-surface: #ffffff;
            --kp-primary: #0f9d69;
            --kp-primary-dark: #0b7b52;
            --kp-primary-soft: rgba(15, 157, 105, 0.16);
            --kp-primary-weak: rgba(15, 157, 105, 0.08);
            --kp-accent: #f59e0b;
            --kp-text: #0f172a;
            --kp-muted: #5b687b;
            --kp-border: #d6e2e6;
            --kp-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at 5% 10%, rgba(15, 157, 105, 0.12), transparent 38%),
                radial-gradient(circle at 95% 92%, rgba(245, 158, 11, 0.14), transparent 42%),
                linear-gradient(180deg, #f7fbfb 0%, var(--kp-bg) 100%);
        }
        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 280px;
            height: 280px;
            border-radius: 999px;
            pointer-events: none;
            z-index: 0;
            filter: blur(6px);
        }
        body::before {
            top: -90px;
            right: -110px;
            background: radial-gradient(circle, rgba(15, 157, 105, 0.24) 0%, rgba(15, 157, 105, 0) 72%);
        }
        body::after {
            bottom: -120px;
            left: -110px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0) 74%);
        }
        .kp-app,
        .kp-main,
        .kp-content {
            position: relative;
            z-index: 1;
        }
        h1,
        h2,
        h3,
        h4,
        h5,
        .kp-page-title,
        .kp-brand-title {
            font-family: 'Sora', sans-serif;
            letter-spacing: -0.02em;
        }
        .kp-sidebar {
            width: 272px;
            border-right: 1px solid rgba(15, 23, 42, 0.06);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.93) 0%, rgba(247, 251, 252, 0.9) 100%);
            backdrop-filter: blur(10px);
            box-shadow: 16px 0 44px rgba(15, 23, 42, 0.05);
            padding: 16px 12px;
            gap: 10px;
            overflow: hidden;
        }
        .kp-sidebar .kp-logo {
            margin-bottom: 8px;
        }
        .kp-sidebar .kp-logo-wrap {
            border-radius: 20px;
            background: linear-gradient(145deg, #ffffff, #f2faf6);
        }
        .kp-nav {
            gap: 4px;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            padding-right: 4px;
            padding-bottom: 6px;
            overscroll-behavior: contain;
        }
        .kp-nav::-webkit-scrollbar {
            width: 6px;
        }
        .kp-nav::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: rgba(100, 116, 139, 0.28);
        }
        .kp-nav a {
            border: 1px solid transparent;
            border-radius: 14px;
            min-height: 42px;
            padding: 8px 11px;
            white-space: nowrap;
            transition: transform 0.18s ease, border-color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
        }
        .kp-nav a .material-icons-outlined {
            width: 20px;
            font-size: 20px;
            text-align: center;
        }
        .kp-nav a:hover {
            transform: translateX(3px);
            border-color: rgba(15, 157, 105, 0.22);
            background: rgba(15, 157, 105, 0.08);
        }
        .kp-nav a.active {
            color: var(--kp-primary-dark);
            border-color: rgba(15, 157, 105, 0.3);
            background: linear-gradient(135deg, rgba(15, 157, 105, 0.2), rgba(255, 255, 255, 0.95));
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.45), 0 8px 18px rgba(15, 157, 105, 0.12);
        }
        .kp-sidebar-footer {
            margin-top: 10px;
            padding-top: 10px;
            flex: 0 0 auto;
        }
        .kp-topbar {
            margin: 10px 10px 0;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(14px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }
        .kp-content {
            padding-top: 18px;
        }
        .kp-card,
        .kp-card-flat,
        .table-responsive {
            border-radius: 20px;
            border-color: var(--kp-border);
            box-shadow: var(--kp-shadow);
        }
        .kp-card-flat,
        .kp-card {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, #ffffff 100%);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .kp-card-flat:hover,
        .kp-card:hover {
            transform: translateY(-2px);
            border-color: rgba(15, 157, 105, 0.26);
            box-shadow: 0 22px 46px rgba(15, 23, 42, 0.11);
        }
        .kp-page-header {
            border-bottom-style: dashed;
            border-bottom-color: rgba(15, 23, 42, 0.12);
        }
        .kp-page-subtitle {
            color: #6b7280;
        }
        .form-control,
        .form-select,
        .input-group-text {
            min-height: 46px;
            border-radius: 14px;
            border-color: #d4e0e5;
            background: rgba(255, 255, 255, 0.92);
        }
        .form-control:focus,
        .form-select:focus {
            border-color: rgba(15, 157, 105, 0.62);
            box-shadow: 0 0 0 4px rgba(15, 157, 105, 0.14);
        }
        .kp-btn-primary {
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #0f9d69 0%, #0b7b52 100%);
            box-shadow: 0 10px 24px rgba(15, 157, 105, 0.3);
            transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
        }
        .kp-btn-primary:hover {
            transform: translateY(-1px);
            filter: saturate(1.05);
            box-shadow: 0 16px 28px rgba(11, 123, 82, 0.34);
        }
        .kp-btn-ghost,
        .kp-icon-btn {
            border-radius: 14px;
            border-color: rgba(15, 23, 42, 0.14);
            background: rgba(255, 255, 255, 0.9);
        }
        .table thead th,
        .kp-table thead th {
            font-size: 10.5px;
            letter-spacing: 0.11em;
            color: #64748b;
        }
        .table tbody td,
        .kp-table tbody td {
            border-color: #e2ebef;
        }
        .table tbody tr:hover td,
        .kp-table tbody tr:hover td {
            background: rgba(15, 157, 105, 0.05);
        }
        .kp-bottom-nav {
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
        }
        .kp-bottom-nav a {
            border-radius: 12px;
            padding: 6px 8px;
            transition: background-color 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }
        .kp-bottom-nav a.active {
            color: var(--kp-primary-dark);
            background: rgba(15, 157, 105, 0.12);
        }
        .kp-bottom-nav a:active {
            transform: translateY(1px);
        }
        .kp-auth {
            background:
                radial-gradient(circle at 10% 12%, rgba(15, 157, 105, 0.14), transparent 36%),
                radial-gradient(circle at 92% 88%, rgba(245, 158, 11, 0.15), transparent 34%);
        }
        .kp-auth-card {
            border-radius: 22px;
            border-color: #dbe7ea;
            box-shadow: 0 30px 65px rgba(15, 23, 42, 0.13);
        }
        .kp-auth-page .kp-app {
            min-height: 100vh;
        }
        .kp-auth-page .kp-main {
            min-height: 100vh;
            width: 100%;
        }
        .kp-auth-page .kp-content-auth {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
        }
        .kp-auth-page .kp-auth {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 12% 15%, rgba(15, 157, 105, 0.16), transparent 38%),
                radial-gradient(circle at 88% 84%, rgba(245, 158, 11, 0.2), transparent 36%),
                linear-gradient(140deg, #edf6f3 0%, #f7fafb 55%, #f8f3e8 100%);
        }
        .kp-auth-page .kp-auth .kp-auth-shell {
            width: 100%;
            max-width: 430px;
            margin: 0 auto;
        }
        .kp-auth-page .kp-auth-card {
            border-radius: 24px;
            border: 1px solid #d4e2e7;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(8px);
            box-shadow: 0 26px 62px rgba(15, 23, 42, 0.14);
        }
        .kp-auth-page .kp-brand-title {
            margin-top: 12px;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }
        .kp-auth-page .kp-auth-subtitle {
            color: var(--kp-muted);
            margin-top: 4px;
            margin-bottom: 0;
            font-size: 13px;
        }
        .kp-auth-page .form-label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }
        .kp-auth-page .kp-input {
            height: 52px;
            border-radius: 14px;
            border-color: #cedbe0;
            font-size: 15px;
            padding-left: 14px;
        }
        .kp-auth-page .kp-btn-auth {
            height: 52px;
            border-radius: 14px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .kp-logo-wrap,
        .kp-auth-logo {
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            overflow: hidden;
        }
        .kp-logo-wrap img,
        .kp-auth-logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            border-radius: inherit;
            display: block;
        }
        .kp-logo-img {
            object-fit: cover;
            object-position: center;
            border-radius: 12px;
            background: transparent !important;
        }
        .kp-chip {
            border-color: #d4e2e7;
            background: rgba(255, 255, 255, 0.86);
        }
        .kp-chip.active {
            background: linear-gradient(135deg, #0f9d69, #0b7b52);
            color: #fff;
        }
        @keyframes kpFadeUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .kp-content > * {
            animation: kpFadeUp 0.46s ease both;
        }
        .kp-content > *:nth-child(2) { animation-delay: 0.04s; }
        .kp-content > *:nth-child(3) { animation-delay: 0.08s; }
        .kp-content > *:nth-child(4) { animation-delay: 0.12s; }
        .kp-content > *:nth-child(5) { animation-delay: 0.16s; }
        .kp-content > *:nth-child(6) { animation-delay: 0.2s; }
        @media (max-width: 991px) {
            body::before,
            body::after {
                width: 210px;
                height: 210px;
                opacity: 0.62;
            }
            .kp-topbar {
                margin: 0;
                border-radius: 0;
                border-left: 0;
                border-right: 0;
            }
            .kp-auth-page .kp-auth {
                padding: 14px;
            }
            .kp-card,
            .kp-card-flat,
            .table-responsive {
                border-radius: 16px;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
    </head>
<body class="<?php echo e($bodyClass); ?>">
<div class="kp-app bg-slate-50 min-h-screen flex">
    <?php if ($user): ?>
        <?php require_once __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    <div class="kp-main flex-1 min-w-0 flex flex-col">
        <?php if (!$hideTopbar): ?>
            <?php require_once __DIR__ . '/navbar/topbar.php'; ?>
        <?php endif; ?>
        <main class="<?php echo e($contentClass); ?>">



