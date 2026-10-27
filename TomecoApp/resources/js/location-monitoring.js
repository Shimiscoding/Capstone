import './bootstrap';

const root = document.getElementById('locationMonitor');

if (root) {
    const endpoint = root.dataset.endpoint;
    const center = JSON.parse(root.dataset.center);
    const thresholds = JSON.parse(root.dataset.thresholds);
    const officers = new Map();
    const officerMarkers = {};
    const portraitImages = new Map();
    const subscriptions = new Set();
    let authorizedTeamIds = [];
    let map;
    let selectedId = null;
    let serverOffset = 0;
    let hasFittedBounds = false;
    let locationRequest;
    let requestVersion = 0;
    let hasLoaded = false;
    let rosterCollapsed = false;
    let lastListMarkup = '';

    const elements = Object.fromEntries(['liveConnection','summaryOnDuty','summaryOffline','summaryOffDuty','summaryTeams','summaryAlerts','officerSearch','clearOfficerSearch','teamFilter','statusFilter','areaFilter','refreshLocations','fitAllLocations','officerList','officerCount','mapStatus','toggleOfficerPanel','officerPanelBody','selectedOfficer','selectedOfficerContent','closeOfficerDetails','locationDataStatus'].map(id => [id, document.getElementById(id)]));
    const statusText = { active: 'Live', delayed: 'Location Delayed', offline: 'Offline', off_duty: 'Off Duty' };
    const colors = { active: '#18864b', delayed: '#d69000', offline: '#c23832', off_duty: '#596270' };
    const now = () => Date.now() + serverOffset;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
    const ageText = timestamp => {
        if (!timestamp) return 'Location unavailable';
        const seconds = Math.max(0, Math.floor((now() - new Date(timestamp).getTime()) / 1000));
        if (seconds < 10) return 'Just now';
        if (seconds < 60) return `${seconds} sec ago`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
        return `${Math.floor(seconds / 3600)} hr ago`;
    };
    const computedStatus = officer => {
        if (!officer.on_duty || !officer.is_sharing) return 'off_duty';
        const age = Math.max(0, (now() - new Date(officer.received_at).getTime()) / 1000);
        return age <= thresholds.active ? 'active' : age <= thresholds.offline ? 'delayed' : 'offline';
    };
    const filtered = () => [...officers.values()].filter(officer => {
        officer.status = computedStatus(officer);
        const term = elements.officerSearch.value.trim().toLowerCase();
        return (!term || `${officer.name} ${officer.personnel_id || ''}`.toLowerCase().includes(term))
            && (!elements.teamFilter.value || String(officer.team_id) === elements.teamFilter.value)
            && (!elements.statusFilter.value || officer.status === elements.statusFilter.value)
            && (!elements.areaFilter.value || officer.area === elements.areaFilter.value);
    });
    const markerIcon = status => ({ path: google.maps.SymbolPath.CIRCLE, scale: 10, fillColor: colors[status], fillOpacity: 1, strokeColor: '#fff', strokeWeight: 3 });

    const avatar = officer => `<span class="location-avatar"><span>${escapeHtml(officer.name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join(''))}</span>${officer.profile_photo_url ? `<img src="${escapeHtml(officer.profile_photo_url)}" alt="" onerror="this.remove()">` : ''}</span>`;
    function portraitIcon(officer) {
        const url = officer.profile_photo_url;
        if (!url) return markerIcon(officer.status);
        if (!portraitImages.has(url)) {
            portraitImages.set(url, null);
            const image = new Image();
            image.onload = () => { portraitImages.set(url, image); upsertMarker(officers.get(Number(officer.id)) || officer); };
            image.src = url;
        }
        const image = portraitImages.get(url);
        if (!image) return markerIcon(officer.status);
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = 80;
        const ctx = canvas.getContext('2d');
        ctx.save(); ctx.beginPath(); ctx.arc(40, 40, 34, 0, Math.PI * 2); ctx.clip();
        const size = Math.min(image.naturalWidth, image.naturalHeight);
        ctx.drawImage(image, (image.naturalWidth - size) / 2, (image.naturalHeight - size) / 2, size, size, 6, 6, 68, 68);
        ctx.restore(); ctx.beginPath(); ctx.arc(40, 40, 36, 0, Math.PI * 2); ctx.strokeStyle = colors[officer.status]; ctx.lineWidth = 6; ctx.stroke();
        return {url: canvas.toDataURL(), scaledSize: new google.maps.Size(40, 40), anchor: new google.maps.Point(20, 20)};
    }

    function infoContent(officer) {
        const speed = officer.speed == null ? '' : `<dt>Speed</dt><dd>${(officer.speed * 3.6).toFixed(1)} km/h</dd>`;
        const accuracy = officer.accuracy == null ? '' : `<dt>GPS Accuracy</dt><dd>±${Math.round(officer.accuracy)} m${officer.low_accuracy ? ' · Low accuracy' : ''}</dd>`;
        const personnel = officer.personnel_id ? `<dt>Personnel ID</dt><dd>${escapeHtml(officer.personnel_id)}</dd>` : '';
        return `<div class="selected-officer-head">${avatar(officer)}<div><h3>${escapeHtml(officer.name)}</h3><span class="map-status-label status-${officer.status}">${statusText[officer.status]}</span></div></div><dl>${personnel}<dt>Team</dt><dd>${escapeHtml(officer.team)}</dd><dt>Assigned area</dt><dd>${escapeHtml(officer.area || 'Not assigned')}</dd><dt>Supervisor</dt><dd>${escapeHtml(officer.supervisor || 'Not assigned')}</dd><dt>Time in</dt><dd>${officer.time_in ? new Date(officer.time_in).toLocaleTimeString('en-PH', {hour:'numeric',minute:'2-digit',timeZone:'Asia/Manila'}) : 'Unavailable'}</dd><dt>Last reported</dt><dd>${ageText(officer.received_at)}</dd>${accuracy}${speed}<dt>Position</dt><dd>${officer.latitude == null || officer.longitude == null ? 'Not available' : officer.status === 'active' ? 'Latest reported location' : 'Last known location'}</dd></dl><a href="${escapeHtml(officer.details_url)}">View officer details</a>`;
    }
    function renderSelected() {
        const officer = officers.get(selectedId);
        elements.selectedOfficer.hidden = !officer;
        if (!officer) return;
        const markup = infoContent(officer);
        if (elements.selectedOfficerContent.innerHTML !== markup) {
            const restoreLink = elements.selectedOfficerContent.contains(document.activeElement);
            elements.selectedOfficerContent.innerHTML = markup;
            if (restoreLink) elements.selectedOfficerContent.querySelector('a').focus({preventScroll:true});
        }
    }
    function setRosterCollapsed(collapsed) {
        rosterCollapsed = collapsed;
        root.querySelector('.officer-panel').classList.toggle('is-collapsed', collapsed);
        root.querySelector('.location-main').classList.toggle('is-roster-collapsed', collapsed);
        elements.officerPanelBody.hidden = collapsed;
        elements.toggleOfficerPanel.setAttribute('aria-expanded', String(!collapsed));
        const label = collapsed ? 'Expand officer list' : 'Collapse officer list';
        elements.toggleOfficerPanel.setAttribute('aria-label', label);
        elements.toggleOfficerPanel.title = label;
        elements.toggleOfficerPanel.firstElementChild.textContent = collapsed ? '›' : '‹';
    }
    function selectOfficer(id) {
        const officer = officers.get(Number(id));
        if (!officer) return;
        selectedId = Number(id); renderList(); renderSelected();
        if (window.matchMedia('(max-width: 760px)').matches) {
            setRosterCollapsed(true);
            elements.closeOfficerDetails.focus({preventScroll:true});
        }
        if (!map || !officerMarkers[id]) return;
        map.setZoom(16);
        const projection = map.getProjection();
        const point = projection?.fromLatLngToPoint(officerMarkers[id].getPosition());
        // Keep the selected marker beside the roster and above the detail card.
        if (point) {
            const surface = root.querySelector('.location-main');
            const offsetX = !rosterCollapsed && surface.clientWidth > 600 ? 160 : 0;
            const offsetY = surface.clientHeight / 2 - Math.min(160, surface.clientHeight / 4);
            const scale = 2 ** map.getZoom();
            map.panTo(projection.fromPointToLatLng(new google.maps.Point(point.x - offsetX / scale, point.y + offsetY / scale)));
        } else map.panTo(officerMarkers[id].getPosition());
    }
    function upsertMarker(officer) {
        if (!map || officer.latitude == null || officer.longitude == null) return;
        const position = {lat: Number(officer.latitude), lng: Number(officer.longitude)};
        if (officerMarkers[officer.id]) {
            officerMarkers[officer.id].setPosition(position);
            officerMarkers[officer.id].setIcon(portraitIcon(officer));
            officerMarkers[officer.id].setTitle(`${officer.name} — ${statusText[officer.status]}`);
        } else {
            const marker = new google.maps.Marker({map, position, icon: portraitIcon(officer), title: `${officer.name} — ${statusText[officer.status]}`});
            marker.addListener('click', () => selectOfficer(officer.id)); officerMarkers[officer.id] = marker;
        }
    }
    function renderList() {
        const visible = filtered(); elements.officerCount.textContent = `${visible.length} of ${officers.size} officers`;
        elements.clearOfficerSearch.hidden = !elements.officerSearch.value;
        const hasFilters = [elements.officerSearch,elements.teamFilter,elements.statusFilter,elements.areaFilter].some(control => control.value);
        const markup = visible.length ? visible.map(officer => `<button type="button" class="officer-row ${selectedId === Number(officer.id) ? 'is-selected' : ''}" aria-pressed="${selectedId === Number(officer.id)}" data-officer-id="${Number(officer.id)}">${avatar(officer)}<span class="status-dot status-${officer.status}" aria-hidden="true"></span><span class="officer-copy"><strong>${escapeHtml(officer.name)}</strong>${officer.personnel_id ? `<small>${escapeHtml(officer.personnel_id)}</small>` : ''}<small>${escapeHtml(officer.team)}${officer.area ? ` · ${escapeHtml(officer.area)}` : ''}</small><small class="officer-age">${statusText[officer.status]} · ${ageText(officer.received_at)}</small></span>${officer.low_accuracy ? '<span class="accuracy-alert" title="Low GPS accuracy" aria-label="Low GPS accuracy">!</span>' : ''}</button>`).join('') : `<div class="location-empty"><strong>${hasFilters ? 'No matching officers' : 'No locations yet'}</strong>${hasFilters ? 'Try another name, team, area, or status.<br><button type="button" class="location-button" data-reset-filters>Clear filters</button>' : 'Officer positions appear here when location reports are received.'}</div>`;
        if (markup !== lastListMarkup) {
            const focusedId = elements.officerList.contains(document.activeElement) ? document.activeElement.dataset.officerId : null;
            elements.officerList.innerHTML = markup;
            lastListMarkup = markup;
            if (focusedId) elements.officerList.querySelector(`[data-officer-id="${Number(focusedId)}"]`)?.focus({preventScroll:true});
        }
        const visibleIds = new Set(visible.map(item => item.id));
        Object.entries(officerMarkers).forEach(([id, marker]) => marker.setMap(visibleIds.has(Number(id)) ? map : null));
        if (selectedId !== null && !visible.some(officer => Number(officer.id) === selectedId)) closeSelection();
    }
    function updateSummary() {
        const list = [...officers.values()]; list.forEach(item => item.status = computedStatus(item));
        elements.summaryOnDuty.textContent = list.length;
        elements.summaryTeams.textContent = list.filter(item => item.status === 'active').length;
        elements.summaryAlerts.textContent = list.filter(item => item.status === 'delayed').length;
        elements.summaryOffline.textContent = list.filter(item => item.status === 'offline').length;
        elements.summaryOffDuty.textContent = list.filter(item => item.status === 'off_duty').length;
    }
    function fillOptions(select, values, key = null) {
        const current = select.value; const first = select.options[0].outerHTML;
        select.innerHTML = first + values.map(value => `<option value="${escapeHtml(key ? value[key] : value)}">${escapeHtml(key ? value.name : value)}</option>`).join('');
        select.value = [...select.options].some(option => option.value === current) ? current : '';
    }
    function syncSubscriptions() {
        const selectedTeam = Number(elements.teamFilter.value);
        const desired = new Set((selectedTeam ? [selectedTeam] : authorizedTeamIds).filter(Boolean));
        [...subscriptions].filter(id => !desired.has(id)).forEach(id => {
            window.Echo.leave(`team.${id}.locations`);
            subscriptions.delete(id);
        });
        [...desired].forEach(id => {
            if (subscriptions.has(id)) return;
            subscriptions.add(id);
            window.Echo.private(`team.${id}.locations`).listen('.OfficerLocationUpdated', event => {
                const officer = event.officer; officer.status = computedStatus(officer); officers.set(Number(officer.id), officer);
                upsertMarker(officer); renderList(); updateSummary(); renderSelected();
            });
        });
    }
    async function loadLocations({fit = false} = {}) {
        locationRequest?.abort();
        locationRequest = new AbortController();
        const controller = locationRequest;
        const version = ++requestVersion;
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        elements.refreshLocations.disabled = true;
        elements.refreshLocations.setAttribute('aria-busy', 'true');
        elements.officerList.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(endpoint, {headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', signal:controller.signal});
            if (response.status === 401 || response.status === 419) { window.location.reload(); return; }
            if (!response.ok) throw new Error('Location data request failed');
            const data = await response.json();
            if (version !== requestVersion) return;
            serverOffset = new Date(data.server_time).getTime() - Date.now();
            officers.clear(); data.officers.forEach(officer => { officer.status = computedStatus(officer); officers.set(Number(officer.id), officer); upsertMarker(officer); });
            Object.keys(officerMarkers).filter(id => !officers.has(Number(id))).forEach(id => { officerMarkers[id].setMap(null); delete officerMarkers[id]; });
            fillOptions(elements.teamFilter, data.teams, 'id'); fillOptions(elements.areaFilter, data.areas);
            authorizedTeamIds = data.teams.map(team => Number(team.id)); syncSubscriptions();
            hasLoaded = true;
            elements.locationDataStatus.hidden = true;
            renderList(); updateSummary(); renderSelected();
            if (map && (fit || !hasFittedBounds)) fitBounds();
        } catch (error) {
            if (version !== requestVersion) return;
            elements.locationDataStatus.hidden = false;
            elements.locationDataStatus.textContent = hasLoaded ? 'Could not refresh. Showing previously received locations. Try Refresh.' : 'Officer locations could not be loaded. Try Refresh.';
            if (!hasLoaded) {
                elements.officerList.innerHTML = '<div class="location-empty"><strong>Locations unavailable</strong>Use Refresh to try again.</div>';
                lastListMarkup = '';
                elements.officerCount.textContent = 'Unavailable';
            }
        } finally {
            window.clearTimeout(timeout);
            if (version === requestVersion) {
                elements.refreshLocations.disabled = false;
                elements.refreshLocations.setAttribute('aria-busy', 'false');
                elements.officerList.setAttribute('aria-busy', 'false');
            }
        }
    }
    function fitBounds() {
        const points = filtered().filter(item => item.latitude != null);
        if (!map || !points.length) return;
        if (points.length === 1) { map.setCenter({lat:Number(points[0].latitude),lng:Number(points[0].longitude)}); map.setZoom(15); }
        else { const bounds = new google.maps.LatLngBounds(); points.forEach(item => bounds.extend({lat:Number(item.latitude),lng:Number(item.longitude)})); map.fitBounds(bounds, {top:60,right:60,bottom:60,left:!rosterCollapsed && root.querySelector('.location-main').clientWidth > 760 ? 350 : 60}); }
        hasFittedBounds = true;
    }
    window.initOfficerMap = () => {
        map = new google.maps.Map(document.getElementById('officerMap'), {center:{lat:center.latitude,lng:center.longitude},zoom:13,mapTypeControl:false,streetViewControl:false,fullscreenControl:true,fullscreenControlOptions:{position:google.maps.ControlPosition.RIGHT_TOP},zoomControlOptions:{position:google.maps.ControlPosition.RIGHT_CENTER}});
        elements.mapStatus.hidden = true;
        [...officers.values()].forEach(upsertMarker); fitBounds();
    };
    function loadMap() {
        const showMapError = message => { elements.mapStatus.hidden = false; elements.mapStatus.textContent = message; elements.mapStatus.classList.add('is-error'); };
        if (!root.dataset.mapKey) { showMapError('Map unavailable. Ask your administrator to configure the map service. Officer details are still available in the list.'); return; }
        window.gm_authFailure = () => {
            showMapError('The map service could not connect. Ask your administrator to check map access. Officer details are still available in the list.');
        };
        const mapTimeout = window.setTimeout(() => { if (!map) showMapError('The map is taking longer to load. Refresh the page to try again. Officer details are still available in the list.'); }, 20000);
        const script = document.createElement('script'); script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(root.dataset.mapKey)}&callback=initOfficerMap`; script.async = true; script.onerror = () => { window.clearTimeout(mapTimeout); showMapError('Map could not load. Refresh the page to try again. Officer details are still available in the list.'); }; document.head.appendChild(script);
    }
    function closeSelection({restoreFocus = false} = {}) {
        const previousId = selectedId;
        selectedId = null;
        elements.selectedOfficer.hidden = true;
        if (restoreFocus) {
            setRosterCollapsed(false);
            renderList();
            (elements.officerList.querySelector(`[data-officer-id="${Number(previousId)}"]`) || elements.officerSearch).focus({preventScroll:true});
        }
    }
    elements.officerList.addEventListener('click', event => {
        const row = event.target.closest('[data-officer-id]');
        if (row) selectOfficer(row.dataset.officerId);
        if (event.target.closest('[data-reset-filters]')) {
            [elements.officerSearch,elements.teamFilter,elements.statusFilter,elements.areaFilter].forEach(control => { control.value = ''; });
            syncSubscriptions(); renderList(); elements.officerSearch.focus();
        }
    });
    elements.toggleOfficerPanel.addEventListener('click', () => setRosterCollapsed(!rosterCollapsed));
    elements.closeOfficerDetails.addEventListener('click', () => closeSelection({restoreFocus:true}));
    elements.selectedOfficer.addEventListener('keydown', event => { if (event.key === 'Escape') closeSelection({restoreFocus:true}); });
    elements.clearOfficerSearch.addEventListener('click', () => { elements.officerSearch.value = ''; renderList(); elements.officerSearch.focus(); });
    elements.officerSearch.addEventListener('input', event => { if (!event.isComposing) renderList(); });
    elements.officerSearch.addEventListener('compositionend', renderList);
    [elements.statusFilter,elements.areaFilter].forEach(control => control.addEventListener('change', renderList));
    elements.teamFilter.addEventListener('change', () => { syncSubscriptions(); renderList(); loadLocations(); });
    elements.refreshLocations.addEventListener('click', () => loadLocations({fit:true}));
    elements.fitAllLocations.addEventListener('click', fitBounds);
    const connection = window.Echo?.connector?.pusher?.connection;
    if (connection) {
        const showConnected = () => { elements.liveConnection.className = 'connection-state is-live'; elements.liveConnection.innerHTML = '<span></span>Connected'; };
        connection.bind('connected', () => { showConnected(); loadLocations(); });
        connection.bind('disconnected', () => { elements.liveConnection.className = 'connection-state is-warning'; elements.liveConnection.innerHTML = '<span></span>Live connection unavailable. Reconnecting…'; });
        connection.bind('error', () => { elements.liveConnection.className = 'connection-state is-warning'; elements.liveConnection.innerHTML = '<span></span>Live connection unavailable. Reconnecting…'; });
        if (connection.state === 'connected') showConnected();
    } else {
        elements.liveConnection.className = 'connection-state is-warning';
        elements.liveConnection.innerHTML = '<span></span>Live updates unavailable';
    }
    loadLocations(); loadMap(); window.setInterval(() => { if (!hasLoaded) return; renderList(); updateSummary(); renderSelected(); Object.values(officerMarkers).forEach(marker => { const officer = [...officers.values()].find(item => marker === officerMarkers[item.id]); if (officer) marker.setIcon(portraitIcon({...officer, status: computedStatus(officer)})); }); }, 10000);
}
