#!/usr/bin/env python3
"""
fetch_news_earnings.py
======================
世界・日本情勢ニュースと各銘柄の決算スケジュールを取得してDBに保存する。

【ニュース取得】
  - gnews ライブラリ（Google News スクレイピング、無料・APIキー不要）
  - カテゴリ: 日本経済, 世界情勢(地政学), 米国経済, 一般マーケット

【決算情報取得】
  - yfinance の ticker.calendar / ticker.earnings_dates を使用
  - stock_master テーブルの全アクティブ銘柄を対象

実行方法:
  /var/ml_project/venv/bin/python fetch_news_earnings.py

Cron設定例（毎日 5:30 に実行 → fetch_data.py の前）:
  30 5 * * * /var/ml_project/venv/bin/python /var/ml_project/fetch_news_earnings.py >> /var/log/ml_project/fetch_news_earnings.log 2>&1
"""

import sys
import os
import logging
import datetime
import time

# ── パス設定 ──────────────────────────────────────────────────
sys.path.insert(0, '/var/ml_project')
from config import DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET

import mysql.connector
import yfinance as yf

# gnews がインストールされていない場合は pip install gnews
try:
    from gnews import GNews
    GNEWS_AVAILABLE = True
except ImportError:
    GNEWS_AVAILABLE = False
    logging.warning("gnews がインストールされていません。pip install gnews を実行してください。")

# ── ロギング設定 ──────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger(__name__)

# ── DB接続 ────────────────────────────────────────────────────
def get_conn():
    return mysql.connector.connect(
        host=DB_HOST, database=DB_NAME,
        user=DB_USER, password=DB_PASS,
        charset=DB_CHARSET, autocommit=False
    )

# ── ニュース取得 ──────────────────────────────────────────────
NEWS_QUERIES = [
    ('japan',       '日本経済 株式市場'),
    ('japan',       '日本 政治 経済'),
    ('geopolitics', '戦争 地政学 リスク'),
    ('geopolitics', 'ウクライナ 中東 情勢'),
    ('us',          '米国 経済 FRB 金利'),
    ('us',          'Trump tariff trade war'),
    ('economy',     '世界経済 景気後退 リセッション'),
    ('economy',     'China economy slowdown'),
]

def fetch_news(conn):
    """Google Newsからニュースを取得してDBに保存する"""
    if not GNEWS_AVAILABLE:
        logger.warning("gnews 未インストールのためニュース取得をスキップします")
        return

    gn = GNews(language='ja', country='JP', period='1d', max_results=10)
    cursor = conn.cursor()
    inserted = 0

    for category, query in NEWS_QUERIES:
        try:
            logger.info(f"ニュース取得: [{category}] {query}")
            articles = gn.get_news(query)
            for art in articles:
                try:
                    pub_dt = art.get('published date', '')
                    # 日時パース
                    try:
                        from email.utils import parsedate_to_datetime
                        dt = parsedate_to_datetime(pub_dt)
                        published_at = dt.strftime('%Y-%m-%d %H:%M:%S')
                    except Exception:
                        published_at = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')

                    title = art.get('title', '')[:500]
                    url   = art.get('url', '')[:1000]
                    src   = art.get('publisher', {}).get('title', '')[:100] if isinstance(art.get('publisher'), dict) else str(art.get('publisher', ''))[:100]

                    # 簡易センチメント（キーワードベース）
                    sentiment = _simple_sentiment(title)

                    cursor.execute("""
                        INSERT IGNORE INTO market_news
                            (published_at, source, category, title, url, sentiment)
                        VALUES (%s, %s, %s, %s, %s, %s)
                    """, (published_at, src, category, title, url, sentiment))
                    inserted += cursor.rowcount
                except Exception as e:
                    logger.warning(f"記事挿入エラー: {e}")
            time.sleep(1)
        except Exception as e:
            logger.error(f"ニュース取得エラー [{query}]: {e}")

    conn.commit()
    cursor.close()
    logger.info(f"ニュース挿入完了: {inserted}件")

def _simple_sentiment(text: str) -> float:
    """
    タイトルのキーワードから簡易センチメントスコアを計算する。
    -1.0（悲観）〜 +1.0（楽観）
    """
    positive_words = ['上昇', '回復', '好調', '成長', '利益', '黒字', '増益', '強気',
                      'rally', 'growth', 'gain', 'rise', 'positive', 'strong']
    negative_words = ['下落', '暴落', '悪化', '懸念', '戦争', 'リスク', '不安', '赤字',
                      '減益', '弱気', 'crash', 'war', 'risk', 'fall', 'decline',
                      'recession', 'tariff', '関税', '制裁', '紛争']
    text_lower = text.lower()
    pos = sum(1 for w in positive_words if w in text_lower)
    neg = sum(1 for w in negative_words if w in text_lower)
    total = pos + neg
    if total == 0:
        return 0.0
    return round((pos - neg) / total, 3)

