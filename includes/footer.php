</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div style="font-weight:700; color: var(--accent); margin-bottom:8px;"><?= SITE_NAME ?></div>
    <p class="footer-note">
      本サービスの予測結果は機械学習モデルによる参考情報であり、投資判断を推奨するものではありません。<br>
      株式投資には元本割れのリスクがあります。投資は自己責任でお願いいたします。<br>
      データ更新: 毎営業日 午前7:30 頃
    </p>
    <p style="margin-top:12px; font-size:.8rem;">
      <a href="<?= SITE_URL ?>/privacy.php" style="color:var(--text-sub); text-decoration:none; margin-right:16px;">プライバシーポリシー</a>
      <a href="<?= SITE_URL ?>/terms.php" style="color:var(--text-sub); text-decoration:none;">利用規約</a>
    </p>
    <p style="margin-top:8px; font-size:.75rem;">
      &copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.
      <?php if (function_exists('getTotalPageViews')): ?>
      <span class="footer-pv">｜ 総PV: <?= number_format(getTotalPageViews()) ?></span>
      <?php endif; ?>
    </p>
  </div>
</footer>

<script src="<?= SITE_URL ?>/js/main.js"></script>
</body>
</html>
