@extends('layouts.app')
@section('title', 'Trips')
@section('content')
<div class="container-fluid px-0">
  <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
      <p class="text-uppercase small fw-semibold text-secondary mb-2">Trip operations</p>
      <h2 class="h2 fw-bold mb-1">Trips</h2>
      <p class="small text-muted mb-0 mt-1" id="trip-count" aria-live="polite"></p>
    </div>
    <x-action-button action="trips.add" label="Add Trip"  />
  </header>

  <section class="card mb-3" aria-labelledby="trip-filters-heading">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-sliders2" aria-hidden="true"></i>
        <h3 class="small fw-semibold mb-0" id="trip-filters-heading">Trip filters</h3>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-12 col-sm-6 col-xl-3">
          <label for="trip-filter-vehicle" class="form-label">Vehicle</label>
          <select id="trip-filter-vehicle" data-ajax-table="trips-dt" class="form-select">
            <option value="">All Vehicles</option>
            @foreach($vehicles as $vehicle)
              <option value="{{ $vehicle->id }}">{{ $vehicle->name }} ({{ $vehicle->registration_number }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="trip-filter-type" class="form-label">Trip type</label>
          <select id="trip-filter-type" data-ajax-table="trips-dt" class="form-select">
            <option value="">All Types</option>
            <option>Local</option><option>Outstation</option><option>Airport Transfer</option><option>Corporate</option><option>Other</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="trip-filter-status" class="form-label">Status</label>
          <select id="trip-filter-status" data-ajax-table="trips-dt" class="form-select">
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="trip-filter-payment" class="form-label">Payment</label>
          <select id="trip-filter-payment" data-ajax-table="trips-dt" class="form-select">
            <option value="">All Payments</option>
            <option value="unpaid">Unpaid</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <label for="trip-date-from" class="form-label">Date from</label>
          <input type="date" id="trip-date-from" data-ajax-table="trips-dt" class="form-control" />
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <label for="trip-date-to" class="form-label">Date to</label>
          <input type="date" id="trip-date-to" data-ajax-table="trips-dt" class="form-control" />
        </div>
      </div>
    </div>
  </section>

  <section class="card" aria-label="Trip list">
    <div class="table-responsive p-2">
      <table id="trips-dt" class="table table-hover align-middle mb-0 small w-100">
        <thead class="table-light">
          <tr>
            <th>Trip #</th>
            <th>Date</th>
            <th>Vehicle</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Route</th>
            <th class="text-end">Amount</th>
            <th>Status</th>
            <th class="text-end no-sort">Actions</th>
          </tr>
        </thead>
      </table>
    </div>
    <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Tap + on a row to see all details and actions</p>
  </section>
</div>

{{-- Delete Confirm --}}
<div id="modal-trip-delete" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete Trip</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small" id="trip-delete-msg"></p>
      </div>
      <div class="modal-footer">
        <button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary btn-sm">Cancel</button>
        <button type="button" onclick="confirmDeleteTrip()" id="trip-del-btn" class="btn btn-danger btn-sm">Delete</button>
      </div>
    </div>
  </div>
</div>

{{-- View Modal --}}
<div id="modal-trip-view" class="modal fade" tabindex="-1" aria-labelledby="trip-view-title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="trip-view-title">Trip Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="trip-view-body"></div>
    </div>
  </div>
</div>

@push('scripts')
<script>
let tripDeleteUuid = null, tripsTable = null;
const tripHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function initTripsTable() {
  tripsTable = window.initDataTable('trips-dt', @json(route('trips.getData')), [
      { data: 'trip_number', render: v => `<span class="font-monospace fw-semibold text-primary">${tripHtml(v)}</span>` },
      { data: 'trip_date',   render: v => fmt.date(v) },
      { data: 'vehicle', orderable: false, searchable: false, render: v => v ? tripHtml(v.name) : '—' },
      { data: 'customer', orderable: false, searchable: false, render: v => v ? tripHtml(v.company_name || v.name) : '<span class="fst-italic text-muted">Walk-in</span>' },
      { data: 'trip_type' },
      { data: 'route', render: v => tripHtml(v || '—') },
      { data: 'total_amount', className: 'text-end', render: v => `<span class="fw-semibold">${fmt.currency(v)}</span>` },
      { data: 'status', render: (v, _, row) =>
          badge(v, window.tripStatusColor(v)) + ' ' + badge(row.payment_status, window.paymentStatusColor(row.payment_status)) },
      { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], function() {
      return {
        vehicle_id:     document.getElementById('trip-filter-vehicle').value,
        trip_type:      document.getElementById('trip-filter-type').value,
        status:         document.getElementById('trip-filter-status').value,
        payment_status: document.getElementById('trip-filter-payment').value,
        date_from:      document.getElementById('trip-date-from').value,
        date_to:      document.getElementById('trip-date-to').value,
      };
    },
    {
      order: [[1, 'desc']],
      drawCallback: function(s) {
        const n = s.fnRecordsTotal();
        document.getElementById('trip-count').textContent = n + ' trip' + (n !== 1 ? 's' : '');
      },
    }
  );
}

function toggleTripTax() {
  const checked = document.getElementById('inp_trip_is_taxable').checked;
  document.getElementById('trip-tax-rate-wrap').classList.toggle('d-none', !checked);
}

function updateTripPreview() {
  const fare  = Math.max(0, parseFloat(document.getElementById('inp_trip_fare_amount')?.value) || 0);
  const add   = Math.max(0, parseFloat(document.getElementById('inp_trip_additional_charges')?.value) || 0);
  const disc  = Math.max(0, parseFloat(document.getElementById('inp_trip_discount')?.value) || 0);
  const taxed = document.getElementById('inp_trip_is_taxable')?.checked;
  const rate  = taxed ? Math.max(0, parseFloat(document.getElementById('inp_trip_tax_rate')?.value) || 0) : 0;
  const recv  = Math.max(0, parseFloat(document.getElementById('inp_trip_amount_received')?.value) || 0);
  const sub   = fare + add;
  const taxable = Math.max(0, sub - disc);
  const tax   = taxed ? Math.round(taxable * rate) / 100 : 0;
  const total = taxable + tax;
  const bal   = Math.max(0, total - recv);
  document.getElementById('trip-prev-taxable').textContent = fmt.currency(taxable);
  document.getElementById('trip-prev-tax').textContent     = fmt.currency(tax);
  document.getElementById('trip-prev-total').textContent   = fmt.currency(total);
  const bEl = document.getElementById('trip-prev-balance');
  bEl.textContent = fmt.currency(bal);
  bEl.className   = 'fw-bold ' + (bal > 0 ? 'text-danger' : 'text-success');
}

async function openTripModal(uuid) {
  try {
    const url = @json(route('trips.addEdit')) + (uuid ? `/${encodeURIComponent(uuid)}` : '');
    await window.loadAddEditModal(url);
    toggleTripTax();
    updateTripPreview();
  } catch (error) {
    toast.error(error.message || 'Unable to load trip form');
  }
}

async function saveTrip() {
  const form = document.getElementById('trip-form');
  if (!form.reportValidity()) return;
  const uuid = document.getElementById('trip-edit-uuid').value;
  const btn  = document.getElementById('trip-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
  const payload = {
    trip_date:           document.getElementById('inp_trip_trip_date').value,
    trip_type:           document.getElementById('inp_trip_trip_type').value,
    vehicle_id:          parseInt(document.getElementById('inp_trip_vehicle_id').value) || null,
    customer_id:         parseInt(document.getElementById('inp_trip_customer_id').value) || null,
    pickup_location:     document.getElementById('inp_trip_pickup_location').value || null,
    drop_location:       document.getElementById('inp_trip_drop_location').value || null,
    route:               document.getElementById('inp_trip_route').value || null,
    driver_name:         document.getElementById('inp_trip_driver_name').value || null,
    status:              document.getElementById('inp_trip_status').value,
    start_odometer:      parseFloat(document.getElementById('inp_trip_start_odometer').value) || null,
    end_odometer:        parseFloat(document.getElementById('inp_trip_end_odometer').value) || null,
    fare_amount:         parseFloat(document.getElementById('inp_trip_fare_amount').value) || 0,
    additional_charges:  parseFloat(document.getElementById('inp_trip_additional_charges').value) || 0,
    discount:            parseFloat(document.getElementById('inp_trip_discount').value) || 0,
    is_taxable:          document.getElementById('inp_trip_is_taxable').checked,
    tax_rate:            parseFloat(document.getElementById('inp_trip_tax_rate').value) || 0,
    payment_mode:        document.getElementById('inp_trip_payment_mode').value || null,
    amount_received:     parseFloat(document.getElementById('inp_trip_amount_received').value) || 0,
    notes:               document.getElementById('inp_trip_notes').value || null,
  };
  try {
    const res = uuid
      ? await window.api.put(form.action, payload)
      : await window.api.post(form.action, payload);
    if (res.ok) {
      toast.success(uuid ? 'Trip updated' : 'Trip recorded');
      closeModal('ajax-form-modal');
      if (tripsTable) tripsTable.ajax.reload(null, false);
    } else {
      showErrors(await extractErrors(res), 'trip');
    }
  } catch { toast.error('Save failed'); }
  finally { btn.disabled = false; btn.textContent = uuid ? 'Update Trip' : 'Save Trip'; }
}

function deleteTrip(uuid, number) {
  tripDeleteUuid = uuid;
  document.getElementById('trip-delete-msg').textContent = 'Delete trip ' + number + '? This cannot be undone.';
  openModal('modal-trip-delete');
}

async function confirmDeleteTrip() {
  const btn = document.getElementById('trip-del-btn');
  btn.disabled = true; btn.textContent = 'Deleting…';
  try {
    const res = await window.api.delete('/trips/' + tripDeleteUuid);
    if (res.ok) { toast.success('Trip deleted'); closeModal('modal-trip-delete'); if (tripsTable) tripsTable.ajax.reload(null, false); }
    else { toast.error('Delete failed'); }
  } catch { toast.error('Delete failed'); }
  finally { btn.disabled = false; btn.textContent = 'Delete'; }
}

async function viewTrip(uuid) {
  document.getElementById('trip-view-title').textContent = 'Trip Details';
  document.getElementById('trip-view-body').innerHTML = '<div class="py-5 text-center text-secondary">Loading...</div>';
  openModal('modal-trip-view');
  try {
    const res = await window.api.get('/trips/' + uuid);
    const t   = await res.json();
    document.getElementById('trip-view-title').textContent = 'Trip: ' + t.trip_number;
    const custName = t.customer ? (t.customer.company_name || t.customer.name) : 'Walk-in';
    document.getElementById('trip-view-body').innerHTML = `
      <div class="d-flex flex-column gap-3 small">
        <div class="row row-cols-1 row-cols-sm-2 gap-3">
          <div><span class="text-muted">Date:</span> <span class="fw-medium ml-1">${fmt.date(t.trip_date)}</span></div>
          <div><span class="text-muted">Type:</span> <span class="fw-medium ml-1">${tripHtml(t.trip_type)}</span></div>
          <div><span class="text-muted">Vehicle:</span> <span class="fw-medium ml-1">${tripHtml(t.vehicle ? t.vehicle.name : '—')}</span></div>
          <div><span class="text-muted">Driver:</span> <span class="fw-medium ml-1">${tripHtml(t.driver_name || '—')}</span></div>
          <div><span class="text-muted">Customer:</span> <span class="fw-medium ml-1">${tripHtml(custName)}</span></div>
          <div><span class="text-muted">Status:</span> <span class="ml-1">${badge(tripHtml(t.status), window.tripStatusColor(t.status))}</span></div>
          ${t.pickup_location ? `<div><span class="text-muted">Pickup:</span> <span class="fw-medium ml-1">${tripHtml(t.pickup_location)}</span></div>` : ''}
          ${t.drop_location   ? `<div><span class="text-muted">Drop:</span> <span class="fw-medium ml-1">${tripHtml(t.drop_location)}</span></div>` : ''}
          ${t.route           ? `<div><span class="text-muted">Route:</span> <span class="fw-medium ml-1">${tripHtml(t.route)}</span></div>` : ''}
          ${t.start_odometer != null ? `<div><span class="text-muted">Start Odo:</span> <span class="fw-medium ml-1">${t.start_odometer} km</span></div>` : ''}
          ${t.end_odometer   != null ? `<div><span class="text-muted">End Odo:</span> <span class="fw-medium ml-1">${t.end_odometer} km</span></div>` : ''}
          ${t.distance_km    != null ? `<div><span class="text-muted">Distance:</span> <span class="fw-medium ml-1">${t.distance_km} km</span></div>` : ''}
          <div><span class="text-muted">Payment:</span> <span class="ml-1">${badge(tripHtml(t.payment_status), window.paymentStatusColor(t.payment_status))}</span></div>
        </div>
        <div class="bg-light rounded-2 p-3 row row-cols-1 row-cols-sm-2 row-cols-sm-4 gap-2 small">
          <div><p class="text-muted">Fare</p><p class="fw-medium mt-1">${fmt.currency(t.fare_amount)}</p></div>
          <div><p class="text-muted">Extra</p><p class="fw-medium mt-1">${fmt.currency(t.additional_charges)}</p></div>
          <div><p class="text-muted">Discount</p><p class="fw-medium mt-1">${fmt.currency(t.discount)}</p></div>
          <div><p class="text-muted">Tax</p><p class="fw-medium mt-1">${fmt.currency(t.tax_amount)}</p></div>
          <div><p class="text-muted">Total</p><p class="fw-bold text-primary mt-1">${fmt.currency(t.total_amount)}</p></div>
          <div><p class="text-muted">Received</p><p class="fw-semibold text-success mt-1">${fmt.currency(t.amount_received)}</p></div>
          <div><p class="text-muted">Balance</p><p class="fw-bold text-danger mt-1">${fmt.currency(t.balance_due)}</p></div>
          ${t.payment_mode ? `<div><p class="text-muted">Mode</p><p class="fw-medium mt-1">${tripHtml(t.payment_mode)}</p></div>` : ''}
        </div>
        ${t.notes ? `<p class="text-muted small">Notes: ${tripHtml(t.notes)}</p>` : ''}
        <div class="d-flex justify-content-end pt-2">
          <button onclick="closeModal('modal-trip-view')" class="px-3 py-2 rounded-2 bg-primary text-white small fw-semibold btn btn-primary btn-sm">Close</button>
        </div>
      </div>`;
  } catch { toast.error('Failed to load trip'); }
}

(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('action') === 'new') {
    history.replaceState(null, '', window.location.pathname);
    initTripsTable();
    openTripModal();
    return;
  }
  initTripsTable();
})();

document.addEventListener('click', event => {
  const button = event.target.closest('[data-action^="trips."]');
  if (!button) return;

  switch (button.dataset.action) {
    case 'trips.add':
      openTripModal();
      break;
    case 'trips.view':
      viewTrip(button.dataset.tripUuid);
      break;
    case 'trips.edit':
      openTripModal(button.dataset.tripUuid);
      break;
    case 'trips.delete':
      deleteTrip(button.dataset.tripUuid, button.dataset.tripNumber);
      break;
  }
});
</script>
@endpush
@endsection
