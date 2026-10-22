@php
    $mobileBusinessName = auth()->user()->businessProfile?->business_name ?: auth()->user()->name;
@endphp
<header class="topbar bg-white border-bottom d-flex align-items-center gap-3 px-3 flex-shrink-0">
    <button id="sidebar-toggle" onclick="toggleSidebar()" class="btn btn-light btn-sm p-1" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="true" title="Toggle navigation">
        <i class="bi bi-list fs-5" aria-hidden="true"></i>
    </button>
    <h1 class="d-none d-md-block text-truncate fw-semibold mb-0 text-dark small">@yield('title', 'Dashboard')</h1>
    <span id="mobile-business-name" class="d-md-none flex-grow-1 text-truncate fw-semibold text-dark small" title="{{ $mobileBusinessName }}">{{ $mobileBusinessName }}</span>

    <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
        <div class="dropdown" id="quick-action-menu">
            <button id="quick-action-toggle" class="btn btn-primary btn-sm dropdown-toggle" onclick="toggleQuickMenu()" type="button" aria-label="Quick Action" aria-controls="quick-dropdown" aria-expanded="false">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span class="quick-action-label">Quick Action</span>
            </button>
            <ul id="quick-dropdown" class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header small">Fast Manual Entry</h6></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('trips.index') }}?action=new"><span class="rounded-circle bg-primary p-1"></span>Add Trip</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('expenses.vehicle.index') }}?action=new"><span class="rounded-circle bg-warning p-1"></span>Add Vehicle Expense</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('expenses.business.index') }}?action=new"><span class="rounded-circle bg-warning p-1"></span>Add Business Expense</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('invoices.index') }}?action=new"><span class="rounded-circle bg-secondary p-1"></span>Create Invoice</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('payments.index') }}?action=new"><span class="rounded-circle bg-success p-1"></span>Record Payment</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('car-wash.index') }}?action=new"><span class="rounded-circle bg-info p-1"></span>Create Car Wash Bill</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <button id="account-menu-toggle" class="btn btn-light rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0 small p-2"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for {{ auth()->user()->name }}" title="{{ auth()->user()->name }}" style="width: 44px; height: 44px;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="account-menu-toggle">
                <li><a class="dropdown-item" href="{{ route('profile.index') }}"><i class="bi bi-person me-2" aria-hidden="true"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
