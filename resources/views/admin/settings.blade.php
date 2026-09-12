@extends('admin.layouts.app')

@section('title', 'Site Settings')

@section('content')
<div class="admin-topbar">
    <div>
        <h1 class="admin-page-title">Site Settings</h1>
        <div class="admin-sub">Global platform configuration — logo, favicon, branding &amp; risk defaults</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="admin-card">
                <h2 class="admin-section-title">General</h2>
                <div class="admin-sub mb-3">Branding shown across the site and admin panel</div>

                <div class="form-group">
                    <label for="site_name">Site Name</label>
                    <input id="site_name" type="text" class="form-control @error('site_name') is-invalid @enderror" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? site_setting('site_name', config('app.name', 'CryptoBot'))) }}">
                    @error('site_name') <span class="small text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="site_tagline">Tagline</label>
                    <input id="site_tagline" type="text" class="form-control @error('site_tagline') is-invalid @enderror" name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? '') }}">
                    @error('site_tagline') <span class="small text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="site_description">Description</label>
                    <textarea id="site_description" rows="3" class="form-control @error('site_description') is-invalid @enderror" name="site_description">{{ old('site_description', $settings['site_description'] ?? '') }}</textarea>
                    @error('site_description') <span class="small text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label for="support_email">Support Email</label>
                    <input id="support_email" type="email" class="form-control @error('support_email') is-invalid @enderror" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? '') }}">
                    @error('support_email') <span class="small text-danger">{{ $message }}</span> @enderror
                </div>

                <hr>

                <div class="row mb-2">
                    <div class="col-6">
                        <label>Site Logo</label>
                        <div class="preview-logo mb-2">
                            @if($siteLogo = site_setting('site_logo'))
                                <img src="{{ asset($siteLogo) }}" alt="">
                            @else
                                <i class="fas fa-image"></i>
                            @endif
                        </div>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="site_logo" name="site_logo" accept=".png,.jpg,.jpeg,.svg,.webp">
                            <label class="custom-file-label form-control" for="site_logo">Choose logo…</label>
                        </div>
                        @error('site_logo') <span class="small text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-6">
                        <label>Favicon</label>
                        <div class="preview-favicon mb-2">
                            @if($siteFavicon = site_setting('site_favicon'))
                                <img src="{{ asset($siteFavicon) }}" alt="">
                            @else
                                <i class="fas fa-image"></i>
                            @endif
                        </div>
                        <div class="custom-file mt-2">
                            <input type="file" class="custom-file-input" id="site_favicon" name="site_favicon" accept=".png,.ico,.jpg,.jpeg,.svg,.webp">
                            <label class="custom-file-label form-control" for="site_favicon">Choose favicon…</label>
                        </div>
                        @error('site_favicon') <span class="small text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <hr>

                <div class="admin-form-switch">
                    <div class="switch-label">
                        <div>
                            <div class="switch-title">Maintenance Mode</div>
                            <div class="switch-desc">Temporarily take the site offline</div>
                        </div>
                        <input type="hidden" name="maintenance_mode" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode" value="1" {{ $maintenance_mode ? 'checked' : '' }}>
                            <label class="custom-control-label" for="maintenance_mode"></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="admin-card">
                <h2 class="admin-section-title">Trading</h2>
                <div class="admin-sub mb-3">Global trading defaults and safety switches</div>

                <div class="admin-form-switch">
                    <div class="switch-label">
                        <div>
                            <div class="switch-title">Live Trading</div>
                            <div class="switch-desc">Globally enable/disable live order placement</div>
                        </div>
                        <input type="hidden" name="live_trading_enabled" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="live_trading_enabled" name="live_trading_enabled" value="1" {{ $live_trading_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="live_trading_enabled"></label>
                        </div>
                    </div>
                    <div class="switch-label">
                        <div>
                            <div class="switch-title text-danger">Emergency Stop</div>
                            <div class="switch-desc">Kill all trading immediately</div>
                        </div>
                        <input type="hidden" name="emergency_stop" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="emergency_stop" name="emergency_stop" value="1" {{ $emergency_stop ? 'checked' : '' }}>
                            <label class="custom-control-label" for="emergency_stop"></label>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="form-row">
                    <div class="col-6 form-group">
                        <label for="max_risk_per_trade">Max Risk / Trade (%)</label>
                        <input id="max_risk_per_trade" type="number" step="0.1" min="0" max="100" class="form-control" name="max_risk_per_trade" value="{{ old('max_risk_per_trade', $settings['max_risk_per_trade'] ?? 0.5) }}">
                    </div>
                    <div class="col-6 form-group">
                        <label for="max_daily_loss">Max Daily Loss (%)</label>
                        <input id="max_daily_loss" type="number" step="0.1" min="0" max="100" class="form-control" name="max_daily_loss" value="{{ old('max_daily_loss', $settings['max_daily_loss'] ?? 2.0) }}">
                    </div>
                    <div class="col-6 form-group">
                        <label for="max_leverage">Max Leverage</label>
                        <input id="max_leverage" type="number" step="1" min="1" max="125" class="form-control" name="max_leverage" value="{{ old('max_leverage', $settings['max_leverage'] ?? 20) }}">
                    </div>
                    <div class="col-6 form-group">
                        <label for="max_open_positions">Max Open Positions</label>
                        <input id="max_open_positions" type="number" step="1" min="1" max="1000" class="form-control" name="max_open_positions" value="{{ old('max_open_positions', $settings['max_open_positions'] ?? 10) }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-4">
            <div class="admin-card">
                <h2 class="admin-section-title"><i class="fas fa-radar mr-1"></i> Market Scanner</h2>
                <div class="admin-sub mb-3">Scanner safety switches and default thresholds for all users</div>

                <div class="admin-form-switch">
                    <div class="switch-label">
                        <div>
                            <div class="switch-title">Scanner Kill Switch</div>
                            <div class="switch-desc">Blocks all auto-execution of scanned signals</div>
                        </div>
                        <input type="hidden" name="scanner_kill_switch" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="scanner_kill_switch" name="scanner_kill_switch" value="1" {{ $scanner_kill_switch ? 'checked' : '' }}>
                            <label class="custom-control-label" for="scanner_kill_switch"></label>
                        </div>
                    </div>
                    <div class="switch-label">
                        <div>
                            <div class="switch-title text-danger">Global Trading Kill Switch</div>
                            <div class="switch-desc">Emergency — block all scanner executions immediately</div>
                        </div>
                        <input type="hidden" name="global_trading_kill_switch" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="global_trading_kill_switch" name="global_trading_kill_switch" value="1" {{ $global_trading_kill_switch ? 'checked' : '' }}>
                            <label class="custom-control-label" for="global_trading_kill_switch"></label>
                        </div>
                    </div>
                    <div class="switch-label">
                        <div>
                            <div class="switch-title">Allow Live Scanner Trading</div>
                            <div class="switch-desc">Let users select live mode for the scanner (off = paper only)</div>
                        </div>
                        <input type="hidden" name="scanner_live_allowed" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="scanner_live_allowed" name="scanner_live_allowed" value="1" {{ $scanner_live_allowed ? 'checked' : '' }}>
                            <label class="custom-control-label" for="scanner_live_allowed"></label>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="admin-sub mb-2">New-user scanner defaults</div>
                <div class="form-row">
                    <div class="col-6 col-lg-3 form-group">
                        <label for="scanner_default_min_score">Min Signal Score</label>
                        <input id="scanner_default_min_score" type="number" min="0" max="100" class="form-control" name="scanner_default_min_score" value="{{ old('scanner_default_min_score', $settings['scanner_default_min_score'] ?? 75) }}">
                    </div>
                    <div class="col-6 col-lg-3 form-group">
                        <label for="scanner_default_ai">Min AI Probability (%)</label>
                        <input id="scanner_default_ai" type="number" step="0.1" min="0" max="100" class="form-control" name="scanner_default_ai" value="{{ old('scanner_default_ai', $settings['scanner_default_ai'] ?? 70) }}">
                    </div>
                    <div class="col-6 col-lg-3 form-group">
                        <label for="scanner_default_rr">Min Risk / Reward</label>
                        <input id="scanner_default_rr" type="number" step="0.1" min="0.1" max="10" class="form-control" name="scanner_default_rr" value="{{ old('scanner_default_rr', $settings['scanner_default_rr'] ?? 1.5) }}">
                    </div>
                    <div class="col-6 col-lg-3 form-group">
                        <label for="scanner_default_expiry">Signal Expiry (min)</label>
                        <input id="scanner_default_expiry" type="number" min="1" max="1440" class="form-control" name="scanner_default_expiry" value="{{ old('scanner_default_expiry', $settings['scanner_default_expiry'] ?? 30) }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-success btn-lg px-4">
        <i class="fas fa-save mr-1"></i> Save Settings
    </button>
</form>
@endsection