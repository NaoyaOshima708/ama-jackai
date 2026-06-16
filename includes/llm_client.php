<?php
declare(strict_types=1);

/**
 * OpenAI 互換 / Ollama ネイティブでチャット完了を取得する。
 *
 * @param array<int, array{role: string, content: mixed}> $messages
 */
function llm_chat_completion(array $config, string $model, array $messages): string
{
    $provider = $config['provider'] ?? 'openai';
    if ($provider === 'ollama') {
        return llm_ollama_native_chat($config, $model, $messages);
    }

    return llm_openai_compatible_chat($config, $model, $messages);
}

/**
 * @param array<int, array{role: string, content: mixed}> $openaiStyleMessages
 */
function llm_openai_compatible_chat(array $config, string $model, array $openaiStyleMessages): string
{
    $key = $config['openai_api_key'] ?? '';
    $baseUrl = rtrim($config['openai_base_url'] ?? 'https://api.openai.com', '/');
    $url = $baseUrl . '/v1/chat/completions';

    $payload = json_encode([
        'model' => $model,
        'messages' => $openaiStyleMessages,
        'temperature' => 0.5,
        'max_tokens' => 2048,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new RuntimeException('JSON encode failed');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        throw new RuntimeException('API connection failed');
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = is_array($data) && isset($data['error']['message'])
            ? (string) $data['error']['message']
            : 'API error HTTP ' . $httpCode;
        throw new RuntimeException($msg);
    }

    $text = $data['choices'][0]['message']['content'] ?? null;
    if (!is_string($text) || $text === '') {
        throw new RuntimeException('Empty response');
    }

    return $text;
}

/**
 * Ollama /api/chat（画像付き user メッセージ対応）
 *
 * @param array<int, array{role: string, content: mixed}> $openaiStyleMessages
 */
function llm_ollama_native_chat(array $config, string $model, array $openaiStyleMessages): string
{
    $base = rtrim($config['ollama_base_url'] ?? 'http://127.0.0.1:11434', '/');
    $url = $base . '/api/chat';

    $ollamaMessages = [];
    foreach ($openaiStyleMessages as $m) {
        $role = $m['role'] ?? 'user';
        $c = $m['content'] ?? '';
        if (is_string($c)) {
            $ollamaMessages[] = ['role' => $role, 'content' => $c];
            continue;
        }
        if (is_array($c)) {
            $text = '';
            $images = [];
            foreach ($c as $part) {
                if (!is_array($part)) {
                    continue;
                }
                if (($part['type'] ?? '') === 'text') {
                    $text .= (string) ($part['text'] ?? '');
                }
                if (($part['type'] ?? '') === 'image_url') {
                    $u = $part['image_url']['url'] ?? '';
                    if (preg_match('#^data:image/[^;]+;base64,(.+)$#', (string) $u, $mm)) {
                        $images[] = $mm[1];
                    }
                }
            }
            $msg = ['role' => $role, 'content' => $text];
            if ($images !== []) {
                $msg['images'] = $images;
            }
            $ollamaMessages[] = $msg;
        }
    }

    $payload = json_encode([
        'model' => $model,
        'messages' => $ollamaMessages,
        'stream' => false,
        'options' => [
            'temperature' => 0.5,
            'num_predict' => 2048,
        ],
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new RuntimeException('JSON encode failed');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        throw new RuntimeException('Ollama に接続できません。サービス起動とモデル取得を確認してください。');
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400 || !is_array($data)) {
        $err = is_array($data) && isset($data['error'])
            ? (is_string($data['error']) ? $data['error'] : json_encode($data['error']))
            : 'Ollama error HTTP ' . $httpCode;
        throw new RuntimeException($err);
    }

    $text = $data['message']['content'] ?? null;
    if (!is_string($text) || $text === '') {
        throw new RuntimeException('Ollama から空の応答でした。');
    }

    return $text;
}
