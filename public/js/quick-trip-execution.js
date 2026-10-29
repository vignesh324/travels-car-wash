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

        function pricingForTrips(configuration, totalTrips) {
            const slabs = (configuration?.slabs || [])
                .filter(slab => slab.max_trips !== null && slab.max_trips !== ''
                    && Number.isFinite(Number(slab.max_trips)))
                .sort((left, right) => Number(left.max_trips) - Number(right.max_trips));
            const baseRateCents = Math.round(Number(configuration?.per_trip_charge || 0) * 100);
            let totalCents = 0;
            let previousThreshold = 0;
            let effectiveRateCents = baseRateCents;

            slabs.forEach(slab => {
                const threshold = Number(slab.max_trips);
                const segmentCount = Math.max(0, Math.min(totalTrips, threshold) - previousThreshold);
                totalCents += segmentCount * effectiveRateCents;

                if (totalTrips > threshold) {
                    effectiveRateCents = Math.round(Number(slab.per_trip_charge || 0) * 100);
                }
                previousThreshold = threshold;
            });

            if (totalTrips > previousThreshold) {
                totalCents += (totalTrips - previousThreshold) * effectiveRateCents;
            }

            return {
                rate: effectiveRateCents / 100,
                amount: totalCents / 100,
                slabs,
            };
        }

        function pricingDescription(row, totalTrips, pricing) {
            if (row.preservesSavedRate && row.savedTotalAmount != null) {
                return 'Saved execution total preserved; changing counts applies the current configured slabs.';
            }
            const exceededSlab = pricing.slabs
                .filter(slab => totalTrips > Number(slab.max_trips))
                .reduce((highest, slab) => !highest
                    || Number(slab.max_trips) > Number(highest.max_trips) ? slab : highest, null);

            return exceededSlab
                ? `First ${exceededSlab.max_trips} trips use the lower tiers; trips above ${exceededSlab.max_trips} use ₹${Number(exceededSlab.per_trip_charge).toFixed(2)} each.`
                : 'Base rate applies through the configured maximum.';
        }

        function amountForRow(row) {
            const totalTrips = Number(row.pickupCount || 0) + Number(row.dropCount || 0);
            if (row.preservesSavedRate) {
                return row.savedTotalAmount == null
                    ? totalTrips * Number(row.perTripCharge || 0)
                    : Number(row.savedTotalAmount);
            }

            return pricingForTrips(row.configuration, totalTrips).amount;
        }

        function updateTotals() {
            const totals = rows.reduce((sum, row) => {
                if (!row.configurationId) return sum;
                const pickup = Math.max(0, Number(row.pickupCount) || 0);
                const drop = Math.max(0, Number(row.dropCount) || 0);
                sum.pickup += pickup;
                sum.drop += drop;
                sum.trips += pickup + drop;
                sum.amount += amountForRow(row);
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
                const pricing = pricingForTrips(configuration, totalTrips);
                const amount = amountForRow(row);
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
                                        <div class="small text-muted">Rate on Last Trip</div>
                                        <div class="fw-medium">${configuration ? `₹${Number(row.perTripCharge).toFixed(2)}` : '—'}</div>
                                    </div>
                                </div>
                                ${configuration ? `<p class="small text-muted mt-3 mb-0">Rate tier: <span id="quick-exec-rate-tier">${escapeHtml(pricingDescription(row, totalTrips, pricing))}</span></p>` : ''}
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
                            <span>Total amount: <strong id="quick-exec-row-total-amount">₹${configuration ? amount.toFixed(2) : '0.00'}</strong></span>
                        </div>
                    </div>`;
            }

            updateTotals();
            const hasConfiguration = rows.some(row => row.configurationId);
            generateButton.disabled = !currentCustomer || !hasConfiguration;
        }

        function makeRow(configuration = null) {
            const pickupCount = Number(configuration?.pickup_count || 0);
            const dropCount = Number(configuration?.drop_count || 0);
            const totalTrips = pickupCount + dropCount;
            return {
                id: ++rowId,
                configurationId: configuration?.id ? String(configuration.id) : '',
                configuration,
                pickupCount,
                dropCount,
                baselinePickup: pickupCount,
                baselineDrop: dropCount,
                preservesSavedRate: Boolean(configuration?.has_execution && configuration.saved_per_trip_charge != null),
                savedTotalAmount: configuration?.saved_total_amount == null
                    ? null
                    : Number(configuration.saved_total_amount),
                perTripCharge: configuration?.has_execution && configuration.saved_per_trip_charge != null
                    ? Number(configuration.saved_per_trip_charge)
                    : pricingForTrips(configuration, totalTrips).rate,
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
            row.preservesSavedRate = Boolean(selected?.has_execution && selected.saved_per_trip_charge != null);
            row.savedTotalAmount = selected?.saved_total_amount == null ? null : Number(selected.saved_total_amount);
            row.perTripCharge = row.preservesSavedRate
                ? Number(selected.saved_per_trip_charge)
                : pricingForTrips(selected, row.pickupCount + row.dropCount).rate;
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
            row.preservesSavedRate = false;
            const pricing = pricingForTrips(row.configuration, trips);
            row.perTripCharge = pricing.rate;
            const rateLabel = [...inputsElement.querySelectorAll('.small.text-muted')]
                .find(element => element.textContent === 'Rate on Last Trip');
            if (rateLabel?.nextElementSibling) rateLabel.nextElementSibling.textContent = `₹${row.perTripCharge.toFixed(2)}`;
            find('#quick-exec-rate-tier').textContent = pricingDescription(row, trips, pricing);
            find('#quick-exec-row-total-trips').textContent = trips;
            find('#quick-exec-row-total-amount').textContent = `₹${pricing.amount.toFixed(2)}`;
            updateTotals();
        });

        root.addEventListener('submit', async event => {
            event.preventDefault();
            if (!root.reportValidity()) return;

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
                status.textContent = result.message;
                document.dispatchEvent(new CustomEvent('quick-trip-execution:saved', {
                    detail: {message: result.message},
                }));
            } catch (requestError) {
                error.textContent = requestError.message;
            } finally {
                generateButton.disabled = !rows.some(row => row.configurationId);
            }
        });

        if (initialCustomerId) loadConfigurations(initialConfigurationId);
    };
})();
