<?php
require_once __DIR__ . '/config.php';

// ===== ログイン状態確認 =====
$lineUser   = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

$pdo = getPDO();

// ===== URLパラメータで日付指定（省略時は最新）=====
$requestDate = $_GET['date'] ?? null;
if ($requestDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
    $requestDate = null;
}

// ===== 最新 or 指定日の見通しを取得 =====
if ($requestDate) {
    $stmt = $pdo->prepare("SELECT * FROM market_outlook WHERE outlook_date = :d");
    $stmt->execute([':d' => $requestDate]);
} else {
    $stmt = $pdo->query("SELECT * FROM market_outlook ORDER BY outlook_date DESC LIMIT 1");
}
$outlook = $stmt->fetch();

// ===== 過去一覧（最新20件）=====
$archiveList = $pdo->query("
    SELECT outlook_date, title, market_signal
    FROM market_outlook
    ORDER BY outlook_date DESC
    LIMIT 20
")->fetchAll();

// ===== SEO =====
$pageTitle       = '本日の株価見通し';
$pageDescription = 'AIが毎日生成する日本株の株価見通し。相場環境・注目銘柄・リスク要因を毎営業日の朝に更新。';
$canonicalUrl    = SITE_URL . '/outlook.php';

$signalLabel = ['bullish' => '強気 ▲', 'bearish' => '弱気 ▼', 'neutral' => '中立 ─'];
$signalClass = ['bullish' => 'bullish', 'bearish' => 'bearish', 'neutral' => 'neutral'];

include __DIR__ . '/includes/header.php';
?>

<!-- ===== タブナビゲーション ===== -->
<div class="tab-nav">
  <a href="<?= SITE_URL ?>/" class="tab-btn">
    <span class="tab-icon">&#9650;</span> 上昇ランキング
  </a>
  <a href="<?= SITE_URL ?>/down_ranking.php" class="tab-btn">
    <span class="tab-icon tab-down">&#9660;</span> 下落ランキング
  </a>
  <a href="<?= SITE_URL ?>/outlook.php" class="tab-btn active tab-btn-outlook">
    <span class="tab-icon">&#128202;</span> 株価見通し
  </a>
  <a href="<?= SITE_URL ?>/news.php" class="tab-btn">
    <span class="tab-icon">&#127758;</span> 情勢・ニュース
  </a>
  <a href="<?= SITE_URL ?>/earnings.php" class="tab-btn">
    <span class="tab-icon">&#128200;</span> 決算カレンダー
  </a>
</div>

<?php if (!$isLoggedIn): ?>
<!-- ===== 未ログイン時: ログイン誘導画面 ===== -->
<div class="detail-login-cta">
  <div class="detail-login-cta-icon">&#128202;</div>
  <h2 class="detail-login-cta-title">株価見通しを確認する</h2>
  <p class="detail-login-cta-text">
    LINEでログインすると、毎日更更される「本日の株価見通し」を確認できます。<br>
    AIが予測データ・ニュースセンチメントを分析し、相場全体の方向感や注目銘柄を毎期お知らせします。
  </p>
  <a href="<?= SITE_URL ?>/line_login.php" class="detail-login-cta-btn">
    <span class="detail-login-cta-btn-icon">LINE</span>
    LINEでログインして見通しを確認
  </a>
  <p class="detail-login-cta-note">無料でログインできます。毎日LINEに見通しもお届けします。</p>
</div>
<?php else: ?>
<?php if ($outlook): ?>
<?php
  $sig   = $outlook['market_signal'] ?? 'neutral';
  $date  = $outlook['outlook_date'];
  $dateJ = date('Y年n月j日（D）', strtotime($date));
  // 曜日を日本語に
  $dayMap = ['Mon'=>'月','Tue'=>'火','Wed'=>'水','Thu'=>'木','Fri'=>'金','Sat'=>'土','Sun'=>'日'];
  $dateJ = str_replace(array_keys($dayMap), array_values($dayMap), $dateJ);
?>

<!-- ===== ヒーローセクション ===== -->
<div class="outlook-hero">
  <div class="outlook-hero-date"><?= htmlspecialchars($dateJ) ?> の見通し</div>
  <h1><?= htmlspecialchars($outlook['title']) ?></h1>
  <p class="outlook-hero-summary">
    <?= nl2br(htmlspecialchars(mb_substr($outlook['line_message'] ?? '', 0, 120))) ?>
  </p>
</div>

<!-- ===== シグナルカード ===== -->
<div class="outlook-signal-grid">
  <div class="outlook-signal-card">
    <div class="outlook-signal-label">市場シグナル</div>
    <div class="outlook-signal-value <?= $signalClass[$sig] ?? 'neutral' ?>">
      <?= $signalLabel[$sig] ?? '中立 ─' ?>
    </div>
  </div>
  <?php if ($outlook['nikkei_signal']): ?>
  <div class="outlook-signal-card">
    <div class="outlook-signal-label">日経平均</div>
    <div class="outlook-signal-value neutral"><?= htmlspecialchars($outlook['nikkei_signal']) ?></div>
  </div>
  <?php endif; ?>
  <?php if ($outlook['topix_signal']): ?>
  <div class="outlook-signal-card">
    <div class="outlook-signal-label">TOPIX</div>
    <div class="outlook-signal-value neutral"><?= htmlspecialchars($outlook['topix_signal']) ?></div>
  </div>
  <?php endif; ?>
  <?php if ($outlook['usdjpy_signal']): ?>
  <div class="outlook-signal-card">
    <div class="outlook-signal-label">ドル円</div>
    <div class="outlook-signal-value neutral"><?= htmlspecialchars($outlook['usdjpy_signal']) ?></div>
  </div>
  <?php endif; ?>
  <?php if ($outlook['top_tickers']): ?>
  <?php
    $tickerList = array_filter(array_map('trim', explode(',', $outlook['top_tickers'])));
    // stock_masterから会社名を一括取得
    $tickerNames = [];
    if (!empty($tickerList)) {
        $placeholders = implode(',', array_fill(0, count($tickerList), '?'));
        $nmStmt = $pdo->prepare("SELECT ticker, company_name FROM stock_master WHERE ticker IN ({$placeholders})");
        $nmStmt->execute($tickerList);
        foreach ($nmStmt->fetchAll(PDO::FETCH_ASSOC) as $nm) {
            $tickerNames[$nm['ticker']] = $nm['company_name'];
        }
    }
  ?>
  <div class="outlook-signal-card outlook-tickers-card">
    <div class="outlook-signal-label">注目銘柄</div>
    <div class="outlook-tickers-list">
      <?php foreach ($tickerList as $tk): ?>
      <a href="<?= SITE_URL ?>/detail.php?ticker=<?= urlencode($tk) ?>" class="outlook-ticker-badge">
        <span class="otb-code"><?= htmlspecialchars($tk) ?></span>
        <?php if (!empty($tickerNames[$tk])): ?>
        <span class="otb-name"><?= htmlspecialchars($tickerNames[$tk]) ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== 詳細本文 ===== -->
<div class="outlook-section">
  <h2>&#128203; 本日の詳細見通し</h2>
  <div class="outlook-section-body"><?= nl2br(htmlspecialchars($outlook['detail_body'])) ?></div>
</div>



<?php else: ?>
<div class="empty-state">
  <div class="empty-icon">&#128202;</div>
  <p>まだ見通しデータがありません。<br>generate_outlook.py を実行してください。</p>
</div>
<?php endif; ?>
<?php endif; ?> <!-- /isLoggedIn -->

<!-- ===== 過去の見通しアーカイブ ===== -->
<?php if ($isLoggedIn && !empty($archiveList)): ?>
<div class="outlook-archive">
  <div class="outlook-archive-header">&#128197; 過去の見通し</div>
  <ul class="outlook-archive-list">
    <?php foreach ($archiveList as $item):
      $isSig   = $item['market_signal'] ?? 'neutral';
      $isSigLbl = $signalLabel[$isSig] ?? '中立';
      $isSigCls = $signalClass[$isSig] ?? 'neutral';
      $iDate   = $item['outlook_date'];
      $iDateJ  = date('Y年n月j日', strtotime($iDate));
      $isActive = ($outlook && $iDate === $outlook['outlook_date']) ? ' style="background:var(--bg-card-hover);"' : '';
    ?>
    <li class="outlook-archive-item">
      <a href="<?= SITE_URL ?>/outlook.php?date=<?= urlencode($iDate) ?>"
         class="outlook-archive-link"<?= $isActive ?>>
        <span class="outlook-archive-date"><?= $iDateJ ?></span>
        <span class="outlook-archive-title"><?= htmlspecialchars($item['title']) ?></span>
        <span class="outlook-archive-signal <?= $isSigCls ?>"><?= $isSigLbl ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
