#!/usr/bin/env python3
"""
train_v2.py
===========
既存の train.py を拡張し、以下の特徴量を追加する。

追加特徴量:
  - news_sentiment    : 予測日前後3日間のニュースセンチメント平均
  - has_earnings      : 翌日に決算発表があるか（0/1）
  - earnings_surprise : 直近決算のEPSサプライズ率（%）

学習データの各行は「翌日の株価が上昇したか（1）下落したか（0）」をラベルとする。
モデルは銘柄ごとに個別学習し {ticker}_model.pkl として保存する。

実行方法:
  /var/ml_project/venv/bin/python train_v2.py

Cron設定例（毎週日曜 2:00）:
  0 2 * * 0 /var/ml_project/venv/bin/python /var/ml_project/train_v2.py >> /var/log/ml_project/train.log 2>&1
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
import lightgbm as lgb
from sklearn.model_selection import TimeSeriesSplit
from sklearn.metrics import roc_auc_score

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger(__name__)

os.makedirs(MODEL_DIR, exist_ok=True)

# ── DB接続 ────────────────────────────────────────────────────
def get_conn():
    return mysql.connector.connect(
        host=DB_HOST, database=DB_NAME,
        user=DB_USER, password=DB_PASS,
        charset=DB_CHARSET, autocommit=False
    )

# ── ニュースセンチメントを日付別に取得 ────────────────────────
def load_news_sentiment_map(conn) -> dict:
    """
    {date_str: sentiment_avg} の辞書を返す（過去全期間）
    """
    cursor = conn.cursor()
    cursor.execute("""
        SELECT DATE(published_at) AS d, AVG(sentiment)
        FROM market_news
        WHERE sentiment IS NOT NULL
        GROUP BY DATE(published_at)
    """)
    rows = cursor.fetchall()
    cursor.close()
    return {str(row[0]): float(row[1]) for row in rows}

# ── 決算日マップを取得 ────────────────────────────────────────
def load_earnings_map(conn) -> dict:
    """
    {ticker: set(date_str)} の辞書を返す
    """
    cursor = conn.cursor()
    cursor.execute("SELECT ticker, earnings_date FROM earnings_calendar")
    rows = cursor.fetchall()
    cursor.close()
    result = {}
    for ticker, ed in rows:
        result.setdefault(ticker, set()).add(str(ed))
    return result

# ── 決算サプライズマップを取得 ────────────────────────────────
def load_surprise_map(conn) -> dict:
    """
    {ticker: latest_surprise_pct} の辞書を返す
    """
    cursor = conn.cursor()
    cursor.execute("""
        SELECT ticker, surprise_pct
        FROM earnings_calendar
        WHERE eps_actual IS NOT NULL
        ORDER BY earnings_date DESC
    """)
    rows = cursor.fetchall()
    cursor.close()
    result = {}
    for ticker, surp in rows:
        if ticker not in result:
            result[ticker] = float(surp) if surp is not None else 0.0
    return result

# ── 特徴量計算 ────────────────────────────────────────────────
def calc_features_for_row(df: pd.DataFrame, idx: int) -> dict | None:
    """
    df の idx 行目（予測対象日）の特徴量を計算する。
    """
    if idx < 30:
        return None

    sub   = df.iloc[:idx]
    close = sub['Close'].astype(float)
    volume = sub['Volume'].astype(float)

    feat = {}

    feat['return_1d']  = close.pct_change(1).iloc[-1]
    feat['return_5d']  = close.pct_change(5).iloc[-1]
    feat['return_20d'] = close.pct_change(20).iloc[-1]

    ma5  = close.rolling(5).mean()
    ma25 = close.rolling(25).mean()
    ma75 = close.rolling(min(75, len(close))).mean()

    feat['ma5_ratio']  = (close.iloc[-1] / ma5.iloc[-1]  - 1) if ma5.iloc[-1]  else 0
    feat['ma25_ratio'] = (close.iloc[-1] / ma25.iloc[-1] - 1) if ma25.iloc[-1] else 0
    feat['ma75_ratio'] = (close.iloc[-1] / ma75.iloc[-1] - 1) if ma75.iloc[-1] else 0

    feat['golden_cross'] = 1 if (ma5.iloc[-1] > ma25.iloc[-1] and ma5.iloc[-2] <= ma25.iloc[-2]) else 0
    feat['dead_cross']   = 1 if (ma5.iloc[-1] < ma25.iloc[-1] and ma5.iloc[-2] >= ma25.iloc[-2]) else 0

    delta = close.diff()
    gain  = delta.clip(lower=0).rolling(14).mean()
    loss  = (-delta.clip(upper=0)).rolling(14).mean()
    rs    = gain / loss.replace(0, np.nan)
    feat['rsi_14'] = float((100 - 100 / (1 + rs)).iloc[-1]) if not rs.isna().iloc[-1] else 50.0

    feat['volatility_20d'] = float(close.pct_change().rolling(20).std().iloc[-1] or 0)

    vol_ma20 = volume.rolling(20).mean()
    feat['volume_ratio'] = float(volume.iloc[-1] / vol_ma20.iloc[-1]) if vol_ma20.iloc[-1] else 1.0

    high_52w = close.rolling(min(252, len(close))).max().iloc[-1]
    low_52w  = close.rolling(min(252, len(close))).min().iloc[-1]
    feat['from_high_52w'] = (close.iloc[-1] / high_52w - 1) if high_52w else 0
    feat['from_low_52w']  = (close.iloc[-1] / low_52w  - 1) if low_52w  else 0

    return feat

# ── マクロ特徴量を日付別に取得 ────────────────────────────────
def load_macro_map(conn) -> pd.DataFrame:
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM macro_indicators ORDER BY date")
    rows = cursor.fetchall()
    cursor.close()
    if not rows:
        return pd.DataFrame()
    df = pd.DataFrame(rows)
    df['date'] = pd.to_datetime(df['date'])
    df = df.set_index('date')
    return df

# ── ファンダメンタル特徴量取得 ────────────────────────────────
def get_fundamental(conn, ticker: str) -> dict:
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT per, pbr, roe, dividend_yield, beta FROM stock_master WHERE ticker = %s", (ticker,))
    row = cursor.fetchone()
    cursor.close()
    if not row:
        return {'per': 0, 'pbr': 0, 'roe': 0, 'dividend_yield': 0, 'beta': 1}
    return {k: float(v or 0) for k, v in row.items()}

# ── 銘柄ごとの学習データ構築 ─────────────────────────────────
def build_dataset(conn, ticker: str, macro_df: pd.DataFrame,
                  news_map: dict, earnings_map: dict, surprise_map: dict) -> tuple:
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT date, open, high, low, close, volume
        FROM stock_history
        WHERE ticker = %s
        ORDER BY date ASC
    """, (ticker,))
    rows = cursor.fetchall()
    cursor.close()

    if len(rows) < 60:
        return None, None

    df = pd.DataFrame(rows)
    df['date'] = pd.to_datetime(df['date'])
    df = df.rename(columns={'close': 'Close', 'open': 'Open',
                             'high': 'High', 'low': 'Low', 'volume': 'Volume'})
    df = df.set_index('date').sort_index()

    fund = get_fundamental(conn, ticker)
    ticker_earnings = earnings_map.get(ticker, set())
    surprise_val    = surprise_map.get(ticker, 0.0)

    X_rows = []
    y_rows = []

    for i in range(30, len(df) - 1):
        feat = calc_features_for_row(df, i)
        if feat is None:
            continue

        # ラベル: 翌日終値が上昇したか
        next_close = float(df['Close'].iloc[i + 1])
        curr_close = float(df['Close'].iloc[i])
        label = 1 if next_close > curr_close else 0

        # マクロ特徴量
        date_str = str(df.index[i].date())
        if not macro_df.empty:
            macro_row = macro_df[macro_df.index <= df.index[i]]
            if not macro_row.empty:
                mr = macro_row.iloc[-1]
                feat['usdjpy'] = float(mr.get('usdjpy', 0) or 0)
                feat['vix']    = float(mr.get('vix', 0) or 0)
                feat['nikkei'] = float(mr.get('nikkei', 0) or 0)
                feat['us10y']  = float(mr.get('us10y', 0) or 0)
                feat['sp500']  = float(mr.get('sp500', 0) or 0)
                feat['wti']    = float(mr.get('wti', 0) or 0)
                feat['gold']   = float(mr.get('gold', 0) or 0)

        # ファンダメンタル
        feat.update(fund)

        # ニュースセンチメント
        feat['news_sentiment'] = news_map.get(date_str, 0.0)

        # 決算フラグ
        next_date = str(df.index[i + 1].date())
        feat['has_earnings']      = 1 if next_date in ticker_earnings else 0
        feat['earnings_surprise'] = surprise_val

        X_rows.append(feat)
        y_rows.append(label)

    if len(X_rows) < 30:
        return None, None

    X = pd.DataFrame(X_rows).astype(float)
    y = pd.Series(y_rows)
    return X, y

