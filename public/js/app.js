window.baseUrl = window.appConfig.baseUrl;

function syncSidebarAccessibility(isOpen) {
    const sidebar = document.getElementById('sidebar');
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    const isHidden = isMobile ? !isOpen : sidebar.classList.contains('collapsed');
    const focusableElements = sidebar.querySelectorAll(
        'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [contenteditable], [tabindex]'
    );

    sidebar.inert = isHidden;
    if (isHidden) {
        sidebar.setAttribute('aria-hidden', 'true');
        focusableElements.forEach(element => {
            if (element.hasAttribute('data-sidebar-original-tabindex')) return;
            element.setAttribute(
                'data-sidebar-original-tabindex',
                element.hasAttribute('tabindex') ? element.getAttribute('tabindex') : ''
            );
            element.setAttribute('tabindex', '-1');
        });
        return;
    }

    sidebar.removeAttribute('aria-hidden');
    focusableElements.forEach(element => {
        if (!element.hasAttribute('data-sidebar-original-tabindex')) return;
        const originalTabindex = element.getAttribute('data-sidebar-original-tabindex');
        if (originalTabindex === '') {
            element.removeAttribute('tabindex');
        } else {
            element.setAttribute('tabindex', originalTabindex);
        }
        element.removeAttribute('data-sidebar-original-tabindex');
    });
}

function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    if (isMobile) {
        sidebar.classList.add('show');
        document.getElementById('sidebar-backdrop').classList.add('show');
    } else {
        sidebar.classList.remove('collapsed');
    }
    syncSidebarAccessibility(true);
    const toggle = document.getElementById('sidebar-toggle');
    toggle.setAttribute('aria-expanded', 'true');
    if (isMobile) sidebar.querySelector('.sidebar-close').focus();
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;

    if (isMobile) {
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
        return;
    }

    sidebar.classList.toggle('collapsed');
    const isOpen = !sidebar.classList.contains('collapsed');
    syncSidebarAccessibility(isOpen);
    document.getElementById('sidebar-toggle').setAttribute('aria-expanded', String(isOpen));
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    if (!isMobile) return;

    sidebar.classList.remove('show');
    document.getElementById('sidebar-backdrop').classList.remove('show');
    syncSidebarAccessibility(false);
    const toggle = document.getElementById('sidebar-toggle');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.focus();
}

let activeQuickMenuToggle = null;

function positionQuickDropdown(dropdown, toggle) {
    const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    const top = isMobile
        ? Math.max(8, toggle.getBoundingClientRect().top - dropdown.offsetHeight - 8)
        : toggle.getBoundingClientRect().bottom + 6;
    dropdown.style.setProperty('--quick-dropdown-top', `${top}px`);
    const clientWidth = document.documentElement.clientWidth;
    dropdown.style.setProperty('--quick-dropdown-max-width', `${Math.max(0, clientWidth - 24)}px`);
    dropdown.style.setProperty('--quick-dropdown-right', '12px');

    const overflow = dropdown.getBoundingClientRect().right - (clientWidth - 12);
    if (overflow > 0) {
        dropdown.style.setProperty('--quick-dropdown-right', `${12 + Math.ceil(overflow)}px`);
    }
}

function toggleQuickMenu(toggleId = 'quick-action-toggle') {
    const dropdown = document.getElementById('quick-dropdown');
    const toggle = document.getElementById(toggleId);
    const isOpening = !dropdown.classList.contains('show');

    if (activeQuickMenuToggle && activeQuickMenuToggle !== toggle) {
        activeQuickMenuToggle.setAttribute('aria-expanded', 'false');
    }
    dropdown.classList.toggle('show', isOpening);
    toggle.setAttribute('aria-expanded', String(isOpening));
    if (isOpening) {
        activeQuickMenuToggle = toggle;
        positionQuickDropdown(dropdown, toggle);
    } else if (!isOpening) {
        activeQuickMenuToggle = null;
        dropdown.style.removeProperty('--quick-dropdown-top');
        dropdown.style.removeProperty('--quick-dropdown-right');
        dropdown.style.removeProperty('--quick-dropdown-max-width');
    }
}

