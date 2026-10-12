<?php
declare(strict_types=1);
$content = trim((string)($_GET['text'] ?? $_GET['id'] ?? ''));
if ($content === '' || strlen($content) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $content)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid QR content.');
}
require_once __DIR__ . '/lib/tcpdf-qrcode.php';
$qr = new QRcode($content, 'M');
$matrix = $qr->getBarcodeArray()['bcode'] ?? [];
if (!$matrix) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Could not generate QR code.');
}
$size = count($matrix);
$quiet = 4;
$path = [];
foreach ($matrix as $y => $row) {
    foreach ($row as $x => $dark) {
        if ($dark) $path[] = 'M' . ($x + $quiet) . ',' . ($y + $quiet) . 'h1v1h-1z';
    }
}
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: private, max-age=3600');
$view = $size + 2 * $quiet;
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $view . ' ' . $view . '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="' . implode('', $path) . '" fill="#000"/></svg>';
