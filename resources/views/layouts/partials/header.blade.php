<header class="topbar d-flex align-items-center gap-3 px-3 flex-shrink-0">
    <button id="sidebar-toggle" onclick="openSidebar()" class="btn btn-light btn-sm d-lg-none p-1" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
        <i class="bi bi-list fs-5" aria-hidden="true"></i>
    </button>
    <h1 class="topbar-title fw-semibold mb-0 text-dark" style="font-size:.95rem">@yield('title', 'Dashboard')</h1>

    <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
        <div class="dropdown" id="quick-action-menu">
            <button id="quick-action-toggle" class="btn btn-primary btn-sm dropdown-toggle" onclick="toggleQuickMenu()" type="button" aria-label="Quick Action" aria-controls="quick-dropdown" aria-expanded="false">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span class="quick-action-label">Quick Action</span>
            </button>
            <ul id="quick-dropdown" class="dropdown-menu dropdown-menu-end" style="display:none;min-width:210px">
                <li><h6 class="dropdown-header" style="font-size:.7rem">Fast Manual Entry</h6></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('trips.index') }}?action=new"><span class="rounded-circle bg-primary" style="width:8px;height:8px;display:inline-block"></span>Add Trip</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('expenses.index') }}?action=new"><span class="rounded-circle bg-warning" style="width:8px;height:8px;display:inline-block"></span>Add Expense</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('invoices.index') }}?action=new"><span class="rounded-circle" style="width:8px;height:8px;display:inline-block;background:#6366f1"></span>Create Invoice</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('payments.index') }}?action=new"><span class="rounded-circle bg-success" style="width:8px;height:8px;display:inline-block"></span>Record Payment</a></li>
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('car-wash.index') }}?action=new"><span class="rounded-circle bg-info" style="width:8px;height:8px;display:inline-block"></span>Create Car Wash Bill</a></li>
            </ul>
        </div>

        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
             style="width:32px;height:32px;font-size:.8rem" aria-label="{{ auth()->user()->name }}" title="{{ auth()->user()->name }}">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
    </div>
</header>