window.openSidebar = openSidebar;
window.closeSidebar = closeSidebar;
window.toggleSidebar = toggleSidebar;
window.toggleQuickMenu = toggleQuickMenu;

document.addEventListener('click', event => {
    const menu = document.getElementById('quick-dropdown');
    if (menu.classList.contains('show') && !event.target.closest('#quick-action-menu, #mobile-quick-action-toggle')) {
        menu.classList.remove('show');
        if (activeQuickMenuToggle) activeQuickMenuToggle.setAttribute('aria-expanded', 'false');
        activeQuickMenuToggle = null;
        menu.style.removeProperty('--quick-dropdown-top');
        menu.style.removeProperty('--quick-dropdown-right');
        menu.style.removeProperty('--quick-dropdown-max-width');
    }
});

window.addEventListener('resize', () => {
    const sidebar = document.getElementById('sidebar');
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    if (!isMobile) {
        sidebar.classList.remove('show');
        document.getElementById('sidebar-backdrop').classList.remove('show');
    }
    const isOpen = isMobile
        ? sidebar.classList.contains('show')
        : !sidebar.classList.contains('collapsed');
    syncSidebarAccessibility(isOpen);
    document.getElementById('sidebar-toggle').setAttribute('aria-expanded', String(isOpen));

    const dropdown = document.getElementById('quick-dropdown');
    if (dropdown.classList.contains('show') && activeQuickMenuToggle?.isConnected) {
        positionQuickDropdown(dropdown, activeQuickMenuToggle);
    }

    document.querySelectorAll('table.mobile-data-table').forEach(table => {
        if ($.fn.DataTable.isDataTable(table)) {
            decorateMobileDataTable($(table).DataTable());
        }
    });
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;

    const menu = document.getElementById('quick-dropdown');
    if (menu.classList.contains('show')) {
        menu.classList.remove('show');
        const quickMenuToggle = activeQuickMenuToggle;
        if (quickMenuToggle) {
            quickMenuToggle.setAttribute('aria-expanded', 'false');
            if (quickMenuToggle.isConnected) quickMenuToggle.focus();
        }
        activeQuickMenuToggle = null;
        menu.style.removeProperty('--quick-dropdown-top');
        menu.style.removeProperty('--quick-dropdown-right');
        menu.style.removeProperty('--quick-dropdown-max-width');
    }

    if (document.getElementById('sidebar').classList.contains('show')) {
        closeSidebar();
    }

    closeMobileMoreMenu();
});

syncSidebarAccessibility(document.getElementById('sidebar').classList.contains('show'));

function closeMobileMoreMenu(returnFocus = false) {
    const menu = document.getElementById('mobile-more-menu');
    const toggle = document.getElementById('mobile-more-toggle');
    if (!menu || !toggle) return;
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (returnFocus) toggle.focus();
}

document.getElementById('mobile-more-toggle')?.addEventListener('click', event => {
    const toggle = event.currentTarget;
    const menu = document.getElementById('mobile-more-menu');
    const isOpening = menu.hidden;
    menu.hidden = !isOpening;
    toggle.setAttribute('aria-expanded', String(isOpening));
});

document.addEventListener('click', event => {
    const menu = document.getElementById('mobile-more-menu');
    if (menu && !menu.hidden && !event.target.closest('#mobile-bottom-nav')) {
        closeMobileMoreMenu();
    }
});

window.api = {
    async request(method, url, data) {
        const fullUrl = url.startsWith('http') ? url : window.baseUrl + url;
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf,
            },
        };
        if (data !== undefined) options.body = JSON.stringify(data);
        const response = await fetch(fullUrl, options);
        if (response.status === 401) {
            window.location.href = window.appConfig.loginUrl;
            throw new Error('Unauthenticated');
        }
        return response;
    },
    get(url, params) {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request('GET', url + query);
    },
    post: (url, data) => window.api.request('POST', url, data),
    put: (url, data) => window.api.request('PUT', url, data),
    delete: url => window.api.request('DELETE', url),
};

