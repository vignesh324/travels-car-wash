(function() {
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[character]));

    window.initQuickTripExecution = function(root) {
        if (!root || root.dataset.initialized === 'true') return;
        root.dataset.initialized = 'true';

        const find = selector => root.querySelector(selector);
        const customer = find('#quick-exec-customer');
        const date = find('#quick-exec-date');
        const initialCustomerId = root.dataset.initialCustomerId || '';
        const initialTripDate = root.dataset.initialTripDate || date.value;
        const initialConfigurationId = root.dataset.initialConfigurationId || '';
        if (initialCustomerId) customer.value = initialCustomerId;
        if (initialTripDate) date.value = initialTripDate;
        const inputsElement = find('#quick-exec-inputs');
        const generateButton = find('#quick-exec-generate');
        const status = find('#quick-exec-status');
        const error = find('#quick-exec-error');
        let configurations = [];
        let rows = [];
        let unsaved = false;
        let currentCustomer = '';
        let currentDate = date.value;
        let rowId = 0;
        let requestId = 0;

        function updateTotals() {
            const totals = rows.reduce((sum, row) => {
                if (!row.configurationId) return sum;
                const pickup = Math.max(0, Number(row.pickupCount) || 0);
                const drop = Math.max(0, Number(row.dropCount) || 0);
                sum.pickup += pickup;
                sum.drop += drop;
                sum.trips += pickup + drop;
                sum.amount += (pickup + drop) * Number(row.configuration.per_trip_charge || 0);
                return sum;
            }, {pickup: 0, drop: 0, trips: 0, amount: 0});
            find('#quick-exec-total-pickup').textContent = totals.pickup;
            find('#quick-exec-total-drop').textContent = totals.drop;
            find('#quick-exec-total-trips').textContent = totals.trips;
            find('#quick-exec-total-amount').textContent = `₹${totals.amount.toFixed(2)}`;
        }

        function renderRows() {
            if (!rows.length) {
                inputsElement.innerHTML = `<p class="text-center text-muted mb-0">${currentCustomer
                    ? (configurations.length ? 'Select a configured Quick Trip below.' : 'No active Quick Trip configurations. Add one under Customers.')
                    : 'Select a customer to load active configurations.'}</p>`;
            } else {
                const row = rows[0];
                const configuration = row.configuration;
                const options = ['<option value="">Select configured trip</option>']
                    .concat(configurations.map(item => `<option value="${item.id}"${String(item.id) === String(row.configurationId) ? ' selected' : ''}>${escapeHtml(item.name)}</option>`))
                    .join('');
                const totalTrips = Number(row.pickupCount) + Number(row.dropCount);
                inputsElement.innerHTML = `
                    <div class="col-12 col-md-6">
                        <label for="quick-exec-config" class="form-label">Configured Quick Trip *</label>
                        <select id="quick-exec-config" class="form-select quick-exec-config" required>${options}</select>
                    </div>
                    <div class="col-12">
                        <section class="card bg-light border">
                            <div class="card-body">
                                <h4 class="h6 mb-3">${escapeHtml(configuration?.name || 'Quick Trip configuration')}</h4>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="small text-muted">Pickup Location</div>
                                        <div class="fw-medium">${escapeHtml(configuration?.pickup_location || '—')}</div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="small text-muted">Drop Location</div>
                                        <div class="fw-medium">${escapeHtml(configuration?.drop_location || '—')}</div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="small text-muted">Route</div>
                                        <div class="fw-medium">${escapeHtml(configuration?.route || '—')}</div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="small text-muted">Per Trip Charge</div>
                                        <div class="fw-medium">${configuration ? `₹${Number(configuration.per_trip_charge).toFixed(2)}` : '—'}</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="quick-exec-pickup-count" class="form-label">Pickup Count *</label>
                        <input id="quick-exec-pickup-count" type="number" min="0" step="1" class="form-control quick-exec-count" data-count="pickupCount" value="${row.pickupCount}" ${configuration ? '' : 'disabled'} required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="quick-exec-drop-count" class="form-label">Drop Count *</label>
                        <input id="quick-exec-drop-count" type="number" min="0" step="1" class="form-control quick-exec-count" data-count="dropCount" value="${row.dropCount}" ${configuration ? '' : 'disabled'} required>
                    </div>
                    <div class="col-12">
                        <div class="rounded border p-3 d-flex flex-wrap justify-content-between gap-3">
                            <span>Total trips: <strong id="quick-exec-row-total-trips">${configuration ? totalTrips : 0}</strong></span>
                            <span>Total amount: <strong id="quick-exec-row-total-amount">₹${configuration ? (totalTrips * Number(configuration.per_trip_charge)).toFixed(2) : '0.00'}</strong></span>
                        </div>
                    </div>`;
            }

            updateTotals();
            const hasConfiguration = rows.some(row => row.configurationId);
            generateButton.disabled = !currentCustomer || !hasConfiguration;
        }

        function makeRow(configuration = null) {
            return {
                id: ++rowId,
                configurationId: configuration?.id ? String(configuration.id) : '',
                configuration,
                pickupCount: Number(configuration?.pickup_count || 0),
                dropCount: Number(configuration?.drop_count || 0),
                baselinePickup: Number(configuration?.pickup_count || 0),
                baselineDrop: Number(configuration?.drop_count || 0),
            };
        }

        async function loadConfigurations(selectedConfigurationId = '') {
            const customerId = customer.value;
            const selectedDate = date.value;
            const thisRequest = ++requestId;
            currentCustomer = customerId;
            currentDate = selectedDate;
            unsaved = false;
            rows = [];
            configurations = [];
            renderRows();
            error.textContent = '';
            if (!customerId || !selectedDate) {
                status.textContent = 'Select a customer and date to begin.';
                return;
            }

            status.textContent = 'Loading active configurations…';
            const url = new URL(root.dataset.configurationsUrl, window.location.origin);
            url.searchParams.set('customer_id', customerId);
            url.searchParams.set('trip_date', selectedDate);
            try {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Unable to load configurations.');
                if (thisRequest !== requestId || customerId !== customer.value || selectedDate !== date.value) return;
                configurations = data;
                const selectedConfiguration = data.find(configuration =>
                    String(configuration.id) === String(selectedConfigurationId)
                ) || null;
                rows = data.length ? [makeRow(selectedConfiguration)] : [];
                renderRows();
                status.textContent = data.length
                    ? `${data.length} active configuration${data.length === 1 ? '' : 's'} loaded. Select one Quick Trip and enter its counts.`
                    : 'No active configurations found for this customer.';
            } catch (requestError) {
                if (thisRequest !== requestId) return;
                status.textContent = '';
                error.textContent = requestError.message;
            }
        }

        function confirmReset() {
            if (!unsaved) return true;
            return window.confirm('Discard the unsaved execution counts and rows before changing customer or date?');
        }

        customer.addEventListener('change', event => {
            if (!confirmReset()) {
                event.target.value = currentCustomer;
                return;
            }
            loadConfigurations();
        });

        date.addEventListener('change', event => {
            if (!confirmReset()) {
                event.target.value = currentDate;
                return;
            }
            loadConfigurations();
        });

        inputsElement.addEventListener('change', event => {
            if (!event.target.matches('.quick-exec-config')) return;
            const row = rows[0];
            if (!row) return;
            const selected = configurations.find(item => String(item.id) === event.target.value);
            row.configuration = selected || null;
            row.configurationId = selected ? String(selected.id) : '';
            row.pickupCount = Number(selected?.pickup_count || 0);
            row.dropCount = Number(selected?.drop_count || 0);
            row.baselinePickup = row.pickupCount;
            row.baselineDrop = row.dropCount;
            unsaved = true;
            renderRows();
        });

        inputsElement.addEventListener('input', event => {
            if (!event.target.matches('.quick-exec-count')) return;
            const row = rows[0];
            if (!row) return;
            row[event.target.dataset.count] = event.target.value === '' ? '' : Number(event.target.value);
            unsaved = true;
            const pickup = Number(row.pickupCount) || 0;
            const drop = Number(row.dropCount) || 0;
            const trips = row.configuration ? pickup + drop : 0;
            find('#quick-exec-row-total-trips').textContent = trips;
            find('#quick-exec-row-total-amount').textContent = `₹${(trips * Number(row.configuration?.per_trip_charge || 0)).toFixed(2)}`;
            updateTotals();
        });

        generateButton.addEventListener('click', async () => {
            const customerId = customer.value;
            const tripDate = date.value;
            const entries = rows.filter(row => row.configurationId).map(row => ({
                configuration_id: row.configurationId,
                pickup_count: row.pickupCount,
                drop_count: row.dropCount,
            }));
            error.textContent = '';
            if (!customerId || !tripDate || entries.length === 0) {
                error.textContent = 'Select a customer, trip date, and one configured Quick Trip.';
                return;
            }
            if (entries.some(entry => !Number.isInteger(Number(entry.pickup_count)) || Number(entry.pickup_count) < 0
                || !Number.isInteger(Number(entry.drop_count)) || Number(entry.drop_count) < 0)) {
                error.textContent = 'Pickup and drop counts must be non-negative whole numbers.';
                return;
            }

            generateButton.disabled = true;
            try {
                const response = await fetch(root.dataset.generateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        customer_id: customerId,
                        trip_date: tripDate,
                        entries,
                    }),
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || Object.values(result.errors || {}).flat().join(' ') || 'Unable to generate trips.');
                }
                await loadConfigurations(entries[0].configuration_id);
                status.textContent = result.message;
                document.dispatchEvent(new CustomEvent('quick-trip-execution:saved'));
            } catch (requestError) {
                error.textContent = requestError.message;
            } finally {
                generateButton.disabled = !rows.some(row => row.configurationId);
            }
        });

        if (initialCustomerId) loadConfigurations(initialConfigurationId);
    };
})();
