@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
<div >
  <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <p class="text-uppercase small fw-semibold text-secondary">Billing records</p>
      <h2 class="h2 fw-bold mb-1">Invoices</h2>
      <p class="small text-muted mb-0" id="inv-count" aria-live="polite"></p>
    </div>
    <x-action-button action="invoices.add" label="Create Invoice" size="" class="px-4 py-2 fw-semibold rounded-2" />
  </header>

  <section class="card mb-4" aria-labelledby="invoice-filter-title">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-sliders2" aria-hidden="true"></i>
        <h3 id="invoice-filter-title" class="small fw-semibold mb-0">Invoice filters</h3>
      </div>
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
          <label for="inv-filter-customer" class="form-label">Customer</label>
          <select id="inv-filter-customer" data-ajax-table="invoices-dt" class="form-select">
            <option value="">All customers</option>
            @foreach($customers as $customer)
              <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="inv-filter-status" class="form-label">Status</label>
          <select id="inv-filter-status" data-ajax-table="invoices-dt" class="form-select">
            <option value="">All statuses</option>
            <option value="issued">Issued</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="inv-filter-source" class="form-label">Source</label>
          <select id="inv-filter-source" data-ajax-table="invoices-dt" class="form-select">
            <option value="">All sources</option>
            <option value="travels">Travels</option>
            <option value="carwash">Car Wash</option>
            <option value="mixed">Mixed</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="inv-date-from" class="form-label">From</label>
          <input type="date" id="inv-date-from" data-ajax-table="invoices-dt" class="form-control" />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="inv-date-to" class="form-label">To</label>
          <input type="date" id="inv-date-to" data-ajax-table="invoices-dt" class="form-control" />
        </div>
      </div>
    </div>
  </section>

  <div class="card overflow-hidden">
    <div class="table-responsive p-2">
      <table id="invoices-dt" class="table table-hover align-middle mb-0 small w-100">
        <thead class="table-light">
          <tr>
            <th>Invoice #</th><th>Date</th><th>Customer</th><th>Source</th>
            <th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th>
            <th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
      </table>
    </div>
    <p class="small text-secondary d-md-none mb-0 px-3 pb-3"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Swipe or scroll horizontally to see all columns</p>
  </div>
</div>

{{-- Cancel Invoice Modal --}}
<div id="modal-inv-cancel" class="modal fade" tabindex="-1" aria-hidden="true" aria-label="Confirm action" >
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm"><div class="modal-content p-4">
    <h3 class="fs-6 fw-semibold text-dark mb-2">Cancel Invoice</h3>
    <p class="small text-secondary mb-4" id="inv-cancel-msg"></p>
    <label class="d-block small fw-medium text-body mb-1 form-label" for="inv-cancel-reason">Cancellation Reason *</label>
    <textarea id="inv-cancel-reason" rows="3" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" placeholder="Reason for cancellation..."></textarea>
    <div class="d-flex justify-content-end gap-3 mt-4">
      <button onclick="closeModal('modal-inv-cancel')" class="px-4 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Back</button>
      <button onclick="confirmCancelInv()" id="inv-cancel-btn" class="px-4 py-2 bg-danger text-white small fw-semibold rounded-2 btn btn-danger btn-sm">Cancel Invoice</button>
    </div>
  </div></div></div>
</div>

{{-- View Invoice Modal --}}
<div id="modal-inv-view" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="inv-view-title">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header d-flex align-items-center justify-content-between px-4 border-light">
      <h2 class="fs-6 fw-semibold text-dark" id="inv-view-title">Invoice Details</h2>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
    </div>
    <div class="modal-body px-4 overflow-auto" id="inv-view-body"></div>
  </div></div></div>
</div>

