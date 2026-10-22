@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="mb-4">
    <h2 class="h4 fw-bold mb-1">Business settings</h2>
    <p class="text-muted small mb-0">Manage business details and defaults used on invoices and receipts.</p>
</div>

<div id="settings-error" class="alert alert-danger d-none" role="alert"></div>
<div id="settings-loading" class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading business settings…</div></div>

<form id="settings-form" class="d-none" onsubmit="saveSettings(event)">
    <div class="row g-3">
        <div class="col-lg-8">
            <section class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3"><h3 class="h6 fw-semibold mb-0">Business profile</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="business_name">Business name <span class="text-danger">*</span></label><input class="form-control" id="business_name" name="business_name" maxlength="255" required /></div>
                        <div class="col-md-6"><label class="form-label" for="email">Business email</label><input class="form-control" type="email" id="email" name="email" /></div>
                        <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" maxlength="20" /></div>
                        <div class="col-md-6"><label class="form-label" for="gstin">GSTIN</label><input class="form-control text-uppercase" id="gstin" name="gstin" maxlength="20" /></div>
                        <div class="col-12"><label class="form-label" for="address">Address</label><textarea class="form-control" id="address" name="address" rows="3"></textarea></div>
                        <div class="col-md-6"><label class="form-label" for="state">State</label><input class="form-control" id="state" name="state" maxlength="100" /></div>
                        <div class="col-md-6"><label class="form-label" for="state_code">State code</label><input class="form-control" id="state_code" name="state_code" maxlength="5" /></div>
                        <div class="col-md-6"><label class="form-label" for="currency">Currency code</label><input class="form-control" id="currency" name="currency" maxlength="5" placeholder="INR" /></div>
                        <div class="col-md-6"><label class="form-label" for="timezone">Timezone</label><input class="form-control" id="timezone" name="timezone" maxlength="50" placeholder="Asia/Kolkata" /></div>
                    </div>
                </div>
            </section>
            <section class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3"><h3 class="h6 fw-semibold mb-0">Invoice and receipt defaults</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="invoice_prefix">Invoice prefix</label><input class="form-control" id="invoice_prefix" name="invoice_prefix" maxlength="20" /></div>
                        <div class="col-md-6"><label class="form-label" for="receipt_prefix">Receipt prefix</label><input class="form-control" id="receipt_prefix" name="receipt_prefix" maxlength="20" /></div>
                        <div class="col-md-6"><label class="form-label" for="financial_year_start">Financial year start (MM-DD)</label><input class="form-control" id="financial_year_start" name="financial_year_start" maxlength="5" placeholder="04-01" /></div>
                        <div class="col-md-6"><label class="form-label" for="default_payment_terms">Default payment terms</label><input class="form-control" id="default_payment_terms" name="default_payment_terms" maxlength="255" placeholder="Due within 15 days" /></div>
                        <div class="col-12"><label class="form-label" for="upi_id">UPI ID</label><input class="form-control" id="upi_id" name="upi_id" maxlength="100" /></div>
                        <div class="col-12"><label class="form-label" for="invoice_footer">Invoice footer</label><textarea class="form-control" id="invoice_footer" name="invoice_footer" rows="3"></textarea></div>
                        <div class="col-12">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="invoice_number_reset" name="invoice_number_reset" value="1" /><label class="form-check-label" for="invoice_number_reset">Restart invoice numbering each financial year</label></div>
                            <div class="form-text">Applies to newly generated invoices only.</div>
                        </div>
                    </div>
                </div>
            </section>
            <details class="card border-0 shadow-sm mb-3">
                <summary class="card-header bg-white py-3 fw-semibold">Advanced configuration</summary>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label" for="bank_details">Bank details (JSON object)</label><textarea class="form-control font-monospace" id="bank_details" rows="4" placeholder='{"bank_name":"…","account_number":"…"}'></textarea><div class="form-text">Optional. Keep the value a valid JSON object.</div></div>
                    <div><label class="form-label" for="tax_config">Tax configuration (JSON object)</label><textarea class="form-control font-monospace" id="tax_config" rows="4" placeholder="{}"></textarea><div class="form-text">Optional. Leave unchanged if tax rules are managed elsewhere.</div></div>
                </div>
            </details>
        </div>
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3"><h3 class="h6 fw-semibold mb-0">Business logo</h3></div>
                <div class="card-body">
                    <div id="logo-preview-wrap" class="d-none mb-3 text-center"><img id="logo-preview" class="img-fluid rounded-1 border p-2" style="max-height:120px" alt="Current business logo"></div>
                    <label class="form-label" for="business-logo">Upload logo</label>
                    <input class="form-control" type="file" id="business-logo" accept="image/*" />
                    <div class="form-text">Image file, maximum 2 MB. Used on business documents.</div>
                </div>
            </section>
            <div class="d-grid"><button class="btn btn-primary" id="settings-save" type="submit"><i class="bi bi-check2 me-1"></i>Save settings</button></div>
            <p class="text-muted small mt-2">Changes are saved to this account's business profile.</p>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
