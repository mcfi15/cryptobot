<div class="ticker-bar">
    <div class="ticker-track">
        @for($i = 0; $i < 2; $i++)
            <div class="ticker-item">
                <span class="live"><span class="dot"></span> {{ $navLiveTrading ? 'Live Trading' : 'Paper Trading' }}</span>
            </div>
            <div class="ticker-item">
                <span class="dot" style="background: var(--green)"></span>
                Systems Operational
            </div>
            <div class="ticker-item">
                <span class="tick-label">{{ $navExchanges->count() }}</span>
                Exchange{{ $navExchanges->count() === 1 ? '' : 's' }} Connected
            </div>
            <div class="ticker-item">
                <span class="tick-label">{{ $navBots->where('status', 'running')->count() }}</span>
                Bot{{ $navBots->where('status', 'running')->count() === 1 ? '' : 's' }} Running
            </div>
            <div class="ticker-item">
                <span class="dot" style="background: var(--cyan)"></span>
                Risk Engine Active
            </div>
            <div class="ticker-item">
                <span class="dot" style="background: var(--purple)"></span>
                24/7 Automated Scanning
            </div>
            <div class="ticker-item">
                <span class="dot" style="background: var(--amber)"></span>
                Stop-Loss & Take-Profit Protection
            </div>
        @endfor
    </div>
</div>