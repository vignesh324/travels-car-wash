@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-3">
    <h5 class="fw-bold mb-0">Dashboard</h5>
    <p class="text-muted small mb-0">Welcome back, {{ auth()->user()->name }}</p>
</div>

{{-- Today --}}
<p class="small text-uppercase fw-semibold text-muted mb-2">Today</p>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-primary-subtle">
            <div class="card-body py-3">
                <p class="small text-primary mb-1 opacity-75">Trips Today</p>
                <p class="h5 fw-bold text-primary mb-0">{{ $summary['today']['trip_count'] }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-success-subtle">
            <div class="card-body py-3">
                <p class="small text-success mb-1 opacity-75">Trip Revenue</p>
                <p class="h5 fw-bold text-success mb-0">₹{{ number_format($summary['today']['trip_revenue'], 2) }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-success-subtle">
            <div class="card-body py-3">
                <p class="small text-success mb-1 opacity-75">Collected</p>
                <p class="h5 fw-bold text-success mb-0">₹{{ number_format($summary['today']['collected'], 2) }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-danger-subtle">
            <div class="card-body py-3">
                <p class="small text-danger mb-1 opacity-75">Expenses</p>
                <p class="h5 fw-bold text-danger mb-0">₹{{ number_format($summary['today']['expenses'], 2) }}</p>
            </div>
        </div>
    </div>
</div>

{{-- This Month --}}
<p class="small text-uppercase fw-semibold text-muted mb-2">This Month</p>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-primary-subtle">
            <div class="card-body py-3">
                <p class="small text-primary mb-1 opacity-75">Revenue</p>
                <p class="h5 fw-bold text-primary mb-0">₹{{ number_format($summary['month']['revenue'], 2) }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-success-subtle">
            <div class="card-body py-3">
                <p class="small text-success mb-1 opacity-75">Collections</p>
                <p class="h5 fw-bold text-success mb-0">₹{{ number_format($summary['month']['collected'], 2) }}</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 bg-danger-subtle">
            <div class="card-body py-3">
                <p class="small text-danger mb-1 opacity-75">Expenses</p>
                <p class="h5 fw-bold text-danger mb-0">₹{{ number_format($summary['month']['expenses'], 2) }}</p>
            </div>
        </div>
    </div>
    @php $profit = $summary['month']['estimated_profit']; @endphp
    <div class="col-6 col-lg-3">
        <div class="card h-100 {{ $profit >= 0 ? 'bg-success-subtle' : 'bg-danger-subtle' }}">
            <div class="card-body py-3">
                <p class="small mb-1 opacity-75 {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">Est. Profit</p>
                <p class="h5 fw-bold mb-0 {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">₹{{ number_format($profit, 2) }}</p>
                <p class="small mb-0 text-body-secondary">Operating expenses only</p>
            </div>
        </div>
    </div>
</div>

{{-- Outstanding + Alerts --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="card border-warning h-100 bg-warning-subtle">
            <div class="card-body py-3">
                <p class="small text-warning mb-1 fw-medium">Outstanding Balance</p>
                <p class="h5 fw-bold text-warning mb-1">₹{{ number_format($summary['outstanding_balance'], 2) }}</p>
                <p class="small text-warning opacity-75 mb-0">Unpaid / partial trips</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card h-100 {{ $summary['expiry_alerts'] > 0 ? 'border-danger bg-danger-subtle' : 'bg-body-secondary' }}">
            <div class="card-body py-3">
                <p class="small text-muted mb-1 fw-medium">Document Expiry Alerts</p>
                <p class="h5 fw-bold mb-1 {{ $summary['expiry_alerts'] > 0 ? 'text-danger' : 'text-secondary' }}">
                    {{ $summary['expiry_alerts'] }} vehicle{{ $summary['expiry_alerts'] !== 1 ? 's' : '' }}
                </p>
                <p class="small text-muted mb-0">Expiring within 30 days</p>
            </div>
        </div>
    </div>
</div>

{{-- Daily Revenue Chart --}}
@if($dailyRevenue->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <p class="fw-semibold text-dark small mb-3" id="daily-chart-title">Daily Revenue — Last 30 Days</p>
        <div class="position-relative chart-container">
            <canvas id="daily-chart" role="img" aria-labelledby="daily-chart-title"></canvas>
        </div>
    </div>
</div>
@endif

{{-- Vehicle Revenue + Expense Breakdown --}}
@if($vehicleRevenue->isNotEmpty() || $expenseBreakdown->isNotEmpty())
<div class="row g-4 mb-4">
    @if($vehicleRevenue->isNotEmpty())
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="fw-semibold text-dark small mb-3" id="vehicle-chart-title">Vehicle Revenue — This Month</p>
                <div class="position-relative chart-container">
                    <canvas id="vehicle-chart" role="img" aria-labelledby="vehicle-chart-title"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif
    @if($expenseBreakdown->isNotEmpty())
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="fw-semibold text-dark small mb-3" id="expense-chart-title">Expense Breakdown — This Month</p>
                <div class="position-relative chart-container">
                    <canvas id="expense-chart" role="img" aria-labelledby="expense-chart-title"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

{{-- Recent Trips --}}
@if($recentTrips->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-2 px-3">
        <p class="fw-semibold small mb-0">Recent Trips</p>
    </div>
    <ul class="list-group list-group-flush">
        @foreach($recentTrips as $trip)
        <li class="list-group-item px-3 py-2 d-flex align-items-center justify-content-between gap-3">
            <div class="overflow-hidden">
                <p class="fw-medium text-dark small mb-0 text-truncate">
                    {{ $trip->customer?->name ?? $trip->customer?->company_name ?? 'Walk-in' }}
                </p>
                <p class="small text-muted mb-0">
                    {{ $trip->vehicle?->name }} · {{ \Carbon\Carbon::parse($trip->trip_date)->format('d M Y') }}
                </p>
            </div>
            <div class="text-end flex-shrink-0">
                <p class="fw-semibold text-dark small mb-1">₹{{ number_format($trip->total_amount, 2) }}</p>
                @php
                    $sc = match($trip->payment_status) {
                        'paid'    => 'text-bg-success',
                        'partial' => 'text-bg-warning',
                        'unpaid'  => 'text-bg-danger',
                        default   => 'text-bg-secondary',
                    };
                @endphp
                <span class="badge {{ $sc }}">{{ ucfirst($trip->payment_status) }}</span>
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

{{-- Expiry Alerts --}}
@if($expiringDocuments->isNotEmpty())
<div class="card border-danger shadow-sm mb-4">
    <div class="card-header bg-danger bg-opacity-10 border-danger py-2 px-3">
        <p class="fw-semibold text-danger small mb-0">⚠️ Document Expiry Alerts</p>
    </div>
    <ul class="list-group list-group-flush">
        @foreach($expiringDocuments as $doc)
        <li class="list-group-item px-3 py-2">
            <p class="fw-medium text-dark small mb-1">
                {{ $doc->name }} <span class="text-muted font-monospace small">({{ $doc->registration_number }})</span>
            </p>
            <div class="d-flex flex-wrap gap-3 text-muted small">
                @if($doc->insurance_expiry) <span>Insurance: {{ \Carbon\Carbon::parse($doc->insurance_expiry)->format('d M Y') }}</span> @endif
                @if($doc->permit_expiry)    <span>Permit: {{ \Carbon\Carbon::parse($doc->permit_expiry)->format('d M Y') }}</span> @endif
                @if($doc->fitness_expiry)   <span>Fitness: {{ \Carbon\Carbon::parse($doc->fitness_expiry)->format('d M Y') }}</span> @endif
                @if($doc->puc_expiry)       <span>PUC: {{ \Carbon\Carbon::parse($doc->puc_expiry)->format('d M Y') }}</span> @endif
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

@push('scripts')
<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script>
const chartDefaults = {
    font: { family: 'system-ui, sans-serif', size: 11 },
    color: '#6b7280',
};
Chart.defaults.font = chartDefaults.font;
Chart.defaults.color = chartDefaults.color;

@if($dailyRevenue->isNotEmpty())
new Chart(document.getElementById('daily-chart'), {
    type: 'bar',
    data: {
        labels: {!! $dailyRevenue->pluck('date')->toJson() !!},
        datasets: [
            { label: 'Revenue',   data: {!! $dailyRevenue->pluck('revenue')->toJson() !!},   backgroundColor: '#3b82f6', borderRadius: 4 },
            { label: 'Collected', data: {!! $dailyRevenue->pluck('collected')->toJson() !!}, backgroundColor: '#10b981', borderRadius: 4 },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } },
            tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ₹${Number(ctx.parsed.y).toLocaleString('en-IN')}` } },
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₹' + Number(v).toLocaleString('en-IN') }, grid: { color: '#f3f4f6' } },
            x: { grid: { display: false } },
        },
    },
});
@endif

@if($vehicleRevenue->isNotEmpty())
new Chart(document.getElementById('vehicle-chart'), {
    type: 'bar',
    data: {
        labels: {!! $vehicleRevenue->pluck('vehicle')->toJson() !!},
        datasets: [{ label: 'Revenue', data: {!! $vehicleRevenue->pluck('revenue')->toJson() !!}, backgroundColor: '#6366f1', borderRadius: 4 }],
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ₹${Number(ctx.parsed.x).toLocaleString('en-IN')}` } },
        },
        scales: {
            x: { beginAtZero: true, ticks: { callback: v => '₹' + Number(v).toLocaleString('en-IN') }, grid: { color: '#f3f4f6' } },
            y: { grid: { display: false } },
        },
    },
});
@endif

@if($expenseBreakdown->isNotEmpty())
new Chart(document.getElementById('expense-chart'), {
    type: 'doughnut',
    data: {
        labels: {!! $expenseBreakdown->pluck('category')->toJson() !!},
        datasets: [{ data: {!! $expenseBreakdown->pluck('total')->toJson() !!}, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316'], borderWidth: 2, borderColor: '#fff' }],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { boxWidth: 12, padding: 12 } },
            tooltip: { callbacks: { label: ctx => '₹' + Number(ctx.parsed).toLocaleString('en-IN') } },
        },
    },
});
@endif
</script>
@endpush
@endsection
