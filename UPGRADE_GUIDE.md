# アップグレードガイド v2.0
## 下落ランキング / 情勢ニュース / 決算カレンダー 追加手順

---

## 追加された機能

| 機能 | ファイル | 説明 |
|---|---|---|
| 下落予測ランキング | `down_ranking.php` | 下落確率TOP30を表示 |
| 情勢・ニュースページ | `news.php` | 世界・日本情勢ニュース＋センチメント分析 |
| 決算カレンダー | `earnings.php` | 決算スケジュール・サプライズ一覧 |
| ニュース・決算取得 | `python/fetch_news_earnings.py` | Google News＋yfinance決算情報 |
| 予測スクリプト v2 | `python/predict_v2.py` | ニュース・決算を特徴量に追加 |
| 学習スクリプト v2 | `python/train_v2.py` | ニュース・決算を特徴量に追加 |
| DBスキーマ追加 | `sql/add_news_earnings.sql` | 新テーブル・カラム追加 |

---

## 設置手順

### Step 1: DBスキーマを更新

VPS上でMySQLに接続し、スキーマを適用します。

```bash
mysql -u root -p ml_db < /var/www/html/aisample.maspis.com/sql/add_news_earnings.sql
```

### Step 2: Pythonライブラリを追加インストール

```bash
/var/ml_project/venv/bin/pip install gnews mysql-connector-python
```

### Step 3: Pythonスクリプトを配置

```bash
cp python/fetch_news_earnings.py /var/ml_project/fetch_news_earnings.py
cp python/predict_v2.py          /var/ml_project/predict_v2.py
cp python/train_v2.py            /var/ml_project/train_v2.py

chmod +x /var/ml_project/fetch_news_earnings.py
chmod +x /var/ml_project/predict_v2.py
chmod +x /var/ml_project/train_v2.py
```

### Step 4: PHPファイルを配置

```bash
cp down_ranking.php /var/www/html/aisample.maspis.com/
cp news.php         /var/www/html/aisample.maspis.com/
cp earnings.php     /var/www/html/aisample.maspis.com/
cp index.php        /var/www/html/aisample.maspis.com/   # タブナビ追加版
cp css/style.css    /var/www/html/aisample.maspis.com/css/
```

### Step 5: 動作確認（手動実行）

```bash
# ニュース・決算情報取得テスト
/var/ml_project/venv/bin/python /var/ml_project/fetch_news_earnings.py

# 予測テスト
/var/ml_project/venv/bin/python /var/ml_project/predict_v2.py
```

### Step 6: Cronを更新

```bash
crontab -e
```

以下の内容に更新します（`cron_setup.txt` 参照）：

```
30 5 * * * /var/ml_project/venv/bin/python /var/ml_project/fetch_news_earnings.py >> /var/log/ml_project/fetch_news_earnings.log 2>&1
0 6 * * * /var/ml_project/venv/bin/python /var/ml_project/fetch_data.py >> /var/log/ml_project/fetch_data.log 2>&1
0 7 * * * /var/ml_project/venv/bin/python /var/ml_project/predict_v2.py >> /var/log/ml_project/predict.log 2>&1
30 7 * * * /var/ml_project/venv/bin/python /var/ml_project/update_result.py >> /var/log/ml_project/update_result.log 2>&1
0 2 * * 0 /var/ml_project/venv/bin/python /var/ml_project/train_v2.py >> /var/log/ml_project/train.log 2>&1
```

---

## 仕組みの説明

### ニュース取得の仕組み

`fetch_news_earnings.py` は Google News（gnewsライブラリ）から以下のキーワードで毎日ニュースを収集します。

| カテゴリ | 検索キーワード |
|---|---|
| 日本情勢 | 日本経済 株式市場 / 日本 政治 経済 |
| 世界情勢・地政学 | 戦争 地政学 リスク / ウクライナ 中東 情勢 |
| 米国経済 | 米国 経済 FRB 金利 / Trump tariff trade war |
| グローバル経済 | 世界経済 景気後退 / China economy slowdown |

各ニュースにはキーワードベースの**センチメントスコア（-1.0〜+1.0）**が付与され、予測特徴量として活用されます。

### 決算情報取得の仕組み

yfinanceの `ticker.calendar` と `ticker.get_earnings_dates()` を使用して、全銘柄の決算予定日・EPS予想・実績・サプライズ率を取得します。

### 下落確率の計算

`predict_v2.py` では LightGBM の `predict_proba()` から：
- クラス1（上昇）の確率 → `up_probability`
- クラス0（下落）の確率 → `down_probability`

として両方をDBに保存します。

---

## 注意事項

- `gnews` ライブラリは Google News のスクレイピングを行います。利用規約に注意してください。
- `predict_v2.py` は既存の `predict.py` の**差し替え版**です。モデルファイル（.pkl）の互換性のため、差し替え後は `train_v2.py` で**再学習**を推奨します。
- 再学習まで `down_probability` が NULL の場合、`down_ranking.php` は自動的に `up_probability` の逆順で表示します。
