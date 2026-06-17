<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$configPath = dirname(__DIR__) . '/includes/config.php';
if (!is_readable($configPath)) {
    http_response_code(503);
    echo json_encode(['error' => '設定ファイルがありません。']);
    exit;
}

/** @var array $config */
$config = require $configPath;

require_once dirname(__DIR__) . '/includes/llm_client.php';
require_once dirname(__DIR__) . '/includes/rag_store.php';
require_once dirname(__DIR__) . '/includes/vision_reader.php';
require_once dirname(__DIR__) . '/includes/scorer.php';

$provider = $config['provider'] ?? 'openai';
if ($provider === 'openai') {
    $key = $config['openai_api_key'] ?? '';
    if ($key === '' || str_starts_with($key, 'sk-...')) {
        http_response_code(503);
        echo json_encode(['error' => 'OpenAI API キーが設定されていません。']);
        exit;
    }
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$mode = $body['mode'] ?? 'consult';
if (!in_array($mode, ['purchase', 'consult'], true)) {
    $mode = 'consult';
}

$messagesIn = $body['messages'] ?? [];
if (!is_array($messagesIn) || count($messagesIn) > 40) {
    http_response_code(400);
    echo json_encode(['error' => 'messages が不正か、長すぎます。']);
    exit;
}

$buyPrice  = isset($body['buy_price']) ? (int) $body['buy_price'] : 0;
$validConditions = ['新品', '中古-ほぼ新品', '中古-非常に良い', '中古-良い', '中古-可'];
$condition = in_array($body['condition'] ?? '', $validConditions, true)
    ? (string) $body['condition']
    : '新品';

$imageBase64 = $body['image'] ?? null;
if ($imageBase64 !== null && !is_string($imageBase64)) {
    http_response_code(400);
    echo json_encode(['error' => 'image の形式が不正です。']);
    exit;
}

if ($imageBase64 !== null && strlen($imageBase64) > 12 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => '画像データが大きすぎます（12MB 以下）。']);
    exit;
}

$sessionId = rag_sanitize_session_id($body['session_id'] ?? null);

$purchaseHasPriorReply = false;
if ($mode === 'purchase') {
    foreach ($messagesIn as $m) {
        if (is_array($m) && ($m['role'] ?? '') === 'assistant') {
            $purchaseHasPriorReply = true;
            break;
        }
    }
}

$promptDir = dirname(__DIR__) . '/includes/prompts';
$purchasePromptFile = $purchaseHasPriorReply
    ? $promptDir . '/purchase_followup.php'
    : $promptDir . '/purchase_initial.php';
$systemPurchase = is_readable($purchasePromptFile)
    ? (string) require $purchasePromptFile
    : 'あなたはAma-Jackの仕入れ判断AIアドバイザーです。';

$systemConsult = <<<'SYS'
あなたはツール「Ama-Jack」の使い方・設定・画面操作に関する相談に答えるアシスタントです。
公式: https://ama-jack.com/manual/
SYS;

$system = $mode === 'purchase' ? $systemPurchase : $systemConsult;

$ragEnabled = !empty($config['rag_enabled']) && $sessionId !== null;
$ragStore = null;

