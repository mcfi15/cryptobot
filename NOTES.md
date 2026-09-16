# CryptoBot — Scanner Notes (crawler scratchpad)

Living notes for the scanning/trading pipeline. Keep in sync with code changes.

## Pipeline
discovery → structuralFilter → liquidity (volume/spread) → capMarkets → analyze (score/AI/RR) → risk gate → execute.

## Signal gates (scanner_configs)
| Field | Default | Notes |
|---|---|---|
| `min_signal_score` | 55 | Was 75 — realistic scores cluster 50–70, 75 qualified ~0. |
| `min_ai_probability` | 55 | Was 70 — AiEngine sigmoid outputs ~55–75%. |
| `min_risk_reward` | 1.5 | Target is derived from this, so the RR gate practically never fires. |
| `min_volume_24h` | 1_000_000 | Quote volume. |
| `max_spread_pct` | 0.500 | 0 = unlimited. |
| `max_volatility` | high | low/normal/high/extreme. |
| `max_markets` | 100 | Deep-analysis cap per scan. |

## Risk fields (scanner_risk_extensions migration)
- `trail_high` / `trail_low` on `bot_positions` — trailing-stop watermarks.
- `exit_reason` on `bot_trades` — tp_hit / sl_hit / trailing_stop / time_exit / invalidation / manual.
- `scanner_activity_logs` table (ScannerActivity model; no updated_at column).
- `scanner_configs` additions: status (running/stopped/paused), paused_reason, peak_equity,
  cooldown_minutes, max_consecutive_losses, max_hold_hours, trailing_enabled,
  trailing_activation_pct, trailing_distance_pct, break_even_pct, last_scan_at,
  last_scan_duration_ms, scanned_markets, qualified_signals.
- `GlobalSetting` risk globals: emergency_stop, global_trading_kill_switch, scanner_kill_switch,
  scanner_live_allowed, scanner_default_min_score/ai/rr/volume/volatility, global_max_drawdown,
  liquidation_distance_min_pct, max_correlated_exposure, correlated_assets, max_risk_per_trade,
  max_leverage, max_open_positions, min_balance.

## Exit management (SignalMonitor, 1-min schedule)
- Trailing stop: activates after `trailing_activation_pct` in favor, distance `trailing_distance_pct`.
- Break-even: moves SL to entry after `break_even_pct`.
- Max-hold: closes at `max_hold_hours` (time_exit).
- Invalidation: closes when the strategy is no longer valid.
- Paper fills log exit fees (10 bps) + slippage; dry-run (`--dry-run`) never closes.

## Operations
- Scheduler (`routes/console.php`): `scanner:run` 5 min, `scanner:monitor` 1 min — requires platform
  cron / `php artisan schedule:work`. Queue worker required for auto-trade (`ProcessScannerSignal`).
- `php artisan scanner:debug --user=<id>` — read-only per-gate rejection breakdown + recommended
  thresholds. Flags: `--limit=`, `--detailed`, `--json`, `--apply`.
- Admin `Scanner settings` controls per-user mode (Paper/Live) and Auto-trade; nav pill reads the
  user's `paper_mode` (TradingServiceProvider). Live requires `scanner_live_allowed`; risk engine is
  the final authority.