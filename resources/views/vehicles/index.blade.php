@extends('layouts.app')
@section('title', 'Vehicles')

@section('content')
<div >
    <header class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-2"><i class="bi bi-truck-front me-1" aria-hidden="true"></i>Fleet operations</p>
            <h2 class="h2 fw-bold mb-2">Vehicles</h2>
            <p class="text-secondary small mb-0">Manage your fleet, keep vehicle details up to date, and find the right record quickly.</p>
        </div>
        <x-action-button
            action="vehicles.add"
            label="Add vehicle"
            size=""
            data-vehicle-action="add"
            class="d-inline-flex align-items-center gap-2 px-4 py-2 fw-semibold rounded-2"
        />
    </header>

    <section class="card mb-4" aria-labelledby="vehicle-filter-title">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-sliders2 text-primary" aria-hidden="true"></i>
                <h3 id="vehicle-filter-title" class="small fw-semibold mb-0">Vehicle filters</h3>
            </div>
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="veh-type" class="form-label">Vehicle type</label>
                    <select id="veh-type" data-ajax-table="vehicles-dt" class="form-select">
                        <option value="">All types</option>
                        <option>Car</option><option>Van</option><option>Bus</option><option>Tempo</option><option>SUV</option><option>Other</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="veh-fuel" class="form-label">Fuel type</label>
                    <select id="veh-fuel" data-ajax-table="vehicles-dt" class="form-select">
                        <option value="">All fuels</option>
                        <option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option><option>Other</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label for="veh-active" class="form-label">Status</label>
                    <select id="veh-active" data-ajax-table="vehicles-dt" class="form-select">
                        <option value="">All statuses</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="card overflow-hidden" aria-labelledby="vehicle-register-title">
        <div class="card-header bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 px-3 px-lg-4 py-3">
            <div>
                <p class="text-uppercase small fw-semibold text-secondary mb-1">Fleet register</p>
                <h3 id="vehicle-register-title" class="fs-6 fw-semibold mb-0">All vehicles</h3>
            </div>
            <p id="veh-count" class="small text-secondary mb-0" role="status" aria-live="polite" aria-atomic="true">Loading vehicle register…</p>
        </div>
        <div class="table-responsive" tabindex="0" aria-label="Vehicle register. Scroll horizontally to see all columns.">
            <table id="vehicles-dt" class="table table-hover align-middle mb-0 small w-100" aria-describedby="veh-count">
                <caption class="visually-hidden">Fleet register with registration, vehicle name, type, fuel, driver, status, and available actions.</caption>
                <thead>
                    <tr>
                        <th scope="col">Registration</th>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Fuel</th>
                        <th scope="col">Driver</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
        <p class="small text-secondary d-md-none mb-0 px-3 pb-3"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Swipe or scroll horizontally to see all columns</p>
    </section>
    @include('vehicles.partials.view-modal')
</div>

{{-- Delete Confirm --}}
<div id="modal-veh-delete" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="veh-delete-title" aria-describedby="veh-delete-msg">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm"><div class="modal-content p-4">
        <h3 id="veh-delete-title" class="fs-6 fw-semibold text-dark mb-2">Delete Vehicle</h3>
        <p class="small text-secondary mb-4" id="veh-delete-msg"></p>
        <div class="d-flex justify-content-end gap-3">
            <button type="button" onclick="closeModal('modal-veh-delete')" class="px-4 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Cancel</button>
            <button type="button" onclick="confirmDeleteVehicle()" id="veh-del-btn" class="px-4 py-2 bg-danger text-white small fw-semibold rounded-2 btn btn-danger btn-sm">Delete</button>
        </div>
    </div></div></div>

