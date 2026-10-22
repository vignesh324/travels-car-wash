@extends('layouts.app')
@section('title', 'Activity Logs')
@section('content')
<div class="resource-page">
<header class="resource-page-header mb-4">
    <p class="resource-kicker">Workspace history</p>
    <h2 class="resource-heading fw-bold mb-1">Activity Logs</h2>
    <p class="resource-intro text-muted small mb-0">Review changes and actions recorded across your workspace.</p>
</header>
<section class="card resource-filter-card mb-3" aria-label="Activity log filters" >
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-2"><label class="form-label" for="log-name">Log type</label><input class="form-control" id="log-name" data-ajax-table="activity-logs-dt" placeholder="e.g. default" /></div>
            <div class="col-sm-6 col-lg-2"><label class="form-label" for="log-user">User ID</label><input class="form-control" type="number" min="1" id="log-user" data-ajax-table="activity-logs-dt" placeholder="Any user" /></div>
            <div class="col-sm-6 col-lg-2"><label class="form-label" for="log-subject">Record type</label><input class="form-control" id="log-subject" data-ajax-table="activity-logs-dt" placeholder="e.g. Trip" /></div>
            <div class="col-6 col-lg-2"><label class="form-label" for="log-date-from">From</label><input class="form-control" type="date" id="log-date-from" data-ajax-table="activity-logs-dt" /></div>
            <div class="col-6 col-lg-2"><label class="form-label" for="log-date-to">To</label><input class="form-control" type="date" id="log-date-to" data-ajax-table="activity-logs-dt" /></div>
            <div class="col-12 col-lg-2"><button class="btn btn-outline-secondary btn-sm w-100" type="button" onclick="resetLogFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</button></div>
        </div>
    </div>
</section>
<section class="card resource-table-card overflow-hidden" aria-label="Activity log entries" >
    <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-3 border-bottom">
        <h3 class="h6 fw-semibold mb-0">Recent activity</h3><span id="activity-count" class="small text-muted"></span>
    </div>
    <div class="table-responsive resource-table-scroll"><table id="activity-logs-dt" class="table table-hover align-middle mb-0 w-100">
        <thead class="table-light"><tr><th scope="col">Action</th><th scope="col">User</th><th scope="col">Record</th><th scope="col">Details</th><th scope="col">Date and time</th></tr></thead>
        <tbody></tbody>
    </table></div>
</section>
</div>
@endsection

@push('scripts')
<script>
let activityTable = null;
const alEscape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function initActivityTable() {
    activityTable = window.initDataTable('activity-logs-dt', @json(route('activity-logs.getData')), [
        { data: 'description', render: (value, _, entry) =>
            `<span class="fw-medium">${alEscape(value || 'Activity')}</span><small class="d-block text-muted">${alEscape(entry.log_name || 'default')}</small>`
        },
        { data: 'causer', orderable: false, searchable: false, render: value => alEscape(value?.name || 'System') },
        { data: null, orderable: false, searchable: false, render: (_, __, entry) =>
            alEscape(entry.subject_type ? entry.subject_type.split('\\').pop() + (entry.subject_id ? ` #${entry.subject_id}` : '') : '—')
        },
        { data: 'properties', orderable: false, searchable: false, className: 'text-muted small', render: value => {
            const properties = value && typeof value === 'object' ? value : {};
            const detail = Object.entries(properties).slice(0, 3)
                .map(([key, item]) => `${key}: ${typeof item === 'object' ? JSON.stringify(item) : item}`)
                .join(' · ');
            return alEscape(detail || '—');
        }},
        { data: 'created_at', render: value =>
            `<span>${fmt.date(value)}</span><small class="d-block text-muted">${value ? alEscape(new Date(value).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})) : ''}</small>`
        },
    ], () => ({
        log_name: document.getElementById('log-name').value,
        causer_id: document.getElementById('log-user').value,
        subject_type: document.getElementById('log-subject').value,
        date_from: document.getElementById('log-date-from').value,
        date_to: document.getElementById('log-date-to').value,
    }), {
        order: [[4, 'desc']],
        drawCallback: settings => {
            const count = settings.fnRecordsDisplay();
            document.getElementById('activity-count').textContent = `${count.toLocaleString('en-IN')} ${count === 1 ? 'entry' : 'entries'}`;
        },
    });
}
function loadActivityLogs() {
    if (activityTable) activityTable.ajax.reload();
}
function resetLogFilters() {
    ['log-name','log-user','log-subject','log-date-from','log-date-to'].forEach(id => document.getElementById(id).value = '');
    loadActivityLogs();
}
initActivityTable();
</script>
@endpush