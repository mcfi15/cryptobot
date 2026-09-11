@extends('layouts.app')
@section('title', 'Bots')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Trading Bots</h4>
    <a href="{{ route('bots.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Create Bot</a>
</div>

<div class="card stat-card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th><th>Exchange</th><th>Market</th><th>Strategy</th>
                    <th>Mode</th><th>Status</th><th>PnL</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($bots as $bot)
                <tr>
                    <td><a href="{{ route('bots.show', $bot) }}" class="font-weight-bold">{{ $bot->name }}</a></td>
                    <td>{{ strtoupper($bot->exchangeAccount->exchange ?? '') }}</td>
                    <td><span class="badge badge-info">{{ strtoupper($bot->market_type) }}</span></td>
                    <td>{{ ucwords(str_replace('_', ' ', $bot->strategy)) }}</td>
                    <td><span class="badge badge-secondary">{{ strtoupper($bot->mode) }}</span></td>
                    <td>
                        <span class="badge badge-{{ $bot->status === 'running' ? 'success' : ($bot->status === 'paused' ? 'warning' : ($bot->status === 'error' ? 'danger' : 'secondary')) }}">
                            {{ strtoupper($bot->status) }}
                        </span>
                    </td>
                    <td class="{{ ($bot->trades()->where('status','closed')->sum('pnl') ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($bot->trades()->where('status','closed')->sum('pnl') ?? 0, 2) }}
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            @if($bot->status !== 'running')
                                <form method="POST" action="{{ route('bots.start', $bot) }}" class="d-inline">@csrf<button type="submit" class="btn btn-outline-success btn-sm"><i class="fas fa-play"></i></button></form>
                            @endif
                            @if($bot->status === 'running')
                                <form method="POST" action="{{ route('bots.stop', $bot) }}" class="d-inline">@csrf<button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-stop"></i></button></form>
                                <form method="POST" action="{{ route('bots.pause', $bot) }}" class="d-inline">@csrf<button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-pause"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No bots created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
