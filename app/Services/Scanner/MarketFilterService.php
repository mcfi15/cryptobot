<?php

namespace App\Services\Scanner;

use App\Models\ExchangeAccount;
use App\Models\ScannerConfig;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketFilterService
{
    protected array $bannedBases = [
        'USDT', 'USDC', 'BUSD', 'TUSD', 'DAI', 'FDUSD', 'UST', 'USTC', 'AUST',
        'EUR', 'GBP', 'USD1', 'USDE', 'USDY', 'USUAL', 'PAXG', 'WBTC', 'WBETH',
        'LUNA', 'LUNA2', 'BTCST', 'TBTC', 'EURT', 'EURI', 'WUSD', 'USDP',
    ];

    protected array $bannedPatterns = ['3L', '3S', '5L', '5S', '2L', '2S', '4L', '4S', 'UP', 'DOWN', 'BULL', 'BEAR'];

    /**
     * Fetch a snapshot of 24h tickers for an account+market type (batched - 1 request).
     *
     * @return array<string, array> keyed by symbol
     */
    public function tickers(ExchangeAccount $account, string $marketType): array
    {
        $cacheKey = "scanner.tickers.{$account->id}.{$marketType}";

        return Cache::remember($cacheKey, 60, function () use ($account, $marketType) {
            try {
                $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
                $tickers = $adapter->getTickers($marketType);
            } catch (\Throwable $e) {
                Log::warning('scanner.tickers.failed', [
                    'exchange' => $account->exchange,
                    'market_type' => $marketType,
                    'error' => $e->getMessage(),
                ]);
                return [];
            }

            $keyed = [];
            foreach ($tickers as $t) {
                $keyed[$t['symbol']] = $t;
            }
            return $keyed;
        });
    }

    /**
     * Stage 1-7: structural filters (listability, quote asset, market type, status).
     *
     * @return array<int, array> candidate markets
     */
    public function structuralFilter(array $markets, ScannerConfig $config): array
    {
        $quoteAssets = $config->quote_assets ?: ['USDT'];
        $preferred = $config->preferred_assets ?: [];

        return array_values(array_filter($markets, function ($m) use ($quoteAssets, $preferred, $config) {
            $symbol = $m['symbol'];
            $base = strtoupper($m['base_asset'] ?? '');
            $quote = strtoupper($m['quote_asset'] ?? '');

            if ($m['status'] !== 'trading') return false;
            if (!in_array($quote, $quoteAssets, true)) return false;
            if (in_array($base, $this->bannedBases, true)) return false;

            foreach ($this->bannedPatterns as $pattern) {
                if (str_ends_with(strtoupper($symbol), $pattern)) return false;
                if (str_contains(strtoupper($symbol), $pattern . 'USDT')) return false;
            }

            if ($config->scan_mode === 'hybrid' && count($preferred) > 0) {
                if (!in_array($base, $preferred, true)) return false;
            }

            return true;
        }));
    }

    /**
     * Score liquidity (24h quote volume) and spread for each candidate using the ticker snapshot.
     * Returns candidates sorted by liquidity descending.
     *
     * @return array<int, array> [{market, ticker, quote_volume, spread_pct, price,}}]
     */
    public function liquidityProfile(array $markets, array $tickers): array
    {
        $result = [];
        foreach ($markets as $market) {
            $ticker = $tickers[$market['symbol']] ?? null;
            if (!$ticker) continue;

            $quoteVolume = (float) ($ticker['quote_volume'] ?? 0);
            if ($quoteVolume <= 0) {
                $quoteVolume = (float) ($ticker['price'] ?? 0) * (float) ($ticker['volume'] ?? 0);
            }

            $bid = (float) ($ticker['bid'] ?? 0);
            $ask = (float) ($ticker['ask'] ?? 0);
            $mid = ($bid + $ask) / 2;
            $spread = $mid > 0 ? (($ask - $bid) / $mid) * 100 : 0;

            $result[] = [
                'market' => $market,
                'ticker' => $ticker,
                'price' => (float) $ticker['price'],
                'quote_volume' => $quoteVolume,
                'spread_pct' => $spread,
                'change_24h' => (float) ($ticker['change_24h'] ?? 0),
            ];
        }

        usort($result, fn ($a, $b) => $b['quote_volume'] <=> $a['quote_volume']);

        return $result;
    }

    /**
     * Stage filters on liquidity and spread. Keeps structurally valid, liquid markets.
     */
    public function applyLiquidityFilters(array $profiled, ScannerConfig $config): array
    {
        $minVolume = (float) $config->min_volume_24h;
        $maxSpread = (float) $config->max_spread_pct;

        return array_values(array_filter($profiled, function ($p) use ($minVolume, $maxSpread) {
            if ($p['quote_volume'] < $minVolume) return false;
            if ($maxSpread > 0 && $p['spread_pct'] > $maxSpread) return false;
            return true;
        }));
    }

    /**
     * Apply the per-scan market cap, optionally prioritizing preferred (hybrid) assets.
     */
    public function capMarkets(array $profiled, ScannerConfig $config): array
    {
        $preferred = $config->preferred_assets ?: [];

        if ($config->scan_mode === 'hybrid' && count($preferred) > 0) {
            $preferredBase = array_map('strtoupper', $preferred);
            $top = array_filter($profiled, function ($p) use ($preferredBase) {
                return in_array(strtoupper($p['market']['base_asset'] ?? ''), $preferredBase, true);
            });
            $rest = array_filter($profiled, function ($p) use ($preferredBase) {
                return !in_array(strtoupper($p['market']['base_asset'] ?? ''), $preferredBase, true);
            });

            return array_slice(array_merge(array_values($top), array_values($rest)), 0, (int) $config->max_markets);
        }

        return array_slice($profiled, 0, (int) $config->max_markets);
    }
}