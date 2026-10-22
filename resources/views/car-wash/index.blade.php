@extends('layouts.app')
@section('title', 'Car Wash')

@section('content')
<div >
    <header class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <span class="text-uppercase small fw-semibold text-secondary">Wash operations</span>
            <h1 class="h2 fw-bold mb-1">Car Wash</h1>
            <p class="text-secondary small mb-0">Manage wash transactions, service pricing and walk-in billing.</p>
        </div>
        <x-action-button action="car-wash.add-transaction" label="New transaction" id="carwash-primary-action"  />
    </header>

    <nav aria-label="Car wash sections" class="mb-3">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" role="tab" id="wash-transactions-tab" aria-controls="wash-transactions-view" aria-selected="true" onclick="switchWashTab('transactions')">
                    <i class="bi bi-receipt me-2" aria-hidden="true"></i>Transactions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" role="tab" id="wash-services-tab" aria-controls="wash-services-view" aria-selected="false" onclick="switchWashTab('services')">
                    <i class="bi bi-droplet-half me-2" aria-hidden="true"></i>Services
                </button>
            </li>
        </ul>
    </nav>

    <section id="wash-transactions-view" role="tabpanel" aria-labelledby="wash-transactions-tab">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1">Transaction register</h2>
                <p class="small text-secondary mb-0">Review bills, payment status and recent wash activity.</p>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-sliders2" aria-hidden="true"></i>
                    <h3 class="small fw-semibold mb-0">Transaction filters</h3>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="wash-customer-filter" class="form-label">Customer</label>
                        <select id="wash-customer-filter" class="form-select" data-ajax-table="car-wash-transactions-dt">
                            <option value="">All customers</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label for="wash-status-filter" class="form-label">Payment status</label>
                        <select id="wash-status-filter" class="form-select" data-ajax-table="car-wash-transactions-dt">
                            <option value="">All statuses</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label for="wash-date-from" class="form-label">From</label>
                        <input class="form-control" type="date" id="wash-date-from" data-ajax-table="car-wash-transactions-dt" />
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label for="wash-date-to" class="form-label">To</label>
                        <input class="form-control" type="date" id="wash-date-to" data-ajax-table="car-wash-transactions-dt" />
                    </div>
                </div>
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center overflow-hidden">
                    <span class="text-secondary"><i class="bi bi-list-check" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="h5 mb-0">Wash transactions</h3>
                        <span class="small text-secondary">Search, filter and manage recorded bills.</span>
                    </div>
                </div>
                <span class="badge rounded-pill text-bg-light border text-secondary d-none d-sm-inline-flex">Live register</span>
            </div>
            <div class="table-responsive">
                <table id="car-wash-transactions-dt" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light"><tr><th scope="col">Bill #</th><th scope="col">Date</th><th scope="col">Customer / vehicle</th><th scope="col">Services</th><th scope="col" class="text-end">Total</th><th scope="col">Payment</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Tap + on a row to see all details and actions</p>
        </div>
    </section>

    <section id="wash-services-view" class="d-none" role="tabpanel" aria-labelledby="wash-services-tab">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">Service catalogue</h2>
                <p class="small text-secondary mb-0">Maintain available wash options, pricing and tax rates.</p>
            </div>
            <x-action-button action="car-wash.add-service" label="Add service" variant="outline-primary"  />
        </div>
        <div class="card overflow-hidden">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center overflow-hidden">
                    <span class="text-secondary"><i class="bi bi-droplet" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="h5 mb-0">Available services</h3>
                        <span class="small text-secondary">Pricing is used when creating a transaction.</span>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table id="car-wash-services-dt" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light"><tr><th scope="col">Service</th><th scope="col">Description</th><th scope="col" class="text-end">Price</th><th scope="col" class="text-end">Tax</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Tap + on a row to see all details and actions</p>
        </div>
    </section>

    <div id="wash-view-modal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="wash-view-title">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-between px-4">
                    <h3 class="h6 fw-semibold mb-0" id="wash-view-title">Transaction details</h3>
                    <button class="btn btn-sm btn-light" type="button" aria-label="Close" onclick="closeModal('wash-view-modal')"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="p-4" id="wash-view-body"></div>
                <div class="d-flex justify-content-end px-4 pb-4"><button class="btn btn-outline-secondary btn-sm" type="button" onclick="closeModal('wash-view-modal')">Close</button></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let washTab = 'transactions', washServices = @json($services);
