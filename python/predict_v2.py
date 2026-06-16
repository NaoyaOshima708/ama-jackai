#!/usr/bin/env python3
"""
predict_v2.py
=============
既存の predict.py を拡張し、以下の機能を追加する。

1. ニュースセンチメントを特徴量として組み込む
2. 決算発表日フラグを特徴量として組み込む
3. down_probability（下落確率）を stock_prediction に保存する
4. 予測理由にニュース・決算情報を反映する

既存の predict.py と差し替えて使用する。
"""

import sys
import os
import logging
import datetime
import pickle
import warnings
import numpy as np
import pandas as pd

warnings.filterwarnings('ignore')

sys.path.insert(0, '/var/ml_project')
from config import DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, MODEL_DIR

import mysql.connector
import yfinance as yf

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

# ── ニュースセンチメント取得 ──────────────────────────────────
def get_news_sentiment(conn, target_date: str) -> float:
    """
    target_date の直前3日間のニュースセンチメント平均を返す。
    データがない場合は 0.0 を返す。
    """
    cursor = conn.cursor()
    cursor.execute("""
        SELECT AVG(sentiment)
        FROM market_news
        WHERE published_at >= DATE_SUB(%s, INTERVAL 3 DAY)
          AND published_at <= %s
          AND sentiment IS NOT NULL
    """, (target_date, target_date))
    row = cursor.fetchone()
    cursor.close()
    val = row[0] if row and row[0] is not None else 0.0
    return float(val)

# ── 決算発表フラグ取得 ────────────────────────────────────────
def get_earnings_flags(conn, target_date: str) -> dict:
    """
    target_date に決算発表がある銘柄の辞書 {ticker: True} を返す。
    前後3日以内の決算も考慮する。
    """
    cursor = conn.cursor()
    cursor.execute("""
        SELECT DISTINCT ticker
        FROM earnings_calendar
        WHERE earnings_date BETWEEN DATE_SUB(%s, INTERVAL 1 DAY)
                                AND DATE_ADD(%s, INTERVAL 2 DAY)
    """, (target_date, target_date))
    rows = cursor.fetchall()
    cursor.close()
    return {row[0]: True for row in rows}

# ── 直近の決算サプライズ取得 ──────────────────────────────────
def get_earnings_surprise(conn, ticker: str) -> float:
    """
    直近の決算サプライズ率（%）を返す。なければ 0.0。
    """
    cursor = conn.cursor()
    cursor.execute("""
        SELECT surprise_pct
        FROM earnings_calendar
        WHERE ticker = %s
          AND eps_actual IS NOT NULL
          AND earnings_date <= CURDATE()
        ORDER BY earnings_date DESC
        LIMIT 1
    """, (ticker,))
    row = cursor.fetchone()
    cursor.close()
    return float(row[0]) if row and row[0] is not None else 0.0

