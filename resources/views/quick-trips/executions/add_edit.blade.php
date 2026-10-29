<form id="quick-trip-execution-form" class="quick-trip-execution p-3 p-md-4"
     data-configurations-url="{{ route('trips.quick-execution.configurations') }}"
     data-generate-url="{{ route('trips.quick-execution.generate') }}"
     data-initial-customer-id="{{ $initialCustomerId ?? '' }}"
     data-initial-trip-date="{{ $initialTripDate ?? now()->toDateString() }}"
     data-initial-configuration-id="{{ $initialConfigurationId ?? '' }}">
    <div class="row g-3 align-items-end mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <label for="quick-exec-customer" class="form-label">Customer *</label>
            <select id="quick-exec-customer" class="form-select" required>
                <option value="">Select customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) ($initialCustomerId ?? '') === (string) $customer->id)>{{ $customer->displayName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <label for="quick-exec-date" class="form-label">Trip Date *</label>
            <input id="quick-exec-date" type="date" value="{{ $initialTripDate ?? now()->toDateString() }}" class="form-control" required>
        </div>
    </div>

    <section class="card">
        <div class="card-header">
            <div>
                <h3 class="h6 mb-1">Quick Trip Details</h3>
                <p class="small text-muted mb-0">Select one configured Quick Trip and enter its pickup and drop counts. This saves one Quick Trip execution record, not individual Trips.</p>
            </div>
        </div>
        <div class="card-body">
            <div id="quick-exec-inputs" class="row g-3">
                <p class="text-center text-muted mb-0">Select a customer to load active configurations.</p>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-6 col-lg-3">
                    <div class="rounded border bg-light p-3 h-100">
                        <div class="small text-muted">Total Pickup Count</div>
                        <div id="quick-exec-total-pickup" class="fw-semibold">0</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="rounded border bg-light p-3 h-100">
                        <div class="small text-muted">Total Drop Count</div>
                        <div id="quick-exec-total-drop" class="fw-semibold">0</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="rounded border bg-light p-3 h-100">
                        <div class="small text-muted">Total Trip Count</div>
                        <div id="quick-exec-total-trips" class="fw-semibold">0</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="rounded border bg-light p-3 h-100">
                        <div class="small text-muted">Total Amount</div>
                        <div id="quick-exec-total-amount" class="fw-semibold">₹0.00</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <p id="quick-exec-status" class="small text-muted mb-0" aria-live="polite">Select a customer and date to begin.</p>
                <button type="submit" class="btn btn-primary" id="quick-exec-generate" disabled>Save Quick Trip Execution</button>
            </div>
            <p id="quick-exec-error" class="small text-danger mt-2 mb-0" role="alert"></p>
        </div>
    </section>
</div>
</form>
