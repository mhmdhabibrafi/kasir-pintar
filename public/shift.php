<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/shift_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/user_permission_helper.php';

require_role(['admin', 'bos', 'karyawan']);

$user = current_user();
user_permission_guard($user, 'access_shift', 'Admin menonaktifkan akses halaman shift untuk akun ini.');
$userId = (int) ($user['id'] ?? 0);
$role = $user['role'] ?? '';
$canManageCash = in_array($role, ['admin', 'bos'], true);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'open') {
            $existingShift = shift_get_active($userId);
            if ($existingShift) {
                $errors[] = 'Shift masih aktif. Tutup shift terlebih dahulu.';
            } else {
                $openingCash = $canManageCash ? max(0.0, (float) ($_POST['opening_cash'] ?? 0)) : 0.0;
                $note = $canManageCash ? trim((string) ($_POST['note'] ?? '')) : '';
                $shift = shift_open($userId, (string) ($user['name'] ?? 'Kasir'), $openingCash, $note);
                $success = 'Shift berhasil dibuka. ID: ' . ($shift['shift_id'] ?? '-');
            }
        } elseif ($action === 'close') {
            $existingShift = shift_get_active($userId);
            if (!$existingShift) {
                $errors[] = 'Shift aktif tidak ditemukan.';
            } else {
                $closeSummary = shift_summary((string) ($existingShift['shift_id'] ?? ''));
                $expectedCash = shift_expected_cash($existingShift, $closeSummary);
                $closingCashRaw = trim((string) ($_POST['closing_cash'] ?? ''));
                $closingCash = $canManageCash && $closingCashRaw !== ''
                    ? max(0.0, (float) $closingCashRaw)
                    : $expectedCash;
                $note = $canManageCash ? trim((string) ($_POST['note'] ?? '')) : 'Ditutup sesuai estimasi kas sistem.';
                $shift = shift_close($userId, $closingCash, $note);
                if ($shift) {
                    $success = 'Shift berhasil ditutup.';
                    $diffCash = $closingCash - $expectedCash;
                    if (abs($diffCash) > 0.0001) {
                        $success .= ' Selisih kas: ' . format_rupiah($diffCash) . '.';
                    }
                } else {
                    $errors[] = 'Shift aktif tidak ditemukan.';
                }
            }
        } elseif ($action === 'cash_in' || $action === 'cash_out') {
            if (!$canManageCash) {
                $errors[] = 'Akses terbatas. Hanya admin atau bos yang dapat menginput kas.';
            } else {
                $existingShift = shift_get_active($userId);
                $targetShiftCode = trim((string) ($_POST['target_shift_id'] ?? ''));
                if ($targetShiftCode === '' && $existingShift) {
                    $targetShiftCode = (string) ($existingShift['shift_id'] ?? '');
                }
                if ($targetShiftCode === '') {
                    $errors[] = 'Shift target tidak ditemukan.';
                } else {
                    $amount = max(0.0, (float) ($_POST['amount'] ?? 0));
                    $note = trim((string) ($_POST['note'] ?? ''));
                    if ($amount <= 0) {
                        $errors[] = 'Nominal wajib diisi.';
                    } else {
                        $type = $action === 'cash_in' ? 'in' : 'out';
                        if (!shift_add_movement_by_code($targetShiftCode, $type, $amount, $note)) {
                            $errors[] = 'Shift target tidak ditemukan atau sudah ditutup.';
                        } else {
                            $success = $type === 'in' ? 'Cash in berhasil dicatat.' : 'Cash out berhasil dicatat.';
                        }
                    }
                }
            }
        }
    }
}

$activeShift = shift_get_active($userId);
$shiftSummary = $activeShift ? shift_summary((string) ($activeShift['shift_id'] ?? '')) : null;
$shiftBalance = $activeShift ? shift_expected_cash($activeShift, $shiftSummary) : 0.0;
$shiftMovementTotals = $activeShift ? shift_movement_totals($activeShift) : ['cash_in' => 0.0, 'cash_out' => 0.0];
$openingCashSuggestion = $canManageCash ? shift_suggest_opening_cash($userId) : 0.0;
$activeShifts = $canManageCash ? shift_active_list(false) : [];
$history = shift_recent_history($canManageCash ? 80 : 40, $canManageCash ? null : $userId);

$title = 'Shift & Kas';

require_once __DIR__ . '/../app/views/kasir/shift.php';
