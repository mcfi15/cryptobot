@extends('layouts.app')
@section('title', 'Exchanges')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Exchange Accounts</h4>
    <button class="btn btn-primary" data-toggle="modal" data-target="#addExchangeModal">
        <i class="fas fa-plus mr-1"></i>Connect Exchange
    </button>
</div>

<div class="row">
    @forelse($exchanges as $exchange)
        <div class="col-md-4 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1">{{ strtoupper($exchange->exchange) }}</h5>
                            <small class="text-muted">{{ $exchange->label }}</small>
                        </div>
                        <span class="badge badge-{{ $exchange->status === 'connected' ? 'success' : ($exchange->status === 'error' ? 'danger' : 'secondary') }}">
                            {{ strtoupper($exchange->status) }}
                        </span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small">Trading</span>
                        <span class="badge badge-{{ $exchange->trading_enabled ? 'success' : 'secondary' }}">
                            {{ $exchange->trading_enabled ? 'ENABLED' : 'DISABLED' }}
                        </span>
                    </div>
                    @if($exchange->last_connection_check)
                        <div class="small text-muted mb-3">
                            Last check: {{ $exchange->last_connection_check->diffForHumans() }}
                        </div>
                    @endif
                    <div class="btn-group w-100">
                        <form method="POST" action="{{ route('exchanges.test', $exchange) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">Test</button>
                        </form>
                        <form method="POST" action="{{ route('exchanges.toggle', $exchange) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                {{ $exchange->trading_enabled ? 'Disable' : 'Enable' }} Trading
                            </button>
                        </form>
                        <form method="POST" action="{{ route('exchanges.destroy', $exchange) }}" class="d-inline"
                              onsubmit="return confirm('Remove this exchange?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card stat-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-link fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No exchanges connected yet.</p>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addExchangeModal">Connect Your First Exchange</button>
                </div>
            </div>
        </div>
    @endforelse
</div>

<div class="modal fade" id="addExchangeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('exchanges.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Connect Exchange</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Exchange</label>
                        <select name="exchange" class="form-control" required>
                            <option value="mexc">MEXC</option>
                            <option value="bybit">Bybit</option>
                            <option value="binance">Binance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Label</label>
                        <input type="text" name="label" class="form-control" placeholder="My MEXC Account" required>
                    </div>
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" name="api_key" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>API Secret</label>
                        <input type="password" name="api_secret" class="form-control" required>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Security Note:</strong> We recommend API keys with <strong>trading enabled</strong> and <strong>withdrawal disabled</strong>. Your credentials are encrypted and never exposed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Connect</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
