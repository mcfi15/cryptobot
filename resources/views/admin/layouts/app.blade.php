<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin') &middot; {{ site_setting('site_name', config('app.name', 'CryptoBot')) }} Admin</title>

    <link rel="icon" href="{{ site_setting('site_favicon') ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230f1a2e'/%3E%3Ccircle cx='16' cy='16' r='9' fill='%2310b981'/%3E%3C/svg%3E" }}">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/crypto.css') }}?v=5">

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="admin-body">
    <div class="admin-shell" x-data="{
        open: false,
        init() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.open = false;
            });
        }
    }">
        <button
            class="admin-menu-btn"
            @click="open = !open"
            aria-label="Toggle admin navigation"
            aria-controls="adminDrawer"
            :aria-expanded="open.toString()"
        >
            <i class="fas" :class="open ? 'fa-times' : 'fa-bars'"></i>
        </button>

        <div
            class="admin-overlay"
            :class="{ 'show': open }"
            @click="open = false"
        ></div>

        <aside id="adminDrawer" class="admin-sidebar" :class="{ 'open': open }">
            <a href="{{ route('admin.dashboard') }}" class="side-brand" @click="open = false">
                @if($siteLogo = site_setting('site_logo'))
                    <img src="{{ asset($siteLogo) }}" alt="Logo">
                @else
                    <span class="brand-mark" style="width:34px;height:34px;border-radius:10px;background:var(--grad);display:flex;align-items:center;justify-content:center;color:#04160e;"><i class="fas fa-robot"></i></span>
                @endif
                <span>{{ site_setting('site_name', 'CryptoBot') }} Admin</span>
            </a>

            <nav class="side-nav">
                <a href="{{ route('admin.dashboard') }}" class="side-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" @click="open = false">
                    <i class="fas fa-gauge-high"></i> Dashboard
                </a>
                <a href="{{ route('admin.settings.edit') }}" class="side-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" @click="open = false">
                    <i class="fas fa-sliders"></i> Site Settings
                </a>
            </nav>

            <div class="side-footer">
                <div class="mb-2">Signed in as <strong style="color:var(--text)">{{ Auth::guard('admin')->user()->name }}</strong></div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </button>
                </form>
            </div>
        </aside>

        <main class="admin-main">
            @if (session('success'))
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger mb-3">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>