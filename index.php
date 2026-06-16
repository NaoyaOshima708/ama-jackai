<?php
require_once __DIR__ . '/config.php';
$pageTitle = '明日の株価予測ランキング';

$pdo = getPDO();

// SEO用
$pageDescription = '明日上がりそうな株をAIがランキング形式で毎日予測。日本株の翌日上昇確率・予測理由を掲載。東証上場銘柄の株価予測サービス。';
$canonicalUrl   = SITE_URL . '/';
$lineUser = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

// ===== ページング設定 =====
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));

// ===== 最新予測日を取得 =====
$latestDate = $pdo->query("SELECT MAX(predict_date) FROM stock_prediction")->fetchColumn();

// ===== ランキング（全銘柄・ページング対応） =====
$ranking    = [];
$totalCount = 0;
$totalPages = 1;
if ($latestDate) {
    // 上昇確率70%超のみ対象（DB小数値(0-1)・パーセント値(0-100)両対応）
    $upCond = "AND (CASE WHEN p.up_probability <= 1 THEN p.up_probability > 0.7 ELSE p.up_probability > 70 END)";

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :date
        $upCond
    ");
    $countStmt->execute([':date' => $latestDate]);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // ログインしていない場合は常に上位3件のみ取得
    if ($isLoggedIn) {
        $limit  = $perPage;
        $offset = ($page - 1) * $perPage;
    } else {
        $limit  = 3;
        $offset = 0;
    }

    $stmt = $pdo->prepare("
        SELECT p.ticker, m.company_name,
               p.up_probability, p.reason_summary, p.predict_date
        FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :date
        $upCond
        ORDER BY p.up_probability DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':date', $latestDate);
    $stmt->bindValue(':lim',  $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ranking = $stmt->fetchAll();
} else {
    $offset = 0;
}

// ===== 前日の結果（前々日・前日の終値も取得） =====
$prevDate = $pdo->query("
    SELECT MAX(predict_date) FROM stock_prediction
    WHERE is_correct IS NOT NULL
")->fetchColumn();

$prevResults    = [];
$prevUpResults  = [];
$prevDownResults = [];
$prevUpCorrect  = 0;
$prevUpTotal    = 0;
$prevDownCorrect = 0;
$prevDownTotal  = 0;
$prevCorrect    = 0;
$prevTotal      = 0;
if ($prevDate) {
    // 上昇確率70%超の銘柄（上昇予測組）—小数値・パーセント値両対応
    $stmtUp = $pdo->prepare("
        SELECT p.ticker, m.company_name,
               p.up_probability, p.down_probability,
               p.actual_price_change, p.is_correct, p.predict_date
        FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :d
          AND p.is_correct IS NOT NULL
          AND (CASE WHEN p.up_probability <= 1 THEN p.up_probability > 0.7 ELSE p.up_probability > 70 END)
        ORDER BY p.up_probability DESC
    ");
    $stmtUp->execute([':d' => $prevDate]);
    $prevUpResults = $stmtUp->fetchAll(PDO::FETCH_ASSOC);

    // 下落確率 0.7 以上の銘柄（下落予測組）
    $stmtDown = $pdo->prepare("
        SELECT p.ticker, m.company_name,
               p.up_probability, p.down_probability,
               p.actual_price_change, p.is_correct, p.predict_date
        FROM stock_prediction p
        JOIN stock_master m ON p.ticker = m.ticker
        WHERE p.predict_date = :d
          AND p.is_correct IS NOT NULL
          AND p.down_probability >= 0.7
        ORDER BY p.down_probability DESC
    ");
    $stmtDown->execute([':d' => $prevDate]);
    $prevDownResults = $stmtDown->fetchAll(PDO::FETCH_ASSOC);

    // 上昇組的中：上昇した（is_correct=1）銘柄数
    $prevUpTotal   = count($prevUpResults);
    $prevUpCorrect = array_sum(array_column($prevUpResults, 'is_correct'));

    // 下落組的中：下落した（is_correct=0）銘柄数
    $prevDownTotal   = count($prevDownResults);
    $prevDownCorrect = 0;
    foreach ($prevDownResults as $r) {
        if ((int)$r['is_correct'] === 0) $prevDownCorrect++;
    }

    // 履歴取得共通処理
    $historyStmt = $pdo->prepare("
        SELECT close_price
        FROM stock_history
        WHERE ticker = :ticker AND target_date < :date
        ORDER BY target_date DESC LIMIT 2
    ");
    foreach ($prevUpResults as &$row) {
        $historyStmt->execute([':ticker' => $row['ticker'], ':date' => $prevDate]);
        $h = $historyStmt->fetchAll(PDO::FETCH_COLUMN);
        $row['prev_close']  = $h[0] ?? null;
        $row['prev2_close'] = $h[1] ?? null;
    }
    unset($row);
    foreach ($prevDownResults as &$row) {
        $historyStmt->execute([':ticker' => $row['ticker'], ':date' => $prevDate]);
        $h = $historyStmt->fetchAll(PDO::FETCH_COLUMN);
        $row['prev_close']  = $h[0] ?? null;
        $row['prev2_close'] = $h[1] ?? null;
    }
    unset($row);

    $prevTotal   = $prevUpTotal + $prevDownTotal;
    $prevCorrect = $prevUpCorrect + $prevDownCorrect;
}

// ===== 全体統計 =====
$totalPredictions = $pdo->query("SELECT COUNT(*) FROM stock_prediction WHERE is_correct IS NOT NULL")->fetchColumn();
$overallAccuracy  = 0;
if ($totalPredictions > 0) {
    $overallAccuracy = $pdo->query("
        SELECT ROUND(AVG(is_correct) * 100, 1) FROM stock_prediction WHERE is_correct IS NOT NULL
    ")->fetchColumn();
}
$activeTickers = $pdo->query("SELECT COUNT(*) FROM stock_master WHERE is_active = 1")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<?php if (!$isLoggedIn): ?>
<!-- ===== LINEログイン案内ダイアログ ===== -->
<div class="line-modal-overlay" id="lineModal">
  <div class="line-modal" role="dialog" aria-modal="true" aria-labelledby="lineModalTitle">
    <button class="line-modal-close" onclick="closeLineModal()" aria-label="閉じる">&times;</button>
    <div class="line-modal-badge">&#10024; 新機能のお知らせ</div>
    <h2 id="lineModalTitle">LINEログイン機能が<br>ご利用いただけます！</h2>
    <ul class="line-modal-list">
      <li>
        <span class="modal-icon">&#9989;</span>
        <span><strong>全銘柄の予測情報を無料で閲覧</strong>できます。<br>LINEログイン＆友だち登録だけで、すべての機能が無料で使えます。</span>
      </li>
      <li>
        <span class="modal-icon">&#128241;</span>
        <span><strong>毎日LINEに「本日の株価見通し」</strong>をお届けします。<br>その日の相場環境・注目銘柄を朝にまとめてお知らせします。</span>
      </li>
      <li>
        <span class="modal-icon">&#128200;</span>
        <span>AIが日本株3,700銘柄以上の<strong>翌日上昇確率を毎日予測</strong>。ランキング上位の銘柄をいち早くチェックできます。</span>
      </li>
    </ul>
    <div class="line-modal-actions">
      <a href="<?= SITE_URL ?>/line_login.php" class="btn-line-modal">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.03 2 11c0 3.07 1.6 5.8 4.1 7.55v2.95l2.9-1.6c.96.26 1.97.4 3 .4 5.52 0 10-4.03 10-9S17.52 2 12 2z"/></svg>
        LINEでログインして全機能を使う（無料）
      </a>
      <button class="btn-modal-skip" onclick="closeLineModal()">今はしない（上位3件のみ閲覧）</button>
    </div>
  </div>
</div>
<script>
function closeLineModal() {
  var el = document.getElementById('lineModal');
  if (!el) return;
  el.style.opacity = '0';
  el.style.transition = 'opacity 0.25s';
  setTimeout(function(){ el.style.display = 'none'; }, 250);
  localStorage.setItem('line_modal_dismissed_v1', Date.now().toString());
}
document.addEventListener('DOMContentLoaded', function(){
  var KEY = 'line_modal_dismissed_v1';
  var el  = document.getElementById('lineModal');
  if (!el) return;
  // 24時間以内に閉じていたら表示しない
  var ts = localStorage.getItem(KEY);
  if (ts && Date.now() - parseInt(ts, 10) < 86400000) return;
  // 初期状態: 透明・非表示
  el.style.display = 'flex';
  el.style.opacity = '0';
  // 0.8秒後にフェードインで表示
  setTimeout(function(){
    el.style.transition = 'opacity 0.35s ease';
    el.style.opacity = '1';
  }, 800);
  // オーバーレイ背景クリックで閉じる
  el.addEventListener('click', function(e){
    if (e.target === el) closeLineModal();
  });
});
</script>
<?php endif; ?>

<!-- ===== タブナビゲーション ===== -->
<div class="tab-nav">
  <a href="<?= SITE_URL ?>/" class="tab-btn active">
    <span class="tab-icon">&#9650;</span> 上昇ランキング
  </a>
  <a href="<?= SITE_URL ?>/down_ranking.php" class="tab-btn">
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
    <div class="stat-label">昨日の上昇予測（≥７０％）的中率</div>
    <div class="stat-value"><?= round($prevUpCorrect / $prevUpTotal * 100) ?>%</div>
    <div class="stat-sub"><?= $prevUpCorrect ?>/<?= $prevUpTotal ?> 銘柄が上昇</div>
  </div>
  <?php endif; ?>
  <?php if ($prevDate && $prevDownTotal > 0): ?>
  <div class="stat-card">
    <div class="stat-label">昨日の下落予測（≥７０％）的中率</div>
    <div class="stat-value"><?= round($prevDownCorrect / $prevDownTotal * 100) ?>%</div>
    <div class="stat-sub"><?= $prevDownCorrect ?>/<?= $prevDownTotal ?> 銘柄が下落</div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== ランキング ===== -->
<div class="section-header">
  <h2 class="section-title">本日上がりそうな株 ランキング</h2>
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
    <p>まだ予測データがありません。<br>predict.py を実行してください。</p>
  </div>
  <?php else: ?>
  <table class="ranking-table">
    <thead>
      <tr>
        <th style="width:52px;">順位</th>
        <th>銘柄</th>
        <th class="prob-cell">上昇確率</th>
        <th>AIの予測理由（概要）</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ranking as $i => $row):
        $rank      = $offset + $i + 1;
        $prob      = round((float)$row['up_probability'], 1);
        $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
        $ticker    = htmlspecialchars($row['ticker']);
        $name      = htmlspecialchars($row['company_name']);
        $reason    = nl2br(htmlspecialchars($row['reason_summary'] ?? ''));
        $detailUrl = SITE_URL . '/detail.php?ticker=' . urlencode($row['ticker']);
      ?>
      <tr onclick="location.href='<?= $detailUrl ?>'">
        <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
        <td>
          <div class="ticker-info">
            <span class="ticker-code"><?= $ticker ?></span>
            <span class="ticker-name"><?= $name ?></span>
          </div>
        </td>
        <td class="prob-cell">
          <div class="prob-bar-wrap">
            <div class="prob-bar-bg">
              <div class="prob-bar-fill" style="width:<?= $prob ?>%"></div>
            </div>
            <span class="prob-value"><?= $prob ?>%</span>
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
    <p class="guest-cta-text">無料ゲストではランキングの上位3件まで閲覧できます。<br>すべての銘柄と前日の予測結果を閲覧するには、LINEでログインしてください。</p>
    <a href="<?= SITE_URL ?>/line_login.php" class="btn btn-line guest-cta-line-btn">LINEでログインする</a>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<!-- ===== 前日の予測結果（答え合わせ） ===== -->
<?php if ($isLoggedIn && $prevDate && ($prevUpTotal > 0 || $prevDownTotal > 0)): ?>
<div class="section-header">
  <h2 class="section-title">前日の予測結果（答え合わせ）</h2>
  <span class="section-badge"><?= date('Y年n月j日', strtotime($prevDate)) ?></span>
</div>

<?php
// カード表示用共通関数
function renderResultCards(array $rows, string $probField, string $probLabel, bool $correctMeans1): string {
    $html = '<div class="result-grid">';
    foreach ($rows as $row) {
        $isCorrect  = (int)$row['is_correct'];
        $hit        = $correctMeans1 ? ($isCorrect === 1) : ($isCorrect === 0);
        $change     = $row['actual_price_change'];
        $prob       = round((float)$row[$probField], 1);
        $ticker     = htmlspecialchars($row['ticker']);
        $name       = htmlspecialchars($row['company_name']);
        $detailUrl  = SITE_URL . '/detail.php?ticker=' . urlencode($row['ticker']);
        $prevClose  = $row['prev_close']  !== null ? number_format((float)$row['prev_close'],  1) : '---';
        $prev2Close = $row['prev2_close'] !== null ? number_format((float)$row['prev2_close'], 1) : '---';
        $changeStr  = $change !== null
            ? ($change >= 0 ? '+' . number_format($change, 1) : number_format($change, 1)) . '円'
            : '---';
        $changeClass = ($change !== null && $change >= 0) ? 'price-up' : 'price-down';
        $hitClass    = $hit ? 'correct' : 'wrong';
        $hitLabel    = $hit ? '&#10003; 的中' : '&#10007; ハズレ';
        $verdictClass = $hit ? 'verdict-correct' : 'verdict-wrong';
        $html .= "
        <div class=\"result-card {$hitClass}\" onclick=\"location.href='{$detailUrl}'\" style=\"cursor:pointer;\">
          <div class=\"result-card-header\">
            <div class=\"ticker-info\">
              <span class=\"ticker-code\">{$ticker}</span>
              <span class=\"ticker-name\">{$name}</span>
            </div>
            <span class=\"result-verdict {$verdictClass}\">{$hitLabel}</span>
          </div>
          <div class=\"price-history\">
            <div class=\"price-history-item\">
              <span class=\"price-history-label\">前々日終値</span>
              <span class=\"price-history-value\">{$prev2Close}円</span>
            </div>
            <div class=\"price-history-arrow\">&#8594;</div>
            <div class=\"price-history-item\">
              <span class=\"price-history-label\">前日終値</span>
              <span class=\"price-history-value\">{$prevClose}円</span>
            </div>
            <div class=\"price-history-arrow\">&#8594;</div>
            <div class=\"price-history-item\">
              <span class=\"price-history-label\">値動き</span>
              <span class=\"price-change {$changeClass}\">{$changeStr}</span>
            </div>
          </div>
          <div class=\"result-card-footer\">
            <span class=\"result-card-prob\">{$probLabel} {$prob}%</span>
          </div>
        </div>";
    }
    $html .= '</div>';
    return $html;
}
?>

<?php if ($prevUpTotal > 0): ?>
<div class="prev-result-summary">
  <span class="prev-result-rate"><?= round($prevUpCorrect / $prevUpTotal * 100) ?>%</span>
  <span class="prev-result-text">上昇予測（上昇確率７０％以上）的中率 — <?= $prevUpTotal ?>銘柄中 <strong><?= $prevUpCorrect ?>銘柄</strong>が予測通り上昇</span>
</div>
<?= renderResultCards($prevUpResults, 'up_probability', '上昇確率', true) ?>
<?php endif; ?>

<?php if ($prevDownTotal > 0): ?>
<div class="prev-result-summary" style="margin-top:1.5rem;">
  <span class="prev-result-rate" style="color:#e74c3c;"><?= round($prevDownCorrect / $prevDownTotal * 100) ?>%</span>
  <span class="prev-result-text">下落予測（下落確率７０％以上）的中率 — <?= $prevDownTotal ?>銘柄中 <strong><?= $prevDownCorrect ?>銘柄</strong>が予測通り下落</span>
</div>
<?= renderResultCards($prevDownResults, 'down_probability', '下落確率', false) ?>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
