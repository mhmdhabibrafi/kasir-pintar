<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/format_helper.php';
require_once __DIR__ . '/store_info_helper.php';

function pdf_text(string $text): string
{
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
        if ($converted !== false) {
            return $converted;
        }
    }
    return utf8_decode($text);
}

function pdf_truncate(string $text, int $max): string
{
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 3)) . '...';
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return rtrim(substr($text, 0, $max - 3)) . '...';
}

function pdf_format_date(?string $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function pdf_format_datetime(?string $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
}

function pdf_money(float $value): string
{
    return format_rupiah($value);
}

function pdf_draw_kpi_cards(BasePDF $pdf, array $cards, float $y, float $height = 16): void
{
    if (empty($cards)) {
        return;
    }

    $left = $pdf->contentLeft();
    $right = $pdf->contentRightMargin();
    $usableWidth = $pdf->GetPageWidth() - $left - $right;
    $gap = 4.0;
    $count = count($cards);
    $width = ($usableWidth - ($gap * ($count - 1))) / $count;

    foreach (array_values($cards) as $index => $card) {
        $x = $left + ($index * ($width + $gap));
        $pdf->SetXY($x, $y);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Rect($x, $y, $width, $height, 'DF');
        $pdf->SetXY($x + 3, $y + 2);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell($width - 6, 4, pdf_text((string) ($card['label'] ?? '')), 0, 2, 'L');
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell($width - 6, 5, pdf_text(pdf_truncate((string) ($card['value'] ?? '-'), 30)), 0, 2, 'L');
        if (!empty($card['note'])) {
            $pdf->SetFont('Arial', '', 6.8);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell($width - 6, 4, pdf_text(pdf_truncate((string) $card['note'], 34)), 0, 2, 'L');
        }
    }

    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetY($y + $height + 7);
}

function pdf_detect_image_type(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $info = @getimagesize($path);
    $type = (int) ($info[2] ?? 0);
    if ($type === IMAGETYPE_JPEG) {
        return 'JPG';
    }
    if ($type === IMAGETYPE_PNG) {
        return 'PNG';
    }
    if ($type === IMAGETYPE_GIF) {
        return 'GIF';
    }

    return '';
}

function pdf_draw_image_safe(FPDF $pdf, string $path, float $x, float $y, float $w, float $h): bool
{
    $type = pdf_detect_image_type($path);
    if ($type === '') {
        return false;
    }

    try {
        $pdf->Image($path, $x, $y, $w, $h, $type);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function pdf_logo_path(): string
{
    $candidates = [
        __DIR__ . '/../../public/assets/images/logo.png',
        __DIR__ . '/../../public/assets/images/logo.jpg',
        __DIR__ . '/../../public/assets/images/logo.jpeg',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        if (pdf_detect_image_type($path) !== '') {
            return $path;
        }
    }

    return '';
}

function pdf_store_info(): array
{
    return store_info_get();
}

class BasePDF extends FPDF
{
    public function contentLeft(): float
    {
        return (float) $this->lMargin;
    }

    public function contentRightMargin(): float
    {
        return (float) $this->rMargin;
    }

    // Draw ellipse based on Bezier curves.
    public function Ellipse(float $x, float $y, float $rx, float $ry, string $style = 'D'): void
    {
        $op = 'S';
        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        }

        $k = $this->k;
        $h = $this->h;
        $lx = 4 / 3 * (sqrt(2) - 1) * $rx;
        $ly = 4 / 3 * (sqrt(2) - 1) * $ry;

        $this->_out(sprintf('%.2F %.2F m', ($x + $rx) * $k, ($h - $y) * $k));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $k,
            ($h - ($y - $ly)) * $k,
            ($x + $lx) * $k,
            ($h - ($y - $ry)) * $k,
            $x * $k,
            ($h - ($y - $ry)) * $k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $lx) * $k,
            ($h - ($y - $ry)) * $k,
            ($x - $rx) * $k,
            ($h - ($y - $ly)) * $k,
            ($x - $rx) * $k,
            ($h - $y) * $k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $rx) * $k,
            ($h - ($y + $ly)) * $k,
            ($x - $lx) * $k,
            ($h - ($y + $ry)) * $k,
            $x * $k,
            ($h - ($y + $ry)) * $k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $lx) * $k,
            ($h - ($y + $ry)) * $k,
            ($x + $rx) * $k,
            ($h - ($y + $ly)) * $k,
            ($x + $rx) * $k,
            ($h - $y) * $k
        ));
        $this->_out($op);
    }

    // Draw circle helper.
    public function Circle(float $x, float $y, float $r, string $style = 'D'): void
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    // Draw a simple location pin icon.
    public function drawLocationIcon(float $x, float $y, float $size = 4): void
    {
        $centerX = $x + ($size / 2);
        $centerY = $y + ($size / 2);
        $radius = $size * 0.28;
        $baseY = $centerY + $radius;
        $bottomY = $y + $size;

        $this->Circle($centerX, $centerY, $radius, 'D');
        $this->Line($centerX - $radius, $baseY, $centerX, $bottomY);
        $this->Line($centerX + $radius, $baseY, $centerX, $bottomY);
        $this->Line($centerX - $radius, $baseY, $centerX + $radius, $baseY);
    }
}

