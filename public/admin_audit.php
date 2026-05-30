<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/audit_helper.php';

require_role(['superadmin', 'admin', 'bos']);

$today = date('Y-m-d');
$user = current_user();
$isSuperadmin = (string) ($user['role'] ?? '') === 'superadmin';
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
$auditSummary = [
    'total' => count($filtered),
    'today' => 0,
    'unique_actions' => 0,
    'unique_users' => 0,
];
$actionSummary = [];
$userSummary = [];
foreach ($filtered as $entry) {
    $entryDate = substr((string) ($entry['time'] ?? ''), 0, 10);
    if ($entryDate === $today) {
        $auditSummary['today']++;
    }

    $actionKey = trim((string) ($entry['message'] ?? ''));
    if ($actionKey !== '') {
        $actionSummary[$actionKey] = true;
    }

    $userKey = trim((string) ($entry['username'] ?? ''));
    if ($userKey !== '') {
        $userSummary[$userKey] = true;
    }
}
$auditSummary['unique_actions'] = count($actionSummary);
$auditSummary['unique_users'] = count($userSummary);
$pageHeading = $isSuperadmin ? 'Audit Sistem' : 'Audit Log';
$pageSubtitle = $isSuperadmin
    ? 'Pantau jejak perubahan lintas toko, akses, maintenance, dan aktivitas inti sistem.'
    : 'Riwayat perubahan kas, shift, dan aktivitas sistem.';
$title = $pageHeading;
require_once __DIR__ . '/../app/views/admin/audit.php';
