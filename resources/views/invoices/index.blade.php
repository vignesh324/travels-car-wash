@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
<div class="resource-page">
  <header class="resource-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <p class="resource-kicker">Billing records</p>
      <h2 class="resource-heading fw-bold mb-1">Invoices</h2>
      <p class="small text-muted mb-0" id="inv-count" aria-live="polite"></p>
    </div>
    <x-action-button action="invoices.add" label="Create Invoice" size="" class="resource-primary-action px-4 py-2 fw-semibold rounded-2" />
  </header>

  <div class="card resource-filter-card p-3 mb-4 d-flex flex-wrap gap-2">
    <select id="inv-filter-customer" data-ajax-table="invoices-dt" class="rounded-2 border border-secondary-subtle px-3 py-2 small form-select" aria-label="Inv Filter Customer" >
      <option value="">All Customers</option>
      @foreach($customers as $customer)
        <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
      @endforeach
    </select>
    <select id="inv-filter-status" data-ajax-table="invoices-dt" class="rounded-2 border border-secondary-subtle px-3 py-2 small form-select" aria-label="Inv Filter Status" >
      <option value="">All Status</option>
      <option value="issued">Issued</option>
      <option value="partial">Partial</option>
      <option value="paid">Paid</option>
      <option value="cancelled">Cancelled</option>
    </select>
    <select id="inv-filter-source" data-ajax-table="invoices-dt" class="rounded-2 border border-secondary-subtle px-3 py-2 small form-select" aria-label="Inv Filter Source" >
      <option value="">All Sources</option>
      <option value="travels">Travels</option>
      <option value="carwash">Car Wash</option>
      <option value="mixed">Mixed</option>
    </select>
    <input type="date" id="inv-date-from" data-ajax-table="invoices-dt" class="rounded-2 border border-secondary-subtle px-3 py-2 small form-control" aria-label="Inv Date From" />
    <input type="date" id="inv-date-to" data-ajax-table="invoices-dt" class="rounded-2 border border-secondary-subtle px-3 py-2 small form-control" aria-label="Inv Date To" />
  </div>

  <div class="card resource-table-card overflow-hidden">
    <div class="table-responsive resource-table-scroll p-2">
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
    <p class="resource-scroll-hint d-md-none mb-0 px-3 pb-3"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Swipe or scroll horizontally to see all columns</p>
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
let invCustomers = @json($customers), invTrips = [];
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

// ── Form builder ─────────────────────────────────────────────────────────────
function custOpts(selId) {
  return invCustomers.map(c => {
    const name = (c.type === 'corporate' && c.company_name) ? c.company_name : c.name;
    return `<option value="${invHtml(c.id)}" ${c.id == selId ? 'selected' : ''}>${invHtml(name)}</option>`;
  }).join('');
}

function buildLineItem(item, idx, showLabels) {
  const v = k => item ? (item[k] ?? '') : (k === 'quantity' ? '1' : k === 'discount' || k === 'tax_rate' ? '0' : '');
  const lbl = showLabels;
  return `<div class="row gap-2 align-items-end bg-light rounded-2 p-2" id="inv-line-${idx}">
    <div class="col-12 col-sm-4">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Description *</label>' : ''}
      <input id="inv_desc_${idx}" value="${invHtml(v('description'))}" placeholder="Service description"
        class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
      <p id="err_inv_items_${idx}_description" class="field-error small text-danger mt-1"></p>
    </div>
    <div class="col-6 col-sm-2">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Source</label>' : ''}
      <select id="inv_src_${idx}" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-select">
        <option value="travels" ${v('source')==='travels'?'selected':''}>Travels</option>
        <option value="carwash" ${v('source')==='carwash'?'selected':''}>Car Wash</option>
      </select>
    </div>
    <div class="col-6 col-sm-1">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Qty</label>' : ''}
      <input id="inv_qty_${idx}" type="number" value="${invHtml(v('quantity'))}" min="0.01" step="0.01"
        oninput="updateInvTotal()" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
    </div>
    <div class="col-4 col-sm-2">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Rate (₹)</label>' : ''}
      <input id="inv_price_${idx}" type="number" value="${invHtml(v('unit_price'))}" min="0" step="0.01"
        oninput="updateInvTotal()" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
    </div>
    <div class="col-4 col-sm-1">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">Disc</label>' : ''}
      <input id="inv_disc_${idx}" type="number" value="${invHtml(v('discount'))}" min="0" step="0.01"
        oninput="updateInvTotal()" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
    </div>
    <div class="col-4 col-sm-1">
      ${lbl ? '<label class="d-block small fw-medium text-body mb-1 form-label">GST%</label>' : ''}
      <input id="inv_tax_${idx}" type="number" value="${invHtml(v('tax_rate'))}" min="0" max="100" step="0.01"
        oninput="updateInvTotal()" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
    </div>
    <div class="col-6 col-sm-1 text-end">
      ${lbl ? '<p class="small text-muted mb-1">Total</p>' : ''}
      <p class="small fw-semibold text-dark py-2" id="inv_line_total_${idx}">₹0.00</p>
    </div>
    <div class="col-6 col-sm-1 d-flex justify-content-end align-items-end pb-1">
      <button type="button" onclick="removeInvLine(${idx})" class="text-danger fs-5 leading-none fw-bold btn btn-link btn-sm">×</button>
    </div>
  </div>`;
}

