if (document.querySelector('#product-data')) import('./product.js');

const header = document.querySelector('[data-store-header]');
if (header && header.querySelector('#store-menu')) {
    const opener = header.querySelector('.menu-toggle');
    const menu = header.querySelector('#store-menu');
    if (!menu) opener.hidden = true;
    const wide = matchMedia('(min-width: 1024px)');
    header.dataset.menuEnhanced = '';
    opener.hidden = false;
    function closeMenu(restoreFocus = false) {
        delete menu.dataset.open;
        opener.setAttribute('aria-expanded', 'false');
        opener.querySelector('[data-menu-label]').textContent = 'Menu';
        if (restoreFocus && !wide.matches) opener.focus();
    }
    opener.addEventListener('click', () => {
        if (menu.hasAttribute('data-open')) return closeMenu(true);
        const search = header.querySelector('[data-header-search]');
        if (search) search.open = false;
        menu.dataset.open = '';
        opener.setAttribute('aria-expanded', 'true');
        opener.querySelector('[data-menu-label]').textContent = 'Close';
        menu.querySelector('a')?.focus();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.hasAttribute('data-open')) {
            event.preventDefault();
            closeMenu(true);
        }
    });
    document.addEventListener('click', event => {
        if (!header.contains(event.target)) closeMenu(menu.contains(document.activeElement));
    });
    menu.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
    header.addEventListener('focusout', () => {
        requestAnimationFrame(() => { if (!header.contains(document.activeElement)) closeMenu(); });
    });
    header.querySelector('[data-header-search] summary')?.addEventListener('click', () => closeMenu());
    wide.addEventListener('change', () => closeMenu());
    window.addEventListener('pageshow', () => closeMenu());
}

const shoppingFeedback = document.querySelector('#shopping-feedback');
if (shoppingFeedback) shoppingFeedback.focus({ preventScroll: true });
document.querySelectorAll('[data-dismiss-after]').forEach(notice => {
    window.setTimeout(() => { notice.hidden = true; }, Number(notice.dataset.dismissAfter));
});

// Keep submitted button values intact (including Remove); server idempotency remains authoritative.
function releaseForm(form) {
    form.removeAttribute('aria-busy');
    form.querySelectorAll('[data-submit-busy]').forEach(button => {
        button.removeAttribute('aria-disabled');
        delete button.dataset.submitBusy;
    });
}
document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !form.checkValidity()) return;
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    form.setAttribute('aria-busy', 'true');
    if (event.submitter) {
        event.submitter.dataset.submitBusy = '';
        event.submitter.setAttribute('aria-disabled', 'true');
    }
    setTimeout(() => releaseForm(form), 12000);
});
window.addEventListener('pageshow', () => document.querySelectorAll('form[aria-busy]').forEach(releaseForm));

document.querySelectorAll('[data-share-url]').forEach(control => {
    const url = control.dataset.shareUrl;
    const status = control.querySelector('[data-share-status]');
    const input = control.querySelector('[data-share-input]');
    async function copyLink() {
        try {
            await navigator.clipboard.writeText(url);
            status.textContent = 'Collection link copied.';
        } catch {
            input.focus();
            input.select();
            status.textContent = 'Copy the selected public link.';
        }
    }
    const share = control.querySelector('[data-share]');
    const copy = control.querySelector('[data-copy-link]');
    share.hidden = false;
    copy.hidden = false;
    copy.addEventListener('click', copyLink);
    share.addEventListener('click', async () => {
        if (!navigator.share) return copyLink();
        try {
            await navigator.share({ title: control.dataset.shareTitle, url });
            status.textContent = 'Collection link shared.';
        } catch (error) {
            if (error.name !== 'AbortError') await copyLink();
        }
    });
});


const paymentSummary = document.querySelector('[data-payment-summary]');
if (paymentSummary) {
    const selectedPayment = document.querySelector('[data-payment-label]:checked');
    if (selectedPayment) paymentSummary.textContent = 'Payment: ' + selectedPayment.dataset.paymentLabel;
    document.querySelectorAll('[data-payment-label]').forEach(input => input.addEventListener('change', () => {
        if (input.checked) paymentSummary.textContent = 'Payment: ' + input.dataset.paymentLabel;
    }));
}

const headerSearch = document.querySelector('[data-header-search]');
if (headerSearch) {
    headerSearch.querySelector('summary').addEventListener('click', () => {
        if (!headerSearch.open) requestAnimationFrame(() => headerSearch.querySelector('input[type=search]').focus());
    });
}
headerSearch?.addEventListener('keydown', (event) => { if (event.key === 'Escape') { headerSearch.open = false; headerSearch.querySelector('summary').focus(); } });
const filtersPanel = document.querySelector('[data-discovery-filters]');



