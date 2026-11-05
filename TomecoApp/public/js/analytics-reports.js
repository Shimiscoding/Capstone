(() => {
    const form = document.getElementById('analyticsFilters');
    const params = new URLSearchParams(location.search);
    const series = document.getElementById('analyticsSeriesFilter');
    const type = document.getElementById('analyticsChartType');
    const canvas = document.getElementById('analyticsChart');
    const feedback = document.getElementById('analyticsChartFeedback');
    const data = JSON.parse(document.getElementById('analyticsChartData').textContent);
    const styles = getComputedStyle(document.documentElement);
    const color = name => styles.getPropertyValue(name).trim();
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let chart;
    if (['overall', 'paid', 'unpaid'].includes(params.get('view'))) series.value = params.get('view');
    if (params.get('chart') === 'bar') type.value = 'bar';
    const render = () => {
        if (typeof Chart === 'undefined') { feedback.hidden = false; canvas.hidden = true; return; }
        const selected = series.value;
        const chartColor = color(selected === 'paid' ? '--green' : selected === 'unpaid' ? '--muted' : '--accent');
        const label = selected === 'overall' ? 'Tickets issued' : selected === 'paid' ? 'Paid tickets' : 'Unpaid tickets';
        chart?.destroy();
        try {
            chart = new Chart(canvas, {
                type: type.value,
                data: { labels: data.map(row => row.label), datasets: [{ label,
                    data: data.map(row => row[selected === 'overall' ? 'value' : selected]),
                    borderColor: chartColor, backgroundColor: type.value === 'bar' ? chartColor : `${chartColor}12`,
                    borderWidth: 2, borderRadius: 3, maxBarThickness: 32, tension: .25,
                    pointRadius: data.length > 32 ? 0 : 2.5, pointHoverRadius: 5, fill: type.value === 'line'
                }] },
                options: { responsive: true, maintainAspectRatio: false, animation: reducedMotion ? false : { duration: 220 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { display: false }, tooltip: { displayColors: false } },
                    scales: {
                        x: { grid: { display: false }, border: { display: false }, ticks: { color: color('--muted'), maxTicksLimit: 9, maxRotation: 0 } },
                        y: { beginAtZero: true, border: { display: false }, ticks: { precision: 0, color: color('--muted') }, grid: { color: color('--line') } }
                    }
                }
            });
            feedback.hidden = true; canvas.hidden = false;
            canvas.setAttribute('aria-label', `${label} by issue date. Values are available in the activity data table.`);
        } catch { feedback.hidden = false; canvas.hidden = true; }
    };
    const update = () => {
        params.set('view', series.value); params.set('chart', type.value);
        form.elements.view.value = series.value; form.elements.chart.value = type.value;
        document.querySelectorAll('[aria-label="Violation ranking pages"] a').forEach(link => {
            const next = new URL(link.href);
            next.searchParams.set('view', series.value);
            next.searchParams.set('chart', type.value);
            link.href = next.toString();
        });
        history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
        render();
    };
    series.addEventListener('change', update); type.addEventListener('change', update);
    const from = form.elements.date_from;
    const to = form.elements.date_to;
    const filterFeedback = document.getElementById('analyticsFilterFeedback');
    let dateTimer;
    const validateDates = () => {
        if (form.elements.summary.value !== 'custom') {
            filterFeedback.hidden = true;
            return true;
        }
        const validFrom = from.value && from.validity.valid && from.value <= from.max;
        const validTo = to.value && to.validity.valid && to.value <= to.max;
        const reversed = validFrom && validTo && to.value < from.value;
        from.setAttribute('aria-invalid', String(!validFrom));
        to.setAttribute('aria-invalid', String(!validTo || reversed));
        const invalid = !validFrom || !validTo || reversed;
        filterFeedback.hidden = !invalid;
        filterFeedback.textContent = reversed
            ? 'The end date must be on or after the start date.'
            : 'Choose complete start and end dates on or before today to update the report.';
        [from, to].forEach(input => {
            if (input.getAttribute('aria-invalid') === 'true') input.setAttribute('aria-describedby', 'analyticsFilterFeedback');
            else if (input.getAttribute('aria-describedby') === 'analyticsFilterFeedback') input.removeAttribute('aria-describedby');
        });
        return !invalid;
    };
    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => {
            clearTimeout(dateTimer);
            // Selecting Custom dates allows editing before requesting new data.
            if (select === form.elements.summary && select.value === 'custom') {
                from.focus();
                return;
            }
            form.requestSubmit();
        });
    });
    [from, to].forEach(input => {
        input.addEventListener('input', () => clearTimeout(dateTimer));
        input.addEventListener('change', () => {
            clearTimeout(dateTimer);
            form.elements.summary.value = 'custom';
            dateTimer = setTimeout(() => { if (validateDates()) form.requestSubmit(); }, 400);
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.isComposing) {
                event.preventDefault();
                clearTimeout(dateTimer);
                form.elements.summary.value = 'custom';
                form.requestSubmit();
            }
        });
    });
    form.addEventListener('submit', event => {
        clearTimeout(dateTimer);
        if (form.dataset.busy) { event.preventDefault(); return; }
        if (!validateDates()) { event.preventDefault(); return; }
        if (form.elements.summary.value !== 'custom') {
            form.elements.date_from.disabled = true; form.elements.date_to.disabled = true;
        }
        form.dataset.busy = 'true'; form.setAttribute('aria-busy', 'true');
        event.submitter?.classList.add('is-loading');
    });
    window.addEventListener('pagehide', () => clearTimeout(dateTimer));
    window.addEventListener('pageshow', () => {
        delete form.dataset.busy; form.removeAttribute('aria-busy');
        form.elements.date_from.disabled = false; form.elements.date_to.disabled = false;
        form.querySelectorAll('.is-loading').forEach(button => button.classList.remove('is-loading'));
    });
    (form.querySelector('[aria-invalid="true"]') || form.querySelector('.analytics-filter-error'))?.focus();
    document.getElementById('analyticsPrint').addEventListener('click', () => window.print());
    window.addEventListener('beforeprint', () => chart?.resize(900, 280));
    window.addEventListener('afterprint', () => chart?.resize());
    render();
})();