let invLineCount = 0;
let invEditUuid  = null;

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

function buildInvForm(inv, items, trips) {
  const isEdit = !!inv;
  const today  = fmt.today();
  return `
    <input type="hidden" id="inv-edit-uuid" value="${isEdit ? invHtml(inv.uuid) : ''}" />
    ${isEdit ? `
    <div class="bg-light rounded-2 px-4 py-3 d-flex flex-column gap-2">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <p class="small text-muted mb-1">Customer</p>
          <p class="small fw-medium text-dark">${invHtml(inv.customer_name)}</p>
        </div>
        ${badge(invHtml(inv.source), inv.source === 'travels' ? 'blue' : inv.source === 'carwash' ? 'cyan' : 'purple')}
      </div>
      <div class="row row-cols-1 row-cols-sm-3 gap-2 pt-1 border-top border-secondary-subtle small">
        <div><p class="text-muted">Invoice Total</p><p class="fw-semibold text-dark mt-1">${invHtml(fmt.currency(inv.total_amount))}</p></div>
        <div><p class="text-muted">Amount Paid</p><p class="fw-semibold text-success mt-1">${invHtml(fmt.currency(inv.amount_paid))}</p></div>
        <div><p class="text-muted">Balance Due</p><p class="fw-semibold ${parseFloat(inv.balance_due) > 0 ? 'text-danger' : 'text-success'} mt-1">${invHtml(fmt.currency(inv.balance_due))}</p></div>
      </div>
      ${parseFloat(inv.amount_paid) > 0 ? '<p class="small text-warning-emphasis bg-warning-subtle rounded-1 px-2 py-1">⚠ This invoice has recorded payments. Editing line items will recalculate the balance due.</p>' : ''}
    </div>` : `
    <div class="row row-cols-1 row-cols-sm-2 gap-4">
      <div>
        <label class="d-block small fw-medium text-body mb-1 form-label">Customer *</label>
        <select id="inp_inv_customer_id" onchange="onInvCustomerChange(this.value)" required
          class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-select">
          <option value="">Select customer</option>${custOpts('')}
        </select>
        <p id="err_inv_customer_id" class="field-error small text-danger mt-1"></p>
      </div>
      <div>
        <label class="d-block small fw-medium text-body mb-1 form-label" for="inp_inv_source">Source *</label>
        <select id="inp_inv_source" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-select">
          <option value="travels">Travels</option>
          <option value="carwash">Car Wash</option>
          <option value="mixed">Mixed</option>
        </select>
      </div>
    </div>`}
    <div class="row row-cols-1 row-cols-sm-2 gap-4">
      <div>
        <label class="d-block small fw-medium text-body mb-1 form-label" for="inp_inv_invoice_date">Invoice Date *</label>
        <input id="inp_inv_invoice_date" type="date" value="${invHtml(isEdit ? (inv.invoice_date||'').slice(0,10) : today)}" required
          class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
      </div>
      <div>
        <label class="d-block small fw-medium text-body mb-1 form-label" for="inp_inv_due_date">Due Date</label>
        <input id="inp_inv_due_date" type="date" value="${invHtml(isEdit ? (inv.due_date||'').slice(0,10) : '')}"
          class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
      </div>
      <div>
        <label class="d-block small fw-medium text-body mb-1 form-label" for="inp_inv_place_of_supply">Place of Supply</label>
        <input id="inp_inv_place_of_supply" value="${invHtml(isEdit ? (inv.place_of_supply||'') : '')}" placeholder="State name"
          class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
      </div>
      <div>
        <label class="d-block small fw-medium text-body mb-1 form-label" for="inp_inv_payment_terms">Payment Terms</label>
        <input id="inp_inv_payment_terms" value="${invHtml(isEdit ? (inv.payment_terms||'') : '')}" placeholder="Net 30"
          class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control" />
      </div>
    </div>
    ${!isEdit && trips && trips.length > 0 ? `
    <div>
      <p class="small fw-semibold text-muted text-uppercase mb-2">Link Trips (optional)</p>
      <div class="overflow-auto overflow-auto border border-secondary-subtle rounded-2" id="inv-trips-list">
        ${trips.map(t => `<label class="d-flex align-items-center gap-3 px-3 py-2 small form-label">
          <input type="checkbox" name="trip_ids" value="${invHtml(t.id)}" class="rounded-1 border-secondary-subtle text-primary form-check-input" />
          <span class="font-monospace small text-primary">${invHtml(t.trip_number)}</span>
          <span class="text-secondary">${invHtml(fmt.date(t.trip_date))}</span>
          <span class="ml-auto fw-semibold">${invHtml(fmt.currency(t.total_amount))}</span>
        </label>`).join('')}
      </div>
    </div>` : ''}
    <div>
      <div class="d-flex align-items-center justify-content-between mb-2">
        <p class="small fw-semibold text-muted text-uppercase">Line Items</p>
        <button type="button" onclick="addInvLine(null)" class="small text-primary fw-medium btn btn-link btn-sm">+ Add Line</button>
      </div>
      <p id="err_inv_items" class="field-error small text-danger mb-1"></p>
      <div id="inv-lines-wrap" class="d-flex flex-column gap-2"></div>
      <div class="text-end mt-2 small fw-bold text-primary">
        Grand Total (preview): <span id="inv-grand-total">₹0.00</span>
      </div>
    </div>
    <div>
      <label class="d-block small fw-medium text-body mb-1 form-label">Notes</label>
      <textarea id="inp_inv_notes" rows="2" class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control">${invHtml(isEdit ? (inv.notes||'') : '')}</textarea>
    </div>
    <div class="d-flex justify-content-end gap-3 pt-4 border-top">
      <button type="button" onclick="closeModal('ajax-form-modal')" class="px-4 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Cancel</button>
      <button onclick="saveInvoice()" id="inv-save-btn" class="px-4 py-2 bg-primary text-white small fw-semibold rounded-2 btn btn-primary btn-sm">${isEdit ? 'Update Invoice' : 'Create Invoice'}</button>
    </div>`;
}

