<form id="quick-trip-config-form" class="p-4" action="{{ $formUrl }}" onsubmit="window.saveQuickTripConfiguration(event)">
    <input type="hidden" id="quick-trip-config-id" value="{{ $configuration?->id ?? '' }}">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="quick-trip-customer" class="form-label">Customer *</label>
            <select id="quick-trip-customer" class="form-select" required>
                <option value="">Select customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $selectedCustomerId === (string) $customer->id)>{{ $customer->displayName() }}</option>
                @endforeach
            </select>
            <p id="err_quick-trip-config_customer_id" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6">
            <label for="quick-trip-name" class="form-label">Quick Trip Name *</label>
            <input id="quick-trip-name" value="{{ $configuration?->name }}" maxlength="255" class="form-control" required>
            <p id="err_quick-trip-config_name" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6">
            <label for="quick-trip-pickup" class="form-label">Pickup Location *</label>
            <input id="quick-trip-pickup" value="{{ $configuration?->pickup_location }}" maxlength="255" class="form-control" required>
            <p id="err_quick-trip-config_pickup_location" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6">
            <label for="quick-trip-drop" class="form-label">Drop Location *</label>
            <input id="quick-trip-drop" value="{{ $configuration?->drop_location }}" maxlength="255" class="form-control" required>
            <p id="err_quick-trip-config_drop_location" class="field-error small text-danger"></p>
        </div>
        <div class="col-12 col-md-6">
            <label for="quick-trip-route" class="form-label">Route <span class="text-muted">(optional)</span></label>
            <input id="quick-trip-route" value="{{ $configuration?->route }}" maxlength="255" class="form-control">
            <p class="form-text">Routes are stored as text; this application has no separate route master.</p>
        </div>
        <div class="col-12 col-md-6">
            <label for="quick-trip-charge" class="form-label">Base Per Trip Charge (₹) *</label>
            <input id="quick-trip-charge" type="number" min="0" step="0.01" value="{{ $configuration?->per_trip_charge }}" class="form-control" required>
            <p id="err_quick-trip-config_per_trip_charge" class="field-error small text-danger mb-0"></p>
            <p class="form-text">Used when the total trip count does not exceed any configured maximum.</p>
        </div>
        <div class="col-12">
            <section class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="h6 mb-1">Trip-count price slabs</h3>
                        <p class="small text-muted mb-0">A slab rate applies only when total pickup + drop trips are greater than its maximum. Otherwise, the base rate applies. If several maximums are exceeded, the highest one applies.</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="quick-trip-add-slab">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add price slab
                    </button>
                </div>
                <div class="card-body">
                    <div id="quick-trip-slab-rows" class="d-flex flex-column gap-2"
                         data-slabs='@json($configuration?->slabs ?? [])'>
                        <p class="small text-muted mb-0" data-slab-empty>No slabs yet. The base per-trip charge will be used.</p>
                    </div>
                    <p id="err_quick-trip-config_quick_trip_slabs" class="field-error small text-danger mt-2 mb-0"></p>
                </div>
            </section>
        </div>
        <div class="col-12 col-md-6">
            <label for="quick-trip-active" class="form-label">Status *</label>
            <select id="quick-trip-active" class="form-select" required>
                <option value="1" @selected($configuration?->is_active ?? true)>Active</option>
                <option value="0" @selected($configuration && !$configuration->is_active)>Inactive</option>
            </select>
        </div>
        <div class="col-12">
            <label for="quick-trip-remarks" class="form-label">Remarks</label>
            <textarea id="quick-trip-remarks" rows="2" class="form-control">{{ $configuration?->remarks }}</textarea>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" id="quick-trip-config-save" class="btn btn-primary btn-sm">{{ $configuration ? 'Update Configuration' : 'Add Configuration' }}</button>
    </div>
</form>