class BosReportPDF extends BasePDF
{
    public string $logoPath = '';
    public string $storeName = 'KASPINDO';
    public array $filters = [];

    function Header(): void
    {
        $this->SetTextColor(15, 23, 42);
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            pdf_draw_image_safe($this, $this->logoPath, 10, 8, 12, 12);
        }
        $this->SetXY(25, 8);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(110, 5, pdf_text($this->storeName), 0, 2, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(110, 4, pdf_text('Dokumen operasional POS'), 0, 2, 'L');

        $this->SetXY(162, 8);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(125, 6, pdf_text('LAPORAN TRANSAKSI'), 0, 2, 'R');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(125, 4, pdf_text('Ringkasan penjualan dan profit'), 0, 2, 'R');

        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, 24, 287, 24);
        $this->SetY(28);
        $this->SetTextColor(15, 23, 42);
    }

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, pdf_text('Laporan ini dibuat otomatis oleh sistem.'), 0, 1, 'C');
        $this->Cell(0, 4, pdf_text($this->storeName . ' | Halaman ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
        $this->SetTextColor(15, 23, 42);
    }
}

class CashReportPDF extends BasePDF
{
    public string $logoPath = '';
    public string $storeName = 'KASPINDO';

    function Header(): void
    {
        $this->SetTextColor(15, 23, 42);
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            pdf_draw_image_safe($this, $this->logoPath, 10, 8, 12, 12);
        }
        $this->SetXY(25, 8);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(110, 5, pdf_text($this->storeName), 0, 2, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(110, 4, pdf_text('Dokumen kontrol kas dan shift'), 0, 2, 'L');

        $this->SetXY(162, 8);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(125, 6, pdf_text('LAPORAN KAS HARIAN'), 0, 2, 'R');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(125, 4, pdf_text('Rekap arus kas, QRIS, dan selisih drawer'), 0, 2, 'R');

        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, 24, 287, 24);
        $this->SetY(28);
        $this->SetTextColor(15, 23, 42);
    }

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, pdf_text('Laporan ini dibuat otomatis oleh sistem.'), 0, 1, 'C');
        $this->Cell(0, 4, pdf_text($this->storeName . ' | Halaman ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
        $this->SetTextColor(15, 23, 42);
    }
}

