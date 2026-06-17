<?php
declare(strict_types=1);

/**
 * Ama-Jack 仕入れスコアリングエンジン
 * アプリから受け取った生データを事前計算し、AIへ渡すサマリーJSONを生成する。
 */

/**
 * メイン関数: 生データ配列からスコアサマリーを返す。
 *
 * @param array $raw  Ama-Jack アプリのレスポンスそのまま
 * @param int   $buyPrice  ユーザー入力の仕入れ値（円）
 * @return array
 */
function scorer_analyze(array $raw, int $buyPrice = 0): array
{
    // --- 基本情報 ---
    $productName  = $raw['shiire_title']    ?? '';
    $asin         = $raw['shiire_asin']     ?? '';
    $category     = $raw['shiire_category'] ?? '';
    $currentRank  = (int) ($raw['shiire_rank']   ?? 0);
    $newPrice     = (int) ($raw['shiire_new']     ?? 0);
    $usedPrice    = (int) ($raw['shiire_old']     ?? 0);
    $cartPrice    = (int) ($raw['shiire_cart']    ?? 0);

    // --- 時系列データ取得（末尾N件で判定） ---
    $rankHistory     = array_filter((array)($raw['rankingData']    ?? []), fn($v) => is_numeric($v) && $v > 0);
    $newPriceHistory = array_filter((array)($raw['newExPriData']   ?? []), fn($v) => is_numeric($v) && $v > 0);
    $newSellerHist   = array_filter((array)($raw['newexNumData']   ?? []), 'is_numeric');
    $usedSellerHist  = array_filter((array)($raw['oldexNumData']   ?? []), fn($v) => is_numeric($v) && $v >= 0);
    $amazonPriceHist = array_filter((array)($raw['amazonPriData']  ?? []), fn($v) => is_numeric($v) && $v > 0);

    // 最新の出品者数
    $newSellerCurrent  = (int) scorer_last_valid($newSellerHist);
    $usedSellerCurrent = (int) scorer_last_valid($usedSellerHist);

    // Amazon本体の出品価格（最新）
    $amazonPrice = (int) scorer_last_valid($amazonPriceHist);
    $amazonSelling = ($amazonPrice > 0 && $amazonPrice < 99999);

    // 販売数（buyCntTable HTMLからパース）
    $salesData = scorer_parse_buy_cnt($raw['buyCntTable'] ?? '');
    $monthlySales     = (int) ($salesData['avg_total'] ?? 0);
    $monthlySalesNew  = (int) ($salesData['avg_new']   ?? 0);

    // --- 利益計算 ---
    $profit = scorer_calc_profit($buyPrice, $newPrice, $category);

    // --- トレンド分析 ---
    $rankTrend     = scorer_trend($rankHistory,     30, 'rank');
    $priceTrend    = scorer_trend($newPriceHistory, 30, 'price');
    $sellerTrend   = scorer_trend($newSellerHist,   30, 'count');
    $rankFrequency = scorer_rank_wave_frequency($rankHistory, 60);

    // --- 各スコア（0〜100） ---
    $profitScore     = scorer_profit_score($profit);
    $salesScore      = scorer_sales_score($monthlySales, $currentRank);
    $competitorScore = scorer_competitor_score($newSellerCurrent, $sellerTrend, $amazonSelling);
    $stabilityScore  = scorer_stability_score($priceTrend, $rankFrequency);

    // 総合スコア（重み付き平均）
    $totalScore = (int) round(
        $profitScore     * 0.40 +
        $salesScore      * 0.25 +
        $competitorScore * 0.20 +
        $stabilityScore  * 0.15
    );

    // 判定ラベル
    $verdict = scorer_verdict($totalScore, $profit);

    return [
        // --- 商品基本情報 ---
        'product_name'    => $productName,
        'asin'            => $asin,
        'category'        => $category,
        'current_rank'    => $currentRank,

        // --- 価格情報 ---
        'buy_price'       => $buyPrice,
        'sell_price'      => $newPrice,
        'amazon_price'    => $amazonSelling ? $amazonPrice : null,
        'amazon_selling'  => $amazonSelling,

        // --- 利益計算結果 ---
        'profit_amount'    => $profit['profit_amount'],
        'profit_rate'      => $profit['profit_rate'],
        'breakeven_price'  => $profit['breakeven_price'],
        'fba_fee_estimate' => $profit['fba_fee'],

        // --- 売れ行き ---
        'monthly_sales'         => $monthlySales,
        'monthly_sales_new'     => $monthlySalesNew,
        'rank_wave_frequency'   => $rankFrequency,  // 'frequent'|'sometimes'|'rare'

        // --- 競合 ---
        'new_seller_count'  => $newSellerCurrent,
        'used_seller_count' => $usedSellerCurrent,
        'seller_trend'      => $sellerTrend,   // 'increasing'|'stable'|'decreasing'

        // --- 価格安定性 ---
        'price_trend'       => $priceTrend,    // 'rising'|'stable'|'falling'
        'price_volatility'  => scorer_price_volatility($newPriceHistory, 30),  // 'high'|'medium'|'low'

        // --- スコア詳細 ---
        'score_profit'      => $profitScore,
        'score_sales'       => $salesScore,
        'score_competitor'  => $competitorScore,
        'score_stability'   => $stabilityScore,
        'score_total'       => $totalScore,

        // --- 総合判定 ---
        'verdict'           => $verdict,   // 'buy'|'ok'|'caution'|'avoid'|'ng'
        'recommended_qty'   => scorer_recommended_qty($monthlySales, $newSellerCurrent),
    ];
}

