document.querySelectorAll('[data-clear-audit-search]').forEach((button) => {
    const input = button.form.querySelector('input[name="search"]');
    input.addEventListener('input', () => { button.hidden = input.value === ''; });
    button.addEventListener('click', () => {
        input.value = '';
        button.hidden = true;
        input.focus();
        button.form.requestSubmit();
    });
});

// Keep disclosures inside the viewport even when their table scrolls sideways.
const positionAuditDetails = (details) => {
    if (!details.open) return;
    const panel = details.querySelector('.audit-change-list');
    const anchor = details.querySelector('summary').getBoundingClientRect();
    panel.classList.add('is-positioned');
    panel.style.maxHeight = `${Math.min(320, window.innerHeight - 32)}px`;
    const left = Math.max(16, Math.min(anchor.right - panel.offsetWidth, window.innerWidth - panel.offsetWidth - 16));
    const below = anchor.bottom + 7;
    const top = below + panel.offsetHeight <= window.innerHeight - 16
        ? below : Math.max(16, anchor.top - panel.offsetHeight - 7);
    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
};
document.querySelectorAll('.audit-change-details').forEach((details) => {
    details.addEventListener('toggle', () => positionAuditDetails(details));
    details.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && details.open) {
            details.open = false;
            details.querySelector('summary').focus();
        }
    });
});
const repositionAuditDetails = () => document.querySelectorAll('.audit-change-details[open]').forEach(positionAuditDetails);
window.addEventListener('resize', repositionAuditDetails);
window.addEventListener('scroll', repositionAuditDetails, true);
