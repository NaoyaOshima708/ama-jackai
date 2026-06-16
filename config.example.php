<?php
/**
 * グローバル設定（テンプレート）
 *
 * 使い方:
 *   cp config.example.php config.php
 *   config.php を開いて各値を本番用に設定してください。
 *   config.php は .gitignore により Git には含まれません。
 */

// セッション開始（全ページ共通）— 30日間有効
if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = 60 * 60 * 24 * 30; // 30日
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,   // HTTPS必須
        'httponly' => true,   // JSからアクセス不可
        'samesite' => 'Lax',
    ]);
    session_start();
}

// DB接続設定
define('DB_HOST',    'localhost');
define('DB_NAME',    'ml_db');
define('DB_USER',    'root');
define('DB_PASS',    'YOUR_DB_PASSWORD');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', '株価予測AI');
define('SITE_URL',  'https://aisample.maspis.com');

// ===== LINEログイン設定 =====
define('LINE_LOGIN_CHANNEL_ID',     'YOUR_LINE_CHANNEL_ID');
define('LINE_LOGIN_CHANNEL_SECRET', 'YOUR_LINE_CHANNEL_SECRET');
define('LINE_LOGIN_REDIRECT_URI',   SITE_URL . '/line_callback.php');

// ===== LINE Messaging API設定 =====
define('LINE_MESSAGING_ACCESS_TOKEN', 'YOUR_LINE_MESSAGING_ACCESS_TOKEN');

// Messaging APIチャネルのLINE公式アカウントID（@から始まるID）
// LINE Official Account Manager → アカウント設定 → 基本情報 で確認
define('LINE_OA_BASIC_ID', 'YOUR_LINE_OA_BASIC_ID');  // 例: @abc1234 → 'abc1234'

// ===== DB接続 =====
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// ===== LINE Messaging API: プッシュメッセージ送信 =====
/**
 * 指定したLINEユーザーIDにテキストメッセージを送信する
 *
 * @param string $userId  送信先のLINEユーザーID（Uから始まる文字列）
 * @param string $text    送信するメッセージ本文
 * @return bool           送信成功時 true、失敗時 false
 */
function sendLineMessage(string $userId, string $text): bool {
    $url = 'https://api.line.me/v2/bot/message/push';

    $payload = json_encode([
        'to'       => $userId,
        'messages' => [['type' => 'text', 'text' => $text]],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LINE_MESSAGING_ACCESS_TOKEN,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 200番台なら成功
    return ($httpCode >= 200 && $httpCode < 300);
}

// ===== LINE Messaging API: 友だち追加ボタン用URLを生成 =====
/**
 * LINE公式アカウントの友だち追加URLを返す
 * （Messaging APIチャネルのベーシックIDが必要）
 *
 * @param string $lineId  @から始まるLINE公式アカウントID（例: @abc1234）
 * @return string
 */
function getLineAddFriendUrl(string $lineId): string {
    return 'https://line.me/R/ti/p/' . urlencode($lineId);
}
