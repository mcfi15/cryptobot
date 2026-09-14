<?php

namespace App\Http\Controllers;

use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Models\GlobalSetting;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Models\ScannerWatchlistEntry;
use App\Services\Scanner\ActivityLogger;
use App\Services\Scanner\ScannerExecutionService;
use App\Services\Scanner\ScannerRiskEngine;
use App\Services\Scanner\ScannerService;
use App\Services\Scanner\SignalRepository;
use App\Services\Scanner\WatchlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScannerController extends Controller
{
    protected ScannerService $scanner;
    protected ScannerRiskEngine $riskEngine;
    protected ScannerExecutionService $execution;
    protected WatchlistService $watchlist;
    protected SignalRepository $watchlistRepository;

    public function __construct(
        ScannerService $scanner,
        ScannerRiskEngine $riskEngine,
        ScannerExecutionService $execution,
        WatchlistService $watchlist,
        SignalRepository $watchlistRepository
    ) {
        $this->scanner = $scanner;
        $this->riskEngine = $riskEngine;
        $this->execution = $execution;
        $this->watchlist = $watchlist;
        $this->watchlistRepository = $watchlistRepository;
    }

    public function index()
    {
        $user = Auth::user();
        $config = ScannerConfig::forUser($user->id);

        $signals = ScannerSignal::where('user_id', $user->id)
            ->orderByDesc('signal_score')
            ->limit(200)
            ->get();

        $groups = [
            'exceptional' => $signals->where('score_quality', 'exceptional'),
            'strong' => $signals->where('score_quality', 'strong'),
            'good' => $signals->where('score_quality', 'good'),
            'weak' => $signals->where('score_quality', 'weak'),
            'rejected' => $signals->where('score_quality', 'reject'),
        ];

        $recentRejected = ScannerSignal::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->latest()
            ->limit(50)
            ->get();

        $executed = ScannerSignal::where('user_id', $user->id)->where('status', 'executed')->count();
        $watching = ScannerSignal::where('user_id', $user->id)->where('status', 'watching')->count();
        $closed = ScannerSignal::where('user_id', $user->id)->where('status', 'closed')->count();

        $exchanges = $user->exchangeAccounts()->where('status', 'connected')->get();
        $watchlist = $this->watchlist->entries($config->id);

        $killSwitch = (bool) GlobalSetting::get('scanner_kill_switch', false);
        $globalKill = (bool) GlobalSetting::get('global_trading_kill_switch', false);
        $liveAllowed = (bool) GlobalSetting::get('scanner_live_allowed', false);

        $activity = ActivityLogger::recent($user->id, 30);

        return view('scanner.index', compact(
            'config', 'signals', 'groups', 'recentRejected',
            'executed', 'watching', 'closed', 'exchanges', 'watchlist',
            'killSwitch', 'globalKill', 'liveAllowed', 'activity'
        ));
    }

    public function start()
    {
        $config = ScannerConfig::forUser(Auth::id());
        $config->status = 'running';
        $config->paused_reason = null;
        $config->save();

        return redirect()->route('scanner.index')->with('success', 'Scanner started.');
    }

    public function stop()
    {
        $config = ScannerConfig::forUser(Auth::id());
        $config->status = 'stopped';
        $config->save();

        return redirect()->route('scanner.index')->with('success', 'Scanner stopped.');
    }

    public function updateConfig(Request $request)
    {
        $config = ScannerConfig::forUser(Auth::id());

        $validated = $request->validate([
            'scan_mode' => 'required|in:all,watchlist,hybrid',
            'market_type' => 'required|in:spot,futures,both',
            'exchanges' => 'nullable|array',
            'exchanges.*' => 'in:mexc,bybit,binance',
            'timeframes' => 'required|array|min:1|max:5',
            'timeframes.*' => 'string',
            'preferred_assets' => 'nullable|string',
            'quote_assets' => 'nullable|array',
            'quote_assets.*' => 'string',
            'min_signal_score' => 'required|integer|min:0|max:100',
            'min_ai_probability' => 'required|numeric|min:0|max:100',
            'min_risk_reward' => 'required|numeric|min:0.1|max:10',
            'max_volatility' => 'required|in:low,normal,high,extreme',
            'min_volume_24h' => 'required|numeric|min:0',
            'max_spread_pct' => 'required|numeric|min:0',
            'max_markets' => 'required|integer|min:1|max:1000',
            'auto_trading' => 'nullable|boolean',
            'risk_per_trade' => 'required|numeric|min:0.1|max:5',
            'max_daily_loss' => 'required|numeric|min:0.5|max:10',
            'max_open_positions' => 'required|integer|min:1|max:50',
            'max_leverage' => 'required|integer|min:1|max:125',
            'signal_expiry_minutes' => 'required|integer|min:1|max:1440',
            'trading_mode' => 'required|in:paper,live',
        ]);

        $validated['exchanges'] = $validated['exchanges'] ?? null;
        $validated['preferred_assets'] = $validated['preferred_assets'] !== null
            ? array_values(array_filter(array_map('trim', explode(',', $validated['preferred_assets'] ?? '')), fn ($v) => $v !== ''))
            : null;
        $validated['quote_assets'] = $validated['quote_assets'] ?? ['USDT'];
        $validated['auto_trading'] = array_key_exists('auto_trading', $validated) && (bool) $validated['auto_trading'];

        $config->fill($validated);
        $config->paper_mode = $config->trading_mode === 'paper';
        $config->save();

        return redirect()->route('scanner.index')->with('success', 'Scanner configuration updated.');
    }

    public function runScan()
    {
        $result = $this->scanner->run(Auth::user());
        return redirect()->route('scanner.index')
            ->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }

    // --- Watchlist ---------------------------------------------------

    public function watchlistStore(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'required|string|max:40',
            'exchange' => 'nullable|string|max:40',
            'market_type' => 'required|in:spot,futures,both',
        ]);

        $config = ScannerConfig::forUser(Auth::id());
        $result = $this->watchlist->add(
            Auth::id(), $config->id,
            $validated['symbol'], $validated['exchange'] ?? null, $validated['market_type']
        );

        return redirect()->route('scanner.index')
            ->with($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Added to watchlist.' : $result['error']);
    }

    public function watchlistDestroy(Request $request, int $entry)
    {
        $config = ScannerConfig::forUser(Auth::id());
        $this->watchlist->remove($config->id, $entry);
        return redirect()->route('scanner.index')->with('success', 'Removed from watchlist.');
    }

    public function watchlistToggle(Request $request, int $entry)
    {
        $config = ScannerConfig::forUser(Auth::id());
        $this->watchlist->setActive($config->id, $entry, $request->boolean('active'));
        return redirect()->route('scanner.index')->with('success', 'Watchlist updated.');
    }

    public function reorderWatchlist(Request $request)
    {
        $config = ScannerConfig::forUser(Auth::id());
        $this->watchlist->reorder($config->id, (array) $request->input('order'));
        return redirect()->route('scanner.index')->with('success', 'Watchlist reordered.');
    }

    // --- Signal lifecycle ----------------------------------------

    public function watch(Request $request, ScannerSignal $signal)
    {
        abort_unless($signal->user_id === Auth::id(), 404);
        $config = ScannerConfig::forUser(Auth::id());
        $this->repositoryWatch($signal);
        return redirect()->route('scanner.index')->with('success', 'Signal added to watchlist.');
    }

    public function unwatch(Request $request, ScannerSignal $signal)
    {
        abort_unless($signal->user_id === Auth::id(), 404);
        $config = ScannerConfig::forUser(Auth::id());
        $this->repositoryUnwatch($signal);
        ActivityLogger::log(Auth::id(), 'signal.unwatch', "Removed {$signal->symbol} from watchlist.", 'info', $config->id, $signal->id);
        return redirect()->route('scanner.index')->with('success', 'Signal removed from watchlist.');
    }

    public function dismiss(Request $request, ScannerSignal $signal)
    {
        abort_unless($signal->user_id === Auth::id(), 404);
        if (!$signal->isActive()) {
            return redirect()->route('scanner.index')->with('warning', 'Signal is no longer active.');
        }
        $config = ScannerConfig::forUser(Auth::id());
        $this->repositoryDismiss($signal);
        ActivityLogger::log(Auth::id(), 'signal.dismiss', "Dismissed {$signal->symbol} ({$signal->direction}).", 'danger', $config->id, $signal->id);
        return redirect()->route('scanner.index')->with('success', 'Signal dismissed.');
    }

    protected function repositoryWatch(ScannerSignal $signal): void
    {
        $this->watchlistRepository->markWatchlist($signal);
    }

    protected function repositoryUnwatch(ScannerSignal $signal): void
    {
        $this->watchlistRepository->removeWatchlist($signal);
    }

    protected function repositoryDismiss(ScannerSignal $signal): void
    {
        $this->watchlistRepository->markDismissed($signal);
    }

    // --- Analysis & execution ---------------------------------------

    public function showSignal(Request $request, ScannerSignal $signal)
    {
        abort_unless($signal->user_id === Auth::id(), 404);
        return response()->json([
            'id' => $signal->id,
            'symbol' => $signal->symbol,
            'exchange' => $signal->exchange,
            'market_type' => $signal->market_type,
            'direction' => $signal->direction,
            'timeframe' => $signal->timeframe,
            'score' => $signal->signal_score,
            'quality' => $signal->score_quality,
            'entry_price' => (float) $signal->entry_price,
            'stop_loss' => (float) $signal->stop_loss,
            'take_profit' => (float) $signal->take_profit,
            'risk_reward' => (float) $signal->risk_reward,
            'ai_probability' => (float) $signal->ai_probability,
            'volume_24h' => (float) ($signal->volume_24h ?? 0),
            'spread_pct' => (float) ($signal->spread_pct ?? 0),
            'volatility_class' => $signal->volatility_class,
            'market_regime' => $signal->market_regime,
            'score_breakdown' => $signal->score_breakdown,
            'reasons' => $signal->reasons,
            'structure' => $signal->structure,
            'timeframes' => array_keys($signal->timeframe_analysis ?? []),
            'invalidation' => $signal->invalidation,
            'status' => $signal->status,
            'risk_rejection' => $this->riskSummary($signal),
        ]);
    }

    public function tradeConfirm(Request $request, ScannerSignal $signal)
    {
        abort_unless($signal->user_id === Auth::id(), 404);
        abort_unless(in_array($signal->status, ['qualified', 'watching', 'entry_pending'], true), 422);

        $account = ExchangeAccount::find($signal->exchange_account_id);
        $market = ExchangeMarket::where('exchange', $signal->exchange)
            ->where('symbol', $signal->symbol)
            ->where('market_type', $signal->market_type)
            ->first();

        if (!$account || !$market) {
            return redirect()->route('scanner.index')->with('danger', 'Market metadata unavailable for this signal.');
        }

        $config = ScannerConfig::forUser(Auth::id());
        $equity = $this->execution->equity($account);
        $plan = $this->execution->prepareOrder($account, $market, $signal, $config, $equity);

        return view('scanner.partials.trade-confirm', compact('signal', 'config', 'plan', 'account', 'equity'));
    }

    public function trade(Request $request, ScannerSignal $signal)
    {
        abort_unless($signal->user_id === Auth::id(), 404);
        abort_unless(in_array($signal->status, ['qualified', 'watching', 'entry_pending'], true), 422);

        $config = ScannerConfig::forUser(Auth::id());
        $account = ExchangeAccount::find($signal->exchange_account_id);
        $market = ExchangeMarket::where('exchange', $signal->exchange)
            ->where('symbol', $signal->symbol)
            ->where('market_type', $signal->market_type)
            ->first();

        if (!$account || !$market) {
            return redirect()->route('scanner.index')->with('danger', 'Market metadata unavailable for this signal.');
        }

        // Risk engine is the final authority — a failing manual trade is never executed.
        $risk = $this->riskEngine->evaluate($account, [
            'symbol' => $signal->symbol,
            'base_asset' => $signal->base_asset,
            'entry_price' => (float) $signal->entry_price,
            'current_price' => (float) ($signal->current_price ?? $signal->entry_price),
            'stop_loss' => (float) $signal->stop_loss,
            'direction' => $signal->direction,
            'market_type' => $signal->market_type,
            'leverage_limit' => $market->leverage_limit ?? null,
        ], $config, manual: true);

        if (!$risk['pass']) {
            return redirect()->route('scanner.index')->with('danger', 'Trade blocked by risk engine: '.implode('; ', $risk['failures']));
        }

        $result = $this->execution->execute($signal, $account, $market, $config);

        return redirect()->route('scanner.index')
            ->with($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Trade executed.' : $result['message']);
    }

    public function refresh()
    {
        $user = Auth::user();
        $signals = ScannerSignal::where('user_id', $user->id)
            ->orderByDesc('signal_score')
            ->limit(100)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'symbol' => $s->symbol,
                'exchange' => $s->exchange,
                'market_type' => $s->market_type,
                'direction' => $s->direction,
                'timeframe' => $s->timeframe,
                'score' => $s->signal_score,
                'quality' => $s->score_quality,
                'ai' => (float) $s->ai_probability,
                'rr' => (float) $s->risk_reward,
                'price' => (float) $s->current_price,
                'status' => $s->status,
                'watch' => (bool) $s->watch,
                'volatility' => $s->volatility_class,
                'created_ago' => $s->created_at?->diffForHumans(),
            ]);

        $config = ScannerConfig::forUser($user->id);

        return response()->json([
            'config' => [
                'status' => $config->status,
                'auto_trading' => (bool) $config->auto_trading,
                'last_scan_at' => optional($config->last_scan_at)->diffForHumans(),
                'scanned_markets' => $config->scanned_markets,
                'qualified_signals' => $config->qualified_signals,
                'trading_mode' => $config->trading_mode,
                'paper_mode' => (bool) $config->paper_mode,
            ],
            'signals' => $signals->values(),
        ]);
    }

    protected function riskSummary(ScannerSignal $signal): array
    {
        $config = ScannerConfig::forUser(Auth::id());
        $account = ExchangeAccount::find($signal->exchange_account_id);
        if (!$account) return ['checked' => false, 'failures' => []];

        $summary = $this->riskEngine->evaluate($account, [
            'symbol' => $signal->symbol,
            'base_asset' => $signal->base_asset,
            'entry_price' => (float) $signal->entry_price,
            'current_price' => (float) ($signal->current_price ?? $signal->entry_price),
            'stop_loss' => (float) $signal->stop_loss,
            'direction' => $signal->direction,
            'market_type' => $signal->market_type,
        ], $config, manual: true);

        return ['checked' => true, 'pass' => $summary['pass'], 'failures' => $summary['failures']];
    }
}