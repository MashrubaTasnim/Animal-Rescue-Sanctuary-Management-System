<?php
/**
 * generate_receipt.php
 * Generates a styled PDF donation receipt using FPDF.
 * 
 * Usage (called internally after successful payment):
 *   require_once 'generate_receipt.php';
 *   $path = generateDonationReceipt($conn, $tran_id);
 *   // $path = 'receipts/RCP-2025-00001.pdf'
 *
 * Or via URL for download (logged-in user only):
 *   generate_receipt.php?trx_id=PT_abc123
 */

// ── Auto-install FPDF if missing ─────────────────────────────
// If you use Composer, add "fpdf/fpdf": "^1.86" to composer.json instead.
if (!class_exists('FPDF')) {
    $fpdf_path = __DIR__ . '/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        die('FPDF not found. Run: composer require fpdf/fpdf  OR place fpdf/ folder here.');
    }
    require_once $fpdf_path;
}

// ── Receipt storage folder ────────────────────────────────────
define('RECEIPT_DIR', __DIR__ . '/receipts/');
if (!is_dir(RECEIPT_DIR)) {
    mkdir(RECEIPT_DIR, 0755, true);
}

// ─────────────────────────────────────────────────────────────
// MAIN FUNCTION
// ─────────────────────────────────────────────────────────────
function generateDonationReceipt(mysqli $conn, string $tran_id): string|false
{
    // 1. Fetch donation + donor info
    $stmt = $conn->prepare("
        SELECT d.id, d.amount, d.method, d.status, d.created_at, d.trx_id,
               u.full_name, u.email
        FROM   donations d
        JOIN   users u ON u.id = d.user_id
        WHERE  d.trx_id = ?
        LIMIT  1
    ");
    $stmt->bind_param('s', $tran_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) return false;

    // 2. Build a sequential receipt number  e.g. RCP-2025-00042
    $year       = date('Y', strtotime($row['created_at']));
    $receipt_no = sprintf('RCP-%s-%05d', $year, $row['id']);
    $date_str   = date('d M Y, h:i A', strtotime($row['created_at']));

    // 3. Create the PDF
    $pdf = new ReceiptPDF('P', 'mm', 'A4');
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pdf->buildReceipt([
        'receipt_no' => $receipt_no,
        'donor_name' => $row['full_name'],
        'email'      => $row['email'],
        'amount'     => number_format((float)$row['amount'], 2),
        'method'     => $row['method'],
        'trx_id'     => $row['trx_id'],
        'date'       => $date_str,
        'status'     => strtoupper($row['status']),
    ]);

    // 4. Save to disk
    $filename = RECEIPT_DIR . $receipt_no . '.pdf';
    $pdf->Output('F', $filename);

    return 'receipts/' . $receipt_no . '.pdf';   // relative path for DB or links
}

// ─────────────────────────────────────────────────────────────
// PDF CLASS
// ─────────────────────────────────────────────────────────────
class ReceiptPDF extends FPDF
{
    // ── Brand colours ──────────────────────────────────────────
    private array $midnight = [10,  19,  41];   // #0a1329
    private array $gold     = [212, 175,  55];  // #d4af37
    private array $green    = [ 25, 135,  84];  // #198754
    private array $lightbg  = [246, 248, 251];  // #f6f8fb
    private array $muted    = [100, 116, 139];  // slate-500

    // ── Entry point ────────────────────────────────────────────
    public function buildReceipt(array $d): void
    {
        $this->drawHeader($d['receipt_no']);
        $this->drawAmountBadge($d['amount']);
        $this->drawDetailsTable($d);
        $this->drawNote();
        $this->drawFooter();
    }

    // ── Header band ────────────────────────────────────────────
    private function drawHeader(string $receipt_no): void
    {
        [$r, $g, $b] = $this->midnight;

        // Dark background
        $this->SetFillColor($r, $g, $b);
        $this->Rect(0, 0, 210, 65, 'F');

        // Org name
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 22);
        $this->SetY(12);
        $this->Cell(0, 10, 'Heartbeat Heaven', 0, 1, 'C');

        // Tagline
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(170, 180, 200);
        $this->Cell(0, 6, 'Caring for Animals, One Paw at a Time', 0, 1, 'C');

        // Gold divider
        [$gr, $gg, $gb] = $this->gold;
        $this->SetDrawColor($gr, $gg, $gb);
        $this->SetLineWidth(0.6);
        $this->Line(30, 36, 180, 36);

        // "OFFICIAL DONATION RECEIPT" label
        $this->SetTextColor($gr, $gg, $gb);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetY(40);
        $this->Cell(0, 7, 'OFFICIAL DONATION RECEIPT', 0, 1, 'C');

        // Receipt number
        $this->SetTextColor(170, 180, 200);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(0, 5, 'Receipt No: ' . $receipt_no, 0, 1, 'C');

        // Reset text colour
        $this->SetTextColor(0, 0, 0);
    }

    // ── Big amount badge ───────────────────────────────────────
    private function drawAmountBadge(string $amount): void
    {
        $this->SetY(76);

        // Label
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(...$this->muted);
        $this->Cell(0, 5, 'DONATION AMOUNT', 0, 1, 'C');

        // Coloured pill
        [$r, $g, $b] = $this->midnight;
        $this->SetFillColor($r, $g, $b);
        $pillW = 70; $pillH = 14;
        $x = (210 - $pillW) / 2;
        $this->RoundedRect($x, $this->GetY() + 1, $pillW, $pillH, 4, 'F');

        [$gr, $gg, $gb] = $this->gold;
        $this->SetTextColor($gr, $gg, $gb);
        $this->SetFont('Helvetica', 'B', 17);
        $this->SetY($this->GetY() + 3);
        $this->Cell(0, 10, 'BDT ' . $amount, 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(5);
    }

    // ── Details table ──────────────────────────────────────────
    private function drawDetailsTable(array $d): void
    {
        $rows = [
            ['Donor Name',     $d['donor_name']],
            ['Email Address',  $d['email']],
            ['Payment Method', $d['method']],
            ['Transaction ID', $d['trx_id']],
            ['Date & Time',    $d['date']],
            ['Status',         $d['status']],
        ];

        $startY  = $this->GetY();
        $labelW  = 52;
        $valueW  = 118;
        $rowH    = 10;
        $x       = 20;

        foreach ($rows as $i => [$label, $value]) {
            // Alternating row background
            if ($i % 2 === 0) {
                $this->SetFillColor(...$this->lightbg);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $y = $this->GetY();

            // Row background
            $this->Rect($x, $y, $labelW + $valueW, $rowH, 'F');

            // Label
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->SetTextColor(...$this->muted);
            $this->SetXY($x + 3, $y + 2.5);
            $this->Cell($labelW, 5, strtoupper($label), 0, 0);

            // Value — highlight status green
            if ($label === 'Status') {
                $this->SetTextColor(...$this->green);
                $this->SetFont('Helvetica', 'B', 8.5);
            } else {
                $this->SetTextColor(10, 19, 41);
                $this->SetFont('Helvetica', '', 8.5);
            }
            $this->SetXY($x + $labelW + 3, $y + 2.5);
            $this->Cell($valueW, 5, $value, 0, 0);

            $this->SetY($y + $rowH);
        }

        // Bottom border of table
        $this->SetDrawColor(200, 208, 220);
        $this->SetLineWidth(0.3);
        $this->Line($x, $this->GetY(), $x + $labelW + $valueW, $this->GetY());

        $this->Ln(8);
    }

    // ── Charitable note ────────────────────────────────────────
    private function drawNote(): void
    {
        // Light card bg
        $this->SetFillColor(...$this->lightbg);
        $cardY = $this->GetY();
        $this->Rect(20, $cardY, 170, 24, 'F');

        // Left gold accent bar
        $this->SetFillColor(...$this->gold);
        $this->Rect(20, $cardY, 3, 24, 'F');

        $this->SetXY(27, $cardY + 3);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetTextColor(10, 19, 41);
        $this->Cell(0, 5, 'Important Note for Tax Purposes', 0, 1);

        $this->SetX(27);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...$this->muted);
        $this->MultiCell(160, 4.5,
            'This receipt confirms your donation to Heartbeat Heaven (Praner Tan). ' .
            'Please retain this document for your records. ' .
            'Heartbeat Heaven is a non-profit animal welfare organisation.', 0, 'L');

        $this->Ln(6);

        // Motivational quote
        $this->SetFont('Helvetica', 'I', 8.5);
        $this->SetTextColor(...$this->muted);
        $this->Cell(0, 5,
            '"Helping one animal might not change the world, but for that one animal the world will change forever."',
            0, 1, 'C');
    }

    // ── Footer ─────────────────────────────────────────────────
    private function drawFooter(): void
    {
        $this->SetY(-28);

        // Divider
        $this->SetDrawColor(200, 208, 220);
        $this->SetLineWidth(0.3);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(3);

        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...$this->muted);
        $this->Cell(0, 5, 'Heartbeat Heaven | info@heartbeatheaven.com | www.heartbeatheaven.com', 0, 1, 'C');
        $this->Cell(0, 5, 'Generated automatically on ' . date('d M Y \a\t h:i A'), 0, 1, 'C');
    }

    // ── Helper: rounded rectangle (FPDF has no built-in) ──────
    public function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void
    {
        $k  = $this->k;
        $hp = $this->h;
        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    private function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k
        ));
    }
}

// ─────────────────────────────────────────────────────────────
// DIRECT DOWNLOAD — hit generate_receipt.php?trx_id=PT_xxx
// ─────────────────────────────────────────────────────────────
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    include_once __DIR__ . '/db_config.php';

    $trx = isset($_GET['trx_id']) ? trim($_GET['trx_id']) : '';
    if (!$trx) die('Missing transaction ID.');

    // Security: make sure this donation belongs to the logged-in user
    $check = $conn->prepare("SELECT id FROM donations WHERE trx_id = ? AND user_id = ?");
    $check->bind_param('si', $trx, $_SESSION['user_id']);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) die('Receipt not found.');

    $path = generateDonationReceipt($conn, $trx);
    if (!$path) die('Could not generate receipt.');

    $full = __DIR__ . '/' . $path;
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($full) . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit();
}