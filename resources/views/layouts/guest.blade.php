<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Sign In — Travels & Car Wash</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}" />
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-3">
    <main class="w-100 d-flex justify-content-center">
        @yield('content')
    </main>
    <div id="toast-container" class="position-fixed top-0 end-0 p-3 z-3"></div>
    <script src="{{ asset('js/vendor/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/common.js') }}"></script>
</body>
</html>