if (document.querySelector('#quick-add') && typeof HTMLDialogElement !== 'undefined' && 'showModal' in HTMLDialogElement.prototype) {
    document.addEventListener('click', async event => {
        const card = event.target.closest('[data-quick-add]');
        if (!card || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        try { const { openQuickAdd } = await import('./quick-add.js'); await openQuickAdd(card); }
        catch { location.assign(card.href); }
    });
}


if (filtersPanel) {
    const wideFilters = matchMedia('(min-width:1024px)');
    const summary = filtersPanel.querySelector('summary');
    function positionFilters() {
        filtersPanel.open = wideFilters.matches;
        document.querySelector('.catalog-filters').scrollTop = 0;
    }
    positionFilters();
    wideFilters.addEventListener('change', positionFilters);
    filtersPanel.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !event.defaultPrevented && filtersPanel.open) {
            event.preventDefault();
            filtersPanel.open = false;
            summary.focus({ preventScroll: true });
        }
    });
}
let searchModule;
document.addEventListener('focusin', event => {
    if (!event.target.matches('[data-search-suggest]')) return;
    const root = event.target.closest('[data-suggest-search]');
    searchModule ??= import('./search.js').catch(() => { searchModule = null; return null; });
    searchModule.then(module => module?.initSuggestions(root));
});


const catalogFilterForm = document.querySelector('#catalog-filter-form');
if (catalogFilterForm) {
    const currentSearch = document.querySelector('#header-search');
    catalogFilterForm.addEventListener('submit', () => {
        if (currentSearch) catalogFilterForm.elements.q.value = currentSearch.value;
    });
    const sort = document.querySelector('[data-catalog-sort]');
    document.querySelector('[data-sort-submit]').hidden = true;
    sort.addEventListener('change', () => catalogFilterForm.requestSubmit());
}

// Debounce typing; keep the native Update button as the no-JavaScript fallback.
document.querySelectorAll('[data-cart-quantity-form]').forEach(form => {
    const quantity = form.querySelector('input[name="quantity"]');
    const button = form.querySelector('[data-update-quantity]');
    const original = quantity.defaultValue;
    const status = document.createElement('span');
    status.setAttribute('role', 'status');
    form.append(status);
    button.hidden = true;
    let timer;
    const save = () => {
        clearTimeout(timer);
        if (quantity.value === original || !quantity.checkValidity() || form.getAttribute('aria-busy') === 'true') return;
        status.textContent = 'Updating quantity...';
        form.requestSubmit(button);
    };
    quantity.addEventListener('input', () => {
        clearTimeout(timer);
        status.textContent = quantity.value === original ? '' : 'Quantity will update automatically.';
        timer = setTimeout(save, 700);
    });
    quantity.addEventListener('change', save);
    document.querySelector('[data-proceed-checkout]')?.addEventListener('pointerdown', () => {
        if (quantity.value !== original && quantity.checkValidity()) form.elements.after_update.value = 'checkout';
    });
    document.querySelector('[data-proceed-checkout]')?.addEventListener('click', event => {
        if (quantity.value === original) return;
        event.preventDefault();
        if (!quantity.reportValidity()) return;
        form.elements.after_update.value = 'checkout';
        save();
    });
});