# ── テクニカル特徴量計算（既存 predict.py から移植） ─────────
def calc_features(df: pd.DataFrame) -> pd.Series | None:
    """
    直近の株価データから特徴量を計算して返す。
    """
    if df is None or len(df) < 30:
        return None

    df = df.copy().sort_index()
    close = df['Close'].astype(float)
    volume = df['Volume'].astype(float)

    feat = {}

    # リターン系
    feat['return_1d']  = close.pct_change(1).iloc[-1]
    feat['return_5d']  = close.pct_change(5).iloc[-1]
    feat['return_20d'] = close.pct_change(20).iloc[-1]

    # 移動平均
    ma5  = close.rolling(5).mean()
    ma25 = close.rolling(25).mean()
    ma75 = close.rolling(75).mean() if len(close) >= 75 else close.rolling(len(close)).mean()

    feat['ma5_ratio']  = (close.iloc[-1] / ma5.iloc[-1]  - 1) if ma5.iloc[-1]  else 0
    feat['ma25_ratio'] = (close.iloc[-1] / ma25.iloc[-1] - 1) if ma25.iloc[-1] else 0
    feat['ma75_ratio'] = (close.iloc[-1] / ma75.iloc[-1] - 1) if ma75.iloc[-1] else 0

    # ゴールデン/デッドクロス
    feat['golden_cross'] = 1 if (ma5.iloc[-1] > ma25.iloc[-1] and ma5.iloc[-2] <= ma25.iloc[-2]) else 0
    feat['dead_cross']   = 1 if (ma5.iloc[-1] < ma25.iloc[-1] and ma5.iloc[-2] >= ma25.iloc[-2]) else 0

    # RSI(14)
    delta = close.diff()
    gain  = delta.clip(lower=0).rolling(14).mean()
    loss  = (-delta.clip(upper=0)).rolling(14).mean()
    rs    = gain / loss.replace(0, np.nan)
    feat['rsi_14'] = float((100 - 100 / (1 + rs)).iloc[-1]) if not rs.isna().iloc[-1] else 50.0

    # ボラティリティ
    feat['volatility_20d'] = float(close.pct_change().rolling(20).std().iloc[-1] or 0)

    # 出来高比率
    vol_ma20 = volume.rolling(20).mean()
    feat['volume_ratio'] = float(volume.iloc[-1] / vol_ma20.iloc[-1]) if vol_ma20.iloc[-1] else 1.0

    # 高値・安値からの乖離
    high_52w = close.rolling(min(252, len(close))).max().iloc[-1]
    low_52w  = close.rolling(min(252, len(close))).min().iloc[-1]
    feat['from_high_52w'] = (close.iloc[-1] / high_52w - 1) if high_52w else 0
    feat['from_low_52w']  = (close.iloc[-1] / low_52w  - 1) if low_52w  else 0

    return pd.Series(feat)

# ── マクロ特徴量取得（DBから） ────────────────────────────────
def get_macro_features(conn, target_date: str) -> dict:
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT * FROM macro_indicators
        WHERE date <= %s
        ORDER BY date DESC
        LIMIT 1
    """, (target_date,))
    row = cursor.fetchone()
    cursor.close()
    if not row:
        return {}
    return {
        'usdjpy':    float(row.get('usdjpy', 0) or 0),
        'vix':       float(row.get('vix', 0) or 0),
        'nikkei':    float(row.get('nikkei', 0) or 0),
        'us10y':     float(row.get('us10y', 0) or 0),
        'sp500':     float(row.get('sp500', 0) or 0),
        'wti':       float(row.get('wti', 0) or 0),
        'gold':      float(row.get('gold', 0) or 0),
    }

# ── ファンダメンタル特徴量取得（DBから） ─────────────────────
def get_fundamental_features(conn, ticker: str) -> dict:
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT per, pbr, roe, dividend_yield, beta
        FROM stock_master
        WHERE ticker = %s
    """, (ticker,))
    row = cursor.fetchone()
    cursor.close()
    if not row:
        return {}
    return {
        'per':            float(row.get('per', 0) or 0),
        'pbr':            float(row.get('pbr', 0) or 0),
        'roe':            float(row.get('roe', 0) or 0),
        'dividend_yield': float(row.get('dividend_yield', 0) or 0),
        'beta':           float(row.get('beta', 1) or 1),
    }

