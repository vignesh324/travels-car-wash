<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Dashboard') — Travels & Car Wash</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/bootstrap-icons.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/vendor/dataTables.bootstrap5.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/app-shell.css') }}" />
    @stack('head')
</head>
<body>

<div id="sidebar-backdrop" onclick="closeSidebar()"></div>

<div class="app-shell d-flex">
    @include('layouts.partials.sidebar')
    <div class="main-content">
        @include('layouts.partials.header')
        <main class="overflow-auto p-3 p-lg-4">
            @yield('content')
        </main>
        @include('layouts.partials.footer')
    </div>
</div>

@include('layouts.partials.mobile-navigation')

<div id="toast-container" class="position-fixed top-0 end-0 mt-5 p-3 z-3 pe-none"></div>
<x-ajax-modal />
<x-record-view-modal />

<script src="{{ asset('js/vendor/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/vendor/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('js/vendor/dataTables.min.js') }}"></script>
<script src="{{ asset('js/vendor/dataTables.bootstrap5.min.js') }}"></script>
<script>
window.appConfig = {
    baseUrl: @json(url('')),
    loginUrl: @json(route('login')),
};
</script>
<script src="{{ asset('js/common.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
