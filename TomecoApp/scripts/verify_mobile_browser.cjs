const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const runtime = path.join(process.env.USERPROFILE, '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules');
const { chromium } = require(path.join(runtime, 'playwright'));

(async () => {
  const root = path.resolve(__dirname, '..');
  const output = path.join(root, 'storage/app/private/mobile-verification');
  const html = fs.readFileSync(path.join(output, 'ticket.html'), 'utf8');
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const results = [];
  try {
    for (const width of [1440, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1000 } });
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      await page.goto('http://192.168.5.178:8000/login');
      await page.setContent(html, { waitUntil: 'networkidle' });
      await page.evaluate(async () => Promise.all([...document.images].map(img => img.decode().catch(() => {}))));
      assert.equal(await page.locator('.violation-evidence-image img').count(), 5);
      assert.equal(await page.locator('.signature-record img').count(), 2);
      assert.match(await page.locator('.violation-detail-main').innerText(), /Separate Fictional Registered Owner/);
      const metrics = await page.evaluate(() => ({
        viewport: innerWidth, scrollWidth: document.documentElement.scrollWidth,
        images: [...document.querySelectorAll('.violation-evidence-image img, .signature-record img')].map(img => ({ loaded: img.complete && img.naturalWidth > 0, width: img.getBoundingClientRect().width })),
        backLink: document.querySelector('.violation-back-button').href,
        details: [...document.querySelectorAll('.violation-detail-list dd')].map(dd => ({ width: dd.clientWidth, scrollWidth: dd.scrollWidth }))
      }));
      assert.ok(metrics.scrollWidth <= metrics.viewport + 1, JSON.stringify(metrics));
      assert.ok(metrics.images.every(img => img.loaded && img.width <= width));
      assert.ok(metrics.details.every(dd => dd.scrollWidth <= dd.width + 1));
      assert.ok(metrics.backLink.endsWith('/dashboard/violation-records'));
      assert.ok(await page.locator('.workspace').evaluate((el, width) => el.clientWidth >= Math.min(width - 60, 600), width));
      const initialExpanded = await page.locator('#sidebarToggle').getAttribute('aria-expanded');
      await page.locator('#sidebarToggle').click();
      assert.notEqual(await page.locator('#sidebarToggle').getAttribute('aria-expanded'), initialExpanded);
      await page.locator('#sidebarToggle').click();
      await page.locator('.violation-back-button').focus();
      assert.equal(await page.locator('.violation-back-button').evaluate(el => el === document.activeElement), true);
      await page.screenshot({ path: path.join(output, `ticket-${width}.png`), fullPage: true });
      await page.getByRole('heading', { name: 'Registered owner', exact: true }).scrollIntoViewIfNeeded();
      await page.screenshot({ path: path.join(output, `registration-${width}.png`), fullPage: true });
      await page.getByRole('heading', { name: 'Enforcer signature', exact: true }).scrollIntoViewIfNeeded();
      await page.screenshot({ path: path.join(output, `signatures-${width}.png`), fullPage: true });
      assert.deepEqual(errors, []);
      results.push({ width, noOverflow: true, evidenceImages: 5, signatures: 2, navigation: true, keyboardFocus: true, browserErrors: errors });
      await page.close();
    }
    fs.writeFileSync(path.join(output, 'browser-results.json'), JSON.stringify(results, null, 2));
    console.log(JSON.stringify(results));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
