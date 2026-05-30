<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function render_system_state_page(string $headline, string $message, array $options = []): void
{
    $statusCode = (int) ($options['status_code'] ?? 503);
    $title = trim((string) ($options['title'] ?? 'KASPINDO'));
    $icon = trim((string) ($options['icon'] ?? 'construction'));
    $actions = isset($options['actions']) && is_array($options['actions']) ? $options['actions'] : [];

    http_response_code($statusCode);
    send_security_headers();

    $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html>';
    echo '<html lang="id">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $escape($title) . '</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">';
    echo '<style>
        :root {
            color-scheme: light;
            --kp-bg: #f4f8f6;
            --kp-surface: rgba(255, 255, 255, 0.92);
            --kp-text: #0f172a;
            --kp-muted: #64748b;
            --kp-primary: #00bf63;
            --kp-primary-dark: #00a454;
            --kp-border: rgba(15, 23, 42, 0.08);
            --kp-shadow: 0 28px 60px rgba(15, 23, 42, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif;
            background: var(--kp-bg);
            color: var(--kp-text);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .kp-state-shell {
            width: min(100%, 720px);
            background: var(--kp-surface);
            border: 1px solid var(--kp-border);
            border-radius: 28px;
            box-shadow: var(--kp-shadow);
            padding: 34px 32px;
            text-align: center;
            backdrop-filter: blur(12px);
        }
        .kp-state-icon {
            width: 78px;
            height: 78px;
            border-radius: 24px;
            margin: 0 auto 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 191, 99, 0.12);
            color: var(--kp-primary-dark);
        }
        .kp-state-icon .material-icons-outlined {
            font-size: 38px;
        }
        .kp-state-title {
            margin: 0 0 10px;
            font-size: clamp(26px, 4vw, 34px);
            line-height: 1.15;
            font-weight: 700;
        }
        .kp-state-copy {
            margin: 0 auto;
            max-width: 560px;
            color: var(--kp-muted);
            font-size: 15px;
            line-height: 1.7;
            white-space: pre-line;
        }
        .kp-state-actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }
        .kp-state-btn {
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid var(--kp-border);
            text-decoration: none;
            color: var(--kp-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 600;
            background: #fff;
        }
        .kp-state-btn.is-primary {
            border-color: transparent;
            color: #fff;
            background: var(--kp-primary);
        }
        @media (max-width: 575.98px) {
            .kp-state-shell {
                padding: 28px 20px;
                border-radius: 24px;
            }
            .kp-state-actions {
                flex-direction: column;
            }
            .kp-state-btn {
                width: 100%;
            }
        }
    </style>';
    echo '</head>';
    echo '<body>';
    echo '<section class="kp-state-shell">';
    echo '<div class="kp-state-icon"><span class="material-icons-outlined">' . $escape($icon) . '</span></div>';
    echo '<h1 class="kp-state-title">' . $escape($headline) . '</h1>';
    echo '<p class="kp-state-copy">' . $escape($message) . '</p>';

    if (!empty($actions)) {
        echo '<div class="kp-state-actions">';
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $label = trim((string) ($action['label'] ?? 'Buka'));
            $url = trim((string) ($action['url'] ?? '#'));
            $primary = !empty($action['primary']);
            $iconName = trim((string) ($action['icon'] ?? 'arrow_forward'));
            echo '<a class="kp-state-btn' . ($primary ? ' is-primary' : '') . '" href="' . $escape($url) . '">';
            echo '<span class="material-icons-outlined">' . $escape($iconName) . '</span>';
            echo $escape($label);
            echo '</a>';
        }
        echo '</div>';
    }

    echo '</section>';
    echo '</body>';
    echo '</html>';
    exit;
}
