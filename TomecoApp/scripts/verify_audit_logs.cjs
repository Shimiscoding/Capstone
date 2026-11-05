const { chromium } = require(process.argv[2] || 'playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 960 } });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto('http://127.0.0.1:8766/dashboard/audit-logs');
        const rows = page.locator('.audit-log-table tbody tr');
        assert.equal(await rows.count(), 20);
        assert.equal(await page.getByRole('button', { name: 'Clear audit log search' }).isVisible(), false);
        assert.equal(await page.getByRole('link', { name: 'Audit Log', exact: true }).count(), 1);
        assert.equal(await page.getByRole('link', { name: 'Enforcer activity', exact: true }).count(), 0);
        await Promise.all([
            page.waitForNavigation(),
            page.getByRole('combobox', { name: 'Filter by actor role' }).selectOption('officer'),
        ]);
        assert.equal(await rows.count(), 8);
        assert.ok((await rows.allTextContents()).every((text) => text.includes('Sample Enforcer')));
        await Promise.all([
            page.waitForNavigation(),
            page.getByRole('combobox', { name: 'Filter by activity' }).selectOption('created'),
        ]);
        assert.equal(await rows.count(), 3);
        await rows.first().locator('summary').click();
        const snapshot = rows.first().locator('.audit-change-list');
        assert.ok(!(await snapshot.innerText()).includes('Empty'));
        assert.equal(await snapshot.locator('.audit-old-value').count(), 0);
        assert.equal(await snapshot.locator('i').count(), 0);
        await page.screenshot({ path: path.resolve('storage/app/private/audit-log-desktop.png') });

        const roleFilter = page.getByRole('combobox', { name: 'Filter by actor role' });
        await roleFilter.focus();
        await roleFilter.press('Alt+ArrowDown');
        await page.screenshot({ path: path.resolve('storage/app/private/audit-log-role-dropdown.png') });
        await roleFilter.press('Escape');
        await Promise.all([page.waitForNavigation(), roleFilter.selectOption('supervisor')]);
        assert.ok((await rows.allTextContents()).every((text) => text.includes('Sample Supervisor')));

        const search = page.getByRole('searchbox', { name: 'Search audit log' });
        await search.fill('No matching event');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Search', exact: true }).click()]);
        assert.ok(await page.getByText('No activity found', { exact: true }).isVisible());
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Clear audit log search' }).click()]);
        assert.equal(await rows.count(), 3);
        assert.equal(await search.inputValue(), '');
        await Promise.all([page.waitForNavigation(), page.getByRole('link', { name: 'Reset filters' }).click()]);
        assert.equal(await rows.count(), 20);
        await Promise.all([page.waitForNavigation(), page.getByRole('link', { name: 'Next', exact: true }).click()]);
        assert.equal(await rows.count(), 4);

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('http://127.0.0.1:8766/dashboard/audit-logs?actor_role=officer&event=created');
        assert.ok(await page.getByRole('combobox', { name: 'Filter by actor role' }).isVisible());
        const dimensions = await page.evaluate(() => ({ width: document.documentElement.clientWidth, scroll: document.documentElement.scrollWidth }));
        assert.ok(dimensions.scroll <= dimensions.width, JSON.stringify(dimensions));
        await rows.first().locator('summary').scrollIntoViewIfNeeded();
        await rows.first().locator('summary').focus();
        await page.keyboard.press('Enter');
        assert.equal(await rows.first().locator('details').getAttribute('open'), '');
        await rows.first().locator('.audit-change-list.is-positioned').waitFor();
        const box = await rows.first().locator('.audit-change-list').boundingBox();
        assert.ok(box.x >= 0 && box.x + box.width <= 391, JSON.stringify(box));
        await page.screenshot({ path: path.resolve('storage/app/private/audit-log-mobile.png') });
        await page.keyboard.press('Escape');
        assert.equal(await rows.first().locator('details').getAttribute('open'), null);
        assert.deepEqual(errors, []);
        console.log('PASS: single Audit Log sidebar link, role/action filters, created values, native dropdown, search, empty state, clear/reset, pagination, keyboard disclosure, and phone layout.');
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
