@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="admin-topbar">
    <div>
        <h1 class="admin-page-title">Dashboard</h1>
        <div class="admin-sub">{{ now()->format('l, F j, Y') }} — Platform overview</div>
    </div>
</div>

<div class="row">
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Users</span>
                <span class="stat-icon green"><i class="fas fa-users"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['users']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Exchanges</span>
                <span class="stat-icon cyan"><i class="fas fa-link"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['exchanges']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Bots</span>
                <span class="stat-icon purple"><i class="fas fa-robot"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['bots']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Bots Running</span>
                <span class="stat-icon amber"><i class="fas fa-play"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['running_bots']) }} <span class="small">/ {{ $stats['bot_uptime'] }}%</span></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Signals</span>
                <span class="stat-icon cyan"><i class="fas fa-bolt"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['signals']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Trades</span>
                <span class="stat-icon purple"><i class="fas fa-chart-line"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['trades']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Open Positions</span>
                <span class="stat-icon amber"><i class="fas fa-briefcase"></i></span>
            </div>
            <div class="stat-value">{{ number_format($stats['open_positions']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-3 mb-3">
        <div class="admin-card stat-card">
            <div class="d-flex align-items-center justify-content-between">
                <span class="stat-label">Realized PnL</span>
                <span class="stat-icon {{ $stats['pnl'] >= 0 ? 'green' : 'red' }}"><i class="fas fa-dollar-sign"></i></span>
            </div>
            <div class="stat-value {{ $stats['pnl'] >= 0 ? 'text-success' : 'text-danger' }}">
                {{ number_format($stats['pnl'], 2, '.', ',') }}
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-12 mb-4">
        <div class="admin-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                <div>
                    <h2 class="admin-section-title mb-0"><i class="fas fa-radar mr-1"></i> Market Scanner</h2>
                    <div class="admin-sub">Discovery engines &amp; scanner activity</div>
                </div>
                <div class="d-flex flex-wrap">
                    @if(!\App\Models\GlobalSetting::get('scanner_kill_switch', false))
                        <span class="badge badge-success mr-1 mb-1">Kill switch off</span>
                    @else
                        <span class="badge badge-danger mr-1 mb-1">Kill switch ON</span>
                    @endif
                    @if(!\App\Models\GlobalSetting::get('global_trading_kill_switch', false))
                        <span class="badge badge-success mr-1 mb-1">Global stop off</span>
                    @else
                        <span class="badge badge-danger mr-1 mb-1">Global stop ON</span>
                    @endif
                    @if(\App\Models\GlobalSetting::get('scanner_live_allowed', false))
                        <span class="badge badge-warning mr-1 mb-1">Live allowed</span>
                    @else
                        <span class="badge badge-secondary mr-1 mb-1">Paper only</span>
                    @endif
                </div>
            </div>

            <div class="admin-inline-stats">
                <div class="inline-stat"><span class="stat-label">Scanners</span><span class="stat-value">{{ number_format($scannerStats['configs']) }}</span></div>
                <div class="inline-stat"><span class="stat-label">Running</span><span class="stat-value text-success">{{ number_format($scannerStats['running']) }}</span></div>
                <div class="inline-stat"><span class="stat-label">Auto-trade on</span><span class="stat-value text-warning">{{ number_format($scannerStats['auto_trading']) }}</span></div>
                <div class="inline-stat"><span class="stat-label">Signals</span><span class="stat-value">{{ number_format($scannerStats['signals']) }}</span></div>
                <div class="inline-stat"><span class="stat-label">Executed</span><span class="stat-value">{{ number_format($scannerStats['executed']) }}</span></div>
                <div class="inline-stat"><span class="stat-label">Avg scan</span><span class="stat-value">{{ number_format($scannerStats['avg_scan_ms']) }}<span class="small text-muted">ms</span></span></div>
            </div>

            @if($recentScannerSignals->isNotEmpty())
                <div class="admin-sub mb-2">Latest scanner signals</div>
                <div style="overflow-x:auto;">
                    <table class="table-dark-custom">
                        <tr><th>User</th><th>Symbol</th><th>Exchange</th><th>Dir</th><th>Score</th><th>Quality</th><th>Status</th><th>When</th></tr>
                        @foreach($recentScannerSignals as $signal)
                            <tr>
                                <td>{{ $signal->user?->name ?? '—' }}</td>
                                <td style="font-weight:700;">{{ $signal->symbol }}</td>
                                <td><span class="badge badge-secondary text-uppercase" style="font-size:0.68rem;">{{ $signal->exchange }}·{{ $signal->market_type }}</span></td>
                                <td>
                                    <span class="badge {{ in_array($signal->direction, ['long', 'buy']) ? 'badge-success' : 'badge-danger' }}">{{ strtoupper($signal->direction) }}</span>
                                </td>
                                <td>{{ $signal->signal_score }}</td>
                                <td><span class="badge badge-{{ $signal->score_quality === 'reject' ? 'danger' : ($signal->score_quality === 'exceptional' ? 'success' : 'info') }}" style="font-size:0.68rem;">{{ $signal->score_quality }}</span></td>
                                <td><span class="badge badge-secondary text-uppercase" style="font-size:0.68rem;">{{ $signal->status }}</span></td>
                                <td>{{ $signal->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h2 class="admin-section-title">Recent Users</h2>
            <div class="admin-sub mb-3">Latest registrations</div>
            @if($recentUsers->isEmpty())
                <div class="empty-state text-center py-4">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <p class="mb-0">No users yet</p>
                </div>
            @else
                <table class="table-dark-custom">
                    <tr><th>Name</th><th>Email</th><th>Joined</th></tr>
                    @foreach($recentUsers as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="admin-card">
            <h2 class="admin-section-title">Recent Signals</h2>
            <div class="admin-sub mb-3">Latest strategy signals</div>
            @if($recentSignals->isEmpty())
                <div class="empty-state text-center py-4">
                    <i class="fas fa-bolt fa-2x mb-2"></i>
                    <p class="mb-0">No signals yet</p>
                </div>
            @else
                <table class="table-dark-custom">
                    <tr><th>Symbol</th><th>Direction</th><th>Score</th><th>Status</th><th>When</th></tr>
                    @foreach($recentSignals as $signal)
                        <tr>
                            <td style="font-weight:700;">{{ $signal->symbol }}</td>
                            <td>
                                <span class="badge {{ in_array($signal->direction, ['long','buy']) ? 'badge-success' : 'badge-danger' }}">{{ strtoupper($signal->direction) }}</span>
                            </td>
                            <td>{{ $signal->signal_score }}</td>
                            <td><span class="badge badge-secondary text-uppercase" style="font-size:0.68rem;">{{ $signal->status }}</span></td>
                            <td>{{ $signal->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="admin-card">
            <h2 class="admin-section-title">Recent Trades</h2>
            <div class="admin-sub mb-3">Latest executed orders across all bots</div>
            @if($recentTrades->isEmpty())
                <div class="empty-state text-center py-4">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <p class="mb-0">No trades yet</p>
                </div>
            @else
                <div style="overflow-x:auto;">
                    <table class="table-dark-custom">
                        <tr><th>Bot</th><th>Symbol</th><th>Side</th><th>Type</th><th>Quantity</th><th>Entry</th><th>PnL</th><th>Status</th><th>When</th></tr>
                        @foreach($recentTrades as $trade)
                            <tr>
                                <td>{{ $trade->bot?->name }}</td>
                                <td style="font-weight:700;">{{ $trade->symbol }}</td>
                                <td>
                                    <span class="badge {{ in_array($trade->side, ['long','buy']) ? 'badge-success' : 'badge-danger' }}">{{ strtoupper($trade->side) }}</span>
                                </td>
                                <td><span class="badge badge-secondary text-uppercase" style="font-size:0.68rem;">{{ $trade->market_type }}</span></td>
                                <td>{{ $trade->quantity }}</td>
                                <td>{{ $trade->entry_price }}</td>
                                <td class="{{ $trade->pnl !== null && $trade->pnl >= 0 ? 'text-success' : ($trade->pnl !== null && $trade->pnl < 0 ? 'text-danger' : 'text-muted') }}">
                                    {{ $trade->pnl !== null ? number_format($trade->pnl, 2, '.', ',') : '—' }}
                                </td>
                                <td><span class="badge badge-secondary text-uppercase" style="font-size:0.68rem;">{{ $trade->status }}</span></td>
                                <td>{{ $trade->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection