(() => {
    document.querySelectorAll('[data-dashboard-search]').forEach(form => {
        const input = form.querySelector('[data-dashboard-search-input]');
        const clear = form.querySelector('[data-dashboard-search-clear]');
        const panel = form.querySelector('[data-dashboard-search-panel]');
        const status = form.querySelector('[data-dashboard-search-status]');
        const results = form.querySelector('[data-dashboard-search-results]');
        const retry = form.querySelector('[data-dashboard-search-retry]');
        let timer;
        let request;
        let sequence = 0;
        let composing = false;

        const position = () => {
            if (panel.hidden) return;
            const box = input.getBoundingClientRect();
            const viewport = window.visualViewport;
            const viewportLeft = viewport?.offsetLeft || 0;
            const viewportTop = viewport?.offsetTop || 0;
            const viewportWidth = viewport?.width || innerWidth;
            const viewportHeight = viewport?.height || innerHeight;
            const width = Math.min(420, viewportWidth - 24);
            const left = Math.max(viewportLeft + 12, Math.min(box.left, viewportLeft + viewportWidth - width - 12));
            const top = Math.max(viewportTop + 12, box.bottom + 8);
            panel.style.left = `${left}px`;
            panel.style.top = `${top}px`;
            panel.style.width = `${width}px`;
            panel.style.maxHeight = `${Math.max(80, Math.min(480, viewportTop + viewportHeight - top - 12))}px`;
        };
        const cancel = () => {
            clearTimeout(timer);
            request?.abort();
            request = null;
            sequence += 1;
            panel.removeAttribute('aria-busy');
        };
        const close = (focus = false) => {
            cancel();
            panel.hidden = true;
            if (focus) input.focus();
        };
        const render = items => {
            results.replaceChildren();
            const groups = new Map();
            for (const item of items) {
                if (!item || typeof item.label !== 'string' || typeof item.url !== 'string') continue;
                let url;
                try { url = new URL(item.url, location.origin); } catch { continue; }
                if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) continue;
                const kind = typeof item.kind === 'string' ? item.kind : 'Results';
                if (!groups.has(kind)) {
                    const group = document.createElement('section');
                    group.className = 'dashboard-search-group';
                    const heading = document.createElement('h2');
                    heading.textContent = kind;
                    const list = document.createElement('ul');
                    group.append(heading, list);
                    results.append(group);
                    groups.set(kind, list);
                }
                const row = document.createElement('li');
                const link = document.createElement('a');
                link.className = 'dashboard-search-result';
                link.href = url.href;
                const label = document.createElement('strong');
                label.textContent = item.label;
                link.append(label);
                if (item.description) {
                    const description = document.createElement('small');
                    description.textContent = String(item.description);
                    link.append(description);
                }
                row.append(link);
                groups.get(kind).append(row);
            }
            return results.querySelectorAll('a').length;
        };
        const search = async () => {
            cancel();
            const query = input.value.trim();
            clear.hidden = input.value === '';
            if (!query || composing) { close(); return; }
            const currentSequence = sequence;
            request = new AbortController();
            const signal = request.signal;
            panel.hidden = false;
            panel.setAttribute('aria-busy', 'true');
            results.replaceChildren();
            retry.hidden = true;
            status.textContent = 'Searching…';
            position();
            try {
                const url = new URL(form.action, location.href);
                url.searchParams.set('q', query);
                const response = await fetch(url, { signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Search request failed');
                const data = await response.json();
                if (!Array.isArray(data.results)) throw new Error('Invalid search response');
                if (currentSequence !== sequence || signal.aborted || query !== input.value.trim()) return;
                const count = render(data.results);
                status.textContent = count ? `${count} ${count === 1 ? 'result' : 'results'} found.` : 'No results found. Try another search.';
            } catch (error) {
                if (signal.aborted || currentSequence !== sequence) return;
                status.textContent = 'Search could not load. Please try again.';
                retry.hidden = false;
            } finally {
                if (currentSequence === sequence) { panel.removeAttribute('aria-busy'); request = null; position(); }
            }
        };
        const queueSearch = () => {
            cancel();
            panel.hidden = true;
            results.replaceChildren();
            status.textContent = '';
            clear.hidden = input.value === '';
            if (!input.value.trim()) { close(); return; }
            if (composing) return;
            timer = setTimeout(search, 300);
        };
        form.addEventListener('submit', event => { event.preventDefault(); if (!composing) search(); });
        input.addEventListener('input', queueSearch);
        input.addEventListener('compositionstart', () => { composing = true; close(); });
        input.addEventListener('compositionend', () => { composing = false; queueSearch(); });
        clear.addEventListener('click', () => {
            input.value = '';
            clear.hidden = true;
            close();
            results.replaceChildren();
            status.textContent = '';
            input.focus();
        });
        retry.addEventListener('click', () => { input.focus(); search(); });
        form.addEventListener('keydown', event => {
            if (composing || event.isComposing) return;
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); return; }
            const links = [...results.querySelectorAll('a')];
            if (event.key === 'ArrowDown') {
                if (panel.hidden) { if (input.value.trim()) search(); return; }
                if (links.length) { event.preventDefault(); links[(links.indexOf(document.activeElement) + 1) % links.length].focus(); }
            }
            if (event.key === 'ArrowUp' && !panel.hidden && links.length) {
                event.preventDefault();
                const index = links.indexOf(document.activeElement);
                if (index <= 0) input.focus(); else links[index - 1].focus();
            }
        });
        document.addEventListener('pointerdown', event => { if (!form.contains(event.target)) close(); });
        form.addEventListener('focusout', () => setTimeout(() => { if (!form.contains(document.activeElement)) close(); }, 0));
        window.addEventListener('resize', position);
        window.addEventListener('scroll', position, true);
        window.visualViewport?.addEventListener('resize', position);
        window.visualViewport?.addEventListener('scroll', position);
        window.addEventListener('pageshow', () => { cancel(); panel.hidden = true; });
    });
})();