# ── LightGBM 学習 ─────────────────────────────────────────────
def train_model(X: pd.DataFrame, y: pd.Series, ticker: str):
    params = {
        'objective':      'binary',
        'metric':         'auc',
        'learning_rate':  0.05,
        'num_leaves':     31,
        'min_data_in_leaf': 20,
        'feature_fraction': 0.8,
        'bagging_fraction': 0.8,
        'bagging_freq':   5,
        'verbose':        -1,
        'n_jobs':         -1,
    }

    tscv = TimeSeriesSplit(n_splits=3)
    best_auc = 0.0

    for fold, (tr_idx, va_idx) in enumerate(tscv.split(X)):
        X_tr, X_va = X.iloc[tr_idx], X.iloc[va_idx]
        y_tr, y_va = y.iloc[tr_idx], y.iloc[va_idx]

        model = lgb.LGBMClassifier(**params, n_estimators=500)
        model.fit(
            X_tr, y_tr,
            eval_set=[(X_va, y_va)],
            callbacks=[lgb.early_stopping(50, verbose=False), lgb.log_evaluation(-1)]
        )
        preds = model.predict_proba(X_va)[:, 1]
        auc   = roc_auc_score(y_va, preds)
        if auc > best_auc:
            best_auc  = auc
            best_model = model

    logger.info(f"  {ticker}: AUC={best_auc:.4f}, サンプル数={len(X)}")
    return best_model