@push('scripts')
<script>
let invTable = null, invCancelUuid = null;
let invTrips = [];
const invHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function initInvoicesTable() {
  invTable = window.initDataTable('invoices-dt', @json(route('invoices.getData')), [
      { data: 'invoice_number', render: v => `<span class="font-monospace fw-medium text-primary">${invHtml(v)}</span>` },
      { data: 'invoice_date',   render: v => fmt.date(v) },
      { data: 'customer', orderable: false, searchable: false, render: v => v ? invHtml(v.company_name || v.name) : '—' },
      { data: 'source',         render: v => badge(v, v==='travels'?'blue':v==='carwash'?'cyan':'purple') },
      { data: 'total_amount',   className: 'text-end', render: v => `<span class="fw-semibold">${fmt.currency(v)}</span>` },
      { data: 'amount_paid',    className: 'text-end', render: v => `<span class="text-success">${fmt.currency(v)}</span>` },
      { data: 'balance_due',    className: 'text-end', render: v => `<span class="text-danger">${fmt.currency(v)}</span>` },
      { data: 'status',         render: v => badge(v, window.invoiceStatusColor(v)) },
      { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], function() {
      return {
        customer_id: document.getElementById('inv-filter-customer').value,
        status:      document.getElementById('inv-filter-status').value,
        source:      document.getElementById('inv-filter-source').value,
        date_from:   document.getElementById('inv-date-from').value,
        date_to:     document.getElementById('inv-date-to').value,
      };
    },
    {
      order: [[1, 'desc']],
      drawCallback: function(s) {
        const n = s.fnRecordsTotal();
        document.getElementById('inv-count').textContent = n + ' invoice' + (n !== 1 ? 's' : '');
      },
    }
  );
}

function buildLineItem(item, idx, showLabels) {
  const v = k => item ? (item[k] ?? '') : (k === 'quantity' ? '1' : k === 'discount' || k === 'tax_rate' ? '0' : '');
  const lbl = showLabels;
  return `<div class="row g-2 align-items-end bg-light rounded p-2" id="inv-line-${idx}">
    <input type="hidden" id="inv_vehicle_type_${idx}" value="${invHtml(v('vehicle_type'))}">
    <input type="hidden" id="inv_route_${idx}" value="${invHtml(v('route'))}">
    <input type="hidden" id="inv_service_days_${idx}" value="${invHtml(v('service_days'))}">
    <div class="col-12 col-sm-3">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Description *</label>' : ''}
      <input id="inv_desc_${idx}" value="${invHtml(v('description'))}" placeholder="Service description"
        class="form-control form-control-sm" />
      <p id="err_inv_items_${idx}_description" class="field-error small text-danger mt-1"></p>
    </div>
    <div class="col-6 col-sm-2">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Source</label>' : ''}
      <select id="inv_src_${idx}" class="form-select form-select-sm">
        <option value="travels" ${v('source')==='travels'?'selected':''}>Travels</option>
        <option value="carwash" ${v('source')==='carwash'?'selected':''}>Car Wash</option>
      </select>
    </div>
    <div class="col-6 col-sm-1">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Qty</label>' : ''}
      <input id="inv_qty_${idx}" type="number" value="${invHtml(v('quantity'))}" min="0.01" step="0.01"
        oninput="updateInvTotal()" class="form-control form-control-sm" />
    </div>
    <div class="col-4 col-sm-2">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Rate (₹)</label>' : ''}
      <input id="inv_price_${idx}" type="number" value="${invHtml(v('unit_price'))}" min="0" step="0.01"
        oninput="updateInvTotal()" class="form-control form-control-sm" />
    </div>
    <div class="col-4 col-sm-1">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Disc</label>' : ''}
      <input id="inv_disc_${idx}" type="number" value="${invHtml(v('discount'))}" min="0" step="0.01"
        oninput="updateInvTotal()" class="form-control form-control-sm" />
    </div>
    <div class="col-4 col-sm-1">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">GST%</label>' : ''}
      <input id="inv_tax_${idx}" type="number" value="${invHtml(v('tax_rate'))}" min="0" max="100" step="0.01"
        oninput="updateInvTotal()" class="form-control form-control-sm" />
    </div>
    <div class="col-6 col-sm-1 text-end">
      ${lbl ? '<p class="small text-muted mb-1">Total</p>' : ''}
      <p class="small fw-semibold text-dark py-2" id="inv_line_total_${idx}">₹0.00</p>
    </div>
    <div class="col-6 col-sm-1 d-flex justify-content-end align-items-end pb-1">
      <button type="button" onclick="removeInvLine(${idx})" class="text-danger fs-5 fw-bold btn btn-link btn-sm">×</button>
    </div>
  </div>`;
}

let invLineCount = 0;

function updateInvTotal() {
  let grand = 0;
  for (let i = 0; i < invLineCount; i++) {
    const el = document.getElementById('inv-line-' + i);
    if (!el) continue;
    const qty   = parseFloat(document.getElementById('inv_qty_'  + i)?.value) || 0;
    const price = parseFloat(document.getElementById('inv_price_'+ i)?.value) || 0;
    const disc  = parseFloat(document.getElementById('inv_disc_' + i)?.value) || 0;
    const tax   = parseFloat(document.getElementById('inv_tax_'  + i)?.value) || 0;
    const taxable = Math.max(0, qty * price - disc);
    const total   = taxable + Math.round(taxable * tax) / 100;
    grand += total;
    const tEl = document.getElementById('inv_line_total_' + i);
    if (tEl) tEl.textContent = fmt.currency(total);
  }
  const gt = document.getElementById('inv-grand-total');
  if (gt) gt.textContent = fmt.currency(grand);
}

function removeInvLine(idx) {
  const el = document.getElementById('inv-line-' + idx);
  if (el) el.remove();
  updateInvTotal();
}

function addInvLine(item) {
  const idx = invLineCount++;
  const wrap = document.getElementById('inv-lines-wrap');
  const div  = document.createElement('div');
  div.innerHTML = buildLineItem(item, idx, idx === 0);
  wrap.appendChild(div.firstElementChild);
  updateInvTotal();
}

async function openInvModal(uuid) {
  invLineCount = 0;
  try {
    const url = @json(route('invoices.addEdit')) + (uuid ? `/${encodeURIComponent(uuid)}` : '');
    const response = await window.loadAddEditModal(url);
    const inv = response.invoice;
    const items = inv?.items || [];
    invTrips = [];
    if (items.length) items.forEach(item => addInvLine(item));
    else addInvLine(null);
    updateInvTotal();
  } catch (error) {
    toast.error(error.message || 'Unable to load invoice form');
  }
}

function onInvCustomerChange() {
  loadInvTrips();
}

function selectedInvTripIds() {
  return Array.from(document.querySelectorAll('input[name="trip_ids"]:checked')).map(input => Number(input.value));
}

function updateInvTripSummary() {
  const selected = invTrips.filter(trip => selectedInvTripIds().includes(Number(trip.id)));
  const manualItems = document.getElementById('inv-manual-items');
  if (manualItems) manualItems.classList.toggle('d-none', selected.length > 0);

  const groups = new Map();
  selected.forEach(trip => {
    const vehicleType = trip.vehicle?.type || trip.vehicle?.name || 'Vehicle';
    const route = trip.route || '';
    const key = `${trip.vehicle_id}|${route.trim().toLocaleLowerCase()}`;
    const group = groups.get(key) || {vehicleType, route, days:0, amount:0};
    group.days += 1;
    group.amount += Number(trip.total_amount || 0);
    groups.set(key, group);
  });
  const summary = document.getElementById('inv-trip-summary');
  if (!summary) return;
  summary.innerHTML = groups.size ? `
    <p class="small fw-semibold mb-2">${selected.length} trip record(s) selected</p>
    <div class="table-responsive"><table class="table table-sm table-bordered mb-1">
      <thead><tr><th>Vehicle type</th><th>Route</th><th class="text-end">Days</th><th class="text-end">Amount</th></tr></thead>
      <tbody>${Array.from(groups.values()).map(group => `<tr><td>${invHtml(group.vehicleType)}</td><td>${invHtml(group.route || '—')}</td><td class="text-end">${group.days}</td><td class="text-end">${fmt.currency(group.amount)}</td></tr>`).join('')}</tbody>
      <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">${fmt.currency(selected.reduce((sum, trip) => sum + Number(trip.total_amount || 0), 0))}</th></tr></tfoot>
    </table></div>` : '';
}

async function loadInvTrips() {
  const customerId = document.getElementById('inp_inv_customer_id')?.value;
  const wrap = document.getElementById('inv-trips-list');
  const status = document.getElementById('inv-trip-status');
  if (!wrap || !status) return;
  invTrips = [];
  document.getElementById('inv-trip-summary').innerHTML = '';
  if (!customerId) {
    wrap.innerHTML = '';
    status.textContent = 'Select a customer to load trips.';
    updateInvTripSummary();
    return;
  }

  const dateFrom = document.getElementById('inv-trip-date-from').value;
  const dateTo = document.getElementById('inv-trip-date-to').value;
  status.textContent = 'Loading trips…';
  wrap.innerHTML = '';
  try {
    const response = await window.api.get('/invoices/eligible-trips', {
      customer_id: customerId,
      date_from: dateFrom,
      date_to: dateTo,
    });
    if (!response.ok) {
      const errors = await extractErrors(response);
      status.textContent = errors._ || 'Unable to load trips for this date range.';
      return;
    }
    invTrips = await response.json();
    status.textContent = invTrips.length ? `${invTrips.length} completed trip(s) available.` : 'No completed, uninvoiced trips found for this customer and date range.';
    wrap.innerHTML = invTrips.length ? `
      <label class="small d-inline-flex align-items-center gap-2 mb-2">
        <input type="checkbox" class="form-check-input" onchange="document.querySelectorAll('input[name=trip_ids]').forEach(input => input.checked = this.checked);updateInvTripSummary()">
        Select all
      </label>
      <div class="border rounded-2 overflow-auto">
        ${invTrips.map(trip => `<label class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 small border-bottom mb-0">
          <input type="checkbox" name="trip_ids" value="${invHtml(trip.id)}" class="form-check-input" onchange="updateInvTripSummary()">
          <span class="font-monospace text-primary">${invHtml(trip.trip_number)}</span>
          <span>${invHtml(fmt.date(trip.trip_date))}</span>
          <span>${invHtml(trip.vehicle?.type || trip.vehicle?.name || 'Vehicle')}</span>
          <span>${invHtml(trip.pickup_location || '—')} → ${invHtml(trip.drop_location || '—')}</span>
          ${trip.route ? `<span class="text-muted">Route: ${invHtml(trip.route)}</span>` : ''}
          <span class="ms-auto fw-semibold">${fmt.currency(trip.total_amount)}</span>
        </label>`).join('')}
      </div>` : '';
    updateInvTripSummary();
  } catch (error) {
    status.textContent = 'Unable to load trips. Please try again.';
    toast.error(error.message || 'Unable to load trips');
  }
}

async function saveInvoice() {
  const form = document.getElementById('invoice-form');
  if (!form.reportValidity()) return;
  const btn  = document.getElementById('inv-save-btn');
  const uuid = document.getElementById('inv-edit-uuid')?.value;
  btn.disabled = true; btn.textContent = 'Saving…';
  document.querySelectorAll('.field-error').forEach(el => el.textContent = '');

  const items = [];
  for (let i = 0; i < invLineCount; i++) {
    const el = document.getElementById('inv-line-' + i);
    if (!el) continue;
    items.push({
      description: document.getElementById('inv_desc_'  + i)?.value || '',
      source:      document.getElementById('inv_src_'   + i)?.value || 'travels',
      quantity:    parseFloat(document.getElementById('inv_qty_'   + i)?.value) || 1,
      unit_price:  parseFloat(document.getElementById('inv_price_' + i)?.value) || 0,
      discount:    parseFloat(document.getElementById('inv_disc_'  + i)?.value) || 0,
      tax_rate:    parseFloat(document.getElementById('inv_tax_'   + i)?.value) || 0,
      vehicle_type: document.getElementById('inv_vehicle_type_' + i)?.value || null,
      route:         document.getElementById('inv_route_' + i)?.value || null,
      service_days:  parseInt(document.getElementById('inv_service_days_' + i)?.value) || null,
    });
  }

  const payload = {
    invoice_date:    document.getElementById('inp_inv_invoice_date')?.value,
    due_date:        document.getElementById('inp_inv_due_date')?.value || null,
    place_of_supply: document.getElementById('inp_inv_place_of_supply')?.value || null,
    payment_terms:   document.getElementById('inp_inv_payment_terms')?.value || null,
    notes:           document.getElementById('inp_inv_notes')?.value || null,
    items: selectedInvTripIds().length ? [] : items,
  };

  if (!uuid) {
    payload.customer_id = parseInt(document.getElementById('inp_inv_customer_id')?.value) || null;
    payload.source      = document.getElementById('inp_inv_source')?.value || 'travels';
    payload.trip_ids = selectedInvTripIds();
    if (payload.trip_ids.length) payload.source = 'travels';
    payload.trip_date_from = document.getElementById('inv-trip-date-from')?.value || null;
    payload.trip_date_to = document.getElementById('inv-trip-date-to')?.value || null;
  }

  try {
    const res = uuid
      ? await window.api.put(form.action, payload)
      : await window.api.post(form.action, payload);
    if (res.ok) {
      toast.success(uuid ? 'Invoice updated' : 'Invoice created');
      closeModal('ajax-form-modal');
      if (invTable) invTable.ajax.reload(null, false);
    } else {
      const errs = await extractErrors(res);
      showErrors(errs, 'inv');
    }
  } catch { toast.error('Save failed'); }
  finally { btn.disabled = false; btn.textContent = uuid ? 'Update Invoice' : 'Create Invoice'; }
}

function cancelInvoice(uuid, number) {
  invCancelUuid = uuid;
  document.getElementById('inv-cancel-msg').textContent =
    'Cancel invoice ' + number + '? Linked trips will be unlinked.';
  document.getElementById('inv-cancel-reason').value = '';
  openModal('modal-inv-cancel');
}

async function confirmCancelInv() {
  const reason = document.getElementById('inv-cancel-reason').value.trim();
  if (!reason) { toast.error('Please enter a cancellation reason'); return; }
  const btn = document.getElementById('inv-cancel-btn');
  btn.disabled = true; btn.textContent = 'Cancelling…';
  try {
    const res = await window.api.post('/invoices/' + invCancelUuid + '/cancel', { reason });
    if (res.ok) {
      toast.success('Invoice cancelled');
      closeModal('modal-inv-cancel');
      if (invTable) invTable.ajax.reload(null, false);
    } else {
      const errs = await extractErrors(res);
      toast.error(errs._ || 'Cancel failed');
    }
  } catch { toast.error('Cancel failed'); }
  finally { btn.disabled = false; btn.textContent = 'Cancel Invoice'; }
}

async function viewInvoice(uuid) {
  document.getElementById('inv-view-title').textContent = 'Invoice Details';
  document.getElementById('inv-view-body').innerHTML = '<div class="py-5 text-center text-secondary">Loading...</div>';
  openModal('modal-inv-view');
  try {
    const res = await window.api.get('/invoices/' + uuid);
    const inv = await res.json();
    document.getElementById('inv-view-title').textContent = 'Invoice: ' + inv.invoice_number;
    const sc = window.invoiceStatusColor(inv.status);
    const canEdit   = inv.status !== 'paid' && inv.status !== 'cancelled';
    const canCancel = inv.status !== 'cancelled' && inv.status !== 'paid';
    const itemRows = (inv.items || []).map(it => `<tr >
      <td class="px-3 py-2">${invHtml(it.vehicle_type || it.description)}${it.route ? `<div class="small text-muted">Route: ${invHtml(it.route)}</div>` : ''}</td>
      <td class="px-3 py-2 text-end">${it.service_days ?? it.quantity}</td>
      <td class="px-3 py-2 text-end">${fmt.currency(it.unit_price)}</td>
      <td class="px-3 py-2 text-end">${fmt.currency(it.discount)}</td>
      <td class="px-3 py-2 text-end">${it.tax_rate}%</td>
      <td class="px-3 py-2 text-end fw-medium">${fmt.currency(it.total_amount)}</td>
    </tr>`).join('');
    document.getElementById('inv-view-body').innerHTML = `
      <div class="d-flex flex-column gap-4 small">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-sm-3 gap-3">
          <div><p class="small text-muted">Customer</p><p class="fw-medium mt-1">${invHtml(inv.customer_name)}</p></div>
          <div><p class="small text-muted">Invoice Date</p><p class="fw-medium mt-1">${fmt.date(inv.invoice_date)}</p></div>
          <div><p class="small text-muted">Due Date</p><p class="fw-medium mt-1">${inv.due_date ? fmt.date(inv.due_date) : '—'}</p></div>
          <div><p class="small text-muted">Status</p><div class="mt-1">${badge(invHtml(inv.status), sc)}</div></div>
          <div><p class="small text-muted">Source</p><p class="fw-medium mt-1 capitalize">${invHtml(inv.source)}</p></div>
          ${inv.place_of_supply ? `<div><p class="small text-muted">Place of Supply</p><p class="fw-medium mt-1">${invHtml(inv.place_of_supply)}</p></div>` : ''}
          ${inv.payment_terms   ? `<div><p class="small text-muted">Payment Terms</p><p class="fw-medium mt-1">${invHtml(inv.payment_terms)}</p></div>` : ''}
          ${inv.customer_gstin  ? `<div><p class="small text-muted">Customer GSTIN</p><p class="font-monospace small fw-medium mt-1">${invHtml(inv.customer_gstin)}</p></div>` : ''}
        </div>
        ${itemRows ? `<table class="small border border-secondary-subtle rounded-2 overflow-hidden table table-hover align-middle mb-0">
          <thead class="bg-light"><tr>
            <th class="text-start px-3 py-2">Vehicle / Description</th>
            <th class="text-end px-3 py-2">Days / Qty</th>
            <th class="text-end px-3 py-2">Rate</th>
            <th class="text-end px-3 py-2">Disc</th>
            <th class="text-end px-3 py-2">GST%</th>
            <th class="text-end px-3 py-2">Total</th>
          </tr></thead>
          <tbody >${itemRows}</tbody>
        </table>` : ''}
        <div class="bg-light rounded-2 p-3 d-flex flex-column gap-1.5 small">
          <div class="d-flex justify-content-between"><span class="text-muted">Subtotal</span><span class="fw-medium">${fmt.currency(inv.subtotal)}</span></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Discount</span><span class="fw-medium">- ${fmt.currency(inv.discount_total)}</span></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Taxable Amount</span><span class="fw-medium">${fmt.currency(inv.taxable_amount)}</span></div>
          ${parseFloat(inv.cgst_amount) > 0 ? `<div class="d-flex justify-content-between"><span class="text-muted">CGST</span><span class="fw-medium">${fmt.currency(inv.cgst_amount)}</span></div>` : ''}
          ${parseFloat(inv.sgst_amount) > 0 ? `<div class="d-flex justify-content-between"><span class="text-muted">SGST</span><span class="fw-medium">${fmt.currency(inv.sgst_amount)}</span></div>` : ''}
          ${parseFloat(inv.igst_amount) > 0 ? `<div class="d-flex justify-content-between"><span class="text-muted">IGST</span><span class="fw-medium">${fmt.currency(inv.igst_amount)}</span></div>` : ''}
          <div class="d-flex justify-content-between border-top border-secondary-subtle pt-1.5 mt-1">
            <span class="fw-semibold text-body">Total</span>
            <span class="fw-bold text-primary small">${fmt.currency(inv.total_amount)}</span>
          </div>
          <div class="d-flex justify-content-between"><span class="text-muted">Amount Paid</span><span class="fw-medium text-success">${fmt.currency(inv.amount_paid)}</span></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Balance Due</span><span class="fw-bold text-danger">${fmt.currency(inv.balance_due)}</span></div>
        </div>
        ${inv.notes ? `<p class="small text-secondary bg-warning-subtle rounded-1 px-3 py-2">Notes: ${invHtml(inv.notes)}</p>` : ''}
        ${inv.cancellation_reason ? `<p class="small text-danger bg-danger-subtle rounded-1 px-3 py-2">Cancellation reason: ${invHtml(inv.cancellation_reason)}</p>` : ''}
        <div class="d-flex justify-content-between align-items-center pt-2 border-top gap-3">
          <div class="d-flex gap-2">
            ${canEdit   ? `<button type="button" data-action="invoices.modal-edit" data-invoice-uuid="${invHtml(inv.uuid)}" class="px-3 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Edit</button>` : ''}
            ${canCancel ? `<button type="button" data-action="invoices.modal-cancel" data-invoice-uuid="${invHtml(inv.uuid)}" data-invoice-number="${invHtml(inv.invoice_number)}" class="px-3 py-2 rounded-2 bg-danger text-white small fw-semibold btn btn-danger btn-sm">Cancel</button>` : ''}
          </div>
          <div class="d-flex gap-2">
            <button type="button" data-action="invoices.modal-pdf" data-invoice-uuid="${invHtml(inv.uuid)}" class="px-3 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">View PDF</button>
            <button onclick="closeModal('modal-inv-view')" class="px-3 py-2 rounded-2 bg-primary text-white small fw-semibold btn btn-primary btn-sm">Close</button>
          </div>
        </div>
      </div>`;
  } catch { toast.error('Failed to load invoice'); }
}

// Handle ?action=new from quick-action menu
(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('action') === 'new') {
    history.replaceState(null, '', window.location.pathname);
    openInvModal();
  }
})();

initInvoicesTable();

document.addEventListener('click', event => {
  const button = event.target.closest('[data-action^="invoices."]');
  if (!button) return;

  switch (button.dataset.action) {
    case 'invoices.add':
      openInvModal();
      break;
    case 'invoices.view':
      viewInvoice(button.dataset.invoiceUuid);
      break;
    case 'invoices.pdf':
      openPdfUrl(button.dataset.pdfUrl);
      break;
    case 'invoices.edit':
      openInvModal(button.dataset.invoiceUuid);
      break;
    case 'invoices.cancel':
      cancelInvoice(button.dataset.invoiceUuid, button.dataset.invoiceNumber);
      break;
    case 'invoices.modal-edit':
      closeModal('modal-inv-view');
      openInvModal(button.dataset.invoiceUuid);
      break;
    case 'invoices.modal-cancel':
      closeModal('modal-inv-view');
      cancelInvoice(button.dataset.invoiceUuid, button.dataset.invoiceNumber);
      break;
    case 'invoices.modal-pdf':
      openPdfUrl('/invoices/' + encodeURIComponent(button.dataset.invoiceUuid) + '/pdf');
      break;
  }
});
</script>
@endpush
@endsection
