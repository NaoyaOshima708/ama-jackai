<?php
require_once __DIR__ . '/config.php';

// LINEログイン未設定の場合はトップへ
if (LINE_LOGIN_CHANNEL_ID === 'YOUR_LINE_LINE_CHANNEL_ID' || LINE_LOGIN_CHANNEL_SECRET === 'YOUR_LINE_CHANNEL_SECRET') {
    header('Location: ' . SITE_URL . '/');
    exit;
}

// CSRF対策のstateを発行
$state = bin2hex(random_bytes(16));
$_SESSION['line_login_state'] = $state;

$params = [
    'response_type' => 'code',
    'client_id'     => LINE_LOGIN_CHANNEL_ID,
    'redirect_uri'  => LINE_LOGIN_REDIRECT_URI,
    'state'         => $state,
    'scope'         => 'openid profile',
    // ログイン画面で公式アカウントの友だち追加を促す
    // 'aggressive' = 友だち追加チェックボックスをONにした状態で表示
    // 'normal'     = チェックボックスをOFFにした状態で表示（任意）
    'bot_prompt'    => 'aggressive',
];

$authUrl = 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
