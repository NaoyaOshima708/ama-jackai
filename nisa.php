<?php
require_once __DIR__ . '/config.php';
$lineUser   = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;
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
  <a href="<?= SITE_URL ?>/earnings.php" class="tab-btn">
    <span class="tab-icon">&#128200;</span> 決算カレンダー
  </a>
  <a href="<?= SITE_URL ?>/nisa.php" class="tab-btn active tab-btn-nisa">
    <span class="tab-icon">&#127981;</span> NISA
  </a>
</div>

<div class="main-content">

  <!-- ===== ページヘッダー ===== -->
  <div class="nisa-page-header">
    <h1 class="nisa-page-title">&#127981; NISA・新NISA ガイド</h1>
    <p class="nisa-page-desc">非課税投資制度「NISA」の基礎知識から、2024年に大幅改正された「新NISA」との違いまでわかりやすく解説します。</p>
  </div>

  <!-- ===== 比較カード ===== -->
  <div class="nisa-compare-grid">

    <!-- 旧NISA -->
    <div class="nisa-card nisa-card-old">
      <div class="nisa-card-header">
        <span class="nisa-badge nisa-badge-old">旧NISA（2023年まで）</span>
        <h2 class="nisa-card-title">NISA</h2>
        <p class="nisa-card-subtitle">少額投資非課税制度</p>
      </div>
      <div class="nisa-card-body">
        <dl class="nisa-spec-list">
          <dt>制度の状態</dt>
          <dd><span class="nisa-tag nisa-tag-ended">&#10006; 新規買付終了（2023年末）</span></dd>

          <dt>種類</dt>
          <dd>一般NISA・つみたてNISA（どちらか一方を選択）</dd>

          <dt>非課税保有期間</dt>
          <dd>
            一般NISA：<strong>5年間</strong><br>
            つみたてNISA：<strong>20年間</strong>
          </dd>

          <dt>年間投資上限額</dt>
          <dd>
            一般NISA：<strong>120万円</strong><br>
            つみたてNISA：<strong>40万円</strong>
          </dd>

          <dt>生涯投資上限額</dt>
          <dd>設定なし（非課税期間×年間上限で実質上限あり）</dd>

          <dt>投資対象</dt>
          <dd>
            一般NISA：株式・投資信託・ETF など<br>
            つみたてNISA：金融庁指定の投資信託・ETF のみ
          </dd>

          <dt>口座数</dt>
          <dd>1人1口座（金融機関は年単位で変更可）</dd>

          <dt>非課税枠の再利用</dt>
          <dd><span class="nisa-tag nisa-tag-no">&#10006; 不可</span>（売却しても枠は復活しない）</dd>

          <dt>ロールオーバー</dt>
          <dd>一般NISAのみ可（翌年の枠に移行）</dd>
        </dl>
      </div>
    </div>

    <!-- 新NISA -->
    <div class="nisa-card nisa-card-new">
      <div class="nisa-card-header">
        <span class="nisa-badge nisa-badge-new">新NISA（2024年〜）</span>
        <h2 class="nisa-card-title">新NISA</h2>
        <p class="nisa-card-subtitle">恒久化された新しい非課税制度</p>
      </div>
      <div class="nisa-card-body">
        <dl class="nisa-spec-list">
          <dt>制度の状態</dt>
          <dd><span class="nisa-tag nisa-tag-active">&#10003; 2024年1月〜開始・恒久化</span></dd>

          <dt>種類</dt>
          <dd>つみたて投資枠・成長投資枠（<strong>両方を同時に利用可</strong>）</dd>

          <dt>非課税保有期間</dt>
          <dd><strong>無期限</strong>（恒久化）</dd>

          <dt>年間投資上限額</dt>
          <dd>
            つみたて投資枠：<strong>120万円</strong><br>
            成長投資枠：<strong>240万円</strong><br>
            合計：<strong>360万円／年</strong>
          </dd>

          <dt>生涯投資上限額</dt>
          <dd><strong>1,800万円</strong>（うち成長投資枠は1,200万円まで）</dd>

          <dt>投資対象</dt>
          <dd>
            つみたて投資枠：金融庁指定の投資信託・ETF<br>
            成長投資枠：株式・投資信託・ETF など
          </dd>

          <dt>口座数</dt>
          <dd>1人1口座（金融機関は年単位で変更可）</dd>

          <dt>非課税枠の再利用</dt>
          <dd><span class="nisa-tag nisa-tag-yes">&#10003; 可能</span>（売却した翌年に枠が復活）</dd>

          <dt>ロールオーバー</dt>
          <dd>不要（無期限のため）</dd>
        </dl>
      </div>
    </div>

  </div><!-- /.nisa-compare-grid -->

  <!-- ===== 主な違い一覧表 ===== -->
  <section class="nisa-section">
    <h2 class="section-title">&#128203; 旧NISAと新NISAの主な違い</h2>
    <div class="nisa-table-wrap">
      <table class="nisa-table">
        <thead>
          <tr>
            <th>項目</th>
            <th>旧NISA（〜2023年）</th>
            <th>新NISA（2024年〜）</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>制度の恒久化</td>
            <td class="td-old">&#10006; 時限措置</td>
            <td class="td-new">&#10003; 恒久化</td>
          </tr>
          <tr>
            <td>非課税保有期間</td>
            <td class="td-old">5年 or 20年（有期限）</td>
            <td class="td-new"><strong>無期限</strong></td>
          </tr>
          <tr>
            <td>年間投資上限</td>
            <td class="td-old">最大120万円</td>
            <td class="td-new"><strong>最大360万円</strong></td>
          </tr>
          <tr>
            <td>生涯投資上限</td>
            <td class="td-old">実質600万円程度</td>
            <td class="td-new"><strong>1,800万円</strong></td>
          </tr>
          <tr>
            <td>口座の種類</td>
            <td class="td-old">一般 or つみたて（選択制）</td>
            <td class="td-new"><strong>両方同時利用可</strong></td>
          </tr>
          <tr>
            <td>非課税枠の再利用</td>
            <td class="td-old">&#10006; 不可</td>
            <td class="td-new">&#10003; <strong>売却翌年に復活</strong></td>
          </tr>
          <tr>
            <td>株式投資</td>
            <td class="td-old">一般NISAのみ可</td>
            <td class="td-new">成長投資枠で可</td>
          </tr>
          <tr>
            <td>ロールオーバー</td>
            <td class="td-old">一般NISAのみ可</td>
            <td class="td-new">不要（無期限のため）</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ===== 新NISAのポイント ===== -->
  <section class="nisa-section">
    <h2 class="section-title">&#128161; 新NISAの3大ポイント</h2>
    <div class="nisa-points-grid">
      <div class="nisa-point-card">
        <div class="nisa-point-icon">&#9855;</div>
        <h3>非課税が無期限に</h3>
        <p>旧NISAでは5年・20年という期限がありましたが、新NISAでは<strong>保有し続ける限り永久に非課税</strong>。長期投資に最適です。</p>
      </div>
      <div class="nisa-point-card">
        <div class="nisa-point-icon">&#128176;</div>
        <h3>投資枠が大幅拡大</h3>
        <p>年間360万円・生涯1,800万円まで投資可能。旧NISAの約3倍の枠で、<strong>より多くの資産を非課税で運用</strong>できます。</p>
      </div>
      <div class="nisa-point-card">
        <div class="nisa-point-icon">&#128260;</div>
        <h3>売却後に枠が復活</h3>
        <p>旧NISAでは売却しても枠は消滅していましたが、新NISAでは<strong>売却した翌年に非課税枠が復活</strong>。柔軟な資産管理が可能です。</p>
      </div>
    </div>
  </section>

  <!-- ===== AIと新NISAの活用 ===== -->
  <section class="nisa-section">
    <h2 class="section-title">&#129302; AIと新NISAを組み合わせた投資戦略</h2>
    <div class="nisa-ai-box">
      <p>当サイトのAI株価予測と新NISAの成長投資枠を組み合わせることで、より効率的な投資が期待できます。</p>
      <ul class="nisa-ai-list">
        <li>&#9654; <strong>上昇確率の高い銘柄</strong>を新NISAの成長投資枠で購入 → 値上がり益が非課税</li>
        <li>&#9654; <strong>つみたて投資枠</strong>でインデックスファンドを積み立て → 分散投資でリスク軽減</li>
        <li>&#9654; 利益確定後も<strong>翌年に枠が復活</strong>するため、AI予測を活かした機動的な売買が可能</li>
      </ul>
      <?php if (!$isLoggedIn): ?>
      <div class="nisa-cta">
        <p>LINEログインすると、AIが予測する本日の上昇ランキングや株価見通しを無料で確認できます。</p>
        <a href="<?= SITE_URL ?>/login.php" class="btn btn-line">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
          LINEでログインして予測を確認（無料）
        </a>
      </div>
      <?php else: ?>
      <div class="nisa-cta">
        <a href="<?= SITE_URL ?>/" class="btn btn-primary">&#9650; 本日の上昇ランキングを見る</a>
        <a href="<?= SITE_URL ?>/outlook.php" class="btn btn-outline" style="margin-left:10px;">&#128202; 株価見通しを確認</a>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== よくある質問 ===== -->
  <section class="nisa-section">
    <h2 class="section-title">&#10067; よくある質問</h2>
    <div class="nisa-faq-list">

      <details class="nisa-faq-item">
        <summary class="nisa-faq-q">旧NISAの資産はどうなりますか？</summary>
        <div class="nisa-faq-a">
          旧NISAで保有している資産は、それぞれの非課税期間（一般NISA：5年、つみたてNISA：20年）が終了するまでそのまま保有できます。新NISAへの自動移行はされないため、期限が来たら課税口座（特定口座など）に移管されます。
        </div>
      </details>

      <details class="nisa-faq-item">
        <summary class="nisa-faq-q">旧NISAと新NISAは別々の口座ですか？</summary>
        <div class="nisa-faq-a">
          はい、別々の口座として管理されます。旧NISAの口座はそのまま残り、2024年以降の新規投資は新NISAの口座で行います。同じ金融機関でNISA口座を持っている場合、自動的に新NISA口座が開設されます。
        </div>
      </details>

      <details class="nisa-faq-item">
        <summary class="nisa-faq-q">新NISAで日本株（個別株）は買えますか？</summary>
        <div class="nisa-faq-a">
          はい、新NISAの「成長投資枠」（年間240万円まで）で日本株・米国株などの個別株を購入できます。当サイトのAI予測ランキングで上昇確率の高い銘柄を探し、成長投資枠で購入するという使い方が可能です。
        </div>
      </details>

      <details class="nisa-faq-item">
        <summary class="nisa-faq-q">新NISAの生涯上限1,800万円とはどういう意味ですか？</summary>
        <div class="nisa-faq-a">
          新NISAで非課税保有できる元本の合計上限が1,800万円です。例えば年間360万円ずつ投資すると5年で上限に達します。ただし、保有資産を売却すると翌年にその分の枠が復活するため、実際には1,800万円以上を運用することも可能です（売却→再投資を繰り返すことで）。
        </div>
      </details>

      <details class="nisa-faq-item">
        <summary class="nisa-faq-q">NISAで損失が出た場合はどうなりますか？</summary>
        <div class="nisa-faq-a">
          NISA口座内の損失は、他の口座の利益と「損益通算」ができません。また、損失を翌年以降に繰り越す「繰越控除」も利用できません。これはNISAのデメリットの一つです。損失リスクを抑えるため、長期・分散投資が推奨されています。
        </div>
      </details>

    </div>
  </section>

</div><!-- /.main-content -->

<?php include __DIR__ . '/includes/footer.php'; ?>