@push('scripts')
<script>
let vehTable = null, vehDeleteUuid = null;
const vehHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function initVehiclesTable() {
  vehTable = window.initDataTable('vehicles-dt', @json(route('vehicles.getData')), [
      { data: 'registration_number', render: v => `<span class="font-monospace fw-medium text-primary">${vehHtml(v)}</span>` },
      { data: 'name', render: v => `<span class="fw-medium">${vehHtml(v)}</span>` },
      { data: 'type',               render: v => v ? vehHtml(v) : '—' },
      { data: 'fuel_type',          render: v => vehHtml(v) },
      { data: 'default_driver_name', render: v => v ? vehHtml(v) : '—' },
      { data: 'is_active', render: v => badge(v ? 'Active' : 'Inactive', v ? 'green' : 'gray') },
      { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], function() {
      return {
        type:      document.getElementById('veh-type').value,
        fuel_type: document.getElementById('veh-fuel').value,
        is_active: document.getElementById('veh-active').value,
      };
    },
    {
      order: [[1, 'asc']],
      layout: {
        topStart: 'pageLength',
        topEnd: 'search',
        bottomStart: 'info',
        bottomEnd: 'paging',
      },
      drawCallback: function(s) {
        const n = s.fnRecordsTotal();
        document.getElementById('veh-count').textContent = n + ' vehicle' + (n !== 1 ? 's' : '');
      },
    }
  );
}

function vehFields() {
    return {
        registration_number: document.getElementById('inp_veh_registration_number').value,
        name:                document.getElementById('inp_veh_name').value,
        model:               document.getElementById('inp_veh_model').value,
        type:                document.getElementById('inp_veh_type').value,
        manufacturer:        document.getElementById('inp_veh_manufacturer').value,
        fuel_type:           document.getElementById('inp_veh_fuel_type').value,
        ownership_type:      document.getElementById('inp_veh_ownership_type').value,
        seating_capacity:    document.getElementById('inp_veh_seating_capacity').value ? Number(document.getElementById('inp_veh_seating_capacity').value) : null,
        current_odometer:    document.getElementById('inp_veh_current_odometer').value ? Number(document.getElementById('inp_veh_current_odometer').value) : null,
        purchase_date:       document.getElementById('inp_veh_purchase_date').value || null,
        default_driver_name: document.getElementById('inp_veh_default_driver_name').value,
        default_driver_phone:document.getElementById('inp_veh_default_driver_phone').value,
        insurance_expiry:    document.getElementById('inp_veh_insurance_expiry').value || null,
        permit_expiry:       document.getElementById('inp_veh_permit_expiry').value || null,
        fitness_expiry:      document.getElementById('inp_veh_fitness_expiry').value || null,
        puc_expiry:          document.getElementById('inp_veh_puc_expiry').value || null,
        is_active:           document.getElementById('inp_veh_is_active').checked,
        notes:               document.getElementById('inp_veh_notes').value,
    };
}

function setVehFields(v) {
    document.getElementById('inp_veh_registration_number').value = v.registration_number||'';
    document.getElementById('inp_veh_name').value                = v.name||'';
    document.getElementById('inp_veh_model').value               = v.model||'';
    document.getElementById('inp_veh_type').value                = v.type||'';
    document.getElementById('inp_veh_manufacturer').value        = v.manufacturer||'';
    document.getElementById('inp_veh_fuel_type').value           = v.fuel_type||'Diesel';
    document.getElementById('inp_veh_ownership_type').value      = v.ownership_type||'Owned';
    document.getElementById('inp_veh_seating_capacity').value    = v.seating_capacity||'';
    document.getElementById('inp_veh_current_odometer').value    = v.current_odometer||'';
    document.getElementById('inp_veh_purchase_date').value       = v.purchase_date||'';
    document.getElementById('inp_veh_default_driver_name').value = v.default_driver_name||'';
    document.getElementById('inp_veh_default_driver_phone').value= v.default_driver_phone||'';
    document.getElementById('inp_veh_insurance_expiry').value    = v.insurance_expiry||'';
    document.getElementById('inp_veh_permit_expiry').value       = v.permit_expiry||'';
    document.getElementById('inp_veh_fitness_expiry').value      = v.fitness_expiry||'';
    document.getElementById('inp_veh_puc_expiry').value          = v.puc_expiry||'';
    document.getElementById('inp_veh_is_active').checked         = v.is_active !== false;
    document.getElementById('inp_veh_notes').value               = v.notes||'';
}

async function openVehicleModal(uuid) {
    try {
        const url = @json(route('vehicles.addEdit')) + (uuid ? `/${encodeURIComponent(uuid)}` : '');
        await window.loadAddEditModal(url);
    } catch (error) {
        toast.error(error.message || 'Unable to load vehicle form');
    }
}

async function openVehicleView(url) {
    try {
        const response = await window.api.get(url);
        if (!response.ok) throw new Error('Unable to load vehicle details');

        const vehicle = await response.json();
        document.querySelectorAll('#modal-veh-view [data-vehicle-field]').forEach(field => {
            const key = field.dataset.vehicleField;
            const value = vehicle[key];
            if (key === 'is_active') {
                field.textContent = value ? 'Active' : 'Inactive';
            } else if (['purchase_date', 'insurance_expiry', 'permit_expiry', 'fitness_expiry', 'puc_expiry'].includes(key)) {
                field.textContent = value ? window.fmt.date(value) : '—';
            } else if (key === 'current_odometer' && value !== null && value !== '') {
                field.textContent = `${value} km`;
            } else {
                field.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
            }
        });

        const title = vehicle.name
            ? `${vehicle.name}${vehicle.registration_number ? ` · ${vehicle.registration_number}` : ''}`
            : 'Vehicle details';
        document.getElementById('veh-view-title').textContent = title;
        openModal('modal-veh-view');
    } catch (error) {
        toast.error(error.message || 'Unable to load vehicle details');
    }
}

document.addEventListener('click', event => {
    const button = event.target.closest('[data-vehicle-action]');
    if (!button) return;

    switch (button.dataset.vehicleAction) {
        case 'add':
            openVehicleModal();
            break;
        case 'view':
            openVehicleView(button.dataset.vehicleUrl);
            break;
        case 'edit':
            openVehicleModal(button.dataset.vehicleUuid);
            break;
        case 'delete':
            deleteVehicle(button.dataset.vehicleUuid, button.dataset.vehicleLabel);
            break;
    }
});

async function saveVehicle() {
    const form = document.getElementById('vehicle-form');
    if (!form.reportValidity()) return;
    const uuid = document.getElementById('veh-edit-uuid').value;
    const btn = document.getElementById('veh-save-btn');
    btn.disabled = true; btn.textContent = 'Saving…';
    document.querySelectorAll('.field-error').forEach(el => el.textContent='');
    document.querySelectorAll('.inp-error').forEach(el => el.classList.remove('is-invalid'));

    try {
        const payload = vehFields();
        const res = uuid ? await window.api.put(form.action, payload) : await window.api.post(form.action, payload);

        if (res.ok) {
            toast.success(uuid ? 'Vehicle updated' : 'Vehicle added');
            closeModal('ajax-form-modal');
            if (vehTable) vehTable.ajax.reload(null, false);
        } else {
            showErrors(await extractErrors(res), 'veh');
        }
    } catch { toast.error('Save failed'); }
    finally { btn.disabled=false; btn.textContent = uuid ? 'Update Vehicle' : 'Add Vehicle'; }
}

function deleteVehicle(uuid, name) {
    vehDeleteUuid = uuid;
    document.getElementById('veh-delete-msg').textContent = `Are you sure you want to delete "${name}"?`;
    openModal('modal-veh-delete');
}

async function confirmDeleteVehicle() {
    const btn = document.getElementById('veh-del-btn');
    btn.disabled=true; btn.textContent='Deleting…';
    try {
        const res = await window.api.delete(`/vehicles/${encodeURIComponent(vehDeleteUuid)}`);
        if (res.ok) { toast.success('Vehicle deleted'); closeModal('modal-veh-delete'); if (vehTable) vehTable.ajax.reload(null, false); }
        else { toast.error('Delete failed'); }
    } catch { toast.error('Delete failed'); }
    finally { btn.disabled=false; btn.textContent='Delete'; }
}

initVehiclesTable();
</script>
@endpush
@endsection
