@extends('layouts.app')
@section('title', 'Quick Trip Executions')
@section('content')
<div class="container-fluid px-0">
    <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-2">Trip operations</p>
            <h2 class="h2 fw-bold mb-1">Quick Trip Executions</h2>
            <p class="small text-muted mb-0" id="quick-trip-execution-count" aria-live="polite"></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" id="quick-trip-execution-add">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New Quick Trip Execution
            </button>
        </div>
    </header>

    <section class="card mb-3" aria-labelledby="quick-trip-execution-filters-heading">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-sliders2" aria-hidden="true"></i>
                <h3 class="small fw-semibold mb-0" id="quick-trip-execution-filters-heading">Execution filters</h3>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-xl-3">
                    <label for="quick-trip-execution-customer" class="form-label">Customer</label>
                    <select id="quick-trip-execution-customer" data-ajax-table="quick-trip-executions-dt" class="form-select">
                        <option value="">All customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-6 col-xl-2">
                    <label for="quick-trip-execution-date-from" class="form-label">Date from</label>
                    <input type="date" id="quick-trip-execution-date-from" data-ajax-table="quick-trip-executions-dt" class="form-control">
                </div>
                <div class="col-6 col-sm-6 col-xl-2">
                    <label for="quick-trip-execution-date-to" class="form-label">Date to</label>
                    <input type="date" id="quick-trip-execution-date-to" data-ajax-table="quick-trip-executions-dt" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <label for="quick-trip-execution-invoice-status" class="form-label">Invoice status</label>
                    <select id="quick-trip-execution-invoice-status" data-ajax-table="quick-trip-executions-dt" class="form-select">
                        <option value="">All executions</option>
                        <option value="uninvoiced">Uninvoiced</option>
                        <option value="invoiced">Invoiced</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="card overflow-hidden" aria-label="Quick Trip execution list">
        <div class="table-responsive p-2">
            <table id="quick-trip-executions-dt" class="table table-hover align-middle mb-0 small w-100">
                <thead class="table-light">
                    <tr>
                        <th>Trip Date</th>
                        <th>Customer</th>
                        <th>Quick Trip</th>
                        <th>Pickup Location</th>
                        <th>Drop Location</th>
                        <th>Route</th>
                        <th class="text-end">Pickup</th>
                        <th class="text-end">Drop</th>
                        <th class="text-end">Total Trips</th>
                        <th class="text-end">Per Trip Charge</th>
                        <th class="text-end">Total Amount</th>
                        <th>Invoice</th>
                        <th class="text-end no-sort">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
        <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Scroll the table to review execution details.</p>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/quick-trip-execution.js') }}"></script>
<script>
let quickTripExecutionsTable = null;
const quickTripExecutionHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[character]));

function initQuickTripExecutionsTable() {
    quickTripExecutionsTable = window.initDataTable('quick-trip-executions-dt', @json(route('quick-trip-executions.getData')), [
        {data: 'trip_date', render: value => fmt.date(value)},
        {data: 'customer_name', render: value => quickTripExecutionHtml(value)},
        {data: 'configuration_name', render: value => quickTripExecutionHtml(value)},
        {data: 'pickup_location', render: value => quickTripExecutionHtml(value)},
        {data: 'drop_location', render: value => quickTripExecutionHtml(value)},
        {data: 'route', render: value => quickTripExecutionHtml(value || '—')},
        {data: 'pickup_count', className: 'text-end'},
        {data: 'drop_count', className: 'text-end'},
        {data: 'total_count', className: 'text-end'},
        {data: 'per_trip_charge', className: 'text-end', render: value => fmt.currency(value)},
        {data: 'total_amount', className: 'text-end', render: value => fmt.currency(value)},
        {data: 'invoice_status', orderable: false, searchable: false},
        {data: 'actions', orderable: false, searchable: false, className: 'text-end'},
    ], function() {
        return {
            customer_id: document.getElementById('quick-trip-execution-customer').value,
            date_from: document.getElementById('quick-trip-execution-date-from').value,
            date_to: document.getElementById('quick-trip-execution-date-to').value,
            invoice_status: document.getElementById('quick-trip-execution-invoice-status').value,
        };
    }, {
        order: [[0, 'desc']],
        drawCallback: function(settings) {
            const count = settings.fnRecordsTotal();
            document.getElementById('quick-trip-execution-count').textContent =
                `${count} Quick Trip execution${count === 1 ? '' : 's'}`;
        },
    });
}

async function openQuickTripExecution(customerId = '', tripDate = '', configurationId = '') {
    const url = new URL(@json(route('trips.quick-execution')), window.location.origin);
    url.searchParams.set('modal', '1');
    if (customerId) url.searchParams.set('customer_id', customerId);
    if (tripDate) url.searchParams.set('trip_date', tripDate);
    if (configurationId) url.searchParams.set('configuration_id', configurationId);
    try {
        await window.loadAddEditModal(url.toString());
        window.initQuickTripExecution?.(document.querySelector('#ajax-form-modal-body .quick-trip-execution'));
    } catch (error) {
        toast.error(error.message || 'Unable to load Quick Trip execution');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initQuickTripExecutionsTable();
    document.addEventListener('quick-trip-execution:saved', () => quickTripExecutionsTable?.ajax.reload(null, false));
    document.getElementById('quick-trip-execution-add').addEventListener('click', () => openQuickTripExecution());
    document.getElementById('quick-trip-executions-dt').addEventListener('click', event => {
        const button = event.target.closest('[data-action="quick-trip-executions.edit"]');
        if (!button) return;
        openQuickTripExecution(button.dataset.customerId, button.dataset.tripDate, button.dataset.configurationId);
    });
});
</script>
@endpush