// =============================================================================
// 利益計算
// =============================================================================

function scorer_calc_profit(int $buyPrice, int $sellPrice, string $category): array
{
    if ($buyPrice <= 0 || $sellPrice <= 0) {
        return ['profit_amount' => null, 'profit_rate' => null, 'breakeven_price' => null, 'fba_fee' => null];
    }

    // FBA手数料の簡易推定（カテゴリ別）
    $fbaFee = scorer_estimate_fba_fee($sellPrice, $category);
    // Amazon手数料（販売価格の約8〜15%、ゲーム類は8%）
    $amazonFeeRate = scorer_amazon_fee_rate($category);
    $amazonFee = (int) round($sellPrice * $amazonFeeRate);

    $totalCost  = $buyPrice + $fbaFee + $amazonFee;
    $profit     = $sellPrice - $totalCost;
    $profitRate = $sellPrice > 0 ? round($profit / $sellPrice * 100, 1) : 0.0;

    // 損益分岐点（利益0になる最低仕入れ値）
    $breakevenBuyPrice = $sellPrice - $fbaFee - $amazonFee;

    return [
        'profit_amount'   => $profit,
        'profit_rate'     => $profitRate,
        'breakeven_price' => $breakevenBuyPrice,
        'fba_fee'         => $fbaFee + $amazonFee,
    ];
}

function scorer_estimate_fba_fee(int $sellPrice, string $category): int
{
    // 簡易FBA手数料（サイズ・重量不明時の目安）
    if ($sellPrice <= 5000)  return 450;
    if ($sellPrice <= 10000) return 600;
    if ($sellPrice <= 20000) return 800;
    if ($sellPrice <= 50000) return 1000;
    return 1500;
}

function scorer_amazon_fee_rate(string $category): float
{
    $rates = [
        'ゲーム' => 0.08, 'テレビゲーム' => 0.08,
        'おもちゃ' => 0.08, 'ホビー' => 0.08,
        'エレクトロニクス' => 0.08, '家電' => 0.08,
        'スポーツ' => 0.10, 'アウトドア' => 0.10,
        '本' => 0.15, 'CD' => 0.15, 'DVD' => 0.15,
    ];
    foreach ($rates as $key => $rate) {
        if (str_contains($category, $key)) return $rate;
    }
    return 0.10; // デフォルト10%
}

