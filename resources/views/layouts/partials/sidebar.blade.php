@php
    $businessProfile = auth()->user()->businessProfile;
    $businessName = $businessProfile?->business_name ?: auth()->user()->name;
@endphp
<aside id="sidebar" class="d-flex flex-column bg-dark text-white" aria-label="Main navigation">
    <div class="d-flex align-items-center gap-3 px-3 py-3 border-bottom border-secondary">
        @if ($businessProfile?->logo_url)
            <img src="{{ $businessProfile->logo_url }}" alt="" class="rounded-2 flex-shrink-0 object-fit-cover"
                width="44" height="44">
        @else
            <div
                class="sidebar-brand-mark rounded-2 bg-primary bg-opacity-25 text-info d-flex align-items-center justify-content-center flex-shrink-0">
                <i class="bi bi-truck fs-4" aria-hidden="true"></i>
            </div>
        @endif
        <div class="overflow-hidden">
            <p class="text-white fw-semibold mb-0 text-truncate" id="sidebar-biz-name" title="{{ $businessName }}">
                {{ $businessName }}</p>
            <p class="text-white-50 small mb-0 text-truncate">{{ auth()->user()->name }}</p>
        </div>
        <button type="button" class="sidebar-close btn btn-sm ms-auto text-white" onclick="closeSidebar()"
            aria-label="Close navigation" aria-controls="sidebar">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="sidebar-navigation flex-grow-1 overflow-auto py-3 px-2" aria-label="Business sections">
        @php
            $navItems = [
                ['route' => 'dashboard.index', 'label' => 'Dashboard', 'icon' => 'bi-grid'],
                ['route' => 'vehicles.index', 'label' => 'Vehicles', 'icon' => 'bi-car-front'],
                ['route' => 'expenses.vehicle.index', 'label' => 'Vehicle Expenses', 'icon' => 'bi-fuel-pump'],
                ['route' => 'expenses.business.index', 'label' => 'Business Expenses', 'icon' => 'bi-wallet2'],
                ['route' => 'customers.index', 'label' => 'Customers', 'icon' => 'bi-people'],
                [
                    'route' => 'customers.quick-trips',
                    'label' => 'Quick Trip Configurations',
                    'icon' => 'bi-signpost-2',
                    'exact' => true,
                ],
                ['route' => 'trips.index', 'label' => 'Trips', 'icon' => 'bi-truck'],
                [
                    'route' => 'quick-trip-executions.index',
                    'label' => 'Quick Trip Execution',
                    'icon' => 'bi-lightning-charge',
                    'exact' => true,
                ],
                ['route' => 'car-wash.index', 'label' => 'Car Wash', 'icon' => 'bi-droplet'],
                ['route' => 'invoices.index', 'label' => 'Invoices', 'icon' => 'bi-file-earmark-text'],
                ['route' => 'payments.index', 'label' => 'Payments', 'icon' => 'bi-credit-card'],
                ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'bi-bar-chart'],
                ['route' => 'activity-logs.index', 'label' => 'Activity Logs', 'icon' => 'bi-clock-history'],
                ['route' => 'settings.index', 'label' => 'Settings', 'icon' => 'bi-gear'],
            ];
        @endphp
        <p class="text-uppercase text-white-50 fw-semibold small px-3 mb-2">Workspace</p>
        @foreach ($navItems as $item)
            @php
                $active = !empty($item['query'])
                    ? request()->routeIs($item['route']) && request()->query('action') === $item['query']['action']
                    : (!empty($item['exact'])
                        ? request()->routeIs($item['route'])
                        : (str_starts_with($item['route'], 'expenses.')
                            ? request()->routeIs($item['route'])
                            : request()->routeIs(explode('.', $item['route'])[0] . '.*') &&
                                !request()->routeIs('customers.quick-trips*') &&
                                !request()->routeIs('quick-trip-executions.*') &&
                                request()->query('action') !== 'quick-execution'));
            @endphp
            <a href="{{ route($item['route'], $item['query'] ?? []) }}" onclick="closeSidebar()"
                @if ($active) aria-current="page" @endif
                class="nav-link link-light d-flex align-items-center gap-3 px-3 py-2 mb-1 {{ !empty($item['query']) ? 'ps-5 small' : '' }} {{ $active ? 'active bg-primary' : '' }}">
                <i class="bi {{ $item['icon'] }} fs-5" aria-hidden="true"></i>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="px-2 py-3 border-top border-secondary">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="nav-link link-light d-flex align-items-center gap-3 px-3 py-2 w-100 border-0 bg-transparent">
                <i class="bi bi-box-arrow-right fs-5" aria-hidden="true"></i> Logout
            </button>
        </form>
    </div>
</aside>
