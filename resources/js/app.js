if (document.querySelector('#product-data')) import('./product.js');

const header = document.querySelector('[data-store-header]');
if (header) {
    const opener = header.querySelector('.menu-toggle');
    const menu = header.querySelector('#store-menu');
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
    document.querySelectorAll('[data-payment-label]').forEach(input => input.addEventListener('change', () => {
        if (input.checked) paymentSummary.textContent = 'Payment: ' + input.dataset.paymentLabel;
    }));
}

const headerSearch = document.querySelector('[data-header-search]');
if (headerSearch) {
    const wideSearch = matchMedia('(min-width:1024px)');
    headerSearch.open = wideSearch.matches;
    wideSearch.addEventListener('change', () => { headerSearch.open = wideSearch.matches; });
    headerSearch.querySelector('summary').addEventListener('click', () => {
        if (!headerSearch.open) requestAnimationFrame(() => headerSearch.querySelector('input[type=search]').focus());
    });
}
headerSearch?.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !matchMedia('(min-width:1024px)').matches) { headerSearch.open = false; headerSearch.querySelector('summary').focus(); } });
const filtersPanel = document.querySelector('[data-discovery-filters]');
filtersPanel?.addEventListener('toggle', () => { if (!filtersPanel.open) filtersPanel.querySelector('form').reset(); });

if (filtersPanel && matchMedia('(min-width:1024px)').matches) filtersPanel.open = true;
