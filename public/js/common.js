window.toast = {
    show(msg, type) {
        const map = { success:'text-bg-success', error:'text-bg-danger', info:'text-bg-primary', warning:'text-bg-warning' };
        const el = document.createElement('div');
        el.className = `toast align-items-center border-0 ${map[type] || map.success}`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        document.getElementById('toast-container').appendChild(el);
        const toast = new bootstrap.Toast(el, { delay: 3000 });
        toast.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    },
    success: message => window.toast.show(message, 'success'),
    error: message => window.toast.show(message, 'error'),
    info: message => window.toast.show(message, 'info'),
    warning: message => window.toast.show(message, 'warning'),
};
