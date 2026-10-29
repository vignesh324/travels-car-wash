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
                        <th class="text-end">Base Charge</th>
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
        window.initQuickTripSlabForm?.(document.querySelector('#ajax-form-modal-body #quick-trip-config-form'));
    } catch (error) {
        toast.error(error.message || 'Unable to load Quick Trip configuration form');
    }
}

window.saveQuickTripConfiguration = async function(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement) || form.id !== 'quick-trip-config-form') return;
    window.initQuickTripSlabForm(form);
    if (!form.reportValidity()) return;
    const getValue = selector => form.querySelector(selector).value;
    const id = getValue('#quick-trip-config-id');
    const button = form.querySelector('#quick-trip-config-save');
    button.disabled = true;
    button.textContent = 'Saving…';
    document.querySelectorAll('#quick-trip-config-form .field-error').forEach(element => element.textContent = '');

    const payload = {
        customer_id: getValue('#quick-trip-customer'),
        name: getValue('#quick-trip-name').trim(),
        pickup_location: getValue('#quick-trip-pickup').trim(),
        drop_location: getValue('#quick-trip-drop').trim(),
        route: getValue('#quick-trip-route').trim() || null,
        per_trip_charge: getValue('#quick-trip-charge'),
        quick_trip_slabs: [...form.querySelectorAll('#quick-trip-slab-rows .quick-trip-slab-row')].map(row => ({
            max_trips: row.querySelector('.quick-trip-slab-max').value.trim() || null,
            per_trip_charge: row.querySelector('.quick-trip-slab-charge').value,
        })),
        is_active: getValue('#quick-trip-active') === '1',
        remarks: getValue('#quick-trip-remarks').trim() || null,
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
};

window.initQuickTripSlabForm = function(form) {
    if (!form || form.dataset.slabsInitialized === 'true') return;
    form.dataset.slabsInitialized = 'true';
    const list = form.querySelector('#quick-trip-slab-rows');
    const addButton = form.querySelector('#quick-trip-add-slab');
    const htmlEscape = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));

    function addSlab(slab = {}) {
        list.querySelector('[data-slab-empty]')?.remove();
        const row = document.createElement('div');
        row.className = 'quick-trip-slab-row row g-2 align-items-end border rounded p-2 mx-0';
        row.innerHTML = `
            <div class="col-12 col-md-5">
                <label class="form-label">Maximum trips <span class="text-muted">(blank = no threshold)</span></label>
                <input type="number" min="1" step="1" class="form-control quick-trip-slab-max"
                    value="${htmlEscape(slab.max_trips ?? '')}" placeholder="e.g. 10">
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label">Per-trip charge (₹)</label>
                <input type="number" min="0" step="0.01" class="form-control quick-trip-slab-charge"
                    value="${htmlEscape(slab.per_trip_charge ?? '')}" required>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="button" class="btn btn-outline-danger quick-trip-slab-remove" aria-label="Remove price slab">
                    <i class="bi bi-dash-lg" aria-hidden="true"></i><span class="ms-1">Remove</span>
                </button>
            </div>`;
        list.append(row);
    }

    let slabs = [];
    try {
        slabs = JSON.parse(list.dataset.slabs || '[]');
    } catch (error) {
        toast.error('Unable to load saved Quick Trip price slabs.');
        return;
    }
    slabs.forEach(addSlab);
    addButton.addEventListener('click', () => addSlab());
    list.addEventListener('click', event => {
        const removeButton = event.target.closest('.quick-trip-slab-remove');
        if (!removeButton) return;
        removeButton.closest('.quick-trip-slab-row').remove();
        if (!list.querySelector('.quick-trip-slab-row')) {
            const message = document.createElement('p');
            message.className = 'small text-muted mb-0';
            message.dataset.slabEmpty = '';
            message.textContent = 'No slabs yet. The base per-trip charge will be used.';
            list.append(message);
        }
    });
};

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
                {label: 'Base Per Trip Charge', value: record => fmt.currency(record.per_trip_charge)},
                {label: 'Trip-count price slabs', value: record => (record.slabs || []).map(slab =>
                    `${slab.max_trips === null ? 'No threshold (base rate)' : `More than ${slab.max_trips} trips`}: ${fmt.currency(slab.per_trip_charge)} per trip`
                ).join('\n') || 'No slabs configured'},
                {label: 'Status', value: record => record.is_active ? 'Active' : 'Inactive'},
                {label: 'Remarks', value: 'remarks', fullWidth: true},
            ],
        });
    }
});

initQuickTripConfigTable();
</script>
@endpush
