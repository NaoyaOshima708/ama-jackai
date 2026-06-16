<?php
require_once __DIR__ . '/config.php';
$pageTitle = '利用規約';
$pageDescription = 'AI株価予測サイトの利用規約です。';
$canonicalUrl = SITE_URL . '/terms.php';
$lineUser = $_SESSION['line_user'] ?? null;
$isLoggedIn = (bool)$lineUser;

include __DIR__ . '/includes/header.php';
?>

<div class="section-header">
  <h2 class="section-title">利用規約</h2>
</div>

<div class="content-box" style="background: var(--bg-card); padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); line-height: 1.8; color: var(--text-main);">
  <p>この利用規約（以下、「本規約」といいます。）は、AI株価予測（以下、「本サービス」といいます。）の利用条件を定めるものです。本サービスをご利用になる皆様（以下、「ユーザー」といいます。）には、本規約に従って、本サービスをご利用いただきます。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第1条（適用）</h3>
  <p>本規約は、ユーザーと本サービスとの間の本サービスの利用に関わる一切の関係に適用されるものとします。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第2条（利用登録）</h3>
  <p>本サービスにおいては、ユーザーが本規約に同意の上、LINEアカウントを用いたログイン（以下、「LINEログイン」といいます。）を行うことによって、利用登録が完了するものとします。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第3条（禁止事項）</h3>
  <p>ユーザーは、本サービスの利用にあたり、以下の行為をしてはなりません。</p>
  <ul style="margin-left: 20px; margin-bottom: 15px;">
    <li>法令または公序良俗に違反する行為</li>
    <li>犯罪行為に関連する行為</li>
    <li>本サービスの内容等、本サービスに含まれる著作権、商標権ほか知的財産権を侵害する行為</li>
    <li>本サービスによって得られた情報を商業的に利用する行為（無断転載、販売等）</li>
    <li>本サービスのサーバーまたはネットワークの機能を破壊したり、妨害したりする行為</li>
    <li>本サービスの運営を妨害するおそれのある行為</li>
    <li>不正アクセスをし、またはこれを試みる行為</li>
    <li>他のユーザーに関する個人情報等を収集または蓄積する行為</li>
    <li>他のユーザーに成りすます行為</li>
    <li>その他、本サービスが不適切と判断する行為</li>
  </ul>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第4条（本サービスの提供の停止等）</h3>
  <p>本サービスは、以下のいずれかの事由があると判断した場合、ユーザーに事前に通知することなく本サービスの全部または一部の提供を停止または中断することができるものとします。</p>
  <ul style="margin-left: 20px; margin-bottom: 15px;">
    <li>本サービスにかかるコンピュータシステムの保守点検または更新を行う場合</li>
    <li>地震、落雷、火災、停電または天災などの不可抗力により、本サービスの提供が困難となった場合</li>
    <li>コンピュータまたは通信回線等が事故により停止した場合</li>
    <li>その他、本サービスが提供が困難と判断した場合</li>
  </ul>
  <p>本サービスは、本サービスの提供の停止または中断により、ユーザーまたは第三者が被ったいかなる不利益または損害についても、一切の責任を負わないものとします。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第5条（免責事項）</h3>
  <p>本サービスは、AIによる株価の予測情報を提供するものであり、その正確性、完全性、有用性、最新性、適切性、確実性について、いかなる保証もするものではありません。</p>
  <p>本サービスは、投資の勧誘や特定の銘柄の売買を推奨するものではありません。本サービスの情報を用いて行う一切の行為、被った損害・損失に対しては、一切の責任を負いかねます。投資に関する最終的な決定は、ユーザーご自身の判断でなさるようお願いいたします。</p>
  <p>本サービスは、本サービスに関して、ユーザーと他のユーザーまたは第三者との間において生じた取引、連絡または紛争等について一切責任を負いません。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第6条（サービス内容の変更等）</h3>
  <p>本サービスは、ユーザーに通知することなく、本サービスの内容を変更しまたは本サービスの提供を中止することができるものとし、これによってユーザーに生じた損害について一切の責任を負いません。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第7条（利用規約の変更）</h3>
  <p>本サービスは、必要と判断した場合には、ユーザーに通知することなくいつでも本規約を変更することができるものとします。なお、本規約の変更後、本サービスの利用を開始した場合には、当該ユーザーは変更後の規約に同意したものとみなします。</p>

  <h3 style="color: var(--primary-color); margin-top: 30px; margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">第8条（準拠法・裁判管轄）</h3>
  <p>本規約の解釈にあたっては、日本法を準拠法とします。本サービスに関して紛争が生じた場合には、本サービスの運営者の所在地を管轄する裁判所を専属的合意管轄とします。</p>

  <p style="margin-top: 40px; text-align: right;">制定日：2026年3月24日</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
