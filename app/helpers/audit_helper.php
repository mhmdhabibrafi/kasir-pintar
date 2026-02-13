<?php

declare(strict_types=1);

function audit_log_entries(int $limit = 500): array
{
    $path = __DIR__ . '/../../storage/logs/audit.log';
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    if (count($lines) > $limit) {
        $lines = array_slice($lines, -$limit);
    }
    $entries = [];
    foreach (array_reverse($lines) as $line) {
        $data = json_decode($line, true);
        if (!is_array($data)) {
            continue;
        }
        $entries[] = $data;
    }
    return $entries;
}

function audit_log_filter(array $entries, array $filters): array
{
    $start = $filters['start_date'] ?? null;
    $end = $filters['end_date'] ?? null;
    $action = trim((string) ($filters['action'] ?? ''));
    $keyword = trim((string) ($filters['keyword'] ?? ''));

    return array_values(array_filter($entries, static function (array $entry) use ($start, $end, $action, $keyword): bool {
        $time = (string) ($entry['time'] ?? '');
        $date = $time !== '' ? substr($time, 0, 10) : '';
        if ($start && $date !== '' && $date < $start) {
            return false;
        }
        if ($end && $date !== '' && $date > $end) {
            return false;
        }
        if ($action !== '' && strtolower((string) ($entry['message'] ?? '')) !== strtolower($action)) {
            return false;
        }
        if ($keyword !== '') {
            $haystack = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($haystack === false || stripos($haystack, $keyword) === false) {
                return false;
            }
        }
        return true;
    }));
}
