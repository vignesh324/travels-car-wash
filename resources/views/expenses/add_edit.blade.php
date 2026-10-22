<form id="expense-form" action="{{ $formUrl }}" onsubmit="saveExpense(event)" class="p-4">
    <input type="hidden" id="expense-id" value="{{ $expense?->id }}">
    <div class="row g-3">
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-date">Date *</label><input class="form-control" type="date" id="expense-date" value="{{ $expense?->expense_date?->format('Y-m-d') ?? now()->toDateString() }}" required></div>
        <div class="col-12 col-sm-6 {{ $type === 'vehicle' ? '' : 'd-none' }}" id="expense-form-vehicle-wrap">
            <label class="form-label" for="expense-vehicle">Vehicle *</label>
            <select class="form-select" id="expense-vehicle" {{ $type === 'vehicle' ? 'required' : '' }}><option value="">Select vehicle</option>
                @foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(($expense?->vehicle_id ?? '') == $vehicle->id)>{{ $vehicle->registration_number }} {{ $vehicle->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-category">Category *</label><select class="form-select" id="expense-category" required><option value="">Select category</option>
            @foreach($categories as $category)<option value="{{ $category->id }}" @selected(($expense?->category_id ?? '') == $category->id)>{{ $category->name }}</option>@endforeach
        </select></div>
        <div class="col-12 col-sm-6 {{ $type === 'business' ? '' : 'd-none' }}" id="expense-form-source-wrap">
            <label class="form-label" for="expense-source">Source</label><select class="form-select" id="expense-source"><option value="general" @selected(($expense?->source ?? 'general') === 'general')>General</option><option value="carwash" @selected(($expense?->source ?? '') === 'carwash')>Car wash</option></select>
        </div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-description">Description</label><input class="form-control" id="expense-description" maxlength="255" value="{{ $expense?->description }}"></div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-vendor">Vendor</label><input class="form-control" id="expense-vendor" maxlength="255" value="{{ $expense?->vendor_name }}"></div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-reference">Reference number</label><input class="form-control" id="expense-reference" maxlength="100" value="{{ $expense?->reference_number }}"></div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-payment-mode">Payment mode *</label><select class="form-select" id="expense-payment-mode" required>@foreach(['Cash','UPI','Bank Transfer','Card','Other'] as $mode)<option @selected(($expense?->payment_mode ?? 'Cash') === $mode)>{{ $mode }}</option>@endforeach</select></div>
        <div class="col-12 col-sm-4"><label class="form-label" for="expense-before-tax">Amount before tax *</label><input class="form-control" type="number" id="expense-before-tax" min="0" step=".01" value="{{ $expense?->amount_before_tax }}" required oninput="updateExpenseTotal()"></div>
        <div class="col-12 col-sm-4"><label class="form-label" for="expense-gst">GST amount</label><input class="form-control" type="number" id="expense-gst" min="0" step=".01" value="{{ $expense?->gst_amount ?? 0 }}" oninput="updateExpenseTotal()"></div>
        <div class="col-12 col-sm-4"><label class="form-label" for="expense-total">Total amount</label><input class="form-control" type="number" id="expense-total" value="{{ $expense?->total_amount ?? '0.00' }}" readonly></div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-payment-status">Payment status</label><select class="form-select" id="expense-payment-status">@foreach(['paid','partial','unpaid'] as $status)<option value="{{ $status }}" @selected(($expense?->payment_status ?? 'paid') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-12 col-sm-6"><label class="form-label" for="expense-payment-date">Payment date</label><input class="form-control" type="date" id="expense-payment-date" value="{{ $expense?->payment_date?->format('Y-m-d') }}"></div>
        <div class="col-12"><label class="form-label" for="expense-notes">Notes</label><textarea class="form-control" id="expense-notes" rows="2">{{ $expense?->notes }}</textarea></div>
    </div>
    <div class="d-flex justify-content-end gap-2 border-top mt-4 pt-3">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="expense-save" type="submit">{{ $expense ? 'Update expense' : 'Save expense' }}</button>
    </div>
</form>
