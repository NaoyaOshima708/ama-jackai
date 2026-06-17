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
if (!is_array($body)) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

$deviceId = trim((string)($body['device_id'] ?? ''));
if (!preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $deviceId)) { http_response_code(400); echo json_encode(['error' => 'device_id が不正です']); exit; }

$messages = $body['messages'] ?? [];
if (!is_array($messages) || count($messages) === 0) { http_response_code(400); echo json_encode(['error' => 'messages が空です']); exit; }

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(503); echo json_encode(['error' => 'DB接続失敗']); exit;
}

$sessionId   = isset($body['session_id']) ? (int)$body['session_id'] : 0;
$asin        = mb_substr(trim((string)($body['asin']         ?? '')), 0, 16);
$productName = mb_substr(trim((string)($body['product_name'] ?? '')), 0, 255);
$condition   = mb_substr(trim((string)($body['condition']    ?? '')), 0, 20);
$buyPrice    = isset($body['buy_price']) ? (int)$body['buy_price'] : null;
$title       = $productName ?: ($asin ?: '新しいチャット');

if ($sessionId > 0) {
    // 既存セッション更新
    $stmt = $pdo->prepare('SELECT id FROM chat_sessions WHERE id=? AND device_id=?');
    $stmt->execute([$sessionId, $deviceId]);
    if (!$stmt->fetch()) { http_response_code(403); echo json_encode(['error' => '不正なセッション']); exit; }
    $pdo->prepare('UPDATE chat_sessions SET updated_at=NOW() WHERE id=?')->execute([$sessionId]);
} else {
    // 新規セッション作成
    $stmt = $pdo->prepare('INSERT INTO chat_sessions (device_id,asin,product_name,condition_type,buy_price,title) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$deviceId, $asin ?: null, $productName ?: null, $condition ?: null, $buyPrice, $title]);
    $sessionId = (int)$pdo->lastInsertId();
}

// メッセージ保存（既存を全削除して入れ直し）
$pdo->prepare('DELETE FROM chat_messages WHERE session_id=?')->execute([$sessionId]);
$ins = $pdo->prepare('INSERT INTO chat_messages (session_id,role,content) VALUES (?,?,?)');
foreach ($messages as $m) {
    if (!is_array($m)) continue;
    $role    = in_array($m['role'] ?? '', ['user','assistant'], true) ? $m['role'] : null;
    $content = mb_substr(trim((string)($m['content'] ?? '')), 0, 20000);
    if (!$role || $content === '') continue;
    $ins->execute([$sessionId, $role, $content]);
}

echo json_encode(['ok' => true, 'session_id' => $sessionId], JSON_UNESCAPED_UNICODE);
