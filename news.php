<?php
require_once __DIR__ . '/config.php';
$pageTitle = '世界・日本情勢ニュース';
$pageDescription = '世界情勢・日本経済ニュースをAIが収集・分析。株式市場への影響をセンチメントスコアで可視化。';
$canonicalUrl = SITE_URL . '/news.php';
$pdo = getPDO();

// ===== カテゴリフィルタ =====
$categoryMap = [
    'all'         => 'すべて',
    'japan'       => '日本情勢',
    'geopolitics' => '世界情勢・地政学',
    'us'          => '米国経済',
    'economy'     => 'グローバル経済',
    'general'     => '一般マーケット',
];
$cat = $_GET['cat'] ?? 'all';
if (!array_key_exists($cat, $categoryMap)) $cat = 'all';

// ===== ニュース取得 =====
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$whereClause = $cat !== 'all' ? "WHERE category = :cat" : "";

$countSql = "SELECT COUNT(*) FROM market_news $whereClause";
$countStmt = $pdo->prepare($countSql);
if ($cat !== 'all') $countStmt->bindValue(':cat', $cat);
$countStmt->execute();
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$newsSql = "SELECT * FROM market_news $whereClause ORDER BY published_at DESC LIMIT :lim OFFSET :off";
$newsStmt = $pdo->prepare($newsSql);
if ($cat !== 'all') $newsStmt->bindValue(':cat', $cat);
$newsStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$newsStmt->bindValue(':off', $offset, PDO::PARAM_INT);
$newsStmt->execute();
$newsList = $newsStmt->fetchAll();

// ===== センチメント集計（カテゴリ別・直近7日） =====
$sentimentStmt = $pdo->query("
    SELECT category,
           ROUND(AVG(sentiment), 3) AS avg_sentiment,
           COUNT(*) AS cnt
    FROM market_news
    WHERE published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND sentiment IS NOT NULL
    GROUP BY category
    ORDER BY avg_sentiment DESC
");
$sentimentByCategory = $sentimentStmt->fetchAll();

// ===== 全体センチメント（直近3日） =====
$overallSentiment = (float)$pdo->query("
    SELECT AVG(sentiment) FROM market_news
    WHERE published_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
      AND sentiment IS NOT NULL
")->fetchColumn();

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
  <a href="<?= SITE_URL ?>/outlook.php" class="tab-btn">
    <span class="tab-icon">&#128202;</span> 株価見通し
  </a>
  <a href="<?= SITE_URL ?>/news.php" class="tab-btn active">
    <span class="tab-icon">&#127758;</span> 情勢・ニュース
  </a>
  <a href="<?= SITE_URL ?>/earnings.php" class="tab-btn">
    <span class="tab-icon">&#128200;</span> 決算カレンダー
  </a>
  <a href="<?= SITE_URL ?>/nisa.php" class="tab-btn">
    <span class="tab-icon">&#127981;</span> NISA
  </a>
</div>

<!-- ===== 全体センチメントゲージ ===== -->
<div class="sentiment-overview">
  <div class="sentiment-gauge-wrap">
    <div class="sentiment-label-left">悲観</div>
    <?php
      $gaugePos = round(($overallSentiment + 1) / 2 * 100);
      $gaugePos = max(2, min(98, $gaugePos));
      $sentClass = $overallSentiment > 0.1 ? 'positive' : ($overallSentiment < -0.1 ? 'negative' : 'neutral');
    ?>
    <div class="sentiment-gauge">
      <div class="sentiment-gauge-track">
        <div class="sentiment-gauge-fill <?= $sentClass ?>" style="width:<?= $gaugePos ?>%"></div>
        <div class="sentiment-gauge-needle" style="left:<?= $gaugePos ?>%"></div>
      </div>
    </div>
    <div class="sentiment-label-right">楽観</div>
  </div>
  <div class="sentiment-score-display">
    市場センチメント（直近3日）:
    <strong class="sentiment-value <?= $sentClass ?>">
      <?= $overallSentiment >= 0 ? '+' : '' ?><?= number_format($overallSentiment, 3) ?>
    </strong>
    <span class="sentiment-word">
      <?= $overallSentiment > 0.3 ? '強気' : ($overallSentiment > 0.1 ? 'やや強気' : ($overallSentiment < -0.3 ? '弱気' : ($overallSentiment < -0.1 ? 'やや弱気' : '中立'))) ?>
    </span>
  </div>
</div>

<!-- ===== カテゴリ別センチメント ===== -->
<?php if (!empty($sentimentByCategory)): ?>
<div class="sentiment-category-grid">
  <?php foreach ($sentimentByCategory as $sc):
    $sc_val   = (float)$sc['avg_sentiment'];
    $sc_class = $sc_val > 0.1 ? 'positive' : ($sc_val < -0.1 ? 'negative' : 'neutral');
    $sc_label = $categoryMap[$sc['category']] ?? $sc['category'];
  ?>
  <div class="sentiment-cat-card">
    <div class="sentiment-cat-name"><?= htmlspecialchars($sc_label) ?></div>
    <div class="sentiment-cat-score <?= $sc_class ?>">
      <?= $sc_val >= 0 ? '+' : '' ?><?= number_format($sc_val, 3) ?>
    </div>
    <div class="sentiment-cat-count"><?= $sc['cnt'] ?>件</div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== カテゴリフィルタ ===== -->
<div class="news-filter">
  <?php foreach ($categoryMap as $key => $label): ?>
  <a href="?cat=<?= $key ?>"
     class="news-filter-btn <?= $cat === $key ? 'active' : '' ?>">
    <?= htmlspecialchars($label) ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ===== ニュース一覧 ===== -->
<div class="section-header">
  <h2 class="section-title">
    <?= htmlspecialchars($categoryMap[$cat]) ?> ニュース
  </h2>
  <span class="section-badge"><?= number_format($totalCount) ?>件</span>
</div>

<div class="news-list">
<?php if (empty($newsList)): ?>
  <div class="empty-state">
    <div class="empty-icon">📰</div>
    <p>ニュースデータがありません。<br>fetch_news_earnings.py を実行してください。</p>
  </div>
<?php else: ?>
  <?php foreach ($newsList as $news):
    $sent      = (float)($news['sentiment'] ?? 0);
    $sentClass = $sent > 0.1 ? 'positive' : ($sent < -0.1 ? 'negative' : 'neutral');
    $sentLabel = $sent > 0.1 ? '↑ ポジティブ' : ($sent < -0.1 ? '↓ ネガティブ' : '→ 中立');
    $catLabel  = $categoryMap[$news['category']] ?? $news['category'];
    $pubDate   = date('m/d H:i', strtotime($news['published_at']));
  ?>
  <div class="news-card">
    <div class="news-card-meta">
      <span class="news-cat-badge cat-<?= htmlspecialchars($news['category']) ?>"><?= htmlspecialchars($catLabel) ?></span>
      <span class="news-source"><?= htmlspecialchars($news['source']) ?></span>
      <span class="news-date"><?= $pubDate ?></span>
      <span class="news-sentiment <?= $sentClass ?>"><?= $sentLabel ?></span>
    </div>
    <a href="<?= htmlspecialchars($news['url']) ?>" target="_blank" rel="noopener noreferrer"
       class="news-title"><?= htmlspecialchars($news['title']) ?></a>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ===== ページング ===== -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?cat=<?= $cat ?>&page=<?= $page - 1 ?>" class="page-btn">&#8592; 前へ</a>
  <?php endif; ?>
  <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
    <a href="?cat=<?= $cat ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?cat=<?= $cat ?>&page=<?= $page + 1 ?>" class="page-btn">次へ &#8594;</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
