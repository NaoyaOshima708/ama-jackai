<?php
declare(strict_types=1);

$configOk = is_readable(__DIR__ . '/includes/config.php');
$configured = false;
$isOllama = false;
if ($configOk) {
    $c = require __DIR__ . '/includes/config.php';
    $isOllama = (($c['provider'] ?? 'openai') === 'ollama');
    if ($isOllama) {
        $configured = true;
    } else {
        $key = $c['openai_api_key'] ?? '';
        $configured = $key !== '' && !str_starts_with($key, 'sk-...');
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ama-Jack相談チャット｜せどり相談</title>
  <link rel="stylesheet" href="/assets/chat.css?v=7">
</head>
<body>
  <div class="app">
    <header>
      <p class="eyebrow">せどり相談</p>
      <h1>Ama-Jack相談チャット</h1>
      <p class="sub">ASINを入力するだけで仕入れ判断できます。グラフ画像を追加すると精度が上がります。</p>
    </header>

    <?php if ($isOllama): ?>
    <div class="banner">
      <strong>自前 LLM モード（Ollama）</strong> —
      画像読み取り: <code>llava-phi3</code> / 判定: <code>qwen2.5:1.5b</code> /
      埋め込み: <code>nomic-embed-text</code>
    </div>
    <?php elseif (!$configured): ?>
    <div class="banner error">
      <strong>セットアップが必要です。</strong>
      <code>includes/config.example.php</code> を <code>includes/config.php</code> にコピーしてください。
    </div>
    <?php endif; ?>

    <div class="mode-tabs">
      <button type="button" id="mode-purchase" class="active">仕入れ判断</button>
      <button type="button" id="mode-consult">Ama-Jackの使い方相談</button>
    </div>

    <div class="chat-panel">
      <div class="messages" id="messages">
        <div class="empty-hint">
          「仕入れ判断」では Ama-Jack のグラフ画面のスクリーンショットを添付してください。Vision AI がグラフを読み取ってから判定します。
        </div>
      </div>
      <form class="composer" id="chat-form">
        <div class="image-row" id="image-row">
          <input type="file" id="image-input" accept="image/jpeg,image/png,image/webp">
          <img id="image-preview" class="image-preview" alt="">
          <button type="button" class="btn-clear-img" id="clear-image">画像をクリア</button>
        </div>
        <div class="text-row">
          <textarea id="message-input" placeholder="仕入れ価格など（画像のみでも可）"></textarea>
          <button type="submit" class="send" id="send-btn">送信</button>
        </div>
      </form>
    </div>

    <p class="manual-link">
      <a href="https://ama-jack.com/manual/" target="_blank" rel="noopener noreferrer">Ama-Jack マニュアル</a>
    </p>

    <footer class="disclaimer">
      <strong>免責事項:</strong>
      本サービスの回答は参考情報です。最終判断はご自身の責任で行ってください。
    </footer>
  </div>
  <script src="/assets/chat.js?v=6" defer></script>
</body>
</html>
