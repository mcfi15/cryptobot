<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', site_setting('site_name', config('app.name', 'CryptoBot')))</title>

        <link rel="icon" href="{{ site_setting('site_favicon') ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230f1a2e'/%3E%3Ccircle cx='16' cy='16' r='9' fill='%2310b981'/%3E%3C/svg%3E" }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Tailwind (Breeze pages) then Bootstrap 4.6 + Font Awesome -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

        <!-- Custom dark theme -->
        <link rel="stylesheet" href="{{ asset('css/crypto.css') }}?v=4">

        <!-- Alpine -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    </head>
    <body class="app-body">
        <div class="app-shell">
            @include('layouts.navigation')
            @include('layouts.ticker')

            <main class="app-main">
                <div class="app-container">
                    @hasSection('header')
                        <div class="page-head">
                            <div>
                                <h4>@yield('header')</h4>
                            </div>
                        </div>
                    @else
                        @isset($header)
                            <div class="page-head">
                                <div>
                                    <h4>{{ $header }}</h4>
                                </div>
                            </div>
                        @endisset
                    @endif

                    @yield('content')
                    {{ $slot ?? '' }}
                </div>
            </main>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>