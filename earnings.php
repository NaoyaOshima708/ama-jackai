<?php
require_once __DIR__ . '/config.php';
$pageTitle = '決算カレンダー';
$pageDescription = '東証上場銘柄の決算発表スケジュール。決算日・EPS予想・実績・サプライズ率を一覧表示。';
$canonicalUrl = SITE_URL . '/earnings.php';
$pdo = getPDO();
$lineUser   = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

// ===== 表示月の設定 =====
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

// ===== 当月の決算データ取得 =====
$stmt = $pdo->prepare("
    SELECT e.*, m.company_name
    FROM earnings_calendar e
    JOIN stock_master m ON e.ticker = m.ticker
    WHERE e.earnings_date BETWEEN :start AND :end
    ORDER BY e.earnings_date ASC, e.ticker ASC
");
$stmt->execute([':start' => $monthStart, ':end' => $monthEnd]);
$earningsList = $stmt->fetchAll();

// 日付ごとにグループ化
$earningsByDate = [];
foreach ($earningsList as $row) {
    $earningsByDate[$row['earnings_date']][] = $row;
}

// ===== 直近の決算サプライズ上位（ログイン時のみ） =====
$surpriseList = [];
if ($isLoggedIn) {
    $surpriseStmt = $pdo->query("
        SELECT e.*, m.company_name
        FROM earnings_calendar e
        JOIN stock_master m ON e.ticker = m.ticker
        WHERE e.eps_actual IS NOT NULL
          AND e.surprise_pct IS NOT NULL
          AND e.earnings_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ORDER BY ABS(e.surprise_pct) DESC
        LIMIT 20
    ");
    $surpriseList = $surpriseStmt->fetchAll();
}

// ===== 今後の決算（直近30日） =====
$upcomingStmt = $pdo->prepare("
    SELECT e.*, m.company_name
    FROM earnings_calendar e
    JOIN stock_master m ON e.ticker = m.ticker
    WHERE e.earnings_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY e.earnings_date ASC
    LIMIT 50
");
$upcomingStmt->execute();
$upcomingList = $upcomingStmt->fetchAll();

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
  <a href="<?= SITE_URL ?>/news.php" class="tab-btn">
    <span class="tab-icon">&#127758;</span> 情勢・ニュース
  </a>
  <a href="<?= SITE_URL ?>/earnings.php" class="tab-btn active">
    <span class="tab-icon">&#128200;</span> 決算カレンダー
  </a>
  <a href="<?= SITE_URL ?>/nisa.php" class="tab-btn">
    <span class="tab-icon">&#127981;</span> NISA
  </a>
</div>

<!-- ===== 直近30日の決算予定 ===== -->
<div class="section-header">
  <h2 class="section-title">直近30日の決算予定</h2>
  <span class="section-badge"><?= count($upcomingList) ?>件</span>
</div>

<?php if (empty($upcomingList)): ?>
<div class="empty-state">
  <div class="empty-icon">📅</div>
  <p>決算データがありません。<br>fetch_news_earnings.py を実行してください。</p>
</div>
<?php else: ?>
<div class="earnings-upcoming-grid">
  <?php
  $prevDateLabel = '';
  foreach ($upcomingList as $row):
    $dateLabel = date('m/d(D)', strtotime($row['earnings_date']));
    $weekdays  = ['Sun'=>'日','Mon'=>'月','Tue'=>'火','Wed'=>'水','Thu'=>'木','Fri'=>'金','Sat'=>'土'];
    $dateLabel = date('m/d', strtotime($row['earnings_date'])) . '(' . ($weekdays[date('D', strtotime($row['earnings_date']))] ?? '') . ')';
    $isToday   = $row['earnings_date'] === date('Y-m-d');
    $isSoon    = (strtotime($row['earnings_date']) - time()) < 86400 * 3;
    $detailUrl = SITE_URL . '/detail.php?ticker=' . urlencode($row['ticker']);
  ?>
  <div class="earnings-card <?= $isToday ? 'earnings-today' : '' ?>"
       onclick="location.href='<?= $detailUrl ?>'" style="cursor:pointer;">
    <div class="earnings-card-date <?= $isSoon ? 'soon' : '' ?>">
      <?= $dateLabel ?>
      <?php if ($isToday): ?><span class="today-badge">本日</span><?php endif; ?>
    </div>
    <div class="earnings-card-ticker">
      <span class="ticker-code"><?= htmlspecialchars($row['ticker']) ?></span>
      <span class="ticker-name"><?= htmlspecialchars($row['company_name']) ?></span>
    </div>
    <?php if ($row['eps_estimate'] !== null): ?>
    <div class="earnings-card-eps">
      EPS予想: <strong><?= number_format((float)$row['eps_estimate'], 2) ?></strong>
    </div>
    <?php endif; ?>
    <?php if ($row['fiscal_period']): ?>
    <div class="earnings-card-period"><?= htmlspecialchars($row['fiscal_period']) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== 月別カレンダー ===== -->
<div class="section-header" style="margin-top:2rem;">
  <h2 class="section-title">決算カレンダー</h2>
  <div class="calendar-nav">
    <?php
      $prevMonth = $month - 1; $prevYear = $year;
      if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
      $nextMonth = $month + 1; $nextYear = $year;
      if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
    ?>
    <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="page-btn">&#8592;</a>
    <span class="calendar-month-label"><?= $year ?>年<?= $month ?>月</span>
    <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="page-btn">&#8594;</a>
  </div>
</div>

<div class="earnings-calendar-wrap">
  <table class="earnings-calendar-table">
    <thead>
      <tr>
        <th class="cal-sun">日</th>
        <th>月</th><th>火</th><th>水</th><th>木</th><th>金</th>
        <th class="cal-sat">土</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $firstDow = (int)date('w', strtotime($monthStart)); // 0=日
      $daysInMonth = (int)date('t', strtotime($monthStart));
      $day = 1;
      $cellCount = 0;
      echo '<tr>';
      // 最初の空白セル
      for ($i = 0; $i < $firstDow; $i++) {
          echo '<td class="cal-empty"></td>';
          $cellCount++;
      }
      while ($day <= $daysInMonth) {
          $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
          $dow      = ($firstDow + $day - 1) % 7;
          $isToday  = $dateStr === date('Y-m-d');
          $tdClass  = $dow === 0 ? 'cal-sun' : ($dow === 6 ? 'cal-sat' : '');
          if ($isToday) $tdClass .= ' cal-today';

          echo '<td class="cal-cell ' . $tdClass . '">';
          echo '<div class="cal-day-num">' . $day . '</div>';

          if (isset($earningsByDate[$dateStr])) {
              echo '<div class="cal-earnings-list">';
              foreach ($earningsByDate[$dateStr] as $e) {
                  $url = SITE_URL . '/detail.php?ticker=' . urlencode($e['ticker']);
                  echo '<a href="' . $url . '" class="cal-earnings-item" title="' . htmlspecialchars($e['company_name']) . '">';
                  echo htmlspecialchars($e['ticker']);
                  echo '</a>';
              }
              echo '</div>';
          }
          echo '</td>';

          $cellCount++;
          if ($cellCount % 7 === 0 && $day < $daysInMonth) {
              echo '</tr><tr>';
          }
          $day++;
      }
      // 末尾の空白セル
      $remaining = 7 - ($cellCount % 7);
      if ($remaining < 7) {
          for ($i = 0; $i < $remaining; $i++) {
              echo '<td class="cal-empty"></td>';
          }
      }
      echo '</tr>';
    ?>
    </tbody>
  </table>
</div>

<!-- ===== 直近の決算サプライズ（ログイン時のみ） ===== -->
<?php if ($isLoggedIn && !empty($surpriseList)): ?>
<div class="section-header" style="margin-top:2rem;">
  <h2 class="section-title">直近90日の決算サプライズ TOP20</h2>
</div>
<div class="ranking-table-wrap">
<table class="ranking-table">
  <thead>
    <tr>
      <th>銘柄</th>
      <th>決算日</th>
      <th>EPS予想</th>
      <th>EPS実績</th>
      <th>サプライズ</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($surpriseList as $row):
    $surp      = (float)$row['surprise_pct'];
    $surpClass = $surp > 0 ? 'price-up' : 'price-down';
    $detailUrl = SITE_URL . '/detail.php?ticker=' . urlencode($row['ticker']);
  ?>
  <tr onclick="location.href='<?= $detailUrl ?>'" style="cursor:pointer;">
    <td>
      <div class="ticker-info">
        <span class="ticker-code"><?= htmlspecialchars($row['ticker']) ?></span>
        <span class="ticker-name"><?= htmlspecialchars($row['company_name']) ?></span>
      </div>
    </td>
    <td><?= htmlspecialchars($row['earnings_date']) ?></td>
    <td><?= $row['eps_estimate'] !== null ? number_format((float)$row['eps_estimate'], 2) : '---' ?></td>
    <td><?= $row['eps_actual']   !== null ? number_format((float)$row['eps_actual'],   2) : '---' ?></td>
    <td class="<?= $surpClass ?>">
      <?= $surp >= 0 ? '+' : '' ?><?= number_format($surp, 1) ?>%
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php elseif (!$isLoggedIn): ?>
<div class="guest-cta-box" style="margin-top:2rem;">
  <p class="guest-cta-text">決算サプライズ一覧はLINEログイン後に閲覧できます。</p>
  <a href="<?= SITE_URL ?>/line_login.php" class="btn btn-line guest-cta-line-btn">LINEでログインする</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
