<?php

namespace App\Services\Risk;

use App\Models\TradingBot;
use App\Models\BotPosition;
use App\Models\BotTrade;

class FuturesRiskEngine
{
    public function checkTrade(TradingBot $bot, array $signal, float $accountBalance): array
    {
        $riskPerTrade = min((float) $bot->risk_per_trade, 0.5);
        $maxDailyLossPct = min((float) $bot->max_daily_loss, 2.0);
        $maxLeverage = min((int) $bot->max_leverage, 20);

        $requestedLeverage = $signal['leverage'] ?? 1;
        if ($requestedLeverage > $maxLeverage) {
            return ['allowed' => false, 'reason' => 'Maximum leverage exceeded'];
        }

        $todayPnl = BotTrade::where('bot_id', $bot->id)
            ->whereDate('closed_at', today())
            ->sum('pnl');
        $dailyLossPct = abs($todayPnl) / $accountBalance * 100;
        if ($todayPnl < 0 && $dailyLossPct >= $maxDailyLossPct) {
            return ['allowed' => false, 'reason' => 'Daily loss limit reached'];
        }

        $openPositions = BotPosition::where('bot_id', $bot->id)->where('status', 'open')->count();
        if ($openPositions >= $bot->max_open_positions) {
            return ['allowed' => false, 'reason' => 'Maximum open positions reached'];
        }

        $totalExposure = BotPosition::where('bot_id', $bot->id)
            ->where('status', 'open')
            ->sum(DB::raw('quantity * entry_price'));
        if ($totalExposure > $accountBalance * $maxLeverage) {
            return ['allowed' => false, 'reason' => 'Maximum leverage exposure reached'];
        }

        $stopDistance = abs($signal['entry_price'] - $signal['stop_loss']) / $signal['entry_price'];
        $riskAmount = $accountBalance * ($riskPerTrade / 100);
        $positionSize = ($riskAmount / $stopDistance) * $signal['entry_price'];
        $marginRequired = $positionSize / $signal['leverage'];

        if ($marginRequired > $accountBalance * 0.95) {
            return ['allowed' => false, 'reason' => 'Insufficient margin for position'];
        }

        $liquidationDistance = (1 / $signal['leverage']) * 0.9;
        if ($stopDistance > $liquidationDistance) {
            return ['allowed' => false, 'reason' => 'Stop loss too close to liquidation price'];
        }

        return [
            'allowed' => true,
            'position_size' => $positionSize,
            'margin_required' => $marginRequired,
            'leverage' => $signal['leverage'] ?? 1,
            'risk_amount' => $riskAmount,
            'liquidation_distance' => $liquidationDistance,
        ];
    }
}
