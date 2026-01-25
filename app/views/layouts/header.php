<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/format_helper.php';

$title = $title ?? 'KASIR PINTAR';
$user = current_user();
$hideTopbar = $hideTopbar ?? false;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            --kp-bg: #f8fafc;
            --kp-surface: #ffffff;
            --kp-primary: #00bf63;
            --kp-primary-dark: #00bf63;
            --kp-accent: #00bf63;
            --kp-text: #1f2937;
            --kp-muted: #6b7280;
            --kp-border: #e5e7eb;
            --kp-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
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
        }
        h1,
        h2,
        h3,
        h4,
        h5 {
            line-height: 1.3;
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
            background: rgba(16, 185, 129, 0.12);
            color: var(--kp-primary);
        }
        .kp-nav a:hover {
            background: rgba(16, 185, 129, 0.08);
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
            object-fit: contain;
            object-position: center;
            background: #fff;
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
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }
        .kp-card-flat {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
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
            background: rgba(16, 185, 129, 0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--kp-primary);
        }
        .kp-badge {
            background: rgba(16, 185, 129, 0.14);
            color: var(--kp-primary-dark);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .kp-btn-primary {
            background: var(--kp-primary);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
            transition: background 0.15s ease, box-shadow 0.15s ease;
        }
        .kp-btn-primary:hover {
            background: var(--kp-primary-dark);
            color: #fff;
            box-shadow: 0 6px 12px rgba(15, 118, 110, 0.25);
        }
        .kp-btn-ghost {
            border: 1px solid var(--kp-border);
            border-radius: 12px;
            background: #fff;
            color: var(--kp-text);
            transition: border-color 0.15s ease, background 0.15s ease;
        }
        .kp-btn-ghost:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
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
        }
        .kp-chip.active {
            background: var(--kp-primary);
            color: #fff;
            border-color: transparent;
        }
        .kp-grid {
            display: grid;
            gap: 16px;
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
            border: 1px solid var(--kp-border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }
        .kp-auth-logo-img,
        .kp-logo-wrap img {
            width: 78%;
            height: 78%;
            object-fit: contain;
            object-position: center;
        }
        .kp-logo-wrap.sm {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            box-shadow: none;
            border: 1px solid var(--kp-border);
        }
        .kp-logo-wrap.sm img {
            width: 78%;
            height: 78%;
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
        }
        .kp-kpi-value {
            font-size: 24px;
            font-weight: 600;
            color: var(--kp-text);
        }
        .kp-kpi-meta {
            font-size: 12px;
            font-weight: 600;
            color: var(--kp-text);
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
        }
        .kp-page-title {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
        }
        .kp-page-subtitle {
            margin: 0;
            color: var(--kp-muted);
        }
        .kp-page-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .kp-section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
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
        }
        .kp-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.12);
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
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<div class="kp-app bg-slate-50 min-h-screen flex">
    <?php if ($user): ?>
        <?php require_once __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>
    <div class="kp-main flex-1 min-w-0 flex flex-col">
        <?php if (!$hideTopbar): ?>
            <?php require_once __DIR__ . '/navbar/topbar.php'; ?>
        <?php endif; ?>
        <main class="kp-content w-full max-w-6xl mx-auto px-4 md:px-6">

