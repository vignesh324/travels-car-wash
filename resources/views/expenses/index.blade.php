@extends('layouts.app')
@section('title', 'Expenses')
@section('content')
<div class="resource-page">
<header class="resource-page-header d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <p class="resource-kicker mb-2">Cost management</p>
        <h2 class="resource-heading fw-bold mb-1">Expenses</h2>
        <p class="resource-intro text-muted small mb-0">Track vehicle maintenance and business operating costs.</p>
    </div>
    <x-action-button action="expenses.add" label="Add expense" class="resource-primary-action" />
</header>

<ul class="nav resource-tabs mb-3" role="tablist" aria-label="Expense type" >
    <li class="nav-item"><button class="nav-link active" type="button" id="vehicle-tab" onclick="switchExpenseTab('vehicle')">Vehicle expenses</button></li>
    <li class="nav-item"><button class="nav-link" type="button" id="business-tab" onclick="switchExpenseTab('business')">Business expenses</button></li>
</ul>

<section class="card resource-filter-card mb-3" aria-label="Expense filters" >
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-lg-3" id="expense-vehicle-filter-wrap">
                <label class="form-label" for="expense-vehicle-filter">Vehicle</label>
                <select class="form-select" id="expense-vehicle-filter" data-ajax-table="expenses-dt">
                    <option value="">All vehicles</option>
                    @foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }} {{ $vehicle->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label" for="expense-category-filter">Category</label>
                <select class="form-select" id="expense-category-filter" data-ajax-table="expenses-dt">
                    <option value="">All categories</option>
                    @foreach($vehicleCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-2 d-none" id="expense-source-filter-wrap">
                <label class="form-label" for="expense-source-filter">Source</label>
                <select class="form-select" id="expense-source-filter" data-ajax-table="expenses-dt">
                    <option value="">All sources</option><option value="general">General</option><option value="carwash">Car wash</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <label class="form-label" for="expense-status-filter">Payment status</label>
                <select class="form-select" id="expense-status-filter" data-ajax-table="expenses-dt">
                    <option value="">All statuses</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option>
                </select>
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label" for="expense-date-from">From</label>
                <input class="form-control" type="date" id="expense-date-from" data-ajax-table="expenses-dt" />
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label" for="expense-date-to">To</label>
                <input class="form-control" type="date" id="expense-date-to" data-ajax-table="expenses-dt" />
            </div>
        </div>
    </div>
</section>

<section class="card resource-table-card overflow-hidden" aria-label="Expense list" >
    <div class="table-responsive resource-table-scroll p-2">
        <table id="expenses-dt" class="table table-hover align-middle mb-0 small w-100">
            <thead class="table-light"><tr>
                <th>Date</th><th>Description</th><th>Category</th>
                <th id="expense-vehicle-heading">Vehicle</th>
                <th class="text-end">Total</th><th>Payment</th><th class="text-end">Actions</th>
            </tr></thead>
        </table>
    </div>
    <p class="resource-scroll-hint d-md-none mb-0 px-3 pb-3"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Swipe or scroll horizontally to see all columns</p>
</section>

</div>
@endsection

@push('scripts')
<script>
let expenseType = 'vehicle';
let expensesTable = null;
const eById = id => document.getElementById(id);
const eEscape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const expenseCategoryOptions = {
    vehicle: @json($vehicleCategories),
    business: @json($businessCategories),
};

function initExpensesTable() {
    const isVehicle = expenseType === 'vehicle';
    expensesTable = window.initDataTable('expenses-dt', @json(route('expenses.getData')), [
            { data: 'expense_date', render: v => fmt.date(v) },
            { data: 'description', render: (v, _, row) => {
                const desc = v || row.vendor_name || row.notes || 'Expense';
                return `<div class="fw-medium">${eEscape(desc)}</div>${row.vendor_name ? `<small class="text-muted">${eEscape(row.vendor_name)}</small>` : ''}`;
            }},
            { data: 'category', orderable: false, searchable: false, render: v => eEscape(v?.name || '—') },
            { data: 'vehicle', orderable: false, searchable: false, render: v => isVehicle ? eEscape(v ? `${v.registration_number||''} ${v.name||''}`.trim() : '—') : '—' },
            { data: 'total_amount', className: 'text-end', render: v => `<span class="fw-semibold">${fmt.currency(v)}</span>` },
            { data: 'payment_status', render: v => {
                const cls = v==='paid'?'text-bg-success':v==='partial'?'text-bg-warning':'text-bg-danger';
                return `<span class="badge ${cls}">${eEscape(v||'unpaid')}</span>`;
            }},
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
        ], function() {
            const p = {
                type: expenseType,
                category_id:    eById('expense-category-filter').value,
                payment_status: eById('expense-status-filter').value,
                date_from:      eById('expense-date-from').value,
                date_to:        eById('expense-date-to').value,
            };
            if (isVehicle) p.vehicle_id = eById('expense-vehicle-filter').value;
            else p.source = eById('expense-source-filter').value;
            return p;
        }, {
            order: [[0, 'desc']],
            columnDefs: [{ targets: [3], visible: isVehicle }],
        }
    );
}

function switchExpenseTab(type) {
    expenseType = type;
    eById('vehicle-tab').classList.toggle('active', type === 'vehicle');
    eById('business-tab').classList.toggle('active', type === 'business');
    eById('expense-vehicle-filter-wrap').classList.toggle('d-none', type !== 'vehicle');
    eById('expense-source-filter-wrap').classList.toggle('d-none', type !== 'business');
    eById('expense-vehicle-heading').classList.toggle('d-none', type !== 'vehicle');
    const categoryFilter = eById('expense-category-filter');
    categoryFilter.replaceChildren(new Option('All categories', ''));
    expenseCategoryOptions[type].forEach(category => {
        categoryFilter.add(new Option(category.name, category.id));
    });
    initExpensesTable();
}

function loadExpenses() {
    if (expensesTable) expensesTable.ajax.reload();
}

async function openExpenseForm() {
    try {
        await window.loadAddEditModal(@json(route('expenses.index')) + '/' + encodeURIComponent(expenseType) + '/add-edit');
        updateExpenseTotal();
    } catch (error) {
        toast.error(error.message || 'Unable to load expense form');
    }
}

function updateExpenseTotal() {
    eById('expense-total').value = (Number(eById('expense-before-tax').value || 0) + Number(eById('expense-gst').value || 0)).toFixed(2);
}

async function editExpense(type, id) {
    switchExpenseTab(type);
    try {
        await window.loadAddEditModal(@json(route('expenses.index')) + '/' + encodeURIComponent(type) + '/add-edit/' + encodeURIComponent(id));
    } catch (error) {
        toast.error(error.message || 'Unable to load expense');
    }
}

async function saveExpense(event) {
    event.preventDefault();
    const id = eById('expense-id').value;
    const data = {
        expense_date:eById('expense-date').value,
        category_id:eById('expense-category').value,
        description:eById('expense-description').value,
        vendor_name:eById('expense-vendor').value,
        reference_number:eById('expense-reference').value,
        amount_before_tax:Number(eById('expense-before-tax').value || 0),
        gst_amount:Number(eById('expense-gst').value || 0),
        total_amount:Number(eById('expense-total').value || 0),
        payment_mode:eById('expense-payment-mode').value,
        payment_status:eById('expense-payment-status').value,
        payment_date:eById('expense-payment-date').value || null,
        notes:eById('expense-notes').value
    };
    if (expenseType === 'vehicle') data.vehicle_id = eById('expense-vehicle').value;
    else data.source = eById('expense-source').value;
    const button = eById('expense-save');
    button.disabled = true;
    try {
        const url = eById('expense-form').action;
        const res = id ? await api.put(url, data) : await api.post(url, data);
        if (!res.ok) { showErrors(await extractErrors(res)); return; }
        closeModal('ajax-form-modal'); toast.success(id ? 'Expense updated' : 'Expense added'); loadExpenses();
    } catch (error) { toast.error('Unable to save expense'); }
    finally { button.disabled = false; }
}

async function deleteExpense(type, id) {
    if (!confirm('Delete this expense? This action cannot be undone.')) return;
    const base = type === 'vehicle' ? '/expenses/vehicle' : '/expenses/business';
    const res = await api.delete(`${base}/${id}`);
    if (!res.ok) return toast.error('Unable to delete expense');
    toast.success('Expense deleted'); loadExpenses();
}

initExpensesTable();
if (new URLSearchParams(window.location.search).get('action') === 'new') openExpenseForm();

document.addEventListener('click', event => {
    const button = event.target.closest('[data-action^="expenses."]');
    if (!button) return;

    switch (button.dataset.action) {
        case 'expenses.add':
            openExpenseForm();
            break;
        case 'expenses.view':
            window.openRecordView({
                url: button.dataset.viewUrl,
                title: `${button.dataset.expenseType === 'vehicle' ? 'Vehicle' : 'Business'} expense details`,
                fields: [
                    { label: 'Date', value: record => fmt.date(record.expense_date) },
                    { label: 'Category', value: record => record.category?.name },
                    { label: 'Vendor', value: 'vendor_name' },
                    { label: 'Vehicle', value: record => record.vehicle ? `${record.vehicle.registration_number || ''} ${record.vehicle.name || ''}`.trim() : null },
                    { label: 'Description', value: 'description', fullWidth: true },
                    { label: 'Payment mode', value: 'payment_mode' },
                    { label: 'Payment status', value: record => record.payment_status ? record.payment_status.replace(/^\w/, letter => letter.toUpperCase()) : null },
                    { label: 'Payment date', value: record => fmt.date(record.payment_date) },
                    { label: 'Amount before tax', value: record => fmt.currency(record.amount_before_tax) },
                    { label: 'Tax amount', value: record => fmt.currency(record.gst_amount) },
                    { label: 'Total amount', value: record => fmt.currency(record.total_amount) },
                    { label: 'Notes', value: 'notes', fullWidth: true },
                ],
            });
            break;
        case 'expenses.edit':
            editExpense(button.dataset.expenseType, Number(button.dataset.expenseId));
            break;
        case 'expenses.delete':
            deleteExpense(button.dataset.expenseType, Number(button.dataset.expenseId));
            break;
    }
});
</script>
@endpush