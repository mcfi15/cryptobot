<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeAccount;
use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Services\Scanner\ActivityLogger;
use App\Services\Scanner\ScannerExecutionService;
use App\Services\Scanner\ScannerRiskEngine;
use App\Services\Scanner\ScannerService;
use App\Services\Scanner\SignalRepository;
use App\Services\Scanner\WatchlistService;
use Illuminate\Http\Request;

class ScannerApiController extends Controller
{
    public function status(Request $request)
    {
        $config = ScannerConfig::forUser($request->user()->id);

        return response()->json([
            'ok' => true,
            'data' => [
                'status' => $config->status,
                'auto_trading' => (bool) $config->auto_trading,
                'trading_mode' => $config->trading_mode,
                'paper_mode' => (bool) $config->paper_mode,
                'kill_switch' => (bool) GlobalSetting::get('scanner_kill_switch', false),
                'global_kill_switch' => (bool) GlobalSetting::get('global_trading_kill_switch', false),
                'last_scan_at' => optional($config->last_scan_at)->toIso8601String(),
                'scanned_markets' => (int) $config->scanned_markets,
                'qualified_signals' => (int) $config->qualified_signals,
                'last_scan_duration_ms' => $config->last_scan_duration_ms,
            ],
        ]);
    }

    public function signals(Request $request)
    {
        $query = ScannerSignal::where('user_id', $request->user()->id)
            ->orderByDesc('signal_score');

        if ($request->has('status')) {
            $query->whereIn('status', explode(',', $request->get('status')));
        }
        if ($request->has('quality')) {
            $query->where('score_quality', $request->get('quality'));
        }

        $signals = $query->limit(min(200, max(1, (int) $request->get('limit', 100))))->get();

        return response()->json([
            'ok' => true,
            'data' => $signals->map(fn ($s) => $this->serialize($s)),
        ]);
    }

    public function signal(Request $request, int $signal)
    {
        $signal = ScannerSignal::where('user_id', $request->user()->id)->find($signal);
        if (!$signal) {
            return response()->json(['ok' => false, 'message' => 'Signal not found'], 404);
        }
        return response()->json(['ok' => true, 'data' => $this->serialize($signal, true)]);
    }

    public function run(Request $request, ScannerService $scanner)
    {
        $result = $scanner->run($request->user());
        return response()->json(['ok' => $result['ok'], 'message' => $result['message'], 'data' => $result]);
    }

    public function trade(Request $request, int $signal, ScannerRiskEngine $riskEngine, ScannerExecutionService $execution)
    {
        $user = $request->user();
        $signal = ScannerSignal::where('user_id', $user->id)->find($signal);
        if (!$signal) {
            return response()->json(['ok' => false, 'message' => 'Signal not found'], 404);
        }
        if (!in_array($signal->status, ['qualified', 'watching', 'entry_pending'], true)) {
            return response()->json(['ok' => false, 'message' => 'Signal no longer executable'], 422);
        }

        $config = ScannerConfig::forUser($user->id);
        $account = ExchangeAccount::find($signal->exchange_account_id);
        $market = \App\Models\ExchangeMarket::where('exchange', $signal->exchange)
            ->where('symbol', $signal->symbol)
            ->where('market_type', $signal->market_type)
            ->first();

        if (!$account || !$market) {
            return response()->json(['ok' => false, 'message' => 'Market metadata unavailable'], 422);
        }

        $risk = $riskEngine->evaluate($account, [
            'symbol' => $signal->symbol,
            'base_asset' => $signal->base_asset,
            'entry_price' => (float) $signal->entry_price,
            'current_price' => (float) ($signal->current_price ?? $signal->entry_price),
        ], $config, manual: true);

        if (!$risk['pass']) {
            return response()->json([
                'ok' => false,
                'message' => 'Trade blocked by risk engine: '.implode('; ', $risk['failures']),
                'failures' => $risk['failures'],
            ], 422);
        }

        $result = $execution->execute($signal, $account, $market, $config);
        if (!$result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Trade executed.', 'trade_id' => $result['trade']->id]);
    }

    public function watchlist(Request $request, WatchlistService $watchlist)
    {
        $config = ScannerConfig::forUser($request->user()->id);
        return response()->json([
            'ok' => true,
            'data' => $watchlist->entries($config->id),
        ]);
    }

    public function watch(Request $request, int $signal, SignalRepository $repository)
    {
        $signal = ScannerSignal::where('user_id', $request->user()->id)->find($signal);
        if (!$signal) {
            return response()->json(['ok' => false, 'message' => 'Signal not found'], 404);
        }
        $repository->markWatchlist($signal);
        return response()->json(['ok' => true, 'message' => 'Signal added to watchlist.']);
    }

    public function unwatch(Request $request, int $signal, SignalRepository $repository)
    {
        $signal = ScannerSignal::where('user_id', $request->user()->id)->find($signal);
        if (!$signal) {
            return response()->json(['ok' => false, 'message' => 'Signal not found'], 404);
        }
        $repository->removeWatchlist($signal);
        return response()->json(['ok' => true, 'message' => 'Signal removed from watchlist.']);
    }

    public function dismiss(Request $request, int $signal, SignalRepository $repository)
    {
        $signal = ScannerSignal::where('user_id', $request->user()->id)->find($signal);
        if (!$signal) {
            return response()->json(['ok' => false, 'message' => 'Signal not found'], 404);
        }
        if (!$signal->isActive()) {
            return response()->json(['ok' => false, 'message' => 'Signal no longer active'], 422);
        }
        $repository->markDismissed($signal);
        return response()->json(['ok' => true, 'message' => 'Signal dismissed.']);
    }

    public function activity(Request $request)
    {
        $items = ActivityLogger::recent($request->user()->id, (int) $request->get('limit', 30));
        return response()->json([
            'ok' => true,
            'data' => $items->map(fn ($a) => [
                'id' => $a->id,
                'level' => $a->level,
                'event' => $a->event,
                'message' => $a->message,
                'signal_id' => $a->signal_id,
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]);
    }

    protected function serialize(ScannerSignal $s, bool $full = false): array
    {
        return [
            'id' => $s->id,
            'exchange' => $s->exchange,
            'symbol' => $s->symbol,
            'market_type' => $s->market_type,
            'direction' => $s->direction,
            'timeframe' => $s->timeframe,
            'status' => $s->status,
            'score' => (int) $s->signal_score,
            'quality' => $s->score_quality,
            'ai_probability' => (float) $s->ai_probability,
            'entry_price' => (float) $s->entry_price,
            'stop_loss' => (float) $s->stop_loss,
            'take_profit' => (float) $s->take_profit,
            'risk_reward' => (float) $s->risk_reward,
            'current_price' => (float) ($s->current_price ?? 0),
            'volume_24h' => (float) ($s->volume_24h ?? 0),
            'spread_pct' => (float) ($s->spread_pct ?? 0),
            'volatility_class' => $s->volatility_class,
            'market_regime' => $s->market_regime,
            'watch' => (bool) $s->watch,
            'created_at' => $s->created_at?->toIso8601String(),
            'expires_at' => $s->expires_at?->toIso8601String(),
        ] + ($full ? $this->fullDetails($s) : []);
    }

    protected function fullDetails(ScannerSignal $s): array
    {
        return [
            'score_breakdown' => $s->score_breakdown,
            'reasons' => $s->reasons,
            'structure' => $s->structure,
            'indicator_snapshot' => $s->indicators,
            'timeframe_analysis' => $s->timeframe_analysis,
            'invalidation' => $s->invalidation,
        ];
    }
}