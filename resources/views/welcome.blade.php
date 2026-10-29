<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Travels &amp; Car Wash</title>
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}">
</head>
<body class="bg-body-tertiary">
    <header class="navbar bg-white border-bottom">
        <div class="container py-2">
            <a class="navbar-brand fw-semibold" href="{{ url('/') }}"><i class="bi bi-truck me-2" aria-hidden="true"></i>Travels &amp; Car Wash</a>
            @if (Route::has('login'))
                <a class="btn btn-primary" href="{{ route('login') }}">Sign in</a>
            @endif
        </div>
    </header>
    <main class="container py-5">
        <div class="row justify-content-center py-lg-5">
            <div class="col-12 col-lg-8 text-center">
                <h1 class="display-5 fw-semibold mb-3">Travel and car-wash operations</h1>
                <p class="lead text-secondary mb-4">Manage trips, vehicles, customers, invoices, payments and wash transactions in one place.</p>
                @if (Route::has('login'))
                    <a class="btn btn-primary btn-lg" href="{{ route('login') }}">Open your workspace</a>
                @endif
            </div>
        </div>
    </main>
</body>
</html>