const settingFields = ['business_name','address','phone','email','gstin','state','state_code','currency','timezone','upi_id','invoice_prefix','receipt_prefix','financial_year_start','default_payment_terms','invoice_footer'];
function showSettingsError(message) {
    const element = document.getElementById('settings-error');
    element.textContent = message;
    element.classList.remove('d-none');
}
async function loadSettings() {
    try {
        const response = await api.get('/settings/profile');
        if (!response.ok) throw new Error('Unable to load business settings');
        const profile = await response.json();
        settingFields.forEach(field => document.getElementById(field).value = profile[field] ?? '');
        document.getElementById('invoice_number_reset').checked = Boolean(profile.invoice_number_reset);
        document.getElementById('bank_details').value = profile.bank_details ? JSON.stringify(profile.bank_details, null, 2) : '';
        document.getElementById('tax_config').value = profile.tax_config ? JSON.stringify(profile.tax_config, null, 2) : '';
        if (profile.logo) {
            document.getElementById('logo-preview').src = `${window.baseUrl}/storage/${profile.logo}`;
            document.getElementById('logo-preview-wrap').classList.remove('d-none');
        }
        document.getElementById('settings-loading').classList.add('d-none');
        document.getElementById('settings-form').classList.remove('d-none');
    } catch (error) {
        document.getElementById('settings-loading').classList.add('d-none');
        showSettingsError('Business settings could not be loaded. Please refresh and try again.');
    }
}
function appendNested(formData, key, value) {
    if (value && typeof value === 'object') {
        Object.entries(value).forEach(([childKey, childValue]) => appendNested(formData, `${key}[${childKey}]`, childValue));
    } else if (value !== undefined && value !== null) {
        formData.append(key, String(value));
    }
}
async function saveSettings(event) {
    event.preventDefault();
    document.getElementById('settings-error').classList.add('d-none');
    let bankDetails, taxConfig;
    try {
        bankDetails = document.getElementById('bank_details').value.trim() ? JSON.parse(document.getElementById('bank_details').value) : {};
        taxConfig = document.getElementById('tax_config').value.trim() ? JSON.parse(document.getElementById('tax_config').value) : {};
        if (!bankDetails || Array.isArray(bankDetails) || typeof bankDetails !== 'object' || !taxConfig || Array.isArray(taxConfig) || typeof taxConfig !== 'object') throw new Error('Enter JSON objects for bank and tax configuration.');
    } catch (error) { return showSettingsError(error.message || 'Check that advanced settings contain valid JSON objects.'); }
    const formData = new FormData();
    settingFields.forEach(field => formData.append(field, document.getElementById(field).value));
    formData.append('invoice_number_reset', document.getElementById('invoice_number_reset').checked ? '1' : '0');
    if (Object.keys(bankDetails).length) appendNested(formData,'bank_details',bankDetails);
    else formData.append('bank_details','');
    if (Object.keys(taxConfig).length) appendNested(formData,'tax_config',taxConfig);
    else formData.append('tax_config','');
    const logo = document.getElementById('business-logo').files[0];
    if (logo) formData.append('logo',logo);
    const button = document.getElementById('settings-save');
    button.disabled = true;
    try {
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const response = await fetch(`${window.baseUrl}/settings`, {
            method:'POST',
            credentials:'same-origin',
            headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-XSRF-TOKEN':xsrf},
            body: (() => { formData.append('_method','PUT'); return formData; })()
        });
        const data = await response.json();
        if (!response.ok) {
            const message = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Settings could not be saved.');
            return showSettingsError(message);
        }
        document.getElementById('business-logo').value = '';
        if (data.logo) {
            document.getElementById('logo-preview').src = `${window.baseUrl}/storage/${data.logo}`;
            document.getElementById('logo-preview-wrap').classList.remove('d-none');
        }
        toast.success('Business settings saved');
    } catch (error) { showSettingsError('Settings could not be saved. Check your connection and try again.'); }
    finally { button.disabled = false; }
}
loadSettings();
</script>
@endpush