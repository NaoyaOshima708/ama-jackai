<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'AIの予測ロジック解説';
$pageDescription = '株価予測AIの仕組みを解説。テクニカル・ファンダメンタル・マクロの3視点、銘柄別AI、予測理由の見方。日本株の翌日上昇確率の算出方法。';
$canonicalUrl   = SITE_URL . '/ai_logic.php';
include __DIR__ . '/includes/header.php';
?>

<div class="ai-logic-page">

  <!-- ===== ヒーローセクション ===== -->
  <div class="logic-hero">
    <div class="logic-hero-inner">
      <div class="logic-hero-icon">🤖</div>
      <h1 class="logic-hero-title">AIはどうやって<br>明日の株価を予想しているの？</h1>
      <p class="logic-hero-sub">
        プロの投資家が毎日行っている情報収集と分析を、<br>
        AIが人間には不可能なスピードと量でなしているだけです。
      </p>
    </div>
  </div>

  <!-- ===== 3つの視点 ===== -->
  <section class="logic-section">
    <div class="logic-section-label">STEP 1</div>
    <h2 class="logic-section-title">AIは「3つの視点」で株価を分析する</h2>
    <p class="logic-section-desc">
      株価は様々な要因で動きます。AIは大きく分けて以下の3つの視点から情報を集め、総合的に判断を下しています。
      競馬に例えるとイメージしやすいかもしれません。
    </p>

    <div class="logic-cards">

      <div class="logic-card">
        <div class="logic-card-icon">📈</div>
        <div class="logic-card-badge">視点 ①</div>
        <h3 class="logic-card-title">過去の値動き<br><span>テクニカル分析</span></h3>
        <div class="logic-card-analogy">🏇 競馬でいうと…「その馬の過去のレース成績・最近の調子」</div>
        <p class="logic-card-desc">
          株価のチャート（グラフ）から、投資家たちの心理状態を読み取ります。
          「最近ずっと上がり続けているから、そろそろ利益確定の売りが増えそう」や
          「急激に下がりすぎたから、割安だと思って買う人が出てきそう」といった、
          過去のパターンから未来の動きを予測します。
        </p>
        <div class="logic-card-indicators">
          <span class="indicator-tag">RSI（買われすぎ・売られすぎ）</span>
          <span class="indicator-tag">移動平均線</span>
          <span class="indicator-tag">ゴールデンクロス</span>
          <span class="indicator-tag">出来高変化</span>
          <span class="indicator-tag">ボラティリティ</span>
        </div>
      </div>

      <div class="logic-card">
        <div class="logic-card-icon">🏢</div>
        <div class="logic-card-badge">視点 ②</div>
        <h3 class="logic-card-title">企業の基礎体力<br><span>ファンダメンタル分析</span></h3>
        <div class="logic-card-analogy">🏇 競馬でいうと…「その馬が走るレース場（芝かダートか）との相性」</div>
        <p class="logic-card-desc">
          その企業がどれくらい利益を出しているか、持っている資産に対して今の株価は
          高すぎないか（割安か割高か）を分析します。
          「この会社はしっかり稼いでいるのに、今の株価は安すぎる」とAIが判断すれば、
          それは「下がるリスクが少なく、上がりやすい」という強い根拠になります。
        </p>
        <div class="logic-card-indicators">
          <span class="indicator-tag">PER（株価収益率）</span>
          <span class="indicator-tag">PBR（株価純資産倍率）</span>
          <span class="indicator-tag">ROE（自己資本利益率）</span>
          <span class="indicator-tag">配当利回り</span>
          <span class="indicator-tag">ベータ値</span>
        </div>
      </div>

      <div class="logic-card">
        <div class="logic-card-icon">🌍</div>
        <div class="logic-card-badge">視点 ③</div>
        <h3 class="logic-card-title">世の中の経済状況<br><span>マクロ経済分析</span></h3>
        <div class="logic-card-analogy">🏇 競馬でいうと…「その日の天気・馬場の状態（全馬に影響する要素）」</div>
        <p class="logic-card-desc">
          どんなに良い企業でも、世の中全体の景気が悪ければ株価は下がってしまいます。
          AIは毎日、以下のような「世の中の空気」をチェックしています。
        </p>
        <div class="macro-table-wrap">
          <table class="macro-table">
            <thead>
              <tr><th>経済指標</th><th>影響を受けやすい企業</th></tr>
            </thead>
            <tbody>
              <tr><td>🇺🇸 為替（ドル円）</td><td>トヨタなど輸出企業（円安なら利益が増える）</td></tr>
              <tr><td>📊 日経平均・S&amp;P500</td><td>日米市場全体の盛り上がり具合</td></tr>
              <tr><td>😱 VIX（恐怖指数）</td><td>投資家が市場をどれだけ警戒しているか</td></tr>
              <tr><td>🛢️ 原油・金の価格</td><td>エネルギー企業・安全資産への逃避度合い</td></tr>
              <tr><td>💵 米国10年債利回り</td><td>グローバルな資金の流れ</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </section>

  <!-- ===== 銘柄ごとの個別AI ===== -->
  <section class="logic-section logic-section-dark">
    <div class="logic-section-label">STEP 2</div>
    <h2 class="logic-section-title">「全銘柄一律」ではなく<br>「銘柄ごとの専用AI」を作る</h2>
    <p class="logic-section-desc">
      このシステムでは、全ての銘柄を同じAIで予測しているわけではありません。
    </p>

    <div class="example-box">
      <div class="example-title">例）「円安になった」というニュースがあったとき</div>
      <div class="example-grid">
        <div class="example-item example-up">
          <div class="example-company">🚗 トヨタ（輸出企業）</div>
          <div class="example-arrow">&#8593; 株価が上がりやすい</div>
          <div class="example-reason">海外で稼いだお金が円に換算すると増えるため</div>
        </div>
        <div class="example-vs">VS</div>
        <div class="example-item example-down">
          <div class="example-company">🍜 食品メーカー（内需企業）</div>
          <div class="example-arrow">&#8595; 株価が下がりやすい</div>
          <div class="example-reason">輸入原材料のコストが上がるため</div>
        </div>
      </div>
    </div>

    <p class="logic-section-desc" style="margin-top:28px;">
      同じ経済ニュースでも銘柄によって影響が全く逆になることがあります。
      そのため、このシステムでは<strong>東証に上場している銘柄それぞれに対して、
      専用のAIモデルを個別に作成</strong>しています。
    </p>

    <div class="model-count-box">
      <div class="model-count-num"><?php
        $pdo = getPDO();
        echo number_format((int)$pdo->query("SELECT COUNT(*) FROM stock_master WHERE is_active=1")->fetchColumn());
      ?></div>
      <div class="model-count-label">個の専用AIモデルが稼働中</div>
    </div>
  </section>

  <!-- ===== 1日のスケジュール ===== -->
  <section class="logic-section">
    <div class="logic-section-label">STEP 3</div>
    <h2 class="logic-section-title">AIの1日のスケジュール</h2>
    <p class="logic-section-desc">AIは毎日、以下のスケジュールで自動的に動いています。</p>

    <div class="timeline">
      <div class="timeline-item">
        <div class="timeline-time">毎朝 6:00</div>
        <div class="timeline-content">
          <div class="timeline-title">📥 情報収集</div>
          <div class="timeline-desc">前日の株価・為替・経済指標などのデータを自動収集してデータベースに保存</div>
        </div>
      </div>
      <div class="timeline-item">
        <div class="timeline-time">毎朝 6:30</div>
        <div class="timeline-content">
          <div class="timeline-title">🔮 予測実行</div>
          <div class="timeline-desc">収集したデータをもとに、全銘柄の「本日の上昇確率」を計算して保存</div>
        </div>
      </div>
      <div class="timeline-item">
        <div class="timeline-time">毎朝 6:45</div>
        <div class="timeline-content">
          <div class="timeline-title">✅ 答え合わせ</div>
          <div class="timeline-desc">前日の予測が「当たっていたか外れていたか」を自動で記録・集計</div>
        </div>
      </div>
      <div class="timeline-item timeline-item-special">
        <div class="timeline-time">毎週日曜<br>深夜 2:00</div>
        <div class="timeline-content">
          <div class="timeline-title">🎓 再学習</div>
          <div class="timeline-desc">最新データを使ってAIを再学習。答え合わせの結果を活かしてより精度を高める</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== 予測理由の説明 ===== -->
  <section class="logic-section logic-section-dark">
    <div class="logic-section-label">STEP 4</div>
    <h2 class="logic-section-title">「なぜそう予想したのか？」を<br>AIが言葉で説明する</h2>
    <p class="logic-section-desc">
      このシステムの特徴として、AIは予測結果だけでなく
      <strong>「なぜそう判断したか」の理由も自動生成</strong>します。
      ブラックボックスではなく、根拠が見える透明なAIです。
    </p>

    <div class="reason-example-box">
      <div class="reason-example-header">
        <span class="reason-example-ticker">7203.T</span>
        <span class="reason-example-name">トヨタ自動車</span>
        <span class="reason-example-prob up">上昇確率 72.4%</span>
      </div>
      <div class="reason-example-summary">
        <div class="reason-label">📋 予測概要（3行）</div>
        <div class="reason-lines">
          <div class="reason-line">AIは翌営業日の株価が【上昇】すると予測しました（確率: 72.4%、確信度: 高い）。</div>
          <div class="reason-line">直近の値動き: 前日比 +1.8% と上昇しており、短期的な上昇モメンタムが継続しています。</div>
          <div class="reason-line">RSI分析: RSIが38.2とやや売られすぎ水準にあり、反発の可能性が高まっています。</div>
        </div>
      </div>
      <div class="reason-example-detail">
        <div class="reason-label">🔍 予測詳細（最大10行）</div>
        <div class="reason-lines">
          <div class="reason-line">AIは翌営業日の株価が【上昇】すると予測しました（確率: 72.4%、確信度: 高い）。</div>
          <div class="reason-line">直近の値動き: 前日比 +1.8% と上昇しており、短期的な上昇モメンタムが継続しています。</div>
          <div class="reason-line">RSI分析: RSIが38.2とやや売られすぎ水準にあり、反発の可能性が高まっています。</div>
          <div class="reason-line">移動平均線: 5日移動平均線が25日線を上回っており（乖離率 +2.3%）、上昇トレンドが継続しています。</div>
          <div class="reason-line">ボラティリティ: 当日の値幅が過去平均の1.4倍と大きく、相場の注目度が高まっています。</div>
          <div class="reason-line">出来高: 前日比 +62.3% と大幅に増加しており、強い買い意欲が確認されています。</div>
          <div class="reason-line">マクロ要因（ドル円レート）: 前日比 +0.9% の円安が進行しており、輸出企業であるトヨタの収益改善につながります。</div>
          <div class="reason-line">マクロ要因（VIX）: 恐怖指数が低下（前日比 -3.2%）しており、市場全体のリスク回避ムードが和らいでいます。</div>
          <div class="reason-line">ファンダメンタル面: PERが11.8倍と割安な水準にあり、下値不安が限定的と判断されています。</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== CTA ===== -->
  <div class="logic-cta">
    <a href="<?= SITE_URL ?>/" class="logic-cta-btn">
      &#8592; ランキングに戻る
    </a>
  </div>

