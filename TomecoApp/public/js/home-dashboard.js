(() => {
    const root = document.querySelector('.home-dashboard');
    if (!root) return;
    const select = root.querySelector('#activityMetric');
    const chart = root.querySelector('#activityChart');
    const rows = Array.from(root.querySelectorAll('.chart-data-table tbody tr'));
    const days = rows.map(row => ({
        label: row.dataset.label,
        violations: Number(row.dataset.violations) || 0,
        users: Number(row.dataset.users) || 0,
    }));
    const number = new Intl.NumberFormat('en-PH');
    const svgElement = (tag, attributes, text) => {
        const element = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, String(value)));
        if (text !== undefined) element.textContent = text;
        return element;
    };
    const draw = () => {
        const metric = select.value;
        const label = select.selectedOptions[0].textContent;
        const total = days.reduce((sum, day) => sum + day[metric], 0);
        const maximum = Math.max(4, Math.ceil(Math.max(...days.map(day => day[metric])) / 4) * 4);
        const width = chart.clientWidth < 500 ? 350 : 650;
        const left = 30, top = 18, height = 135, step = (width - left - 15) / 7;
        const barWidth = step * .42;
        const svg = svgElement('svg', {
            viewBox: `0 0 ${width} 190`, preserveAspectRatio: 'none', role: 'img',
            'aria-label': `${label} over the last 7 days. Total ${number.format(total)}. Exact daily values are in View chart data.`,
        });
        for (let i = 0; i < 5; i++) {
            const y = top + i * height / 4;
            svg.append(svgElement('line', {class: 'grid-line', x1: left, y1: y, x2: width - 15, y2: y}));
            svg.append(svgElement('text', {x: 20, y: y + 4, 'text-anchor': 'end'}, number.format(maximum - i * maximum / 4)));
        }
        days.forEach((day, i) => {
            const value = day[metric];
            const x = left + i * step + (step - barWidth) / 2;
            const barHeight = value / maximum * height;
            const bar = svgElement('rect', {
                class: `bar ${i === days.length - 1 ? 'bar-today' : ''}`,
                x, y: top + height - barHeight, width: barWidth, height: barHeight, rx: 3,
            });
            bar.append(svgElement('title', {}, `${day.label}: ${number.format(value)} ${label.toLowerCase()}`));
            svg.append(bar);
            svg.append(svgElement('text', {class: 'value-label', x: x + barWidth / 2, y: top + height - barHeight - 7, 'text-anchor': 'middle'}, number.format(value)));
            svg.append(svgElement('text', {x: x + barWidth / 2, y: 179, 'text-anchor': 'middle'}, day.label));
        });
        chart.replaceChildren(svg);
        root.querySelector('#chartTotal').textContent = number.format(total);
        root.querySelector('#chartCaption').textContent = metric === 'violations' ? 'tickets issued in the last 7 days' : 'users registered in the last 7 days';
        root.querySelector('#chartLegend').textContent = label;
        const empty = root.querySelector('#chartEmpty');
        empty.textContent = metric === 'violations' ? 'No tickets issued in the last 7 days.' : 'No users registered in the last 7 days.';
        empty.hidden = total !== 0;
    };
    chart.hidden = false;
    draw();
    root.querySelector('.chart-select').hidden = false;
    root.querySelector('.chart-data').open = false;
    select.addEventListener('change', draw);
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(draw).observe(chart);
    } else {
        window.addEventListener('resize', draw);
    }
})();