document.querySelectorAll('#add-to-cart input[name="quantity"], [data-cart-quantity-form] input[name="quantity"]').forEach(input => {
    const stepper = document.createElement('span');
    stepper.className = 'quantity-stepper';
    input.before(stepper);
    stepper.append(input);
    for (const [label, direction] of [['Decrease quantity', -1], ['Increase quantity', 1]]) {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.textContent = direction === -1 ? '\u2212' : '+';
        const sync = () => { button.disabled = input.disabled || (direction < 0 ? Number(input.value) <= Number(input.min || 1) : Number(input.value) >= Number(input.max || 100)); };
        input.addEventListener('input', sync);
        new MutationObserver(sync).observe(input, { attributes: true, attributeFilter: ['max', 'min', 'disabled'] });
        sync();
        button.addEventListener('click', () => {
            if (input.disabled) return;
            if (direction === -1) input.stepDown(); else input.stepUp();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        if (direction === -1) stepper.prepend(button); else stepper.append(button);
    }
});

const addForm = document.querySelector('#add-to-cart');
const cartConfirmation = document.querySelector('#added-to-cart');
if (addForm && cartConfirmation && typeof cartConfirmation.showModal === 'function') {
    let adding = false;
    const message = document.createElement('p');
    message.setAttribute('role', 'status');
    addForm.after(message);
    addForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (adding || !addForm.reportValidity()) return;
        adding = true;
        addForm.setAttribute('aria-busy', 'true');
        message.textContent = 'Adding to your cart...';
        try {
            const response = await fetch(addForm.action, { method: 'POST', body: new FormData(addForm), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok) {
                message.textContent = Object.values(result.errors ?? {}).flat().join(' ') || 'Please review your selection and try again.';
                const cartLink = document.createElement('a');
                cartLink.href = cartConfirmation.querySelector('a[href]').nextElementSibling.href;
                cartLink.textContent = ' View cart';
                message.append(cartLink);
                return;
            }
            document.dispatchEvent(new CustomEvent('sf:commerce', { detail: result.analytics }));
            document.querySelector('.cart-count').textContent = result.count;
            document.querySelector('.header-cart').setAttribute('aria-label', `Cart, ${result.count} items`);
            cartConfirmation.querySelector('[data-added-cart-message]').textContent = result.message;
            message.textContent = '';
            cartConfirmation.showModal();
        } catch {
            message.textContent = 'Connection interrupted. Check your cart before trying again.';
        } finally {
            adding = false;
            addForm.removeAttribute('aria-busy');
        }
    });
    cartConfirmation.querySelector('[data-close-cart]').addEventListener('click', () => cartConfirmation.close());
    cartConfirmation.addEventListener('close', () => addForm.querySelector('button[type=submit], #cart-submit')?.focus());
}

const checkoutForm = document.querySelector('form.checkout-grid');
if (checkoutForm) {
    const city = checkoutForm.elements.city;
    const province = checkoutForm.elements.region;
    city?.addEventListener('change', () => {
        const match = [...document.querySelectorAll('#checkout-city-options option')].find(option => option.value.toLowerCase() === city.value.trim().toLowerCase());
        if (match) { city.value = match.value; province.value = match.dataset.province; }
        updateCheckout();
    });
    const placeOrder = checkoutForm.querySelector('button[type=submit]');
    function updateCheckout() {
        if (placeOrder) placeOrder.disabled = ![...checkoutForm.elements].every(field => !field.willValidate || field.validity.valid);
    }
    checkoutForm.addEventListener('input', updateCheckout);
    checkoutForm.addEventListener('change', updateCheckout);
    checkoutForm.addEventListener('focusout', event => {
        const field = event.target;
        if (!field.willValidate) return;
        field.setAttribute('aria-invalid', String(!field.validity.valid));
        let error = field.parentElement.querySelector(`[data-validation-for="${field.name}"]`);
        if (!error) { error = document.createElement('small'); error.className = 'field-error'; error.dataset.validationFor = field.name; field.after(error); }
        error.textContent = field.validity.valid ? '' : field.validationMessage;
    });
    updateCheckout();
    window.addEventListener('pageshow', updateCheckout);
}

if (filtersPanel && typeof HTMLDialogElement !== 'undefined' && 'showModal' in HTMLDialogElement.prototype) {
    const filterDialog = document.createElement('dialog');
    filterDialog.className = 'mobile-filter-dialog';
    filterDialog.setAttribute('aria-label', 'Filter products');
    const closeFilters = document.createElement('button');
    closeFilters.className = 'button-secondary'; closeFilters.type = 'button'; closeFilters.textContent = 'Close filters';
    filterDialog.append(closeFilters); document.body.append(filterDialog);
    const opener = filtersPanel.querySelector('summary');
    opener.addEventListener('click', event => {
        if (matchMedia('(min-width:1024px)').matches) return;
        event.preventDefault();
        filterDialog.append(catalogFilterForm); filterDialog.showModal();
    });
    closeFilters.addEventListener('click', () => filterDialog.close());
    filterDialog.addEventListener('close', () => { filtersPanel.append(catalogFilterForm); opener.focus(); });
    matchMedia('(min-width:1024px)').addEventListener('change', event => { if (event.matches && filterDialog.open) filterDialog.close(); });
    let filterTimer;
    catalogFilterForm.addEventListener('change', event => {
        if (!matchMedia('(min-width:1024px)').matches || !event.target.matches('input[type=checkbox]')) return;
        clearTimeout(filterTimer); filterTimer = setTimeout(() => catalogFilterForm.requestSubmit(), 600);
    });
}

const mobileBuy = document.querySelector('[data-mobile-buy]');
const mainBuy = document.querySelector('#cart-submit');
if (mobileBuy && mainBuy && 'IntersectionObserver' in window) {
    const price = document.querySelector('#selected-price');
    const button = mobileBuy.querySelector('[data-mobile-add]');
    const syncBuy = () => {
        mobileBuy.querySelector('[data-mobile-price]').textContent = price.textContent;
        button.textContent = mainBuy.disabled ? 'Choose size' : 'Add to cart';
        button.disabled = addForm?.getAttribute('aria-busy') === 'true';
    };
    new MutationObserver(syncBuy).observe(price, { childList:true, subtree:true, characterData:true });
    new MutationObserver(syncBuy).observe(mainBuy, { attributes:true, attributeFilter:['disabled'] });
    new MutationObserver(syncBuy).observe(addForm, { attributes:true, attributeFilter:['aria-busy'] });
    new IntersectionObserver(entries => { mobileBuy.hidden = !(entries[0].boundingClientRect.bottom < 0 && !entries[0].isIntersecting); }).observe(mainBuy);
    button.addEventListener('click', () => {
        if (mainBuy.disabled) { document.querySelector('#sizes').scrollIntoView({ block:'center', behavior:'smooth' }); document.querySelector('#sizes [data-size]')?.focus({preventScroll:true}); }
        else addForm.requestSubmit(mainBuy);
    });
    syncBuy();
}

if (document.querySelector('#commerce-events')) import('./commerce-events.js');