</div>

<style>
/* ===== AI Logic Page Styles ===== */
.ai-logic-page { max-width: 900px; margin: 0 auto; padding-bottom: 60px; }

/* Hero */
.logic-hero {
  background: linear-gradient(135deg, #0d1a2e 0%, #0a1628 50%, #0d0f14 100%);
  border: 1px solid rgba(245,197,24,.25);
  border-radius: 20px;
  padding: 56px 32px;
  text-align: center;
  margin-bottom: 48px;
  position: relative;
  overflow: hidden;
}
.logic-hero::before {
  content: '';
  position: absolute;
  top: -60px; left: 50%; transform: translateX(-50%);
  width: 400px; height: 300px;
  background: radial-gradient(ellipse, rgba(245,197,24,.12) 0%, transparent 70%);
  pointer-events: none;
}
.logic-hero-icon { font-size: 3.5rem; margin-bottom: 16px; }
.logic-hero-title {
  font-size: clamp(1.4rem, 3vw, 2rem);
  font-weight: 800;
  color: #fff;
  line-height: 1.4;
  margin-bottom: 16px;
}
.logic-hero-sub { color: var(--text-secondary); font-size: .95rem; line-height: 1.7; }

/* Section */
.logic-section { margin-bottom: 48px; }
.logic-section-dark {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 40px 32px;
  margin-bottom: 48px;
}
.logic-section-label {
  display: inline-block;
  background: var(--accent);
  color: #000;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .1em;
  padding: 3px 10px;
  border-radius: 4px;
  margin-bottom: 12px;
}
.logic-section-title {
  font-size: clamp(1.2rem, 2.5vw, 1.6rem);
  font-weight: 800;
  color: #fff;
  margin-bottom: 16px;
  line-height: 1.4;
}
.logic-section-desc { color: var(--text-secondary); line-height: 1.8; margin-bottom: 24px; }

/* 3 Cards */
.logic-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
.logic-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 28px 24px;
  transition: border-color .2s, transform .2s;
}
.logic-card:hover { border-color: var(--accent); transform: translateY(-3px); }
.logic-card-icon { font-size: 2.2rem; margin-bottom: 10px; }
.logic-card-badge {
  display: inline-block;
  background: rgba(245,197,24,.15);
  color: var(--accent);
  font-size: .72rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
  margin-bottom: 10px;
}
.logic-card-title { font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 10px; line-height: 1.4; }
.logic-card-title span { color: var(--text-secondary); font-size: .85rem; font-weight: 400; }
.logic-card-analogy {
  background: rgba(245,197,24,.08);
  border-left: 3px solid var(--accent);
  padding: 8px 12px;
  font-size: .82rem;
  color: var(--text-secondary);
  border-radius: 0 6px 6px 0;
  margin-bottom: 12px;
  line-height: 1.5;
}
.logic-card-desc { font-size: .88rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 14px; }
.logic-card-indicators { display: flex; flex-wrap: wrap; gap: 6px; }
.indicator-tag {
  background: rgba(255,255,255,.06);
  border: 1px solid var(--border);
  color: var(--text-secondary);
  font-size: .75rem;
  padding: 3px 8px;
  border-radius: 20px;
}