window.fmt = {
    currency(value) {
        const number = parseFloat(value ?? 0);
        if (isNaN(number)) return '₹0.00';
        return '₹' + number.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    date(value) {
        if (!value) return '—';
        const date = new Date(value);
        return isNaN(date.getTime()) ? '—' : date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    },
    today() {
        return new Date().toISOString().split('T')[0];
    },
    nowLocal() {
        const date = new Date();
        date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
        return date.toISOString().slice(0, 16);
    },
};

window.badge = function(text, color) {
    const colors = {
        green: 'text-bg-success',
        red: 'text-bg-danger',
        yellow: 'text-bg-warning',
        blue: 'text-bg-primary',
        gray: 'text-bg-secondary',
        purple: 'text-bg-secondary',
        orange: 'text-bg-warning',
        cyan: 'text-bg-info',
    };
    return `<span class="badge ${colors[color] || colors.gray}">${text}</span>`;
};

window.paymentStatusColor = status => status === 'paid' ? 'green' : status === 'partial' ? 'yellow' : status === 'unpaid' ? 'red' : 'gray';
window.tripStatusColor = status => status === 'completed' ? 'green' : status === 'draft' ? 'yellow' : status === 'cancelled' ? 'red' : 'gray';
window.invoiceStatusColor = status => status === 'paid' ? 'green' : status === 'partial' ? 'yellow' : status === 'cancelled' ? 'red' : status === 'issued' ? 'blue' : 'gray';

window.extractErrors = async function(response) {
    try {
        const data = await response.clone().json();
        if (data.errors) {
            const errors = {};
            Object.entries(data.errors).forEach(([key, value]) => {
                errors[key] = Array.isArray(value) ? value[0] : value;
            });
            return errors;
        }
        return { _: data.message || 'An error occurred' };
    } catch {
        return { _: 'An error occurred' };
    }
};

window.showErrors = function(errors, prefix) {
    document.querySelectorAll('.field-error').forEach(element => element.textContent = '');
    document.querySelectorAll('.is-invalid').forEach(element => element.classList.remove('is-invalid'));
    if (errors._) {
        toast.error(errors._);
        return;
    }
    let firstUnmapped = null;
    Object.entries(errors).forEach(([field, message]) => {
        const key = prefix ? prefix + '_' + field : field;
        const error = document.getElementById('err_' + key);
        if (error) error.textContent = message;
        else if (!firstUnmapped) firstUnmapped = message;
        const input = document.getElementById('inp_' + key);
        if (input) input.classList.add('is-invalid');
    });
    if (firstUnmapped) toast.error(firstUnmapped);
};

window.openModal = function(id) {
    const element = document.getElementById(id);
    if (!element) return;
    const title = element.querySelector('h1, h2, h3, h4, h5, h6');
    if (title) {
        if (!title.id) title.id = `${id}-title`;
        element.setAttribute('aria-labelledby', title.id);
    }
    const modal = bootstrap.Modal.getOrCreateInstance(element);
    element.addEventListener('shown.bs.modal', () => {
        const focusable = element.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
        if (focusable) focusable.focus({ preventScroll: true });
    }, { once: true });
    modal.show();
};

window.closeModal = function(id) {
    const element = document.getElementById(id);
    if (element) bootstrap.Modal.getOrCreateInstance(element).hide();
};

window.openPdfUrl = async function(apiPath) {
    try {
        const response = await window.api.get(apiPath);
        if (!response.ok) {
            toast.error('Failed to generate PDF');
            return;
        }
        const blobUrl = URL.createObjectURL(await response.blob());
        const tab = window.open(blobUrl, '_blank');
        if (tab) tab.addEventListener('load', () => URL.revokeObjectURL(blobUrl), { once: true });
    } catch {
        toast.error('Failed to generate PDF');
    }
};

window.renderPagination = function(containerId, page, totalPages, total, onPage) {
    const container = document.getElementById(containerId);
    if (!container) return;
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    container.innerHTML = `
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between small">
            <span class="text-muted">Page ${page} of ${totalPages} <span class="text-secondary">(${total} total)</span></span>
            <div class="d-flex gap-1">
                <button onclick="(${onPage})(${page - 1})" ${page === 1 ? 'disabled' : ''} class="btn btn-outline-secondary btn-sm">← Prev</button>
                <button onclick="(${onPage})(${page + 1})" ${page === totalPages ? 'disabled' : ''} class="btn btn-outline-secondary btn-sm">Next →</button>
            </div>
        </div>`;
};

window.dtAjax = function(url, extraParams) {
    return {
        url,
        type: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || ''),
        },
        data(data) {
            if (typeof extraParams === 'function') Object.assign(data, extraParams());
            else if (extraParams) Object.assign(data, extraParams);
            return data;
        },
    };
};