let washTransactionsTable = null, washServicesTable = null;
const w = id => document.getElementById(id);
const wEscape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function switchWashTab(tab) {
    washTab = tab;
    w('wash-transactions-tab').classList.toggle('active', tab === 'transactions');
    w('wash-services-tab').classList.toggle('active', tab === 'services');
    w('wash-transactions-tab').setAttribute('aria-selected', String(tab === 'transactions'));
    w('wash-services-tab').setAttribute('aria-selected', String(tab === 'services'));
    w('wash-transactions-view').classList.toggle('d-none', tab !== 'transactions');
    w('wash-services-view').classList.toggle('d-none', tab !== 'services');
    w('carwash-primary-action').classList.toggle('d-none', tab === 'services');
    if (tab === 'services') {
        w('carwash-primary-action').dataset.action = 'car-wash.add-service';
        w('carwash-primary-action').querySelector('span').textContent = 'Add service';
        w('carwash-primary-action').setAttribute('aria-label', 'Add service');
        w('carwash-primary-action').title = 'Add service';
    }
    else {
        w('carwash-primary-action').dataset.action = 'car-wash.add-transaction';
        w('carwash-primary-action').querySelector('span').textContent = 'New transaction';
        w('carwash-primary-action').setAttribute('aria-label', 'New transaction');
        w('carwash-primary-action').title = 'New transaction';
    }
    if (tab === 'services') {
        if (!washServicesTable) initWashServiceTable();
        else washServicesTable.columns.adjust();
    }
}

