(() => {
    const editDialog = document.getElementById('attendanceEditDialog');
    const deleteDialog = document.getElementById('attendanceDeleteDialog');
    if (!editDialog || !deleteDialog) return;

    function showError(dialog, message, fields = []) {
        const error = dialog.querySelector('.attendance-record-error');
        error.textContent = message;
        error.hidden = false;
        dialog.querySelectorAll('input[name="time_in"], input[name="time_out"]').forEach(input => {
            const invalid = fields.includes(input.name);
            input.setAttribute('aria-invalid', String(invalid));
            if (invalid) input.setAttribute('aria-describedby', 'attendanceRecordError');
            else input.removeAttribute('aria-describedby');
        });
        error.focus();
    }

    document.querySelectorAll('[data-attendance-edit], [data-attendance-delete]').forEach(button => {
        button.addEventListener('click', () => {
            const editing = button.hasAttribute('data-attendance-edit');
            const dialog = editing ? editDialog : deleteDialog;
            const form = dialog.querySelector('form');
            dialog.returnFocus = button;
            form.action = button.dataset.url;
            dialog.querySelector('[data-record-label]').textContent = button.dataset.label;
            dialog.querySelector('.attendance-record-error').hidden = true;
            dialog.querySelectorAll('[aria-invalid]').forEach(input => {
                input.removeAttribute('aria-invalid');
                input.removeAttribute('aria-describedby');
            });
            if (editing) {
                form.elements.time_in.value = button.dataset.timeIn;
                form.elements.time_out.value = button.dataset.timeOut;
            }
            dialog.showModal();
            if (editing) form.elements.time_in.focus();
            else dialog.querySelector('[data-close-dialog][autofocus]').focus();
        });
    });

    editDialog.querySelector('.attendance-record-error').id = 'attendanceRecordError';
    [editDialog, deleteDialog].forEach(dialog => {
        const form = dialog.querySelector('form');
        let busy = false;
        dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => {
            if (!busy) dialog.close();
        }));
        dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
        dialog.addEventListener('close', () => dialog.returnFocus?.focus());
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            if (dialog === editDialog) {
                const timeIn = form.elements.time_in.value;
                const timeOut = form.elements.time_out.value;
                if (!timeIn || !timeOut) return showError(dialog, 'Enter both Time in and Time out.', ['time_in', 'time_out']);
                if (timeOut <= timeIn) return showError(dialog, 'Time out must be after Time in.', ['time_out']);
            }
            busy = true;
            const submit = form.querySelector('[type="submit"]');
            const originalText = submit.textContent;
            const data = new FormData(form);
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button, input').forEach(control => control.disabled = true);
            submit.textContent = dialog === editDialog ? 'Saving…' : 'Deleting…';
            let navigating = false;
            try {
                const response = await fetch(form.action, {
                    method: 'POST', body: data, credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = [401, 419].includes(response.status) ? 'Your session expired. Reload the page and try again.'
                        : response.status === 404 ? 'This attendance record is no longer available. Reload the page to refresh the history.'
                        : response.status === 403 ? 'You no longer have permission to change this attendance record.'
                        : Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save changes. Try again.';
                    showError(dialog, message, Object.keys(result.errors || {}));
                    return;
                }
                navigating = true;
                if (result.redirect) window.location.assign(result.redirect);
                else window.location.reload();
            } catch {
                showError(dialog, 'Could not connect. Your entries are still here; try again.');
            } finally {
                if (!navigating) {
                    busy = false;
                    form.removeAttribute('aria-busy');
                    form.querySelectorAll('button, input').forEach(control => control.disabled = false);
                    submit.textContent = originalText;
                }
            }
        });
    });
})();
