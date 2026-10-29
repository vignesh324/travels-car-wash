@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div><p class="text-uppercase small fw-semibold text-secondary mb-2">Workspace insights</p><h2 class="h2 fw-bold mb-1">Reports</h2><p class="text-secondary small mb-0">Review activity and financial performance for a selected period.</p></div>
</div>

<section class="card mb-3" aria-labelledby="report-filter-title">
    <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-sliders2" aria-hidden="true"></i>
            <h2 id="report-filter-title" class="small fw-semibold mb-0">Report filters</h2>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-6 col-md-3"><label class="form-label" for="report-date-from">From</label><input class="form-control" type="date" id="report-date-from" /></div>
            <div class="col-12 col-sm-6 col-md-3"><label class="form-label" for="report-date-to">To</label><input class="form-control" type="date" id="report-date-to" /></div>
            <div class="col-12 col-sm-6 col-md-3 d-none" id="report-customer-filter-wrap">
                <label class="form-label" for="report-customer">Customer</label>
                <select class="form-select" id="report-customer">
                    <option value="">All customers</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto"><button class="btn btn-primary" type="button" onclick="reportPage=1;updateReportExport();loadReport()"><i class="bi bi-funnel me-1"></i>Apply filters</button></div>
        </div>
    </div>
</section>

<nav class="nav nav-pills gap-2 flex-nowrap overflow-auto mb-3" aria-label="Report type" >
    <button class="nav-link active" type="button" data-report="revenue" onclick="switchReport('revenue')">Revenue</button>
    <button class="nav-link" type="button" data-report="expenses" onclick="switchReport('expenses')">Expenses</button>
    <button class="nav-link" type="button" data-report="trips" onclick="switchReport('trips')">Trips</button>
    <button class="nav-link" type="button" data-report="invoices" onclick="switchReport('invoices')">Invoices</button>
    <button class="nav-link" type="button" data-report="payments" onclick="switchReport('payments')">Payments</button>
    <button class="nav-link" type="button" data-report="carwash" onclick="switchReport('carwash')">Car wash</button>
</nav>

<section id="report-summary" class="row g-3 mb-3" aria-live="polite"></section>
<section id="report-chart-wrap" class="card border-0 shadow-sm mb-3 d-none" aria-label="Report chart" >
    <div class="card-body"><h3 class="h6 fw-semibold mb-3" id="report-chart-title">Report breakdown</h3><div class="position-relative chart-container"><canvas id="report-chart" role="img" aria-labelledby="report-chart-title"></canvas></div></div>
</section>
<section class="card border-0 shadow-sm overflow-hidden" aria-label="Report details" >
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between gap-2">
        <h3 class="h6 fw-semibold mb-0" id="report-table-title">Report details</h3>
        <a class="btn btn-sm btn-outline-primary d-none" id="report-export" href="#" target="_blank"><i class="bi bi-download me-1"></i>Export Excel</a>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light" id="report-table-head"></thead><tbody id="report-table-body"><tr><td class="text-center text-muted py-5">Choose a report to view its data.</td></tr></tbody></table></div>
    <div id="report-pagination"></div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script>
let activeReport = 'revenue', reportChart = null, reportPage = 1;
const reportLabels = {
    revenue:'Revenue by day',expenses:'Vehicle expenses by category',trips:'Trips in period',
    invoices:'Invoices in period',payments:'Payments by method',carwash:'Car wash transactions'
};
const rEscape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const rCurrency = value => fmt.currency(value);
const r = id => document.getElementById(id);

