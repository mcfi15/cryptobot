<?php

namespace App\Services\Scanner;

use App\Models\BotTrade;
use App\Models\ScannerSignal;
use Illuminate\Support\Collection;

/**
 * Statistics derived exclusively from recorded trades — never fabricated.
 */
class PerformanceService
{
    /**
     * @return array{total_trades:int, wins:int, losses:int, win_rate:float,
     *               total_pnl:float, today_pnl:float, avg_win:float, avg_loss:float,
     *               profit_factor:float, max_drawdown:float, best_trade:float, worst_trade:float,
     *               by_strategy:array, series:array}
     */
    public function summary(int $userId, int $days = 90): array
    {
        $closed = $this->closedTrades($userId, $days);

        $wins = $closed->filter(fn ($t) => (float) $t->pnl > 0);
        $losses = $closed->filter(fn ($t) => (float) $t->pnl < 0);

        $total = $closed->sum(fn ($t) => (float) $t->pnl);
        $grossWin = $wins->sum(fn ($t) => (float) $t->pnl);
        $grossLoss = abs($losses->sum(fn ($t) => (float) $t->pnl));
        $count = $closed->count();

        [$drawdown, $series] = $this->drawdownSeries($closed);

        return [
            'total_trades' => $count,
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'win_rate' => $count > 0 ? round($wins->count() / $count * 100, 1) : 0.0,
            'total_pnl' => round($total, 8),
            'today_pnl' => $this->todayPnl($userId),
            'avg_win' => $wins->count() > 0 ? round($grossWin / $wins->count(), 8) : 0.0,
            'avg_loss' => $losses->count() > 0 ? round(-$grossLoss / $losses->count(), 8) : 0.0,
            'profit_factor' => $grossLoss > 0 ? round($grossWin / $grossLoss, 3) : ($grossWin > 0 ? PHP_FLOAT_MAX : 0.0),
            'max_drawdown' => round($drawdown, 8),
            'best_trade' => $closed->max(fn ($t) => (float) $t->pnl) ?? 0.0,
            'worst_trade' => $closed->min(fn ($t) => (float) $t->pnl) ?? 0.0,
            'by_strategy' => $this->byStrategy($closed),
            'series' => $series,
        ];
    }

    protected function closedTrades(int $userId, int $days): Collection
    {
        $since = now()->subDays(max(1, $days));

        return BotTrade::query()
            ->where('user_id', $userId)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $since)
            ->whereNotNull('pnl')
            ->with('signal')
            ->orderBy('closed_at')
            ->get();
    }

    protected function todayPnl(int $userId): float
    {
        return (float) BotTrade::query()
            ->where('user_id', $userId)
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->startOfDay())
            ->whereNotNull('pnl')
            ->sum('pnl');
    }

    /**
     * Trade-by-trade cumulative equity drawdown and full series.
     */
    protected function drawdownSeries(Collection $closed): array
    {
        $equity = 0.0;
        $peak = 0.0;
        $maxDrawdown = 0.0;
        $series = [];

        foreach ($closed as $t) {
            $equity += (float) $t->pnl;
            if ($equity > $peak) {
                $peak = $equity;
            }
            $dd = $peak > 0 ? $peak - $equity : 0.0;
            if ($dd > $maxDrawdown) {
                $maxDrawdown = $dd;
            }
            $series[] = [
                'closed_at' => optional($t->closed_at)->format('Y-m-d H:i'),
                'pnl' => round((float) $t->pnl, 8),
                'equity' => round($equity, 8),
                'drawdown' => round($maxDrawdown, 8),
                'symbol' => $t->symbol,
                'exit_reason' => $t->exit_reason ?? 'unknown',
            ];
        }

        return [$maxDrawdown, $series];
    }

    protected function byStrategy(Collection $closed): array
    {
        $mapped = [];
        foreach ($closed as $t) {
            // Manual trades have no signal; enumerates unknown strategy as 'manual'.
            $strategy = $t->signal_id && $t->signal
                ? ($t->signal->strategy ?: 'technical')
                : 'manual';
            if (!isset($mapped[$strategy])) {
                $mapped[$strategy] = ['count' => 0, 'wins' => 0, 'pnl' => 0.0];
            }
            $mapped[$strategy]['count']++;
            $mapped[$strategy]['pnl'] += (float) $t->pnl;
            if ((float) $t->pnl > 0) {
                $mapped[$strategy]['wins']++;
            }
        }

        $result = [];
        foreach ($mapped as $strategy => $s) {
            $result[] = [
                'strategy' => $strategy,
                'count' => $s['count'],
                'wins' => $s['wins'],
                'win_rate' => $s['count'] > 0 ? round($s['wins'] / $s['count'] * 100, 1) : 0.0,
                'pnl' => round($s['pnl'], 8),
            ];
        }
        usort($result, fn ($a, $b) => $b['pnl'] <=> $a['pnl']);

        return $result;
    }
}