function mobileDataTableDetails(row) {
    const table = row.node().closest('table');
    const headers = Array.from(table.querySelectorAll('thead tr:first-child th'));
    const cells = Array.from(row.node().cells);
    const details = document.createElement('div');
    details.className = 'mobile-row-details';

    cells.slice(3).forEach((cell, offset) => {
        const index = offset + 3;
        const item = document.createElement('div');
        const label = document.createElement('span');
        const value = document.createElement('div');
        item.className = 'mobile-row-detail';
        label.className = 'mobile-row-detail-label';
        value.className = 'mobile-row-detail-value';
        label.textContent = headers[index]?.textContent.trim() || `Column ${index + 1}`;
        value.innerHTML = cell.innerHTML;
        value.querySelectorAll('[id]').forEach(element => element.removeAttribute('id'));
        item.append(label, value);
        details.append(item);
    });

    return details;
}

function setMobileRowToggleState(button, expanded) {
    button.classList.toggle('btn-success', !expanded);
    button.classList.toggle('btn-danger', expanded);
    button.setAttribute('aria-expanded', String(expanded));
    button.setAttribute('aria-label', `${expanded ? 'Hide' : 'Show'} additional row details`);
    button.innerHTML = `<i class="bi bi-${expanded ? 'dash' : 'plus'}-lg" aria-hidden="true"></i>`;
}

function decorateMobileDataTable(api) {
    const table = api.table().node();
    const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    table.classList.add('mobile-data-table');
    table.closest('.table-responsive')?.classList.add('mobile-no-scroll');

    if (!table.dataset.mobileRowsBound) {
        table.addEventListener('click', event => {
            const button = event.target.closest('.mobile-row-toggle');
            if (!button || !window.matchMedia('(max-width: 767.98px)').matches) return;
            const row = $(button.closest('tr')).closest('table').DataTable().row(button.closest('tr'));
            if (row.child.isShown()) {
                row.child.hide();
                setMobileRowToggleState(button, false);
            } else {
                row.child(mobileDataTableDetails(row), 'mobile-data-table-child').show();
                setMobileRowToggleState(button, true);
            }
        });
        table.dataset.mobileRowsBound = 'true';
    }

    api.rows({ page: 'current' }).every(function() {
        const row = this;
        const node = row.node();
        const firstCell = node.cells[0];
        if (!firstCell) return;

        if (!isMobile) {
            row.child.hide();
            firstCell.querySelector('.mobile-row-toggle')?.remove();
            return;
        }

        if (node.cells.length < 4) return;
        let button = firstCell.querySelector('.mobile-row-toggle');
        if (!button) {
            button = document.createElement('button');
            button.type = 'button';
            button.className = 'mobile-row-toggle btn';
            firstCell.prepend(button);
        }

        setMobileRowToggleState(button, row.child.isShown());
    });
}

