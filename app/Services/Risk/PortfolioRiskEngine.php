<?php

namespace App\Services\Risk;

use App\Models\BotPosition;
use App\Models\TradingBot;

class PortfolioRiskEngine
{
    public function checkPortfolioRisk(TradingBot $bot, float $accountBalance): array
    {
        $allPositions = BotPosition::where('user_id', $bot->user_id)
            ->where('status', 'open')
            ->get();

        $totalExposure = $allPositions->sum(fn($p) => $p->quantity * $p->entry_price);
        $totalUnrealizedPnl = $allPositions->sum('unrealized_pnl');

        $btcPositions = $allPositions->filter(fn($p) => str_contains($p->symbol, 'BTC'));
        $btcExposure = $btcPositions->sum(fn($p) => $p->quantity * $p->entry_price);
        $btcConcentration = $totalExposure > 0 ? ($btcExposure / $totalExposure * 100) : 0;

        $maxCorrelatedExposure = 50;
        if ($btcConcentration > $maxCorrelatedExposure) {
            return ['allowed' => false, 'reason' => 'Excessive BTC concentration: ' . round($btcConcentration, 1) . '%'];
        }

        $totalExposurePct = $totalExposure / $accountBalance * 100;
        if ($totalExposurePct > 300) {
            return ['allowed' => false, 'reason' => 'Total portfolio exposure too high: ' . round($totalExposurePct, 1) . '%'];
        }

        $todayPnl = $allPositions->sum('unrealized_pnl');
        $dailyLossPct = abs(min(0, $todayPnl)) / $accountBalance * 100;
        if ($dailyLossPct > 5) {
            return ['allowed' => false, 'reason' => 'Portfolio daily loss limit exceeded'];
        }

        return [
            'allowed' => true,
            'total_exposure' => $totalExposure,
            'total_exposure_pct' => $totalExposurePct,
            'btc_concentration' => $btcConcentration,
            'unrealized_pnl' => $totalUnrealizedPnl,
            'open_positions' => $allPositions->count(),
        ];
    }
}
