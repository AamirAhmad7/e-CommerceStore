# Leather Store

## Reachable filters and live search - 22 November 2026

Shop and collection pages now use a sticky left filter sidebar on desktop (1024px and wider). Its own scroll area keeps long size lists accessible. On smaller screens, a sticky Filters & sort bar opens a panel limited to 65% of the viewport; the rest of the catalog remains visible. Escape closes the panel and returns focus to its toggle. The short main navigation stays across the top on desktop and uses a collapsible, height-limited two-column menu on mobile. Filters remain native GET controls; no-JavaScript visitors can expand/collapse and submit them normally.

Both search fields now offer up to six public product suggestions after two characters and a 250ms pause. Search code loads only on focus. Requests are cancelled when typing continues; outdated responses cannot replace newer suggestions. Arrow keys/Enter select a result; Escape dismisses suggestions; Enter without a highlighted result submits the original search form. Show results preserves the current collection/category and filter choices. Exact SKU suggestions retain the SKU's color/size in the product URL. No draft or inactive-category products are exposed.

Verification: focused PHP checks cover publication, search matching/limits, unchanged cart/session state, exact-SKU links and shared sidebar markup; isolated Node interaction tests cover debounce, aborted/stale responses, keyboard selection and preserved collection filters. These Node tests are not a real browser visual check. Suggestions execute one SQL query for up to six matches; one-character input executes none. Listing queries remain 11 for both 1 and 12 cards with a warm settings cache. Production assets rebuilt; no migration or catalog write is required. Refresh /shop to load the new build. Mobile visual acceptance remains unverified.


## Storefront filters and customer care - 22 November 2026

