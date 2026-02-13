<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/auth/middleware.php';
require_once __DIR__ . '/../app/helpers/format_helper.php';
require_once __DIR__ . '/../app/helpers/shift_helper.php';
require_once __DIR__ . '/../app/helpers/refund_helper.php';
require_once __DIR__ . '/../app/helpers/telegram_helper.php';

require_role(['admin', 'bos', 'karyawan']);

$user = current_user();
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
                if (telegram_can_send('shift')) {
                    $lines = [];
                    $lines[] = 'KASPINDO';
                    $lines[] = 'Shift Dibuka';
                    $lines[] = str_repeat('-', 30);
                    $lines[] = 'Shift ID    : ' . (string) ($shift['shift_id'] ?? '-');
                    $lines[] = 'Kasir       : ' . (string) ($user['name'] ?? '-');
                    $lines[] = 'Kas Awal    : ' . format_rupiah($openingCash);
                    $lines[] = 'Catatan     : ' . ($note !== '' ? $note : '-');
                    $lines[] = 'Waktu       : ' . date('d/m/Y H:i:s');
                    telegram_send_message('<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>', 'HTML');
                }
            }
        } elseif ($action === 'close') {
            $existingShift = shift_get_active($userId);
            if (!$existingShift) {
                $errors[] = 'Shift aktif tidak ditemukan.';
            } else {
                $closingCash = $canManageCash ? max(0.0, (float) ($_POST['closing_cash'] ?? 0)) : 0.0;
                $note = $canManageCash ? trim((string) ($_POST['note'] ?? '')) : '';
                $shift = shift_close($userId, $closingCash, $note);
                if ($shift) {
                    $success = 'Shift berhasil ditutup.';
                    if (telegram_can_send('shift')) {
                        $lines = [];
                        $lines[] = 'KASPINDO';
                        $lines[] = 'Shift Ditutup';
                        $lines[] = str_repeat('-', 30);
                        $lines[] = 'Shift ID    : ' . (string) ($shift['shift_id'] ?? '-');
                        $lines[] = 'Kasir       : ' . (string) ($user['name'] ?? '-');
                        $lines[] = 'Kas Akhir   : ' . format_rupiah($closingCash);
                        $lines[] = 'Catatan     : ' . ($note !== '' ? $note : '-');
                        $lines[] = 'Waktu       : ' . date('d/m/Y H:i:s');
                        telegram_send_message('<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>', 'HTML');
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
                            if (telegram_can_send('shift')) {
                                $lines = [];
                                $lines[] = 'KASPINDO';
                                $lines[] = 'Pergerakan Kas Shift';
                                $lines[] = str_repeat('-', 30);
                                $lines[] = 'Shift ID    : ' . $targetShiftCode;
                                $lines[] = 'Kasir       : ' . (string) ($user['name'] ?? '-');
                                $lines[] = 'Tipe        : ' . ($type === 'in' ? 'CASH IN' : 'CASH OUT');
                                $lines[] = 'Nominal     : ' . format_rupiah($amount);
                                $lines[] = 'Catatan     : ' . ($note !== '' ? $note : '-');
                                $lines[] = 'Waktu       : ' . date('d/m/Y H:i:s');
                                telegram_send_message('<pre>' . telegram_escape(implode("\n", $lines)) . '</pre>', 'HTML');
                            }
                        }
                    }
                }
            }
        }
    }
}

$activeShift = shift_get_active($userId);
$shiftBalance = $activeShift ? shift_cash_balance($activeShift) : 0.0;
$shiftSummary = $activeShift ? shift_summary((string) ($activeShift['shift_id'] ?? '')) : null;

$store = shift_store();
$history = $store['history'] ?? [];
$activeShifts = array_values($store['active'] ?? []);
if (!in_array($role, ['admin', 'bos'], true)) {
    $history = array_values(array_filter($history, static fn ($item) => (int) ($item['user_id'] ?? 0) === $userId));
}

$title = 'Shift & Kas';

require_once __DIR__ . '/../app/views/kasir/shift.php';
