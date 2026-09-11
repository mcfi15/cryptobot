@extends('layouts.app')
@section('title', 'Create Bot')

@section('content')
<h4 class="mb-4">Create Trading Bot</h4>

<div class="card stat-card">
    <div class="card-body">
        <form method="POST" action="{{ route('bots.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Bot Name</label>
                        <input type="text" name="name" class="form-control" placeholder="BTC Spot Trend Bot" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Exchange Account</label>
                        <select name="exchange_account_id" class="form-control" required>
                            @foreach($exchanges as $ex)
                                <option value="{{ $ex->id }}">{{ strtoupper($ex->exchange) }} - {{ $ex->label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Market Type</label>
                        <select name="market_type" class="form-control" required>
                            <option value="spot">Spot</option>
                            <option value="futures">Futures</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Trading Mode</label>
                        <select name="mode" class="form-control" required>
                            <option value="paper" selected>Paper Trading (Default)</option>
                            <option value="signal_only">Signal Only</option>
                            <option value="manual">Manual</option>
                            <option value="live">Live Trading</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Strategy</label>
                        <select name="strategy" class="form-control" required>
                            <option value="trend_following">Trend Following</option>
                            <option value="momentum">Momentum</option>
                            <option value="breakout">Breakout</option>
                            <option value="mean_reversion">Mean Reversion</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Symbols (comma-separated)</label>
                        <input type="text" name="symbols[]" class="form-control" value="BTCUSDT" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Primary Timeframe</label>
                        <select name="timeframes[]" class="form-control" required>
                            <option value="1h">1 Hour</option>
                            <option value="4h" selected>4 Hour</option>
                            <option value="1d">1 Day</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr>
            <h6 class="mb-3">Risk Settings</h6>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Risk Per Trade (%)</label>
                        <input type="number" name="risk_per_trade" class="form-control" value="0.5" step="0.1" min="0.1" max="5">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Max Daily Loss (%)</label>
                        <input type="number" name="max_daily_loss" class="form-control" value="2" step="0.5" min="0.5" max="10">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Max Open Positions</label>
                        <input type="number" name="max_open_positions" class="form-control" value="3" min="1" max="20">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Max Leverage</label>
                        <input type="number" name="max_leverage" class="form-control" value="1" min="1" max="125">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>AI Threshold (%)</label>
                        <input type="number" name="ai_threshold" class="form-control" value="70" min="50" max="100">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Signal Score Threshold</label>
                        <input type="number" name="signal_threshold" class="form-control" value="75" min="50" max="100">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Max Position Size ($)</label>
                        <input type="number" name="max_position_size" class="form-control" value="1000" min="10">
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <button type="submit" class="btn btn-primary btn-lg mt-3">Create Bot</button>
        </form>
    </div>
</div>
@endsection