window.initDataTable = function(tableId, ajaxUrl, columns, extraParams, options) {
    if ($.fn.DataTable.isDataTable('#' + tableId)) {
        $('#' + tableId).DataTable().destroy();
    }
    const config = Object.assign({
        serverSide: true,
        processing: true,
        searching: true,
        ajax: window.dtAjax(ajaxUrl, extraParams),
        columns,
        order: [[0, 'desc']],
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50],
        language: {
            processing: '<span class="spinner-border spinner-border-sm me-2"></span>Loading…',
            emptyTable: 'No records found',
            zeroRecords: 'No matching records found',
        },
    }, options || {});
    const drawCallback = config.drawCallback;
    config.drawCallback = function(settings) {
        if (drawCallback) drawCallback.call(this, settings);
        decorateMobileDataTable(this.api());
    };
    return $('#' + tableId).DataTable(config);
};

window.loadAddEditModal = async function(url) {
    const response = await window.api.get(url);
    if (!response.ok) throw new Error(`Unable to load form (${response.status})`);

    const form = await response.json();
    const modal = document.getElementById('ajax-form-modal');
    const body = document.getElementById('ajax-form-modal-body');
    if (!modal || !body || !form.html || !form.pageTitle || !form.formUrl) {
        throw new Error('The form response is incomplete');
    }

    body.innerHTML = form.html;
    const title = modal.querySelector('[data-add-edit-title]');
    if (title) title.textContent = form.pageTitle;
    openModal('ajax-form-modal');
    return form;
};

window.openRecordView = async function({ url, title, fields }) {
    const modal = document.getElementById('record-view-modal');
    const titleElement = document.getElementById('record-view-title');
    const body = document.getElementById('record-view-body');
    if (!modal || !titleElement || !body) return;

    titleElement.textContent = title;
    body.replaceChildren();
    const loading = document.createElement('p');
    loading.className = 'text-muted mb-0';
    loading.textContent = 'Loading record details…';
    body.append(loading);
    openModal('record-view-modal');

    try {
        const response = await window.api.get(url);
        if (!response.ok) throw new Error('Unable to load record');
        const record = await response.json();
        const content = document.createElement('div');
        content.className = 'row g-3';

        fields.forEach(field => {
            const column = document.createElement('div');
            column.className = field.fullWidth ? 'col-12' : 'col-sm-6';
            const label = document.createElement('span');
            label.className = 'd-block small text-muted mb-1';
            label.textContent = field.label;
            const value = document.createElement('div');
            value.className = 'fw-medium text-break';
            value.textContent = String(typeof field.value === 'function' ? field.value(record) ?? '—' : record[field.value] ?? '—');
            column.append(label, value);
            content.append(column);
        });

        body.replaceChildren(content);
    } catch {
        const error = document.createElement('p');
        error.className = 'text-danger mb-0';
        error.textContent = 'Unable to load record details. Please try again.';
        body.replaceChildren(error);
    }
};

$(document).ready(function() {
    const filterTimeouts = new Map();

    $(document).on('change', '[data-ajax-table]', function() {
        if (this.tagName !== 'SELECT' && this.type !== 'date') return;
        const tableId = this.dataset.ajaxTable;
        if (!tableId || !$.fn.DataTable.isDataTable('#' + tableId)) return;
        $('#' + tableId).DataTable().ajax.reload();
    });

    $(document).on('input', '[data-ajax-table]', function() {
        if (this.tagName === 'SELECT' || this.type === 'date') return;
        const tableId = this.dataset.ajaxTable;
        if (!tableId || !$.fn.DataTable.isDataTable('#' + tableId)) return;
        if (filterTimeouts.has(tableId)) clearTimeout(filterTimeouts.get(tableId));
        filterTimeouts.set(tableId, setTimeout(() => {
            $('#' + tableId).DataTable().ajax.reload();
            filterTimeouts.delete(tableId);
        }, 300));
    });
});
