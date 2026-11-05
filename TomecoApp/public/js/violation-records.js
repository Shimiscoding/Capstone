(() => {
    const form = document.getElementById('recordFilters');
    const workspace = document.querySelector('.violation-records-page');
    const panel = workspace.querySelector('.records-panel');
    const search = form.elements.search;
    const clear = document.getElementById('clearRecordSearch');
    const feedback = document.getElementById('recordFilterFeedback');
    const retry = document.getElementById('retryRecords');
    const from = form.elements.date_from;
    const to = form.elements.date_to;
    let controller;
    let timer;
    let composing = false;
    let revision = 0;
    let retryUrl;
    const toggleDates = () => {
        document.getElementById('recordDates').hidden = form.elements.period.value !== 'custom';
    };
    const validate = () => {
        const custom = form.elements.period.value === 'custom';
        const invalidFrom = custom && !from.validity.valid;
        const invalidTo = custom && (!to.validity.valid || (from.value && to.value && to.value < from.value));
        from.setAttribute('aria-invalid', String(Boolean(invalidFrom)));
        to.setAttribute('aria-invalid', String(Boolean(invalidTo)));
        if (invalidFrom || invalidTo) {
            feedback.textContent = 'Choose valid dates with the end date on or after the start date.';
            feedback.hidden = false;
            (invalidFrom ? from : to).setAttribute('aria-describedby', 'recordFilterFeedback');
            return false;
        }
        [from, to].forEach(input => {
            if (input.getAttribute('aria-describedby') === 'recordFilterFeedback') input.removeAttribute('aria-describedby');
        });
        return true;
    };
    const formUrl = () => {
        const url = new URL(form.action);
        const data = new FormData(form);
        for (const [key, value] of data) if (value) url.searchParams.set(key, value);
        if (form.elements.period.value !== 'custom') {
            url.searchParams.delete('date_from'); url.searchParams.delete('date_to');
        }
        return url;
    };
    const refresh = async (url = formUrl(), push = true) => {
        clearTimeout(timer);
        if (!validate()) return;
        controller?.abort();
        controller = new AbortController();
        const current = ++revision;
        panel.setAttribute('aria-busy', 'true');
        feedback.hidden = true; retry.hidden = true;
        retryUrl = url;
        const focusedLink = document.activeElement?.closest('.table-footer a')?.textContent.trim();
        try {
            const response = await fetch(url, { signal: controller.signal, headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (current !== revision) return;
            if (response.status === 401 || response.status === 403 || response.redirected) { location.assign(response.url); return; }
            if (!response.ok) throw new Error('Unable to load records');
            const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('.violation-records-page');
            if (current !== revision) return;
            if (!next) { location.assign(response.url); return; }
            for (const selector of ['.summary-strip', '.table-scroll tbody', '.table-heading > div', '.table-footer', '.records-print-note']) {
                const replacement = next.querySelector(selector);
                if (!replacement) throw new Error('Incomplete records response');
            }
            for (const selector of ['.summary-strip', '.table-scroll tbody', '.table-heading > div', '.table-footer', '.records-print-note']) {
                workspace.querySelector(selector).replaceWith(document.importNode(next.querySelector(selector), true));
            }
            workspace.querySelectorAll('.records-filter-error[role="alert"]').forEach(el => el.remove());
            const error = next.querySelector('.records-filter-error[role="alert"]');
            if (error) form.append(document.importNode(error, true));
            if (push && url.toString() !== location.href) history.pushState(null, '', url);
            if (focusedLink) {
                const link = [...panel.querySelectorAll('.table-footer a')].find(el => el.textContent.trim() === focusedLink);
                (link || panel.querySelector('.table-scroll')).focus({ preventScroll: true });
            }
        } catch (error) {
            if (error.name === 'AbortError' || current !== revision) return;
            feedback.textContent = 'The records could not update. Your current list is still shown. Check your connection and try again.';
            feedback.hidden = false; retry.hidden = false;
        } finally {
            if (current === revision) panel.removeAttribute('aria-busy');
        }
    };
    form.addEventListener('submit', event => { event.preventDefault(); if (!composing) refresh(); });
    form.querySelectorAll('select').forEach(select => select.addEventListener('change', () => {
        clearTimeout(timer); controller?.abort(); revision++; panel.removeAttribute('aria-busy');
        toggleDates();
        if (select === form.elements.period && select.value === 'custom') { from.focus(); return; }
        refresh();
    }));
    document.getElementById('recordSort').addEventListener('change', () => refresh());
    search.addEventListener('compositionstart', () => { composing = true; clearTimeout(timer); controller?.abort(); revision++; panel.removeAttribute('aria-busy'); });
    search.addEventListener('compositionend', () => { composing = false; clearTimeout(timer); timer = setTimeout(() => refresh(), 300); });
    search.addEventListener('input', () => {
        clear.hidden = !search.value;
        clearTimeout(timer); controller?.abort(); revision++; panel.removeAttribute('aria-busy');
        if (!composing) timer = setTimeout(() => refresh(), 300);
    });
    clear.addEventListener('click', () => { clearTimeout(timer); search.value = ''; clear.hidden = true; search.focus(); refresh(); });
    [from, to].forEach(input => {
        input.addEventListener('input', () => { clearTimeout(timer); controller?.abort(); revision++; panel.removeAttribute('aria-busy'); });
        input.addEventListener('change', () => { clearTimeout(timer); form.elements.period.value = 'custom'; toggleDates(); timer = setTimeout(() => refresh(), 400); });
    });
    panel.addEventListener('click', event => {
        const link = event.target.closest('.table-footer a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault(); refresh(new URL(link.href));
    });
    retry.addEventListener('click', () => refresh(retryUrl));
    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(location.search);
        for (const key of ['search', 'violation_type', 'status', 'date_from', 'date_to']) form.elements[key].value = params.get(key) || '';
        form.elements.period.value = params.get('period') || 'month';
        document.getElementById('recordSort').value = params.get('sort') || 'newest';
        clear.hidden = !search.value; toggleDates(); refresh(new URL(location.href), false);
    });
    window.addEventListener('pagehide', () => { clearTimeout(timer); controller?.abort(); });
    document.getElementById('printRecords').addEventListener('click', () => window.print());
    toggleDates();
    (form.querySelector('[aria-invalid="true"]') || form.querySelector('[role="alert"]'))?.focus();
})();
