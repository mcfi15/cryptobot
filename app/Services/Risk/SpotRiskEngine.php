<?php

namespace App\Services\Risk;

use App\Models\TradingBot;
use App\Models\BotPosition;
use App\Models\BotTrade;

class SpotRiskEngine
{
    public function checkTrade(TradingBot $bot, array $signal, float $accountBalance): array
    {
        $globalSettings = [
            'max_risk_per_trade' => (float) \App\Models\GlobalSetting::get('max_risk_per_trade', 0.5),
            'max_daily_loss' => (float) \App\Models\GlobalSetting::get('max_daily_loss', 2.0),
            'max_open_positions' => (int) \App\Models\GlobalSetting::get('max_open_positions', 10),
        ];

        $riskPerTrade = min((float) $bot->risk_per_trade, $globalSettings['max_risk_per_trade']);
        $maxDailyLossPct = min((float) $bot->max_daily_loss, $globalSettings['max_daily_loss']);

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

        $totalOpenExposure = BotPosition::where('bot_id', $bot->id)
            ->where('status', 'open')
            ->sum('quantity');
        if ($totalOpenExposure > $bot->max_position_size) {
            return ['allowed' => false, 'reason' => 'Maximum position size reached'];
        }

        $stopDistance = abs($signal['entry_price'] - $signal['stop_loss']) / $signal['entry_price'];
        if ($stopDistance == 0) {
            return ['allowed' => false, 'reason' => 'Invalid stop loss distance'];
        }

        $riskAmount = $accountBalance * ($riskPerTrade / 100);
        $notionalPositionSize = $riskAmount / $stopDistance;
        $notionalPositionSize = min($notionalPositionSize, $bot->max_position_size);

        if ($notionalPositionSize > $accountBalance * 0.95) {
            return ['allowed' => false, 'reason' => 'Insufficient balance for position'];
        }

        $quantity = $notionalPositionSize / $signal['entry_price'];

        return [
            'allowed' => true,
            'position_size' => $notionalPositionSize,
            'quantity' => $quantity,
            'risk_amount' => $riskAmount,
            'risk_per_trade' => $riskPerTrade,
            'stop_distance' => $stopDistance,
        ];
    }
}
