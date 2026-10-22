@extends('layouts.guest')

@section('content')
<div class="card w-100 col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
    <div class="card-body p-4 p-sm-5">
        <div class="text-center mb-4">
            <div class="rounded-3 bg-primary d-inline-flex align-items-center justify-content-center text-white fw-bold mb-3 px-3 py-2">TC</div>
            <h1 class="h5 fw-bold text-dark mb-1">Travels & Car Wash</h1>
            <p class="text-muted small mb-0">Sign in to your account</p>
        </div>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="form-control @error('email') is-invalid @enderror" />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="form-control @error('password') is-invalid @enderror" />
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 form-check">
                <input id="remember" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                       class="form-check-input" />
                <label for="remember" class="form-check-label small">Remember Me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold">Sign In</button>
        </form>
    </div>
</div>
@endsection
