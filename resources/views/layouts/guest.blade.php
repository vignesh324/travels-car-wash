<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Sign In — Travels & Car Wash</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <style>
        body { background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%); min-height: 100vh; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    @yield('content')
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1100"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/common.js') }}"></script>
</body>
</html>
