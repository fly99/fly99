<?php
declare(strict_types=1);
$sessionLifetime = 30 * 24 * 60 * 60;
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
session_set_cookie_params(['lifetime'=>$sessionLifetime,'path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
session_start();
require __DIR__ . '/db.php';

function pdfError(string $message, int $status): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}

$sessionUser = $_SESSION['staff'] ?? null;
if (!$sessionUser || ($sessionUser['role'] ?? '') !== 'ADMIN') pdfError('Please sign in as Admin to download Staff ID cards.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') pdfError('Use the Download ID button to create a card.', 405);
$payload = json_decode((string)file_get_contents('php://input'), true) ?? [];
$username = strtolower(trim((string)($payload['username'] ?? '')));
if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) pdfError('Staff account not found.', 400);

try {
    $pdo = db();
    $q = $pdo->prepare("SELECT u.username,u.role,u.active,COALESCE(NULLIF(r.name,''),NULLIF(u.full_name,''),u.username) AS full_name,u.contact,u.position,u.date_hired,u.photo_url,u.staff_id,u.id_name_font_size,u.id_back_note,u.emergency_contact_name,u.emergency_contact_number,u.id_position_font_size,u.id_date_font_size,u.id_number_font_size FROM users u LEFT JOIN riders r ON lower(r.username)=lower(u.username) WHERE lower(u.username)=?");
    $q->execute([$username]);
    $staff = $q->fetch();
    if (!$staff) pdfError('Staff account not found.', 404);

    $tcpdfCandidates = [
        '/Applications/MAMP/bin/phpMyAdmin6/vendor/tecnickcom/tcpdf/tcpdf.php',
        '/Applications/MAMP/bin/phpMyAdmin/vendor/tecnickcom/tcpdf/tcpdf.php'
    ];
    $tcpdfPath = '';
    foreach ($tcpdfCandidates as $candidate) if (is_file($candidate)) { $tcpdfPath = $candidate; break; }
    if ($tcpdfPath === '') pdfError('The local PDF generator is unavailable.', 500);
    ob_start();
    require_once $tcpdfPath;
    ob_end_clean();

    $pageW = 53.975;
    $pageH = 85.725;
    $pdf = new TCPDF('P', 'mm', [$pageW, $pageH], true, 'UTF-8', false);
    $pdf->SetCreator('Food Traps Cafe');
    $pdf->SetAuthor('Food Traps Cafe');
    $pdf->SetTitle('Staff ID - ' . (string)$staff['staff_id']);
    $pdf->SetSubject('Staff identification card');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->setImageScale(1.0);
    $pdf->SetFont('helvetica', '', 8);
    $background = __DIR__ . '/assets/staff-id-background.jpeg';
    $logo = __DIR__ . '/assets/foodtraps-logo.png';
    $drawBackground = static function () use ($pdf, $background, $pageW, $pageH): void {
        if (is_file($background)) {
            $pdf->SetAlpha(0.56);
            $pdf->Image($background, 0, 0, $pageW, $pageH, 'JPEG', '', '', false, 300, '', false, false, 0);
            $pdf->SetAlpha(1);
        }
    };
    $drawHeader = static function () use ($pdf, $logo, $pageW): void {
        $pdf->SetFillColor(50, 27, 15);
        $pdf->Rect(0, 0, $pageW, 9.65, 'F');
        if (is_file($logo)) $pdf->Image($logo, 3, 1.2, 14, 7.2, 'PNG', '', '', false, 300, '', false, false, 0);
        $pdf->SetTextColor(255, 248, 232);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(0, 2.0);
        $pdf->Cell($pageW + 9, 5, 'FOOD TRAPS CAFE', 0, 0, 'C', false, '', 0, false, 'T', 'M', true);
    };
    $safeText = static fn($value, int $limit = 500): string => mb_substr(trim((string)$value), 0, $limit, 'UTF-8');

    // Front of card
    $pdf->AddPage();
    $drawBackground();
    $drawHeader();
    $photoPath = '';
    $photoUrl = (string)($staff['photo_url'] ?? '');
    if (str_starts_with($photoUrl, 'uploads/')) {
        $candidate = __DIR__ . '/' . $photoUrl;
        if (is_file($candidate) && realpath(dirname($candidate)) === realpath(__DIR__ . '/uploads')) $photoPath = $candidate;
    }
    if ($photoPath !== '') {
        $pdf->SetDrawColor(203, 161, 107);
        $pdf->SetLineWidth(0.6);
        $pdf->RoundedRect(16.5, 11.4, 21, 21, 2, '1111', 'D');
        $pdf->Image($photoPath, 17, 11.9, 20, 20, '', '', '', false, 300, '', false, false, 0);
    } else {
        $pdf->SetFillColor(244, 236, 223);
        $pdf->SetDrawColor(203, 161, 107);
        $pdf->RoundedRect(16.5, 11.4, 21, 21, 2, '1111', 'DF');
    }
    $nameSize = max(11, min(24, (int)($staff['id_name_font_size'] ?? 14))) * 0.72;
    $pdf->SetTextColor(50, 27, 15);
    $pdf->SetFont('helvetica', 'B', min(15, $nameSize));
    $pdf->SetXY(3, 33.3);
    $pdf->Cell($pageW - 6, 5.2, $safeText($staff['full_name'], 80), 0, 0, 'C', false, '', 1, false, 'T', 'M', true);
    $position = trim((string)($staff['position'] ?? '')) ?: ((string)$staff['role'] === 'ADMIN' ? 'Administrator' : (string)$staff['role']);
    $positionLabelSize = max(8, min(18, (int)($staff['id_position_font_size'] ?? 10))) * 0.72;
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', min(11, $positionLabelSize));
    $pdf->SetXY(3, 39.2);
    $pdf->Cell($pageW - 6, 4, 'POSITION', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 7.5);
    $pdf->SetXY(3, 43.0);
    $pdf->Cell($pageW - 6, 4, $safeText($position, 70), 0, 0, 'C', false, '', 1, false, 'T', 'M', true);

    $factY = 48.0;
    $factW = 22.3;
    $factH = 11.5;
    $factX1 = 3.8;
    $factX2 = $pageW - $factX1 - $factW;
    foreach ([[$factX1, 'DATE HIRED', (string)($staff['date_hired'] ?? ''), max(8,min(18,(int)($staff['id_date_font_size'] ?? 10)))], [$factX2, 'ID NUMBER', (string)($staff['staff_id'] ?? ''), max(8,min(18,(int)($staff['id_number_font_size'] ?? 10)))]] as [$x, $label, $value, $fontPx]) {
        $pdf->SetFillColor(244, 236, 223);
        $pdf->RoundedRect($x, $factY, $factW, $factH, 1.5, '1111', 'F');
        $pdf->SetTextColor(122, 75, 42);
        $pdf->SetFont('helvetica', 'B', min(10, $fontPx * 0.72));
        $pdf->SetXY($x + 0.8, $factY + 1.2);
        $pdf->Cell($factW - 1.6, 3, $label, 0, 0, 'C', false, '', 1, false, 'T', 'M', true);
        $pdf->SetTextColor(52, 37, 28);
        $pdf->SetFont('helvetica', '', 7.2);
        $pdf->SetXY($x + 0.8, $factY + 5.3);
        $pdf->Cell($factW - 1.6, 4, $value !== '' ? $safeText($value, 30) : '—', 0, 0, 'C', false, '', 1, false, 'T', 'M', true);
    }
    $cardNo = (string)($staff['staff_id'] ?? '');
    if ($cardNo !== '') {
        $pdf->write2DBarcode($cardNo, 'QRCODE,M', ($pageW - 14) / 2, 61.5, 14, 14, ['border'=>0,'vpadding'=>0,'hpadding'=>0,'fgcolor'=>[0,0,0],'bgcolor'=>false,'module_width'=>1,'module_height'=>1], 'N');
        $pdf->SetTextColor(122, 75, 42);
        $pdf->SetFont('helvetica', 'B', 5.5);
        $pdf->SetXY(5, 76.1);
        $pdf->Cell($pageW - 10, 3, 'STAFF ID', 0, 0, 'C');
    }
    $pdf->SetDrawColor(213, 193, 164);
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect(0.5, 0.5, $pageW - 1, $pageH - 1, 2, '1111', 'D');

    // Back of card
    $pdf->AddPage();
    $drawBackground();
    $drawHeader();
    $pdf->SetTextColor(91, 70, 55);
    $pdf->SetFillColor(244, 236, 223);
    $pdf->SetDrawColor(184, 137, 80);
    $pdf->SetLineWidth(0.8);
    $pdf->Rect(4, 13, 1.2, 16, 'F');
    $pdf->SetFont('helvetica', '', 6.6);
    $pdf->SetXY(7, 13.2);
    $pdf->MultiCell($pageW - 11, 15, 'If found, please return this card to the Food Traps Cafe Human Resources Department at Nearest Branch or call 09162642431.', 0, 'L', false, 1, '', '', true, 0, false, true, 15, 'T', false);
    $pdf->SetTextColor(122, 75, 42);
    $pdf->SetFont('helvetica', 'B', 6.8);
    $pdf->SetXY(4, 31);
    $pdf->Cell($pageW - 8, 4, 'TERMS AND DISCLAIMERS', 0, 0, 'C');
    $pdf->SetTextColor(52, 37, 28);
    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->SetXY(4, 35.5);
    $pdf->MultiCell($pageW - 8, 7, 'This card remains the property of Food Traps Cafe and must be surrendered upon request.', 0, 'L', false, 1, '', '', true, 0, false, true, 7, 'T', false);
    $note = $safeText($staff['id_back_note'] ?? '', 500);
    if ($note !== '') {
        $pdf->SetTextColor(52, 37, 28);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY(4, 44);
        $pdf->MultiCell($pageW - 8, 18, $note, 0, 'L', false, 1, '', '', true, 0, false, true, 18, 'T', false);
    }
    $pdf->SetDrawColor(213, 193, 164);
    $pdf->Line(4, 68, $pageW - 4, 68);
    $pdf->SetTextColor(122, 75, 42);
    $pdf->SetFont('helvetica', 'B', 6.5);
    $pdf->SetXY(4, 70);
    $pdf->Cell($pageW - 8, 4, 'EMERGENCY CONTACT PERSON', 0, 0, 'C');
    $pdf->SetTextColor(52, 37, 28);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY(4, 74);
    $pdf->Cell($pageW - 8, 4, $safeText($staff['emergency_contact_name'] ?? '', 70) ?: '—', 0, 0, 'C', false, '', 1, false, 'T', 'M', true);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY(4, 78.5);
    $pdf->Cell($pageW - 8, 4, $safeText($staff['emergency_contact_number'] ?? '', 40) ?: '—', 0, 0, 'C', false, '', 1, false, 'T', 'M', true);
    $pdf->SetDrawColor(213, 193, 164);
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect(0.5, 0.5, $pageW - 1, $pageH - 1, 2, '1111', 'D');

    $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($staff['staff_id'] ?: $staff['username'])) . '-Staff-ID.pdf';
    $bytes = $pdf->Output($filename, 'S');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
} catch (Throwable $e) {
    pdfError('Could not generate the Staff ID PDF. ' . $e->getMessage(), 500);
}
