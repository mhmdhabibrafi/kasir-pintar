<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/api_helper.php';
require_once __DIR__ . '/../../app/helpers/shift_helper.php';
require_once __DIR__ . '/../../app/helpers/user_permission_helper.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$auth = api_require_auth(['admin', 'bos', 'karyawan']);
$user = $auth['user'];
if (!user_can($user, 'access_shift')) {
    api_error('Akun ini tidak diizinkan membuka halaman shift.', 403, 'shift_access_denied');
}
$canManageCash = in_array((string) ($user['role'] ?? ''), ['admin', 'bos'], true);

if ($method === 'GET') {
    $active = shift_get_active((int) $user['id']);
    $summary = $active ? shift_summary((string) ($active['shift_id'] ?? '')) : null;
    api_ok([
        'active_shift' => $active,
        'summary' => $summary,
        'movement_totals' => $active ? shift_movement_totals($active) : ['cash_in' => 0.0, 'cash_out' => 0.0],
        'cash_balance' => $active ? shift_expected_cash($active, $summary) : 0.0,
    ]);
}

if ($method !== 'POST') {
    api_error('Method tidak diizinkan.', 405, 'method_not_allowed');
}

$body = api_json_body();
$action = strtolower(trim((string) ($body['action'] ?? '')));

if (!in_array($action, ['open', 'close', 'cash_in', 'cash_out'], true)) {
    api_error('Action shift tidak valid. Gunakan open, close, cash_in, atau cash_out.', 422, 'validation_error');
}

if ($action === 'open') {
    $existingShift = shift_get_active((int) $user['id']);
    if ($existingShift) {
        $existingSummary = shift_summary((string) ($existingShift['shift_id'] ?? ''));
        api_error('Masih ada shift aktif untuk user ini.', 409, 'shift_already_open', [
            'active_shift' => $existingShift,
            'summary' => $existingSummary,
            'cash_balance' => shift_expected_cash($existingShift, $existingSummary),
        ]);
    }

    $openingCash = $canManageCash ? max(0.0, (float) ($body['opening_cash'] ?? 0)) : 0.0;
    $note = $canManageCash ? trim((string) ($body['note'] ?? '')) : '';
    $shift = shift_open((int) $user['id'], (string) $user['name'], $openingCash, $note);
    $summary = shift_summary((string) ($shift['shift_id'] ?? ''));

    api_ok([
        'message' => 'Shift berhasil dibuka.',
        'active_shift' => $shift,
        'summary' => $summary,
        'movement_totals' => shift_movement_totals($shift),
        'cash_balance' => shift_expected_cash($shift, $summary),
    ]);
}

if ($action === 'cash_in' || $action === 'cash_out') {
    if (!$canManageCash) {
        api_error('Hanya admin atau bos yang dapat menginput kas.', 403, 'cash_movement_denied');
    }

    $active = shift_get_active((int) $user['id']);
    $targetShiftCode = trim((string) ($body['target_shift_id'] ?? ''));
    if ($targetShiftCode === '' && $active) {
        $targetShiftCode = (string) ($active['shift_id'] ?? '');
    }
    $amountRaw = $body['amount'] ?? null;
    if ($targetShiftCode === '' || $amountRaw === null || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        api_error('Shift target dan nominal wajib valid.', 422, 'validation_error');
    }

    $type = $action === 'cash_in' ? 'in' : 'out';
    if (!shift_add_movement_by_code($targetShiftCode, $type, (float) $amountRaw, trim((string) ($body['note'] ?? '')))) {
        api_error('Shift target tidak ditemukan atau sudah ditutup.', 409, 'shift_not_found');
    }

    $active = shift_get_active((int) $user['id']);
    $summary = $active ? shift_summary((string) ($active['shift_id'] ?? '')) : null;
    api_ok([
        'message' => $type === 'in' ? 'Cash in berhasil dicatat.' : 'Cash out berhasil dicatat.',
        'active_shift' => $active,
        'summary' => $summary,
        'movement_totals' => $active ? shift_movement_totals($active) : ['cash_in' => 0.0, 'cash_out' => 0.0],
        'cash_balance' => $active ? shift_expected_cash($active, $summary) : 0.0,
    ]);
}

$active = shift_get_active((int) $user['id']);
if (!$active) {
    api_error('Tidak ada shift aktif untuk ditutup.', 409, 'no_active_shift');
}

$summary = shift_summary((string) ($active['shift_id'] ?? ''));
$expectedCash = shift_expected_cash($active, $summary);
$closingCashRaw = $body['closing_cash'] ?? null;
if ($canManageCash && $closingCashRaw !== null && $closingCashRaw !== '') {
    if (!is_numeric($closingCashRaw)) {
        api_error('closing_cash wajib angka.', 422, 'validation_error');
    }
    $closingCash = (float) $closingCashRaw;
    if ($closingCash < 0) {
        api_error('closing_cash tidak boleh negatif.', 422, 'validation_error');
    }
} else {
    $closingCash = $expectedCash;
}

$note = $canManageCash ? trim((string) ($body['note'] ?? '')) : 'Ditutup sesuai estimasi kas sistem.';
$closed = shift_close((int) $user['id'], $closingCash, $note);
if (!$closed) {
    api_error('Tidak ada shift aktif untuk ditutup.', 409, 'no_active_shift');
}

api_ok([
    'message' => 'Shift berhasil ditutup.',
    'shift' => $closed,
    'expected_cash' => $expectedCash,
    'diff_cash' => $closingCash - $expectedCash,
]);
