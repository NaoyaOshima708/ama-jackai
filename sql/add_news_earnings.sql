-- =====================================================
-- 追加テーブル: market_news（世界・日本情勢ニュース）
-- =====================================================
CREATE TABLE IF NOT EXISTS market_news (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    published_at DATETIME     NOT NULL,
    source       VARCHAR(100) NOT NULL DEFAULT '',
    category     VARCHAR(50)  NOT NULL DEFAULT 'general'
                 COMMENT 'general/geopolitics/japan/us/economy',
    title        TEXT         NOT NULL,
    url          TEXT         NOT NULL,
    sentiment    FLOAT        NULL     COMMENT '-1.0(悲観)〜+1.0(楽観)',
    fetched_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_published (published_at),
    INDEX idx_category  (category),
    INDEX idx_fetched   (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 追加テーブル: earnings_calendar（決算スケジュール）
-- =====================================================
CREATE TABLE IF NOT EXISTS earnings_calendar (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    ticker            VARCHAR(20)  NOT NULL,
    earnings_date     DATE         NOT NULL COMMENT '決算発表予定日',
    fiscal_period     VARCHAR(20)  NOT NULL DEFAULT '' COMMENT '例: 2025Q3',
    eps_estimate      FLOAT        NULL,
    eps_actual        FLOAT        NULL,
    revenue_estimate  BIGINT       NULL,
    revenue_actual    BIGINT       NULL,
    surprise_pct      FLOAT        NULL COMMENT 'EPS乖離率(%)',
    fetched_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticker_date (ticker, earnings_date),
    INDEX idx_earnings_date (earnings_date),
    INDEX idx_ticker        (ticker)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- stock_prediction テーブルに down_probability カラム追加
-- （上昇確率の逆数ではなく、独立したスコアとして管理）
-- =====================================================
ALTER TABLE stock_prediction
    ADD COLUMN IF NOT EXISTS down_probability FLOAT NULL
        COMMENT '下落確率(0-100)' AFTER up_probability,
    ADD COLUMN IF NOT EXISTS news_sentiment   FLOAT NULL
        COMMENT '予測時点のニュースセンチメント(-1〜+1)' AFTER down_probability,
    ADD COLUMN IF NOT EXISTS has_earnings     TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '予測日に決算発表があるか' AFTER news_sentiment;
