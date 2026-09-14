@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Dashboard</h4>
    <small class="text-muted">Last updated: {{ now()->format('M d, Y H:i') }}</small>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Connected Exchanges</div>
                <div class="stat-value">{{ $exchanges->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Active Bots</div>
                <div class="stat-value text-success">{{ $activeBots->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Open Positions</div>
                <div class="stat-value">{{ $positions->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Total PnL</div>
                <div class="stat-value {{ $totalPnl >= 0 ? 'text-success' : 'text-danger' }}">
                    ${{ number_format($totalPnl, 2) }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Today's PnL</div>
                <div class="{{ $todayPnl >= 0 ? 'text-success' : 'text-danger' }}">
                    ${{ number_format($todayPnl, 2) }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Win Rate</div>
                <div class="stat-value">{{ $winRate }}%</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Total Trades</div>
                <div class="stat-value">{{ $totalTrades }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Trading Mode</div>
                <div class="{{ ($scannerStats['trading_mode'] ?? 'paper') === 'live' ? 'text-danger' : 'text-warning' }} font-weight-bold">
                    {{ strtoupper($scannerStats['trading_mode'] ?? 'paper') }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Scanner Status</strong>
                <a href="{{ route('scanner.index') }}" class="btn btn-xs btn-outline-secondary">Open</a>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small">State</span>
                    <span class="badge badge-{{ ($scannerStats['status'] ?? 'stopped') === 'running' ? 'success' : (($scannerStats['status'] ?? '') === 'paused' ? 'warning' : 'secondary') }}">
                        {{ strtoupper($scannerStats['status'] ?? 'stopped') }}
                    </span>
                </div>
                @if(!empty($scannerStats['paused_reason']))
                    <div class="alert alert-warning py-1 px-2 small mb-2">{{ $scannerStats['paused_reason'] }}</div>
                @endif
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Auto-trading</span>
                    <span class="badge badge-{{ $scannerStats['auto_trading'] ? 'success' : 'secondary' }}">{{ $scannerStats['auto_trading'] ? 'ON' : 'OFF' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Last scan</span>
                    <span class="small">{{ optional($scannerStats['last_scan_at'])->diffForHumans() ?? 'never' }}</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Qualified</span><strong>{{ $scannerStats['qualified'] }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Watching</span><strong>{{ $scannerStats['watching'] }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Executed</span><strong>{{ $scannerStats['executed'] }}</strong>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-header bg-white"><strong>Top Opportunities</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <tbody>
                    @forelse($opportunities as $op)
                        <tr>
                            <td>
                                <strong>{{ $op->symbol }}</strong>
                                <small class="text-muted d-block">{{ strtoupper($op->exchange) }} · {{ strtoupper($op->direction) }}</small>
                            </td>
                            <td class="text-right">
                                <span class="score-badge {{ $op->score_quality }}">{{ $op->signal_score }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-3">No active opportunities</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100">
            <div class="card-header bg-white"><strong>Performance</strong></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Profit factor</span>
                    <strong>{{ $performanceSummary['profit_factor'] === PHP_FLOAT_MAX ? '∞' : $performanceSummary['profit_factor'] }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Avg win</span>
                    <span class="text-success">${{ number_format($performanceSummary['avg_win'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Avg loss</span>
                    <span class="text-danger">${{ number_format($performanceSummary['avg_loss'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">Max drawdown</span>
                    <span class="text-warning">${{ number_format($performanceSummary['max_drawdown'], 2) }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">Best / worst</span>
                    <span class="small">
                        <span class="text-success">${{ number_format($performanceSummary['best_trade'], 2) }}</span>
                        /
                        <span class="text-danger">${{ number_format($performanceSummary['worst_trade'], 2) }}</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

@if(isset($activity) && $activity->count() > 0)
<div class="card stat-card mb-4">
    <div class="card-header bg-white"><strong>Recent Activity</strong></div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($activity as $item)
                <li class="list-group-item bg-transparent">
                    <span class="badge badge-{{ $item->level === 'danger' ? 'danger' : ($item->level === 'warning' ? 'warning' : ($item->level === 'success' ? 'success' : 'secondary')) }} mr-2">
                        {{ strtoupper($item->level) }}
                    </span>
                    <span class="small">{{ $item->message }}</span>
                    <small class="text-muted d-block">{{ $item->created_at?->diffForHumans() }}</small>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="card stat-card mb-4">
            <div class="card-header bg-white"><strong>Exchange Accounts</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Exchange</th><th>Status</th><th>Trading</th><th>Bots</th></tr></thead>
                    <tbody>
                    @forelse($exchangeData as $ex)
                        <tr>
                            <td><strong>{{ strtoupper($ex['exchange']) }}</strong> <small class="text-muted">{{ $ex['label'] }}</small></td>
                            <td><span class="badge badge-{{ $ex['status'] === 'connected' ? 'success' : 'danger' }}">{{ strtoupper($ex['status']) }}</span></td>
                            <td><span class="badge badge-{{ $ex['trading_enabled'] ? 'success' : 'secondary' }}">{{ $ex['trading_enabled'] ? 'YES' : 'NO' }}</span></td>
                            <td>{{ $ex['bots'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No exchanges connected</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card mb-4">
            <div class="card-header bg-white"><strong>Active Bots</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Exchange</th><th>Mode</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($bots as $bot)
                        <tr>
                            <td><a href="{{ route('bots.show', $bot) }}">{{ $bot->name }}</a></td>
                            <td>{{ strtoupper($bot->exchangeAccount->exchange ?? '') }}</td>
                            <td><span class="badge badge-info">{{ strtoupper($bot->mode) }}</span></td>
                            <td><span class="badge badge-{{ $bot->status === 'running' ? 'success' : ($bot->status === 'paused' ? 'warning' : 'secondary') }}">{{ strtoupper($bot->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No bots created</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($positions->count() > 0)
<div class="card stat-card mb-4">
    <div class="card-header bg-white"><strong>Open Positions</strong></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Symbol</th><th>Side</th><th>Entry</th><th>Current</th><th>PnL</th><th>Exchange</th></tr></thead>
            <tbody>
            @foreach($positions as $pos)
                <tr>
                    <td>{{ $pos->symbol }}</td>
                    <td><span class="badge badge-{{ $pos->side === 'long' ? 'success' : 'danger' }}">{{ strtoupper($pos->side) }}</span></td>
                    <td>${{ number_format($pos->entry_price, 2) }}</td>
                    <td>${{ number_format($pos->current_price ?? 0, 2) }}</td>
                    <td class="{{ ($pos->unrealized_pnl ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($pos->unrealized_pnl ?? 0, 2) }}
                    </td>
                    <td>{{ strtoupper($pos->exchange) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
