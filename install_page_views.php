<?php
/**
 * ページビュー用テーブルを1回だけ作成するスクリプト
 * ブラウザでこのファイルにアクセスするか、CLI で php install_page_views.php を実行してください。
 * 完了したら削除するか、.htaccess でアクセス禁止にすることを推奨します。
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = getPDO();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS page_views (
            page_path VARCHAR(255) NOT NULL,
            view_date DATE NOT NULL,
            count INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (page_path, view_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo '<p>page_views テーブルを作成しました。</p>';
    echo '<p>このファイル（install_page_views.php）は削除するか、アクセス制限をかけてください。</p>';
} catch (Throwable $e) {
    echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
