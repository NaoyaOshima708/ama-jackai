<?php
declare(strict_types=1);

require_once __DIR__ . '/llm_client.php';

/**
 * グラフスクリーンショットを Vision LLM で読み取り、構造化テキストに変換する。
 * OCR は使わない（Vision モデルがグラフ形状＋画面上の文字を直接読む）。
 */
function vision_read_graph_screenshot(array $config, string $imageBase64, string $mime = 'image/jpeg'): string
{
    $model = $config['model_vision'] ?? 'llava-phi3';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $mime = 'image/jpeg';
    }

    $dataUrl = 'data:' . $mime . ';base64,' . $imageBase64;

    $messages = [
        [
            'role' => 'system',
            'content' => <<<'SYS'
あなたは Ama-Jack アプリの商品グラフ画面を読み取る専門AIです。
仕入れ判断はしません。画像に実際に見える情報だけを書きます。
見えない・判別できない項目は必ず「読み取れない」と書き、推測や想像で補完しないでください。
SYS,
        ],
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => <<<'USER'
この画像は Ama-Jack の商品グラフ画面（価格推移・出品者数・ランキング）のスクリーンショットです。
次の形式で、見えている情報だけを出力してください。

【価格推移グラフ】
・トレンド:（上昇 / 安定 / 下降 / 読み取れない）
・変動:（激しい / 普通 / 穏やか / 読み取れない）

【出品者数グラフ】
・トレンド:（増加 / 安定 / 減少 / 読み取れない）

【ランキンググラフ】
・波の頻度:（頻繁 / たまに / ほぼなし / 読み取れない）

【画面上の数値・文字】
・読める数値やラベルがあれば列挙（価格、出品者数、FBA数、ランキング、販売見込み、商品名など）
・読めない場合は「数値は読み取れない」と書く
USER,
                ],
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => $dataUrl],
                ],
            ],
        ],
    ];

    return llm_chat_completion($config, $model, $messages);
}
