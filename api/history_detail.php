<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$config = require dirname(__DIR__) . '/includes/config.php';
$deviceId  = trim((string)($_REQUEST['device_id']  ?? ''));
$sessionId = (int)($_REQUEST['session_id'] ?? 0);
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

$stmt = $pdo->prepare('SELECT * FROM chat_sessions WHERE id=? AND device_id=?');
$stmt->execute([$sessionId, $deviceId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) { http_response_code(404); echo json_encode(['error' => 'セッションが見つかりません']); exit; }

$stmt = $pdo->prepare('SELECT role, content, created_at FROM chat_messages WHERE session_id=? ORDER BY id ASC');
$stmt->execute([$sessionId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$session['id']        = (int)$session['id'];
$session['buy_price'] = $session['buy_price'] !== null ? (int)$session['buy_price'] : null;

echo json_encode(['ok' => true, 'session' => $session, 'messages' => $messages], JSON_UNESCAPED_UNICODE);
