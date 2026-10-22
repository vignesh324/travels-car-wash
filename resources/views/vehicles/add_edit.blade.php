<form id="vehicle-form" class="p-4" action="{{ $formUrl }}" data-uuid="{{ $vehicle?->uuid ?? '' }}">
    <input type="hidden" id="veh-edit-uuid" value="{{ $vehicle?->uuid ?? '' }}">
    <div class="row g-3">
        @foreach([
            ['registration_number', 'Registration Number', 'text', 'required'],
            ['name', 'Vehicle Name', 'text', 'required'],
            ['model', 'Model', 'text', ''],
            ['manufacturer', 'Manufacturer', 'text', ''],
            ['seating_capacity', 'Seating Capacity', 'number', ''],
            ['current_odometer', 'Current Odometer (km)', 'number', ''],
            ['purchase_date', 'Purchase Date', 'date', ''],
            ['default_driver_name', 'Default Driver Name', 'text', ''],
            ['default_driver_phone', 'Default Driver Phone', 'tel', ''],
            ['insurance_expiry', 'Insurance Expiry', 'date', ''],
            ['permit_expiry', 'Permit Expiry', 'date', ''],
            ['fitness_expiry', 'Fitness Expiry', 'date', ''],
            ['puc_expiry', 'PUC Expiry', 'date', ''],
        ] as [$field, $label, $inputType, $required])
            <div class="col-12 col-md-6">
                <label class="form-label" for="inp_veh_{{ $field }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
                <input id="inp_veh_{{ $field }}" name="{{ $field }}" type="{{ $inputType }}"
                    class="form-control inp-error" {{ $required }} value="{{ $vehicle?->{$field} ?? '' }}">
                <p id="err_veh_{{ $field }}" class="field-error small text-danger"></p>
            </div>
        @endforeach
        <div class="col-12 col-md-6">
            <label class="form-label" for="inp_veh_type">Type</label>
            <select id="inp_veh_type" name="type" class="form-select inp-error">
                <option value="">Select type</option>
                @foreach($vehicleTypes as $type)<option value="{{ $type }}" @selected(($vehicle?->type ?? '') === $type)>{{ $type }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="inp_veh_fuel_type">Fuel Type *</label>
            <select id="inp_veh_fuel_type" name="fuel_type" class="form-select inp-error" required>
                @foreach($fuelTypes as $type)<option value="{{ $type }}" @selected(($vehicle?->fuel_type ?? 'Diesel') === $type)>{{ $type }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="inp_veh_ownership_type">Ownership Type *</label>
            <select id="inp_veh_ownership_type" name="ownership_type" class="form-select inp-error" required>
                @foreach($ownershipTypes as $type)<option value="{{ $type }}" @selected(($vehicle?->ownership_type ?? 'Owned') === $type)>{{ $type }}</option>@endforeach
            </select>
        </div>
        <div class="col-12 col-md-6 d-flex align-items-end">
            <div class="form-check mb-2">
                <input id="inp_veh_is_active" name="is_active" type="checkbox" class="form-check-input" @checked($vehicle?->is_active ?? true)>
                <label class="form-check-label" for="inp_veh_is_active">Active vehicle</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="inp_veh_notes">Notes</label>
            <textarea id="inp_veh_notes" name="notes" rows="2" class="form-control">{{ $vehicle?->notes ?? '' }}</textarea>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" onclick="saveVehicle()" id="veh-save-btn" class="btn btn-primary btn-sm">{{ $vehicle ? 'Update Vehicle' : 'Save Vehicle' }}</button>
    </div>
</form>
