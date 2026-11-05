// Fit the complete form, including validation feedback, inside the locked viewport.
document.querySelectorAll('.auth-form-panel').forEach((panel) => {
    const inner = panel.querySelector('.auth-panel-inner');
    const fit = () => {
        const style = getComputedStyle(panel);
        const available = panel.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
        inner.style.setProperty('--auth-fit', String(Math.min(1, available / Math.max(inner.scrollHeight, 1))));
    };
    const observer = new ResizeObserver(fit);
    observer.observe(panel);
    observer.observe(inner);
    window.visualViewport?.addEventListener('resize', fit);
    fit();
});

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const input = document.getElementById(toggle.getAttribute('aria-controls'));
    const label = document.querySelector(`label[for="${input.id}"]`).textContent.trim().toLowerCase();
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.querySelector('.eye-open').hidden = visible;
        toggle.querySelector('.eye-closed').hidden = !visible;
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${label}`);
        toggle.title = toggle.getAttribute('aria-label');
    });
});

document.querySelectorAll('[data-auth-form]').forEach((form) => {
    const fields = [...form.querySelectorAll('.auth-input')];
    const button = form.querySelector('[type="submit"]');
    const status = form.querySelector('.auth-form-status');
    const initialLabel = button.innerHTML;

    function setError(field, message) {
        const error = document.getElementById(`${field.id}-error`);
        field.setAttribute('aria-invalid', String(Boolean(message)));
        error.textContent = message;
        error.hidden = !message;
    }

    function validationMessage(field) {
        if (field.validity.valueMissing) return 'Please fill in this field.';
        if (field.validity.typeMismatch) return 'Enter a valid email address.';
        if (field.validity.patternMismatch) return 'Enter 11 digits starting with 09.';
        if (field.minLength > 0 && field.value && field.value.length < field.minLength) {
            return `Use at least ${field.minLength} characters.`;
        }
        if (field.maxLength > 0 && field.value.length > field.maxLength) return `Use no more than ${field.maxLength} characters.`;
        if (field.name === 'password_confirmation' && field.value !== form.elements.password.value) {
            return 'The passwords do not match. Try again.';
        }
        return '';
    }

    fields.forEach((field) => {
        field.addEventListener('input', () => {
            if (field.getAttribute('aria-invalid') === 'true') setError(field, '');
            status.textContent = '';
        });
    });

    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        fields.forEach((field) => setError(field, validationMessage(field)));
        const firstInvalid = fields.find((field) => field.getAttribute('aria-invalid') === 'true');
        if (firstInvalid) {
            event.preventDefault();
            status.textContent = 'Check the highlighted fields and try again.';
            firstInvalid.focus();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        button.textContent = button.dataset.busyLabel;
        status.textContent = button.dataset.busyLabel;
    });

    fields.find((field) => field.getAttribute('aria-invalid') === 'true')?.focus();
    window.addEventListener('pageshow', () => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        button.disabled = false;
        button.innerHTML = initialLabel;
        status.textContent = '';
    });
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.body.classList.contains('auth-login')) window.location.reload();
});
