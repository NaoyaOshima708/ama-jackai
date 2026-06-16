<?php
/**
 * ページビュー（PV）の記録・取得
 * テーブル作成は install_page_views.php を1回実行してください。
 */

function getPageViewPath(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = $path === '' || $path === false ? '/' : $path;
    return $path === '/index.php' ? '/' : $path;
}

function incrementPageView(): void {
    try {
        $pdo = getPDO();
        $path = getPageViewPath();
        $date = date('Y-m-d');

        $stmt = $pdo->prepare("
            INSERT INTO page_views (page_path, view_date, count)
            VALUES (:path, :view_date, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ");
        $stmt->execute([
            ':path'      => $path,
            ':view_date' => $date,
        ]);
    } catch (Throwable $e) {
        // テーブル未作成などで失敗しても画面表示は続行
        error_log('PV increment failed: ' . $e->getMessage());
    }
}

function getTotalPageViews(): int {
    try {
        $pdo = getPDO();
        $v = $pdo->query("SELECT COALESCE(SUM(count), 0) FROM page_views")->fetchColumn();
        return (int) $v;
    } catch (Throwable $e) {
        return 0;
    }
}
