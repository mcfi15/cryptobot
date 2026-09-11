@extends('layouts.app')
@section('title', $bot->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">{{ $bot->name }}</h4>
        <small class="text-muted">{{ strtoupper($bot->exchangeAccount->exchange ?? '') }} | {{ strtoupper($bot->market_type) }} | {{ ucwords(str_replace('_', ' ', $bot->strategy)) }}</small>
    </div>
    <div>
        <span class="badge badge-{{ $bot->status === 'running' ? 'success' : ($bot->status === 'paused' ? 'warning' : 'secondary') }} p-2 mr-2">
            {{ strtoupper($bot->status) }}
        </span>
        <span class="badge badge-info p-2">{{ strtoupper($bot->mode) }}</span>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-2">
        <div class="card stat-card"><div class="card-body text-center">
            <div class="text-muted small">Risk/Trade</div>
            <div class="font-weight-bold">{{ $bot->risk_per_trade }}%</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card"><div class="card-body text-center">
            <div class="text-muted small">Max Daily Loss</div>
            <div class="font-weight-bold">{{ $bot->max_daily_loss }}%</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card"><div class="card-body text-center">
            <div class="text-muted small">Signals</div>
            <div class="font-weight-bold">{{ $bot->signals()->count() }}</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card"><div class="card-body text-center">
            <div class="text-muted small">Trades</div>
            <div class="font-weight-bold">{{ $bot->trades()->count() }}</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card"><div class="card-body text-center">
            <div class="text-muted small">Open Positions</div>
            <div class="font-weight-bold">{{ $bot->positions()->where('status','open')->count() }}</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card"><div class="card-body text-center">
            <div class="text-muted small">Total PnL</div>
            <div class="font-weight-bold {{ ($bot->trades()->where('status','closed')->sum('pnl') ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                ${{ number_format($bot->trades()->where('status','closed')->sum('pnl') ?? 0, 2) }}
            </div>
        </div></div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="btn-group">
            @if($bot->status !== 'running')
                <form method="POST" action="{{ route('bots.start', $bot) }}" class="d-inline">@csrf<button class="btn btn-success"><i class="fas fa-play mr-1"></i>Start</button></form>
            @endif
            @if($bot->status === 'running')
                <form method="POST" action="{{ route('bots.stop', $bot) }}" class="d-inline">@csrf<button class="btn btn-danger"><i class="fas fa-stop mr-1"></i>Stop</button></form>
                <form method="POST" action="{{ route('bots.pause', $bot) }}" class="d-inline">@csrf<button class="btn btn-warning"><i class="fas fa-pause mr-1"></i>Pause</button></form>
            @endif
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card stat-card mb-4">
            <div class="card-header bg-white"><strong>Recent Signals</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Symbol</th><th>Dir</th><th>Score</th><th>Confidence</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($recentSignals as $signal)
                        <tr>
                            <td>{{ $signal->symbol }}</td>
                            <td><span class="badge badge-{{ $signal->direction === 'long' || $signal->direction === 'buy' ? 'success' : 'danger' }}">{{ strtoupper($signal->direction) }}</span></td>
                            <td>{{ $signal->signal_score }}/100</td>
                            <td>{{ $signal->confidence }}%</td>
                            <td><span class="badge badge-secondary">{{ strtoupper($signal->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No signals yet</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card mb-4">
            <div class="card-header bg-white"><strong>Open Positions</strong></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Symbol</th><th>Side</th><th>Entry</th><th>Current</th><th>PnL</th></tr></thead>
                    <tbody>
                    @forelse($bot->positions()->where('status','open')->get() as $pos)
                        <tr>
                            <td>{{ $pos->symbol }}</td>
                            <td><span class="badge badge-{{ $pos->side === 'long' ? 'success' : 'danger' }}">{{ strtoupper($pos->side) }}</span></td>
                            <td>${{ number_format($pos->entry_price, 2) }}</td>
                            <td>${{ number_format($pos->current_price ?? 0, 2) }}</td>
                            <td class="{{ ($pos->unrealized_pnl ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                                ${{ number_format($pos->unrealized_pnl ?? 0, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No open positions</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
