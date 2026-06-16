-- ページビュー（PV）記録用テーブル
-- 実行方法: MySQL にログインして source create_page_views.sql または
--          phpMyAdmin でインポート / クエリで実行

CREATE TABLE IF NOT EXISTS page_views (
    page_path VARCHAR(255) NOT NULL COMMENT 'ページパス（例: /, /detail.php）',
    view_date DATE NOT NULL COMMENT '閲覧日',
    count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'その日のPV数',
    PRIMARY KEY (page_path, view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='日別・パス別PV';
