# Pakistan storefront release - 23 November 2026

The public storefront now uses PKR and COD. Existing USD prices and international services remain stored for backward compatibility, but public market controls and inquiry routes are hidden or disabled in the Pakistan-only mode.

## Phase 1 - Immediate fixes

- Cart stock errors distinguish the last piece already in the cart from other shortages, with a nearby View cart link.
- Product and Quick Add submissions use JSON and stay on the current page; normal HTML form fallbacks remain available without JavaScript.
- Search uses a magnifier beside the cart, with a compact expandable field and existing suggestions.
- Clear swatches, outlined card actions, spaced quantity steppers with disabled limit buttons, no redundant checkout-summary number or duplicate category chips.
- Whole amounts display as PKR 15,000; fractional amounts retain their precision. Editable money fields and structured-data prices retain their original decimal representation.
- Corrected the existing Windbreaker Jacket Black product name to Windbreaker Jacket without changing its slug or SKUs. Unknown size codes were not renamed.

Review: resources/views/layouts/store.blade.php, resources/views/products/card.blade.php, resources/js/app.js, app/Services/Cart.php, app/Services/Pricing.php.
Manual check: at 360px, open search, choose a suggestion, add the last piece twice, and confirm the error and View cart link are next to the buying controls.

## Phase 2 - Product selection

- Size-specific prices, visible Sold out labels, and low-stock notices only at five pieces or fewer.
- Existing per-color galleries and no-request size changes are preserved.
- Full-screen image viewer with zoom, previous/next, arrow keys and touch swipe; Escape closes and restores focus.
- Existing measurement data opens in a size-guide modal and converts cm/inches. Optional age-range labels can be entered alongside kids' measurements.
- Structured material, lining, closure, fit and care details are owner-authored. No material or delivery claims were invented.
- Related products use batch-loaded relationships; same-category jackets appear first.
- Mobile price/buying bar uses IntersectionObserver, not scroll listeners.

Review: resources/views/products/show.blade.php, resources/js/product.js, app/Http/Controllers/ProductController.php, ProductForm.php and SizeGuideFields.php.
Manual check: select two sizes of the same color and confirm price changes without changing the photo. Try unavailable sizes, the guide's units, zoom/swipe, and the mobile bottom bar.

## Phase 3 - Home and shop

- Two mobile columns, three/four when the results area allows; incomplete rows retain consistent card widths.
- New (created within 30 days), Last piece and Sold out badges from existing product/stock data.
- Responsive images retain their original photography and framing; no fake products or altered photos.
- Mobile filters open in a native accessible dialog; desktop checkbox changes apply after a brief debounce. Explicit Show results and no-JavaScript filtering remain available.
- Existing pagination, empty states, SKU/color search and debounced suggestions are reused.

Review: resources/views/shop/filters.blade.php, resources/views/shop/index.blade.php, resources/views/products/card.blade.php, resources/css/app.css.
Manual check: filter and sort at 360px, close with Escape, follow pagination and reset filters. Confirm no page-level horizontal overflow and that product cards remain clickable.

## Phase 4 - Cart

- Store default delivery: PKR 500. Free delivery from PKR 14,000, subject to the existing discount calculation.
- Product-specific charges are preserved as requested. The highest applicable charge applies once per order; no per-color, per-size or per-unit duplication.
- Free-delivery progress reflects the server quote, including already-free delivery.
- Inline discount controls, clearer thumbnails and stock notes, and a friendly empty state.
- Encrypted, HTTP-only, 30-day cart cookie stores only variant IDs and quantities. Prices and stock are always read again from the server; restoring a cart never reserves stock.

Admin: Settings > Commerce Settings for store delivery defaults; Catalog > Products > Edit > Pricing for product delivery overrides (blank = default; 0 = free).
Manual check: add jackets with different charges, change quantities, cross the free-delivery threshold, and reload. Confirm the highest charge occurs once and discounts/free delivery agree with checkout.

## Phase 5 - Checkout

- Minimal navigation, no search, COD only in Pakistan-only mode.
- Optional email, required Pakistani mobile number, city suggestions that fill province, manual entry retained for destinations outside the suggestion list, and optional order notes.
- Existing address values survive validation failures; the server rechecks stock, prices and shipping and retains retry protection.
- Inline validation, policy agreement, thumbnails, Edit cart link, Free shipping label and synchronized COD summary.
- Orders without email do not enqueue email jobs. Admin clearly shows that no email was supplied rather than suggesting a stuck queue.

