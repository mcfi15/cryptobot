@extends('layouts.app')
@section('title', 'Confirm trade')

@section('header', 'Confirm scanner trade')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-rocket mr-1"></i>
                {{ $signal->symbol }} · {{ strtoupper($signal->exchange) }} · {{ strtoupper($signal->market_type) }}
            </div>
            <div class="card-body">
                <div class="mb-3">
                    @foreach([
                        'Direction' => strtoupper($signal->direction),
                        'Signal score' => $signal->signal_score,
                        'AI probability' => number_format((float) $signal->ai_probability, 2).'%',
                        'Risk / reward' => $signal->risk_reward,
                        'Timeframe' => $signal->timeframe,
                        'Quality' => strtoupper($signal->score_quality),
                    ] as $label => $value)
                        <span class="badge badge-secondary mr-1 mb-1">{{ $label }}: <strong>{{ $value }}</strong></span>
                    @endforeach
                </div>

                <h6><i class="fas fa-credit-card mr-1"></i> Order plan</h6>
                @if($plan['ok'])
                    <table class="table table-sm">
                        <tbody>
                            <tr><td>Side</td><td><strong>{{ strtoupper($plan['plan']['side']) }}</strong></td></tr>
                            <tr><td>Quantity</td><td>{{ $plan['plan']['quantity'] }}</td></tr>
                            <tr><td>Estimated entry</td><td>${{ number_format($plan['plan']['entry_price'], 8, '.', '') }}</td></tr>
                            <tr><td>Stop loss</td><td class="text-danger">${{ number_format($plan['plan']['stop_loss'], 8, '.', '') }}</td></tr>
                            <tr><td>Take profit</td><td class="text-success">${{ number_format($plan['plan']['take_profit'], 8, '.', '') }}</td></tr>
                            <tr><td>Leverage</td><td>{{ $plan['plan']['leverage'] }}x</td></tr>
                            <tr><td>Risk amount</td><td>${{ number_format($plan['plan']['risk_amount'], 2) }}</td></tr>
                            <tr><td>Notional</td><td>${{ number_format($plan['plan']['notional'], 2) }}</td></tr>
                        </tbody>
                    </table>

                    @if(!$config->paper_mode)
                        <div class="alert alert-warning py-2 small"><i class="fas fa-bolt mr-1"></i>Live mode: this will send a <strong>real market order</strong> to {{ strtoupper($signal->exchange) }}.</div>
                    @else
                        <div class="alert alert-info py-2 small"><i class="fas fa-flask mr-1"></i>Paper mode: simulated fill at current price. No real order.</div>
                    @endif

                    <div class="alert alert-secondary py-2 small mb-3">
                        Trading on <strong>{{ $account->label }}</strong>
                        (equity ≈ ${{ number_format($equity, 2) }}).
                    </div>

                    <form method="POST" action="{{ route('scanner.signal.trade', $signal) }}">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>Confirm and execute</button>
                        <a href="{{ route('scanner.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                @else
                    <div class="alert alert-danger">
                        <strong>Cannot trade this signal.</strong>
                        <ul class="mb-0 small mt-1">
                            @foreach($plan['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <a href="{{ route('scanner.index') }}" class="btn btn-outline-secondary btn-sm">Back to scanner</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection