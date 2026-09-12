<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', site_setting('site_name', config('app.name', 'CryptoBot'))) &middot; {{ site_setting('site_name', config('app.name', 'CryptoBot')) }}</title>

    <link rel="icon" href="{{ site_setting('site_favicon') ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230f1a2e'/%3E%3Ccircle cx='16' cy='16' r='9' fill='%2310b981'/%3E%3C/svg%3E" }}">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/crypto.css') }}?v=4">
</head>
<body class="admin-body">
    @yield('content')
</body>
</html>