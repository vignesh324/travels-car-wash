<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}">
</head>
<body class="bg-body-tertiary">
    <main class="container d-flex align-items-center justify-content-center min-vh-100 py-5 text-center">
        <div>
            <h1 class="display-5 text-secondary mb-3">@yield('code')</h1>
            <p class="h5 text-body-secondary mb-0">@yield('message')</p>
        </div>
    </main>
</body>
</html>