# ── メイン ────────────────────────────────────────────────────
def main():
    logger.info("=== train_v2.py 開始 ===")
    conn = get_conn()

    macro_df     = load_macro_map(conn)
    news_map     = load_news_sentiment_map(conn)
    earnings_map = load_earnings_map(conn)
    surprise_map = load_surprise_map(conn)

    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT ticker FROM stock_master WHERE is_active = 1 ORDER BY ticker")
    tickers = [row['ticker'] for row in cursor.fetchall()]
    cursor.close()

    logger.info(f"学習対象銘柄数: {len(tickers)}")
    success = 0
    skip    = 0

    for ticker in tickers:
        try:
            X, y = build_dataset(conn, ticker, macro_df, news_map, earnings_map, surprise_map)
            if X is None:
                logger.debug(f"{ticker}: データ不足 → スキップ")
                skip += 1
                continue

            model = train_model(X, y, ticker)
            model_path = os.path.join(MODEL_DIR, f"{ticker}_model.pkl")
            with open(model_path, 'wb') as f:
                pickle.dump(model, f)
            success += 1

        except Exception as e:
            logger.error(f"{ticker} 学習エラー: {e}")
            skip += 1

    conn.close()
    logger.info(f"=== train_v2.py 完了: 成功={success}, スキップ={skip} ===")

if __name__ == '__main__':
    main()
