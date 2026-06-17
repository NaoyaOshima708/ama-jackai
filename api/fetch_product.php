<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$asin = trim((string) ($body['asin'] ?? ''));
if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
    http_response_code(400);
    echo json_encode(['error' => 'ASINの形式が不正です（例: B0C1MC3SL9）']);
    exit;
}

$apiUrl = 'http://staging.ec-jack.com/php/keepa_api/keepaGetItemJS20240710_2.php'
        . '?keyword=' . urlencode($asin);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);

$response = curl_exec($ch);
$errno    = curl_errno($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0 || $response === false) {
    http_response_code(502);
    echo json_encode(['error' => '商品データの取得に失敗しました。時間をおいて再試行してください。']);
    exit;
}

if ($httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['error' => '商品データAPI エラー (HTTP ' . $httpCode . ')']);
    exit;
}

// UTF-8 BOM を除去してからデコード
$response = ltrim($response, "\xef\xbb\xbf");
$data = json_decode($response, true);
if (!is_array($data) || ($data['result'] ?? '') !== 'OK') {
    http_response_code(404);
    echo json_encode(['error' => 'ASINが見つかりませんでした。正しいASINか確認してください。']);
    exit;
}

echo json_encode([
    'ok'           => true,
    'product_name' => $data['shiire_title']    ?? '',
    'asin'         => $data['shiire_asin']     ?? $asin,
    'category'     => $data['shiire_category'] ?? '',
    'rank'         => (int) ($data['shiire_rank'] ?? 0),
    'new_price'    => (int) ($data['shiire_new']  ?? 0),
    'product_data' => $data,
], JSON_UNESCAPED_UNICODE);
