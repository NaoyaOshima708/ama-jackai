<?php
require_once __DIR__ . '/pv.php';
incrementPageView();

$searchQuery = htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8');
$currentPage = basename($_SERVER['PHP_SELF']);
$lineUser    = $_SESSION['line_user'] ?? null;

// SEO用（各ページで上書き可能）
$seoTitle       = isset($pageTitle) ? (htmlspecialchars($pageTitle) . ' | ' . SITE_NAME) : SITE_NAME;
$seoDescription = isset($pageDescription) ? $pageDescription : '日本株の翌日上昇確率をAIが毎日予測。東証上場銘柄の株価予測・テクニカル・ファンダメンタル分析。LINEログインで予測理由の詳細を確認できます。';
$seoCanonical   = $canonicalUrl ?? (SITE_URL . rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/');
$seoOgImage    = $pageOgImage ?? (SITE_URL . '/ogp.png');
$seoOgType     = $pageOgType ?? 'website';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/svg+xml" href="<?= SITE_URL ?>/favicon.svg">
  <title><?= $seoTitle ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescription) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($seoCanonical) ?>">
  <meta name="robots" content="index, follow">
  <meta name="format-detection" content="telephone=no">

  <!-- OGP（日本語・日本向け） -->
  <meta property="og:type" content="<?= htmlspecialchars($seoOgType) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($seoCanonical) ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars(SITE_NAME) ?>">
  <meta property="og:locale" content="ja_JP">
  <meta property="og:image" content="<?= htmlspecialchars($seoOgImage) ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($seoDescription) ?>">
  <?php if (!empty($seoOgImage)): ?>
  <meta name="twitter:image" content="<?= htmlspecialchars($seoOgImage) ?>">
  <?php endif; ?>

  <!-- 構造化データ（JSON-LD）日本向け -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "<?= htmlspecialchars(SITE_NAME) ?>",
    "url": "<?= htmlspecialchars(SITE_URL) ?>",
    "description": "<?= htmlspecialchars($seoDescription) ?>",
    "inLanguage": "ja",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "<?= htmlspecialchars(SITE_URL) ?>/search.php?q={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a href="<?= SITE_URL ?>/" class="site-logo">
      <div class="logo-icon">&#9650;</div>
      <span><?= SITE_NAME ?></span>
    </a>
    <form class="search-form" action="<?= SITE_URL ?>/search.php" method="get">
      <input
        type="text"
        name="q"
        class="search-input"
        placeholder="銘柄コード・銘柄名で検索（例: 7203 / トヨタ）"
        value="<?= $searchQuery ?>"
        autocomplete="off"
        id="searchInput"
      >
      <button type="submit" class="btn btn-primary">検索</button>
    </form>
    <div class="header-actions">
      <?php if ($lineUser): ?>
        <span class="header-user-label">
          LINEログイン中:
          <strong style="color:var(--text-primary);">
            <?= htmlspecialchars($lineUser['display_name'] ?: 'ゲスト') ?>
          </strong>
        </span>

        <a href="<?= SITE_URL ?>/logout.php" class="btn btn-outline">ログアウト</a>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/line_login.php" class="btn btn-line">LINEでログイン</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="main-content">
