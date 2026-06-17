<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

$config = require dirname(__DIR__) . '/includes/config.php';
$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);

$deviceId  = trim((string)($body['device_id']  ?? ''));
$sessionId = (int)($body['session_id'] ?? 0);
if (!preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $deviceId) || $sessionId <= 0) {
    http_response_code(400); echo json_encode(['error' => 'パラメータ不正']); exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(503); echo json_encode(['error' => 'DB接続失敗']); exit;
}

$stmt = $pdo->prepare('DELETE FROM chat_sessions WHERE id=? AND device_id=?');
$stmt->execute([$sessionId, $deviceId]);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
