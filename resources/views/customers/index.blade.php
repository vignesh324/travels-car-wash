@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<div >
  <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <p class="text-uppercase small fw-semibold text-secondary">Customer records</p>
      <h2 class="h2 fw-bold mb-1">Customers</h2>
      <p class="small text-muted mb-0" id="cust-count" aria-live="polite"></p>
    </div>
    <x-action-button action="customers.add" label="Add Customer" size="" class="px-4 py-2 fw-semibold rounded-2" />
  </header>

  <section class="card mb-3" aria-labelledby="customer-filter-title">
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-sliders2" aria-hidden="true"></i>
        <h3 id="customer-filter-title" class="small fw-semibold mb-0">Customer filters</h3>
      </div>
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-lg-4">
          <label for="cust-type" class="form-label">Customer type</label>
          <select id="cust-type" data-ajax-table="customers-dt" class="form-select">
            <option value="">All types</option>
            <option value="individual">Individual</option>
            <option value="corporate">Corporate</option>
            <option value="walkin">Walk-in</option>
          </select>
        </div>
      </div>
    </div>
  </section>

  <div class="card overflow-hidden">
    <div class="table-responsive p-2">
      <table id="customers-dt" class="table table-hover align-middle mb-0 small w-100">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Phone</th>
            <th>GSTIN</th>
            <th>State</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
      </table>
    </div>
    <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Tap + on a row to see all details and actions</p>
  </div>
</div>

{{-- Delete Confirm --}}
<div id="modal-cust-delete" class="modal fade" tabindex="-1" aria-hidden="true" aria-label="Confirm action" >
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm"><div class="modal-content p-4">
    <h3 class="fs-6 fw-semibold text-dark mb-2">Delete Customer</h3>
    <p class="small text-secondary mb-4" id="cust-delete-msg"></p>
    <div class="d-flex justify-content-end gap-3">
      <button onclick="closeModal('modal-cust-delete')" class="px-4 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Cancel</button>
      <button onclick="confirmDeleteCust()" id="cust-del-btn" class="px-4 py-2 bg-danger text-white small fw-semibold rounded-2 btn btn-danger btn-sm">Delete</button>
    </div>
  </div></div></div>
</div>

{{-- Statement Modal --}}
<div id="modal-statement" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="stmt-title">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header d-flex align-items-center justify-content-between px-4 border-light">
      <h2 class="fs-6 fw-semibold text-dark" id="stmt-title">Customer Statement</h2>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
    </div>
    <div class="modal-body px-4 overflow-auto" id="stmt-body"></div>
  </div></div></div>
</div>

@push('scripts')
<script>
let custTable = null, custDeleteUuid = null;
const custHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function initCustomersTable() {
  custTable = window.initDataTable('customers-dt', @json(route('customers.getData')), [
      { data: 'name', render: (v, _, row) => {
          const name = row.type === 'corporate' && row.company_name ? row.company_name : v;
          return `<div class="fw-medium">${custHtml(name)}</div>${row.type==='corporate'&&row.contact_person?`<small class="text-muted">${custHtml(row.contact_person)}</small>`:''}` ;
        }
      },
      { data: 'type', render: v => badge(v, v==='corporate'?'blue':v==='walkin'?'yellow':'gray') },
      { data: 'phone',  render: v => v ? custHtml(v) : '—' },
      { data: 'gstin',  render: v => v ? `<span class="font-monospace small">${custHtml(v)}</span>` : '—' },
      { data: 'state',  render: v => v ? custHtml(v) : '—' },
      { data: 'is_active', render: v => badge(v ? 'Active' : 'Inactive', v ? 'green' : 'gray') },
      { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], function() {
      return {
        type: document.getElementById('cust-type').value,
      };
    },
    {
      order: [[0, 'asc']],
      drawCallback: function(s) {
        const n = s.fnRecordsTotal();
        document.getElementById('cust-count').textContent = n + ' customer' + (n !== 1 ? 's' : '');
      },
    }
  );
}

function toggleCorpFields() {
  const isCorp = document.getElementById('inp_cust_type').value === 'corporate';
  ['cust-corp-wrap','cust-contact-wrap','cust-corp-extra','cust-credit-wrap','cust-billing-wrap'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('d-none', !isCorp);
  });
  const nl = document.getElementById('cust-name-label');
  if (nl) nl.textContent = (isCorp ? 'Contact Person Name' : 'Full Name') + ' *';
}

async function openCustModal(uuid) {
  try {
    const url = @json(route('customers.addEdit')) + (uuid ? `/${encodeURIComponent(uuid)}` : '');
    await window.loadAddEditModal(url);
    toggleCorpFields();
  } catch (error) {
    toast.error(error.message || 'Unable to load customer form');
  }
}

