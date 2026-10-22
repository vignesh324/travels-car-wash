@extends('layouts.app')
@section('title', 'Profile')
@section('content')
<div class="mb-4">
    <h2 class="h4 fw-bold mb-1">Profile</h2>
    <p class="text-muted small mb-0">Manage your personal sign-in details and password.</p>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <form id="account-form" class="card border-0 shadow-sm h-100" onsubmit="saveAccount(event)">
            <div class="card-header bg-white py-3">
                <h3 class="h6 fw-semibold mb-0">Personal profile</h3>
            </div>
            <div class="card-body">
                <div id="account-feedback" class="alert d-none mb-3" role="status" aria-live="polite"></div>
                <div class="mb-3">
                    <label class="form-label" for="account-name">Name</label>
                    <input class="form-control" type="text" id="account-name" name="name" maxlength="255" autocomplete="name" value="{{ auth()->user()->name }}" aria-describedby="account-name-error" required>
                    <div class="invalid-feedback" id="account-name-error" aria-live="polite"></div>
                </div>
                <div>
                    <label class="form-label" for="account-email">Email address</label>
                    <input class="form-control" type="email" id="account-email" name="email" maxlength="255" autocomplete="email" value="{{ auth()->user()->email }}" aria-describedby="account-email-error" required>
                    <div class="invalid-feedback" id="account-email-error" aria-live="polite"></div>
                </div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
                <button class="btn btn-primary" type="submit" id="account-save">Save profile</button>
            </div>
        </form>
    </div>
    <div class="col-lg-6">
        <form id="password-form" class="card border-0 shadow-sm h-100" onsubmit="changePassword(event)">
            <div class="card-header bg-white py-3">
                <h3 class="h6 fw-semibold mb-0">Change password</h3>
            </div>
            <div class="card-body">
                <div id="password-feedback" class="alert d-none mb-3" role="status" aria-live="polite"></div>
                <div class="mb-3">
                    <label class="form-label" for="current-password">Current password</label>
                    <input class="form-control" type="password" id="current-password" name="current_password" autocomplete="current-password" aria-describedby="current-password-error" required>
                    <div class="invalid-feedback" id="current-password-error" aria-live="polite"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new-password">New password</label>
                    <input class="form-control" type="password" id="new-password" name="new_password" autocomplete="new-password" minlength="8" aria-describedby="new-password-error" required>
                    <div class="form-text">Use at least 8 characters.</div>
                    <div class="invalid-feedback" id="new-password-error" aria-live="polite"></div>
                </div>
                <div>
                    <label class="form-label" for="new-password-confirmation">Confirm new password</label>
                    <input class="form-control" type="password" id="new-password-confirmation" name="new_password_confirmation" autocomplete="new-password" minlength="8" aria-describedby="new-password-confirmation-error" required>
                    <div class="invalid-feedback" id="new-password-confirmation-error" aria-live="polite"></div>
                </div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
                <button class="btn btn-outline-primary" type="submit" id="password-save">Update password</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showAccountFeedback(element, message, success = false) {
    element.textContent = message;
    element.classList.remove('d-none', 'alert-danger', 'alert-success');
    element.classList.add(success ? 'alert-success' : 'alert-danger');
}
function clearFieldError(field) {
    const error = document.getElementById(`${field.id}-error`);
    field.classList.remove('is-invalid');
    field.removeAttribute('aria-invalid');
    if (error) error.textContent = '';
}
function clearFormErrors(form) {
    form.querySelectorAll('input[name]').forEach(clearFieldError);
}
function showFieldErrors(form, errors) {
    Object.entries(errors || {}).forEach(([name, messages]) => {
        const confirmationErrors = name === 'new_password'
            ? messages.filter(message => /confirmation/i.test(message) && /match/i.test(message))
            : [];
        const fieldErrors = confirmationErrors.length
            ? messages.filter(message => !confirmationErrors.includes(message))
            : messages;
        const field = form.elements[name];
        if (fieldErrors.length && field && field.id) {
            const error = document.getElementById(`${field.id}-error`);
            if (error) {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
                error.textContent = fieldErrors.join(' ');
            }
        }
        if (confirmationErrors.length) {
            const confirmation = form.elements.new_password_confirmation;
            const error = document.getElementById(`${confirmation.id}-error`);
            if (error) {
                confirmation.classList.add('is-invalid');
                confirmation.setAttribute('aria-invalid', 'true');
                error.textContent = 'Password confirmation does not match.';
            }
        }
    });
}
function prepareAccountForm(form, feedback) {
    clearFormErrors(form);
    feedback.classList.add('d-none');
}
function bindFieldErrorClearing(form, feedback) {
    form.querySelectorAll('input[name]').forEach(field => {
        field.addEventListener('input', () => {
            clearFieldError(field);
            if (form.querySelector('.is-invalid')) return;
            feedback.classList.add('d-none');
        });
    });
}
async function saveAccount(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = document.getElementById('account-feedback');
    const button = document.getElementById('account-save');
    prepareAccountForm(form, feedback);
    button.disabled = true;
    try {
        const response = await window.api.put('/settings/account', {
            name: form.elements.name.value,
            email: form.elements.email.value
        });
        const data = await response.json();
        if (!response.ok) {
            if (data.errors) {
                showFieldErrors(form, data.errors);
                showAccountFeedback(feedback, 'Please correct the highlighted fields.');
            } else {
                showAccountFeedback(feedback, data.message || 'Your changes could not be saved.');
            }
            return;
        }
        form.elements.name.value = data.user.name;
        form.elements.email.value = data.user.email;
        showAccountFeedback(feedback, data.message, true);
    } catch (error) {
        showAccountFeedback(feedback, 'Profile could not be saved. Check your connection and try again.');
    } finally {
        button.disabled = false;
    }
}
async function changePassword(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const feedback = document.getElementById('password-feedback');
    const button = document.getElementById('password-save');
    prepareAccountForm(form, feedback);
    button.disabled = true;
    try {
        const response = await window.api.put('/settings/password', {
            current_password: form.elements.current_password.value,
            new_password: form.elements.new_password.value,
            new_password_confirmation: form.elements.new_password_confirmation.value
        });
        const data = await response.json();
        if (!response.ok) {
            if (data.errors) {
                showFieldErrors(form, data.errors);
                showAccountFeedback(feedback, 'Please correct the highlighted fields.');
            } else {
                showAccountFeedback(feedback, data.message || 'Your changes could not be saved.');
            }
            return;
        }
        form.reset();
        showAccountFeedback(feedback, data.message, true);
    } catch (error) {
        showAccountFeedback(feedback, 'Password could not be changed. Check your connection and try again.');
    } finally {
        button.disabled = false;
    }
}
bindFieldErrorClearing(document.getElementById('account-form'), document.getElementById('account-feedback'));
bindFieldErrorClearing(document.getElementById('password-form'), document.getElementById('password-feedback'));
</script>
@endpush
