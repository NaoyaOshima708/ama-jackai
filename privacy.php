<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'プライバシーポリシー';
$pageDescription = 'AI株価予測サイトのプライバシーポリシーです。';
$canonicalUrl = SITE_URL . '/privacy.php';
$lineUser = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

include __DIR__ . '/includes/header.php';
?>

<div class="section-header">
  <h2 class="section-title">プライバシーポリシー</h2>
</div>

<div class="content-box" style="background: var(--bg-card); padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); line-height: 1.8; color: var(--text-main);">
  <p>AI株価予測（以下、「本サービス」といいます。）は、本サービスをご利用になる皆様（以下、「ユーザー」といいます。）のプライバシーを尊重し、個人情報の管理に細心の注意を払い、これを適正に取り扱います。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第1条（個人情報の定義）</h3>
  <p>本プライバシーポリシーにおいて、個人情報とは、個人情報保護法第2条第1項により定義された個人情報、すなわち、生存する個人に関する情報であって、当該情報に含まれる氏名、生年月日その他の記述等により特定の個人を識別することができるもの（他の情報と容易に照合することができ、それにより特定の個人を識別することができることとなるものを含みます。）を意味するものとします。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第2条（取得する情報）</h3>
  <p>本サービスでは、LINEログイン機能を利用して以下の情報を取得します。</p>
  <ul style="margin-left: 20px; margin-bottom: 15px;">
    <li>LINEのユーザー識別子（ユーザーID）</li>
    <li>LINEのプロフィール名</li>
    <li>LINEのプロフィール画像</li>
  </ul>
  <p>これらの情報は、ユーザーがLINEログイン画面にて同意した場合にのみ取得されます。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第3条（個人情報の利用目的）</h3>
  <p>本サービスは、取得した個人情報を以下の目的で利用します。</p>
  <ul style="margin-left: 20px; margin-bottom: 15px;">
    <li>本サービスの提供、維持、保護及び改善のため</li>
    <li>ユーザーの本人確認およびログイン状態の維持のため</li>
    <li>本サービスに関するご案内、お問い合わせ等への対応のため</li>
    <li>本サービスに関する利用規約、ポリシー等に違反する行為に対する対応のため</li>
    <li>LINE Messaging APIを利用した通知メッセージの送信のため</li>
  </ul>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第4条（個人情報の第三者提供）</h3>
  <p>本サービスは、個人情報保護法その他の法令に基づき開示が認められる場合を除くほか、あらかじめユーザーの同意を得ないで、個人情報を第三者に提供しません。ただし、次に掲げる場合は上記に定める第三者への提供には該当しません。</p>
  <ul style="margin-left: 20px; margin-bottom: 15px;">
    <li>本サービスが利用目的の達成に必要な範囲内において個人情報の取扱いの全部または一部を委託する場合</li>
    <li>合併その他の事由による事業の承継に伴って個人情報が提供される場合</li>
  </ul>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第5条（アクセス解析ツールについて）</h3>
  <p>本サービスでは、Googleによるアクセス解析ツール「Google Analytics」を利用しています。このGoogle Analyticsはトラフィックデータの収集のためにCookieを使用しています。このトラフィックデータは匿名で収集されており、個人を特定するものではありません。この機能はCookieを無効にすることで収集を拒否することが出来ますので、お使いのブラウザの設定をご確認ください。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第6条（免責事項）</h3>
  <p>本サービスは、AIによる株価の予測情報を提供するものであり、投資の勧誘や特定の銘柄の売買を推奨するものではありません。本サービスの情報を用いて行う一切の行為、被った損害・損失に対しては、一切の責任を負いかねます。投資に関する最終的な決定は、ユーザーご自身の判断でなさるようお願いいたします。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第7条（プライバシーポリシーの変更）</h3>
  <p>本サービスは、個人情報の取扱いに関する運用状況を適宜見直し、継続的な改善に努めるものとし、必要に応じて、本プライバシーポリシーを変更することがあります。変更した場合には、本サイト上に掲載いたします。</p>

  <p style="margin-top: 40px; text-align: right;">制定日：2026年3月24日</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