Open [Shop](http://127.0.0.1:8000/shop) or the [demo shop](http://127.0.0.1:8001/shop). Filters now start as a compact toolbar, expanding into grouped category/color/size chips, price inputs, sorting and availability. Apply once with Show results; GET URLs, pagination and Back behavior remain. Closing the panel preserves unfinished choices locally. No per-keystroke requests or new library.

WhatsApp now has a visible branded SVG icon and text beside product purchase controls, a floating help button and footer/contact links. Product inquiry buttons retain the selected color/size context. A blank WhatsApp number hides the links. The new responsive footer includes real category/collection links, Latest arrivals, Find my order and customer information pages: /about-us, /contact-us, /help, /privacy and /terms, alongside existing /delivery-and-returns. FAQs expose the existing size guide, quick add, restock alerts, order recovery and promotion workflows.

Owner edits: **Settings > Store settings** for the store name, WhatsApp number, About story, optional contact address/support hours, privacy notice and ordering terms. Saves publish immediately and invalidate the public-settings cache. **Settings > Commerce settings** remains the source of contact email, payment options and delivery/returns policy. No business history, address, opening hours or return promises were invented. Default information pages describe existing shopping behavior; replace policy wording with your own approved text as needed.

Reviewed the official public sites of [J.](https://www.junaidjamshed.com/pages/contact), [Outfitters](https://outfitters.com.pk/), [Breakout](https://www.breakout.com.pk/), [Almas](https://www.almas.pk/pages/faq) and [Engine](https://engine.com.pk/). Applied useful customer-care/footer, FAQ and category-browsing patterns. Existing Quick Add and measurement guidance were retained rather than duplicated. No competitor images, charts or policy promises were copied; no newsletter/account service was added.

**Previous fit/Quick Add release:** measurements already supported authoritative units, body/garment methods and image-only guides. Size & fit now highlights the selected size, supplies method-specific guidance and keeps entered material/lining/fit beside sizes. Optional model height (cm) and size worn are entered in **Catalog > Products > Edit > Product colors > Model in this color gallery**. Both values and actual color photos are required for a caption. Quick Add loads one compact choices response only when opened, uses the existing cart action, retains listing position, and refreshes rejected stock/price choices without substituting a SKU. Its JavaScript is loaded on demand.

The model-fit migration is applied in demo only. **Normal store:** from this project, run this additive migration before saving model fields (no fixtures are added):

~~~powershell
& 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe' artisan migrate --path=database/migrations/2026_11_22_050136_add_model_fit_to_product_colors.php --no-interaction
~~~

**Demo:** no migration command needed. The filter/footer/information-page changes themselves need no migration. Production assets are built; refresh the browser to load the new build. Real catalog, media, orders and publication were preserved.

Focused checks cover information-page links, escaped owner content, immediate cache invalidation, admin validation preserving input, configured contact visibility, exact product WhatsApp context, GET filters, bounded listing queries, fit/model details and Quick Add rejection paths. Listing query count is 11 for both 1 and 12 cards under the same warmed settings cache. Browser visual acceptance is still with the owner; Computer Use previously could not reliably identify the browser URL and was stopped.

Manual checks:
1. At phone width, open Filters & sort, choose size/color chips, apply and clear; return from a product using Back.
2. Select a product color and size; check the visible WhatsApp button and its inquiry text.
3. Open footer pages; enter your About text/contact details in Store settings and verify the public update.
4. Compare M/L in Size & fit, then Quick Add an exact size from a filtered collection; check confirmation, cart count and unchanged listing position.


## Owner workspace release - 22 November 2026

This release completes the newer speed/storefront/owner-workspace brief. The older 80-item roadmap below remains historical; its deferred items are outside this release.

Normal store: homepage, authenticated Purchase Orders and Promotions returned HTTP 200. Both normal and demo migration lists are fully applied, including purchasing, promotions, restock and SEO. The former missing-table errors were schema drift already corrected before this pass; no discount fallback was introduced. Normal catalog, orders, accounts, media and publication were not changed. **Normal store: no command needed. Demo: no command needed.** No migration, dependency change or asset build is required for these admin-only changes.

Start at [demo dashboard](http://127.0.0.1:8001/admin), [demo product editor](http://127.0.0.1:8001/admin/products/6/edit), or [demo product](http://127.0.0.1:8001/products/demo-launch-jacket). Admin navigation is grouped into Catalog, Orders & shipping, Purchasing, Marketing, Storefront and Settings. Dashboard counts open matching filtered lists; Ready to pack excludes unpaid bank orders and pending cancellations. There is no polling.

**Catalog > Products > Edit > Storefront readiness** now shows each color's size, purchasable-size and photo counts. Enter stock and Set PKR price open the relevant saved SKU, including from a blocker. Add sizes and Manage photos expand and scroll to the saved color locally. Stock fields distinguish physical/reserved/available; quantity preview updates when leaving the input. Stock/price actions save immediately while other form edits stay pending. A zero-price blocker defaults to Replace prices; it would otherwise be intentionally skipped by Fill missing. The size-row Receive stock action now supplies its Independent stock default correctly. Purchase deliveries still belong in Purchasing > Purchase orders > Receive delivery.

The additional owner feature is **Catalog > Sizes needing attention > In stock, missing PKR price**: only active sizes in visible products, with available stock but no positive effective checkout price. Each row opens the exact SKU's pricing action. Launch readiness distinguishes store-wide setup from product information; missing photography is optional, and inventory/price findings link to matching lists. Category and collection forms explain their different purposes.

Branding remains **Settings > Store settings > Store name** (header/footer/default metadata, immediate public-cache invalidation). **Storefront > Homepage** controls the hero: preview drafts and publish deliberately. Customer-facing menu, responsive photography, no-JS links, same-color galleries, disabled sold-out sizes, checkout revalidation and SEO are retained from the existing implementation.

Measurements below are actual local HTTP-kernel requests against the same demo fixture (13 products, 12 visible), same asset build, array sessions, and no browser/network throttling. Public-branding cache warmth can add one query; its variation is not a new optimization. The editor improvement comes from replacing per-size fresh reads with a request-scoped batch, invalidated after saves. A focused 24-size regression confirms repeated stock reads require one query, not one query per row. No mobile latency claim is made.

| Demo route | Before SQL queries | After SQL queries |
| --- | ---: | ---: |
| /shop | 12 | 11 |
| /products/demo-launch-jacket | 10 | 10 |
| /admin/products/6/edit | 48 | 24-25 |

Shop/product HTML remains 32,915 / 33,143 bytes. Editor HTML grew from 569,770 to 602,104 bytes with the new owner controls; public bundles are unchanged. Dashboard and sizes-needing-attention also rendered HTTP 200 against demo MySQL.

Focused verification passed: OwnerWorkspaceTest, StockBatchAvailabilityTest, VariantPricingTest, AddProductSizesTest, ProductVariantSaveTest, ProductUsabilityTest, DispatchSettlementTest, StorefrontLaunchTest and StorefrontRefreshTest (33 tests across these files). These cover batch reads/invalidation, exact filtered destinations/permissions, unsaved text, stock and price previews, stale stock, reservations, immediate double save with existing photos, size copying/SKUs, branding, and the cart/COD/order-recovery journey. Pint applied. Browser host remains unavailable; touch, keyboard and final visual acceptance are not claimed.

Owner acceptance (use demo for trial stock/order changes):
1. In a product editor, follow Black/M's blocker to enter stock and PKR price; inspect the preview and save. Other pending product text should remain.
2. On a phone, select Black/M and add it to cart. Black/L uses the same gallery; a sold-out size stays crossed out and disabled. The automated regression uses Classic Biker Jacket; the existing demo is named DEMO Launch Jacket.
3. Open Add sizes and Manage photos from each color summary; save a new size and immediately save again with its color photo intact.
4. Open dashboard counts and the stocked/missing-price list; check that each shows the matching records and SKU action.
5. Change the demo store name, preview a draft hero, then publish; verify the name and hero remain independent.


## Master roadmap milestone - 22 November 2026

Start at http://127.0.0.1:8001/collections/demo-launch-jackets. First admin screen: http://127.0.0.1:8001/admin/products/6/edit (DEMO Launch Jacket); expand **Search and sharing** to try the new metadata controls. Normal catalog, prices, stock, publication, credentials and uploads were preserved.

| Items | Status | Outcome / remaining work |
| --- | --- | --- |
| 1-6, 8: baseline and recovery | Verified locally | Existing purchasing, promotions, dispatch, assisted sales, discovery and restock modules retained. Local Git checkpoint, isolated restore, authenticated normal admin checks and demo baseline completed. |
| 7: browser smoke journey | Implemented | Reusable runner and guarded demo manifest prepared. Browser execution is Deferred: browser access host unavailable; optional Playwright runner is not installed. No browser order was placed. |
| 9-16: storefront speed/usability | Verified locally | Existing responsive components retained; redundant homepage queries removed and public branding/contact cache added with save invalidation. WebP/responsive media retained. AVIF encoder exists, but conversion rollout is Deferred without a measured benefit. Visual acceptance remains with owner. |
| 17-20: initial SEO | Verified locally | Owner metadata overrides/previews, draft-safe homepage SEO, PKR Product/Offer and breadcrumbs, Organization, sitemap lastmod and environment-aware indexing policy. |
| 21: redirects manager | Deferred | **Exact next work item:** safe internal redirects with loop/chain checks and deliberate retired-product destinations. |
| 22, 24: social previews and missing pages | Implemented | Existing-image social previews now include dimensions; branded 404 is implemented. Optional IndexNow is Deferred. |
| 23: merchant feeds | Deferred | Exporter and local feed validation remain to implement; no external submissions occurred. |
| 25-56: content, retention, merchandising, reporting | Deferred | Not implemented by this milestone; existing working promotions and commerce remain available. Continue phase order after item 21. |
| 57-80: production, markets, payments, launch | Deferred | No deployment, provider enrollment, live payment or production mail activation. Existing COD/manual transfer remain intact. |
| Production launch decisions | Needs owner input | Approved contact/policy wording, production host/domain and mail transport; later market destinations and verified card-merchant eligibility. These do not block local redirects work. |

### Recovery and environment status

Local Git was initialized after checking both the project and parent; checkpoint **bb0096d** preserves the pre-roadmap implementation. No remote was added or push performed. Secrets, dumps, databases, uploads, proofs, dependencies, builds and runtime files are ignored. Keep the original APP_KEY separately: encrypted database values cannot be recovered with a replacement key.

Demo backup completed at storage/app/backups/20261121-192913-e405d1 (filename uses host UTC). It was restored into **leather_store_restore_demo_20261122** and storage/app/restores/leather_store_restore_demo_20261122. **135 files** matched SHA-256 checksums, including demo-black-front.jpg and the private care-proof.png fixture. Neither running database was replaced. This is an offline restore of the baseline before the SEO migration; it is not a third running storefront.

Reusable commands (run from this project in Laragon PowerShell):

```powershell
$php = 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe'
& $php artisan store:backup --no-interaction
# Add --env=demo for the isolated demo backup. Copy the printed absolute backup path below.
& $php artisan store:restore 'ABSOLUTE_BACKUP_PATH' leather_store_restore_new_check --no-interaction
```

For a coordinated backup, pause uploads, order writes and workers first; the SQL dump is transactional but media copying is not a database transaction. The command keeps credentials off command arguments, writes only under private storage, verifies file hashes, and refuses an existing restore database/directory or any normal/demo destination. A restore remains offline: do not start its workers or publicly expose its private files. Retain backups securely and arrange off-server copies separately; none were transferred externally.

**Normal 8000 / leather_store:** all migrations applied, including nullable SEO columns. Homepage, authorized Purchasing/Promotions empty states and product editor rendered HTTP 200. **Demo 8001 / leather_store_demo:** all migrations applied; logged mail, separate media and existing demo restock worker retained. No migration command remains. The known demo starting point was reused without resetting prior fixture edits or orders.

### Measured budget and checks

Before/after measurements used the same local MySQL demo (13 non-deleted products, 12 visible), three separate PHP HTTP-kernel requests per route, empty array-backed cart/session, and no browser/network throttling. Warm public-branding cache: homepage **14 -> 10 SQL queries**, collection **14 -> 13**, product **12 -> 10**, empty cart **4 -> 3**. Homepage HTML stayed 23,716 bytes during the optimization; product HTML increased from 31,283 to about 33,143 bytes after structured data was added. Cold branding-cache fill adds one SQL query. Warm homepage render samples were roughly 141-251 ms before and 206-210 ms after; timing noise does not establish a latency improvement. The query reductions are the measured improvement.

Current built assets: CSS 33.05 kB / 8.16 kB gzip; shared JS 5.07 kB / 1.89 kB gzip; product-only JS 5.66 kB / 1.96 kB gzip. Keep this fixture within 12 homepage/product queries (warm branding cache), 15 collection queries, 40 kB product HTML and 15 kB combined gzip storefront assets unless a feature justifies the increase. These are regression budgets, not mobile performance promises. Repeat with `& $php artisan store:baseline --env=demo --no-interaction`. The baseline command uses isolated array sessions; `--path=/admin/promotions --admin` checks an actual authorized local page, not a login redirect.

Public branding/WhatsApp values are cached for five minutes in a file cache (configurable STORE_PUBLIC_CACHE_STORE), separately by database and app URL. Store Settings saves invalidate immediately. Out-of-band SQL changes may take up to five minutes. Stock, prices, carts, previews and private order content are not placed in this cache. Existing collection data is reused for homepage navigation.

Focused PHP checks: **16 tests / 145 assertions passed** (not the full suite). They cover recovery destination guards, branding/contact invalidation, homepage publication and SEO, exact-price availability markup, private preview exclusion, sitemap/robots and missing-page behavior. A stale WhatsApp test fixture was updated to supply the price and stock its selected-size assertion requires; purchase eligibility was not weakened.

### SEO controls and browser smoke

Products, Categories, Collections and Homepage now have **Search and sharing** overrides with title/description previews. Empty overrides use existing names/text. Homepage edits stay private until Publish. USD is browsing-only, so no USD checkout Offer is emitted; unknown prices/stock and unpublished products do not produce purchasable offers. No ratings, GTINs, manufacturer claims or policy commitments are fabricated. Implementation references: [Google Product structured data](https://developers.google.com/search/docs/appearance/structured-data/product-snippet), [Google noindex guidance](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

Indexing defaults off. Production indexing requires APP_ENV=production, a non-local APP_URL and STORE_INDEXING_ENABLED=true, followed by the normal configuration-cache rollout. Dynamic /robots.txt replaces the old static allow-all file; HTML also carries noindex locally. Robots directives are not access control. Existing private authorization remains required. Merchant feeds, redirect management, named AI-crawler settings and IndexNow are not completed yet.

The lean optional browser runner checks collection -> exact color/size -> cart -> SMOKE10 -> guest COD, plus a disabled sold-out size. It uses a fresh browser context, no screenshots, and creates one clearly fictional order. The manifest must be regenerated within an hour of running. Browser execution was attempted but stopped before opening a browser because Playwright is absent; the built-in browser access host is also unavailable. To enable and run it locally with your installed Chrome:

```powershell
npm install --no-save --package-lock=false playwright
& $php artisan store:smoke-fixture --env=demo --no-interaction
node tests/Support/storefront-smoke.mjs
```

Manual phone acceptance: open the demo collection, choose Black and an available size, inspect the size guide, add one jacket, apply SMOKE10, then review a fictitious COD order. Confirm the crossed-out XL cannot be added. Review the new search title in admin, and check that saving a homepage draft does not change the live title.

**Resume:** item 21, redirects manager. No credential or external approval is needed for this next local slice. The smallest environment check is `& $php artisan migrate:status --no-interaction`; continue from the current working tree rather than reinitializing fixtures. The 80-item roadmap is not complete.


## Price all sizes of a jacket

Open **Admin > Products > Classic Biker Jacket > Edit > Manage prices**. Saved variants are grouped by color and show size, SKU and effective PKR/USD prices, labelled **product price** (inherited), **variant override**, or **Not set**. Save newly added sizes first. Opening/canceling preserves pending product edits. If you have pending edits in the relevant price fields, save those first; other unsaved edits can remain pending.

Choose individual checkboxes, **Select all** within a color, or **Quick selection > All Black sizes / All saved variants**; check the selected count. Choose **PKR**, retain the default **Fill missing prices only**, and enter **18500**. Click **Preview prices**, review every SKU's current/proposed value and change/skip status, then **Confirm previewed prices**. With Black S/L missing, M at PKR 18,500 and XXL at PKR 20,000, exactly S/L change; M/XXL, Brown and USD remain unchanged. An inherited product price counts as already set (as does an explicit zero). Use **Replace prices for selected variants** deliberately to change an existing effective price. Select one SKU for an individual edit. Enter USD independently; no currency conversion is performed. Amounts must be positive, with at most two decimal places; blank never clears a price.

Confirmation saves the selected variant overrides immediately and reports updated/skipped counts. The product form's affected price fields are synchronized, so a later **Save changes** retains them without losing other edits. Changes are transactional; a changed price, product base price or selection invalidates the preview. Nothing is overwritten: click **Preview prices** again, review the latest values and confirm. Existing order amounts remain historical snapshots. No real catalog prices were changed during implementation.

Focused verification: **5 tests / 74 assertions passed on isolated MySQL** (`VariantPricingTest` plus the existing money/base/override test). Covered fill-missing and the XXL exception, selected replacement, independent USD, inheritance, blank/precision validation, ownership/authorization, conflict review, repeat submission, pending-edit preservation, subsequent product saves, storefront/cart prices and historical order amounts. Targeted Pint passed (`--dirty` is unavailable without Git). No migration, dependency install, build or local command is needed. Save pending edits before reloading an already-open editor. Stock, image and storefront browser acceptance checks remain deferred; no browser automation or full suite was run.

## Enter stock and place product photos

Open **Admin > Products > Edit product > Product colors > Black > Sizes for this color**. Each saved row shows its size/SKU and **Available**, On hand and Reserved, even when collapsed. On hand includes units reserved for orders; Available subtracts Reserved once. Zero stays visible. New sizes must be saved before entering opening stock. Copying sizes to Brown still copies only labels and generates Brown SKUs, not stock, prices or photos.

- **Opening 8 units:** expand Black / M > **Manage stock > Opening stock (final physical count)** > enter **8**, add a reason, review the SKU and expected balance, then **Confirm stock adjustment**.
- **Receive 5 more:** same row > **Manage stock > Receive stock (quantity to add)** > enter **5** and a reason. Leave the quantity field to preview **+5, new on hand 13**, then confirm.
- **Correct 13 to 12:** choose **Correct a count (final physical count)** > enter **12**, review **-1** and unchanged reservations, supply the recount reason, then confirm. With 3 reserved units, Available becomes 9. Counts below reservations are rejected. If stock changes or the same submission is repeated, close/reopen Manage stock and review the current balance; pending product edits remain intact. Stock is audited immediately, refreshes in the row and is never replayed by Save changes. **Stock history** shows the movements.

For shared images, open **Products > Edit > Product details and size-guide images > Photo use and alt text** after saving uploads. Each image shows its current destination and has a **Storefront placement** selector: **Product details** renders it once below the product with its alt description; **Size guide** uses the single control beside size selection, together with any measurement table; **Awaiting classification** stays hidden. Use the existing **Move shared photo > destination color > Save product and move photo** for a color-specific image; this action saves pending product edits first. Files, order, alt text and media identity are retained. Ordinary color photos remain under **Product colors > color > Photos for this color**.

No migration, package install or asset build is needed for this update; existing styling is reused. Save any pending edits before reloading an already-open editor to load the new controls. Demo-only manual location: http://127.0.0.1:8001/admin/products/7/edit (Black/M starts without configured stock unless you have already adjusted it). Automated verification uses isolated test records/files, not real catalog records. Browser automation was intentionally skipped.

Focused verification: **14 tests / 385 assertions passed on isolated MySQL** (`VariantStockTest`, `ProductUsabilityTest`, `ProductPhotosTest`, `ProductVariantSaveTest`, and the single batch/copy workflow test in `AddProductSizesTest`). Covered opening 8, receipt +5, counted correction -1 with reservations, stale/repeated submission rejection, preserved pending edits and repeated main saves, single shared-detail placement, combined image/table guide, hidden unclassified images, retained photo identity/alt/order, and copying sizes without stock/prices. Targeted Pint passed; `--dirty` cannot run because this checkout has no Git repository. No full suite or browser automation was run.

## Storefront design and launch readiness (2026-11-21)

The storefront now uses consistent mobile navigation, a readable cart count, quieter surfaces, larger photography, stable product-card framing and a stronger Add to cart action. An unconfigured homepage uses eligible public catalog products and a matching photo as a fallback; it does not publish draft homepage content. Configure your own homepage before launch. Pending conversions now show the original photo as a thumbnail instead of Photo 1/Photo 2; the queued responsive WebP versions take over when ready. Add to cart remains disabled when the selected size lacks a positive PKR/display price, confirmed stock, or availability. Set real prices and audited stock instead of bypassing this check.

### Owner controls

- **Admin > Homepage:** save a draft headline, supporting text, hero/alt, primary destination and featured collections; Preview saved draft; Publish homepage when approved. Existing published snapshots and retained hero copies still protect the live page. Blank hero/empty featured sections can use the public catalog fallback. Homepage design does not change publication flags.
- **Admin > Size guides > New size guide:** name a reusable guide, choose cm or inches, explicitly choose garment or body measurements, and add measurements. Each measurement names its method (full circumference, flat width, or length) and its values by size. Add your own How to measure note. No real measurements are invented.
- **Products > Edit > Sizing and product details:** select a reusable guide, or choose Product-specific measurements for an override. Updating a shared guide updates all assigned products; an override stays independent. Existing uploaded images classified as Size guide continue to appear. Material, lining, fit and care are optional and only populated fields are rendered.
- **Customer Size guide:** beside size selection, opens as a keyboard-accessible disclosure without replacing the gallery or changing color, size or quantity. Measurements retain their original units/values without JavaScript. The optional browser-only cm/in display uses 2.54 and rounds converted values to one decimal; switching back restores the exact saved value. Wide tables scroll inside their own region.
- **Admin > Commerce Settings > Customer service policy:** maintain delivery information, returns conditions and contact email. Approve public returns wording explicitly. A return window is shown only when requests are enabled, wording exists and approval is checked; its days come from the existing return workflow setting. Do not repeat rates/window numbers in prose. Product, cart and footer link to Delivery & returns. Charges are reviewed at checkout; there is no unsupported advance shipping estimate. Pakistan checkout charges explicit PKR prices even when browsing USD.
- **Admin > Launch readiness:** read Needs configuration, Manual check and Ready findings with direct admin links. This is advisory, not approval. It checks public purchasable products, prices, stock, color photos, published homepage, unavailable featured collections, shipping/tax/payment configuration, customer contact and policy text. Sold-out sizes are normal; zero purchasable products needs attention. WhatsApp remains optional. Public URL, actual email delivery, backup restoration, social previews and owner acceptance remain honest configuration/manual checks. No polling or writes occur on viewing.

### Prepared local demo

Use **demo@example.test / Demo-only-2026!** at http://127.0.0.1:8001/admin. All new measurements, stock, policies and the order below are isolated fixtures. Available Black catalog photography was copied into demo storage without changing its original files or ownership. Brown deliberately remains photo-less when no matching catalog photograph exists; it never borrows Black's image.

- Homepage: http://127.0.0.1:8001/
- Collection: http://127.0.0.1:8001/collections/demo-launch-jackets
- Shopping jacket: http://127.0.0.1:8001/products/demo-launch-jacket?size=M
- Delivery/returns: http://127.0.0.1:8001/delivery-and-returns
- Cart and recovery: http://127.0.0.1:8001/cart and http://127.0.0.1:8001/find-order
- Readiness: http://127.0.0.1:8001/admin/launch-readiness
- Reusable guides: http://127.0.0.1:8001/admin/size-guides
- Shopping product editor: http://127.0.0.1:8001/admin/products/6/edit
- Pending batch browser check: http://127.0.0.1:8001/admin/products/7/edit (DEMO Size Setup Jacket, Black M/L with one photo, Brown initially empty)
- Captured HTTP test order: **SF-01M7Y517A31YQWA62FQS0CAVJT**, **Admin > Orders > order 5**. Black/M, quantity 2, subtotal PKR 25,800, shipping PKR 250, tax 0, total **PKR 26,050**, COD **unpaid**. Test recovery email: `launch-buyer@example.test`. Open the order's message history in Filament to inspect its captured confirmation/recovery text; demo mail is forced to the local log, not sent externally.

The existing demo server remains on port 8001. If stopped, use the following from the project folder; the fixtures and built assets are already prepared:

```powershell
$env:PATH = 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64;' + $env:PATH
php artisan serve --env=demo --host=127.0.0.1 --port=8001 --no-reload
```

To view on your phone on the same trusted Wi-Fi, stop the loopback demo server first (Ctrl+C in its terminal). Your PC's observed LAN address was **192.168.1.17**; check `ipconfig` if it changes. Start the demo with its own process-level URL so image, collection and canonical links use the reachable host:

```powershell
$env:APP_URL = 'http://192.168.1.17:8001'
php artisan serve --env=demo --host=0.0.0.0 --port=8001 --no-reload
```

Then open **http://192.168.1.17:8001/** on your phone. If prompted, allow PHP only on your private network. Do not expose this local demo to the public Internet. Remove the process override afterwards with `Remove-Item Env:APP_URL`; it does not edit your real `.env`.

For new demo uploads or test-order messages, use a second terminal with the same PHP PATH (and matching APP_URL if using the phone):

```powershell
php artisan queue:work database --env=demo --queue=default,media --tries=2 --timeout=120
```

Re-running `php artisan db:seed --env=demo --class=StorefrontLaunchDemoSeeder` is optional: it refuses other databases/disks, retains existing fixture orders/stock and only configures demo homepage/policies once. Never use demo seeders against `leather_store`.

### Acceptance status and next owner decisions

The Black/Brown **browser** check remains pending at the owner's request. Open demo product 7, Black > Sizes for this color > Add sizes; enter S/M/L/XL. Expect two new (S/XL) and two existing (M/L). Apply, then Brown > Add sizes > Use sizes from... > Black. Save twice without refresh; reopen and verify exactly four sizes per color, destination-specific SKUs, and the original photo only on Black. Automated component coverage still exercises this workflow and failure/retry behavior.

The browser access host was unavailable on the resumed run. No 390px/1440px visual pass, live keyboard/network check or paired before/after capture is claimed. Remaining eyes-on checks: homepage hierarchy at 390px and 1440px (also 320px overflow), card photo/destination agreement, product thumbnail/size selection without a gallery reload, guide open/close and units without lost quantity/selection, cart quantity, and the full phone COD journey. Use Black/M for a purchase and XL for sold out; Brown exercises the honest no-photo state.

Verification completed: **64 tests / 987 assertions passed on isolated MySQL**, covering launch readiness, guides/policies, batch sizes, gallery-dependent double-save, product usability/media, homepage/collections, cart/checkout, order care, WhatsApp and SKU search. The new launch tests also passed separately on SQLite (**6 tests / 112 assertions**). Production asset build, JavaScript syntax and browser-only measurement conversion checks passed. Targeted Pint passed; `--dirty` is unavailable because this checkout has no Git repository.

Actual demo HTTP requests completed homepage → collection → Black/M → cart quantity 2 → COD checkout and fresh-session order recovery. Order 5 remains unpaid at PKR 26,050; confirmation and recovery messages were processed into local demo mail with the correct private order link. Product HTML contains a clean canonical, description and share metadata; its WebP image returned HTTP 200. The existing real product also returned HTTP 200. The additive size-guide/product-details migration is applied to both local and demo schemas; real catalog values, uploads, orders and business settings were preserved.

Public metadata checks are local HTML verification, not proof that an external social crawler can fetch localhost. Before a real Pakistan launch, approve your actual product measurements/material/care, positive PKR prices and counted stock, shipping/tax rates and payment methods, monitored contact email, delivery/returns wording, public HTTPS address and media URL. Test real email delivery, a backup restoration and public social previews. International checkout remains out of scope.



## Set up sizes for a new jacket

In **Admin > Products > Edit > Product colors > Black > Sizes for this color > Add sizes**, choose S, M, L and XL (or type a custom/numeric label such as 42 and press Enter). If M and L already exist, Preview lists them as already present and shows only S and XL with suggested SKUs. Matching ignores surrounding whitespace and case in both saved and pending rows; existing labels and custom SKUs remain unchanged. Correct any marked SKU conflict before **Add pending sizes**. This updates the form only: **Save changes** persists the batch. Cancel preserves existing edits, and repeating a selection does not add duplicates.

Add/name Brown, open its **Add sizes** dialog and choose **Use sizes from... > Black**. This copies size labels only, with Brown SKU suggestions and ordinary new-variant defaults. It does not copy photos, barcode, price overrides or stock. Source sizes can be unsaved. If the destination name is blank, the preview tells you to name it first. Single-row **Add size** remains available. SKU edits in the dialog are retained when the selection changes; checks run on selection/application, not every keystroke.

After saving, **Storefront readiness** shows each color's size count, missing effective PKR checkout prices, out-of-stock count, and stock-not-configured count separately. Follow its link to the color/size rows for existing price controls and **Inventory > Adjust stock**; stock adjustments remain audited. New sizes start without stock and use the existing product-base/variant-override pricing rules. Product create/edit saves now use Filament database transactions, including all variant rows.

Isolated demo click path: **http://127.0.0.1:8001/admin/products/2/edit > Product colors > Black > Sizes for this color > Add sizes** (start the documented demo server if stopped). For the exact M/L example, use **Admin > Products > New product** in that demo and create your test jacket. No real catalog or demo fixtures were changed for this feature. Browser checks still needed: tag suggestions/custom entry, inline conflict display and correction, cancel preserving edits, new row expansion, and Black-to-Brown copy followed by two saves. Automated coverage is in `AddProductSizesTest`; the gallery-dependent double-save regression remains in `ProductVariantSaveTest`. Verification: **12 tests / 228 assertions passed on isolated MySQL** (`AddProductSizesTest`, `ProductVariantSaveTest`, `ProductUsabilityTest`), including a simulated second-insert failure, full rollback, retained pending entries and successful retry. Targeted Pint formatting passed; no frontend asset rebuild was needed.

**Find and copy variants:** In **Admin > Products**, search a full or partial SKU (for example `CBJ-BLK-M` or `CBJ-BLK`) or a product name. Each matching product appears once. Open **Edit > Product colors > a color > Sizes for this color**: saved headings already show size and SKU; new/error rows open for entry. Use the **Copy SKU** icon beside the SKU field to copy its currently displayed value, including unsaved edits. It is disabled when blank and reports **SKU copied** only on clipboard success; copying does not save or make a server request. Existing SKU blur validation is unchanged. If clipboard access is unavailable, the field is selected for manual copying.

**Ask on WhatsApp:** Open **Admin > Store Settings > WhatsApp number (optional)**, enter an international number such as `+923001234567` (8–15 digits, country code included, no spaces), then Save. Blank hides the secondary product-page link. Color/size selection updates its prefilled message locally; without a selected size it omits size/SKU. The shopper reviews and sends the message in WhatsApp. Preview pages never show this action. No number is configured by default.

## Project handoff

Updated **2026-11-20** for the next developer. This documentation-only refresh used the existing README and one focused check of routes, relevant Filament forms/actions and Blade views. No application changes, installs, builds, tests, database writes or browser checks were performed. Earlier verification results below are historical evidence, not a fresh run.

- **Product gallery cleanup, classification/move and readiness:** The README records one selected-color gallery, neutral details/size guides and hidden unclassified photos. Current code contains the product view with Size guide, shared-photo classification guidance, **Move shared photo / Save product and move photo**, and **Storefront readiness** based on saved data. **Partial/missing:** browser acceptance remains unfinished: mobile layout, keyboard navigation, keeping the selected photo when sizes change, and the move/classification workflow need a visual pass. The owner's real close-up was deliberately left unmoved. Verify in the isolated demo at **http://127.0.0.1:8001/admin/products/2/edit**: inspect readiness, classify a shared photo, then try Move shared photo. The README records earlier automated/HTTP coverage; the owner's Chrome confirmation covers the SKU fix and second save only, not this entire milestone.
- **Editable homepage, curated collections and sharing:** Current routes/forms/views contain homepage draft save/preview/publish, hero/alt and ordered featured collections; collection title/stable slug, ordered product selection by name/SKU, optional featured color, publication/preview and Copy public link; public collection cards, sharing controls and Open Graph markup. **Partial/missing:** no missing implementation was identified in this limited check, but desktop/mobile browsing, clipboard/native sharing and the complete owner workflow have not been browser-verified. External WhatsApp previews are also unverified because the demo is localhost. Verify **http://127.0.0.1:8001/collections/biker-jackets**; owner controls are **Admin > Collections** and **Admin > Homepage**. The README records 38 isolated MySQL tests / 621 assertions and successful HTTP/metadata checks, not an end-to-end browser pass.

**Next unfinished task:** complete and record one desktop and one approximately 390px mobile acceptance pass in the isolated demo: classify/move a photo and change sizes; compare homepage draft versus public content before publishing; follow Black/Brown collection cards and copy a clean share URL. Fix only issues reproduced by that pass. Use the demo startup/login instructions below; its current running state was not checked. Preserve the real catalog and uploads.

## Homepage and shoppable collections (2026-11-20)

Implemented in the existing Blade/Filament/Spatie stack without adding dependencies. Categories still classify products; collections hand-pick products across categories, with independent ordering and featured colors. Public eligibility is applied before pagination/counts. Cards load only the first gallery media record per color, use responsive WebP when ready, and link to the pictured color. Empty or unpublished collections are omitted from the homepage. Original uploads are retained; conversions run on the existing database `media` queue.

The screenshot's `Unknown column product_colors.sort_order` error was caused by pending migrations in the normal Laragon database. The existing order-care and color-order migrations, and the new additive campaign tables, have now been applied to `leather_store`. The real Classic Biker Jacket URL returns HTTP 200. Two stray PHP import lines printed by the store layout were also removed. No real catalog rows, uploads, credentials, publication flags or checkout settings were changed; no demo fixtures were seeded into the real database. The previously owner-confirmed Chrome SKU fix and double-save behavior remain recorded below and their regression tests still pass.

### Create and share a collection.

1. Open **Admin > Collections > New collection**. Enter a title and leave the field to suggest a slug. You can edit the slug before publishing. Add a short introduction and, optionally, a social-preview cover with alt text.
2. Choose **Add product**, search by name or SKU, and select products. Duplicate products are rejected. Use the reorder controls to set display order.
3. Optionally choose **Featured color** for each product. Only that product's colors are offered. For example, select Black in Biker Jackets and Brown in Earth Tones. Automatic chooses the normal eligible color. A color with no active sizes falls back; an eligible color without photos stays photo-less, never borrowing another color's image.
4. Create/save the collection, then **Preview saved collection**. Preview requires an authorized admin and sends private/no-store and noindex headers. Unsaved edits are not previewed. A draft's ordinary public URL returns 404.
5. Choose **Publish collection**, or enable Published and save. Editing the title leaves the slug unchanged. A published slug is locked; unpublish first only if deliberately changing the public URL.
6. Choose **Copy public link**, then the clipboard icon. It confirms copying; if clipboard permission is unavailable, select/copy the displayed URL manually. The customer page also provides **Share collection** (native share when supported) and **Copy link**, with an always-visible public URL for JavaScript-disabled browsing. Links omit tracking parameters and preview paths.

Published collection edits take effect on save. Homepage editing has a separate draft/publish workflow:

- Open **Admin > Homepage**. Set headline, supporting text, hero/alt text, primary Shop or published-collection destination, and ordered featured collections.
- **Save draft**, then **Preview saved draft**. The public homepage still shows its last published content. **Publish homepage** saves and publishes the current form.
- Remove a row from Featured collections and publish to remove that campaign from the homepage. This keeps its public collection link working. Unpublishing a collection removes its homepage section and makes its public URL return 404; the primary button falls back to Shop.
- Replacing a draft hero cannot delete the live hero: publishing retains a separate image copy. Earlier published hero copies remain in storage intentionally. An unconfigured homepage shows the configured store name and a working Shop link.

### Prepared isolated demo

The following are ready in `leather_store_demo` with the separate `demo` media disk and synthetic TEST IMAGE uploads:

- Homepage: http://127.0.0.1:8001/
- Published Black collection: http://127.0.0.1:8001/collections/biker-jackets
- Published Brown collection: http://127.0.0.1:8001/collections/earth-tones
- Draft (public 404): http://127.0.0.1:8001/collections/draft-campaign
- Draft staff preview: http://127.0.0.1:8001/collections/draft-campaign/preview
- Homepage staff preview: http://127.0.0.1:8001/homepage/preview
- Collections admin: http://127.0.0.1:8001/admin/collections (Biker edit `/admin/collections/1/edit`, Earth `/2/edit`, Draft `/3/edit`)
- Homepage admin: http://127.0.0.1:8001/admin/homepage
- Sitemap: http://127.0.0.1:8001/sitemap.xml

Demo login: **demo@example.test / Demo-only-2026!**. Three eligible campaign products across two categories allow reordering; the same Demo Campaign Jacket uses Black in Biker Jackets and Brown in Earth Tones. Re-running `CollectionDemoSeeder` produced exactly 3 collections, 9 selections and 1 homepage, without resetting existing fixture edits. Media jobs finished and the demo queue is empty.

Only start the demo server to view these prepared fixtures (verification server stopped at handoff):

```powershell
Set-Location C:\laragon\www\leather-store
$env:PATH = 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64;' + $env:PATH
php artisan serve --env=demo --host=127.0.0.1 --port=8001
```

When adding/replacing demo images, run this in a second terminal using the same PHP PATH:

```powershell
php artisan queue:work database --env=demo --queue=media --tries=2 --timeout=120
```

For the real site, omit `--env=demo` from the worker command. No seeding or further migrations are needed for this prepared installation. On another/fresh demo installation only, use `php artisan migrate --env=demo --force`, `php artisan db:seed --env=demo --class=CollectionDemoSeeder`, then the media worker. This seeder refuses any database/disk other than the isolated demo. It does not provision admin credentials; the existing demo owner is reused. After future frontend edits, run `npm run build`.

### Verification and remaining visual checks

Final isolated MySQL regression run: **38 tests / 621 assertions passed**, covering the 8 new collection/homepage tests plus product usability, gallery/SKU saves, product pages, commerce and default homepage. The earlier SQLite regression run passed 37 tests / 605 assertions before the additional upload/publish test. Production assets, JavaScript syntax and targeted Pint formatting passed. This folder has no Git repository, so Pint `--dirty` could not run; explicit changed PHP paths were formatted instead.

Tests cover name/SKU selection, duplicate prevention and database uniqueness, foreign-color rejection, publishing/title stability/reordering, authorized private previews, hidden/soft-deleted products and category eligibility before counts/pagination, color fallback and correct product-page destination, missing cover/empty collections, responsive cover conversion, homepage upload/publish/repeated draft saves and retained live files, clean canonical/share URLs, and sitemap exclusions. A 12-card collection request stays within 16 database queries. No per-card media model lookup is needed.

HTTP checks returned 200 for the demo homepage, both public collections and sitemap; draft public 404 and guest preview 403. A served card WebP returned `image/webp`. Returned HTML contains configured absolute canonical/OG URLs, cover alt text, clean share URLs despite `utm_source`, and Brown photo/destination agreement. This verifies local metadata, **not** an external WhatsApp/social preview; external crawlers cannot access 127.0.0.1. Set production `APP_URL` to the public HTTPS address before sharing externally.

Chrome's browser host was unavailable and native input was blocked by the Windows lock screen. Check these manually at desktop width and about 390px mobile width:

1. Open both demo collections. Confirm readable cards/no sideways overflow. Click the shared Jacket: Biker opens Black, Earth opens Brown. Try ordinary links with JavaScript disabled.
2. In Admin > Collections > Draft Campaign, preview, reorder products, save, publish, then rename its title. Confirm saved order and unchanged public slug. Try selecting the same product twice.
3. In Admin > Homepage, change headline/hero, save draft, compare Preview against the public homepage, then publish. Confirm the old live hero survived until publish. Remove one featured row and publish again.
4. Open Biker Jackets with `?utm_source=whatsapp`; use Copy link and Share collection. Verify the copied/native-shared URL has no query parameters. Check keyboard focus, clipboard feedback and manual-copy fallback.
5. Confirm images show alt text where appropriate, first visible image is prioritized, lower cards lazy-load, and mobile navigation remains comfortable. The files are converted; layout and native share UI still need this visual pass.

Changed areas: new `Collection`, `CollectionItem`, `Homepage` models and campaign migration; Collections Filament resource/pages; Homepage admin page/view; `CollectionController`, `HomeController`, `StorefrontCampaigns`; shared product card, collection/home/sitemap views; shop eager loading/card reuse; routes, store layout, CSS/JS and built assets; `CollectionDemoSeeder`, `CollectionHomepageTest`, and this README. Existing checkout services and the product repeater SKU save callback were retained.



## Product gallery and catalog usability (2026-11-20)

The product page now has one selected-color gallery and one buying panel. The separate full-width Product-wide images block is removed. Saved media files, IDs, UUIDs, alt text and existing image URLs are retained. No real catalog data, photo placement, publication, prices or inventory were changed. Dependencies and checkout/order services were left unchanged. The attached brief did not include the two referenced screenshots; implementation followed its described cases and the existing page/form code.

### Photo placement and moving an existing photo

- **Color gallery:** photographs of that color, shared by all its sizes. The first ordered photo is the hero. Reorder the upload list to choose the cover. Converted photos have actual WebP thumbnails and responsive sources; pending conversions now use original-image thumbnails (updated 2026-11-21).
- **Color-neutral detail:** upload to Product details and size-guide images, save, then open **Photo use and alt text** and choose **Product details** in Storefront placement. It appears once in the Product details area below the product, independently of the selected color gallery. It never becomes an empty color's hero.
- **Size guide:** choose **Size guide** for measurement charts. These appear behind the keyboard-accessible Size guide disclosure beside the size selector, with larger-image links.
- **Unclassified:** legacy and newly uploaded shared images stay hidden from public galleries and catalog-card fallbacks until explicitly classified or moved. They remain available in the admin. Alt text and classification belong to the media UUID, so reordering cannot transfer them to another image.

For the owner's black-jacket close-up: after applying the migration below, open the product edit page, choose **Move shared photo**, select that saved image and **Black**, then **Save product and move photo**. This validates/saves current product edits first and appends the existing media record to Black's gallery. It preserves the original, alt text, responsive files and URLs; no second upload is needed. A validation error keeps entered values and prevents the move. Save a newly added color before using it as a destination. Existing target photos keep their relative order. The move is limited to colors owned by the same product. This implementation requires the installed media-ID path generator and identical storefront conversions on product/color models; it rejects incompatible custom path generators instead of silently breaking files. The real jacket image was deliberately left untouched.

### Color, size and readiness rules

Public selectors omit colors with no active variants. Authorized Preview includes them with **No active sizes (preview only)**. Active sold-out colors remain selectable and labeled Sold out. Valid direct color links are preserved, including sold-out and photo-less colors; an older link to a hidden/deleted color falls back gracefully. Initial selection prefers a color with stock and positive display/PKR checkout prices, then uses configured color order. Move color rows with the admin reorder buttons or drag handle and save. Existing equal-order rows keep ID order until configured.

Sizes display in apparel order (XXXS, XXS, XS, S, M, L, XL, XXL, XXXL, XXXXL), then numeric order, then custom labels. Presentation normalizes common apparel case/aliases, while saved labels, SKUs, identities and historical receipts remain untouched. If two records normalize to the same label, their displayed labels include SKUs to keep them distinct. Size links retain their original saved labels. Changing size updates only price, availability and cart selection; it does not replace the gallery or fetch images. Thumbnail controls support Tab/Enter, Left/Right, Home/End; ordinary links and larger-image viewing still work without JavaScript.

The buying panel shows one selected price or range, one availability message, quantity and one Add to cart action. It says **Choose a size.** before selection. Missing setup receives concise public wording and specific Preview explanations. Cart/checkout still perform their existing server-side validation. No-photo colors show a compact message, never another color's photo.

At the top of **Products > Edit**, open **Storefront readiness** findings. This reads saved data: save changes to refresh it. It explains publication state, missing active sizes/color photos/PKR prices/opening stock and unclassified shared images. Sold out is labeled a normal stock state, not a configuration failure. It is guidance only and does not change publication policy. Saved color rows summarize name/size/photo counts; saved variants show size/SKU. New rows remain open, error rows expand, and invalid entered values are retained. The nested variant save/reload callback remains in place for the gallery-dependent SKU regression.

### Prepared isolated demo and commands

The demo migration, fixtures and WebP conversions are prepared. The synthetic images are clearly labeled TEST IMAGE; they are not real product photography. No fixtures were written to the real database.

- Public: http://127.0.0.1:8001/products/demo-gallery-jacket
- Owner edit: http://127.0.0.1:8001/admin/products/2/edit
- Authorized Preview: http://127.0.0.1:8001/products/demo-gallery-jacket/preview
- Brown (no active sizes): public `?color=3` falls back; Preview `?color=3` explains why it is hidden.
- Grey (sold out): http://127.0.0.1:8001/products/demo-gallery-jacket?color=4
- Black (photos and sizes): http://127.0.0.1:8001/products/demo-gallery-jacket?color=5
- Green (stock, no photos): http://127.0.0.1:8001/products/demo-gallery-jacket?color=6
- Unpriced: http://127.0.0.1:8001/products/demo-gallery-jacket?color=7&size=M

Demo owner: `demo@example.test` / `Demo-only-2026!`. Repeating `php artisan db:seed --env=demo --class=ProductUsabilityDemoSeeder` preserves existing fixture edits, prints current URLs and does not recreate a fixture photo that was moved. IDs above belong to this prepared demo database.

Start the demo (the verification server is stopped at handoff):

```powershell
Set-Location C:\laragon\www\leather-store
$env:PATH = 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64;' + $env:PATH
php artisan serve --env=demo --no-reload --host=127.0.0.1 --port=8001
```

For additional demo uploads, run in another terminal with the same PHP PATH:

```powershell
php artisan queue:work database --env=demo --queue=media --sleep=1 --tries=3 --timeout=60
```

The follow-up collections milestone has applied the pending migrations to the normal Laragon site. On another installation, run **`php artisan migrate --force`** in its normal environment. This earlier milestone adds only `product_colors.sort_order` (default 0); media placement uses existing Spatie custom properties and needs no media migration. Historical note: the follow-up collections milestone has now applied this migration to the real database. Production assets are already built; use **`npm run build`** after future Blade/CSS/JS changes. Do not run demo seeders against the real catalog.

### Verification and remaining browser checks

The final relevant isolated MySQL suite passed **29 tests / 490 assertions**. Production asset build and JS syntax checks passed. Pint passed for changed PHP files; `--dirty` is unavailable because this checkout has no Git repository. Tests use the guarded isolated database and generated files. Coverage includes public/preview color rules, direct links, default/order selection, apparel/numeric/custom sizes without merging, classification, ownership checks, invalid-form move prevention, completed and pending conversions across a move, preserved image identity/checksums/URLs, repeated saves, error-state retention, and the existing gallery/SKU/cart/preview tests. Demo HTTP checks verified default Black, hidden Brown, sold-out Grey, empty Green, size guide, removal of the old block, and served WebP thumbnails. The browser host remains unavailable: no claim is made that 390px/1440px layout, live keyboard behavior or network activity was visually checked.

1. At normal zoom, inspect the public demo at **390px and 1440px**: one gallery, readable buying panel, tappable swatches/sizes and no large blank mobile gallery. Black opens first; Brown is absent.
2. Select a non-first Black thumbnail, then change sizes: the photo/selected thumbnail must stay unchanged and Network must show no gallery requests. Try arrow keys, Home/End, Back/Forward, larger-image links and a no-JavaScript reload.
3. Visit Grey and Green direct links; check Sold out versus the honest empty-photo message. Open Size guide. Confirm the unclassified close-up is absent and the neutral detail appears once in Product details, outside every color gallery. Compare Unpriced publicly and in Preview; compare Brown publicly and in Preview.
4. In the demo edit page, inspect readiness and collapsed labels. Use Move shared photo for **Demo unclassified black close-up** into Black; verify alt text, thumbnail, larger original and responsive source after saving again. Reorder colors and verify that the first purchasable color follows the saved order.
5. With a saved color photo, add a new size and save, edit that variant and save again without refreshing. Both saves must say **Product saved**. Try another variant's SKU: the row must reveal the error, retain typed values and reject the duplicate. Correct it and save successfully.

Changed areas: product controller/presentation/photo helpers; product and color ordering relationship; product/gallery/catalog-card Blade views; gallery JS/CSS and generated assets; ProductForm, EditProduct, photo-description action and readiness view; photo-placement service; additive color-order migration; demo seeder; gallery/usability tests. Checkout/order workflow implementation was not changed.

## Customer order care milestone (2026-11-20)

Implemented private order recovery, transactional messages, cancellation requests, partial returns, physical receipt/disposition, and manual refund records in the existing storefront and Orders area. No packages were installed or upgraded. The real database, catalog, uploads, publication flags, credentials and checkout settings were not touched in this milestone. **Follow-up: the collections milestone has now applied this additive migration to the normal Laragon database.** This leaves real-store return requests disabled. Never run a demo seeder on the real database.

Verification: isolated MySQL **39 tests / 565 assertions passed**, including two-process approval, shipment/cancellation, receipt and refund races plus the original SKU/photo double-save regression. After the final preview/admin refinements, the focused SQLite suite passed **23 tests / 264 assertions**. Production assets and JS syntax passed. Real HTTP requests with cookies and CSRF completed lookup, cancellation, partial-return submission and duplicate submission in the demo. All demo emails were processed with log mail; no failed jobs remain. An old queued notification exposed a serialized-property compatibility issue; it was fixed, regression-tested and retried successfully. Desktop/mobile visual checks remain manual because the browser access host is unavailable.

### Open the prepared demo

The demo is already migrated and seeded. Start the server in PowerShell (it was stopped after verification):

```powershell
Set-Location C:\laragon\www\leather-store
$env:PATH = 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64;' + $env:PATH
php artisan serve --env=demo --no-reload --host=127.0.0.1 --port=8001
```

In a separate terminal with the same PHP PATH:

```powershell
php artisan queue:work database --env=demo --queue=default,media --sleep=1 --tries=3 --timeout=60
```

The existing expiry scheduler still uses `php artisan schedule:work --env=demo` in another terminal. No new scheduler is required for returns: eligibility is rechecked on submission. Restart existing workers after code changes. Do not share cached configuration between demo and real environments.

- Storefront: http://127.0.0.1:8001/shop
- Find my order: http://127.0.0.1:8001/find-order
- Owner login / orders: http://127.0.0.1:8001/admin/orders — `demo@example.test` / `Demo-only-2026!`.
- **DEMO-CARE-COD**: http://127.0.0.1:8001/admin/orders/2 — unpaid, cancellation request **#2** pending.
- **DEMO-CARE-TRANSFER**: http://127.0.0.1:8001/admin/orders/3 — test PNG proof awaiting review, no funds collected. Its initial demo reservation expires seven days after fixture creation.
- **DEMO-CARE-RETURN**: http://127.0.0.1:8001/admin/orders/4 — delivered, fictitiously collected COD, two Black / M and one Brown / L. Return request **#1** for one Black / M is pending.

All fixtures use checkout email **demo-buyer@example.test**. The clearly test-only return window is **14 days from recorded delivery**. `php artisan db:seed --env=demo --class=OrderCareDemoSeeder` prints current URLs and preserves existing fixture orders, requests, movements and decisions. It does not reset delivery clocks or reopen closed requests. On a fresh demo database, first run `php artisan migrate --env=demo --force`, then this seeder. Both demo seeders refuse other databases. IDs above belong to this prepared database; use printed URLs if recreated elsewhere.

### Customer journey and demo messages

Checkout redirects to the same private order page linked from confirmation mail. It shows saved jacket/color/size quantities, original PKR totals, shipping method, separate order/payment progress and a customer-visible timeline. Shipped COD stays unpaid until collection is recorded. A bank proof stays awaiting review until staff decide. Existing random 64-character access tokens are reused; an order reference or numeric ID alone cannot open an order.

Use **Find my order** with `DEMO-CARE-RETURN` and `demo-buyer@example.test`. Correct and incorrect details receive the same neutral response. Limits are six attempts per IP per minute and three per email per ten minutes. Matching lookups in the same ten-minute bucket share one notification event. The response never displays an order or its private URL.

To open a saved demo message, sign in as the demo owner, visit **http://127.0.0.1:8001/admin/order-messages/3**, then click **Open private order page**. This is the delivered fixture's original confirmation. Alternatively, open an order, expand a message card and choose **Open message preview**. New lookup messages appear there too. Run the worker to mark messages processed. Demo mail is forcibly routed to Laravel's `log` mailer even if another default is configured; messages also appear in the configured local Laravel log. Preview pages and logs contain private links and must stay private.

Emails cover confirmation, verified/rejected transfer review, shipment/status changes, request receipt, cancellation/return decisions, physical receipt and completed-refund records. Bodies are saved from order snapshots when the event occurs; later product edits cannot rewrite a receipt. Brand uses existing `StoreSettings`. **Commerce Settings > Customer contact email** supplies contact information; a blank address is omitted.

Each business event writes a uniquely keyed `order_events` outbox row in its transaction. Jobs dispatch after commit on database/default. Repeated business actions reuse the event; workers skip sent rows. A failure is visible on the order and in **Needs attention**. Correct the cause and use **Retry unsent email**, or `php artisan queue:failed --env=demo` and `php artisan queue:retry <uuid> --env=demo`. Pending events can also be requeued if dispatch was interrupted. Retrying mail never repeats fulfillment or inventory. As with SMTP generally, a crash after remote acceptance but before the sent marker commits can cause a retry delivery; event deduplication and stable Message-ID cannot promise provider-level exactly-once delivery. Real SMTP/provider delivery was not enabled or verified.

### Daily store-owner workflow

1. Open **Orders**, enable **Needs attention**, then open an order. The filter includes proofs awaiting review, pending cancellation/return decisions, approved returns awaiting receipt and failed messages. Existing search and status/payment filters remain available.
2. For a transfer, download the private proof and independently verify funds. **Record verified funds** records the original total as collected. **Reject transfer proof** requires a customer-visible explanation and offers a separate internal note. Rejection permits another proof while the reservation is open; a proof alone never means paid.
3. Use **Approve request** or **Reject request** beside a cancellation reason. Both require a customer explanation and allow a staff-only note. Approval reuses cancellation and releases reservations once. If shipping won the race, approval fails visibly; reject with the next step. Paid cancellation does not refund automatically. The ordinary Cancel header action is hidden while a cancellation request awaits a decision.
4. For a return, compare purchased/requested quantities, other pending/approved/received units, collected funds and prior refunds. Approval changes no stock. After all approved units in that request physically arrive, choose **Receive and classify returned units**. Enter resellable and damaged quantities for every line and an optional inspection note. Counts must sum to approved quantities. Only resellable units increase stock. This simple workflow records one complete receipt per request, not staggered receipts; customers can make separate requests for remaining eligible units.
5. After a refund is actually completed externally, use **Record completed manual refund** with its PKR amount and actual external reference. Currency, staff ID and timestamp are saved. Repeated references cannot create another refund, and cumulative refunds cannot exceed collected funds. Unpaid COD cancellation has no refundable funds. The app never initiates a transfer. Refund recording is available after cancellation or receipt of a return.

Expand **Order stock movements** on the same page for deltas, resulting balances, variant/staff/order IDs and time. Resellable receipts have reason `Resellable return #<request-id>`; damaged-only receipts have no positive movement. Existing product variant **Stock history** remains available. Actions provide immediate success or validation feedback and rebuild the affected infolist, so approval can be followed by receipt without refresh.

Real-store returns are disabled by default. Configure **Commerce Settings > Customer service policy**, choose your own 1–365 day window and contact email, then explicitly enable requests when your policy is ready. Eligibility starts at `delivered_at`; an approved request can be received after the window closes. Existing paid orders backfill collected amount from the previously verified total; delivered timestamps backfill only from recorded delivery history. Missing historical evidence does not invent a return deadline.

### Five-minute browser checklist

1. At desktop and phone widths, compare matching/incorrect **Find my order** responses. Open a demo message as staff, follow its private link, and check readable quantities, original totals and separate progress. Test Tab focus and form errors. Incognito access to the admin message preview must be denied.
2. Approve the pending cancellation on **DEMO-CARE-COD**. Check immediate success, cancelled/unpaid state, one reservation release and no refund. On a separate test order, ship before approving a pending cancellation and confirm that stale approval fails.
3. On **DEMO-CARE-TRANSFER**, open the test proof and reject with distinct customer/internal explanations. Only the customer explanation should appear on its private page, with resubmission available. Use only fictitious verification references in the demo.
4. Approve return **#1** on **DEMO-CARE-RETURN**, then receive **resellable 1 / damaged 0** without refreshing. Inspect the one-unit stock movement. Request the remaining Black / M from its private page, approve and receive **resellable 0 / damaged 1**; sellable stock must not increase.
5. Record a demo completed refund, for example **PKR 12900.00**, reference **DEMO-REFUND-1**. Inspect its audit and message. Try an excessive amount and repeat a submission: no excessive or duplicate refund should be created. Finally save an existing product twice to confirm the SKU/photo behavior.

### Changed areas and test command

New: `OrderCare`, `OrderMessages`, `OrderCareController`, `OrderCareActions`, `OrderRequest`, `OrderRequestLine`, `OrderEvent`, the additive `2026_11_20_082542_add_order_care_tables` migration, `OrderCareDemoSeeder`, lookup/mail templates and `OrderCareTest`. Extended: `Order`, `Checkout`, `Fulfillment`, `OrderNotification`, commerce settings/page, `CheckoutController`, Orders resource/view, routes, private order/store layout, stock history, CSS/generated assets, and the existing MySQL race test/helper. Product/SKU/media implementation was reused unchanged.

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'leather_store_variant_regression_test'
php artisan test --compact
Remove-Item Env:DB_CONNECTION
Remove-Item Env:DB_DATABASE
npm run build
```

Tests guard the isolated database before migrations. Coverage includes private/neutral lookup, immutable receipts, transaction rollback and after-commit jobs, legacy jobs, failed mail/retry, duplicate requests, shipment/cancellation races, return ownership/window/quantity limits, atomic multi-line receipts, damaged/resellable units, refund caps and concurrent staff actions. Pint passed on changed PHP files; `--dirty` is unavailable because this checkout has no Git repository. No real email, carrier, money transfer or real-store policy was activated.

## Commerce milestone: setup and operation

Verified on 2026-11-20: the complete isolated MySQL suite passed **25 tests / 399 assertions**, including real two-process stock/idempotency races and the photo/SKU double-save regression. The production asset build and JS syntax check passed. A cookie/CSRF-aware HTTP demo smoke test completed cart → checkout → COD confirmation and retry, returning the same unpaid order. It left one test COD order/reservation in the demo database. The main catalog's pre-existing product, variant, color, category, media, user and store-setting fingerprints were identical before/after the additive migration. Browser automation was unavailable (access host disconnected), so desktop/mobile visual and live keyboard/selection checks remain manual.

The storefront now has `/`, `/shop`, `/categories/{slug}`, `/products/{slug}`, `/cart`, `/checkout`, and private `/orders/{token}` confirmations. The draft Preview button remains available. Public visibility is shared across listings, direct product pages and cart additions: active product, active category, non-deleted product and active variant. Filters use submitted GET forms, preserve URLs/pagination and match color and size on the same variant. Filter/search URLs are noindex; product/category canonicals are stable. Catalogs with one product or no published products are supported.

### Merchant configuration and prices

`brick/money` 0.15.2 parses decimal strings exactly. Nullable integer `usd_price_minor` / `pkr_price_minor` columns on products and variants explicitly identify currency. `Pricing` resolves variant override then product base and formats cards, detail pages, cart, checkout and snapshots. Missing/nonpositive selling prices are not purchasable. “From” reflects varying or partially configured variant prices. Admin fields accept at most two decimal places without floats. Pakistan checkout always reviews and charges the explicit PKR selling prices, including after USD browsing; no exchange rate exists.

In **Admin > Products > Edit**, enter optional base prices and size-specific overrides. Existing unpriced products remain editable. In each saved variant, **Adjust stock** takes a signed quantity change and required reason (opening count 5 means +5). **Stock history** shows the most recent 100 movements, resulting balances, actor and order references. Existing catalog variants start with unconfigured on_hand, reserved 0, and available 0. No opening stock has been assigned to the owner's jackets.

In **Admin > Commerce Settings**, configure the Pakistan shipping name/rate in PKR, optional free-shipping threshold, merchandise tax percentage, enabled methods and bank instructions. Blank shipping or tax disables checkout. Explicit 0 is deliberately zero, distinct from missing. Tax is calculated on merchandise subtotal using integer basis points and half-up minor-unit rounding; shipping is separate and discounts currently zero. These are merchant-defined rules: no tax rate, delivery promise or bank details are invented. Bank transfer requires both enablement and instructions. COD must be enabled explicitly.

### Orders, inventory, access and queues

The additive migration creates `orders`, `order_lines`, `checkout_attempts`, `stock_movements`, `order_histories`, plus price columns and variant `on_hand`/`reserved`. `Inventory` is the only application stock writer. Available = on_hand - reserved; negative balances and reductions below reservations are rejected. Every mutation records deltas, resulting balances, reason, timestamp, actor/order reference.

Cart additions do not reserve. `Checkout` locks the idempotency row, variants in ID order, products/categories and checkout settings inside one retryable transaction. It verifies all lines and the reviewed PKR quote, then writes immutable item/amount/customer/payment-instruction snapshots, reservations and history. A failed line rolls everything back. Same-key/same-details retries return one order; materially different details are rejected. Session locking protects cart writes; an old successful checkout retry does not clear a new cart. Prices, variant ownership and quantity bounds are server-validated. Cart and customer order responses are private/no-store and noindex. Random 64-character order capability links are private; a sequential ID/order number is not sufficient for access.

Order flow: pending -> confirmed -> processing -> shipped -> delivered. Eligible orders can cancel before shipping; paid orders need a separate completed-refund record. Shipping deducts on_hand and releases reserved atomically. Cancellation releases reserved without deducting on_hand. Repeating the same transition is a no-op. Paid cancellations, manual refund records and partial returns are now supported by the order-care workflow above. Bank orders require verified funds before processing. COD is not paid by creation or shipping; collection can be recorded after delivery. Separate payment states: unpaid, proof_submitted, proof_rejected, paid; refunds are separately audited amounts.

**Admin > Orders** has number/customer search, state/payment filters, snapshot items/address/totals and history. Actions are Confirm, Process, Ship, Deliver, Cancel and Record verified funds. The private proof download/payment action requires the `verify-payments` gate; fulfillment requires `OrderPolicy::update`. `manage-commerce`, `verify-payments`, and `OrderPolicy` use `CommerceAccess`, following existing Filament panel access: the plain User is admitted only in local mode; outside local mode a `FilamentUser::canAccessPanel()` grant is required. No public registration/new role is introduced. Narrow these gates if separate staff roles are added later.

Bank proof accepts JPEG/PNG/PDF up to 5 MB on the private local disk. Upload is not payment verification. Admins must independently verify receipt and enter a reference. The new small `PaymentGateway` contract has COD and bank-transfer implementations; no previous contract was found in the repository. No external payment/carrier integration is used. Order notifications now use the event outbox above, dispatch after commit to database/default, use configured mail outside the demo, and force log mail in the demo. Logs contain private confirmation links and must remain private.

`orders:expire` runs every minute, rechecks the locked order, and releases overdue unpaid pending/confirmed bank reservations once. The expiry window is merchant-configurable (1–720 hours). Paid transfers and all COD orders are excluded. An uploaded proof alone does not stop expiry. Scheduler overlap prevention and retry-safe transitions protect repeated runs.

### Isolated demo and exact Laragon commands

A gitignored `.env.demo` and **leather_store_demo** database are prepared, migrated and seeded. The demo has its own session cookie, port 8001 and `demo` media disk (`storage/app/demo-media`), separate from real uploads. It contains a published test jacket, fictitious prices/stock, explicit test-only shipping/tax rules and no real bank details. `CommerceDemoSeeder` refuses every database except leather_store_demo and never wipes it.

Start the demo in PowerShell:

```powershell
Set-Location C:\laragon\www\leather-store
$env:PATH = 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64;' + $env:PATH
php artisan serve --env=demo --no-reload --host=127.0.0.1 --port=8001
```

Open **http://127.0.0.1:8001/shop** first. Demo admin is **http://127.0.0.1:8001/admin**, email `demo@example.test`, password `Demo-only-2026!` (demo database only). `--no-reload` passes the selected demo environment to the PHP server process. Do not share a config cache between live/demo environments, and do not override DB_DATABASE with the real catalog in demo terminals.

In separate terminals with the same PHP PATH, run one long-lived command per terminal:

```powershell
php artisan queue:work database --env=demo --queue=default,media --sleep=1 --tries=3 --timeout=60
php artisan schedule:work --env=demo
```

For the real Laragon store, omit `--env=demo`. Schema updates use only `php artisan migrate --force`. Never run migrate:fresh, db:wipe or destructive seeders against leather_store. Real checkout stays unavailable until the owner explicitly supplies prices, real stock and checkout configuration. The demo may be reseeded with `php artisan db:seed --env=demo --class=CommerceDemoSeeder`; existing demo orders/reservations are not reset.

Assets: `npm ci --ignore-scripts` on a fresh checkout, `npm run build` after asset changes; use `npm run dev` for development. The production build is prepared. `php artisan storage:link` creates missing public/demo media links. Existing photos keep their original disk/IDs and the media queue workflow below. Inspect failed jobs with `php artisan queue:failed`; correct the cause and retry the specific job. Restart workers after code changes with `php artisan queue:restart`.

### Tests and manual journey

`CommerceTest` covers pricing, matched color/size filters, visibility, session isolation, tampered prices/ownership, changed quotes, snapshots, duplicate checkout, all-or-nothing rollback, repeat cancellation/shipping, bank expiry retries, proof access and admin stock workflows. `InventoryConcurrencyTest` launches two independent PHP processes synchronized against isolated MySQL: one last-unit buyer wins; concurrent same-key submissions reserve once. SQLite skips only those MySQL races. Existing photo/SKU regressions remain in the suite.

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'leather_store_variant_regression_test'
php artisan test --compact
Remove-Item Env:DB_CONNECTION
Remove-Item Env:DB_DATABASE
```

Tests guard the database name before migrations and use fake queues/generated files. The test database is separate from the demo. Never select leather_store for tests. For manual checks: browse/filter at phone and desktop widths; select/add/update a variant and review the PKR checkout; fulfill a COD order without auto-paying it; upload/verify a test transfer or expire a separate unpaid order; inspect inventory history and the original draft Preview/double-save behavior. Browser automation availability and final test counts are recorded in the handoff.

## Product variant save verification

On 2026-11-20, the owner confirmed the SKU fix works in Chrome on Laragon/MySQL, including a second save without refreshing. The failure required an existing color gallery photo: its save hook rebuilt nested form schemas after inserting a variant, losing the temporary item's record binding before subsequent unique validation. `ProductForm` now reloads the variant relationship immediately after saving it. Keep that callback when modifying the nested repeater. Existing catalog rows were preserved.

## Product photo workflow

- Shared product photos belong to the product (`shared` collection). Color photos belong to the color (`gallery` collection), shared by every size; variants have no separate gallery.
- Upload and drag photos into the desired order, then click **Save changes**. The first color image remains its cover photo.
- Click **Edit photo alt text** beside either gallery. Each saved photo has a preview and its own description field. Describe useful visual information, for example “Black leather jacket, rear view showing waist buckle.” Save with **Save descriptions**. This dialog saves descriptions immediately; it does not save other unsaved product changes.
- Save a new product, color, or newly uploaded image first, then open its descriptions dialog. Reopen the dialog after adding/removing photos. Blank alt text is allowed for decorative images; meaningful product images should have descriptions (maximum 500 characters).
- Alt text is stored in each media row's `custom_properties.alt`, keyed by its immutable media UUID in the dialog. Reordering cannot swap descriptions. Other custom properties, original files, and existing order are preserved when editing descriptions. Existing images start with blank descriptions until edited.

## Responsive images and the local worker

Both collections register a queued `storefront` conversion: WebP quality 80, fit within 1600 × 1600 without cropping or enlarging small originals. Spatie generates a responsive WebP set and `srcset` from that conversion, choosing widths based on image dimensions and file size. Very small/compressible images may need only one size. The original JPEG, PNG, or WebP is retained unchanged. GD handles image encoding; no new PHP packages or external optimizer installation is needed.

Media jobs explicitly use the **database** queue connection and **media** queue, after database commit, regardless of the application's default queue. Upload/save does not execute conversions. With the default public disk, generated files live under `storage/app/public/<media-id>/conversions/` and `storage/app/public/<media-id>/responsive-images/`; the original remains in `storage/app/public/<media-id>/`. Keep this command running from the project directory in a Laragon terminal using PHP 8.4 with GD/WebP enabled:

```powershell
php artisan queue:work database --queue=media --sleep=1 --tries=3 --timeout=60
```

If your terminal resolves XAMPP PHP 8.2, use the installed Laragon executable instead:

```powershell
& 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe' artisan queue:work database --queue=media --sleep=1 --tries=3 --timeout=60
```

The existing jobs/failed_jobs migrations must be applied. Restart a running worker after deploying conversion changes (`php artisan queue:restart`). If configuration was cached, run `php artisan config:clear`. Public images require the existing storage link (`php artisan storage:link` if missing). Inspect failures with `php artisan queue:failed`; correct the cause and retry the specific failed job with `php artisan queue:retry <id>`.

### Process existing uploads

Run these commands once to enqueue missing storefront conversions for existing product and color photos, then let the worker process them:

```powershell
php artisan media-library:regenerate 'App\Models\Product' --only=storefront --only-missing --queue-all
php artisan media-library:regenerate 'App\Models\ProductColor' --only=storefront --only-missing --queue-all
```

To rebuild after changing conversion settings, or repair a missing responsive set when the base conversion already exists, omit `--only-missing`. Use `--ids=123` to target a specific **media ID**, not a product ID. These commands derive images from retained originals; they do not re-upload photos, change alt text, or reorder galleries. They were verified against test media; the existing catalog has not been bulk-regenerated by this change.

## Product detail page and private preview

Public route: `GET /products/{product:slug}`, named `products.show`. It uses the stored slug and `StoreSettings::brandName()`. Only active, non-deleted products in active categories are visible; otherwise the route returns 404, including for logged-in admins. The commerce milestone now supplies the home page and shared storefront navigation.

Open **Admin > Products > Classic Biker Jacket > Edit > Preview** first. The Preview action opens `GET /products/{product:slug}/preview` (`products.preview`) in a new tab using the same layout, without changing publication state. Save edits before previewing. The server requires an authenticated user with the admin panel's access and the product resource's edit permission. It follows the existing Filament rules: this application's plain User model has panel access only in `APP_ENV=local`; in other environments Filament requires a `FilamentUser` implementation granting panel access. Preview responses are private/no-store and noindex/nofollow/noarchive. Preview is never linked from public navigation. Deleted products still return 404.

The first purchasable color in saved display order is selected by default, falling back to the first color with active variants; authorized Preview can also show empty colors. `?color=<color-id>&size=<size-label>` provides shareable selection and ordinary links work without JavaScript. With JavaScript, selections update locally without fetching a gallery. A size is retained on color change only if that size is offered in the new color. Only active variants appear; the commerce milestone now adds resolved prices, real available quantities and server-validated cart additions (hidden in preview). Each color uses its own ordered gallery; a color without images shows an explicit empty state. Explicitly neutral details appear once in Product details below the product; size guides use the Size guide control; unclassified shared images stay hidden. Browser Back/Forward restores selections.

The Blade layout outputs actual media alt text, dimensions, conversion URLs, responsive `srcset` and `sizes`. The main image is prioritized; other images are lazy-loaded. Pending conversions now use originals for the main image and thumbnails; queued conversions replace these with responsive WebP when ready (updated 2026-11-21). Inactive color templates are inert and do not request their images. Selecting sizes leaves the gallery DOM intact. On the existing local disk, dimensions are read from image headers, without generating conversions in the request. Titles, descriptions, canonical URLs and social preview metadata use catalog facts; all color URLs canonicalize to the base product URL. No offers, ratings or reviews are fabricated.

### Windows / Laragon commands

From PowerShell, use the actual Laragon PHP rather than XAMPP PHP 8.2:

```powershell
Set-Location C:\laragon\www\leather-store
$env:PATH = 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64;' + $env:PATH
npm ci --ignore-scripts
npm run build
php artisan config:clear
```

Dependencies and a production build have been prepared in this workspace. Run `npm ci` on a fresh checkout; rerun `npm run build` after changing Blade/CSS/JS. For asset development use `npm run dev` instead. Laragon can serve `http://leather-store.test`; alternatively run `php artisan serve --host=127.0.0.1 --port=8000` and open `http://127.0.0.1:8000/admin/products`. Set `APP_URL` to the host you use so media URLs match it. The existing public storage link is required; run `php artisan storage:link` only if missing.

In a separate terminal, run the database/media worker documented above. Use the existing-upload regeneration commands above once if originals do not yet have a `storefront` conversion. Check the worker output and `php artisan queue:failed`; verify WebP files appear under each media ID's conversions and responsive-images folders. Existing upload data and publication flags have not been changed by this milestone.

### Manual acceptance checks

1. Open Classic Biker Jacket's **Preview** while signed in. Confirm the private preview banner; save/reopen as needed, without enabling Active. A guest preview must be denied and a draft public URL must be 404.
2. At a narrow mobile viewport and desktop width, check the single/two-column layout, readable descriptions and keyboard focus on colors, sizes, thumbnails and the larger-image link.
3. Switch Black/Brown. Confirm the color label, offered sizes, and photos update. A color without photos must not borrow another color's gallery. Select a size, switch colors, and verify it clears if not offered there.
4. Select different sizes while watching the browser Network panel: no gallery fetch or image reload should occur. Try thumbnails, larger-image links, and browser Back/Forward. Disable JavaScript and verify color/size links still render the requested selection.
5. Verify saved alt text and photo order, pending-conversion fallback, then responsive WebP sources after the worker finishes. Confirm the canonical URL has no color/size query and inactive variants never appear.

## Regression tests

`ProductPageTest` covers public visibility, draft preview authorization and privacy, ordered media/alt text, pending and completed conversions, inactive variants, empty color galleries, metadata and the Filament Preview action. `ProductPhotosTest` uses fake storage and isolated media to verify alt editing, collection ownership, ordering, repeated saves, original checksums, queued conversion settings, regeneration, decoded WebP files, and responsive srcsets. `ProductVariantSaveTest` preserves the gallery-dependent SKU regression. Tests refuse database refresh unless the database is in-memory SQLite or the explicitly isolated MySQL database `leather_store_variant_regression_test`.

```powershell
php artisan test --compact tests/Feature/ProductPageTest.php tests/Feature/ProductPhotosTest.php tests/Feature/ProductVariantSaveTest.php
```

The default PHPUnit configuration uses in-memory SQLite. To use MySQL, create the separate database `leather_store_variant_regression_test` first and run in a dedicated terminal:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'leather_store_variant_regression_test'
php artisan test --compact tests/Feature/ProductPageTest.php tests/Feature/ProductPhotosTest.php tests/Feature/ProductVariantSaveTest.php
Remove-Item Env:DB_CONNECTION
Remove-Item Env:DB_DATABASE
```

Never run these tests with the catalog database selected. Tests execute conversion job handlers against generated test images; production processing requires the worker above.

Milestone verification on 2026-11-20: all 9 relevant feature tests (241 assertions) passed against isolated MySQL and in-memory SQLite databases; the production `npm run build` and JavaScript syntax check passed. Browser automation reported that its access host was unavailable, so mobile visual checks and live browser interaction/network checks remain manual. The earlier owner-confirmed Chrome/Laragon SKU verification above remains recorded separately.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).



### Make one jacket size available to buy

Go to **Admin ? Products ? Classic Biker Jacket ? Edit ? Manage stock** (beside Manage prices), at `/admin/products/{product-id}/edit`. Find **Black / M** and choose **Set opening stock** if On hand says Not configured; enter the total counted quantity. For later deliveries use **Receive stock** (5 means five more arrived). **Correct physical count** replaces the total and requires a reason; zero is an explicit count and blank rows are skipped. Review the SKU/current/change/result preview and confirm once. Several sizes can be entered together. Stock saves immediately, without saving other pending product edits.

Use **Manage prices ? PKR** to set the size's price, or save a positive **product PKR price** and leave the variant PKR override blank to inherit it. The readiness actions **Enter stock** and **Set PKR price** identify the affected SKU. USD alone does not enable PKR checkout. Save new sizes before entering stock. Copying sizes to Brown does not copy stock.

Customers can select active sizes with available units and an effective checkout price. When the last available unit is reserved, the size becomes crossed out and disabled as Sold out, even though the physical on-hand balance may still be positive. An old unavailable-size link asks customers to choose another size without substituting a SKU.

### Take a WhatsApp or phone order

Go to **Admin ? Orders ? Create assisted sale** (opens **Assisted Sales**, at `/admin/assisted-sales?create=1`). Choose WhatsApp or Phone, optionally enter a customer label/contact/internal note, search by product name or partial/exact SKU, and enter quantities. Repeated SKUs combine into one quantity. The preview separates PKR merchandise subtotal, delivery and tax. Save the draft; it is separate from confirmed orders.

In **Assisted Sales**, choose **Review and issue link**, review the basket, and issue the private link. Then open **Copy checkout link / message** and copy the link or suggested message. Nothing is sent automatically. Links default to 72 hours; set `ASSISTED_SALE_HOURS` in the environment to change the validity of newly issued links. The list and copy dialog show expiry. Use **Edit and replace link** to change a shared basket or renew its link; issuing the replacement invalidates the old link immediately. **Revoke link** stops checkout without issuing another. **Duplicate as new draft** copies SKU quantities, refreshes prices/availability, and starts unshared.

The customer opens the private selection, clicks **Continue to checkout**, enters their delivery/contact details and chooses COD or manual transfer through the usual Pakistan checkout. Stock is reserved only when **Place order in PKR** succeeds, never when saving, sharing, opening, refreshing or previewing a link. If prices changed, the page shows the shared and current merchandise subtotals; the customer must explicitly accept the revised prices and current total. A later change requires another review. Insufficient stock identifies the SKU and requires the owner to update the selection and issue a replacement link. Personal cart contents are never changed by assisted checkout.

Find the converted order through **Assisted Sales ? Open resulting order** or the normal **Orders** list, which shows acquisition source. COD starts unpaid as usual. Continue shipping, payment review, cancellation, returns and refunds in Orders. A used sales link does not reveal the customer's order history, address or payment proof.

Local setup (PowerShell, from the project folder): apply only the new migration with `& C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe artisan migrate --path=database/migrations/2026_11_21_165845_create_assisted_sales_tables.php --no-interaction`. This feature does not need an asset build. Continue using the existing queue worker for normal order-confirmation notifications. Localhost links open only on the device running the demo; a real customer needs a publicly reachable store URL, with the correct `APP_URL`.

Manual demo: create a WhatsApp draft for **Black / M ? 2**, issue and copy its link, open it with Brown / L already in your personal cart, continue to checkout, enter delivery details and place a COD order. The draft should become Converted, the order should contain only Black / M ? 2 with payment Unpaid, two units should be reserved once, and Brown / L should remain in the cart.

### Ship today's orders

1. Open **Admin > Orders navigation group > Couriers > Create**. Enter the courier name, optional service, and optionally an HTTPS tracking template such as `https://courier.example/track?number={tracking}`. Deactivation removes a courier from new selections without changing historical shipments. No booking API or credentials are used.
2. Open **Admin > Orders navigation group > Dispatch > Ready to pack** (`/admin/dispatch`). The order-date/courier filters and summary figures link to actionable rows. Blocked bank orders explain that funds need verification; open the order reference to review its proof. Pending cancellation requests must be reviewed first. Select eligible orders and choose **Print combined picking list** for SKU totals with an order breakdown. Printing changes nothing.
3. On each order choose **Review and mark packed**, enter the expected full quantities, and confirm. Use **Packing slip** for individual parcels. Packing keeps stock reserved; it does not deduct stock. This release uses one full parcel per order.
4. Open **Packed**, select parcels, and choose **Review courier handover**. Correct every row, select an active courier and enter tracking numbers as text. Check the exact collectible amount: paid bank orders collect zero; COD uses the outstanding customer balance. Confirm once to hand over and print the courier-grouped manifest. The existing shipment transition is the sole stock deduction/reservation release. Reprinting does not resend shipment email.
5. In **With courier**, record delivery, a delivery issue, reattempt, or **Returning to store**. Delivery alone does not mark COD collected. After physical return choose **Received at store** and classify every line as resellable or damaged. Only resellable units are restored, once. Delivered customer returns remain in the existing Orders return workflow; do not process a parcel through both. An unpaid COD return has no customer refund. Paid courier returns can use the existing completed-manual-refund action after receipt.

Older shipped/delivered orders are available through **All / older orders > Attach legacy courier details**. This adds tracking without shipping again, and carries forward only previously verified COD funds. Customer order links show tracking and delivery updates; internal notes and settlement figures stay private. Normal Orders details show the same dispatch stage.

### Match a courier payout

Open **Admin > Orders navigation group > Dispatch > Delivered / record collection > Confirm COD collection** and enter evidence that the courier received the money. Then open **Admin > Orders navigation group > Courier settlements > Enter or import statement** (`/admin/courier-settlements`). Enter the courier, external statement reference, actual payout date, bank/reference note and manual rows, or upload a local CSV. Use **Download CSV template**; the exact format is:

```csv
courier,statement_reference,tracking_number,gross_cod,fee_deduction,net_remitted
Demo Manual Courier,DEMO-COURIER-PAYOUT-250,00090004,18500.00,250.00,18000.00
```

One courier and reference per statement; maximum 200 rows / 1 MB. Amounts are decimal PKR with at most two decimal places. Keep tracking cells as text in spreadsheet software so leading zeros survive export. Unknown/ambiguous tracking, duplicate rows, previously posted parcels and unconfirmed collection evidence block a new posting. Review notes should be entered before **Preview matching and amounts**. Amount differences require the explicit review checkbox and note; posting preserves the unresolved difference. Repeated uploads with the same reference and contents do not post twice.

Worked example: confirmed collection **PKR 18,500**, courier fee **PKR 250**, net remitted **PKR 18,250** reconciles to zero. Net **PKR 18,000** leaves **PKR 250 unresolved**. Use **Unresolved differences only > Statement and adjustments** to inspect the original entry. After the courier actually pays the missing amount, choose **Record adjustment**, use a new external reference, and enter gross change **0**, fee change **0**, net change **250.00**, plus the reason. The original PKR 18,000 entry stays visible. A negative delta corrects an overstatement. No action initiates a bank transfer, edits an order total, changes stock, or treats a fee as a customer refund.

Summary definitions: confirmed COD awaiting remittance is verified collection minus recorded courier fees and net remittances, floored at zero per parcel. Expected COD still in transit is excluded. Unresolved posted differences use the larger absolute gross-vs-confirmed or net discrepancy per parcel; excess payments are shown as negative net differences. Dispatch date filters use order dates; settlement filters use payout dates, and links from Dispatch retain their order-date scope.

Setup on the normal Laragon database (PowerShell from the project folder):

```powershell
& C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe artisan migrate --path=database/migrations/2026_11_21_171701_create_dispatch_and_settlement_tables.php --no-interaction
```

The isolated `leather_store_demo` database has already received this migration and **DispatchDemoSeeder**. Start with **Dispatch > Packed > DEMO-DISPATCH-PREPAID**. Other reusable fixtures are **DEMO-DISPATCH-COD** (confirmed collection awaiting remittance), **DEMO-DISPATCH-RTO** (awaiting physical receipt), and **DEMO-COURIER-PAYOUT-250** (PKR 250 shortfall). If the demo server is not running, start `& C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe artisan serve --env=demo --host=127.0.0.1 --port=8001`, then use the existing demo login. Re-running `artisan db:seed --env=demo --class=DispatchDemoSeeder --no-interaction` preserves existing examples. The seeder refuses normal databases and non-log demo mail. Existing queue workers handle notifications; demo messages stay in local logs. No asset build is needed. Carrier integrations, partial shipments, bank transactions and settlement of unconfirmed/prepaid parcel collections are outside this manual COD release.


## Order jackets from a supplier and receive a delivery

Open **Purchasing > Purchasing overview** at `/admin/purchase-orders` (isolated demo: http://127.0.0.1:8001/admin/purchase-orders). Review **DEMO-PO-JACKETS-001**. Its Black M line has 2 accepted jackets and 1 damaged jacket with replacement expected: 3 remain incoming. At `/admin/replenishment`, 2 available + 3 incoming against target 12, pack 2, minimum 4 suggests 8 units. The fixture is repeatable and does not reset subsequent demo edits.

1. Add a supplier at `/admin/suppliers`; open its workspace for contact details, supplied SKUs, quotes, purchases and receipts. Archive instead of deleting. Map existing store SKUs at `/admin/supplier-sources`, set preferred supplier, pack/minimum and optional quote. Quotes, agreed order costs and receipt costs are separate; none changes retail prices.
2. Create a purchase draft. Choose the supplier/product and tab through the color-by-size grid; add the grid, then review line quantities and PKR costs. Save incomplete costs if needed. Review and issue explicitly once costs are complete. Drafts do not count as incoming. Issue counts only unresolved quantities as incoming, adds no sellable stock and sends nothing externally. Use Supplier copy to print agreed items without internal notes; freight is separate. Duplicate creates a fresh draft with refreshed quotes and no receipt history.
3. On an issued order choose **Receive delivery**, optionally fill remaining quantities, and adjust the carton. Classify damaged units as replacement expected (still incoming) or shortfall accepted (no longer incoming). Missing units remain outstanding. Preview, then post once. Only accepted units increase sellable stock; reservations remain intact. **Products > Edit > Manage stock > Open purchase orders for this product** leads to this same receipt workflow. Independent opening stock and physical counts remain available.
4. Open **Receipt history** (`/admin/purchase-receipts`). To fix a mistake, select the original receipt and **Correct receipt**, enter signed quantity changes and a reason, preview, and post. Originals and linked stock movements are retained. A negative accepted adjustment cannot take stock below reservations. If another staff member posts first, inputs remain but the stale receipt is rejected: note your quantities, reopen the latest order and review before submitting. Use **Close remaining shortfall** with a reason when no more units are expected.
5. Set per-size thresholds using **Products > Edit > Reorder settings**; select exceptions to keep using the preserve-configured option, or select only sizes to change. Blank means unconfigured; zero is intentional. Reorder needed uses available stock after reservations plus issued outstanding quantities. Preview selected suggestions grouped by preferred supplier before creating drafts. Existing draft references are shown to avoid repeat buying. Overview links open awaiting, overdue and recent receipt lists; Export open lines CSV follows supplier/product and overdue filters.

The purchasing migration and example were applied **only to the isolated demo**, with logged mail. No asset build is needed (no public storefront assets changed). Browser acceptance remains for the owner. From PowerShell in `C:/laragon/www/leather-store`, these repeatable demo commands are:

```powershell
& 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe' artisan migrate --env=demo --path=database/migrations/2026_11_21_174342_create_purchasing_tables.php --no-interaction
& 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe' artisan db:seed --env=demo --class=PurchasingDemoSeeder --no-interaction
```

Update: this purchasing migration is now applied to normal `leather_store` as well. For another installation, apply this additive migration without demo fixtures:

```powershell
& 'C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe' artisan migrate --path=database/migrations/2026_11_21_174342_create_purchasing_tables.php --no-interaction
```

Focused verification: `php artisan test --compact tests/Feature/PurchasingTest.php` covers idempotent retry and competing stale submissions, partial/damaged balances, reserved-stock correction rollback, and replenishment math/draft retry. It uses SQLite memory; it is not a simultaneous MySQL-process concurrency test.


## Create and share a jacket promotion

Open **Marketing > Promotions** (/admin/promotions). Create a promotion, keep the default 10% or choose fixed PKR / free standard shipping, select eligibility and optional minimums/cap, then add an example basket. **Preview current rules and basket** explains eligible/excluded lines and uses the checkout calculator without stock, orders or uses. Example SKUs and quantities are saved for next time. Set Active and save to publish; a future start schedules it. Dates use `STORE_TIMEZONE` (default Asia/Karachi). Pause or archive from the same form. **Duplicate as draft** copies rules and the example basket, clears dates, generates a new code, and resets uses.

After publishing, choose **Copy campaign link**. It links to a published collection or Shop; copying uses the browser clipboard. Opening it remembers the code but adds no items and consumes no use. The shopper can Apply or Remove in the PKR cart. Invalid replacement codes preserve the previous code and basket. USD remains browsing-only; PKR offers do not imply an exchange rate.

Qualification uses eligible merchandise before discount and eligible units, excluding shipping. Overlapping collections count each line once. At most one offer applies: choose the greatest actual merchandise-plus-shipping saving, then the lowest promotion ID on a tie. A code never displaces a better automatic offer. Existing shipping thresholds and tax use the original merchandise subtotal; the promotion reduces merchandise or the existing standard shipping charge separately. Fixed discounts cannot exceed eligible merchandise. Final submission rechecks stock, prices, dates and remaining uses under transaction locks. Changed totals or offer terms return the customer to a fresh review, preserving their input and basket; the same behavior works without JavaScript.

A use is counted only when the order commits, once per checkout key. Cancellation/expiry does not restore it. Orders, confirmation messages and admin details retain the campaign rules and allocations. Line allocation uses largest remainders with SKU ID as the tie-break; remainder paisa within a line goes to its first units. A partial return uses those saved unit amounts, then the next units for later returns, without repricing kept items. Promotional refunds cannot exceed received units' net merchandise plus allocated original tax, previous refunds or actual collected funds. Paid shipping is included only for a complete return/cancellation; free shipping cannot be refunded. Historical non-promotion refunds retain their existing rules.

Choose **Results** on a campaign for orders using the offer, eligible units/value, discounts, cancelled/expired orders and separately recorded refunds. Filter by store-timezone order date, open an order, or export the same date range. This is offer usage, not proof of WhatsApp attribution. CSV excludes contact information, prefixes codes as text to preserve leading zeros, escapes formula-like values and reads in batches of 200.

Isolated demo: http://127.0.0.1:8001/collections/demo-winter-jackets?offer=WINTER10 . Codes: **WINTER10** (10%, eligible minimum PKR 15,000, cap PKR 2,000), **JACKET500**, **SHIPFREE**, and expired **WINTEROLD**. Winter Jackets has a saved mixed preview: `DEMO-OFFER-BLACK-M` x 3 plus excluded `DEMO-OFFER-BELT` x 1. Results includes one fictitious ordinary-checkout order. SHIPFREE saves only where the current standard delivery quote is nonzero. Re-running the demo seeder preserves subsequent edits and does not duplicate its order.

Update: this promotions migration is now also applied to normal `leather_store`, without seeding or changing its catalog. For another installation, use PowerShell from `C:/laragon/www/leather-store`:

```powershell
& 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe' artisan migrate --path=database/migrations/2026_11_21_180643_create_promotions_and_order_allocations.php --no-interaction
```

To repeat the isolated demo setup, use the same migration command with `--env=demo`, then:

```powershell
& 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe' artisan db:seed --env=demo --class=PromotionDemoSeeder --no-interaction
```

Verification is limited to money/redemption checks: `PromotionTest` covers cap/allocation on a mixed basket, expiry/review/retry, and paid-unit partial refunds; `PromotionConcurrencyTest` runs real competing processes only against `leather_store_variant_regression_test`. Measured: resolving 24 basket lines takes 2 promotion queries (candidate offers and batched collection membership), excluding the existing cart/settings reads. Public cards perform no promotion lookups; one page-level terms summary avoids card-by-card calculations. No new public JavaScript, dependencies or asset build is required. Visual browser acceptance remains with the owner.


## Normal-store upgrade and storefront controls

**Completed:** normal http://127.0.0.1:8000 runs environment `local`, MySQL `leather_store`. Its pending Purchasing (`2026_11_21_174342`) and Promotions (`2026_11_21_180643`) migrations are now applied. Missing tables/columns caused the homepage and Purchasing errors. The separate demo at port 8001 uses `leather_store_demo`; its remaining additive Assisted Sales migration (`2026_11_21_165845`) is now applied too. No demo seeding or catalog/order edits were performed on normal data.

After a future demo-only milestone, review migrations in the **normal** context first. Read the pending migrations' `up()` methods, confirm the effective database, then apply reviewed additive changes. Never use `migrate:fresh`, reset migration history or run demo seeders against the normal store. These upgrade commands are complete for this installation; running them again reports no pending changes:

```powershell
& 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe' artisan migrate:status --no-interaction
& 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe' artisan migrate --no-interaction
```

- **Rename the store:** Admin > Store Settings > **Store name** > Save (/admin/store-settings). This controls header, footer and the brand portion of titles/social metadata. The value is reused within a request and cleared when saved; the next request sees the new name.
- **Change the campaign headline/image:** Admin > Homepage (/admin/homepage) > **Hero headline**, supporting text, draft hero image and alt text. Save draft > Preview saved draft > Publish homepage. The separate **Change store name** shortcut opens Store Settings without discarding hero edits.
- **Choose discovery/navigation:** Homepage > Primary button destination and Featured collections in display order. The public menu uses up to four of those published, nonempty collections; if none qualify, it uses up to four active categories containing public products. There is no separate menu editor. Shop, Cart and Find my order remain available. Edit collection covers, product order and featured colors in Admin > Collections; category names/publication in Categories. Nothing in this refresh overwrites those settings.

The mobile menu, gallery/size choices, explanations and copy controls operate locally. Continue shopping retains the last shop/collection URL, including filters. Product interactions are loaded only on product pages. Public CSS scans only storefront/pagination templates; it no longer includes compiled admin templates. System fonts remove the font-download requirement. Production assets were built with `npm run build`.

Verification: the actual normal homepage returned HTTP 200; authenticated read-only Laravel requests in the normal context rendered all Purchasing destinations and Promotions without login redirects. Isolated tests cover store name versus private/published hero, empty routes/navigation, featured Brown selection, product selection rules and checkout retry. Browser visual acceptance, device coverage and mobile latency were not measured. Check phone/tablet/desktop widths and zoom, Menu/Escape/focus, hero crops and long names, Brown/size/photo selection, then cart/code errors and checkout field errors.


## Storefront discovery

Shop and collections share database filtering for category, color, size, stock after reservations, and effective currency price. Choices within a group are OR; groups must match the same variant. Collection order remains the default without a search. Exact public SKUs identify the color/size and report unavailable stock honestly. Filters use GET, work without JavaScript, and submit together; changing currency clears the price bounds. Public cards use only matching variants and existing responsive photos. Admin > Products manages the choices; Admin > Collections manages membership, order and featured colors.

Example: http://127.0.0.1:8001/shop?q=Demo%20Discovery&color%5B%5D=Brown&size%5B%5D=M&in_stock=1&currency=PKR shows one Brown/M demo jacket at PKR 12000.00 with an honest missing-photo placeholder. The same Black/M stock filter returns zero. Search http://127.0.0.1:8001/shop?q=DEMO-DISCOVERY-BLACK-M to see the sold-out SKU. Collection: http://127.0.0.1:8001/collections/demo-discovery.

Focused verification: same-variant stock/prices/reservations, currency changes, public search/relevance, collection scope/order, stable pagination and featured photos pass. Measured in isolated SQLite HTTP tests: **13 database queries for both 1 card and 12 cards**, including navigation and the page-level promotion summary. Two redundant reads were removed during implementation; this is a query-count measurement, not a mobile latency benchmark. Demo MySQL HTTP checks confirm Brown/M=1 card, Black/M/in-stock=0, exact Black/M SKU=1 unavailable card. Visual acceptance remains with the owner.

No migration or dependency was added. Normal (8000) and demo (8001) migrations are current; only the guarded DiscoveryDemoSeeder ran against leather_store_demo. Assets are built and the demo server is running. Its previous launcher without --no-reload fell back to normal-store environment values; it has been restarted correctly. If restarting later, retain --no-reload:

```powershell
& 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe' artisan serve --env=demo --host=127.0.0.1 --port=8001 --no-reload --no-interaction
```


## Notify customers when a size returns

On a sold-out product color, open **Notify me when available**, choose the exact size and enter an email. The separate alert selector never enables Add to cart. A private email link opens a review page; press **Confirm alert** to authorize one notification or **Cancel this alert** to cancel. Merely opening a link changes nothing. Unconfirmed links expire after seven days; repeat requests are neutral and pending confirmation emails are limited to one per 15 minutes per address/variant. If stock returns during form entry, submission links straight to that selection. These are transactional size alerts, not marketing or reservations.

Owner: **Admin > Purchasing > Restock requests** (/admin/restock-requests). Sort confirmed waiting counts or oldest request; open the product's existing stock controls or Replenishment. Addresses are not shown in this overview. Demand does not change reorder calculations or create purchase orders. Manual receipts, accepted purchase receipts, resellable returns and reservation releases all use Inventory::change; increased available stock queues only the changed permanent variant ID after commit. Damaged-only receipts do not change sellable stock. Jobs recheck public visibility, active status, PKR pricing and unreserved stock. If stock disappears, the confirmed request remains waiting.

Demo: http://127.0.0.1:8001/products/demo-restock-jacket?color=26&size=M&currency=PKR. Black/M is sold out; Brown/M has five units. One fictional confirmed Black/M request is already waiting, and restock-demo@example.test has a captured confirmation email. Read **storage/logs/laravel.log**, searching for **confirm your size alert**; open its private link to try confirmation/cancellation. Receive Black/M stock through Admin > Products > Demo Restock Jacket > Product colors > Black > Sizes > Manage stock to capture the availability email. The demo deliberately has no borrowed color photos. Only isolated demo records were seeded; normal catalog/uploads were preserved.

Deployment: the additive restock_subscriptions migration is applied to **both demo 8001 (leather_store_demo) and normal 8000 (leather_store)**. Assets are built. Both environments currently use logged mail; demo notifications are forced to the log transport even if its mail setting changes. No migration command remains. A dedicated demo restock worker is running (worker output: storage/logs/restock-demo-worker.log). Start a normal-store restock worker in a separate Laragon PowerShell terminal:

```powershell
& 'C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe' artisan queue:work --queue=restock --sleep=2 --tries=3 --timeout=60 --no-interaction
```

For the demo after a restart, use the same command with `--env=demo`. A worker consuming only the default queue will not consume restock jobs. Configure the normal mail transport when ready for actual customer delivery; the current local log transport does not deliver to inboxes.

Focused verification: 6 tests / 77 assertions cover exact variants, confirmation/cancellation POST safety, repeat submission/job retry, stock disappearing, reservation release after commit/rollback, failed transport handoff, pricing/publication guards and permission-controlled aggregated demand. Public product rendering performs **zero subscriber-table queries**. Product-only JavaScript grew by about 0.7 kB before gzip; no new library or public polling was added. Visual browser acceptance remains with the owner. Database row locks, completed timestamps and stable message IDs prevent ordinary retry duplicates; an SMTP acceptance followed by a process crash before the database commit remains a mail-transport ambiguity, as with existing order notifications.


### Admin redesign - Section 1 (isolated demo)

Open http://127.0.0.1:8001/admin/products/14/edit (Catalog > Products > Classic Biker Jacket). This is a separate fixture, not the normal catalog jacket. Black/M has opening stock; S has never had stock entered; L is sold out; XXL needs a PKR price. The guarded AdminEditorDemoSeeder only writes to leather_store_demo with the demo media disk and does not reset existing fixture edits.

The admin uses the available width, native collapsible navigation, global search, user menu and existing amber primary actions. Orders & customers retains dispatch, settlements and the existing order URLs. Analytics navigation becomes populated in Section 4; the personal PKR/USD/Both top-bar preference is scheduled for Section 3. Both independent prices remain editable and are never converted by this redesign.

Product editing: name and Active/Draft control appear above General, Pricing, Colors & sizes, Photos, SEO & sharing, and Size guide tabs. Native tabs keep form state in place. Readiness is a saved snapshot with a grouped, expandable checklist. Open Colors & sizes > Black > M for effective prices, on-hand/reserved/available stock, status, Manage price and Manage stock. Color galleries are collapsed within their color and linked from Photos, avoiding duplicate relationship forms. Sizes continue to share color photos. Product tools contains Move shared photo and reorder settings; deletion is under More.

Save changes / Cancel uses Filament's sticky edit actions. Pending-change status and navigation warnings use Filament's saved-data baseline. Stock and price dialogs save on their own confirmation; fields in the main form are staged. Preview shows saved data. Creation suggests a slug; custom slugs and existing published URLs survive name changes. Existing explicit slug changes retain the redirect workflow.

Checks: focused PHP tests cover slug preservation, independent USD preservation during PKR edits, validation retaining staged values, readiness, photo placement, size additions and the SKU/photo double-save regression. The authenticated demo editor returns HTTP 200. Desktop/phone tab focus, sticky-bar clearance and visual acceptance still need browser checks; the connected browser host was unavailable. No Section 1 migration or normal-store command is required.

Try: (1) find Black/M and open Manage stock; (2) compare S, L and XXL status labels; (3) change a description, switch tabs, then Save changes or Cancel. Approve Section 1 before proceeding to inventory cleanup in Section 2.

Section 1 visual refinement: fixed malformed tab attributes that exposed JavaScript as text and displayed every panel together. A DOM regression now checks that the editor has one valid tab container holding six panels. Added native tab icons, grouped Price & stock shortcuts, quieter readiness messaging, and a product workspace that uses the available width beside navigation. Existing data and workflows remain unchanged.

Further Section 1 polish: product identity and readiness sit side by side on large desktops and stack on phones. Stronger headings, 44px inputs, clearer active tabs, currency prefixes, and borderless inner SEO/size-guide sections reduce visual clutter. Focused editor/tab/price-preservation and SKU/photo tests pass; browser visual acceptance remains outstanding.

Homepage UI refresh: forest-green/ivory hero with a warm-gold shopping action; existing category shortcuts and Latest arrivals; compact fixed-column product cards with no name-to-price spacer; customer-care links above the footer. Published hero/copy and catalog records are preserved. No migration, external font, new image or JavaScript dependency. Demo: http://127.0.0.1:8001/. Browser visual acceptance remains outstanding.


### Focused shopper release - Pakistan orders and international inquiries

Choose Delivering to Pakistan (PKR) or International (USD inquiry) in the storefront market bar. Product-page market links preserve the current color/size. Once chosen, the delivery market controls card, collection and product prices; a currency-only link cannot override it. Existing independently entered prices and variant overrides remain intact. International cards open the product selection instead of Pakistan Quick Add. Pakistan checkout accepts only PK delivery and PKR payment; an international-market session is redirected to its inquiry before checkout.

International: choose an available exact size, set quantity, and Add to inquiry. Add another jacket, enter the delivery country, and Update inquiry & shipping estimate. The WhatsApp action contains the jacket/color/size/SKU, quantity, independent USD price, destination, merchandise and applicable shipping estimate. No order, payment or inventory reservation is created. Quantity zero removes a line; unavailable/deleted sizes block sending until corrected. Missing WhatsApp configuration provides a copyable message and Contact us. Shipping and prices are estimates until confirmed by the owner.

Owner path: Admin > Settings > Commerce settings > International inquiry shipping (USD). Choose Included, Charged separately, or Request a shipping quote. Separate destination rates use country names matched without case sensitivity and an explicit Order or Jacket basis. An order fee applies once across all items; included shipping adds zero; unmatched or missing destinations show Request a shipping quote. No default USD 60 fee, conversion, or live rate service is used. The normal store defaults safely to quote until you configure its terms yourself.

Admin product price view: top bar > Price view > PKR / USD / Both. This is a browser-local preference keyed to the signed-in admin, not a conversion or data edit. It only hides field wrappers: pending values remain in the same form and are saved together. A validation error reveals Both so a hidden invalid value is reachable. Different browsers maintain their own viewing preferences.

Existing ordered color galleries, size guide and larger-image controls remain. Size changes do not replace the gallery or request images; color changes use the matching existing gallery. Sold-out sizes stay disabled and discontinued sizes remain excluded. Pakistan checkout now retains contact/address details in the private session across a stock-error/cart-correction round trip, clearing them on successful order completion. Existing server stock checks, total review, COD/bank status and retry deduplication remain authoritative.

Demo URLs:
- http://127.0.0.1:8001/products/demo-journey-biker?market=pakistan
- http://127.0.0.1:8001/products/demo-journey-biker?market=international
- http://127.0.0.1:8001/products/demo-journey-windbreaker?market=international
- http://127.0.0.1:8001/inquiry

Guarded ShopperJourneyDemoSeeder wrote only isolated demo products and demo international terms: each jacket is USD 150, United Kingdom shipping is a fictional USD 25 per order, so two jackets estimate USD 325. Other countries require a quote. Black/Brown M/L are stocked, XL is sold out. This fixture does not represent real prices or shipping promises. No normal migration or command is required. Focused checks cover the three shopper workflows, included/separate shipping arithmetic, market enforcement, hidden-value preservation, and the existing SKU/photo double-save regression. Final visual acceptance is left to the owner as requested.

Cart/checkout clarity: Update quantity is enabled only after changing a quantity when JavaScript is available; native submission remains available without it. Proceed to checkout explains the address/payment step. Cart and checkout show a quiet Pakistan/PKR label instead of market-switch links. Contact/address fields now remain visible even when shipping, tax, payment configuration or stock prevents checkout; unavailable totals never produce a Place order button. Normal settings were inspected read-only: shipping and tax are unset and COD/bank disabled. Actual normal-store rates and payment activation still require owner values and approval; none were invented or saved.

Cart quantities now submit automatically after a 700ms typing pause or a committed change, using the existing server stock checks. The Update quantity button remains a no-JavaScript fallback. Proceed to checkout can first save a changed quantity. Checkout uses plain customer wording and omits market messaging from its header/footer. On the owner's explicit request, normal-store COD was enabled and bank transfer disabled; shipping/tax values were preserved unset pending owner input. When COD is the only available method it is selected automatically. No catalog, orders or migrations changed.

Delivery is controlled in Admin > Settings > Commerce settings > Pakistan checkout rules > Delivery charges: choose Free delivery or Fixed delivery charge, enter the rate when fixed, set tax (explicit 0 if applicable), then Save checkout and service rules. Not configured deliberately keeps ordering unavailable. Changing the selector stages the choice; it does not change customer totals until saved.

Checkout setup guidance: if missing delivery/tax/payment settings block checkout, shoppers see a store-contact action instead of instructions to change their cart. Signed-in authorized owners also get a direct Commerce settings link. The owner elected to configure normal delivery and tax values personally; they remain unset, so ordering intentionally remains unavailable until those settings are saved. COD remains enabled.

Checkout readiness now gates public buying controls: products use Contact us to order and carts omit Proceed to checkout until a delivery policy and usable payment method exist. Direct checkout links show one contact notice rather than a disabled form. Owner setup instructions are confined to admin, which shows the saved Live/Not live checkout status. Tax is removed from new checkout calculations and the admin form; historical order/payment/refund records remain unchanged. Free delivery must still be selected intentionally in admin; no normal-store delivery values were changed.

Admin size-row presentation: Sizes & availability now uses aligned expandable summaries with a size tile, SKU, separate PKR/USD prices, saved stock balances and text status badges. Rows stack on phones and honor the existing admin price-view preference. Saved values are explicitly labeled so they are not confused with pending edits. Stock/price actions, relationship saving and galleries are unchanged.

## Dashboard traffic and quicker product fixes - 23 November 2026

Admin > Dashboard now shows 14-day public page-request and order graphs plus referral websites, pages, device categories, operating systems and browsers. These are aggregate requests, not unique people or conversion rates; order counts include all statuses. No IP addresses, visitor IDs, full user agents, query strings or private shopping URLs are stored. Staff, known bots and DNT/GPC requests are excluded. Aggregates are pruned after 90 days by the existing Laravel scheduler (`analytics:prune` runs daily); keep the scheduler running. The dashboard refreshes its cached data after at most one minute. Device detection is approximate. The new traffic migration is applied only in the demo until the owner approves normal-store migration.

Products > Edit > Colors & sizes: collapsed saved rows now have Fix / edit price and Fix / edit stock actions, scoped to the selected SKU. The existing confirmation, inventory audit and pending-edit protection remain. Product pages display configured delivery terms beside the buying controls. Assisted-sale review uses the same product-delivery calculation as checkout. Draft previews no longer show footer WhatsApp links.

Isolated acceptance fixture: http://127.0.0.1:8001/products/demo-workflow-jacket (use `?market=international` for USD), http://127.0.0.1:8001/admin/products/17/edit and http://127.0.0.1:8001/admin/orders/11. The demo order contains two jackets with a PKR 24,250 total; its customer and address are fictional. Black/XL deliberately has stock and a zero PKR price for the row-fix exercise. Brown is sold out. Test traffic sources such as demo.example.test are simulated HTTP requests, not actual customers. No real messages or shipments were sent. Browser visual acceptance remains outstanding.
