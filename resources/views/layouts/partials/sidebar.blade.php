<aside id="sidebar" class="d-flex flex-column" aria-label="Main navigation">
    <div class="sidebar-brand d-flex align-items-center gap-2 px-3 border-bottom">
        <div class="sidebar-brand-mark rounded-2 d-flex align-items-center justify-content-center flex-shrink-0">
            <i class="bi bi-truck text-white" style="font-size:.95rem" aria-hidden="true"></i>
        </div>
        <div class="overflow-hidden">
            <p class="text-white fw-semibold mb-0 text-truncate" style="font-size:.85rem" id="sidebar-biz-name">Travels & Car Wash</p>
            <p class="sidebar-brand-kicker mb-1">OPERATIONS</p>
            <p class="sidebar-user mb-0 text-truncate" style="font-size:.75rem">{{ auth()->user()->name }}</p>
        </div>
        <button type="button" class="sidebar-close btn btn-sm ms-auto" onclick="closeSidebar()" aria-label="Close navigation" aria-controls="sidebar">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="flex-grow-1 overflow-auto py-3 px-2" aria-label="Business sections">
        @php
        $navItems = [
            ['route'=>'dashboard.index',     'label'=>'Dashboard',     'icon'=>'bi-grid'],
            ['route'=>'trips.index',         'label'=>'Trips',         'icon'=>'bi-truck'],
            ['route'=>'vehicles.index',      'label'=>'Vehicles',      'icon'=>'bi-car-front'],
            ['route'=>'customers.index',     'label'=>'Customers',     'icon'=>'bi-people'],
            ['route'=>'expenses.index',      'label'=>'Expenses',      'icon'=>'bi-wallet2'],
            ['route'=>'invoices.index',      'label'=>'Invoices',      'icon'=>'bi-file-earmark-text'],
            ['route'=>'payments.index',      'label'=>'Payments',      'icon'=>'bi-credit-card'],
            ['route'=>'car-wash.index',      'label'=>'Car Wash',      'icon'=>'bi-droplet'],
            ['route'=>'reports.index',       'label'=>'Reports',       'icon'=>'bi-bar-chart'],
            ['route'=>'activity-logs.index', 'label'=>'Activity Logs', 'icon'=>'bi-clock-history'],
            ['route'=>'settings.index',      'label'=>'Settings',      'icon'=>'bi-gear'],
        ];
        @endphp
        @foreach($navItems as $item)
            @php $active = request()->routeIs(explode('.', $item['route'])[0].'.*'); @endphp
            <a href="{{ route($item['route']) }}" onclick="closeSidebar()"
               @if($active) aria-current="page" @endif
               class="nav-link d-flex align-items-center gap-2 mb-1 {{ $active ? 'active' : '' }}">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="px-2 py-2 border-top" style="border-color:#334155!important">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link d-flex align-items-center gap-2 w-100 border-0 bg-transparent">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
            </button>
        </form>
    </div>
</aside>
