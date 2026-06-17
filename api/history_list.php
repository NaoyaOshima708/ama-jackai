<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$config = require dirname(__DIR__) . '/includes/config.php';
$deviceId = trim((string)($_REQUEST['device_id'] ?? ''));
if (!preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $deviceId)) { http_response_code(400); echo json_encode(['error' => 'device_id が不正です']); exit; }

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(503); echo json_encode(['error' => 'DB接続失敗']); exit;
}

$stmt = $pdo->prepare('
    SELECT s.id, s.asin, s.product_name, s.condition_type, s.buy_price, s.title,
           s.created_at, s.updated_at,
           (SELECT content FROM chat_messages WHERE session_id=s.id AND role="assistant" ORDER BY id DESC LIMIT 1) AS last_reply
    FROM chat_sessions s
    WHERE s.device_id=?
    ORDER BY s.updated_at DESC
    LIMIT 100
');
$stmt->execute([$deviceId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['id']        = (int)$r['id'];
    $r['buy_price'] = $r['buy_price'] !== null ? (int)$r['buy_price'] : null;
    // last_reply を短く切る
    if ($r['last_reply']) {
        $r['last_reply'] = mb_substr(strip_tags($r['last_reply']), 0, 60);
    }
}

echo json_encode(['ok' => true, 'sessions' => $rows], JSON_UNESCAPED_UNICODE);