Review: app/Http/Controllers/CheckoutController.php, resources/views/shop/checkout.blade.php, app/Support/PakistanAddress.php.
Manual check: place an isolated COD order without email, try an invalid phone, and retry a successful submission. Check saved notes, one order only, and the actual total.

## Phase 6 - After ordering

- /order-received is tied to the checkout session, with order details and a private recovery link.
- Existing email confirmation is sent only when an email is supplied. WhatsApp opens a prefilled inquiry; it does not automatically send a message.
- Find my order requires reference plus matching phone, or sends a private link to a matching checkout email. Lookup throttling remains enabled.

Review: app/Http/Controllers/OrderCareController.php, resources/views/shop/find-order.blade.php, resources/views/shop/order.blade.php.
Manual check: confirm another session cannot open the receipt URL, an incorrect phone fails recovery, and the correct reference/phone succeeds.

## Phase 7 - Trust, SEO and analytics

- Existing canonical URLs, sitemap, Product offers, SKU data, Open Graph images and page metadata are preserved. Generic trust text uses COD, support and the actual policy rather than unverified leather/exchange claims.
- Optional GA4/Meta events: view_item, add_to_cart, begin_checkout, purchase. Events contain product/amount data, not contact/address information or private order-access tokens.
- Vendor scripts are off by default and load only after consent. Do Not Track and Global Privacy Control are respected.
- Purchase uses an opaque transaction ID and browser deduplication. It represents an order placed, including unpaid COD, not cash collected. Tracking failures never block checkout; client analytics can be blocked by browsers and are not an accounting ledger.
- Private token-bearing order pages, assisted-sale links and admin pages do not load marketing tags.

Enable only with your real IDs in .env:

    STORE_MARKETING_ENABLED=true
    STORE_GA4_ID=G-YOURID
    STORE_META_PIXEL_ID=YOUR_NUMERIC_ID

Then clear cached configuration if applicable. No accounts, IDs, external messages or paid providers were created.
Reference APIs: [GA4 ecommerce](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce) and [Meta Pixel](https://developers.facebook.com/docs/meta-pixel/reference/).
Manual check: with test IDs, deny consent and confirm no vendor requests; allow consent and verify events in vendor debug tools. Reload a receipt and check purchase deduplication. Provider delivery has not been verified because IDs were not supplied.

## Remaining owner decisions and migration

- Confirm whether SS means XS and whether numeric sizes are intentional; no SKU or size was guessed or renamed.
- Supply accurate measurement/age-range values, closure/material specifications and delivery/exchange policy copy.
- Delivery estimates and WhatsApp-before-dispatch promises remain unpublished until confirmed. Optional settings: STORE_DELIVERY_ESTIMATE and STORE_CONFIRM_COD_WHATSAPP.
- Confirm your analytics IDs and privacy copy before enabling vendor tracking.
- The additive closure column migration was approved and applied to both the normal store and isolated demo. Closure / hardware is available in admin. The completed command was:

    php artisan migrate --path=database/migrations/2026_11_23_061333_add_closure_to_products_table.php --force --no-interaction

Use PHP 8.4. The installed executable is C:/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe. No dependency installation is required. No migration command remains for you to run.

## Demo and verification

- Product: http://127.0.0.1:8001/products/demo-workflow-jacket
- Catalog: http://127.0.0.1:8001/shop
- Cart and checkout: http://127.0.0.1:8001/cart and http://127.0.0.1:8001/checkout after adding a demo jacket.
- Admin: http://127.0.0.1:8001/admin/products/17/edit
- Demo uses leather_store_demo, isolated media and log mail. Normal catalog edits were limited to the requested product name and delivery settings; product delivery overrides were preserved.
- Browser visual acceptance is still required. No installed Playwright runner was available, and no dependencies were installed. Follow each phase's manual check at 360px and a desktop width.

Verified: full PHP suite 148 passed, 7 MySQL concurrency tests skipped, 2,306 assertions; 3 search-interaction tests passed; Pint, Vite build and Blade compilation passed. Demo product returned HTTP 200. Catalog query check remains 13 queries for both 1 and 12 cards. Visual browser/device acceptance and live GA4/Meta delivery were not performed.
