@extends('layouts.app')
@section('title', 'Market Scanner')

@section('header', 'Market Scanner')

@section('content')
<div class="scanner-page" x-data="scannerApp('{{ csrf_token() }}')">
    @if($killSwitch || $globalKill)
        <div class="alert alert-danger d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-exclamation-triangle mr-2"></i>
                @if($globalKill) <strong>Global trading kill switch is ON.</strong> @endif
                @if($killSwitch) <strong>Scanner kill switch is ON.</strong> @endif
                No scanner signals will auto-execute until an admin disables it.
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if(session('danger'))
        <div class="alert alert-danger">{{ session('danger') }}</div>
    @endif

    <!-- Control bar -->
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <span class="badge badge-{{ $config->status === 'running' ? 'success' : 'secondary' }} mr-2">
                    {{ strtoupper($config->status) }}
                </span>
                <span class="badge badge-{{ $config->paper_mode ? 'info' : 'warning' }} mr-2">
                    <i class="fas fa-{{ $config->paper_mode ? 'flask' : 'bolt' }} mr-1"></i>
                    {{ strtoupper($config->trading_mode) }}
                </span>
                @if($config->auto_trading)
                    <span class="badge badge-danger mr-2"><i class="fas fa-robot mr-1"></i> AUTO-TRADE ON</span>
                @endif
                <span class="text-muted small">
                    last scan:
                    @if($config->last_scan_at)
                        {{ $config->last_scan_at->diffForHumans() }}
                        <span x-text="'(' + lastScanMs + 'ms)'"></span>
                    @else
                        never
                    @endif
                </span>
            </div>
            <div class="d-flex">
                @if($config->status !== 'running')
                    <form method="POST" action="{{ route('scanner.start') }}" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-play mr-1"></i>Start</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('scanner.stop') }}" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-stop mr-1"></i>Stop</button>
                    </form>
                    <form method="POST" action="{{ route('scanner.run') }}" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-info btn-sm"><i class="fas fa-sync mr-1"></i>Scan now</button>
                    </form>
                @endif
                <button class="btn btn-outline-secondary btn-sm" @click="configOpen = !configOpen">
                    <i class="fas fa-cog mr-1"></i>Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-6 col-lg-3 mb-2">
            <div class="card stat-card"><div class="card-body">
                <div class="stat-label">Scanned markets</div>
                <div class="stat-value" x-text="scannedMarkets">{{ $config->scanned_markets }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card stat-card"><div class="card-body">
                <div class="stat-label">Qualified signals</div>
                <div class="stat-value" x-text="qualifiedSignals">{{ $config->qualified_signals }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card stat-card"><div class="card-body">
                <div class="stat-label">Executed</div>
                <div class="stat-value text-success">{{ $executed }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card stat-card"><div class="card-body">
                <div class="stat-label">Watching / Closed</div>
                <div class="stat-value">{{ $watching }} / {{ $closed }}</div>
            </div></div>
        </div>
    </div>

    <!-- Settings panel -->
    <div class="card mb-3" x-show="configOpen" x-cloak>
        <div class="card-header"><i class="fas fa-cog mr-1"></i> Scanner settings</div>
        <div class="card-body">
            <form method="POST" action="{{ route('scanner.config.update') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Scan mode</label>
                        <select name="scan_mode" class="form-control form-control-sm">
                            <option value="all" {{ $config->scan_mode === 'all' ? 'selected' : '' }}>All markets</option>
                            <option value="watchlist" {{ $config->scan_mode === 'watchlist' ? 'selected' : '' }}>Watchlist only</option>
                            <option value="hybrid" {{ $config->scan_mode === 'hybrid' ? 'selected' : '' }}>Hybrid (preferred first)</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Market type</label>
                        <select name="market_type" class="form-control form-control-sm">
                            <option value="both" {{ $config->market_type === 'both' ? 'selected' : '' }}>Spot + Futures</option>
                            <option value="spot" {{ $config->market_type === 'spot' ? 'selected' : '' }}>Spot</option>
                            <option value="futures" {{ $config->market_type === 'futures' ? 'selected' : '' }}>Futures</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Exchanges</label>
                        <select name="exchanges[]" class="form-control form-control-sm" multiple size="3">
                            @foreach(['mexc', 'bybit', 'binance'] as $ex)
                                <option value="{{ $ex }}" {{ in_array($ex, $config->exchanges ?? [], true) ? 'selected' : '' }}>{{ strtoupper($ex) }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Leave empty to scan all connected.</small>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Timeframes (primary first)</label>
                        <input type="text" name="timeframes[]" value="{{ implode(',', $config->timeframeList()) }}" class="form-control form-control-sm" placeholder="4h,1h,15m">
                        <small class="text-muted">Comma separated. Mutli-TF analysis.</small>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Min signal score</label>
                        <input type="number" name="min_signal_score" class="form-control form-control-sm" value="{{ $config->min_signal_score }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Min AI probability (%)</label>
                        <input type="number" step="0.01" name="min_ai_probability" class="form-control form-control-sm" value="{{ $config->min_ai_probability }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Min risk/reward</label>
                        <input type="number" step="0.1" name="min_risk_reward" class="form-control form-control-sm" value="{{ $config->min_risk_reward }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Max volatility</label>
                        <select name="max_volatility" class="form-control form-control-sm">
                            @foreach(['low', 'normal', 'high', 'extreme'] as $v)
                                <option value="{{ $v }}" {{ $config->max_volatility === $v ? 'selected' : '' }}>{{ ucfirst($v) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Min 24h volume (quote)</label>
                        <input type="number" step="1000" name="min_volume_24h" class="form-control form-control-sm" value="{{ $config->min_volume_24h }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Max spread (%)</label>
                        <input type="number" step="0.1" name="max_spread_pct" class="form-control form-control-sm" value="{{ $config->max_spread_pct }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Max markets per scan</label>
                        <input type="number" name="max_markets" class="form-control form-control-sm" value="{{ $config->max_markets }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Preferred assets (hybrid)</label>
                        <input type="text" name="preferred_assets" class="form-control form-control-sm" value="{{ implode(',', $config->preferred_assets ?? []) }}" placeholder="BTC,ETH,SOL">
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Risk per trade (%)</label>
                        <input type="number" step="0.1" name="risk_per_trade" class="form-control form-control-sm" value="{{ $config->risk_per_trade }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Max daily loss (%)</label>
                        <input type="number" step="0.1" name="max_daily_loss" class="form-control form-control-sm" value="{{ $config->max_daily_loss }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Max open positions</label>
                        <input type="number" name="max_open_positions" class="form-control form-control-sm" value="{{ $config->max_open_positions }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Max leverage</label>
                        <input type="number" name="max_leverage" class="form-control form-control-sm" value="{{ $config->max_leverage }}">
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Signal expiry (min)</label>
                        <input type="number" name="signal_expiry_minutes" class="form-control form-control-sm" value="{{ $config->signal_expiry_minutes }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Trading mode</label>
                        <select name="trading_mode" class="form-control form-control-sm">
                            <option value="paper" {{ $config->trading_mode === 'paper' ? 'selected' : '' }}>Paper (simulated)</option>
                            <option value="live" {{ $config->trading_mode === 'live' ? 'selected' : '' }} {{ $liveAllowed ? '' : 'disabled' }}>Live {{ $liveAllowed ? '' : '(admin must enable)' }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group d-flex align-items-end">
                        <div class="form-check form-check-inline align-middle">
                            <input type="checkbox" name="auto_trading" value="1" class="form-check-input" id="autoTradeCb" {{ $config->auto_trading ? 'checked' : '' }}>
                            <label class="form-check-label" for="autoTradeCb">
                                <i class="fas fa-robot mr-1"></i> Auto-trade qualifying ({{ $config->paper_mode ? 'paper' : 'live' }})
                            </label>
                        </div>
                    </div>
                </div>
                @if(!$liveAllowed && $config->trading_mode === 'live')
                    <div class="alert alert-warning small py-2">Live trading is not enabled globally (admin setting). Switched to paper-on-save.</div>
                @endif
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save settings</button>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Watchlist -->
        <div class="col-lg-3 mb-3">
            <div class="card">
                <div class="card-header"><i class="fas fa-star mr-1"></i> Watchlist</div>
                <div class="card-body p-2">
                    <form method="POST" action="{{ route('scanner.watchlist.store') }}" class="mb-2">
                        @csrf
                        <input type="text" name="symbol" class="form-control form-control-sm mb-2" placeholder="BTCUSDT">
                        <div class="d-flex">
                            <select name="exchange" class="form-control form-control-sm mr-1">
                                <option value="">Any exchange</option>
                                @foreach(['mexc', 'bybit', 'binance'] as $ex)
                                    <option value="{{ $ex }}">{{ strtoupper($ex) }}</option>
                                @endforeach
                            </select>
                            <select name="market_type" class="form-control form-control-sm">
                                <option value="both">Both</option>
                                <option value="spot">Spot</option>
                                <option value="futures">Futures</option>
                            </select>
                        </div>
                        <button class="btn btn-outline-primary btn-sm btn-block mt-2"><i class="fas fa-plus mr-1"></i>Add symbol</button>
                    </form>
                    <ul class="list-group list-group-flush small">
                        @forelse($watchlist as $w)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $w->symbol }}</strong>
                                    <small class="text-muted">{{ strtoupper($w->exchange ?? 'all') }} · {{ $w->market_type }}</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    @if($w->active)
                                        <span class="badge badge-success mr-1">on</span>
                                    @else
                                        <span class="badge badge-secondary mr-1">off</span>
                                    @endif
                                    <form method="POST" action="{{ route('scanner.watchlist.toggle', $w) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="active" value="{{ $w->active ? '0' : '1' }}">
                                        <button class="btn btn-xs btn-outline-secondary mr-1 p-0 px-1"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('scanner.watchlist.destroy', $w) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-outline-danger p-0 px-1"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted text-center">No symbols watched.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- Signal terminal -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-satellite-dish mr-1"></i> Discovery terminal</span>
                    <div class="btn-group btn-group-sm" role="group">
                        @foreach(['exceptional' => 'Exceptional', 'strong' => 'Strong', 'good' => 'Watch', 'rejected' => 'Rejected'] as $qv => $ql)
                            <button type="button" class="btn btn-outline-secondary btn-sm" :class="tab === '{{ $qv }}' ? 'active' : ''" @click="tab = '{{ $qv }}'">{{ $ql }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="card-body p-0">
                    @foreach(['exceptional', 'strong', 'good'] as $qv)
                        <div class="table-responsive" x-show="tab === '{{ $qv }}'" x-cloak>
                            <table class="table table-hover table-sm terminal-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Symbol</th><th>Dir</th><th>Score</th><th>AI</th><th>R:R</th><th>Entry</th><th>Chg</th><th>Expiry</th><th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($groups[$qv] as $s)
                                    @include('scanner.partials.signal-row', ['s' => $s])
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted py-3">No {{ $qv }} signals yet.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                    <div class="table-responsive" x-show="tab === 'rejected'" x-cloak>
                        <table class="table table-hover table-sm terminal-table mb-0">
                            <thead>
                                <tr>
                                    <th>Symbol</th><th>Dir</th><th>Score</th><th>Vol</th><th>Regime</th><th>Reject reason</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($recentRejected as $s)
                                <tr class="cr-row reject">
                                    <td><strong>{{ $s->symbol }}</strong></td>
                                    <td><span class="badge badge-dark">{{ strtoupper($s->direction) }}</span></td>
                                    <td>{{ $s->signal_score }}</td>
                                    <td>{{ $s->volatility_class ?? '–' }}</td>
                                    <td>{{ $s->market_regime ?? '–' }}</td>
                                    <td class="text-muted small">
                                        {{ collect($s->invalidation ?? [])->get('reason', 'score below threshold') }}
                                    </td>
                                    <td><button class="btn btn-xs btn-outline-secondary" @click="openInfo({{ $s->id }})"><i class="fas fa-search"></i></button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-3">No rejected signals yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis modal -->
    <div class="modal fade" id="signalModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" x-text="info.title"></h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <template x-if="info.loading"><div class="text-center py-4"><i class="fas fa-spinner fa-spin text-muted"></i></div></template>
                    <template x-if="!info.loading">
                        <div>
                            <div class="d-flex flex-wrap mb-2">
                                <template x-for="chip in info.chips" :key="chip.label">
                                    <span class="badge badge-secondary mr-1 mb-1" x-text="chip.value"></span>
                                </template>
                            </div>

                            <div x-show="info.risk">
                                <h6 class="text-warning"><i class="fas fa-shield-alt mr-1"></i>Risk pre-check</h6>
                                <div x-show="info.risk.checked && info.risk.pass" class="text-success small mb-2"><i class="fas fa-check-circle mr-1"></i>Risk engine passed</div>
                                <ul x-show="info.risk.checked && !info.risk.pass" class="text-danger small">
                                    <template x-for="f in info.risk.failures" :key="f"><li x-text="f"></li></template>
                                </ul>
                            </div>

                            <h6 class="mt-2"><i class="fas fa-balance-scale mr-1"></i>Score breakdown</h6>
                            <div class="row">
                                <template x-for="row in info.scores" :key="row.label">
                                    <div class="col-6 col-lg-3 mb-1">
                                        <div class="small text-muted" x-text="row.label"></div>
                                        <div class="font-weight-bold" x-text="row.value"></div>
                                        <div class="progress" style="height:4px;">
                                            <div class="progress-bar" :class="row.pct >= 70 ? 'bg-success' : (row.pct >= 40 ? 'bg-warning' : 'bg-danger')" :style="'width:'+row.pct+'%'"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <h6 class="mt-3"><i class="fas fa-lightbulb mr-1"></i>Why (measured features)</h6>
                            <ul class="small mb-0">
                                <template x-for="r in info.reasons" :key="r"><li x-text="r"></li></template>
                                <li x-show="info.reasons.length === 0" class="text-muted">No strong supporting reasons; treat as low-confidence.</li>
                            </ul>

                            <template x-if="info.levels">
                                <div class="d-flex flex-wrap mt-3">
                                    <span class="badge badge-success mr-2">Entry $<span x-text="info.levels.entry"></span></span>
                                    <span class="badge badge-danger mr-2">Stop $<span x-text="info.levels.stop"></span></span>
                                    <span class="badge badge-info">Target $<span x-text="info.levels.target"></span></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
                    <a x-show="info.tradeable" :href="info.tradeUrl" class="btn btn-success btn-sm"><i class="fas fa-rocket mr-1"></i>Trade this signal</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.timeago = function (iso) {
        if (!iso) return '–';
        const then = new Date(iso);
        const diff = then - Date.now();
        const mins = Math.round(Math.abs(diff) / 60000);
        if (mins < 1) return 'soon';
        return (diff < 0 ? '-' : '+') + mins + 'm';
    };
    function scannerApp(csrf) {
        return {
            tab: 'strong',
            configOpen: false,
            scannedMarkets: {{ $config->scanned_markets }},
            qualifiedSignals: {{ $config->qualified_signals }},
            lastScanMs: {{ $config->last_scan_duration_ms ?? 0 }},
            signals: [],
            info: { loading: false, title: '', chips: [], scores: [], reasons: [], levels: null, tradeable: false, tradeUrl: '#', risk: null },
init() {
        this.poll();
        setInterval(() => this.poll(), 15000);
        window.addEventListener('open-signal', e => this.openInfo(e.detail));
    },
    timeago(iso) {
        if (!iso) return '–';
        const then = new Date(iso);
        const diff = then - Date.now();
        const mins = Math.round(Math.abs(diff) / 60000);
        if (mins < 1) return 'soon';
        return (diff < 0 ? '-' : '+') + mins + 'm';
    },
            poll() {
                fetch('{{ route('scanner.refresh') }}', { headers: { 'X-CSRF-TOKEN': csrf } })
                    .then(r => r.json())
                    .then(d => {
                        if (d.config) {
                            this.scannedMarkets = d.config.scanned_markets;
                            this.qualifiedSignals = d.config.qualified_signals;
                            this.lastScanMs = d.config.last_scan_duration_ms ?? 0;
                        }
                        this.signals = d.signals || [];
                    })
                    .catch(() => {});
            },
            openInfo(id) {
                this.info = { loading: true, title: '', chips: [], scores: [], reasons: [], levels: null, tradeable: false, tradeUrl: '#', risk: null };
                $('#signalModal').modal('show');
                fetch('/scanner/signals/' + id, { headers: { 'X-CSRF-TOKEN': csrf } })
                    .then(r => r.json())
                    .then(d => {
                        const bd = d.score_breakdown || {};
                        const comps = bd.components || {};
                        let scores = [];
                        for (const k of ['trend','momentum','volume','structure','mtf','ai']) {
                            if (comps[k] !== undefined) {
                                scores.push({ label: k.toUpperCase(), value: comps[k], pct: comps[k] });
                            }
                        }
                        this.info = {
                            loading: false,
                            title: d.symbol + ' · ' + d.exchange.toUpperCase() + ' · ' + d.timeframe + ' · ' + d.direction.toUpperCase(),
                            chips: [
                                { label: 'DIR', value: d.direction.toUpperCase() },
                                { label: 'SCORE', value: d.score + ' / ' + d.quality.toUpperCase() },
                                { label: 'AI', value: d.ai_probability + '%' },
                                { label: 'R:R', value: d.risk_reward },
                                { label: 'VOL', value: d.volatility_class },
                                { label: 'MODE', value: d.market_type },
                            ],
                            scores,
                            reasons: d.reasons || [],
                            levels: {
                                entry: d.entry_price,
                                stop: d.stop_loss,
                                target: d.take_profit,
                            },
                            tradeable: ['qualified','watching','entry_pending'].includes(d.status),
                            tradeUrl: '/scanner/signals/' + d.id + '/confirm',
                            risk: d.risk_rejection,
                        };
                    })
                    .catch(() => { this.info.loading = false; });
            },
        };
    }
    </script>
</div>
@endsection