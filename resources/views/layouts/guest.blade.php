<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Tailwind (Breeze pages) then Bootstrap 4.6 + Font Awesome -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

        <!-- Custom dark theme -->
        <link rel="stylesheet" href="{{ asset('css/crypto.css') }}?v=3">

        <!-- Alpine -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

        <style>
            .guest-wrap {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px 16px;
                position: relative;
                z-index: 1;
            }
            .guest-card {
                width: 100%;
                max-width: 430px;
                background: linear-gradient(180deg, var(--surface) 0%, var(--bg-elev) 100%);
                border: 1px solid var(--border-strong);
                border-radius: 18px;
                padding: 38px 36px 30px;
                box-shadow: var(--shadow);
            }
            .guest-brand {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
                margin-bottom: 26px;
                color: var(--text);
                text-decoration: none;
            }
            .guest-brand .brand-mark {
                width: 52px;
                height: 52px;
                border-radius: 15px;
                background: var(--grad);
                color: #04160e;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                box-shadow: 0 8px 30px rgba(16, 185, 129, 0.45);
            }
            .guest-brand .brand-name {
                font-weight: 800;
                font-size: 1.25rem;
                letter-spacing: -0.02em;
            }
            .guest-brand .brand-sub {
                font-size: 0.68rem;
                color: var(--green);
                text-transform: uppercase;
                letter-spacing: 0.2em;
                font-weight: 700;
            }
        </style>
    </head>
    <body class="app-body">
        <div class="guest-wrap">
            <div class="guest-card">
                <a href="/" class="guest-brand">
                    <span class="brand-mark"><i class="fas fa-robot"></i></span>
                    <span class="brand-name">CryptoBot</span>
                    <span class="brand-sub">Automated AI Trading</span>
                </a>

                {{ $slot }}
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>