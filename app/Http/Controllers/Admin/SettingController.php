<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingController extends Controller
{
    protected array $generalKeys = [
        'site_name',
        'site_tagline',
        'site_description',
        'support_email',
    ];

    protected array $tradingKeys = [
        'max_risk_per_trade',
        'max_daily_loss',
        'max_leverage',
        'max_open_positions',
    ];

    protected array $scannerBooleanKeys = [
        'scanner_kill_switch',
        'global_trading_kill_switch',
        'scanner_live_allowed',
    ];

    protected array $scannerNumericKeys = [
        'scanner_default_min_score',
        'scanner_default_ai',
        'scanner_default_rr',
        'scanner_default_expiry',
        'global_max_drawdown',
        'liquidation_distance_min_pct',
        'max_correlated_exposure',
    ];

    protected array $scannerTextKeys = [
        'correlated_assets',
    ];

    public function edit(): View
    {
        $keys = array_merge($this->generalKeys, $this->tradingKeys, $this->scannerNumericKeys, $this->scannerTextKeys);
        $rows = collect(\App\Models\GlobalSetting::whereIn('key', $keys)->get())
            ->pluck('value', 'key');

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $rows[$key] ?? null;
        }

        $booleans = [
            'maintenance_mode' => (bool) GlobalSetting::get('maintenance_mode', false),
            'live_trading_enabled' => (bool) GlobalSetting::get('live_trading_enabled', false),
            'emergency_stop' => (bool) GlobalSetting::get('emergency_stop', false),
            'scanner_kill_switch' => (bool) GlobalSetting::get('scanner_kill_switch', false),
            'global_trading_kill_switch' => (bool) GlobalSetting::get('global_trading_kill_switch', false),
            'scanner_live_allowed' => (bool) GlobalSetting::get('scanner_live_allowed', false),
        ];

        return view('admin.settings', ['settings' => $settings] + $booleans);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:1000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'site_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'site_favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg,svg,webp', 'max:512'],
            'maintenance_mode' => ['boolean'],
            'live_trading_enabled' => ['boolean'],
            'emergency_stop' => ['boolean'],
            'scanner_kill_switch' => ['boolean'],
            'global_trading_kill_switch' => ['boolean'],
            'scanner_live_allowed' => ['boolean'],
            'max_risk_per_trade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_daily_loss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_leverage' => ['nullable', 'integer', 'min:1', 'max:125'],
            'max_open_positions' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'scanner_default_min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'scanner_default_ai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scanner_default_rr' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'scanner_default_expiry' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'global_max_drawdown' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'liquidation_distance_min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_correlated_exposure' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'correlated_assets' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($this->generalKeys as $key) {
            if (array_key_exists($key, $validated)) {
                GlobalSetting::set($key, $validated[$key]);
                Cache::forget('site_setting:' . $key);
            }
        }

        if ($request->hasFile('site_logo')) {
            $slug = 'logo-' . Str::random(6) . '.' . $request->file('site_logo')->getClientOriginalExtension();
            $request->file('site_logo')->move(public_path('uploads/settings'), $slug);
            GlobalSetting::set('site_logo', '/uploads/settings/' . $slug);
        }

        if ($request->hasFile('site_favicon')) {
            $slug = 'favicon-' . Str::random(6) . '.' . $request->file('site_favicon')->getClientOriginalExtension();
            $request->file('site_favicon')->move(public_path('uploads/settings'), $slug);
            GlobalSetting::set('site_favicon', '/uploads/settings/' . $slug);
        }

        GlobalSetting::set('maintenance_mode', $request->boolean('maintenance_mode'));
        GlobalSetting::set('live_trading_enabled', $request->boolean('live_trading_enabled'));
        GlobalSetting::set('emergency_stop', $request->boolean('emergency_stop'));
        Cache::forget('site_setting:maintenance_mode');
        Cache::forget('site_setting:live_trading_enabled');
        Cache::forget('site_setting:emergency_stop');

        foreach ($this->scannerBooleanKeys as $key) {
            GlobalSetting::set($key, $request->boolean($key));
            Cache::forget('site_setting:'.$key);
        }

        foreach ($this->scannerNumericKeys as $key) {
            if (array_key_exists($key, $validated)) {
                GlobalSetting::set($key, (float) $validated[$key]);
                Cache::forget('site_setting:'.$key);
            }
        }

        foreach ($this->scannerTextKeys as $key) {
            if (array_key_exists($key, $validated)) {
                GlobalSetting::set($key, (string) $validated[$key]);
                Cache::forget('site_setting:'.$key);
            }
        }

        foreach ($this->tradingKeys as $key) {
            if (array_key_exists($key, $validated)) {
                GlobalSetting::set($key, (float) $validated[$key]);
                Cache::forget('site_setting:' . $key);
            }
        }

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Site settings saved.');
    }
}