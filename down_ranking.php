<?php
require_once __DIR__ . '/config.php';
$pageTitle = '明日の下落予測ランキング';

$pdo = getPDO();

// SEO用
$pageDescription = '明日下がりそうな株をAIがランキング形式で毎日予測。日本株の翌日下落確率・予測理由を掲載。東証上場銘柄の株価予測サービス。';
$canonicalUrl   = SITE_URL . '/down_ranking.php';
$lineUser = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

// ===== ページング設定 =====
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));

// ===== 最新予測日を取得 =====
$latestDate = $pdo->query("SELECT MAX(predict_date) FROM stock_prediction")->fetchColumn();

// ===== ランキング（下落確率順） =====
$ranking    = [];
$totalCount = 0;
$totalPages = 1;
if ($latestDate) {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :date
          AND p.down_probability IS NOT NULL
    ");
    $countStmt->execute([':date' => $latestDate]);
    $totalCount = (int)$countStmt->fetchColumn();

    // down_probability が未実装の場合は up_probability から計算
    if ($totalCount === 0) {
        $countStmt2 = $pdo->prepare("
            SELECT COUNT(*) FROM stock_prediction p
            JOIN stock_master m ON p.ticker = m.ticker
            WHERE p.predict_date = :date
        ");
        $countStmt2->execute([':date' => $latestDate]);
        $totalCount = (int)$countStmt2->fetchColumn();
        $useComputed = true;
    } else {
        $useComputed = false;
    }

    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page       = min($page, $totalPages);

    if ($isLoggedIn) {
        $limit  = $perPage;
        $offset = ($page - 1) * $perPage;
    } else {
        $limit  = 3;
        $offset = 0;
    }

    if (!$useComputed) {
        // down_probability カラムが存在する場合
        $stmt = $pdo->prepare("
            SELECT p.ticker, m.company_name,
                   p.down_probability,
                   p.up_probability,
                   p.reason_summary,
                   p.has_earnings,
                   p.news_sentiment,
                   p.predict_date
            FROM stock_prediction p
            JOIN stock_master m ON p.ticker = m.ticker
            WHERE p.predict_date = :date
              AND p.down_probability IS NOT NULL
            ORDER BY p.down_probability DESC
            LIMIT :lim OFFSET :off
        ");
    } else {
        // down_probability がない場合は up_probability の逆順
        $stmt = $pdo->prepare("
            SELECT p.ticker, m.company_name,
                   (100 - p.up_probability * 100) AS down_probability,
                   p.up_probability,
                   p.reason_summary,
                   0 AS has_earnings,
                   NULL AS news_sentiment,
                   p.predict_date
            FROM stock_prediction p
            JOIN stock_master m ON p.ticker = m.ticker
            WHERE p.predict_date = :date
            ORDER BY p.up_probability ASC
            LIMIT :lim OFFSET :off
        ");
    }
    $stmt->bindValue(':date', $latestDate);
    $stmt->bindValue(':lim',  $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ranking = $stmt->fetchAll();
} else {
    $offset = 0;
}

// ===== 全体統計（index.phpと同じ） =====
$activeTickers    = $pdo->query("SELECT COUNT(*) FROM stock_master WHERE is_active = 1")->fetchColumn();
$totalPredictions = $pdo->query("SELECT COUNT(*) FROM stock_prediction WHERE is_correct IS NOT NULL")->fetchColumn();
$overallAccuracy  = 0;
if ($totalPredictions > 0) {
    $overallAccuracy = $pdo->query("
        SELECT ROUND(AVG(is_correct) * 100, 1) FROM stock_prediction WHERE is_correct IS NOT NULL
    ")->fetchColumn();
}

// ===== 昨日の上昇予測70%以上・下落予測70%以上の的中率 =====
$prevDate        = $pdo->query("
    SELECT MAX(predict_date) FROM stock_prediction
    WHERE is_correct IS NOT NULL
")->fetchColumn();
$prevUpTotal     = 0;
$prevUpCorrect   = 0;
$prevDownTotal   = 0;
$prevDownCorrect = 0;
if ($prevDate) {
    // 上昇確率70%以上
    $stmtUp2 = $pdo->prepare("
        SELECT p.is_correct, p.up_probability
        FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :d
          AND p.is_correct IS NOT NULL
          AND p.up_probability >= 0.7
        ORDER BY p.up_probability DESC
    ");
    $stmtUp2->execute([':d' => $prevDate]);
    $prevUpResults = $stmtUp2->fetchAll(PDO::FETCH_ASSOC);
    $prevUpTotal   = count($prevUpResults);
    $prevUpCorrect = array_sum(array_column($prevUpResults, 'is_correct'));

    // 下落確率70%以上
    $stmt2 = $pdo->prepare("
        SELECT p.is_correct, p.down_probability
        FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :d
          AND p.is_correct IS NOT NULL
          AND p.down_probability >= 0.7
        ORDER BY p.down_probability DESC
    ");
    $stmt2->execute([':d' => $prevDate]);
    $prevDownResults = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $prevDownTotal   = count($prevDownResults);
    foreach ($prevDownResults as $r) {
        if ((int)$r['is_correct'] === 0) $prevDownCorrect++;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- ===== タブナビゲーション ===== -->
<div class="tab-nav">
  <a href="<?= SITE_URL ?>/" class="tab-btn">
    <span class="tab-icon">&#9650;</span> 上昇ランキング
  </a>
  <a href="<?= SITE_URL ?>/down_ranking.php" class="tab-btn active">
    <span class="tab-icon tab-down">&#9660;</span> 下落ランキング
  </a>
  <a href="<?= SITE_URL ?>/outlook.php" class="tab-btn">
    <span class="tab-icon">&#128202;</span> 株価見通し
  </a>
  <a href="<?= SITE_URL ?>/news.php" class="tab-btn">
    <span class="tab-icon">&#127758;</span> 情勢・ニュース
  </a>
  <a href="<?= SITE_URL ?>/earnings.php" class="tab-btn">
    <span class="tab-icon">&#128200;</span> 決算カレンダー
  </a>
  <a href="<?= SITE_URL ?>/nisa.php" class="tab-btn">
    <span class="tab-icon">&#127981;</span> NISA
  </a>
</div>

<!-- ===== STATS BAR ===== -->
<div class="stats-bar">
  <div class="stat-card">
    <div class="stat-label">予測対象銘柄数</div>
    <div class="stat-value"><?= number_format($activeTickers) ?></div>
    <div class="stat-sub">東証上場銘柄</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">通算的中率</div>
    <div class="stat-value"><?= $overallAccuracy ?>%</div>
    <div class="stat-sub"><?= number_format($totalPredictions) ?>件の予測実績</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">最新予測日</div>
    <div class="stat-value" style="font-size:1.3rem;">
      <?= $latestDate ? date('m/d', strtotime($latestDate)) : '---' ?>
    </div>
    <div class="stat-sub">毎営業日 7:30 更新</div>
  </div>
  <?php if ($prevDate && $prevUpTotal > 0): ?>
  <div class="stat-card">
    <div class="stat-label">昨日の上昇予測（≥70%）的中率</div>
    <div class="stat-value"><?= round($prevUpCorrect / $prevUpTotal * 100) ?>%</div>
    <div class="stat-sub"><?= $prevUpCorrect ?>/<?= $prevUpTotal ?> 銘柄が上昇</div>
  </div>
  <?php endif; ?>
  <?php if ($prevDate && $prevDownTotal > 0): ?>
  <div class="stat-card">
    <div class="stat-label">昨日の下落予測（≥70%）的中率</div>
    <div class="stat-value" style="color:#e74c3c;"><?= round($prevDownCorrect / $prevDownTotal * 100) ?>%</div>
    <div class="stat-sub"><?= $prevDownCorrect ?>/<?= $prevDownTotal ?> 銘柄が下落</div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== ランキング ===== -->
<div class="section-header">
  <h2 class="section-title">
    <span style="color:var(--danger,#e74c3c);">&#9660;</span>
    本日下がりそうな株 ランキング
  </h2>
  <div class="section-actions">
    <?php if ($latestDate): ?>
    <span class="section-badge"><?= date('Y年n月j日', strtotime($latestDate)) ?> 予測</span>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>/ai_logic.php" class="ai-logic-btn">
      <span class="ai-logic-icon">🤖</span> AIの予測ロジックとは？
    </a>
  </div>
</div>

<div class="ranking-table-wrap">
<?php if (empty($ranking)): ?>
<div class="empty-state">
  <div class="empty-icon">📊</div>
  <p>まだ予測データがありません。<br>predict_v2.py を実行してください。</p>
</div>
<?php else: ?>
<table class="ranking-table">
  <thead>
    <tr>
      <th style="width:52px;">順位</th>
      <th>銘柄</th>
      <th class="prob-cell" style="color:var(--danger,#e74c3c);">下落確率</th>
      <th>AIの予測理由（概要）</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($ranking as $i => $row):
      $rank      = $offset + $i + 1;
      $downProb  = round((float)$row['down_probability'], 1);
      $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
      $ticker    = htmlspecialchars($row['ticker']);
      $name      = htmlspecialchars($row['company_name']);
      $reason    = nl2br(htmlspecialchars($row['reason_summary'] ?? ''));
      $detailUrl = SITE_URL . '/detail.php?ticker=' . urlencode($row['ticker']);
      $hasEarnings = !empty($row['has_earnings']);
    ?>
    <tr onclick="location.href='<?= $detailUrl ?>'">
      <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
      <td>
        <div class="ticker-info">
          <span class="ticker-code"><?= $ticker ?></span>
          <span class="ticker-name"><?= $name ?></span>
          <?php if ($hasEarnings): ?>
          <span class="earnings-badge" title="決算発表予定あり">決算</span>
          <?php endif; ?>
        </div>
      </td>
      <td class="prob-cell">
        <div class="prob-bar-wrap">
          <div class="prob-bar-bg">
            <div class="prob-bar-fill prob-bar-down" style="width:<?= $downProb ?>%"></div>
          </div>
          <span class="prob-value prob-down"><?= $downProb ?>%</span>
        </div>
      </td>
      <td><div class="reason-text"><?= $reason ?></div></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- ===== ページング ===== -->
<?php if ($isLoggedIn && $totalPages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?page=<?= $page - 1 ?>" class="page-btn">&#8592; 前へ</a>
  <?php endif; ?>
  <?php
    $pStart = max(1, $page - 2);
    $pEnd   = min($totalPages, $page + 2);
  ?>
  <?php if ($pStart > 1): ?>
    <a href="?page=1" class="page-btn">1</a>
    <?php if ($pStart > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
  <?php endif; ?>
  <?php for ($p = $pStart; $p <= $pEnd; $p++): ?>
    <a href="?page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
  <?php if ($pEnd < $totalPages): ?>
    <?php if ($pEnd < $totalPages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
    <a href="?page=<?= $totalPages ?>" class="page-btn"><?= $totalPages ?></a>
  <?php endif; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?page=<?= $page + 1 ?>" class="page-btn">次へ &#8594;</a>
  <?php endif; ?>
  <span class="page-info"><?= number_format($totalCount) ?>銘柄 / <?= $totalPages ?>ページ中 <?= $page ?>ページ目</span>
</div>
<?php elseif (!$isLoggedIn): ?>
<div class="guest-cta-box">
  <p class="guest-cta-text">無料ゲストではランキングの上位3件まで閲覧できます。<br>すべての銘柄を閲覧するには、LINEでログインしてください。</p>
  <a href="<?= SITE_URL ?>/line_login.php" class="btn btn-line guest-cta-line-btn">LINEでログインする</a>
</div>
<?php endif; ?>

<?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