# ── 予測理由生成 ──────────────────────────────────────────────
def build_reason(ticker, company_name, up_prob, down_prob, feat_dict,
                 macro, fundamental, news_sentiment, has_earnings,
                 earnings_surprise, news_headlines):
    """予測理由のサマリー（3行）と詳細（10行）を生成する"""

    rsi      = feat_dict.get('rsi_14', 50)
    ret_1d   = feat_dict.get('return_1d', 0) * 100
    ret_5d   = feat_dict.get('return_5d', 0) * 100
    ma5_r    = feat_dict.get('ma5_ratio', 0) * 100
    ma25_r   = feat_dict.get('ma25_ratio', 0) * 100
    vol_r    = feat_dict.get('volume_ratio', 1)
    gc       = feat_dict.get('golden_cross', 0)
    dc       = feat_dict.get('dead_cross', 0)
    vix      = macro.get('vix', 0)
    usdjpy   = macro.get('usdjpy', 0)
    per      = fundamental.get('per', 0)
    beta     = fundamental.get('beta', 1)

    # ── サマリー（3行） ──
    direction = "上昇" if up_prob >= 50 else "下落"
    conf_word = "高い" if abs(up_prob - 50) >= 20 else "やや高い" if abs(up_prob - 50) >= 10 else "拮抗"

    line1 = f"AIは{company_name}の翌日{direction}確率を{up_prob:.1f}%と予測（信頼度: {conf_word}）。"

    if gc:
        line2 = "5日MA・25日MAのゴールデンクロスを確認。短期上昇トレンドに転換の兆し。"
    elif dc:
        line2 = "5日MA・25日MAのデッドクロスを確認。短期下落トレンドに転換の可能性。"
    elif rsi >= 70:
        line2 = f"RSI={rsi:.0f}と買われすぎ水準。短期的な調整リスクに注意。"
    elif rsi <= 30:
        line2 = f"RSI={rsi:.0f}と売られすぎ水準。反発の可能性を示唆。"
    else:
        line2 = f"RSI={rsi:.0f}（中立圏）、5日MA乖離率={ma5_r:+.1f}%。"

    if has_earnings:
        line3 = "⚠️ 予測日前後に決算発表予定あり。サプライズによる急変動に注意。"
    elif news_sentiment < -0.3:
        line3 = "世界・国内情勢のネガティブニュースが相場の重しとなる可能性。"
    elif news_sentiment > 0.3:
        line3 = "市場センチメントはポジティブ。外部環境は追い風。"
    else:
        line3 = f"市場センチメントは中立。VIX={vix:.1f}、USD/JPY={usdjpy:.1f}円。"

    summary = f"{line1}\n{line2}\n{line3}"

    # ── 詳細（10行） ──
    details = []
    details.append(f"【上昇確率】{up_prob:.1f}% ／ 【下落確率】{down_prob:.1f}%")
    details.append(f"【直近リターン】前日比: {ret_1d:+.2f}%、5日比: {ret_5d:+.2f}%")
    details.append(f"【移動平均乖離】5日MA: {ma5_r:+.1f}%、25日MA: {ma25_r:+.1f}%")
    details.append(f"【RSI(14)】{rsi:.1f} ／ 【出来高比率】{vol_r:.2f}倍（20日平均比）")

    if gc:
        details.append("【シグナル】ゴールデンクロス発生 → 強気シグナル")
    elif dc:
        details.append("【シグナル】デッドクロス発生 → 弱気シグナル")
    else:
        details.append("【シグナル】特定のクロスシグナルなし")

    details.append(f"【マクロ指標】USD/JPY={usdjpy:.1f}円、VIX={vix:.1f}、米10年債={macro.get('us10y', 0):.2f}%")
    details.append(f"【ファンダメンタル】PER={per:.1f}倍、PBR={fundamental.get('pbr', 0):.2f}倍、β={beta:.2f}")

    if has_earnings:
        surp_str = f"（直近サプライズ: {earnings_surprise:+.1f}%）" if earnings_surprise else ""
        details.append(f"【決算情報】予測日前後に決算発表予定あり{surp_str}")
    else:
        details.append("【決算情報】予測日前後に決算発表なし")

    sent_label = "ポジティブ" if news_sentiment > 0.1 else "ネガティブ" if news_sentiment < -0.1 else "中立"
    details.append(f"【ニュースセンチメント】{sent_label}（スコア: {news_sentiment:+.3f}）")

    if news_headlines:
        top_news = news_headlines[0]['title'][:60] + ('…' if len(news_headlines[0]['title']) > 60 else '')
        details.append(f"【注目ニュース】{top_news}")
    else:
        details.append("【注目ニュース】なし")

    detail = "\n".join(details)
    return summary, detail

