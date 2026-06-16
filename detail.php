<?php
require_once __DIR__ . '/config.php';

$ticker = trim($_GET['ticker'] ?? '');
if ($ticker === '') {
    header('Location: ' . SITE_URL . '/');
    exit;
}

$pdo = getPDO();

// 銘柄基本情報
$stmt = $pdo->prepare("SELECT * FROM stock_master WHERE ticker = :ticker AND is_active = 1");
$stmt->execute([':ticker' => $ticker]);
$stock = $stmt->fetch();

if (!$stock) {
    header('HTTP/1.1 404 Not Found');
    $pageTitle = '銘柄が見つかりません';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><div class="empty-icon">🔍</div><p>指定された銘柄が見つかりませんでした。</p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = htmlspecialchars($stock['company_name']);
$lineUser  = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

// 詳細ページ用SEO（headerで使用）
$pageDescription = sprintf(
    '%s（%s）の翌日株価上昇確率・AI予測理由を掲載。%sのファンダメンタル指標と予測履歴。',
    $stock['company_name'],
    $ticker,
    $stock['company_name']
);
$canonicalUrl = SITE_URL . '/detail.php?ticker=' . urlencode($ticker);

// 最新予測
$stmt = $pdo->prepare("
    SELECT * FROM stock_prediction
    WHERE ticker = :ticker
    ORDER BY predict_date DESC
    LIMIT 1
");
$stmt->execute([':ticker' => $ticker]);
$latestPrediction = $stmt->fetch();

// 過去10日間の予測履歴
$stmt = $pdo->prepare("
    SELECT predict_date, up_probability, actual_price_change, is_correct, reason_summary
    FROM stock_prediction
    WHERE ticker = :ticker
    ORDER BY predict_date DESC
    LIMIT 10
");
$stmt->execute([':ticker' => $ticker]);
$history = $stmt->fetchAll();

// 通算的中率
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total, SUM(is_correct) AS correct
    FROM stock_prediction
    WHERE ticker = :ticker AND is_correct IS NOT NULL
");
$stmt->execute([':ticker' => $ticker]);
$stats = $stmt->fetch();
$totalPred   = (int)$stats['total'];
$correctPred = (int)$stats['correct'];
$accuracy    = $totalPred > 0 ? round($correctPred / $totalPred * 100, 1) : null;

// 最新株価
$stmt = $pdo->prepare("
    SELECT close_price, target_date
    FROM stock_history
    WHERE ticker = :ticker
    ORDER BY target_date DESC
    LIMIT 1
");
$stmt->execute([':ticker' => $ticker]);
$latestPrice = $stmt->fetch();

include __DIR__ . '/includes/header.php';
?>

<!-- ===== 銘柄ヒーロー ===== -->
<div class="detail-hero">
  <div class="detail-hero-left">
    <div class="detail-hero-sector">
      <?= htmlspecialchars($stock['sector'] ?? '') ?>
      <?php if ($stock['industry']): ?>
        &nbsp;/&nbsp;<?= htmlspecialchars($stock['industry']) ?>
      <?php endif; ?>
    </div>
    <h1 class="detail-title"><?= htmlspecialchars($stock['company_name']) ?></h1>
    <div class="detail-code"><?= htmlspecialchars($ticker) ?></div>
    <?php if ($latestPrice): ?>
    <div class="detail-price-wrap">
      <span class="detail-price-value"><?= number_format($latestPrice['close_price']) ?>円</span>
      <span class="detail-price-date"><?= $latestPrice['target_date'] ?> 終値</span>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($latestPrediction): ?>
  <?php
    $prob = round($latestPrediction['up_probability'] * 100, 1);
    $direction = $prob >= 50 ? '上昇' : '下落';
    $probColor = $prob >= 60 ? 'var(--up-color)' : ($prob <= 40 ? 'var(--down-color)' : 'var(--accent)');
  ?>
  <div class="prediction-box">
    <div class="prediction-label">翌日上昇確率（<?= $latestPrediction['predict_date'] ?>）</div>
    <div class="prediction-prob" style="color:<?= $probColor ?>;"><?= $prob ?>%</div>
    <div class="prediction-sub">AIは【<?= $direction ?>】と予測</div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== ファンダメンタル指標 ===== -->
<div class="section-header">
  <h2 class="section-title">ファンダメンタル指標</h2>
</div>
<div class="fundamental-grid" style="margin-bottom:32px;">
  <?php
  $fundamentals = [
    ['label' => 'PER', 'value' => $stock['per'] !== null ? number_format($stock['per'], 1) . '倍' : '---'],
    ['label' => 'PBR', 'value' => $stock['pbr'] !== null ? number_format($stock['pbr'], 2) . '倍' : '---'],
    ['label' => 'ROE', 'value' => $stock['roe'] !== null ? number_format($stock['roe'], 1) . '%' : '---'],
    ['label' => '配当利回り', 'value' => $stock['dividend_yield'] !== null ? number_format($stock['dividend_yield'], 2) . '%' : '---'],
    ['label' => 'ベータ値', 'value' => $stock['beta'] !== null ? number_format($stock['beta'], 2) : '---'],
    ['label' => '時価総額', 'value' => $stock['market_cap'] !== null ? number_format($stock['market_cap'] / 1e8, 0) . '億円' : '---'],
  ];
  foreach ($fundamentals as $f):
  ?>
  <div class="fundamental-item">
    <div class="fundamental-label"><?= $f['label'] ?></div>
    <div class="fundamental-value"><?= $f['value'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ===== 最新予測理由（概要・詳細）===== -->
<?php if ($latestPrediction): ?>

<?php if ($isLoggedIn): ?>
  <?php if ($latestPrediction['reason_summary']): ?>
  <div class="section-header">
    <h2 class="section-title">予測概要</h2>
    <span class="section-badge">3行サマリー</span>
  </div>
  <div class="reason-box reason-box-summary">
    <?= nl2br(htmlspecialchars($latestPrediction['reason_summary'])) ?>
  </div>
  <?php endif; ?>

  <?php if ($latestPrediction['reason_detail']): ?>
  <div class="section-header section-header-spaced">
    <h2 class="section-title">予測詳細</h2>
    <span class="section-badge">詳細分析</span>
  </div>
  <div class="reason-box reason-box-detail">
    <?= nl2br(htmlspecialchars($latestPrediction['reason_detail'])) ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- 未ログイン時: ログイン促しCTA -->
  <div class="detail-login-cta">
    <div class="detail-login-cta-icon">🔒</div>
    <h2 class="detail-login-cta-title">予測概要・予測詳細を確認する</h2>
    <p class="detail-login-cta-text">LINEでログインしたら、AIの予測理由（概要・詳細分析）をその場で確認できます。</p>
    <a href="<?= SITE_URL ?>/line_login.php" class="detail-login-cta-btn">
      <span class="detail-login-cta-btn-icon">LINE</span>
      LINEでログインして詳細を確認
    </a>
    <p class="detail-login-cta-note">無料でログインできます</p>
  </div>
<?php endif; ?>

<?php endif; ?>

<!-- ===== 通算成績 ===== -->
<?php if ($totalPred > 0): ?>
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px 24px;margin-bottom:32px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
  <div>
    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:4px;">通算的中率</div>
    <div style="font-size:2rem;font-weight:700;color:var(--accent);"><?= $accuracy ?>%</div>
  </div>
  <div style="color:var(--text-secondary);font-size:.9rem;">
    <?= number_format($totalPred) ?>件の予測のうち <strong style="color:var(--text-primary);"><?= number_format($correctPred) ?>件</strong>が的中
  </div>
</div>
<?php endif; ?>

<!-- ===== 予測履歴 ===== -->
<div class="section-header">
  <h2 class="section-title">予測履歴（直近10日）</h2>
</div>
<div class="history-table-wrap" style="margin-bottom:40px;">
  <?php if (empty($history)): ?>
  <div class="empty-state" style="padding:40px;">
    <p>予測履歴がありません。</p>
  </div>
  <?php else: ?>
  <table class="history-table">
    <thead>
      <tr>
        <th>予測日</th>
        <th>上昇確率</th>
        <th>実際の値動き</th>
        <th>結果</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($history as $h): ?>
      <?php
        $prob   = round($h['up_probability'] * 100, 1);
        $change = $h['actual_price_change'];
        $changeStr = $change !== null
          ? ($change >= 0 ? '+' . number_format($change, 1) : number_format($change, 1)) . '円'
          : '未確定';
        $changeClass = $change !== null ? ($change >= 0 ? 'text-up' : 'text-down') : 'text-muted';
        $isCorrect = $h['is_correct'];
      ?>
      <tr>
        <td><?= htmlspecialchars($h['predict_date']) ?></td>
        <td><span style="font-weight:700;color:var(--accent);"><?= $prob ?>%</span></td>
        <td class="<?= $changeClass ?> fw-bold"><?= $changeStr ?></td>
        <td>
          <?php if ($isCorrect === null): ?>
            <span class="text-muted">---</span>
          <?php elseif ($isCorrect): ?>
            <span class="result-verdict verdict-correct">&#10003; 的中</span>
          <?php else: ?>
            <span class="result-verdict verdict-wrong">&#10007; ハズレ</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
