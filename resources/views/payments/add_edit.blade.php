<form id="payment-form" class="p-4" action="{{ $formUrl }}">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label" for="inp_pmt_customer_id">Customer *</label>
            <select id="inp_pmt_customer_id" onchange="onPmtCustomerChange(this.value)" class="form-select" required>
                <option value="">Select customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->type === 'corporate' && $customer->company_name ? $customer->company_name : $customer->name }}</option>
                @endforeach
            </select>
            <p id="err_pmt_customer_id" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6"><label class="form-label" for="inp_pmt_payment_date">Payment Date *</label><input id="inp_pmt_payment_date" type="date" class="form-control" required></div>
        <div class="col-12 col-md-6"><label class="form-label" for="inp_pmt_amount">Amount (₹) *</label><input id="inp_pmt_amount" type="number" min=".01" step=".01" oninput="updatePmtSummary()" class="form-control" required><p id="err_pmt_amount" class="field-error small text-danger"></p></div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="inp_pmt_method">Payment Method *</label>
            <select id="inp_pmt_method" class="form-select" required>@foreach(['Cash','UPI','Bank Transfer','Card','Other'] as $method)<option>{{ $method }}</option>@endforeach</select>
        </div>
        <div class="col-12 col-md-6"><label class="form-label" for="inp_pmt_tx_ref">Transaction Reference</label><input id="inp_pmt_tx_ref" class="form-control"></div>
        <div class="col-12 col-md-6"><label class="form-label" for="inp_pmt_notes">Notes</label><input id="inp_pmt_notes" class="form-control"></div>
        <div class="col-12 d-none" id="pmt-alloc-wrap">
            <p class="small fw-semibold text-muted text-uppercase mb-2">Allocate to Invoices (optional)</p>
            <div id="pmt-alloc-list" class="border rounded-2"></div>
            <div class="d-flex justify-content-between small mt-2"><span>Allocated: <span id="pmt-allocated-display">₹0.00</span></span><span id="pmt-unalloc-display">Advance/Unallocated: ₹0.00</span></div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" onclick="savePayment()" id="pmt-save-btn" class="btn btn-primary btn-sm">Record Payment</button>
    </div>
</form>