async function saveCust() {
  const uuid = document.getElementById('cust-edit-uuid').value;
  const form = document.getElementById('customer-form');
  if (!form.reportValidity()) return;
  const btn  = document.getElementById('cust-save-btn');
  btn.disabled = true; btn.textContent = 'Saving…';
  document.querySelectorAll('.field-error').forEach(el => el.textContent='');

  const g = id => { const el = document.getElementById(id); return el ? el.value : null; };
  const payload = {
    type:            g('inp_cust_type'),
    name:            g('inp_cust_name'),
    company_name:    g('inp_cust_company_name') || null,
    contact_person:  g('inp_cust_contact_person') || null,
    phone:           g('inp_cust_phone') || null,
    email:           g('inp_cust_email') || null,
    gstin:           g('inp_cust_gstin') || null,
    state:           g('inp_cust_state') || null,
    state_code:      g('inp_cust_state_code') || null,
    payment_terms:   g('inp_cust_payment_terms') || null,
    credit_limit:    g('inp_cust_credit_limit') || null,
    address:         g('inp_cust_address') || null,
    billing_address: g('inp_cust_billing_address') || null,
    notes:           g('inp_cust_notes') || null,
    is_active:       document.getElementById('inp_cust_is_active').checked,
  };

  try {
    const res = uuid
      ? await window.api.put(form.action, payload)
      : await window.api.post(form.action, payload);
    if (res.ok) {
      toast.success(uuid ? 'Customer updated' : 'Customer added');
      closeModal('ajax-form-modal');
      if (custTable) custTable.ajax.reload(null, false);
    } else {
      showErrors(await extractErrors(res), 'cust');
    }
  } catch { toast.error('Save failed'); }
  finally { btn.disabled=false; btn.textContent = uuid ? 'Update Customer' : 'Add Customer'; }
}

function deleteCust(uuid, name) {
  custDeleteUuid = uuid;
  document.getElementById('cust-delete-msg').textContent = `Are you sure you want to delete "${name}"?`;
  openModal('modal-cust-delete');
}

async function confirmDeleteCust() {
  const btn = document.getElementById('cust-del-btn');
  btn.disabled=true; btn.textContent='Deleting…';
  try {
    const res = await window.api.delete('/customers/' + custDeleteUuid);
    if (res.ok) { toast.success('Customer deleted'); closeModal('modal-cust-delete'); if (custTable) custTable.ajax.reload(null, false); }
    else { toast.error('Delete failed'); }
  } catch { toast.error('Delete failed'); }
  finally { btn.disabled=false; btn.textContent='Delete'; }
}