# ── 決算情報取得 ──────────────────────────────────────────────
def fetch_earnings(conn):
    """yfinance から各銘柄の決算スケジュールを取得してDBに保存する"""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT ticker FROM stock_master WHERE is_active = 1 ORDER BY ticker")
    tickers = [row['ticker'] for row in cursor.fetchall()]
    cursor.close()

    logger.info(f"決算情報取得対象: {len(tickers)}銘柄")
    inserted = 0
    updated  = 0

    for ticker in tickers:
        try:
            yfobj = yf.Ticker(ticker)

            # --- calendar（次回決算日）---
            try:
                cal = yfobj.calendar
                if cal is not None and not cal.empty:
                    # calendar は DataFrame または dict
                    if hasattr(cal, 'to_dict'):
                        cal_dict = cal.to_dict()
                    else:
                        cal_dict = cal

                    # 'Earnings Date' キーを探す
                    earnings_dates = cal_dict.get('Earnings Date', [])
                    if not isinstance(earnings_dates, list):
                        earnings_dates = [earnings_dates]

                    for ed in earnings_dates:
                        if ed is None:
                            continue
                        if hasattr(ed, 'date'):
                            ed = ed.date()
                        _upsert_earnings(conn, ticker, str(ed), '', None, None, None, None)
                        inserted += 1
            except Exception as e:
                logger.debug(f"{ticker} calendar エラー: {e}")

            # --- earnings_dates（過去〜将来の決算日リスト）---
            try:
                ed_df = yfobj.get_earnings_dates(limit=8)
                if ed_df is not None and not ed_df.empty:
                    for idx, row in ed_df.iterrows():
                        try:
                            date_str = str(idx.date()) if hasattr(idx, 'date') else str(idx)[:10]
                            eps_est  = float(row.get('EPS Estimate', None) or 0) or None
                            eps_act  = float(row.get('Reported EPS', None) or 0) or None
                            surp     = float(row.get('Surprise(%)', None) or 0) or None
                            _upsert_earnings(conn, ticker, date_str, '', eps_est, eps_act, None, surp)
                            updated += 1
                        except Exception:
                            pass
            except Exception as e:
                logger.debug(f"{ticker} earnings_dates エラー: {e}")

            time.sleep(0.3)

        except Exception as e:
            logger.error(f"{ticker} 決算情報取得エラー: {e}")

    conn.commit()
    logger.info(f"決算情報 挿入/更新完了: insert={inserted}, update={updated}")

def _upsert_earnings(conn, ticker, earnings_date, fiscal_period,
                     eps_estimate, eps_actual, revenue_estimate, surprise_pct):
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO earnings_calendar
            (ticker, earnings_date, fiscal_period, eps_estimate, eps_actual,
             revenue_estimate, surprise_pct)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            fiscal_period    = VALUES(fiscal_period),
            eps_estimate     = COALESCE(VALUES(eps_estimate),     eps_estimate),
            eps_actual       = COALESCE(VALUES(eps_actual),       eps_actual),
            revenue_estimate = COALESCE(VALUES(revenue_estimate), revenue_estimate),
            surprise_pct     = COALESCE(VALUES(surprise_pct),     surprise_pct),
            fetched_at       = CURRENT_TIMESTAMP
    """, (ticker, earnings_date, fiscal_period,
          eps_estimate, eps_actual, revenue_estimate, surprise_pct))
    cursor.close()

# ── 古いニュースを削除（30日以上前） ─────────────────────────
def cleanup_old_news(conn):
    cursor = conn.cursor()
    cursor.execute("""
        DELETE FROM market_news
        WHERE published_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    """)
    deleted = cursor.rowcount
    conn.commit()
    cursor.close()
    logger.info(f"古いニュース削除: {deleted}件")

# ── メイン ────────────────────────────────────────────────────
def main():
    logger.info("=== fetch_news_earnings.py 開始 ===")
    conn = get_conn()
    try:
        fetch_news(conn)
        fetch_earnings(conn)
        cleanup_old_news(conn)
    finally:
        conn.close()
    logger.info("=== fetch_news_earnings.py 完了 ===")

if __name__ == '__main__':
    main()
