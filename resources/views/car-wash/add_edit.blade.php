@if($type === 'transaction')
    <form id="wash-transaction-form" class="p-4" action="{{ $formUrl }}" onsubmit="saveWashTransaction(event)">
        <input id="wash-id" type="hidden" value="{{ $record?->id }}">
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6"><label class="form-label" for="wash-datetime">Service date and time *</label><input class="form-control" type="datetime-local" id="wash-datetime" value="{{ $record?->service_datetime?->format('Y-m-d\TH:i') }}" required></div>
            <div class="col-12 col-md-6"><label class="form-label" for="wash-customer">Customer</label><select class="form-select" id="wash-customer"><option value="">Walk-in customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(($record?->customer_id ?? '') == $customer->id)>{{ $customer->type === 'corporate' && $customer->company_name ? $customer->company_name : $customer->name }}</option>@endforeach</select></div>
            <div class="col-12 col-md-6"><label class="form-label" for="wash-registration">Vehicle registration</label><input class="form-control" id="wash-registration" maxlength="20" value="{{ $record?->vehicle_registration }}"></div>
            <div class="col-12 col-md-6"><label class="form-label" for="wash-discount">Transaction discount</label><input class="form-control" type="number" id="wash-discount" min="0" step=".01" value="{{ $record?->discount ?? 0 }}" oninput="updateWashTotal()"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2"><h4 class="small text-uppercase fw-semibold text-muted mb-0">Services</h4><button class="btn btn-sm btn-outline-secondary" type="button" onclick="addWashItem()"><i class="bi bi-plus me-1"></i>Add line</button></div>
        <div class="table-responsive border rounded-3">
            <table class="table table-sm align-middle mb-0 table-hover"><thead class="table-light"><tr><th>Service</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Tax %</th><th class="text-end">Line total</th><th></th></tr></thead><tbody id="wash-item-rows"></tbody></table>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12 col-md-4"><label class="form-label" for="wash-amount-paid">Amount paid</label><input class="form-control" type="number" min="0" step=".01" id="wash-amount-paid" value="{{ $record?->amount_paid ?? 0 }}"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="wash-payment-mode">Payment mode</label><select class="form-select" id="wash-payment-mode"><option value="">Not specified</option>@foreach(['Cash','UPI','Bank Transfer','Card','Other'] as $mode)<option @selected(($record?->payment_mode ?? '') === $mode)>{{ $mode }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4"><label class="form-label" for="wash-total-preview">Estimated total</label><input class="form-control fw-semibold" id="wash-total-preview" readonly></div>
            <div class="col-12"><label class="form-label" for="wash-notes">Notes</label><textarea class="form-control" rows="2" id="wash-notes">{{ $record?->notes }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 border-top mt-4 pt-3"><button class="btn btn-outline-secondary btn-sm" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary btn-sm" id="wash-save-button" type="submit">{{ $record ? 'Update transaction' : 'Save transaction' }}</button></div>
    </form>
@else
    <form id="wash-service-form" class="p-4" action="{{ $formUrl }}" onsubmit="saveWashService(event)">
            <input type="hidden" id="wash-service-id" value="{{ $record?->id }}">
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="wash-service-name">Name *</label><input class="form-control" id="wash-service-name" maxlength="100" value="{{ $record?->name }}" required></div>
                <div class="col-12"><label class="form-label" for="wash-service-description">Description</label><textarea class="form-control" rows="2" id="wash-service-description">{{ $record?->description }}</textarea></div>
                <div class="col-12 col-sm-6"><label class="form-label" for="wash-service-price">Default price *</label><input class="form-control" type="number" min="0" step=".01" id="wash-service-price" value="{{ $record?->default_price }}" required></div>
                <div class="col-12 col-sm-6"><label class="form-label" for="wash-service-tax">Tax rate (%)</label><input class="form-control" type="number" min="0" max="100" step=".01" id="wash-service-tax" value="{{ $record?->tax_rate ?? 0 }}"></div>
                <div class="col-12 col-sm-6"><label class="form-label" for="wash-service-order">Display order</label><input class="form-control" type="number" min="0" id="wash-service-order" value="{{ $record?->sort_order ?? 0 }}"></div>
                <div class="col-12 col-sm-6 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="wash-service-active" @checked($record?->is_active ?? true)><label class="form-check-label" for="wash-service-active">Available for billing</label></div></div>
            </div>
        <div class="d-flex justify-content-end gap-2 border-top mt-4 pt-3"><button class="btn btn-outline-secondary btn-sm" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary btn-sm" type="submit">Save service</button></div>
    </form>
@endif
