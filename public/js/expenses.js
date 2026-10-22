(() => {
    const page = document.getElementById('expenses-page');
    if (!page) return;

    const type = page.dataset.type;
    const isVehicle = type === 'vehicle';
    const byId = id => document.getElementById(id);
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));
    let table;

    function loadExpenses() {
        if (table) table.ajax.reload();
    }

    function initTable() {
        const subjectColumn = isVehicle
            ? { data: 'vehicle', orderable: false, searchable: false, render: vehicle => escapeHtml(vehicle ? `${vehicle.registration_number || ''} ${vehicle.name || ''}`.trim() : '—') }
            : { data: 'source', render: source => escapeHtml(source === 'carwash' ? 'Car wash' : 'General') };

        table = window.initDataTable('expenses-dt', page.dataset.dataUrl, [
            { data: 'expense_date', render: value => fmt.date(value) },
            { data: 'description', render: (value, _, row) => {
                const description = value || row.vendor_name || row.notes || 'Expense';
                return `<div class="fw-medium">${escapeHtml(description)}</div>${row.vendor_name ? `<small class="text-muted">${escapeHtml(row.vendor_name)}</small>` : ''}`;
            }},
            { data: 'category', orderable: false, searchable: false, render: category => escapeHtml(category?.name || '—') },
            subjectColumn,
            { data: 'total_amount', className: 'text-end', render: value => `<span class="fw-semibold">${fmt.currency(value)}</span>` },
            { data: 'payment_status', render: value => {
                const status = value || 'unpaid';
                const badge = status === 'paid' ? 'success' : status === 'partial' ? 'warning' : 'danger';
                return `<span class="badge text-bg-${badge}">${escapeHtml(status)}</span>`;
            }},
            { data: 'actions', orderable: false, searchable: false, className: 'text-end' },
        ], () => {
            const params = {
                type,
                category_id: byId('expense-category-filter').value,
                payment_status: byId('expense-status-filter').value,
                date_from: byId('expense-date-from').value,
                date_to: byId('expense-date-to').value,
            };
            params[isVehicle ? 'vehicle_id' : 'source'] = byId(isVehicle ? 'expense-vehicle-filter' : 'expense-source-filter').value;
            return params;
        });
    }

    window.updateExpenseTotal = function() {
        byId('expense-total').value = (Number(byId('expense-before-tax').value || 0) + Number(byId('expense-gst').value || 0)).toFixed(2);
    };

    window.saveExpense = async function(event) {
        event.preventDefault();
        const form = byId('expense-form');
        const id = byId('expense-id').value;
        const data = {
            expense_date: byId('expense-date').value,
            category_id: byId('expense-category').value,
            description: byId('expense-description').value,
            vendor_name: byId('expense-vendor').value,
            reference_number: byId('expense-reference').value,
            amount_before_tax: Number(byId('expense-before-tax').value || 0),
            gst_amount: Number(byId('expense-gst').value || 0),
            total_amount: Number(byId('expense-total').value || 0),
            payment_mode: byId('expense-payment-mode').value,
            payment_status: byId('expense-payment-status').value,
            payment_date: byId('expense-payment-date').value || null,
            notes: byId('expense-notes').value,
        };
        data[form.dataset.type === 'vehicle' ? 'vehicle_id' : 'source'] = byId(form.dataset.type === 'vehicle' ? 'expense-vehicle' : 'expense-source').value;

        const button = byId('expense-save');
        button.disabled = true;
        try {
            const response = id ? await api.put(form.action, data) : await api.post(form.action, data);
            if (!response.ok) {
                showErrors(await extractErrors(response));
                return;
            }
            closeModal('ajax-form-modal');
            toast.success(id ? 'Expense updated' : 'Expense added');
            loadExpenses();
        } catch (error) {
            toast.error(error.message || 'Unable to save expense');
        } finally {
            button.disabled = false;
        }
    };

    async function openForm(url) {
        try {
            await window.loadAddEditModal(url);
            window.updateExpenseTotal();
        } catch (error) {
            toast.error(error.message || 'Unable to load expense form');
        }
    }

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-action^="expenses."]');
        if (!button) return;

        if (button.dataset.action === 'expenses.add') {
            await openForm(page.dataset.formUrl);
        } else if (button.dataset.action === 'expenses.edit') {
            await openForm(`${page.dataset.formUrl}/${encodeURIComponent(button.dataset.expenseId)}`);
        } else if (button.dataset.action === 'expenses.view') {
            window.openRecordView({
                url: button.dataset.viewUrl,
                title: `${isVehicle ? 'Vehicle' : 'Business'} expense details`,
                fields: [
                    { label: 'Date', value: record => fmt.date(record.expense_date) },
                    { label: 'Category', value: record => record.category?.name },
                    { label: 'Vendor', value: 'vendor_name' },
                    { label: 'Vehicle', value: record => record.vehicle ? `${record.vehicle.registration_number || ''} ${record.vehicle.name || ''}`.trim() : null },
                    { label: 'Description', value: 'description', fullWidth: true },
                    { label: 'Payment mode', value: 'payment_mode' },
                    { label: 'Payment status', value: record => record.payment_status ? record.payment_status.replace(/^\w/, letter => letter.toUpperCase()) : null },
                    { label: 'Payment date', value: record => fmt.date(record.payment_date) },
                    { label: 'Amount before tax', value: record => fmt.currency(record.amount_before_tax) },
                    { label: 'Tax amount', value: record => fmt.currency(record.gst_amount) },
                    { label: 'Total amount', value: record => fmt.currency(record.total_amount) },
                    { label: 'Notes', value: 'notes', fullWidth: true },
                ],
            });
        } else if (button.dataset.action === 'expenses.delete') {
            if (!confirm('Delete this expense? This action cannot be undone.')) return;
            try {
                const url = page.dataset.deleteUrl.replace('__ID__', encodeURIComponent(button.dataset.expenseId));
                const response = await api.delete(url);
                if (!response.ok) throw new Error('Unable to delete expense');
                toast.success('Expense deleted');
                loadExpenses();
            } catch (error) {
                toast.error(error.message || 'Unable to delete expense');
            }
        }
    });

    initTable();
    if (new URLSearchParams(window.location.search).get('action') === 'new') openForm(page.dataset.formUrl);
})();