// =============================================================================
// トレンド分析
// =============================================================================

/**
 * 末尾 $n 件の時系列から傾向を判定する。
 * $type = 'rank': 数値が下がる方向（順位向上）が「改善」
 * $type = 'price'|'count': 数値が上がる方向が「増加」
 */
function scorer_trend(array $history, int $n, string $type): string
{
    $values = array_values(array_slice($history, -$n));
    $len = count($values);
    if ($len < 4) return 'unknown';

    $first = array_sum(array_slice($values, 0, (int)($len / 3))) / ($len / 3);
    $last  = array_sum(array_slice($values, -(int)($len / 3))) / ($len / 3);

    $changePct = $first > 0 ? ($last - $first) / $first * 100 : 0;

    if ($type === 'rank') {
        // ランキングは数値が小さいほど良い（1位が最高）
        if ($changePct < -10) return 'improving';   // 順位が上がった
        if ($changePct > 10)  return 'worsening';   // 順位が下がった
        return 'stable';
    }

    if ($changePct > 10)  return ($type === 'count') ? 'increasing' : 'rising';
    if ($changePct < -10) return ($type === 'count') ? 'decreasing' : 'falling';
    return 'stable';
}

/**
 * ランキング波の頻度（売れ行きの活発さ）を判定する。
 * 「波」= ランキング値が急激に下がる（＝売れた）イベントを数える。
 */
function scorer_rank_wave_frequency(array $rankHistory, int $n): string
{
    $values = array_values(array_slice($rankHistory, -$n));
    $len = count($values);
    if ($len < 5) return 'unknown';

    $drops = 0;
    for ($i = 1; $i < $len; $i++) {
        // 直前より20%以上ランクが良くなった（数値が小さくなった）=売れたと判断
        if ($values[$i] < $values[$i - 1] * 0.8) {
            $drops++;
        }
    }

    $frequency = $drops / $len;
    if ($frequency >= 0.15) return 'frequent';
    if ($frequency >= 0.05) return 'sometimes';
    return 'rare';
}

function scorer_price_volatility(array $priceHistory, int $n): string
{
    $values = array_values(array_slice($priceHistory, -$n));
    if (count($values) < 4) return 'unknown';

    $avg = array_sum($values) / count($values);
    if ($avg <= 0) return 'unknown';
    $variance = array_sum(array_map(fn($v) => ($v - $avg) ** 2, $values)) / count($values);
    $cv = sqrt($variance) / $avg; // 変動係数

    if ($cv >= 0.15) return 'high';
    if ($cv >= 0.05) return 'medium';
    return 'low';
}

// =============================================================================
// スコア計算（各0〜100）
// =============================================================================

function scorer_profit_score(array $profit): int
{
    if ($profit['profit_rate'] === null) return 0;
    $rate = $profit['profit_rate'];
    $amount = $profit['profit_amount'];

    if ($amount < 300)  return 10;
    if ($rate >= 25)    return 100;
    if ($rate >= 20)    return 80;
    if ($rate >= 15)    return 60;
    if ($rate >= 10)    return 40;
    if ($rate >= 5)     return 20;
    return 5;
}

function scorer_sales_score(int $monthlySales, int $currentRank): int
{
    if ($monthlySales <= 0 && $currentRank <= 0) return 0;

    // 月販売数ベース
    $salesScore = 0;
    if ($monthlySales >= 20)      $salesScore = 100;
    elseif ($monthlySales >= 10)  $salesScore = 80;
    elseif ($monthlySales >= 5)   $salesScore = 60;
    elseif ($monthlySales >= 2)   $salesScore = 40;
    elseif ($monthlySales >= 1)   $salesScore = 20;

    // ランキングで補正（1万位以内ならボーナス）
    $rankBonus = 0;
    if ($currentRank > 0) {
        if ($currentRank <= 100)   $rankBonus = 20;
        elseif ($currentRank <= 500)  $rankBonus = 10;
        elseif ($currentRank <= 3000) $rankBonus = 5;
    }

    return min(100, $salesScore + $rankBonus);
}

