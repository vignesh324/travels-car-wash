<form id="customer-form" class="p-4" action="{{ $formUrl }}" data-uuid="{{ $customer?->uuid ?? '' }}">
    <input type="hidden" id="cust-edit-uuid" value="{{ $customer?->uuid ?? '' }}">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="inp_cust_type" class="form-label">Customer Type *</label>
            <select id="inp_cust_type" name="type" onchange="toggleCorpFields()" class="form-select" required>
                @foreach(['individual' => 'Individual', 'corporate' => 'Corporate', 'walkin' => 'Walk-in'] as $value => $label)
                    <option value="{{ $value }}" @selected(($customer?->type ?? 'individual') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6" id="cust-corp-wrap">
            <label for="inp_cust_company_name" class="form-label">Company Name</label>
            <input id="inp_cust_company_name" name="company_name" value="{{ $customer?->company_name }}" class="form-control">
            <p id="err_cust_company_name" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6">
            <label for="inp_cust_name" class="form-label" id="cust-name-label">Full Name *</label>
            <input id="inp_cust_name" name="name" value="{{ $customer?->name }}" class="form-control" required>
            <p id="err_cust_name" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6" id="cust-contact-wrap">
            <label for="inp_cust_contact_person" class="form-label">Contact Person</label>
            <input id="inp_cust_contact_person" name="contact_person" value="{{ $customer?->contact_person }}" class="form-control">
        </div>
        <div class="col-12 col-md-6">
            <label for="inp_cust_phone" class="form-label">Phone</label>
            <input id="inp_cust_phone" name="phone" type="tel" value="{{ $customer?->phone }}" class="form-control">
        </div>
        <div class="col-12 col-md-6">
            <label for="inp_cust_email" class="form-label">Email</label>
            <input id="inp_cust_email" name="email" type="email" value="{{ $customer?->email }}" class="form-control">
        </div>
        <div class="col-12 col-md-6">
            <label for="inp_cust_gstin" class="form-label">GSTIN</label>
            <input id="inp_cust_gstin" name="gstin" value="{{ $customer?->gstin }}" class="form-control">
        </div>
        <div class="col-12 col-md-6">
            <label for="inp_cust_state" class="form-label">State</label>
            <select id="inp_cust_state" name="state" class="form-select">
                <option value="">Select state</option>
                @foreach($states as $state)<option value="{{ $state }}" @selected(($customer?->state ?? '') === $state)>{{ $state }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="inp_cust_state_code" class="form-label">State Code</label>
            <input id="inp_cust_state_code" name="state_code" value="{{ $customer?->state_code }}" maxlength="5" class="form-control">
        </div>
        <div class="col-12 col-md-6" id="cust-corp-extra">
            <label for="inp_cust_payment_terms" class="form-label">Payment Terms</label>
            <input id="inp_cust_payment_terms" name="payment_terms" value="{{ $customer?->payment_terms }}" class="form-control">
        </div>
        <div class="col-12 col-md-6" id="cust-credit-wrap">
            <label for="inp_cust_credit_limit" class="form-label">Credit Limit (₹)</label>
            <input id="inp_cust_credit_limit" name="credit_limit" type="number" min="0" step=".01" value="{{ $customer?->credit_limit }}" class="form-control">
        </div>
        <div class="col-12">
            <label for="inp_cust_address" class="form-label">Address</label>
            <textarea id="inp_cust_address" name="address" rows="2" class="form-control">{{ $customer?->address }}</textarea>
        </div>
        <div class="col-12" id="cust-billing-wrap">
            <label for="inp_cust_billing_address" class="form-label">Billing Address</label>
            <textarea id="inp_cust_billing_address" name="billing_address" rows="2" class="form-control">{{ $customer?->billing_address }}</textarea>
        </div>
        <div class="col-12">
            <label for="inp_cust_notes" class="form-label">Notes</label>
            <textarea id="inp_cust_notes" name="notes" rows="2" class="form-control">{{ $customer?->notes }}</textarea>
        </div>
        <div class="col-12">
            <div class="form-check">
                <input id="inp_cust_is_active" name="is_active" type="checkbox" class="form-check-input" @checked($customer?->is_active ?? true)>
                <label class="form-check-label" for="inp_cust_is_active">Active customer</label>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" onclick="saveCust()" id="cust-save-btn" class="btn btn-primary btn-sm">{{ $customer ? 'Update Customer' : 'Add Customer' }}</button>
    </div>
</form>
