<div class="col-12 col-sm-6 col-lg-2">
    <label class="form-label" for="expense-status-filter">Payment status</label>
    <select class="form-select" id="expense-status-filter" data-ajax-table="expenses-dt">
        <option value="">All statuses</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="unpaid">Unpaid</option>
    </select>
</div>
<div class="col-12 col-sm-6 col-lg-2">
    <label class="form-label" for="expense-date-from">From</label>
    <input class="form-control" type="date" id="expense-date-from" data-ajax-table="expenses-dt">
</div>
<div class="col-12 col-sm-6 col-lg-2">
    <label class="form-label" for="expense-date-to">To</label>
    <input class="form-control" type="date" id="expense-date-to" data-ajax-table="expenses-dt">
</div>
