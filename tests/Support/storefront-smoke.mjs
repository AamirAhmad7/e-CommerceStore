import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const fixture = JSON.parse(await readFile(new URL('../../storage/app/private/demo-smoke.json', import.meta.url), 'utf8'));
assert.equal(fixture.database, 'leather_store_demo');
assert.equal(fixture.origin, 'http://127.0.0.1:8001');
assert.ok(Date.now() - Date.parse(fixture.generated_at) < 3600000, 'Regenerate the demo fixture manifest before running.');
let chromium;
try { ({ chromium } = await import('playwright')); }
catch { throw new Error('Browser tooling missing. Install the optional local runner: npm install --no-save --package-lock=false playwright'); }
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
const page = await context.newPage();
page.setDefaultTimeout(15000);
try {
    await page.goto(fixture.origin + fixture.collection);
    await page.locator('.product-card a').filter({ has: page.locator('h2', { hasText: 'DEMO Launch Jacket' }) }).first().click();
    await page.locator('[data-color="' + fixture.color + '"]').click();
    await page.locator('[data-size="' + fixture.size + '"]').click();
    await page.locator('#cart-submit').click();
    await page.getByLabel('Discount code', { exact: true }).fill(fixture.code);
    await page.getByRole('button', { name: 'Apply', exact: true }).click();
    await page.getByRole('button', { name: 'Remove ' + fixture.code, exact: true }).waitFor();
    await page.getByRole('link', { name: 'Review Pakistan checkout in PKR' }).click();
    for (const [name,value] of Object.entries({ name:'Demo Browser Buyer',email:'browser-smoke@example.test',phone:'03000000000',address:'DEMO ONLY - Do not dispatch',city:'Sialkot',region:'Punjab' })) {
        await page.locator('[name="' + name + '"]').fill(value);
    }
    await page.locator('[name="payment_method"][value="cod"]').check();
    await page.locator('[name="reviewed"]').check();
    await page.getByRole('button', { name: /^Place order/ }).click();
    await page.waitForURL(/\/orders\/[a-zA-Z0-9]{64}$/);
    assert.match(await page.locator('main').innerText(), /SMOKE10/);
    await page.goto(fixture.origin + fixture.product + '?color=' + fixture.sold_color + '&size=' + encodeURIComponent(fixture.sold_size));
    assert.equal(await page.locator('#cart-submit').isDisabled(), true);
    console.log('PASS: collection -> exact size -> cart -> promotion -> guest COD; sold-out SKU stays unbuyable.');
} finally { await context.close(); await browser.close(); }
