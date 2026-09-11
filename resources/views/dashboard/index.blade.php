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
                <div class="text-warning font-weight-bold">PAPER</div>
            </div>
        </div>
    </div>
</div>

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