if ($ragEnabled) {
    try {
        $ragStore = new RagStore($config);
        $ragQueryText = '';
        foreach (array_reverse($messagesIn) as $m) {
            if (is_array($m) && ($m['role'] ?? '') === 'user' && is_string($m['content'] ?? null)) {
                $ragQueryText = trim((string) $m['content']);
                break;
            }
        }
        if ($ragQueryText === '' && $imageBase64) {
            $ragQueryText = '商品グラフの仕入れ判断';
        }
        if ($ragQueryText !== '') {
            $qv = $ragStore->embed($ragQueryText);
            if ($qv !== null) {
                $ctx = $ragStore->searchContext(
                    $sessionId,
                    $qv,
                    (int) ($config['rag_top_k'] ?? 4),
                    (int) ($config['rag_max_scan'] ?? 800)
                );
                if ($ctx !== '') {
                    $system .= "\n\n【参考: 過去の関連メモ】\n" . $ctx;
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

$productDataJson = '';
$scorerResult = null;
if ($mode === 'purchase' && isset($body['product_data'])) {
    $pd = $body['product_data'];
    $pdArray = null;
    if (is_array($pd)) {
        $pdArray = $pd;
    } elseif (is_string($pd) && trim($pd) !== '') {
        $decoded = json_decode($pd, true);
        if (is_array($decoded)) {
            $pdArray = $decoded;
        }
    }

    if ($pdArray !== null) {
        // スコアリングエンジンで事前計算
        try {
            $scorerResult = scorer_analyze($pdArray, $buyPrice, $condition);
            $encoded = json_encode($scorerResult, JSON_UNESCAPED_UNICODE);
            $productDataJson = is_string($encoded) ? $encoded : '';
        } catch (Throwable $e) {
            // スコアリング失敗時は生データをそのまま使う
            $encoded = json_encode($pdArray, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $productDataJson = is_string($encoded) ? mb_substr($encoded, 0, 20000) : '';
        }
    } elseif (is_string($pd) && trim($pd) !== '') {
        $productDataJson = mb_substr(trim($pd), 0, 20000);
    }
}

// --- 画像読み取り（Vision LLM・OCR 不要）---
$imageReading = null;
$hasImage = $mode === 'purchase' && is_string($imageBase64) && $imageBase64 !== '';

if ($hasImage) {
    $imageMime = $body['image_mime'] ?? 'image/jpeg';
    if (!in_array($imageMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $imageMime = 'image/jpeg';
    }
    try {
        set_time_limit(300);
        $imageReading = vision_read_graph_screenshot($config, $imageBase64, $imageMime);
    } catch (Throwable $e) {
        $imageReading = '【画像読み取りエラー】' . $e->getMessage();
    }
}

$openaiMessages = [
    ['role' => 'system', 'content' => $system],
];

foreach ($messagesIn as $m) {
    if (!is_array($m)) {
        continue;
    }
    $role = $m['role'] ?? '';
    $content = $m['content'] ?? '';
    if (!in_array($role, ['user', 'assistant'], true) || !is_string($content)) {
        continue;
    }
    $openaiMessages[] = ['role' => $role, 'content' => mb_substr($content, 0, 12000)];
}

$lastIdx = count($openaiMessages) - 1;
if ($lastIdx < 1 || ($openaiMessages[$lastIdx]['role'] ?? '') !== 'user') {
    http_response_code(400);
    echo json_encode(['error' => '最後のメッセージは user である必要があります。']);
    exit;
}

// ユーザー入力に画像読み取り結果・JSON を付与
if ($mode === 'purchase') {
    $parts = [];
    if ($imageReading !== null && $imageReading !== '') {
        $parts[] = "【画像読み取り結果（Vision AI）】\n" . $imageReading;
    } elseif (!$hasImage && !$purchaseHasPriorReply) {
        $parts[] = "【画像読み取り結果】\nグラフ画像なし。グラフのトレンド・波は「読み取れない」と書いてください。";
    }
    if ($productDataJson !== '') {
        $label = $scorerResult !== null ? '【スコアリング済みデータ（PHP事前計算）】' : '【構造化データ（JSON）】';
        $parts[] = $label . "\n" . $productDataJson;
    }
    if ($parts !== []) {
        $userPart = (string) $openaiMessages[$lastIdx]['content'];
        $openaiMessages[$lastIdx]['content'] = implode("\n\n", $parts) . "\n\n【ユーザー入力】\n" . $userPart;
    }
}

// 判定はテキストモデル（画像は事前にテキスト化済み）
$model = $provider === 'ollama'
    ? ($config['model_text'] ?? 'qwen2.5:1.5b')
    : ($config['model_text'] ?? 'gpt-4o-mini');

set_time_limit(300);

try {
    $text = llm_chat_completion($config, $model, $openaiMessages);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($ragStore !== null && $sessionId !== null) {
    try {
        $lastUserPlain = '';
        foreach (array_reverse($messagesIn) as $m) {
            if (is_array($m) && ($m['role'] ?? '') === 'user' && is_string($m['content'] ?? null)) {
                $lastUserPlain = (string) $m['content'];
                break;
            }
        }
        $storeBody = "【質問】\n" . $lastUserPlain . "\n\n【回答】\n" . $text;
        $storeVec = $ragStore->embed($storeBody);
        if ($storeVec !== null) {
            $ragStore->store($sessionId, $storeBody, $storeVec);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

$response = ['reply' => $text];
if (!empty($config['include_image_reading']) && $imageReading !== null) {
    $response['image_reading'] = $imageReading;
}
if ($scorerResult !== null) {
    $response['score'] = $scorerResult;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
