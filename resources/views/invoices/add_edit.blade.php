<form id="invoice-form" class="card" action="{{ $formUrl }}">
    <input type="hidden" id="inv-edit-uuid" value="{{ $invoice?->uuid ?? '' }}">
    <header class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h3 class="h5 mb-1">{{ $invoice ? 'Review invoice details' : 'Prepare a new invoice' }}</h3>
            <p class="small text-muted mb-0">Set the billing details, then choose customer trips or add manual line items.</p>
        </div>
        <span class="badge text-bg-secondary">
            <i class="bi {{ $invoice ? 'bi-pencil-square' : 'bi-file-earmark-plus' }}" aria-hidden="true"></i>
            {{ $invoice ? 'Editing' : 'New draft' }}
        </span>
    </header>
    <div class="card-body d-flex flex-column gap-3">
        @if($invoice)
            <section class="bg-light rounded-2 px-4 py-3 d-flex flex-column gap-2">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <p class="small text-muted mb-1">Customer</p>
                        <p class="small fw-medium text-dark mb-0">{{ $invoice->customer_name }}</p>
                    </div>
                    <span class="badge text-bg-primary">{{ ucfirst($invoice->source) }}</span>
                </div>
                <div class="row row-cols-1 row-cols-sm-3 g-2 pt-2 border-top border-secondary-subtle small">
                    <div><p class="text-muted mb-1">Invoice total</p><p class="fw-semibold text-dark mb-0">₹{{ number_format((float) $invoice->total_amount, 2) }}</p></div>
                    <div><p class="text-muted mb-1">Amount paid</p><p class="fw-semibold text-success mb-0">₹{{ number_format((float) $invoice->amount_paid, 2) }}</p></div>
                    <div><p class="text-muted mb-1">Balance due</p><p class="fw-semibold {{ (float) $invoice->balance_due > 0 ? 'text-danger' : 'text-success' }} mb-0">₹{{ number_format((float) $invoice->balance_due, 2) }}</p></div>
                </div>
                @if((float) $invoice->amount_paid > 0)
                    <p class="small text-warning-emphasis bg-warning-subtle rounded-1 px-2 py-1 mb-0">This invoice has recorded payments. Editing line items will recalculate the balance due.</p>
                @endif
            </section>
        @else
            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label class="form-label" for="inp_inv_customer_id">Customer *</label>
                    <select id="inp_inv_customer_id" onchange="onInvCustomerChange(this.value)" required class="form-select">
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->displayName() }}</option>
                        @endforeach
                    </select>
                    <p id="err_inv_customer_id" class="field-error small text-danger mt-1"></p>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="inp_inv_source">Source *</label>
                    <select id="inp_inv_source" class="form-select">
                        <option value="travels">Travels</option>
                        <option value="carwash">Car Wash</option>
                        <option value="mixed">Mixed</option>
                    </select>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="inp_inv_invoice_date">Invoice date *</label>
                <input id="inp_inv_invoice_date" type="date" value="{{ $invoice?->invoice_date?->format('Y-m-d') ?? now()->toDateString() }}" required class="form-control">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="inp_inv_due_date">Due date</label>
                <input id="inp_inv_due_date" type="date" value="{{ $invoice?->due_date?->format('Y-m-d') ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="inp_inv_place_of_supply">Place of supply</label>
                <input id="inp_inv_place_of_supply" value="{{ $invoice?->place_of_supply ?? '' }}" placeholder="State name" class="form-control">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="inp_inv_payment_terms">Payment terms</label>
                <input id="inp_inv_payment_terms" value="{{ $invoice?->payment_terms ?? '' }}" placeholder="Net 30" class="form-control">
            </div>
        </div>

        @unless($invoice)
            <section class="card card-body bg-light">
                <p class="small fw-semibold text-muted text-uppercase mb-2">Regular trip invoice</p>
                <p class="small text-muted">Load completed, uninvoiced trips for one customer and date range. Selected trips are grouped by vehicle and route; each trip record counts as one day.</p>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="inv-trip-date-from">Trip date from</label>
                        <input class="form-control" type="date" id="inv-trip-date-from" value="{{ now()->startOfMonth()->toDateString() }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="inv-trip-date-to">Trip date to</label>
                        <input class="form-control" type="date" id="inv-trip-date-to" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary w-100" onclick="loadInvTrips()">Load customer trips</button>
                    </div>
                </div>
                <div class="small text-muted mt-2" id="inv-trip-status" aria-live="polite">Select a customer to load trips.</div>
                <p id="err_inv_trip_ids" class="field-error small text-danger mt-1"></p>
                <div id="inv-trips-list" class="mt-2"></div>
                <div id="inv-trip-summary" class="mt-3"></div>
            </section>
        @endunless

        <section id="inv-manual-items" class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="small fw-semibold text-muted text-uppercase mb-0">Line items</p>
                <button type="button" onclick="addInvLine(null)" class="small text-primary fw-medium btn btn-link btn-sm">+ Add line</button>
            </div>
            <p id="err_inv_items" class="field-error small text-danger mb-1"></p>
            <div id="inv-lines-wrap" class="d-flex flex-column gap-2"></div>
            <div class="text-end mt-2 small fw-bold text-primary">
                Grand total (preview): <span id="inv-grand-total">₹0.00</span>
            </div>
        </section>

        <div>
            <label class="form-label" for="inp_inv_notes">Notes</label>
            <textarea id="inp_inv_notes" rows="2" class="form-control">{{ $invoice?->notes ?? '' }}</textarea>
        </div>

        <div class="d-flex justify-content-end gap-3 pt-4 border-top">
            <button type="button" onclick="closeModal('ajax-form-modal')" class="btn btn-outline-secondary">Cancel</button>
            <button type="button" onclick="saveInvoice()" id="inv-save-btn" class="btn btn-primary">{{ $invoice ? 'Update invoice' : 'Create invoice' }}</button>
        </div>
    </div>
</form>