# ── 最新ニュースヘッドライン取得 ──────────────────────────────
def get_recent_headlines(conn, limit=3) -> list:
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT title, source, published_at
        FROM market_news
        ORDER BY published_at DESC
        LIMIT %s
    """, (limit,))
    rows = cursor.fetchall()
    cursor.close()
    return rows

# ── メイン予測処理 ────────────────────────────────────────────
def main():
    logger.info("=== predict_v2.py 開始 ===")
    conn = get_conn()

    # 予測対象日（翌営業日）
    today = datetime.date.today()
    target_date = str(today + datetime.timedelta(days=1))
    # 土日スキップ
    td = today + datetime.timedelta(days=1)
    while td.weekday() >= 5:
        td += datetime.timedelta(days=1)
    target_date = str(td)

    logger.info(f"予測対象日: {target_date}")

    # 共通データ取得
    news_sentiment = get_news_sentiment(conn, str(today))
    earnings_flags = get_earnings_flags(conn, target_date)
    macro          = get_macro_features(conn, str(today))
    headlines      = get_recent_headlines(conn, 3)

    # アクティブ銘柄一覧
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT ticker, company_name FROM stock_master WHERE is_active = 1 ORDER BY ticker")
    stocks = cursor.fetchall()
    cursor.close()

    logger.info(f"予測対象銘柄数: {len(stocks)}")

    success = 0
    skip    = 0

    for stock in stocks:
        ticker       = stock['ticker']
        company_name = stock['company_name']

        try:
            # モデルロード
            model_path = os.path.join(MODEL_DIR, f"{ticker}_model.pkl")
            if not os.path.exists(model_path):
                logger.debug(f"{ticker}: モデルなし → スキップ")
                skip += 1
                continue

            with open(model_path, 'rb') as f:
                model = pickle.load(f)

            # 株価データ取得（直近100日）
            yfobj = yf.Ticker(ticker)
            hist  = yfobj.history(period='6mo')
            if hist is None or len(hist) < 30:
                skip += 1
                continue

            # テクニカル特徴量
            tech_feat = calc_features(hist)
            if tech_feat is None:
                skip += 1
                continue

            # ファンダメンタル特徴量
            fund_feat = get_fundamental_features(conn, ticker)

            # 決算フラグ・サプライズ
            has_earnings     = 1 if ticker in earnings_flags else 0
            earnings_surprise = get_earnings_surprise(conn, ticker)

            # 全特徴量を結合
            feat_dict = tech_feat.to_dict()
            feat_dict.update(macro)
            feat_dict.update(fund_feat)
            feat_dict['news_sentiment']    = news_sentiment
            feat_dict['has_earnings']      = has_earnings
            feat_dict['earnings_surprise'] = earnings_surprise

            # モデルの特徴量順に並べる
            feature_names = model.feature_name_
            X = pd.DataFrame([feat_dict]).reindex(columns=feature_names, fill_value=0).astype(float)

            # 予測
            prob = model.predict_proba(X)[0]
            # クラス0=下落, クラス1=上昇 の想定
            if len(prob) >= 2:
                up_prob   = round(float(prob[1]) * 100, 2)
                down_prob = round(float(prob[0]) * 100, 2)
            else:
                up_prob   = round(float(prob[0]) * 100, 2)
                down_prob = round(100 - up_prob, 2)

            # 予測理由生成
            reason_summary, reason_detail = build_reason(
                ticker, company_name, up_prob, down_prob,
                feat_dict, macro, fund_feat,
                news_sentiment, has_earnings, earnings_surprise, headlines
            )

            # DB保存（UPSERT）
            cur2 = conn.cursor()
            cur2.execute("""
                INSERT INTO stock_prediction
                    (ticker, predict_date, up_probability, down_probability,
                     news_sentiment, has_earnings,
                     reason_summary, reason_detail)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    up_probability  = VALUES(up_probability),
                    down_probability = VALUES(down_probability),
                    news_sentiment  = VALUES(news_sentiment),
                    has_earnings    = VALUES(has_earnings),
                    reason_summary  = VALUES(reason_summary),
                    reason_detail   = VALUES(reason_detail),
                    created_at      = CURRENT_TIMESTAMP
            """, (ticker, target_date, up_prob, down_prob,
                  news_sentiment, has_earnings,
                  reason_summary, reason_detail))
            cur2.close()
            success += 1

        except Exception as e:
            logger.error(f"{ticker} 予測エラー: {e}")
            skip += 1

    conn.commit()
    conn.close()
    logger.info(f"=== predict_v2.py 完了: 成功={success}, スキップ={skip} ===")

if __name__ == '__main__':
    main()
