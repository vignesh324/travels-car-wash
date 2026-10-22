@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div >
  <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <p class="text-uppercase small fw-semibold text-secondary">Cash flow</p>
      <h2 class="h2 fw-bold mb-1">Payments</h2>
      <p class="small text-muted mb-0" id="pmt-count" aria-live="polite"></p>
    </div>
    <x-action-button action="payments.add" label="Record Payment" size="" class="px-4 py-2 fw-semibold rounded-2" />
  </header>

  <section class="card mb-4" aria-labelledby="payment-filter-title">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-sliders2" aria-hidden="true"></i>
        <h3 id="payment-filter-title" class="small fw-semibold mb-0">Payment filters</h3>
      </div>
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
          <label for="pmt-filter-customer" class="form-label">Customer</label>
          <select id="pmt-filter-customer" data-ajax-table="payments-dt" class="form-select">
            <option value="">All customers</option>
            @foreach($customers as $customer)
              <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="pmt-filter-method" class="form-label">Payment method</label>
          <select id="pmt-filter-method" data-ajax-table="payments-dt" class="form-select">
            <option value="">All methods</option>
            <option>Cash</option><option>UPI</option><option>Bank Transfer</option><option>Card</option><option>Other</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="pmt-filter-status" class="form-label">Status</label>
          <select id="pmt-filter-status" data-ajax-table="payments-dt" class="form-select">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="reversed">Reversed</option>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="pmt-date-from" class="form-label">From</label>
          <input type="date" id="pmt-date-from" data-ajax-table="payments-dt" class="form-control" />
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
          <label for="pmt-date-to" class="form-label">To</label>
          <input type="date" id="pmt-date-to" data-ajax-table="payments-dt" class="form-control" />
        </div>
      </div>
    </div>
  </section>

  <div class="card overflow-hidden">
    <div class="table-responsive p-2" tabindex="0" aria-label="Payments table. Expand a row to view all details and actions.">
      <table id="payments-dt" class="table table-hover align-middle mb-0 small w-100">
        <thead class="table-light">
          <tr>
            <th>Receipt #</th><th>Date</th><th>Customer</th><th>Method</th>
            <th class="text-end">Amount</th><th class="text-end">Unallocated</th>
            <th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
      </table>
    </div>
    <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Tap + on a row to see all details and actions</p>
  </div>
</div>

{{-- Reverse Payment Modal --}}
<div id="modal-pmt-reverse" class="modal fade" tabindex="-1" aria-hidden="true" aria-label="Confirm action" >
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm"><div class="modal-content p-4">
    <h3 class="fs-6 fw-semibold text-dark mb-2">Reverse Payment</h3>
    <p class="small text-secondary mb-4" id="pmt-reverse-msg"></p>
    <label class="d-block small fw-medium text-body mb-1 form-label" for="pmt-reverse-reason">Reason *</label>
    <textarea id="pmt-reverse-reason" rows="3"
      class="w-100 rounded-2 border border-secondary-subtle px-3 py-2 small form-control"
      placeholder="Reason for reversal..."></textarea>
    <div class="d-flex justify-content-end gap-3 mt-4">
      <button onclick="closeModal('modal-pmt-reverse')" class="px-4 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Back</button>
      <button onclick="confirmReversePayment()" id="pmt-reverse-btn" class="px-4 py-2 bg-danger text-white small fw-semibold rounded-2 btn btn-danger btn-sm">Reverse Payment</button>
    </div>
  </div></div></div>
</div>

{{-- View Payment Modal --}}
<div id="modal-pmt-view" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="pmt-view-title">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header d-flex align-items-center justify-content-between px-4 border-light">
      <h2 class="fs-6 fw-semibold text-dark" id="pmt-view-title">Payment Details</h2>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
    </div>
    <div class="modal-body px-4 overflow-auto" id="pmt-view-body"></div>
  </div></div></div>
</div>

