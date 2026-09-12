@extends('admin.layouts.auth')

@section('title', 'Sign In')

@section('content')
<div class="admin-auth">
    <div class="auth-card card">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                @if($siteLogo = site_setting('site_logo'))
                    <div class="auth-logo mb-3"><img src="{{ asset($siteLogo) }}" alt="Logo"></div>
                @else
                    <div class="auth-logo mb-3"><i class="fas fa-robot"></i></div>
                @endif
                <h1 class="h4 mb-0" style="font-weight:800;letter-spacing:-0.02em;">{{ site_setting('site_name', 'CryptoBot') }}</h1>
                <div class="admin-sub">Admin Sign In</div>
            </div>

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    @error('email')
                        <span class="invalid-feedback d-block text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                    @error('password')
                        <span class="invalid-feedback d-block text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group d-flex align-items-center justify-content-between">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="remember" name="remember">
                        <label class="custom-control-label" for="remember" style="color:var(--muted);">Remember me</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-block btn-lg">
                    <i class="fas fa-lock mr-1"></i> Sign In
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="{{ route('login') }}" class="small" style="color:var(--muted);">Back to user login</a>
            </div>
        </div>
    </div>
</div>
@endsection