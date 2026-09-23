import './bootstrap';

const root = document.getElementById('locationMonitor');

if (root) {
    const endpoint = root.dataset.endpoint;
    const center = JSON.parse(root.dataset.center);
    const thresholds = JSON.parse(root.dataset.thresholds);
    const officers = new Map();
    const officerMarkers = {};
    const subscriptions = new Set();
    let authorizedTeamIds = [];
    let map;
    let infoWindow;
    let selectedId = null;
    let serverOffset = 0;
    let hasFittedBounds = false;

    const elements = Object.fromEntries(['liveConnection','summaryOnDuty','summaryOffline','summaryTeams','summaryAlerts','officerSearch','teamFilter','statusFilter','areaFilter','refreshLocations','fitAllLocations','officerList','officerCount','mapStatus'].map(id => [id, document.getElementById(id)]));
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

    function infoContent(officer) {
        const speed = officer.speed == null ? '' : `<dt>Speed</dt><dd>${(officer.speed * 3.6).toFixed(1)} km/h</dd>`;
        const accuracy = officer.accuracy == null ? '' : `<dt>GPS Accuracy</dt><dd>±${Math.round(officer.accuracy)} m${officer.low_accuracy ? ' · Low accuracy' : ''}</dd>`;
        const personnel = officer.personnel_id ? `<dt>Personnel ID</dt><dd>${escapeHtml(officer.personnel_id)}</dd>` : '';
        return `<div class="officer-info"><h3>${escapeHtml(officer.name)}</h3><span class="map-status-label status-${officer.status}">${statusText[officer.status]}</span><dl>${personnel}<dt>Team</dt><dd>${escapeHtml(officer.team)}</dd><dt>Assigned Area</dt><dd>${escapeHtml(officer.area || 'Not assigned')}</dd><dt>Supervisor</dt><dd>${escapeHtml(officer.supervisor || 'Not assigned')}</dd><dt>Time In</dt><dd>${officer.time_in ? new Date(officer.time_in).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'}) : 'Unavailable'}</dd><dt>Last Updated</dt><dd>${ageText(officer.received_at)}</dd>${accuracy}${speed}</dl><a href="${escapeHtml(officer.details_url)}">View Officer Details</a></div>`;
    }
    function selectOfficer(id) {
        const officer = officers.get(Number(id));
        if (!officer || !map || !officerMarkers[id]) return;
        selectedId = Number(id); renderList();
        map.panTo({lat: officer.latitude, lng: officer.longitude}); map.setZoom(16);
        infoWindow.setContent(infoContent(officer)); infoWindow.open({map, anchor: officerMarkers[id]});
    }
    function upsertMarker(officer) {
        if (!map || officer.latitude == null || officer.longitude == null) return;
        const position = {lat: Number(officer.latitude), lng: Number(officer.longitude)};
        if (officerMarkers[officer.id]) {
            officerMarkers[officer.id].setPosition(position);
            officerMarkers[officer.id].setIcon(markerIcon(officer.status));
            officerMarkers[officer.id].setTitle(`${officer.name} — ${statusText[officer.status]}`);
        } else {
            const marker = new google.maps.Marker({map, position, icon: markerIcon(officer.status), title: `${officer.name} — ${statusText[officer.status]}`});
            marker.addListener('click', () => selectOfficer(officer.id)); officerMarkers[officer.id] = marker;
        }
    }
    function renderList() {
        const visible = filtered(); elements.officerCount.textContent = `${visible.length} shown`;
        elements.officerList.innerHTML = visible.length ? visible.map(officer => `<button type="button" class="officer-row ${selectedId === officer.id ? 'is-selected' : ''}" data-officer-id="${officer.id}"><span class="status-dot status-${officer.status}"></span><span class="officer-copy"><strong>${escapeHtml(officer.name)}</strong>${officer.personnel_id ? `<small>${escapeHtml(officer.personnel_id)}</small>` : ''}<small>${escapeHtml(officer.team)}${officer.area ? ` · ${escapeHtml(officer.area)}` : ''}</small><small class="officer-age">${statusText[officer.status]} · ${ageText(officer.received_at)}</small></span>${officer.low_accuracy ? '<span class="accuracy-alert" title="Low GPS accuracy">!</span>' : ''}</button>`).join('') : `<div class="location-empty">${elements.teamFilter.value ? 'No active officers found for this team.' : 'No officers are currently sharing their location.'}</div>`;
        elements.officerList.querySelectorAll('[data-officer-id]').forEach(row => row.addEventListener('click', () => selectOfficer(row.dataset.officerId)));
        const visibleIds = new Set(visible.map(item => item.id));
        Object.entries(officerMarkers).forEach(([id, marker]) => marker.setMap(visibleIds.has(Number(id)) ? map : null));
    }
    function updateSummary() {
        const list = [...officers.values()]; list.forEach(item => item.status = computedStatus(item));
        elements.summaryOnDuty.textContent = list.length;
        elements.summaryTeams.textContent = list.filter(item => item.status === 'active').length;
        elements.summaryAlerts.textContent = list.filter(item => item.status === 'delayed').length;
        elements.summaryOffline.textContent = list.filter(item => ['offline', 'off_duty'].includes(item.status)).length;
    }
    function fillOptions(select, values, key = null) {
        const current = select.value; const first = select.options[0].outerHTML;
        select.innerHTML = first + values.map(value => `<option value="${escapeHtml(key ? value[key] : value)}">${escapeHtml(key ? value.name : value)}</option>`).join(''); select.value = current;
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
                upsertMarker(officer); renderList(); updateSummary();
                if (selectedId === Number(officer.id) && infoWindow.getMap()) infoWindow.setContent(infoContent(officer));
            });
        });
    }
    async function loadLocations({fit = false} = {}) {
        elements.refreshLocations.disabled = true;
        try {
            const response = await fetch(endpoint, {headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
            if (response.status === 401 || response.status === 419) { window.location.reload(); return; }
            if (!response.ok) throw new Error('Location data request failed');
            const data = await response.json(); serverOffset = new Date(data.server_time).getTime() - Date.now();
            officers.clear(); data.officers.forEach(officer => { officer.status = computedStatus(officer); officers.set(Number(officer.id), officer); upsertMarker(officer); });
            fillOptions(elements.teamFilter, data.teams, 'id'); fillOptions(elements.areaFilter, data.areas);
            authorizedTeamIds = data.teams.map(team => Number(team.id)); syncSubscriptions();
            renderList(); updateSummary();
            if (map && (fit || !hasFittedBounds)) fitBounds();
        } catch (error) { elements.officerList.innerHTML = '<div class="location-empty is-error">Officer locations could not be loaded. Try Refresh.</div>'; }
        finally { elements.refreshLocations.disabled = false; }
    }
    function fitBounds() {
        const points = filtered().filter(item => item.latitude != null);
        if (!map || !points.length) return;
        if (points.length === 1) { map.setCenter({lat:Number(points[0].latitude),lng:Number(points[0].longitude)}); map.setZoom(15); }
        else { const bounds = new google.maps.LatLngBounds(); points.forEach(item => bounds.extend({lat:Number(item.latitude),lng:Number(item.longitude)})); map.fitBounds(bounds, 70); }
        hasFittedBounds = true;
    }
    window.initOfficerMap = () => {
        map = new google.maps.Map(document.getElementById('officerMap'), {center:{lat:center.latitude,lng:center.longitude},zoom:13,mapTypeControl:false,streetViewControl:false,fullscreenControl:true});
        infoWindow = new google.maps.InfoWindow(); elements.mapStatus.hidden = true;
        [...officers.values()].forEach(upsertMarker); fitBounds();
    };
    function loadMap() {
        if (!root.dataset.mapKey) { elements.mapStatus.textContent = 'Google Maps is not configured. Set GOOGLE_MAPS_BROWSER_KEY.'; elements.mapStatus.classList.add('is-error'); return; }
        window.gm_authFailure = () => {
            elements.mapStatus.textContent = 'Google Maps rejected the API key. Allow this site in the key restrictions and enable Maps JavaScript API.';
            elements.mapStatus.classList.add('is-error');
        };
        const script = document.createElement('script'); script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(root.dataset.mapKey)}&callback=initOfficerMap`; script.async = true; script.onerror = () => { elements.mapStatus.textContent = 'Google Maps failed to initialize.'; elements.mapStatus.classList.add('is-error'); }; document.head.appendChild(script);
    }
    ['input','change'].forEach(event => [elements.officerSearch,elements.statusFilter,elements.areaFilter].forEach(control => control.addEventListener(event, renderList)));
    elements.teamFilter.addEventListener('change', () => { syncSubscriptions(); renderList(); loadLocations(); });
    elements.refreshLocations.addEventListener('click', () => loadLocations({fit:true}));
    elements.fitAllLocations.addEventListener('click', fitBounds);
    const connection = window.Echo?.connector?.pusher?.connection;
    if (connection) {
        const showConnected = () => { elements.liveConnection.className = 'connection-state is-live'; elements.liveConnection.innerHTML = '<span></span>Live monitoring restored'; };
        connection.bind('connected', () => { showConnected(); loadLocations(); });
        connection.bind('disconnected', () => { elements.liveConnection.className = 'connection-state is-warning'; elements.liveConnection.innerHTML = '<span></span>Live connection unavailable. Reconnecting…'; });
        connection.bind('error', () => { elements.liveConnection.className = 'connection-state is-warning'; elements.liveConnection.innerHTML = '<span></span>Live connection unavailable. Reconnecting…'; });
        if (connection.state === 'connected') showConnected();
    } else {
        elements.liveConnection.className = 'connection-state is-warning';
        elements.liveConnection.innerHTML = '<span></span>Live monitoring could not initialize';
    }
    loadLocations(); loadMap(); window.setInterval(() => { renderList(); updateSummary(); Object.values(officerMarkers).forEach(marker => { const officer = [...officers.values()].find(item => marker === officerMarkers[item.id]); if (officer) marker.setIcon(markerIcon(computedStatus(officer))); }); }, 10000);
}
