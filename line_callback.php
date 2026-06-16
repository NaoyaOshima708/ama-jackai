<?php
require_once __DIR__ . '/config.php';

// エラー時はトップにリダイレクトし、簡単なフラグだけ残す
function redirect_with_error(string $code): void {
    $_SESSION['line_login_error'] = $code;
    header('Location: ' . SITE_URL . '/');
    exit;
}

// state検証
if (empty($_GET['state']) || empty($_SESSION['line_login_state']) || $_GET['state'] !== $_SESSION['line_login_state']) {
    redirect_with_error('invalid_state');
}
unset($_SESSION['line_login_state']);

if (!isset($_GET['code'])) {
    redirect_with_error('no_code');
}

$code = $_GET['code'];

// ===== アクセストークン取得 =====
$tokenUrl = 'https://api.line.me/oauth2/v2.1/token';
$postData = [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => LINE_LOGIN_REDIRECT_URI,
    'client_id'     => LINE_LOGIN_CHANNEL_ID,
    'client_secret' => LINE_LOGIN_CHANNEL_SECRET,
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    redirect_with_error('token_error');
}

$tokenData = json_decode($response, true);
if (!is_array($tokenData) || empty($tokenData['access_token'])) {
    redirect_with_error('token_parse_error');
}

$accessToken = $tokenData['access_token'];

// ===== プロフィール取得 =====
$ch = curl_init('https://api.line.me/v2/profile');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$profileRes  = curl_exec($ch);
$profileCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($profileCode !== 200 || !$profileRes) {
    redirect_with_error('profile_error');
}

$profile = json_decode($profileRes, true);
if (!is_array($profile) || empty($profile['userId'])) {
    redirect_with_error('profile_parse_error');
}

$userId      = $profile['userId'];
$displayName = $profile['displayName'] ?? '';
$pictureUrl  = $profile['pictureUrl']  ?? '';

// ===== DBにユーザー情報を保存（初回登録 or 更新） =====
$isNewUser = false;
try {
    $pdo = getPDO();

    // line_usersテーブルが存在しない場合は作成
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_users (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            line_user_id VARCHAR(64)  NOT NULL UNIQUE,
            display_name VARCHAR(255) NOT NULL DEFAULT '',
            picture_url  TEXT,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 既存ユーザーか確認
    $stmt = $pdo->prepare("SELECT id FROM line_users WHERE line_user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        // 既存ユーザー: プロフィール更新 + last_login更新
        $stmt = $pdo->prepare("
            UPDATE line_users
            SET display_name = :name,
                picture_url  = :pic,
                last_login   = NOW()
            WHERE line_user_id = :uid
        ");
        $stmt->execute([
            ':name' => $displayName,
            ':pic'  => $pictureUrl,
            ':uid'  => $userId,
        ]);
    } else {
        // 新規ユーザー: 登録
        $stmt = $pdo->prepare("
            INSERT INTO line_users (line_user_id, display_name, picture_url)
            VALUES (:uid, :name, :pic)
        ");
        $stmt->execute([
            ':uid'  => $userId,
            ':name' => $displayName,
            ':pic'  => $pictureUrl,
        ]);
        $isNewUser = true;
    }
} catch (Exception $e) {
    // DBエラーはログに記録するが、ログイン自体は続行
    error_log('line_callback DB error: ' . $e->getMessage());
}

// ===== ログイン情報をセッションに保存 =====
$_SESSION['line_user'] = [
    'user_id'      => $userId,
    'display_name' => $displayName,
    'picture_url'  => $pictureUrl,
];

// ===== 新規ユーザーにウェルカムメッセージを送信 =====
if ($isNewUser) {
    $welcomeText =
        $displayName . " さん、ご登録ありがとうございます！\n\n" .
        "▼ AI株価予測サービスへようこそ\n" .
        "毎営業日 7:30 に東証上場銘柄の翌日予測を更新しています。\n\n" .
        "▼ 本日の予測ランキングはこちら\n" .
        SITE_URL . "/\n\n" .
        "ぜひ投資の参考にご活用ください。\n" .
        "※本サービスは投資を推奨するものではありません。";

    sendLineMessage($userId, $welcomeText);
}

// ===== トップへリダイレクト =====
header('Location: ' . SITE_URL . '/');
exit;
