# CryptoBot - Multi-Exchange AI Crypto Trading Automation Platform

A production-grade multi-exchange cryptocurrency trading automation platform built with Laravel 13, Python/FastAPI, and modern trading infrastructure.

## Overview

Connect your own exchange accounts (MEXC, Bybit, Binance) and use the platform for market analysis, AI-assisted signals, automated trading, paper trading, backtesting, risk management, and portfolio monitoring.

**Your funds remain on your exchange.** The platform communicates through exchange APIs and never requires deposits.

## Features

- **Multi-exchange support**: MEXC, Bybit, Binance (extensible via adapters)
- **Spot & Futures** trading
- **4 trading modes**: Manual, Signal Only, Paper (default), Live
- **AI/ML prediction engine** (Python/FastAPI)
- **Technical analysis**: EMA, SMA, RSI, MACD, ATR, Bollinger Bands, Stochastic RSI, CCI, OBV, VWAP, market structure
- **Market regime detection**: Trending, Ranging, Volatility
- **Modular strategies**: Trend Following, Momentum, Breakout, Mean Reversion
- **Risk management**: Spot + Futures risk engines, portfolio risk, position sizing
- **Backtesting engine** with realistic costs (fees, slippage)
- **Paper trading** with real market data
- **Multi-bot management**
- **Real-time updates** with WebSockets (with polling fallback)
- **Emergency stop / global kill switch**

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ (SQLite for development/tests)
- Redis (optional, for queues/cache)
- Python 3.10+ (for AI engine)
- Node.js & npm (for frontend assets)

## Installation

See [INSTALLATION.md](INSTALLATION.md).

## Security

- Exchange API credentials are **encrypted at rest** with Laravel encryption
- Secrets never exposed to frontend or logs
- Live trading is **disabled by default** (`LIVE_TRADING=false`)
- API permission recommendations (trading on, withdrawal off)

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [EXCHANGES.md](EXCHANGES.md) - Exchange adapters
- [SPOT_TRADING.md](SPOT_TRADING.md) - Spot trading
- [FUTURES_TRADING.md](FUTURES_TRADING.md) - Futures trading
- [AI.md](AI.md) - AI/ML pipeline
- [BACKTESTING.md](BACKTESTING.md) - Backtesting
- [RISK_MANAGEMENT.md](RISK_MANAGEMENT.md) - Risk engine
- [SECURITY.md](SECURITY.md) - Security model
- [API.md](API.md) - API reference
- [DEPLOYMENT.md](DEPLOYMENT.md) - Deploy to VPS

## Artisan Commands

```bash
php artisan trading:health          # Check system health
php artisan trading:sync-markets    # Sync markets from exchanges
php artisan trading:run-signals     # Run signal generation for active bots
php artisan trading:stop-all        # Emergency stop all bots
```

## License

This is a BETA trading platform. **Trading involves significant risk.** The system is designed to measure and report actual performance; it makes no guaranteed-profit or accuracy claims.
