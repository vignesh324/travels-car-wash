@php
    $mobileNavItems = [
        ['route' => 'quick-trip-executions.index', 'label' => 'Quick Trip Execution', 'icon' => 'bi-lightning-charge'],
        ['route' => 'vehicles.index', 'label' => 'Vehicles', 'icon' => 'bi-car-front'],
        ['route' => 'customers.quick-trips', 'label' => 'Quick Trip Configurations', 'icon' => 'bi-signpost-2'],
        ['route' => 'customers.index', 'label' => 'Customers', 'icon' => 'bi-people'],
        ['route' => 'expenses.vehicle.index', 'label' => 'Vehicle Expenses', 'icon' => 'bi-fuel-pump'],
        ['route' => 'expenses.business.index', 'label' => 'Business Expenses', 'icon' => 'bi-wallet2'],
        ['route' => 'invoices.index', 'label' => 'Invoices', 'icon' => 'bi-file-earmark-text'],
        ['route' => 'payments.index', 'label' => 'Payments', 'icon' => 'bi-credit-card'],
        ['route' => 'car-wash.index', 'label' => 'Car Wash', 'icon' => 'bi-droplet'],
        ['route' => 'reports.index', 'label' => 'Reports', 'icon' => 'bi-bar-chart'],
        ['route' => 'activity-logs.index', 'label' => 'Activity Logs', 'icon' => 'bi-clock-history'],
        ['route' => 'settings.index', 'label' => 'Settings', 'icon' => 'bi-gear'],
    ];
    $moreIsActive = collect($mobileNavItems)->contains(fn ($item) => !empty($item['query'])
        ? request()->routeIs($item['route']) && request()->query('action') === $item['query']['action']
        : ($item['route'] === 'customers.quick-trips'
        ? request()->routeIs($item['route'])
        : (str_starts_with($item['route'], 'expenses.')
            ? request()->routeIs($item['route'])
            : request()->routeIs(explode('.', $item['route'])[0].'.*')
                && !request()->routeIs('customers.quick-trips*'))));
@endphp

<nav id="mobile-bottom-nav" class="mobile-bottom-nav" aria-label="Primary navigation">
    <a href="{{ route('dashboard.index') }}" class="mobile-nav-item {{ request()->routeIs('dashboard.*') ? 'active' : '' }}"
       @if(request()->routeIs('dashboard.*')) aria-current="page" @endif>
        <i class="bi bi-grid" aria-hidden="true"></i><span>Dashboard</span>
    </a>
    <a href="{{ route('trips.index') }}" class="mobile-nav-item {{ request()->routeIs('trips.*') && request()->query('action') !== 'quick-execution' ? 'active' : '' }}"
       @if(request()->routeIs('trips.*') && request()->query('action') !== 'quick-execution') aria-current="page" @endif>
        <i class="bi bi-truck" aria-hidden="true"></i><span>Trips</span>
    </a>
    <button id="mobile-quick-action-toggle" type="button" class="mobile-nav-item mobile-nav-add"
            onclick="toggleQuickMenu('mobile-quick-action-toggle')" aria-label="Add" aria-controls="quick-dropdown" aria-expanded="false">
        <span class="mobile-nav-add-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span><span>Add</span>
    </button>
    <a href="{{ route('expenses.business.index') }}" class="mobile-nav-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}"
       @if(request()->routeIs('expenses.*')) aria-current="page" @endif>
        <i class="bi bi-wallet2" aria-hidden="true"></i><span>Expenses</span>
    </a>
    <button id="mobile-more-toggle" type="button" class="mobile-nav-item {{ $moreIsActive ? 'active' : '' }}"
            aria-controls="mobile-more-menu" aria-expanded="false">
        <i class="bi bi-three-dots" aria-hidden="true"></i><span>More</span>
    </button>

    <div id="mobile-more-menu" class="mobile-more-menu" hidden>
        <p class="mobile-more-heading">Workspace</p>
        <div class="mobile-more-links">
            @foreach($mobileNavItems as $item)
                @php
                    $active = !empty($item['query'])
                        ? request()->routeIs($item['route']) && request()->query('action') === $item['query']['action']
                        : ($item['route'] === 'customers.quick-trips'
                        ? request()->routeIs($item['route'])
                        : (str_starts_with($item['route'], 'expenses.')
                            ? request()->routeIs($item['route'])
                            : request()->routeIs(explode('.', $item['route'])[0].'.*')
                                && !request()->routeIs('customers.quick-trips*')));
                @endphp
                <a href="{{ route($item['route'], $item['query'] ?? []) }}" class="{{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mobile-more-logout">
            @csrf
            <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Log out</button>
        </form>
    </div>
</nav>
