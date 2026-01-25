<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/shift_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';

require_role(['admin', 'bos', 'karyawan']);

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$role = $user['role'] ?? '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'open') {
            $openingCash = max(0.0, (float) ($_POST['opening_cash'] ?? 0));
            $note = trim((string) ($_POST['note'] ?? ''));
            $shift = shift_open($userId, (string) ($user['name'] ?? 'Kasir'), $openingCash, $note);
            $success = 'Shift berhasil dibuka. ID: ' . ($shift['shift_id'] ?? '-');
        } elseif ($action === 'close') {
            $closingCash = max(0.0, (float) ($_POST['closing_cash'] ?? 0));
            $note = trim((string) ($_POST['note'] ?? ''));
            $shift = shift_close($userId, $closingCash, $note);
            if ($shift) {
                $success = 'Shift berhasil ditutup.';
            } else {
                $errors[] = 'Shift aktif tidak ditemukan.';
            }
        } elseif ($action === 'cash_in' || $action === 'cash_out') {
            $amount = max(0.0, (float) ($_POST['amount'] ?? 0));
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($amount <= 0) {
                $errors[] = 'Nominal wajib diisi.';
            } else {
                $type = $action === 'cash_in' ? 'in' : 'out';
                shift_add_movement($userId, $type, $amount, $note);
                $success = $type === 'in' ? 'Cash in berhasil dicatat.' : 'Cash out berhasil dicatat.';
            }
        }
    }
}

$activeShift = shift_get_active($userId);
$shiftBalance = $activeShift ? shift_cash_balance($activeShift) : 0.0;
$shiftSummary = $activeShift ? shift_summary((string) ($activeShift['shift_id'] ?? '')) : null;

$store = shift_store();
$history = $store['history'] ?? [];
if (!in_array($role, ['admin', 'bos'], true)) {
    $history = array_values(array_filter($history, static fn ($item) => (int) ($item['user_id'] ?? 0) === $userId));
}

$title = 'Shift & Kas';

require_once __DIR__ . '/../app/views/kasir/shift.php';