function initWashTransactionsTable() {
    washTransactionsTable = window.initDataTable('car-wash-transactions-dt', @json(route('car-wash.getData')), [
        { data: 'transaction_number', render: v => `<span class="fw-medium">${wEscape(v || '—')}</span>` },
        { data: 'service_datetime', render: v => fmt.date(v) },
        { data: null, orderable: false, searchable: false, render: (_, __, row) => {
            const customer = row.customer?.company_name || row.customer?.name || 'Walk-in';
            return `${wEscape(customer)}${row.vehicle_registration ? `<small class="text-muted d-block">${wEscape(row.vehicle_registration)}</small>` : ''}`;
        }},
        { data: 'items', orderable: false, searchable: false, render: items =>
            (items || []).map(item => wEscape(item.service_name || item.service?.name || 'Service')).join(', ') || '—'
        },
        { data: 'total_amount', className: 'text-end', render: v => fmt.currency(v) },
        { data: 'payment_status', render: v => {
            const status = v || 'unpaid';
            const badge = status === 'paid' ? 'text-bg-success' : status === 'partial' ? 'text-bg-warning' : 'text-bg-danger';
            return `<span class="badge ${badge}">${wEscape(status)}</span>`;
        }},
        { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], () => ({
        customer_id: w('wash-customer-filter').value,
        payment_status: w('wash-status-filter').value,
        date_from: w('wash-date-from').value,
        date_to: w('wash-date-to').value,
    }), {
        order: [[1, 'desc']],
    });
}

function loadWashTransactions() {
    if (washTransactionsTable) washTransactionsTable.ajax.reload();
}

function initWashServiceTable() {
    washServicesTable = window.initDataTable('car-wash-services-dt', @json(route('car-wash.services.getData')), [
        { data: 'name', render: v => `<span class="fw-medium">${wEscape(v)}</span>` },
        { data: 'description', render: v => `<span class="text-muted">${wEscape(v || '—')}</span>` },
        { data: 'default_price', className: 'text-end', render: v => fmt.currency(v) },
        { data: 'tax_rate', className: 'text-end', render: v => `${Number(v || 0)}%` },
        { data: 'is_active', render: v => `<span class="badge ${v ? 'text-bg-success' : 'text-bg-secondary'}">${v ? 'Available' : 'Inactive'}</span>` },
        { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
    ], null, {
        order: [[0, 'asc']],
    });
}

function serviceOptions(selected) {
    return '<option value="">Custom service</option>' + washServices.map(s => `<option value="${Number(s.id)}" data-price="${Number(s.default_price)}" data-tax="${Number(s.tax_rate || 0)}" ${Number(selected) === Number(s.id) ? 'selected' : ''}>${wEscape(s.name)}</option>`).join('');
}
function addWashItem(item = {}) {
    const row = document.createElement('tr');
    row.innerHTML = `<td><select class="form-select form-select-sm wash-item-service" onchange="selectWashService(this)">${serviceOptions(item.service_id)}</select><input class="form-control form-control-sm mt-1 wash-item-name" maxlength="100" placeholder="Service name" value="${wEscape(item.service_name || '')}" aria-label="Service name" /></td>
        <td><input class="form-control form-control-sm wash-item-quantity" type="number" min=".01" step=".01" value="${Number(item.quantity || 1)}" oninput="updateWashTotal()" aria-label="Quantity" /></td>
        <td><input class="form-control form-control-sm wash-item-price" type="number" min="0" step=".01" value="${Number(item.unit_price || 0)}" oninput="updateWashTotal()" aria-label="Unit price" /></td>
        <td><input class="form-control form-control-sm wash-item-discount" type="number" min="0" step=".01" value="${Number(item.discount || 0)}" oninput="updateWashTotal()" aria-label="Line discount" /></td>
        <td><input class="form-control form-control-sm wash-item-tax" type="number" min="0" max="100" step=".01" value="${Number(item.tax_rate || 0)}" oninput="updateWashTotal()" aria-label="Tax percentage" /></td>
        <td class="text-end wash-item-total">₹0.00</td><td class="text-end"><button class="btn btn-sm btn-outline-danger" type="button" onclick="this.closest('tr').remove();updateWashTotal()" aria-label="Remove service" ><i class="bi bi-x"></i></button></td>`;
    w('wash-item-rows').appendChild(row);
    if (item.service_id) selectWashService(row.querySelector('.wash-item-service'), true);
    updateWashTotal();
}
function selectWashService(select, keepValues = false) {
    const option = select.selectedOptions[0], row = select.closest('tr');
    if (!option?.value) return;
    row.querySelector('.wash-item-name').value = washServices.find(s => Number(s.id) === Number(option.value))?.name || '';
    if (!keepValues) {
        row.querySelector('.wash-item-price').value = option.dataset.price || 0;
        row.querySelector('.wash-item-tax').value = option.dataset.tax || 0;
    }
    updateWashTotal();
}
function updateWashTotal() {
    let total = 0;
    document.querySelectorAll('#wash-item-rows tr').forEach(row => {
        const qty = Number(row.querySelector('.wash-item-quantity').value || 0);
        const price = Number(row.querySelector('.wash-item-price').value || 0);
        const discount = Math.min(Number(row.querySelector('.wash-item-discount').value || 0), qty * price);
        const tax = Number(row.querySelector('.wash-item-tax').value || 0);
        const taxable = qty * price - discount;
        const line = taxable + Math.round(taxable * tax / 100 * 100) / 100;
        total += line;
        row.querySelector('.wash-item-total').textContent = fmt.currency(line);
    });
    total = Math.max(0, total - Number(w('wash-discount').value || 0));
    w('wash-total-preview').value = total.toFixed(2);
}
async function openWashForm() {
    try {
        const form = await window.loadAddEditModal(@json(route('car-wash.addEdit', ['type' => 'transaction'])));
        washServices = form.services || washServices;
        w('wash-datetime').value = fmt.nowLocal();
        w('wash-total-preview').value = '0.00';
        addWashItem();
    } catch (error) {
        toast.error(error.message || 'Unable to load transaction form');
    }
}
async function editWashTransaction(id) {
    try {
        const form = await window.loadAddEditModal(@json(route('car-wash.addEdit', ['type' => 'transaction', 'id' => 'ID'])).replace('ID', id));
        const tx = form.record;
        washServices = form.services || washServices;
        w('wash-datetime').value = (tx.service_datetime || '').replace(' ', 'T').slice(0,16);
        w('wash-customer').value = tx.customer_id || ''; w('wash-registration').value = tx.vehicle_registration || '';
        w('wash-discount').value = tx.discount || 0; w('wash-amount-paid').value = tx.amount_paid || 0;
        w('wash-payment-mode').value = tx.payment_mode || ''; w('wash-notes').value = tx.notes || '';
        w('wash-item-rows').innerHTML = '';
        (tx.items || []).forEach(item => addWashItem({service_id:item.service_id,service_name:item.service_name,quantity:item.quantity,unit_price:item.unit_price,discount:item.discount,tax_rate:item.tax_rate}));
    } catch (error) {
        toast.error(error.message || 'Unable to load transaction');
    }
}
async function saveWashTransaction(event) {
    event.preventDefault();
    const items = [...document.querySelectorAll('#wash-item-rows tr')].map(row => ({
        service_id:row.querySelector('.wash-item-service').value || null,
        service_name:row.querySelector('.wash-item-name').value.trim(),
        quantity:Number(row.querySelector('.wash-item-quantity').value),
        unit_price:Number(row.querySelector('.wash-item-price').value),
        discount:Number(row.querySelector('.wash-item-discount').value || 0),
        tax_rate:Number(row.querySelector('.wash-item-tax').value || 0)
    }));
    if (!items.length || items.some(item => !item.service_name)) return toast.error('Add at least one service with a name');
    const data = {
        service_datetime:w('wash-datetime').value.replace('T',' '),customer_id:w('wash-customer').value || null,
        vehicle_registration:w('wash-registration').value,discount:Number(w('wash-discount').value || 0),
        amount_paid:Number(w('wash-amount-paid').value || 0),payment_mode:w('wash-payment-mode').value || null,
        notes:w('wash-notes').value,items
    };
    const id = w('wash-id').value, button = w('wash-save-button');
    button.disabled = true;
    try {
        const form = w('wash-transaction-form');
        const res = id ? await api.put(form.action, data) : await api.post(form.action, data);
        if (!res.ok) { showErrors(await extractErrors(res)); return; }
        closeModal('ajax-form-modal'); toast.success(id ? 'Transaction updated' : 'Transaction saved'); loadWashTransactions();
    } catch (error) { toast.error('Unable to save transaction'); }
    finally { button.disabled = false; }
}
async function openWashServiceForm() {
    try {
        await window.loadAddEditModal(@json(route('car-wash.addEdit', ['type' => 'service'])));
    } catch (error) {
        toast.error(error.message || 'Unable to load service form');
    }
}
async function editWashService(id) {
    try {
        await window.loadAddEditModal(@json(route('car-wash.addEdit', ['type' => 'service', 'id' => 'ID'])).replace('ID', id));
    } catch (error) {
        toast.error(error.message || 'Unable to load service');
    }
}
async function saveWashService(event) {
    event.preventDefault();
    const id = w('wash-service-id').value, data = {
        name:w('wash-service-name').value,description:w('wash-service-description').value,
        default_price:Number(w('wash-service-price').value),tax_rate:Number(w('wash-service-tax').value || 0),
        is_active:w('wash-service-active').checked,sort_order:Number(w('wash-service-order').value || 0)
    };
    const form = w('wash-service-form');
    const res = id ? await api.put(form.action, data) : await api.post(form.action, data);
    if (!res.ok) return showErrors(await extractErrors(res));
    const service = await res.json();
    washServices = washServices.filter(item => Number(item.id) !== Number(service.id));
    if (service.is_active) washServices.push(service);
    washServices.sort((a, b) => a.sort_order - b.sort_order);
    closeModal('ajax-form-modal');
    toast.success('Service saved');
    if (washServicesTable) washServicesTable.ajax.reload();
}
async function viewWashTransaction(id) {
    const res = await api.get(`/car-wash/${id}`);
    if (!res.ok) return toast.error('Unable to open transaction');
    const tx = await res.json();
    const customer = tx.customer?.company_name || tx.customer?.name || 'Walk-in';
    const items = tx.items || [];
    w('wash-view-title').textContent = `Bill ${tx.transaction_number || ''}`;
    w('wash-view-body').innerHTML = `<div class="row g-3 mb-3">
        <div class="col-sm-6"><span class="text-muted small d-block">Service date</span><span class="fw-medium">${fmt.date(tx.service_datetime)}</span></div>
        <div class="col-sm-6"><span class="text-muted small d-block">Customer</span><span class="fw-medium">${wEscape(customer)}</span></div>
        <div class="col-sm-6"><span class="text-muted small d-block">Vehicle registration</span><span class="fw-medium">${wEscape(tx.vehicle_registration || '—')}</span></div>
        <div class="col-sm-6"><span class="text-muted small d-block">Payment</span><span class="badge ${tx.payment_status==='paid'?'text-bg-success':tx.payment_status==='partial'?'text-bg-warning':'text-bg-danger'}">${wEscape(tx.payment_status || 'unpaid')}</span></div>
        <div class="col-sm-6"><span class="text-muted small d-block">Payment mode</span><span class="fw-medium">${wEscape(tx.payment_mode || '—')}</span></div>
    </div>
    <div class="table-responsive border rounded-3"><table class="table table-sm align-middle mb-0 table-hover"><thead class="table-light"><tr><th>Service</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Discount</th><th class="text-end">Amount</th></tr></thead><tbody>
        ${items.map(item=>`<tr><td>${wEscape(item.service_name || 'Service')}</td><td class="text-end">${Number(item.quantity || 0)}</td><td class="text-end">${fmt.currency(item.unit_price)}</td><td class="text-end">${fmt.currency(item.discount)}</td><td class="text-end">${fmt.currency(item.total_amount ?? 0)}</td></tr>`).join('')}
    </tbody><tfoot><tr><td colspan="4" class="text-end text-muted">Transaction discount</td><td class="text-end">${fmt.currency(tx.discount)}</td></tr><tr><th colspan="4" class="text-end">Total</th><th class="text-end">${fmt.currency(tx.total_amount)}</th></tr><tr><td colspan="4" class="text-end text-muted">Paid</td><td class="text-end">${fmt.currency(tx.amount_paid)}</td></tr><tr><td colspan="4" class="text-end text-muted">Balance due</td><td class="text-end">${fmt.currency(tx.balance_due)}</td></tr></tfoot></table></div>
    ${tx.notes ? `<p class="small text-muted mt-3 mb-0">${wEscape(tx.notes)}</p>` : ''}`;
    openModal('wash-view-modal');
}
async function deleteWashTransaction(id) {
    if (!confirm('Delete this car wash transaction?')) return;
    const res = await api.delete(`/car-wash/${id}`);
    if (!res.ok) return toast.error('Unable to delete transaction');
    toast.success('Transaction deleted'); loadWashTransactions();
}
async function downloadWashBill(id) {
    const res = await api.get(`/car-wash/${id}/pdf-url`);
    if (!res.ok) return toast.error('Unable to create receipt link');
    const data = await res.json();
    if (data.url) window.open(data.url, '_blank', 'noopener');
    else toast.error('Receipt link unavailable');
}
initWashTransactionsTable();
if (new URLSearchParams(window.location.search).get('action') === 'new') openWashForm();

document.addEventListener('click', event => {
    const button = event.target.closest('[data-action^="car-wash."]');
    if (!button) return;

    switch (button.dataset.action) {
        case 'car-wash.add-transaction':
            openWashForm();
            break;
        case 'car-wash.add-service':
            openWashServiceForm();
            break;
        case 'car-wash.service-view':
            window.openRecordView({
                url: button.dataset.viewUrl,
                title: 'Car wash service details',
                fields: [
                    { label: 'Name', value: 'name' },
                    { label: 'Description', value: 'description', fullWidth: true },
                    { label: 'Price', value: record => fmt.currency(record.default_price) },
                    { label: 'Tax rate', value: record => `${Number(record.tax_rate || 0)}%` },
                    { label: 'Status', value: record => record.is_active ? 'Active' : 'Inactive' },
                ],
            });
            break;
        case 'car-wash.view':
            viewWashTransaction(Number(button.dataset.transactionId));
            break;
        case 'car-wash.edit':
            editWashTransaction(Number(button.dataset.transactionId));
            break;
        case 'car-wash.download':
            downloadWashBill(Number(button.dataset.transactionId));
            break;
        case 'car-wash.delete':
            deleteWashTransaction(Number(button.dataset.transactionId));
            break;
        case 'car-wash.service-edit':
            editWashService(Number(button.dataset.serviceId));
            break;
    }
});
</script>
@endpush