<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ config('app.name') }}</title>
    <meta name="app-base-path" content="{{ rtrim(config('app.url'), '/') }}" />
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#1e40af" />
    <link rel="apple-touch-icon" href="/icons/icon-192.png" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="TCW" />
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/ts/main.tsx'])
</head>
<body class="bg-gray-50">
    <div id="root"></div>
</body>
</html>
