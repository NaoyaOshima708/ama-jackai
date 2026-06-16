<?php
require_once __DIR__ . '/config.php';

$q = trim($_GET['q'] ?? '');
$pageTitle = $q ? "「{$q}」の検索結果" : '銘柄検索';

// SEO用
$pageDescription = $q
  ? "「{$q}」の銘柄検索結果。日本株の株価予測・上昇確率をAIでチェック。"
  : "銘柄コード・銘柄名で日本株を検索。株価予測AIの上昇確率ランキングと連携。";
$canonicalUrl = SITE_URL . '/search.php' . ($q !== '' ? '?q=' . urlencode($q) : '');

$results = [];
$totalCount = 0;

if ($q !== '') {
    $pdo = getPDO();
    $like = '%' . $q . '%';

    // 件数取得
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM stock_master
        WHERE is_active = 1
          AND (ticker LIKE :q1 OR company_name LIKE :q2)
    ");
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    $totalCount = (int)$stmt->fetchColumn();

    // 最新予測日
    $latestDate = $pdo->query("SELECT MAX(predict_date) FROM stock_prediction")->fetchColumn();

    // 検索結果（最大100件）＋最新予測を LEFT JOIN
    $stmt = $pdo->prepare("
        SELECT m.ticker, m.company_name, m.sector, m.industry,
               m.per, m.pbr, m.roe,
               p.up_probability, p.predict_date
        FROM stock_master m
        LEFT JOIN stock_prediction p
          ON m.ticker = p.ticker AND p.predict_date = :date
        WHERE m.is_active = 1
          AND (m.ticker LIKE :q1 OR m.company_name LIKE :q2)
        ORDER BY p.up_probability DESC, m.ticker ASC
        LIMIT 100
    ");
    $stmt->execute([':date' => $latestDate, ':q1' => $like, ':q2' => $like]);
    $results = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="section-header search-page-header">
  <h1 class="section-title">
    <?php if ($q): ?>
      「<?= htmlspecialchars($q) ?>」の検索結果
    <?php else: ?>
      銘柄検索
    <?php endif; ?>
  </h1>
  <?php if ($totalCount > 0): ?>
  <span class="section-badge"><?= number_format($totalCount) ?> 件</span>
  <?php endif; ?>
</div>

<?php if ($q === ''): ?>
<!-- 検索前の案内 -->
<div class="empty-state empty-state-search">
  <div class="empty-icon">🔍</div>
  <p class="empty-state-lead">銘柄コードまたは銘柄名を入力して検索してください</p>
  <p class="text-muted">例: <span class="text-accent">7203</span>（コード）、<span class="text-accent">トヨタ</span>（銘柄名）、<span class="text-accent">自動車</span>（業種）</p>
</div>

<?php elseif (empty($results)): ?>
<!-- 検索結果なし -->
<div class="empty-state">
  <div class="empty-icon">😔</div>
  <p>「<?= htmlspecialchars($q) ?>」に一致する銘柄が見つかりませんでした。</p>
  <p class="text-muted" style="margin-top:8px;">別のキーワードでお試しください。</p>
</div>

<?php else: ?>
<!-- 検索結果一覧 -->
<div class="search-result-list">
  <?php foreach ($results as $row): ?>
  <?php
    $ticker    = htmlspecialchars($row['ticker']);
    $name      = htmlspecialchars($row['company_name']);
    $sector    = htmlspecialchars($row['sector'] ?? '');
    $industry  = htmlspecialchars($row['industry'] ?? '');
    $prob      = $row['up_probability'] !== null ? round($row['up_probability'] * 100, 1) : null;
    $detailUrl = SITE_URL . '/detail.php?ticker=' . urlencode($row['ticker']);
  ?>
  <div class="search-result-item" onclick="location.href='<?= $detailUrl ?>'">
    <div class="search-result-item-inner">
      <div class="search-result-item-meta">
        <span class="search-result-item-ticker"><?= $ticker ?></span>
        <?php if ($sector): ?><span class="sector-tag"><?= $sector ?></span><?php endif; ?>
        <?php if ($industry): ?><span class="search-result-item-industry"><?= $industry ?></span><?php endif; ?>
      </div>
      <span class="search-result-item-name"><?= $name ?></span>
      <div class="search-result-item-fundamentals">
        <?php if ($row['per'] !== null): ?>
        <span>PER <span class="search-result-value"><?= number_format($row['per'], 1) ?>倍</span></span>
        <?php endif; ?>
        <?php if ($row['pbr'] !== null): ?>
        <span>PBR <span class="search-result-value"><?= number_format($row['pbr'], 2) ?>倍</span></span>
        <?php endif; ?>
        <?php if ($row['roe'] !== null): ?>
        <span>ROE <span class="search-result-value"><?= number_format($row['roe'], 1) ?>%</span></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="search-result-item-right">
      <?php if ($prob !== null): ?>
      <div class="search-result-prob-wrap">
        <div class="search-result-prob-label">上昇確率</div>
        <div class="search-result-prob-value search-result-prob-<?= $prob >= 60 ? 'up' : ($prob <= 40 ? 'down' : 'mid') ?>"><?= $prob ?>%</div>
      </div>
      <?php else: ?>
      <span class="search-result-no-pred">予測データなし</span>
      <?php endif; ?>
      <span class="search-result-arrow">&#8250;</span>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($totalCount > 100): ?>
<p class="search-limit-note">
  ※ 上位100件を表示しています。より絞り込んで検索してください。
</p>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