function switchReport(type) {
    activeReport = type; reportPage = 1;
    document.querySelectorAll('[data-report]').forEach(button => button.classList.toggle('active', button.dataset.report === type));
    r('report-customer-filter-wrap').classList.toggle('d-none', type !== 'trips');
    updateReportExport();
    loadReport();
}
function reportDates() {
    return {
        date_from:r('report-date-from').value,
        date_to:r('report-date-to').value,
        page:reportPage,
        ...(activeReport === 'trips' && r('report-customer').value ? {customer_id:r('report-customer').value} : {})
    };
}
function updateReportExport() {
    const exports = {expenses:'expenses',trips:'trips',invoices:'invoices'};
    const link = r('report-export');
    if (!exports[activeReport]) {
        link.classList.add('d-none');
        return;
    }
    const params = {date_from:r('report-date-from').value,date_to:r('report-date-to').value};
    if (activeReport === 'trips' && r('report-customer').value) params.customer_id = r('report-customer').value;
    const query = new URLSearchParams(params);
    link.href = `${window.baseUrl}/reports/export/${exports[activeReport]}?${query.toString()}`;
    link.classList.remove('d-none');
}
function setSummary(items) {
    r('report-summary').innerHTML = items.map((item, index) => `<div class="col-6 col-xl-3"><article class="card h-100 ${['bg-primary-subtle','bg-success-subtle','bg-warning-subtle','bg-danger-subtle'][index%4]}"><div class="card-body py-3"><p class="small text-muted mb-1">${rEscape(item.label)}</p><p class="h5 fw-bold mb-0">${rEscape(item.value)}</p></div></article></div>`).join('');
}
function setTable(columns, rows, emptyText = 'No records found for this period.') {
    r('report-table-head').innerHTML = `<tr>${columns.map(column => `<th scope="col" class="${column.right ? 'text-end' : ''}">${rEscape(column.label)}</th>`).join('')}</tr>`;
    r('report-table-body').innerHTML = rows.length ? rows.map(row => `<tr>${row.map((cell,index) => `<td class="${columns[index]?.right ? 'text-end' : ''}">${cell}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${columns.length}" class="text-center text-muted py-5"><i class="bi bi-clipboard2-data fs-3 d-block mb-2"></i>${rEscape(emptyText)}</td></tr>`;
}
function showChart(title, labels, datasets, type = 'bar') {
    r('report-chart-wrap').classList.remove('d-none');
    r('report-chart-title').textContent = title;
    r('report-chart').setAttribute('aria-label', title);
    if (reportChart) reportChart.destroy();
    const ctx = r('report-chart').getContext('2d');
    reportChart = new Chart(ctx, {
        type,
        data:{labels,datasets},
        options:{
            responsive:true,maintainAspectRatio:false,
            scales:type === 'doughnut' ? {} : {
                x:{grid:{display:false},ticks:{maxRotation:0,autoSkip:true}},
                y:{beginAtZero:true,grid:{color:'#edf1f7'},ticks:{callback:value => fmt.currency(value)}}
            },
            plugins:{
                legend:{display:datasets.length > 1 || type === 'doughnut',position:'top',labels:{boxWidth:12,usePointStyle:true}},
                tooltip:{callbacks:{label:context => `${context.dataset.label ? context.dataset.label + ': ' : ''}${fmt.currency(context.parsed.y ?? context.parsed)}`}}
            }
        }
    });
}
function hideChart() {
    r('report-chart-wrap').classList.add('d-none');
    if (reportChart) { reportChart.destroy(); reportChart = null; }
}
async function loadReport() {
    const endpoint = `/reports/${activeReport === 'carwash' ? 'car-wash' : activeReport}`;
    r('report-table-title').textContent = reportLabels[activeReport];
    r('report-table-body').innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading report…</td></tr>';
    try {
        const response = await api.get(endpoint, reportDates());
        if (!response.ok) throw new Error('Report request failed');
        const data = await response.json();
        renderReport(data);
    } catch (error) {
        hideChart();
        setSummary([]);
        r('report-table-body').innerHTML = '<tr><td colspan="8" class="text-center text-danger py-5">Unable to load this report. Please try again.</td></tr>';
        toast.error('Unable to load report');
    }
}
function renderReport(data) {
    if (activeReport === 'revenue') {
        const summary = data.summary || {};
        setSummary([
            {label:'Total revenue',value:rCurrency(summary.total_revenue)},
            {label:'Total collected',value:rCurrency(summary.total_collected)},
            {label:'Travel revenue',value:rCurrency(summary.travels_revenue)},
            {label:'Car wash revenue',value:rCurrency(summary.carwash_revenue)}
        ]);
        const daily = new Map();
        [...(data.trips || []).map(row => ({...row,kind:'Travels'})),...(data.carwash || []).map(row => ({...row,kind:'Car wash'}))].forEach(row => {
            const point = daily.get(row.date) || {trips:0,carwash:0};
            point[row.kind === 'Travels' ? 'trips' : 'carwash'] += Number(row.revenue || 0);
            daily.set(row.date, point);
        });
        const dates = [...daily.keys()].sort();
        if (dates.length) showChart('Daily revenue', dates.map(fmt.date), [
            {label:'Travels',data:dates.map(date=>daily.get(date).trips),backgroundColor:'#3b82f6',borderRadius:4},
            {label:'Car wash',data:dates.map(date=>daily.get(date).carwash),backgroundColor:'#10b981',borderRadius:4}
        ]);
        else hideChart();
        const rows = dates.map(date => {
            const trip = (data.trips || []).find(row => row.date === date), wash = (data.carwash || []).find(row => row.date === date);
            const tripAmount = Number(trip?.revenue || 0), washAmount = Number(wash?.revenue || 0);
            return [fmt.date(date),rCurrency(tripAmount),rCurrency(washAmount),`<strong>${rCurrency(tripAmount+washAmount)}</strong>`,rCurrency(Number(trip?.collected || 0)+Number(wash?.collected || 0))];
        });
        setTable([{label:'Date'},{label:'Travels',right:true},{label:'Car wash',right:true},{label:'Total revenue',right:true},{label:'Collected',right:true}],rows);
        r('report-pagination').innerHTML = '';
    } else if (activeReport === 'expenses') {
        const summary = data.summary || {};
        setSummary([{label:'Total expenses',value:rCurrency(summary.total_expenses)},{label:'Vehicle expenses',value:rCurrency(summary.vehicle_expenses)},{label:'Business expenses',value:rCurrency(summary.business_expenses)}]);
        const categories = data.by_category || {};
        const labels = Object.keys(categories), values = Object.values(categories).map(Number);
        if (labels.length) showChart('Vehicle expenses by category',labels,[{label:'Vehicle expenses',data:values,backgroundColor:['#3b82f6','#10b981','#f59e0b','#ef4444','#6366f1','#06b6d4'],borderWidth:0}], 'doughnut'); else hideChart();
        const vehicle = (data.vehicle_expenses || []).map(item => ({...item,sourceLabel:'Vehicle'}));
        const business = (data.business_expenses || []).map(item => ({...item,sourceLabel:'Business'}));
        const rows = [...vehicle,...business].sort((a,b)=>String(b.expense_date).localeCompare(String(a.expense_date))).map(item => [
            fmt.date(item.expense_date),rEscape(item.sourceLabel),rEscape(item.category?.name || '—'),
            rEscape(item.vehicle?.registration_number || item.vehicle?.name || item.vendor_name || item.description || '—'),
            rCurrency(item.total_amount),`<span class="badge ${item.payment_status==='paid'?'text-bg-success':item.payment_status==='partial'?'text-bg-warning':'text-bg-danger'}">${rEscape(item.payment_status || 'unpaid')}</span>`
        ]);
        setTable([{label:'Date'},{label:'Type'},{label:'Category'},{label:'Vehicle / vendor'},{label:'Amount',right:true},{label:'Payment'}],rows);
        r('report-pagination').innerHTML = '';
    } else if (activeReport === 'trips') {
        const summary = data.summary || {};
        setSummary([{label:'Trips',value:Number(summary.count || 0).toLocaleString('en-IN')},{label:'Revenue',value:rCurrency(summary.revenue)},{label:'Collected',value:rCurrency(summary.collected)},{label:'Outstanding',value:rCurrency(summary.outstanding)}]);
        hideChart();
        const rows = (data.trips?.data || []).map(trip => [
            rEscape(trip.trip_number || trip.id),
            fmt.date(trip.trip_date),
            rEscape(trip.customer?.company_name || trip.customer?.name || 'Walk-in'),
            rEscape(trip.vehicle?.registration_number || trip.vehicle?.name || '—'),
            rEscape(trip.quick_trip_direction ? trip.quick_trip_direction[0].toUpperCase() + trip.quick_trip_direction.slice(1) : '—'),
            rEscape(trip.pickup_location || '—'),
            rEscape(trip.drop_location || '—'),
            rEscape(trip.route || '—'),
            rCurrency(trip.total_amount),
            `<span class="badge ${trip.status==='completed'?'text-bg-success':trip.status==='draft'?'text-bg-warning':'text-bg-danger'}">${rEscape(trip.status || '—')}</span>`
        ]);
        setTable([{label:'Trip #'},{label:'Date'},{label:'Customer'},{label:'Vehicle'},{label:'Direction'},{label:'Pickup'},{label:'Drop'},{label:'Route'},{label:'Amount',right:true},{label:'Status'}],rows);
        renderPagination('report-pagination',Number(data.trips?.current_page || 1),Number(data.trips?.last_page || 1),Number(data.trips?.total || 0),'function(p){reportPage=p;loadReport()}');
    } else if (activeReport === 'invoices') {
        const summary = data.summary || {};
        setSummary([{label:'Invoices',value:Number(summary.count || 0).toLocaleString('en-IN')},{label:'Invoiced',value:rCurrency(summary.invoiced)},{label:'Collected',value:rCurrency(summary.collected)},{label:'Outstanding',value:rCurrency(summary.outstanding)}]);
        hideChart();
        const rows = (data.invoices?.data || []).map(invoice => [rEscape(invoice.invoice_number || invoice.id),fmt.date(invoice.invoice_date),rEscape(invoice.customer?.company_name || invoice.customer?.name || '—'),rCurrency(invoice.total_amount),rCurrency(invoice.balance_due),`<span class="badge ${invoice.status==='paid'?'text-bg-success':invoice.status==='partial'?'text-bg-warning':invoice.status==='cancelled'?'text-bg-secondary':'text-bg-primary'}">${rEscape(invoice.status || '—')}</span>`]);
        setTable([{label:'Invoice #'},{label:'Date'},{label:'Customer'},{label:'Total',right:true},{label:'Balance',right:true},{label:'Status'}],rows);
        renderPagination('report-pagination',Number(data.invoices?.current_page || 1),Number(data.invoices?.last_page || 1),Number(data.invoices?.total || 0),'function(p){reportPage=p;loadReport()}');
    } else if (activeReport === 'payments') {
        setSummary([{label:'Collected',value:rCurrency(data.summary?.total_collected)}]);
        const methods = data.by_method || [], labels = methods.map(item => item.payment_method || 'Other'), values = methods.map(item => Number(item.total || 0));
        if (labels.length) showChart('Collections by payment method',labels,[{label:'Collected',data:values,backgroundColor:['#2563eb','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ef4444'],borderWidth:0}], 'doughnut'); else hideChart();
        const rows = (data.payments?.data || []).map(payment => [rEscape(payment.receipt_number || payment.id),fmt.date(payment.payment_date),rEscape(payment.customer?.company_name || payment.customer?.name || '—'),rEscape(payment.payment_method || '—'),rCurrency(payment.amount)]);
        setTable([{label:'Receipt #'},{label:'Date'},{label:'Customer'},{label:'Method'},{label:'Amount',right:true}],rows);
        renderPagination('report-pagination',Number(data.payments?.current_page || 1),Number(data.payments?.last_page || 1),Number(data.payments?.total || 0),'function(p){reportPage=p;loadReport()}');
    } else {
        const summary = data.summary || {};
        setSummary([{label:'Transactions',value:Number(summary.count || 0).toLocaleString('en-IN')},{label:'Revenue',value:rCurrency(summary.revenue)},{label:'Collected',value:rCurrency(summary.collected)},{label:'Outstanding',value:rCurrency(summary.outstanding)}]);
        hideChart();
        const rows = (data.transactions?.data || []).map(tx => [rEscape(tx.transaction_number || tx.id),fmt.date(tx.service_datetime),rEscape(tx.customer?.company_name || tx.customer?.name || 'Walk-in'),(tx.items || []).map(item=>rEscape(item.service_name || 'Service')).join(', '),rCurrency(tx.total_amount),`<span class="badge ${tx.payment_status==='paid'?'text-bg-success':tx.payment_status==='partial'?'text-bg-warning':'text-bg-danger'}">${rEscape(tx.payment_status || 'unpaid')}</span>`]);
        setTable([{label:'Bill #'},{label:'Date'},{label:'Customer'},{label:'Services'},{label:'Total',right:true},{label:'Payment'}],rows);
        renderPagination('report-pagination',Number(data.transactions?.current_page || 1),Number(data.transactions?.last_page || 1),Number(data.transactions?.total || 0),'function(p){reportPage=p;loadReport()}');
    }
}
const now = new Date(), monthStart = new Date(now.getFullYear(),now.getMonth(),1);
r('report-date-from').value = monthStart.toISOString().slice(0,10);
r('report-date-to').value = now.toISOString().slice(0,10);
Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
Chart.defaults.color = '#64748b';
updateReportExport();
loadReport();
</script>
@endpush