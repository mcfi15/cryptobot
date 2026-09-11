<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'Laravel'))</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Bootstrap 4.6 + Font Awesome -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

        <!-- App assets (Tailwind + Alpine) & custom theme -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('css/crypto.css') }}?v=2">
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