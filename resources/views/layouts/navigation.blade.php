<nav class="crypto-navbar" x-data="{ open: false, userOpen: false, isActive: (r) => r === '{{ request()->route()?->getName() ?? '' }}' }">
    <div class="crypto-navbar-inner">
        <!-- Brand -->
        <a href="{{ route('dashboard') }}" class="nav-brand">
            @if($siteLogo = site_setting('site_logo'))
                <img src="{{ asset($siteLogo) }}" alt="Logo" style="height:38px; border-radius:8px;">
            @else
                <span class="brand-mark"><i class="fas fa-robot"></i></span>
            @endif
            <span class="brand-text">
                {{ site_setting('site_name', 'CryptoBot') }}
                <span class="brand-sub">{{ site_setting('site_tagline', 'Automated AI Trading') }}</span>
            </span>
        </a>

        <!-- Desktop nav -->
        <div class="crypto-nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="{{ route('exchanges.index') }}" class="{{ request()->routeIs('exchanges.*') ? 'active' : '' }}">
                <i class="fas fa-link"></i> Exchanges
            </a>
            <a href="{{ route('bots.index') }}" class="{{ request()->routeIs('bots.*') ? 'active' : '' }}">
                <i class="fas fa-robot"></i> Bots
            </a>
            <a href="{{ route('scanner.index') }}" class="{{ request()->routeIs('scanner.*') ? 'active' : '' }}">
                <i class="fas fa-radar"></i> Market Scanner
            </a>
        </div>

        <!-- Right side -->
        <div class="nav-right">
            @if(!$navLiveTrading)
                <span class="mode-pill" title="Live trading is disabled"><span class="dot"></span> Paper</span>
            @else
                <span class="badge badge-warning">Live</span>
            @endif
 
            <!-- User menu -->
            <div class="position-relative" @click.outside="userOpen = false">
                <a href="#" class="nav-user" @click.prevent="userOpen = !userOpen" aria-expanded="false" :aria-expanded="userOpen.toString()">
                    <span class="nav-user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                    <span class="nav-user-name">{{ Auth::user()->name }}</span>
                    <i class="fas fa-chevron-down"></i>
                </a>

                <div class="nav-menu" x-show="userOpen" x-transition.style.opacity.duration.150ms x-cloak @click.outside="userOpen = false">
                    <a href="{{ route('profile.edit') }}"><i class="far fa-user"></i> Profile</a>
                    <div class="nav-menu-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><i class="fas fa-sign-out-alt"></i> Log Out</button>
                    </form>
                </div>
            </div>

            <!-- Mobile burger -->
            <button
                class="nav-burger"
                @click="open = !open, userOpen = false"
                aria-label="Toggle navigation menu"
                aria-controls="navDrawer"
                :aria-expanded="open.toString()"
            >
                <i class="fas" :class="open ? 'fa-times' : 'fa-bars'"></i>
            </button>
        </div>
    </div>

    <!-- Overlay -->
    <div
        class="nav-overlay"
        :class="{ 'show': open }"
        @click="open = false"
    ></div>

    <!-- Slide-out drawer -->
    <aside
        id="navDrawer"
        class="nav-drawer"
        :class="{ 'open': open }"
        role="dialog"
        aria-modal="true"
        aria-label="Navigation menu"
    >
        <div class="drawer-header">
            <div>
                <div class="drawer-title">{{ site_setting('site_name', 'CryptoBot') }}</div>
                <div class="drawer-sub">Menu</div>
            </div>
            <button class="drawer-close" @click="open = false" aria-label="Close navigation menu"><i class="fas fa-times"></i></button>
        </div>

        <div class="drawer-links">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" @click="open = false">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="{{ route('exchanges.index') }}" class="{{ request()->routeIs('exchanges.*') ? 'active' : '' }}" @click="open = false">
                <i class="fas fa-link"></i> Exchanges
            </a>
            <a href="{{ route('bots.index') }}" class="{{ request()->routeIs('bots.*') ? 'active' : '' }}" @click="open = false">
                <i class="fas fa-robot"></i> Bots
            </a>
            <a href="{{ route('scanner.index') }}" class="{{ request()->routeIs('scanner.*') ? 'active' : '' }}" @click="open = false">
                <i class="fas fa-radar"></i> Market Scanner
            </a>
        </div>

        <div class="drawer-footer">
            @if(!$navLiveTrading)
                <span class="mode-pill drawer-mode" title="Live trading is disabled"><span class="dot"></span> Paper mode</span>
            @else
                <span class="badge badge-warning drawer-mode">Live</span>
            @endif
            <a href="{{ route('profile.edit') }}" class="drawer-link" @click="open = false"><i class="far fa-user"></i> Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="drawer-link"><i class="fas fa-sign-out-alt"></i> Log Out</button>
            </form>
        </div>
    </aside>
</nav>