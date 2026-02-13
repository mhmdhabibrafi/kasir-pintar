<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/audit_helper.php';

require_role(['admin', 'bos']);

$today = date('Y-m-d');
$normalizeDate = static function (?string $value, string $fallback): string {
    if ($value === null || $value === '') {
        return $fallback;
    }
    $parsed = DateTime::createFromFormat('Y-m-d', $value);
    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
        return $fallback;
    }
    return $value;
};

$filters = [
    'start_date' => $normalizeDate($_GET['start_date'] ?? null, $today),
    'end_date' => $normalizeDate($_GET['end_date'] ?? null, $today),
    'action' => $_GET['action'] ?? '',
    'keyword' => $_GET['keyword'] ?? '',
];

$entries = audit_log_entries(800);
$actions = [];
foreach ($entries as $entry) {
    $message = (string) ($entry['message'] ?? '');
    if ($message !== '') {
        $actions[$message] = true;
    }
}
$actionList = array_keys($actions);
sort($actionList);

$filtered = audit_log_filter($entries, $filters);
$title = 'Audit Log';
require_once __DIR__ . '/../app/views/admin/audit.php';