function scorer_competitor_score(int $newSellers, string $sellerTrend, bool $amazonSelling): int
{
    // Amazon本体出品は大きなマイナス
    if ($amazonSelling) return 10;

    $score = 0;
    if ($newSellers <= 2)       $score = 100;
    elseif ($newSellers <= 5)   $score = 80;
    elseif ($newSellers <= 10)  $score = 60;
    elseif ($newSellers <= 15)  $score = 40;
    elseif ($newSellers <= 25)  $score = 25;
    else                        $score = 10;

    // 増加トレンドはリスク
    if ($sellerTrend === 'increasing') $score = (int)($score * 0.7);

    return $score;
}

function scorer_stability_score(string $priceTrend, string $rankFrequency): int
{
    $score = 50;

    if ($priceTrend === 'rising')  $score += 25;
    if ($priceTrend === 'stable')  $score += 10;
    if ($priceTrend === 'falling') $score -= 25;

    if ($rankFrequency === 'frequent')  $score += 25;
    if ($rankFrequency === 'sometimes') $score += 10;
    if ($rankFrequency === 'rare')      $score -= 20;

    return max(0, min(100, $score));
}

// =============================================================================
// 総合判定
// =============================================================================

function scorer_verdict(int $totalScore, array $profit): string
{
    // 利益率が著しく低い場合はNG確定
    if ($profit['profit_rate'] !== null && $profit['profit_rate'] < 10) return 'ng';
    if ($profit['profit_amount'] !== null && $profit['profit_amount'] < 200) return 'ng';

    if ($totalScore >= 75) return 'buy';
    if ($totalScore >= 60) return 'ok';
    if ($totalScore >= 45) return 'caution';
    if ($totalScore >= 30) return 'avoid';
    return 'ng';
}

function scorer_recommended_qty(int $monthlySales, int $newSellers): int
{
    if ($monthlySales <= 0) return 1;
    $sellers = max(1, $newSellers + 1);
    $qty = (int) floor($monthlySales / $sellers * 0.5);
    return max(1, min($qty, 10)); // 最低1、最高10個
}

// =============================================================================
// ユーティリティ
// =============================================================================

function scorer_last_valid(array $arr): float
{
    $reversed = array_reverse(array_values($arr));
    foreach ($reversed as $v) {
        if (is_numeric($v) && $v > 0) return (float)$v;
    }
    return 0.0;
}

/**
 * buyCntTable の HTML をパースして販売数を取得する。
 */
function scorer_parse_buy_cnt(string $html): array
{
    if ($html === '') return [];

    // <td>数値</td> のパターンを抽出
    preg_match_all('/<td[^>]*>(\d+)<\/td>/', $html, $matches);
    $nums = array_map('intval', $matches[1]);

    // テーブル構造: 合計行・新品行・中古行 それぞれ5列（1ヶ月・2ヶ月・3ヶ月・平均・合計）
    if (count($nums) < 15) return [];

    return [
        'month1_total' => $nums[0],  'month2_total' => $nums[1],  'month3_total' => $nums[2],
        'avg_total'    => $nums[3],  'sum_total'    => $nums[4],
        'month1_new'   => $nums[5],  'month2_new'   => $nums[6],  'month3_new'   => $nums[7],
        'avg_new'      => $nums[8],  'sum_new'      => $nums[9],
        'month1_used'  => $nums[10], 'month2_used'  => $nums[11], 'month3_used'  => $nums[12],
        'avg_used'     => $nums[13], 'sum_used'     => $nums[14],
    ];
}
