<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'Dashboard') — Travels & Car Wash</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" />
    <style>
        :root {
            --app-ink: #122b35;
            --app-canvas: #f3f7f6;
            --app-line: #dbe7e5;
            --app-muted: #60777d;
            --app-accent: #087f79;
            --app-signal: #e8a33a;
            --sidebar-bg: #102b34;
            --sidebar-muted: #a9c0c2;
        }
        html, body { height: 100%; }
        body { overflow: hidden; background: var(--app-canvas); color: var(--app-ink); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .app-shell { height: 100vh; height: 100dvh; min-height: 0; }
        #sidebar { position: relative; width: 252px; height: 100vh; height: 100dvh; min-height: 0; padding-top: env(safe-area-inset-top); box-sizing: border-box; background: linear-gradient(165deg, #153b43 0%, var(--sidebar-bg) 34%, #0d222d 100%); flex-shrink: 0; }
        #sidebar::before { content: ""; position: absolute; top: 0; left: 0; width: 3px; height: 100%; background: linear-gradient(180deg, var(--app-signal) 0 76px, rgba(67, 188, 174, .55) 76px 100%); }
        #sidebar .nav-link { color: var(--sidebar-muted); font-size: .85rem; border-radius: .4rem; padding: .58rem .75rem; transition: background-color .15s ease, color .15s ease, transform .15s ease; }
        #sidebar .nav-link:hover { background: rgba(157, 209, 204, .12); color: #fff; transform: translateX(2px); }
        #sidebar .nav-link.active { background: linear-gradient(100deg, rgba(8, 127, 121, .9), rgba(8, 127, 121, .55)); color: #fff; box-shadow: inset 3px 0 0 var(--app-signal); }
        #sidebar .nav-link i { width: 1.1rem; font-size: 1rem; text-align: center; }
        #sidebar :focus-visible, .topbar :focus-visible { outline: 3px solid #f2bd64; outline-offset: 2px; }
        .sidebar-brand { position: relative; height: 76px; border-color: rgba(169, 192, 194, .2) !important; }
        .sidebar-brand::after { content: ""; position: absolute; left: 16px; right: 16px; bottom: -1px; height: 1px; background: linear-gradient(90deg, var(--app-signal), rgba(67, 188, 174, .5), transparent); }
        .sidebar-brand-mark { position: relative; overflow: hidden; width: 40px; height: 40px; background: linear-gradient(145deg, #20a99c, #087f79 70%, #075b60); border: 1px solid rgba(175, 238, 224, .45); box-shadow: 0 5px 14px rgba(0, 0, 0, .2), inset 0 1px 0 rgba(255, 255, 255, .2); }
        .sidebar-brand-mark::after { content: ""; position: absolute; width: 16px; height: 16px; right: -6px; top: -6px; border: 2px solid var(--app-signal); border-radius: 50%; }
        .sidebar-brand-mark i { position: relative; z-index: 1; }
        .sidebar-brand-kicker { color: #7ed0c5; font-size: .58rem; font-weight: 700; letter-spacing: .16em; line-height: 1.2; }
        .sidebar-user { color: #b1c5c4; }
        .sidebar-close { display: none; }
        .main-content { flex: 1; height: 100%; min-width: 0; min-height: 0; display: flex; flex-direction: column; }
        .topbar { position: relative; min-height: calc(64px + env(safe-area-inset-top)); padding-top: env(safe-area-inset-top); box-sizing: border-box; flex-shrink: 0; background: #fff; border-bottom: 1px solid var(--app-line); box-shadow: 0 2px 8px rgba(16, 43, 52, .035); }
        .topbar::before { content: ""; position: absolute; top: 0; left: 0; width: 116px; height: 2px; background: linear-gradient(90deg, var(--app-signal) 0 22px, var(--app-accent) 22px 100%); }
        .topbar-title { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .topbar-title { border-left: 2px solid var(--app-signal); padding-left: .7rem; }
        .topbar-actions { flex-shrink: 0; }
        .topbar .btn-primary { background: var(--app-accent); border-color: var(--app-accent); box-shadow: 0 2px 0 #075d5a; }
        .topbar .btn-primary:hover { background: #066b67; border-color: #066b67; }
        .app-footer { position: relative; padding-bottom: calc(1rem + env(safe-area-inset-bottom)) !important; flex-shrink: 0; color: var(--app-muted); border-top: 1px solid var(--app-line); border-top-color: #d4e2df !important; font-size: .75rem; }
        .app-footer::before { content: ""; position: absolute; top: -1px; left: 0; width: 48px; height: 2px; background: var(--app-signal); }
        .footer-brand-mark { display: inline-block; width: .48rem; height: .48rem; margin-right: .5rem; vertical-align: .05rem; border-radius: 50% 50% 2px 50%; background: var(--app-accent); box-shadow: 0 0 0 3px rgba(8, 127, 121, .1); }
        .badge-status { font-size: .72rem; font-weight: 600; }
        .table > :not(caption) > * > * { padding: .6rem 1rem; }
        .modal-content { border-color: var(--app-line); box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, .16); }
        .form-label { font-size: .8rem; font-weight: 500; color: #374151; }
        .field-error { font-size: .75rem; color: #dc2626; margin-top: .2rem; }
        .main-content main { background: var(--app-canvas); }
        .main-content main table th { white-space: nowrap; font-size: .75rem; text-transform: uppercase; letter-spacing: .035em; }
        .main-content main table td { vertical-align: middle; }
        .resource-page { min-width: 0; color: var(--app-ink); }
        .resource-page-header { min-width: 0; }
        .resource-kicker {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .45rem;
            color: var(--app-accent);
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .13em;
            text-transform: uppercase;
        }
        .resource-kicker::before { width: 1.1rem; height: 2px; background: var(--app-signal); content: ""; }
        .resource-heading {
            color: var(--app-ink);
            font-size: clamp(1.45rem, 2.2vw, 1.9rem);
            letter-spacing: -.04em;
        }
        .resource-intro { max-width: 38rem; color: var(--app-muted); }
        .resource-primary-action {
            min-height: 2.65rem;
            padding-inline: 1rem;
            background: var(--app-accent);
            border-color: var(--app-accent);
            box-shadow: 0 2px 0 #075d5a;
            font-weight: 650;
            white-space: nowrap;
        }
        .resource-primary-action:hover { background: #066b67; border-color: #066b67; }
        .resource-page .btn:focus-visible,
        .resource-page .form-control:focus-visible,
        .resource-page .form-select:focus-visible {
            outline: 3px solid rgba(8, 127, 121, .28);
            outline-offset: 2px;
        }
        .resource-filter-card,
        .resource-table-card {
            border: 1px solid var(--app-line) !important;
            border-radius: .7rem;
            background: #fff;
            box-shadow: 0 3px 12px rgba(16, 43, 52, .035) !important;
        }
        .resource-filter-card .card-body { padding: 1rem 1.15rem; }
        .resource-filter-heading { color: var(--app-ink); }
        .resource-filter-heading > .bi { color: var(--app-accent); }
        .resource-filter-card .form-label,
        .resource-filter-label {
            margin-bottom: .38rem;
            color: var(--app-muted);
            font-size: .75rem;
            font-weight: 650;
            letter-spacing: .025em;
        }
        .resource-filter-card .form-select,
        .resource-filter-card .form-control {
            min-height: 2.55rem;
            border-color: var(--app-line);
            border-radius: .45rem;
            color: var(--app-ink);
            font-size: .875rem;
        }
        .resource-filter-card .form-select:focus,
        .resource-filter-card .form-control:focus {
            border-color: var(--app-accent);
            box-shadow: 0 0 0 .2rem rgba(8, 127, 121, .12);
        }
        .resource-filter-card .form-select:focus-visible,
        .resource-filter-card .form-control:focus-visible,
        .resource-filter-card .btn:focus-visible {
            outline: 3px solid rgba(8, 127, 121, .28);
            outline-offset: 2px;
        }
        .resource-filter-card .btn { min-height: 2.55rem; border-radius: .45rem; }
        .resource-filter-card .btn-primary { background: var(--app-accent); border-color: var(--app-accent); }
        .resource-filter-card .btn-primary:hover { background: #066b67; border-color: #066b67; }
        .main-content > main { flex: 1 1 auto; min-height: 0; overscroll-behavior-y: contain; scrollbar-gutter: stable; }
        .resource-table-scroll { min-height: 11rem; }
        .resource-table-card .table-responsive { scrollbar-color: #afc6c2 transparent; }
        .resource-table-card .table-responsive:focus-visible {
            outline: 3px solid rgba(8, 127, 121, .28);
            outline-offset: -3px;
        }
        .resource-table-card .card-header { border-bottom-color: var(--app-line); }
        .resource-table-card .table > :not(caption) > * > * { border-color: #e6eeec; }
        .resource-table-card .table thead th {
            padding-top: .78rem;
            padding-bottom: .78rem;
            border-bottom-color: var(--app-line);
            background: #f3f8f7;
            color: var(--app-muted);
        }
        .resource-table-card .table tbody td { padding-top: .75rem; padding-bottom: .75rem; }
        .resource-table-card .dataTables_wrapper { padding: .95rem 1rem 1rem; }
        .resource-table-card .dataTables_wrapper .row { row-gap: .75rem; }
        .resource-table-card .dataTables_wrapper .dataTables_info { padding-top: .55rem; color: var(--app-muted); }
        .resource-table-card .dataTables_wrapper .dataTables_filter input,
        .resource-table-card .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--app-line);
            border-radius: .4rem;
        }
        .resource-row-actions { white-space: nowrap; }
        .resource-row-actions .btn { min-height: 2rem; }
        .resource-table-card .btn:focus-visible { outline: 3px solid rgba(8, 127, 121, .28); outline-offset: 2px; }
        .resource-scroll-hint { color: var(--app-muted); font-size: .75rem; }
        .resource-scroll-hint .bi { color: var(--app-accent); }
        .resource-tabs {
            display: flex;
            width: fit-content;
            max-width: 100%;
            gap: .25rem;
            padding: .3rem;
            border: 1px solid var(--app-line);
            border-radius: .65rem;
            background: #e9f1ef;
        }
        .resource-tabs .nav-link {
            min-width: 9rem;
            padding: .62rem 1rem;
            border-radius: .45rem;
            color: var(--app-muted);
            font-size: .86rem;
            font-weight: 650;
            transition: color .15s ease, background-color .15s ease, box-shadow .15s ease;
        }
        .resource-tabs .nav-link:hover { color: #075c5c; }
        .resource-tabs .nav-link.active {
            color: #fff;
            background: #075c5c;
            box-shadow: 0 2px 5px rgba(16, 43, 52, .16);
        }
        .dataTables_wrapper .dataTables_filter input { font-size: .8rem; }
        .resource-table-card .dataTables_wrapper .dataTables_filter input {
            min-height: 2.35rem;
            margin-left: .45rem;
            padding: .35rem .65rem;
            border-color: var(--app-line);
            color: var(--app-ink);
        }
        .resource-table-card .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--app-accent);
            box-shadow: 0 0 0 .2rem rgba(8, 127, 121, .12);
            outline: 0;
        }
        .resource-table-card .dataTables_wrapper .dataTables_filter input:focus-visible {
            outline: 3px solid rgba(8, 127, 121, .28);
            outline-offset: 2px;
        }
        .dataTables_wrapper .dataTables_length select { font-size: .8rem; }
        .dataTables_wrapper .dataTables_info { font-size: .8rem; color: #6c757d; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { font-size: .8rem; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--app-accent); color: #fff !important; border-color: var(--app-accent); border-radius: .375rem; }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) { background: #f1f7f6; color: var(--app-accent) !important; border-color: var(--app-line); border-radius: .375rem; }
        #sidebar-backdrop { display: none; }
        .topbar #quick-dropdown { position: fixed !important; top: var(--quick-dropdown-top, 4rem) !important; right: max(12px, env(safe-area-inset-right), var(--quick-dropdown-right, 0px)) !important; left: auto !important; width: min(210px, var(--quick-dropdown-max-width, calc(100vw - 24px))); min-width: min(210px, var(--quick-dropdown-max-width, calc(100vw - 24px))) !important; max-width: min(var(--quick-dropdown-max-width, 100vw), calc(100vw - 24px - env(safe-area-inset-left) - env(safe-area-inset-right))); max-height: calc(100vh - 5rem); overflow-y: auto; overflow-wrap: anywhere; box-sizing: border-box; z-index: 1060; }
        @media (max-width: 991.98px) {
            #sidebar { position: fixed; top: 0; left: 0; z-index: 1045; transform: translateX(-100%); transition: transform .2s; height: 100vh; height: 100dvh; padding-bottom: env(safe-area-inset-bottom); overflow-y: auto; }
            #sidebar.show { transform: translateX(0); }
            #sidebar-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1044; }
            #sidebar-backdrop.show { display: block; }
            .sidebar-close { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 36px; height: 36px; padding: 0; color: #d4e7e3; border: 1px solid rgba(169, 192, 194, .32); background: rgba(255, 255, 255, .06); }
            .sidebar-close:hover { color: #fff; border-color: var(--app-signal); background: rgba(255, 255, 255, .12); }
        }
        @media (max-width: 575.98px) {
            .topbar { gap: .5rem !important; padding-inline: .75rem !important; }
            .topbar-title { font-size: .88rem !important; }
            .quick-action-label { display: none; }
            .topbar-actions { gap: .5rem !important; }
            .resource-page-header { align-items: stretch !important; }
            .resource-primary-action { width: 100%; justify-content: center; }
            .resource-filter-card .card-body { padding: .9rem; }
            .resource-tabs { width: 100%; }
            .resource-tabs .nav-link { min-width: 0; flex: 1; }
            .resource-table-card .dataTables_wrapper { padding: .75rem; }
            .resource-table-card .dataTables_wrapper .dataTables_length,
            .resource-table-card .dataTables_wrapper .dataTables_filter { text-align: left; }
        }
    </style>
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

<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:1100"></div>
<x-ajax-modal />
<x-record-view-modal />

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script>
window.appConfig = {
    baseUrl: @json(url('')),
    loginUrl: @json(route('login')),
};
</script>
<script src="{{ asset('js/common.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