async function openInvModal(uuid) {
  invEditUuid  = uuid || null;
  invLineCount = 0;
  try {
    const url = @json(route('invoices.addEdit')) + (uuid ? `/${encodeURIComponent(uuid)}` : '');
    const response = await window.loadAddEditModal(url);
    const inv = response.invoice;
    const items = inv?.items || [];
    const trips = response.trips || [];
    invCustomers = response.customers || invCustomers;
    invTrips = trips;
    document.getElementById('inv-form-fields').innerHTML = buildInvForm(inv, items, trips);
    if (items.length) items.forEach(item => addInvLine(item));
    else addInvLine(null);
    updateInvTotal();
  } catch (error) {
    toast.error(error.message || 'Unable to load invoice form');
  }
}

function onInvCustomerChange(customerId) {
  const wrap = document.getElementById('inv-trips-list');
  if (!wrap) return;
  const trips = customerId
    ? invTrips.filter(trip => Number(trip.customer_id) === Number(customerId))
    : invTrips;
  wrap.innerHTML = trips.length ? trips.map(trip => `<label class="d-flex align-items-center gap-3 px-3 py-2 small form-label">
    <input type="checkbox" name="trip_ids" value="${invHtml(trip.id)}" class="rounded-1 border-secondary-subtle text-primary form-check-input" />
    <span class="font-monospace small text-primary">${invHtml(trip.trip_number)}</span>
    <span class="text-secondary">${invHtml(fmt.date(trip.trip_date))}</span>
    <span class="ml-auto fw-semibold">${invHtml(fmt.currency(trip.total_amount))}</span>
  </label>`).join('') : '<p class="small text-muted px-3 py-2 mb-0">No completed trips available for this customer.</p>';
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
    });
  }

  const payload = {
    invoice_date:    document.getElementById('inp_inv_invoice_date')?.value,
    due_date:        document.getElementById('inp_inv_due_date')?.value || null,
    place_of_supply: document.getElementById('inp_inv_place_of_supply')?.value || null,
    payment_terms:   document.getElementById('inp_inv_payment_terms')?.value || null,
    notes:           document.getElementById('inp_inv_notes')?.value || null,
    items,
  };

  if (!uuid) {
    payload.customer_id = parseInt(document.getElementById('inp_inv_customer_id')?.value) || null;
    payload.source      = document.getElementById('inp_inv_source')?.value || 'travels';
    const checked = document.querySelectorAll('input[name="trip_ids"]:checked');
    payload.trip_ids = Array.from(checked).map(el => parseInt(el.value));
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
      <td class="px-3 py-2">${invHtml(it.description)}</td>
      <td class="px-3 py-2 text-end">${it.quantity}</td>
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
            <th class="text-start px-3 py-2">Description</th>
            <th class="text-end px-3 py-2">Qty</th>
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
