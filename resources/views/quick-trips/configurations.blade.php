@extends('layouts.app')
@section('title', 'Quick Trip Configurations')
@section('content')
<div class="container-fluid px-0">
    <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-2">Customer records</p>
            <h2 class="h2 fw-bold mb-1">Quick Trip Configurations</h2>
            <p class="small text-muted mb-0" id="quick-trip-config-count" aria-live="polite"></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Back to Customers</a>
            <x-action-button action="quick-trips.add" label="Add Quick Trip" />
        </div>
    </header>

    <section class="card mb-3" aria-labelledby="quick-trip-config-filters-heading">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-sliders2" aria-hidden="true"></i>
                <h3 class="small fw-semibold mb-0" id="quick-trip-config-filters-heading">Configuration filters</h3>
            </div>
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="quick-trip-filter-customer" class="form-label">Customer</label>
                    <select id="quick-trip-filter-customer" data-ajax-table="quick-trip-configurations-dt" class="form-select">
                        <option value="">All customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) $selectedCustomerId === (string) $customer->id)>{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="quick-trip-filter-status" class="form-label">Status</label>
                    <select id="quick-trip-filter-status" data-ajax-table="quick-trip-configurations-dt" class="form-select">
                        <option value="">All statuses</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="card overflow-hidden" aria-label="Quick Trip configuration list">
        <div class="table-responsive p-2">
            <table id="quick-trip-configurations-dt" class="table table-hover align-middle mb-0 small w-100">
                <thead class="table-light">
                    <tr>
                        <th>Customer</th>
                        <th>Quick Trip Name</th>
                        <th>Pickup Location</th>
                        <th>Drop Location</th>
                        <th>Route</th>
                        <th class="text-end">Per Trip Charge</th>
                        <th>Status</th>
                        <th class="text-end no-sort">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
        <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Scroll the table to see route and configuration actions.</p>
    </section>
</div>
@endsection

@push('scripts')
<script>
let quickTripConfigTable = null;
const quickTripConfigHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));

function initQuickTripConfigTable() {
    quickTripConfigTable = window.initDataTable('quick-trip-configurations-dt', @json(route('quick-trip-configurations.getData')), [
        {data: 'customer_name', render: value => quickTripConfigHtml(value || '—')},
        {data: 'name', render: value => quickTripConfigHtml(value)},
        {data: 'pickup_location', render: value => quickTripConfigHtml(value || '—')},
        {data: 'drop_location', render: value => quickTripConfigHtml(value || '—')},
        {data: 'route', render: value => quickTripConfigHtml(value || '—')},
        {data: 'per_trip_charge', className: 'text-end', render: value => fmt.currency(value)},
        {data: 'is_active', render: value => badge(value ? 'Active' : 'Inactive', value ? 'green' : 'gray')},
        {data: 'actions', orderable: false, searchable: false, className: 'text-end'},
    ], function() {
        return {
            customer_id: document.getElementById('quick-trip-filter-customer').value,
            is_active: document.getElementById('quick-trip-filter-status').value,
        };
    }, {
        order: [[0, 'asc'], [1, 'asc']],
        drawCallback: function(settings) {
            const count = settings.fnRecordsTotal();
            document.getElementById('quick-trip-config-count').textContent =
                `${count} Quick Trip configuration${count === 1 ? '' : 's'}`;
        },
    });
}

async function openQuickTripConfigModal(id = null) {
    try {
        let url = @json(route('quick-trip-configurations.addEdit'));
        if (id) url += `/${encodeURIComponent(id)}`;
        const customerId = document.getElementById('quick-trip-filter-customer').value;
        if (!id && customerId) url += `?customer_id=${encodeURIComponent(customerId)}`;
        await window.loadAddEditModal(url);
    } catch (error) {
        toast.error(error.message || 'Unable to load Quick Trip configuration form');
    }
}

async function saveQuickTripConfiguration() {
    const form = document.getElementById('quick-trip-config-form');
    if (!form.reportValidity()) return;
    const id = document.getElementById('quick-trip-config-id').value;
    const button = document.getElementById('quick-trip-config-save');
    button.disabled = true;
    button.textContent = 'Saving…';
    document.querySelectorAll('#quick-trip-config-form .field-error').forEach(element => element.textContent = '');

    const payload = {
        customer_id: document.getElementById('quick-trip-customer').value,
        name: document.getElementById('quick-trip-name').value.trim(),
        pickup_location: document.getElementById('quick-trip-pickup').value.trim(),
        drop_location: document.getElementById('quick-trip-drop').value.trim(),
        route: document.getElementById('quick-trip-route').value.trim() || null,
        per_trip_charge: document.getElementById('quick-trip-charge').value,
        is_active: document.getElementById('quick-trip-active').value === '1',
        remarks: document.getElementById('quick-trip-remarks').value.trim() || null,
    };

    try {
        const response = id
            ? await window.api.put(form.action, payload)
            : await window.api.post(form.action, payload);
        if (!response.ok) {
            showErrors(await extractErrors(response), 'quick-trip-config');
            return;
        }
        toast.success(id ? 'Quick Trip configuration updated' : 'Quick Trip configuration added');
        closeModal('ajax-form-modal');
        if (quickTripConfigTable) quickTripConfigTable.ajax.reload(null, false);
    } catch (error) {
        toast.error(error.message || 'Unable to save Quick Trip configuration');
    } finally {
        button.disabled = false;
        button.textContent = id ? 'Update Configuration' : 'Add Configuration';
    }
}

async function toggleQuickTripConfiguration(button) {
    const id = button.dataset.configurationId;
    const activating = button.dataset.isActive === '1';
    if (!window.confirm(`${activating ? 'Activate' : 'Deactivate'} this Quick Trip configuration?`)) return;
    button.disabled = true;
    try {
        const response = await window.api.post(`/quick-trip-configurations/${encodeURIComponent(id)}/toggle-status`, {});
        if (!response.ok) {
            const result = await response.json();
            throw new Error(result.message || 'Unable to update configuration status.');
        }
        toast.success(`Quick Trip configuration ${activating ? 'activated' : 'deactivated'}`);
        if (quickTripConfigTable) quickTripConfigTable.ajax.reload(null, false);
    } catch (error) {
        toast.error(error.message || 'Unable to update configuration status');
        button.disabled = false;
    }
}

document.addEventListener('click', event => {
    const button = event.target.closest('[data-action^="quick-trips."]');
    if (!button) return;

    if (button.dataset.action === 'quick-trips.add') {
        openQuickTripConfigModal();
    } else if (button.dataset.action === 'quick-trips.edit') {
        openQuickTripConfigModal(button.dataset.configurationId);
    } else if (button.dataset.action === 'quick-trips.toggle') {
        toggleQuickTripConfiguration(button);
    } else if (button.dataset.action === 'quick-trips.view') {
        window.openRecordView({
            url: @json(url('quick-trip-configurations')) + `/${encodeURIComponent(button.dataset.configurationId)}`,
            title: 'Quick Trip Configuration',
            fields: [
                {label: 'Customer', value: record => record.customer?.company_name || record.customer?.name},
                {label: 'Quick Trip Name', value: 'name'},
                {label: 'Pickup Location', value: 'pickup_location'},
                {label: 'Drop Location', value: 'drop_location'},
                {label: 'Route', value: 'route'},
                {label: 'Per Trip Charge', value: record => fmt.currency(record.per_trip_charge)},
                {label: 'Status', value: record => record.is_active ? 'Active' : 'Inactive'},
                {label: 'Remarks', value: 'remarks', fullWidth: true},
            ],
        });
    }
});

initQuickTripConfigTable();
</script>
@endpush