async function openStatement(uuid, name) {
  document.getElementById('stmt-title').textContent = 'Statement: ' + name;
  document.getElementById('stmt-body').innerHTML = '<div class="py-5 text-center text-secondary">Loading...</div>';
  openModal('modal-statement');
  try {
    const res  = await window.api.get('/customers/' + uuid + '/statement');
    const data = await res.json();
    const inv  = data.invoices || [];
    const pmts = data.payments || [];
    document.getElementById('stmt-body').innerHTML = `
      <div class="row row-cols-1 row-cols-sm-2 row-cols-sm-4 gap-3 mb-4">
        <div class="p-3 bg-light rounded-2 border border-light"><p class="small text-muted fw-medium">Opening Balance</p><p class="fs-6 fw-bold text-dark mt-1">${fmt.currency(data.opening_balance||0)}</p></div>
        <div class="p-3 bg-primary-subtle rounded-2 border border-primary-subtle"><p class="small text-primary fw-medium">Total Invoiced</p><p class="fs-6 fw-bold text-primary-emphasis mt-1">${fmt.currency(data.total_invoiced||0)}</p></div>
        <div class="p-3 bg-success-subtle rounded-2 border border-success-subtle"><p class="small text-success fw-medium">Total Paid</p><p class="fs-6 fw-bold text-success-emphasis mt-1">${fmt.currency(data.total_paid||0)}</p></div>
        <div class="p-3 bg-danger-subtle rounded-2 border border-danger-subtle"><p class="small text-danger fw-medium">Closing Due</p><p class="fs-6 fw-bold text-danger-emphasis mt-1">${fmt.currency(data.closing_balance||0)}</p></div>
      </div>
      ${parseFloat(data.advance_balance||0)>0?`<div class="p-3 bg-warning-subtle rounded-2 border border-warning-subtle small text-warning-emphasis mb-4"><strong>Advance Credit Available:</strong> ${fmt.currency(data.advance_balance)}</div>`:''}
      <p class="small fw-bold text-uppercase text-muted mb-2">Invoices</p>
      <div class="overflow-auto border border-secondary-subtle rounded-2 mb-4">
        <table class="small table table-hover align-middle mb-0"><thead class="bg-light"><tr>
          <th class="px-3 py-2 text-start fw-semibold">Invoice #</th>
          <th class="px-3 py-2 text-start fw-semibold">Date</th>
          <th class="px-3 py-2 text-end fw-semibold">Total</th>
          <th class="px-3 py-2 text-end fw-semibold">Balance</th>
          <th class="px-3 py-2 text-center fw-semibold">Status</th>
        </tr></thead><tbody >
        ${inv.length===0?'<tr><td colspan="5" class="text-center py-4 text-secondary">No invoices</td></tr>':inv.map(i=>`<tr>
          <td class="px-3 py-2 font-monospace">${custHtml(i.invoice_number)}</td>
          <td class="px-3 py-2">${custHtml((i.invoice_date||'').slice(0,10))}</td>
          <td class="px-3 py-2 text-end fw-medium">${fmt.currency(i.total_amount)}</td>
          <td class="px-3 py-2 text-end fw-bold text-danger">${fmt.currency(i.balance_due)}</td>
          <td class="px-3 py-2 text-center">${badge(custHtml(i.status),i.status==='paid'?'green':i.status==='cancelled'?'red':'yellow')}</td>
        </tr>`).join('')}
        </tbody></table>
      </div>
      <p class="small fw-bold text-uppercase text-muted mb-2">Payments Received</p>
      <div class="overflow-auto border border-secondary-subtle rounded-2 mb-4">
        <table class="small table table-hover align-middle mb-0"><thead class="bg-light"><tr>
          <th class="px-3 py-2 text-start fw-semibold">Receipt #</th>
          <th class="px-3 py-2 text-start fw-semibold">Date</th>
          <th class="px-3 py-2 text-start fw-semibold">Mode</th>
          <th class="px-3 py-2 text-end fw-semibold">Amount</th>
          <th class="px-3 py-2 text-end fw-semibold">Allocated</th>
        </tr></thead><tbody >
        ${pmts.length===0?'<tr><td colspan="5" class="text-center py-4 text-secondary">No payments</td></tr>':pmts.map(p=>`<tr>
          <td class="px-3 py-2 font-monospace">${custHtml(p.payment_number)}</td>
          <td class="px-3 py-2">${custHtml((p.payment_date||'').slice(0,10))}</td>
          <td class="px-3 py-2">${custHtml(p.payment_method)}</td>
          <td class="px-3 py-2 text-end fw-semibold text-success">${fmt.currency(p.amount)}</td>
          <td class="px-3 py-2 text-end text-body">${fmt.currency(p.allocated_amount)}</td>
        </tr>`).join('')}
        </tbody></table>
      </div>
      <div class="d-flex justify-content-end pt-3 border-top">
        <button onclick="closeModal('modal-statement')" class="px-4 py-2 rounded-2 border border-secondary-subtle small fw-medium text-body btn btn-outline-secondary btn-sm">Close</button>
      </div>`;
  } catch {
    document.getElementById('stmt-body').innerHTML = '<div class="py-4 text-center text-danger">Failed to load statement</div>';
  }
}

initCustomersTable();

document.addEventListener('click', event => {
  const button = event.target.closest('[data-action^="customers."]');
  if (!button) return;

  switch (button.dataset.action) {
    case 'customers.add':
      openCustModal();
      break;
    case 'customers.view':
      window.openRecordView({
        url: button.dataset.viewUrl,
        title: 'Customer details',
        fields: [
          { label: 'Name', value: record => record.company_name || record.name },
          { label: 'Type', value: record => ({ individual: 'Individual', corporate: 'Corporate', walkin: 'Walk-in' })[record.type] || record.type },
          { label: 'Contact person', value: 'contact_person' },
          { label: 'Phone', value: 'phone' },
          { label: 'Email', value: 'email' },
          { label: 'Address', value: 'address', fullWidth: true },
          { label: 'Billing address', value: 'billing_address', fullWidth: true },
          { label: 'GSTIN', value: 'gstin' },
          { label: 'State', value: record => [record.state, record.state_code].filter(Boolean).join(' · ') },
          { label: 'Payment terms', value: 'payment_terms' },
          { label: 'Credit limit', value: record => record.credit_limit == null ? null : fmt.currency(record.credit_limit) },
          { label: 'Status', value: record => record.is_active ? 'Active' : 'Inactive' },
          { label: 'Notes', value: 'notes', fullWidth: true },
        ],
      });
      break;
    case 'customers.statement':
      openStatement(button.dataset.customerUuid, button.dataset.customerName);
      break;
    case 'customers.edit':
      openCustModal(button.dataset.customerUuid);
      break;
    case 'customers.delete':
      deleteCust(button.dataset.customerUuid, button.dataset.customerName);
      break;
  }
});
</script>
@endpush
@endsection