@push('scripts')
<script>
let pmtTable = null, pmtReverseUuid = null;
let pmtInvoiceOptions = [], pmtOutstandingInvoices = [];
const pmtHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function initPaymentsTable() {
  pmtTable = window.initDataTable('payments-dt', @json(route('payments.getData')), [
      { data: 'payment_number', render: v => `<span class="font-monospace fw-medium text-success">${pmtHtml(v)}</span>` },
      { data: 'payment_date',   render: v => fmt.date(v) },
      { data: 'customer', orderable: false, searchable: false, render: v => v ? pmtHtml(v.company_name || v.name) : '—' },
      { data: 'payment_method', render: v => pmtHtml(v) },
      { data: 'amount',             className: 'text-end', render: v => `<span class="fw-semibold">${fmt.currency(v)}</span>` },
      { data: 'unallocated_amount', className: 'text-end', render: v => `<span class="text-warning-emphasis">${fmt.currency(v)}</span>` },
      { data: 'status', render: v => badge(v, v==='active'?'green':v==='reversed'?'red':'gray') },
      { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], function() {
      return {
        customer_id:     document.getElementById('pmt-filter-customer').value,
        payment_method:  document.getElementById('pmt-filter-method').value,
        status:          document.getElementById('pmt-filter-status').value,
        date_from:       document.getElementById('pmt-date-from').value,
        date_to:         document.getElementById('pmt-date-to').value,
      };
    },
    {
      order: [[1, 'desc']],
      drawCallback: function(s) {
        const n = s.fnRecordsTotal();
        document.getElementById('pmt-count').textContent = n + ' payment' + (n !== 1 ? 's' : '');
      },
    }
  );
}

async function openPmtModal() {
  try {
    const form = await window.loadAddEditModal(@json(route('payments.addEdit')));
    pmtInvoiceOptions = form.outstandingInvoices || [];
    pmtOutstandingInvoices = [];
    document.getElementById('inp_pmt_payment_date').value = fmt.today();
    document.getElementById('pmt-alloc-wrap').classList.add('d-none');
    document.getElementById('pmt-alloc-list').innerHTML = '';
  } catch (error) {
    toast.error(error.message || 'Unable to load payment form');
  }
}

function onPmtCustomerChange(customerId) {
  const wrap = document.getElementById('pmt-alloc-wrap');
  const list = document.getElementById('pmt-alloc-list');
  pmtOutstandingInvoices = customerId
    ? pmtInvoiceOptions.filter(invoice => Number(invoice.customer_id) === Number(customerId))
    : [];
  list.innerHTML = pmtOutstandingInvoices.map(invoice => `
    <div class="d-flex align-items-center gap-3 px-3 py-2">
      <div class="flex-grow-1 overflow-hidden">
        <p class="small font-monospace fw-medium text-primary">${invoice.invoice_number}</p>
        <p class="small text-muted">${fmt.date(invoice.invoice_date)} · Balance: ${fmt.currency(invoice.balance_due)}</p>
      </div>
      <input type="number" placeholder="0.00" id="alloc_${invoice.id}" oninput="updatePmtSummary()"
        class="w-28 rounded-2 border border-secondary-subtle px-2 py-1 small text-end form-control"
        min="0" step="0.01" max="${invoice.balance_due}" />
    </div>`).join('');
  wrap.classList.toggle('d-none', pmtOutstandingInvoices.length === 0);
}

function updatePmtSummary() {
  const total = parseFloat(document.getElementById('inp_pmt_amount').value) || 0;
  let allocated = 0;
  pmtOutstandingInvoices.forEach(inv => {
    allocated += parseFloat(document.getElementById('alloc_' + inv.id)?.value || 0) || 0;
  });
  const unalloc = Math.max(0, total - allocated);
  document.getElementById('pmt-allocated-display').textContent = fmt.currency(allocated);
  const uEl = document.getElementById('pmt-unalloc-display');
  uEl.textContent = 'Advance/Unallocated: ' + fmt.currency(unalloc);
  uEl.className = 'fw-medium ' + (unalloc > 0 ? 'text-warning-emphasis' : 'text-success');
}

async function savePayment() {
  const form = document.getElementById('payment-form');
  if (!form.reportValidity()) return;
  const btn = document.getElementById('pmt-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  document.querySelectorAll('.field-error').forEach(el => el.textContent = '');

  const allocations = pmtOutstandingInvoices
    .map(inv => ({ invoice_id: inv.id, amount: parseFloat(document.getElementById('alloc_' + inv.id)?.value || 0) || 0 }))
    .filter(a => a.amount > 0);

  const payload = {
    customer_id:           parseInt(document.getElementById('inp_pmt_customer_id').value) || null,
    payment_date:          document.getElementById('inp_pmt_payment_date').value,
    amount:                parseFloat(document.getElementById('inp_pmt_amount').value) || 0,
    payment_method:        document.getElementById('inp_pmt_method').value,
    transaction_reference: document.getElementById('inp_pmt_tx_ref').value || null,
    notes:                 document.getElementById('inp_pmt_notes').value || null,
    allocations,
  };

  try {
    const res = await window.api.post(form.action, payload);
    if (res.ok) {
      toast.success('Payment recorded');
      closeModal('ajax-form-modal');
      if (pmtTable) pmtTable.ajax.reload(null, false);
    } else {
      const errs = await extractErrors(res);
      showErrors(errs, 'pmt');
    }
  } catch { toast.error('Save failed'); }
  finally { btn.disabled = false; btn.textContent = 'Record Payment'; }
}

function reversePayment(uuid, number, amount) {
  pmtReverseUuid = uuid;
  document.getElementById('pmt-reverse-msg').textContent =
    'Reverse payment ' + number + ' of ' + fmt.currency(amount) + '? All allocations will be removed and invoice balances restored.';
  document.getElementById('pmt-reverse-reason').value = '';
  openModal('modal-pmt-reverse');
}

async function confirmReversePayment() {
  const reason = document.getElementById('pmt-reverse-reason').value.trim();
  if (!reason) { toast.error('Please enter a reason'); return; }
  const btn = document.getElementById('pmt-reverse-btn');
  btn.disabled = true; btn.textContent = 'Reversing…';
  try {
    const res = await window.api.post('/payments/' + pmtReverseUuid + '/reverse', { reason });
    if (res.ok) {
      toast.success('Payment reversed');
      closeModal('modal-pmt-reverse');
      if (pmtTable) pmtTable.ajax.reload(null, false);
    } else {
      const errs = await extractErrors(res);
      toast.error(errs._ || 'Reverse failed');
    }
  } catch { toast.error('Reverse failed'); }
  finally { btn.disabled = false; btn.textContent = 'Reverse Payment'; }
}

async function viewPayment(uuid) {
  document.getElementById('pmt-view-title').textContent = 'Payment Details';
  document.getElementById('pmt-view-body').innerHTML = '<div class="py-5 text-center text-secondary">Loading...</div>';
  openModal('modal-pmt-view');
  try {
    const res = await window.api.get('/payments/' + uuid);
    const p   = await res.json();
    document.getElementById('pmt-view-title').textContent = 'Payment: ' + p.payment_number;
    const sc = p.status === 'active' ? 'green' : p.status === 'reversed' ? 'red' : 'gray';
    const custName = p.customer ? (p.customer.company_name || p.customer.name) : '—';
    const allocRows = (p.allocations || []).map(a => `
      <div class="d-flex justify-content-between px-3 py-2 small">
        <span class="font-monospace text-primary">${pmtHtml(a.invoice ? a.invoice.invoice_number : 'Invoice')}</span>
        <span class="fw-medium">${fmt.currency(a.amount)}</span>
      </div>`).join('');
    document.getElementById('pmt-view-body').innerHTML = `
      <div class="d-flex flex-column gap-3 small">
        <div class="row row-cols-1 row-cols-sm-2 gap-3">
          <div><span class="text-muted">Date:</span> <span class="fw-medium ml-1">${fmt.date(p.payment_date)}</span></div>
          <div><span class="text-muted">Method:</span> <span class="fw-medium ml-1">${pmtHtml(p.payment_method)}</span></div>
          <div><span class="text-muted">Customer:</span> <span class="fw-medium ml-1">${pmtHtml(custName)}</span></div>
          <div><span class="text-muted">Status:</span> <span class="ml-1">${badge(pmtHtml(p.status), sc)}</span></div>
          ${p.transaction_reference ? `<div class="col-2"><span class="text-muted">Reference:</span> <span class="fw-medium ml-1">${pmtHtml(p.transaction_reference)}</span></div>` : ''}
        </div>
        <div class="bg-light rounded-2 p-3 row row-cols-1 row-cols-sm-3 gap-2 small">
          <div><p class="text-muted">Amount</p><p class="fw-bold text-primary mt-1">${fmt.currency(p.amount)}</p></div>
          <div><p class="text-muted">Allocated</p><p class="fw-semibold text-success mt-1">${fmt.currency(p.allocated_amount)}</p></div>
          <div><p class="text-muted">Advance</p><p class="fw-semibold text-warning-emphasis mt-1">${fmt.currency(p.unallocated_amount)}</p></div>
        </div>
        ${allocRows ? `<div>
          <p class="small fw-semibold text-muted text-uppercase mb-1">Applied Against</p>
          <div class="border border-secondary-subtle rounded-2">${allocRows}</div>
        </div>` : ''}
        ${p.notes ? `<p class="text-muted small">Notes: ${pmtHtml(p.notes)}</p>` : ''}
        <div class="d-flex justify-content-end gap-3 pt-2 border-top">
          <button type="button" data-action="payments.modal-receipt" data-payment-uuid="${pmtHtml(p.uuid)}" class="px-3 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Download Receipt</button>
          <button onclick="closeModal('modal-pmt-view')" class="px-3 py-2 rounded-2 bg-primary text-white small fw-semibold btn btn-primary btn-sm">Close</button>
        </div>
      </div>`;
  } catch { toast.error('Failed to load payment'); }
}

(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('action') === 'new') {
    history.replaceState(null, '', window.location.pathname);
    openPmtModal();
  }
})();

initPaymentsTable();
document.addEventListener('click', event => {
  const button = event.target.closest('[data-action^="payments."]');
  if (!button) return;

  switch (button.dataset.action) {
    case 'payments.add':
      openPmtModal();
      break;
    case 'payments.view':
      viewPayment(button.dataset.paymentUuid);
      break;
    case 'payments.receipt':
      openPdfUrl(button.dataset.receiptUrl);
      break;
    case 'payments.reverse':
      reversePayment(button.dataset.paymentUuid, button.dataset.paymentNumber, button.dataset.paymentAmount);
      break;
    case 'payments.modal-receipt':
      openPdfUrl('/payments/' + encodeURIComponent(button.dataset.paymentUuid) + '/receipt');
      break;
  }
});
</script>
@endpush
@endsection