/* Macro Table */
.macro-table-wrap { margin-top: 12px; overflow-x: auto; }
.macro-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.macro-table th { background: rgba(245,197,24,.1); color: var(--accent); padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--border); }
.macro-table td { padding: 8px 12px; color: var(--text-secondary); border-bottom: 1px solid rgba(255,255,255,.05); }
.macro-table tr:last-child td { border-bottom: none; }

/* Example Box */
.example-box {
  background: rgba(245,197,24,.05);
  border: 1px solid rgba(245,197,24,.2);
  border-radius: 12px;
  padding: 24px;
}
.example-title { font-size: .9rem; color: var(--accent); font-weight: 600; margin-bottom: 20px; }
.example-grid { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.example-item { flex: 1; min-width: 180px; background: var(--bg-card); border-radius: 10px; padding: 20px; text-align: center; }
.example-item.example-up { border: 1px solid rgba(38,194,129,.3); }
.example-item.example-down { border: 1px solid rgba(231,76,60,.3); }
.example-company { font-size: .9rem; font-weight: 600; color: #fff; margin-bottom: 10px; }
.example-arrow { font-size: 1.3rem; font-weight: 800; margin-bottom: 8px; }
.example-item.example-up .example-arrow { color: var(--up); }
.example-item.example-down .example-arrow { color: var(--down); }
.example-reason { font-size: .78rem; color: var(--text-muted); line-height: 1.5; }
.example-vs { font-size: 1.1rem; font-weight: 800; color: var(--text-muted); flex-shrink: 0; }

/* Model Count */
.model-count-box {
  display: flex;
  align-items: baseline;
  gap: 12px;
  justify-content: center;
  margin-top: 28px;
  padding: 24px;
  background: rgba(245,197,24,.08);
  border: 1px solid rgba(245,197,24,.2);
  border-radius: 12px;
}
.model-count-num { font-size: 3rem; font-weight: 900; color: var(--accent); }
.model-count-label { font-size: 1rem; color: var(--text-secondary); }

/* Timeline */
.timeline { display: flex; flex-direction: column; gap: 0; }
.timeline-item {
  display: flex;
  gap: 24px;
  padding: 20px 0;
  border-left: 2px solid var(--border);
  margin-left: 60px;
  padding-left: 28px;
  position: relative;
}
.timeline-item::before {
  content: '';
  position: absolute;
  left: -7px; top: 24px;
  width: 12px; height: 12px;
  background: var(--accent);
  border-radius: 50%;
  box-shadow: 0 0 8px rgba(245,197,24,.5);
}
.timeline-item-special::before { background: #fff; box-shadow: 0 0 12px rgba(255,255,255,.4); }
.timeline-time {
  position: absolute;
  left: -90px;
  top: 20px;
  width: 80px;
  text-align: right;
  font-size: .78rem;
  color: var(--accent);
  font-weight: 700;
  line-height: 1.4;
}
.timeline-title { font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 6px; }
.timeline-desc { font-size: .87rem; color: var(--text-secondary); line-height: 1.6; }

/* Reason Example */
.reason-example-box {
  background: var(--bg-primary);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}
.reason-example-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  background: rgba(255,255,255,.04);
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}
.reason-example-ticker { font-size: 1rem; font-weight: 700; color: var(--accent); }
.reason-example-name { font-size: .9rem; color: var(--text-secondary); }
.reason-example-prob { font-size: .85rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; margin-left: auto; }
.reason-example-prob.up { background: rgba(38,194,129,.15); color: var(--up); }
.reason-example-summary, .reason-example-detail { padding: 20px 24px; }
.reason-example-detail { border-top: 1px solid var(--border); }
.reason-label { font-size: .8rem; font-weight: 700; color: var(--accent); margin-bottom: 12px; }
.reason-lines { display: flex; flex-direction: column; gap: 8px; }
.reason-line {
  font-size: .87rem;
  color: var(--text-secondary);
  line-height: 1.6;
  padding: 8px 12px;
  background: rgba(255,255,255,.03);
  border-radius: 6px;
  border-left: 2px solid rgba(245,197,24,.3);
}

/* CTA */
.logic-cta { text-align: center; margin-top: 40px; }
.logic-cta-btn {
  display: inline-block;
  background: var(--accent);
  color: #000;
  font-weight: 700;
  padding: 14px 32px;
  border-radius: 8px;
  text-decoration: none;
  font-size: .95rem;
  transition: opacity .2s;
}
.logic-cta-btn:hover { opacity: .85; }

/* Responsive */
@media (max-width: 600px) {
  .logic-section-dark { padding: 28px 20px; }
  .timeline-item { margin-left: 50px; }
  .timeline-time { left: -72px; width: 64px; font-size: .72rem; }
  .example-grid { flex-direction: column; }
  .example-vs { align-self: center; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
