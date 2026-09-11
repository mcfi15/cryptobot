<nav class="crypto-navbar" x-data="{ open: false, userOpen: false, isActive: (r) => r === '{{ request()->route()?->getName() ?? '' }}' }">
    <div class="crypto-navbar-inner">
        <!-- Brand -->
        <a href="{{ route('dashboard') }}" class="nav-brand">
            <span class="brand-mark"><i class="fas fa-robot"></i></span>
            <span class="brand-text">
                CryptoBot
                <span class="brand-sub">Automated Trading</span>
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
                <a href="#" class="nav-user" @click.prevent="userOpen = !userOpen">
                    <span class="nav-user-avatar">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                    <span class="nav-user-name">{{ Auth::user()->name }}</span>
                    <i class="fas fa-chevron-down"></i>
                </a>

                <div class="nav-menu" x-show="userOpen" x-transition.style.opacity.duration.150ms x-cloak>
                    <a href="{{ route('profile.edit') }}"><i class="far fa-user"></i> Profile</a>
                    <div class="nav-menu-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><i class="fas fa-sign-out-alt"></i> Log Out</button>
                    </form>
                </div>
            </div>

            <!-- Mobile burger -->
            <button class="nav-burger" @click="open = !open">
                <i class="fas" :class="open ? 'fa-times' : 'fa-bars'"></i>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="mobile-menu" :class="{ 'open': open }">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="{{ route('exchanges.index') }}" class="{{ request()->routeIs('exchanges.*') ? 'active' : '' }}"><i class="fas fa-link"></i> Exchanges</a>
        <a href="{{ route('bots.index') }}" class="{{ request()->routeIs('bots.*') ? 'active' : '' }}"><i class="fas fa-robot"></i> Bots</a>
    </div>
</nav>