function build_cash_report_pdf(
    array $dailyRecap,
    array $dailyTotals,
    array $shiftRows,
    array $shiftTotals,
    array $filters
): string {
    $storeInfo = pdf_store_info();
    $storeName = (string) ($storeInfo['store_name'] ?? 'KASPINDO');
    $storeAddress = store_info_compose_address($storeInfo);
    $storePhone = (string) ($storeInfo['store_phone'] ?? '');
    $storeWhatsapp = (string) ($storeInfo['store_whatsapp'] ?? '');
    $storeEmail = (string) ($storeInfo['store_email'] ?? '');

    $pdf = new CashReportPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 12, 10);
    $pdf->SetAutoPageBreak(true, 14);

    $pdf->logoPath = pdf_logo_path();
    $pdf->storeName = $storeName !== '' ? $storeName : 'KASPINDO';

    $pdf->AddPage();

    $period = ($filters['start_date'] ?? '-') . ' - ' . ($filters['end_date'] ?? '-');
    $printedAt = $filters['printed_at'] ?? date('d/m/Y H:i');
    $printedBy = trim((string) ($filters['printed_by'] ?? ''));
    $printedRole = trim((string) ($filters['printed_role'] ?? ''));
    $printedLabel = $printedBy !== '' ? $printedBy : '-';
    if ($printedRole !== '') {
        $printedLabel .= ' (' . $printedRole . ')';
    }

    $netCashTotal = (float) ($dailyTotals['cash_sales'] ?? 0)
        - (float) ($dailyTotals['refund_cash'] ?? 0)
        + (float) ($dailyTotals['cash_in'] ?? 0)
        - (float) ($dailyTotals['cash_out'] ?? 0);
    $netQrisTotal = (float) ($dailyTotals['qris_sales'] ?? 0) - (float) ($dailyTotals['refund_qris'] ?? 0);
    $totalSales = (float) ($dailyTotals['cash_sales'] ?? 0) + (float) ($dailyTotals['qris_sales'] ?? 0);
    $totalRefund = (float) ($dailyTotals['refund_cash'] ?? 0) + (float) ($dailyTotals['refund_qris'] ?? 0);
    $cashMovement = (float) ($dailyTotals['cash_in'] ?? 0) - (float) ($dailyTotals['cash_out'] ?? 0);
    $contactLine = implode(' | ', array_values(array_filter([$storePhone, $storeWhatsapp, $storeEmail], static fn ($value): bool => trim((string) $value) !== '')));

    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(32, 6, pdf_text('Periode'), 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(94, 6, pdf_text($period), 1, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(32, 6, pdf_text('Dicetak Pada'), 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(119, 6, pdf_text($printedAt), 1, 1, 'L');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(32, 6, pdf_text('Store'), 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(94, 6, pdf_text(pdf_truncate($storeName !== '' ? $storeName : 'KASPINDO', 48)), 1, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(32, 6, pdf_text('Dicetak Oleh'), 1, 0, 'L', true);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(119, 6, pdf_text(pdf_truncate($printedLabel, 58)), 1, 1, 'L');

    if ($storeAddress !== '' || $contactLine !== '') {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(32, 6, pdf_text('Alamat/Kontak'), 1, 0, 'L', true);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(245, 6, pdf_text(pdf_truncate(trim($storeAddress . ($contactLine !== '' ? ' | ' . $contactLine : '')), 140)), 1, 1, 'L');
    }
    $pdf->Ln(5);

    pdf_draw_kpi_cards($pdf, [
        ['label' => 'Net Cash', 'value' => pdf_money($netCashTotal), 'note' => 'Sales cash - refund + kas masuk/keluar'],
        ['label' => 'Net QRIS', 'value' => pdf_money($netQrisTotal), 'note' => 'Sales QRIS - refund'],
        ['label' => 'Total Sales', 'value' => pdf_money($totalSales), 'note' => 'Cash + QRIS sebelum refund'],
        ['label' => 'Total Refund', 'value' => pdf_money($totalRefund), 'note' => 'Cash + QRIS'],
        ['label' => 'Arus Kas Manual', 'value' => pdf_money($cashMovement), 'note' => 'Cash in - cash out'],
    ], $pdf->GetY());

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, pdf_text('Rekap Kas Harian'), 0, 1, 'L');

    $dailyHeaders = ['Tanggal', 'Cash In', 'Cash Out', 'Sales Cash', 'Refund Cash', 'Net Cash', 'Sales QRIS', 'Refund QRIS', 'Net QRIS'];
    $dailyWidths = [24, 31.5, 31.5, 31.5, 31.5, 31.5, 31.5, 31.5, 31.5];
    $dailyAligns = ['L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R'];

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial', 'B', 8.5);
    foreach ($dailyHeaders as $i => $header) {
        $pdf->Cell($dailyWidths[$i], 7, pdf_text($header), 1, 0, $dailyAligns[$i], true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8.5);
    foreach ($dailyRecap as $index => $row) {
        $cashIn = (float) ($row['cash_in'] ?? 0);
        $cashOut = (float) ($row['cash_out'] ?? 0);
        $cashSales = (float) ($row['cash_sales'] ?? 0);
        $refundCash = (float) ($row['refund_cash'] ?? 0);
        $qrisSales = (float) ($row['qris_sales'] ?? 0);
        $refundQris = (float) ($row['refund_qris'] ?? 0);
        $netCash = $cashSales - $refundCash + $cashIn - $cashOut;
        $netQris = $qrisSales - $refundQris;

        $rowData = [
            pdf_format_date((string) ($row['date'] ?? '')),
            format_rupiah($cashIn),
            format_rupiah($cashOut),
            format_rupiah($cashSales),
            format_rupiah($refundCash),
            format_rupiah($netCash),
            format_rupiah($qrisSales),
            format_rupiah($refundQris),
            format_rupiah($netQris),
        ];

        $pdf->SetFillColor($index % 2 === 0 ? 255 : 248, $index % 2 === 0 ? 255 : 250, $index % 2 === 0 ? 255 : 252);
        foreach ($rowData as $i => $value) {
            $pdf->Cell($dailyWidths[$i], 7, pdf_text($value), 1, 0, $dailyAligns[$i], $index % 2 !== 0);
        }
        $pdf->Ln();
    }

    $totalRow = [
        'Total',
        format_rupiah((float) ($dailyTotals['cash_in'] ?? 0)),
        format_rupiah((float) ($dailyTotals['cash_out'] ?? 0)),
        format_rupiah((float) ($dailyTotals['cash_sales'] ?? 0)),
        format_rupiah((float) ($dailyTotals['refund_cash'] ?? 0)),
        format_rupiah($netCashTotal),
        format_rupiah((float) ($dailyTotals['qris_sales'] ?? 0)),
        format_rupiah((float) ($dailyTotals['refund_qris'] ?? 0)),
        format_rupiah($netQrisTotal),
    ];
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->SetFillColor(236, 253, 245);
    foreach ($totalRow as $i => $value) {
        $pdf->Cell($dailyWidths[$i], 7, pdf_text($value), 1, 0, $dailyAligns[$i], true);
    }
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, pdf_text('Detail Kas per Shift'), 0, 1, 'L');

    $shiftHeaders = [
        'Shift', 'Kasir', 'Status', 'Mulai', 'Selesai', 'Kas Awal', 'Cash In', 'Cash Out',
        'Sales Cash', 'Refund', 'Expected', 'Kas Akhir', 'Selisih', 'QRIS'
    ];
    $shiftWidths = [20, 22, 14, 22, 22, 19, 18, 18, 20, 19, 21, 19, 17, 20];
    $shiftAligns = ['L', 'L', 'C', 'L', 'L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R'];

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial', 'B', 8);
    foreach ($shiftHeaders as $i => $header) {
        $pdf->Cell($shiftWidths[$i], 7, pdf_text($header), 1, 0, $shiftAligns[$i], true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    foreach ($shiftRows as $index => $row) {
        $diff = $row['diff_cash'];
        $rowData = [
            pdf_truncate((string) ($row['shift_id'] ?? '-'), 12),
            pdf_truncate((string) ($row['user_name'] ?? '-'), 14),
            strtoupper(pdf_truncate((string) ($row['status'] ?? '-'), 6)),
            pdf_format_datetime((string) ($row['opened_at'] ?? '')),
            empty($row['closed_at']) ? '-' : pdf_format_datetime((string) $row['closed_at']),
            format_rupiah((float) ($row['opening_cash'] ?? 0)),
            format_rupiah((float) ($row['cash_in'] ?? 0)),
            format_rupiah((float) ($row['cash_out'] ?? 0)),
            format_rupiah((float) ($row['sales_cash'] ?? 0)),
            format_rupiah((float) ($row['refund_cash'] ?? 0)),
            format_rupiah((float) ($row['expected_cash'] ?? 0)),
            $row['closing_cash'] === null ? '-' : format_rupiah((float) $row['closing_cash']),
            $diff === null ? '-' : format_rupiah((float) $diff),
            format_rupiah((float) ($row['sales_qris'] ?? 0)),
        ];
        $pdf->SetFillColor($index % 2 === 0 ? 255 : 248, $index % 2 === 0 ? 255 : 250, $index % 2 === 0 ? 255 : 252);
        foreach ($rowData as $i => $value) {
            $pdf->Cell($shiftWidths[$i], 7, pdf_text($value), 1, 0, $shiftAligns[$i], $index % 2 !== 0);
        }
        $pdf->Ln();
    }

    if (empty($shiftRows)) {
        $pdf->Cell(array_sum($shiftWidths), 8, pdf_text('Tidak ada data shift pada periode ini.'), 1, 1, 'C');
    } else {
        $totalRow = [
            'Total', '', '', '', '',
            format_rupiah((float) ($shiftTotals['opening_cash'] ?? 0)),
            format_rupiah((float) ($shiftTotals['cash_in'] ?? 0)),
            format_rupiah((float) ($shiftTotals['cash_out'] ?? 0)),
            format_rupiah((float) ($shiftTotals['sales_cash'] ?? 0)),
            format_rupiah((float) ($shiftTotals['refund_cash'] ?? 0)),
            format_rupiah((float) ($shiftTotals['expected_cash'] ?? 0)),
            format_rupiah((float) ($shiftTotals['closing_cash'] ?? 0)),
            '-',
            format_rupiah((float) ($shiftTotals['sales_qris'] ?? 0)),
        ];
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(236, 253, 245);
        foreach ($totalRow as $i => $value) {
            $pdf->Cell($shiftWidths[$i], 7, pdf_text($value), 1, 0, $shiftAligns[$i], true);
        }
        $pdf->Ln();
    }

    return $pdf->Output('S');
}

function build_bos_report_pdf(array $rows, array $summary, array $filters): string
{
    $storeInfo = pdf_store_info();
    $storeName = (string) ($storeInfo['store_name'] ?? 'KASPINDO');
    $storeAddress = store_info_compose_address($storeInfo);
    $storePhone = (string) ($storeInfo['store_phone'] ?? '');
    $storeWhatsapp = (string) ($storeInfo['store_whatsapp'] ?? '');
    $storeEmail = (string) ($storeInfo['store_email'] ?? '');

    $pdf = new BosReportPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 12, 10);
    $pdf->SetAutoPageBreak(true, 14);

    $pdf->logoPath = pdf_logo_path();
    $pdf->storeName = $storeName !== '' ? $storeName : 'KASPINDO';
    $pdf->filters = $filters;

    $pdf->AddPage();

    $period = ($filters['start_date'] ?? '-') . ' - ' . ($filters['end_date'] ?? '-');
    $method = strtoupper((string) ($filters['method'] ?? 'all'));
    $printedAt = $filters['printed_at'] ?? date('d/m/Y H:i');
    $printedBy = trim((string) ($filters['printed_by'] ?? ''));
    $printedRole = trim((string) ($filters['printed_role'] ?? ''));
    $printedLabel = $printedBy !== '' ? $printedBy : '-';
    if ($printedRole !== '') {
        $printedLabel .= ' (' . $printedRole . ')';
    }

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(36, 5, pdf_text('Periode Laporan'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_text(': ' . $period), 0, 1, 'L');
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(36, 5, pdf_text('Metode Pembayaran'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_text(': ' . $method), 0, 1, 'L');
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(36, 5, pdf_text('Tanggal Cetak'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_text(': ' . $printedAt), 0, 1, 'L');
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(36, 5, pdf_text('Dicetak Oleh'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_text(': ' . $printedLabel), 0, 1, 'L');
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(36, 5, pdf_text('Lokasi Store'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->MultiCell(0, 5, pdf_text(': ' . ($storeAddress !== '' ? $storeAddress : '-')), 0, 'L');
    if ($storePhone !== '') {
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(36, 5, pdf_text('No. Telepon'), 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 5, pdf_text(': ' . $storePhone), 0, 1, 'L');
    }
    if ($storeWhatsapp !== '') {
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(36, 5, pdf_text('WhatsApp'), 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 5, pdf_text(': ' . $storeWhatsapp), 0, 1, 'L');
    }
    if ($storeEmail !== '') {
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(36, 5, pdf_text('Email'), 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 5, pdf_text(': ' . $storeEmail), 0, 1, 'L');
    }
    $pdf->Ln(4);

    $totalTransactions = count(array_unique(array_map(static fn ($row) => (string) ($row['id'] ?? ''), $rows)));
    $summaryQty = (int) ($summary['qty'] ?? 0);
    $summarySales = (float) ($summary['sales'] ?? 0);
    $summaryProfit = (float) ($summary['profit'] ?? 0);
    $summaryCash = (float) ($summary['cash'] ?? 0);
    $summaryQris = (float) ($summary['qris'] ?? 0);

    $summaryValue = static function ($value, bool $isCurrency = false): string {
        if ((float) $value <= 0) {
            return $isCurrency ? format_rupiah(0) : '0';
        }
        return $isCurrency ? format_rupiah((float) $value) : (string) $value;
    };

    $row1 = [
        ['Total Transaksi', $summaryValue($totalTransactions)],
        ['Total Qty', $summaryValue($summaryQty)],
    ];
    $summaryRefund = (float) ($summary['refund'] ?? 0);
    $summaryNetSales = (float) ($summary['net_sales'] ?? 0);

    $row2 = [
        ['Total Penjualan', $summaryValue($summarySales, true)],
        ['Total Refund', $summaryValue($summaryRefund, true)],
        ['Net Penjualan', $summaryValue($summaryNetSales > 0 ? $summaryNetSales : $summarySales, true)],
        ['Total Profit', $summaryValue($summaryProfit, true)],
        ['Total Cash', $summaryValue($summaryCash, true)],
        ['Total QRIS', $summaryValue($summaryQris, true)],
    ];

    $gap = 4;
    $startX = 10;
    $startY = $pdf->GetY();
    $cardHeight = 14;

    $row1Width = (277 - $gap) / 2;
    foreach ($row1 as $index => $card) {
        $x = $startX + $index * ($row1Width + $gap);
        $pdf->SetXY($x, $startY);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Rect($x, $startY, $row1Width, $cardHeight);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell($row1Width, 5, pdf_text($card[0]), 0, 2, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell($row1Width, 7, pdf_text($card[1]), 0, 2, 'L');
    }

    $row2Y = $startY + $cardHeight + 4;
    $row2Count = count($row2);
    $row2Width = (277 - ($gap * ($row2Count - 1))) / $row2Count;
    foreach ($row2 as $index => $card) {
        $x = $startX + $index * ($row2Width + $gap);
        $pdf->SetXY($x, $row2Y);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Rect($x, $row2Y, $row2Width, $cardHeight);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell($row2Width, 5, pdf_text($card[0]), 0, 2, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell($row2Width, 7, pdf_text($card[1]), 0, 2, 'L');
    }

    $pdf->SetY($row2Y + $cardHeight + 6);

    $paymentTotal = $summaryCash + $summaryQris;
    $cashPct = $paymentTotal > 0 ? ($summaryCash / $paymentTotal) * 100 : 0.0;
    $qrisPct = $paymentTotal > 0 ? ($summaryQris / $paymentTotal) * 100 : 0.0;
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(0, 5, pdf_text('Rincian Pembayaran:'), 0, 1, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(
        0,
        5,
        pdf_text(
            'Cash ' . format_rupiah($summaryCash) . ' (' . number_format($cashPct, 1) . '%) | ' .
            'QRIS ' . format_rupiah($summaryQris) . ' (' . number_format($qrisPct, 1) . '%)'
        ),
        0,
        1,
        'L'
    );
    $pdf->Ln(4);

    $headers = ['Nomor', 'Tanggal', 'Kasir', 'Nama Item', 'Qty', 'Harga Satuan', 'Harga Jual', 'Modal', 'Profit', 'Metode'];
    $widths = [14, 28, 28, 80, 10, 24, 24, 24, 24, 17];
    $aligns = ['L', 'L', 'L', 'L', 'R', 'R', 'R', 'R', 'R', 'C'];

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial', 'B', 9);
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 7, pdf_text($header), 1, 0, $aligns[$i], true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 9);
    foreach ($rows as $index => $row) {
        $qty = (int) ($row['qty'] ?? 0);
        $price = (float) ($row['price'] ?? 0);
        $cost = (float) ($row['cost_price'] ?? 0);
        $sales = $qty * $price;
        $profit = $sales - ($qty * $cost);
        $itemName = pdf_truncate((string) ($row['product_name'] ?? '-'), 32);

        $data = [
            '#' . (string) $row['id'],
            pdf_format_datetime((string) $row['created_at']),
            pdf_truncate((string) ($row['cashier'] ?? '-'), 18),
            $itemName,
            (string) $qty,
            format_rupiah($price),
            format_rupiah($sales),
            format_rupiah($qty * $cost),
            format_rupiah($profit),
            strtoupper((string) ($row['method'] ?? '-')),
        ];

        $pdf->SetFillColor($index % 2 === 0 ? 255 : 248, $index % 2 === 0 ? 255 : 250, $index % 2 === 0 ? 255 : 252);
        foreach ($data as $i => $value) {
            $pdf->Cell($widths[$i], 7, pdf_text($value), 1, 0, $aligns[$i], $index % 2 !== 0);
        }
        $pdf->Ln();
    }

    if (empty($rows)) {
        $pdf->Cell(array_sum($widths), 8, pdf_text('Tidak ada transaksi pada periode ini.'), 1, 1, 'C');
    }

    return $pdf->Output('S');
}

function build_receipt_pdf(array $receipt): string
{
    $storeInfo = pdf_store_info();
    $storeName = (string) ($storeInfo['store_name'] ?? 'KASPINDO');
    $storeAddress = store_info_compose_address($storeInfo);
    $storePhone = trim((string) ($storeInfo['store_phone'] ?? ''));
    $storeWhatsapp = trim((string) ($storeInfo['store_whatsapp'] ?? ''));
    $storeInstagram = trim((string) ($storeInfo['store_instagram'] ?? ''));
    $businessHours = trim((string) ($storeInfo['business_hours'] ?? ''));
    $receiptFooter = trim((string) ($storeInfo['receipt_footer'] ?? 'Terima kasih!'));

    $pdf = new BasePDF('P', 'mm', [80, 200]);
    $pdf->SetMargins(6, 6, 6);
    $pdf->SetAutoPageBreak(true, 6);
    $pdf->AddPage();

    $logoPath = pdf_logo_path();

    if ($logoPath !== '') {
        if (pdf_draw_image_safe($pdf, $logoPath, 33, 6, 14, 14)) {
            $pdf->Ln(18);
        } else {
            $pdf->Ln(6);
        }
    } else {
        $pdf->Ln(6);
    }

    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell(0, 4, pdf_text($storeName !== '' ? $storeName : 'KASPINDO'), 0, 1, 'C');
    $pdf->SetFont('Courier', '', 8);
    $pdf->Cell(0, 4, pdf_text('Struk Pembayaran'), 0, 1, 'C');
    $pdf->Ln(2);

    $divider = str_repeat('-', 42);
    $pdf->Cell(0, 4, pdf_text($divider), 0, 1, 'C');

    $createdAt = $receipt['created_at'] ?? '-';
    $pdf->Cell(34, 4, pdf_text('No. #' . ($receipt['id'] ?? '-')), 0, 0, 'L');
    $pdf->Cell(0, 4, pdf_text($createdAt), 0, 1, 'R');
    $pdf->Cell(34, 4, pdf_text('Kasir'), 0, 0, 'L');
    $pdf->Cell(0, 4, pdf_text($receipt['cashier'] ?? '-'), 0, 1, 'R');
    $pdf->Cell(34, 4, pdf_text('Metode'), 0, 0, 'L');
    $pdf->Cell(0, 4, pdf_text((string) ($receipt['method'] ?? '-')), 0, 1, 'R');
    $pdf->Cell(0, 4, pdf_text($divider), 0, 1, 'C');

    $pdf->SetFont('Courier', 'B', 8);
    $pdf->Cell(38, 4, pdf_text('Item'), 0, 0, 'L');
    $pdf->Cell(8, 4, pdf_text('Qty'), 0, 0, 'R');
    $pdf->Cell(22, 4, pdf_text('Total'), 0, 1, 'R');
    $pdf->SetFont('Courier', '', 8);

    $items = $receipt['items'] ?? [];
    if (!empty($items)) {
        foreach ($items as $item) {
            $name = pdf_truncate((string) ($item['name'] ?? '-'), 22);
            $qty = (int) ($item['qty'] ?? 0);
            $total = (float) ($item['total'] ?? 0);

            $pdf->Cell(38, 4, pdf_text($name), 0, 0, 'L');
            $pdf->Cell(8, 4, pdf_text((string) $qty), 0, 0, 'R');
            $pdf->Cell(22, 4, pdf_text(format_rupiah($total)), 0, 1, 'R');
        }
    } else {
        $pdf->Cell(0, 4, pdf_text('Tidak ada item.'), 0, 1, 'L');
    }

    $pdf->Cell(0, 4, pdf_text($divider), 0, 1, 'C');
    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell(38, 5, pdf_text('Total'), 0, 0, 'L');
    $pdf->Cell(0, 5, pdf_text(format_rupiah((float) ($receipt['total'] ?? 0))), 0, 1, 'R');

    $note = trim((string) ($receipt['note'] ?? ''));
    if ($note !== '') {
        $pdf->SetFont('Courier', '', 8);
        $pdf->Cell(0, 4, pdf_text($divider), 0, 1, 'C');
        $pdf->Cell(0, 4, pdf_text('Catatan:'), 0, 1, 'L');
        $pdf->MultiCell(0, 4, pdf_text($note), 0, 'L');
    }

    $pdf->Cell(0, 4, pdf_text($divider), 0, 1, 'C');
    $pdf->SetFont('Courier', '', 8);
    if ($storeInstagram !== '') {
        $pdf->Cell(0, 4, pdf_text($storeInstagram), 0, 1, 'C');
    }
    $pdf->SetFont('Courier', '', 7);
    if ($storeAddress !== '') {
        $pdf->MultiCell(0, 3.5, pdf_text('Lokasi: ' . $storeAddress), 0, 'C');
    }
    if ($storePhone !== '') {
        $pdf->Cell(0, 3.5, pdf_text('Telp: ' . $storePhone), 0, 1, 'C');
    }
    if ($storeWhatsapp !== '') {
        $pdf->Cell(0, 3.5, pdf_text('WA: ' . $storeWhatsapp), 0, 1, 'C');
    }
    if ($businessHours !== '') {
        $pdf->Cell(0, 3.5, pdf_text('Jam: ' . $businessHours), 0, 1, 'C');
    }
    $pdf->Cell(0, 4, pdf_text($receiptFooter !== '' ? $receiptFooter : 'Terima kasih!'), 0, 1, 'C');

    return $pdf->Output('S');
}
