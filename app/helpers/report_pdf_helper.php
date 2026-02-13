<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/format_helper.php';

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

class BasePDF extends FPDF
{
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
    public array $filters = [];

    function Header(): void
    {
        $this->SetFont('Arial', 'B', 12);
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            $this->Image($this->logoPath, 10, 8, 14, 14);
        }
        $this->Cell(0, 6, pdf_text('MY KASPIN'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, pdf_text('LAPORAN TRANSAKSI'), 0, 1, 'C');
        $this->Ln(4);
    }

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, pdf_text('Laporan ini dibuat otomatis oleh sistem.'), 0, 1, 'C');
        $this->Cell(0, 4, pdf_text('MY KASPIN | Halaman ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
        $this->SetTextColor(15, 23, 42);
    }
}

class CashReportPDF extends BasePDF
{
    public string $logoPath = '';

    function Header(): void
    {
        $this->SetFont('Arial', 'B', 12);
        if ($this->logoPath !== '' && is_file($this->logoPath)) {
            $this->Image($this->logoPath, 10, 8, 14, 14);
        }
        $this->Cell(0, 6, pdf_text('MY KASPIN'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 6, pdf_text('LAPORAN KAS HARIAN'), 0, 1, 'C');
        $this->Ln(4);
    }

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, pdf_text('Laporan ini dibuat otomatis oleh sistem.'), 0, 1, 'C');
        $this->Cell(0, 4, pdf_text('MY KASPIN | Halaman ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
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
    $pdf = new CashReportPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 12, 10);
    $pdf->SetAutoPageBreak(true, 14);

    $logoPng = __DIR__ . '/../../public/assets/images/logo.png';
    $logoJpg = __DIR__ . '/../../public/assets/images/logo.jpg';
    $pdf->logoPath = is_file($logoPng) ? $logoPng : (is_file($logoJpg) ? $logoJpg : '');

    $pdf->AddPage();

    $period = ($filters['start_date'] ?? '-') . ' - ' . ($filters['end_date'] ?? '-');
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
    $pdf->Cell(36, 5, pdf_text('Tanggal Cetak'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_text(': ' . $printedAt), 0, 1, 'L');
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(36, 5, pdf_text('Dicetak Oleh'), 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 5, pdf_text(': ' . $printedLabel), 0, 1, 'L');
    $pdf->Ln(4);

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
    foreach ($dailyRecap as $row) {
        $cashIn = (float) ($row['cash_in'] ?? 0);
        $cashOut = (float) ($row['cash_out'] ?? 0);
        $cashSales = (float) ($row['cash_sales'] ?? 0);
        $refundCash = (float) ($row['refund_cash'] ?? 0);
        $qrisSales = (float) ($row['qris_sales'] ?? 0);
        $refundQris = (float) ($row['refund_qris'] ?? 0);
        $netCash = $cashSales - $refundCash + $cashIn - $cashOut;
        $netQris = $qrisSales - $refundQris;

        $rowData = [
            (string) ($row['date'] ?? '-'),
            format_rupiah($cashIn),
            format_rupiah($cashOut),
            format_rupiah($cashSales),
            format_rupiah($refundCash),
            format_rupiah($netCash),
            format_rupiah($qrisSales),
            format_rupiah($refundQris),
            format_rupiah($netQris),
        ];

        foreach ($rowData as $i => $value) {
            $pdf->Cell($dailyWidths[$i], 7, pdf_text($value), 1, 0, $dailyAligns[$i]);
        }
        $pdf->Ln();
    }

    $netCashTotal = (float) ($dailyTotals['cash_sales'] ?? 0)
        - (float) ($dailyTotals['refund_cash'] ?? 0)
        + (float) ($dailyTotals['cash_in'] ?? 0)
        - (float) ($dailyTotals['cash_out'] ?? 0);
    $netQrisTotal = (float) ($dailyTotals['qris_sales'] ?? 0) - (float) ($dailyTotals['refund_qris'] ?? 0);

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
    foreach ($totalRow as $i => $value) {
        $pdf->Cell($dailyWidths[$i], 7, pdf_text($value), 1, 0, $dailyAligns[$i]);
    }
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, pdf_text('Detail Kas per Shift'), 0, 1, 'L');

    $shiftHeaders = [
        'Shift', 'Kasir', 'Mulai', 'Selesai', 'Kas Awal', 'Cash In', 'Cash Out',
        'Sales Cash', 'Refund Cash', 'Expected Cash', 'Kas Akhir', 'Selisih', 'Sales QRIS'
    ];
    $shiftWidths = [20, 22, 24, 24, 20, 20, 20, 20, 20, 22, 20, 18, 22];
    $shiftAligns = ['L', 'L', 'L', 'L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R'];

    $pdf->SetFillColor(248, 250, 252);
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFont('Arial', 'B', 8);
    foreach ($shiftHeaders as $i => $header) {
        $pdf->Cell($shiftWidths[$i], 7, pdf_text($header), 1, 0, $shiftAligns[$i], true);
    }
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 8);
    foreach ($shiftRows as $row) {
        $diff = $row['diff_cash'];
        $rowData = [
            (string) ($row['shift_id'] ?? '-'),
            (string) ($row['user_name'] ?? '-'),
            (string) ($row['opened_at'] ?? '-'),
            (string) ($row['closed_at'] ?? '-'),
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
        foreach ($rowData as $i => $value) {
            $pdf->Cell($shiftWidths[$i], 7, pdf_text($value), 1, 0, $shiftAligns[$i]);
        }
        $pdf->Ln();
    }

    if (empty($shiftRows)) {
        $pdf->Cell(array_sum($shiftWidths), 8, pdf_text('Tidak ada data shift pada periode ini.'), 1, 1, 'C');
    } else {
        $totalRow = [
            'Total', '', '', '',
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
        foreach ($totalRow as $i => $value) {
            $pdf->Cell($shiftWidths[$i], 7, pdf_text($value), 1, 0, $shiftAligns[$i]);
        }
        $pdf->Ln();
    }

    return $pdf->Output('S');
}

function build_bos_report_pdf(array $rows, array $summary, array $filters): string
{
    $pdf = new BosReportPDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(10, 12, 10);
    $pdf->SetAutoPageBreak(true, 14);

    $logoPng = __DIR__ . '/../../public/assets/images/logo.png';
    $logoJpg = __DIR__ . '/../../public/assets/images/logo.jpg';
    $pdf->logoPath = is_file($logoPng) ? $logoPng : (is_file($logoJpg) ? $logoJpg : '');
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
    $storeAddress = 'Jl. Sma 1 Kel No.Rt. 16, Aur, Sarolangun, Kab. Sarolangun, Jambi 37481';

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
    $pdf->MultiCell(0, 5, pdf_text(': ' . $storeAddress), 0, 'L');
    $pdf->Ln(4);

    $totalTransactions = count(array_unique(array_map(static fn ($row) => (string) ($row['id'] ?? ''), $rows)));
    $summaryQty = (int) ($summary['qty'] ?? 0);
    $summarySales = (float) ($summary['sales'] ?? 0);
    $summaryProfit = (float) ($summary['profit'] ?? 0);
    $summaryCash = (float) ($summary['cash'] ?? 0);
    $summaryQris = (float) ($summary['qris'] ?? 0);

    $summaryValue = static function ($value, bool $isCurrency = false): string {
        if ((float) $value <= 0) {
            return 'Tidak ada transaksi';
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
    foreach ($rows as $row) {
        $qty = (int) ($row['qty'] ?? 0);
        $price = (float) ($row['price'] ?? 0);
        $cost = (float) ($row['cost_price'] ?? 0);
        $sales = $qty * $price;
        $profit = $sales - ($qty * $cost);
        $itemName = pdf_truncate((string) ($row['product_name'] ?? '-'), 32);

        $data = [
            '#' . (string) $row['id'],
            date('d/m/Y H:i', strtotime((string) $row['created_at'])),
            (string) ($row['cashier'] ?? '-'),
            $itemName,
            (string) $qty,
            format_rupiah($price),
            format_rupiah($sales),
            format_rupiah($qty * $cost),
            format_rupiah($profit),
            strtoupper((string) ($row['method'] ?? '-')),
        ];

        foreach ($data as $i => $value) {
            $pdf->Cell($widths[$i], 7, pdf_text($value), 1, 0, $aligns[$i]);
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
    $pdf = new BasePDF('P', 'mm', [80, 200]);
    $pdf->SetMargins(6, 6, 6);
    $pdf->SetAutoPageBreak(true, 6);
    $pdf->AddPage();

    $logoPng = __DIR__ . '/../../public/assets/images/logo.png';
    $logoJpg = __DIR__ . '/../../public/assets/images/logo.jpg';
    $logoPath = is_file($logoPng) ? $logoPng : (is_file($logoJpg) ? $logoJpg : '');

    if ($logoPath !== '') {
        $pdf->Image($logoPath, 33, 6, 14, 14);
        $pdf->Ln(18);
    } else {
        $pdf->Ln(6);
    }

    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell(0, 4, pdf_text('MY KASPIN'), 0, 1, 'C');
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
    $pdf->Cell(0, 4, pdf_text('@mykaspin'), 0, 1, 'C');
    $pdf->SetFont('Courier', '', 7);
    $storeAddress = 'Jl. Sma 1 Kel No.Rt. 16, Aur, Sarolangun, Kab. Sarolangun, Jambi 37481';
    $pdf->MultiCell(0, 3.5, pdf_text('Lokasi: ' . $storeAddress), 0, 'C');
    $pdf->Cell(0, 4, pdf_text('Terima kasih!'), 0, 1, 'C');

    return $pdf->Output('S');
}
