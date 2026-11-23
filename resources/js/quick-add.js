const dialog = document.querySelector('#quick-add');
const form = dialog.querySelector('[data-quick-form]');
const status = dialog.querySelector('[data-quick-status]');
const colors = dialog.querySelector('[data-quick-colors]');
const sizes = dialog.querySelector('[data-quick-sizes]');
const price = dialog.querySelector('[data-quick-price]');
const submit = dialog.querySelector('[data-quick-submit]');
const quantity = form.elements.quantity;
let opener, endpoint, product, colorId, variantId, pending = false, generation = 0;

function selectedColor() { return product?.colors.find(color => color.id === colorId); }
function selectedVariant() { return selectedColor()?.variants.find(variant => String(variant.id) === variantId); }
function renderPrice() {
    const variant = selectedVariant();
    form.elements.variant_id.value = variant?.can_buy ? variant.id : '';
    form.elements.expected_price.value = variant?.can_buy ? variant.price : '';
    price.textContent = variant?.price_label ?? 'Choose an available size to see its price.';
    quantity.max = Math.min(100, variant?.available ?? 100);
    submit.disabled = pending || !variant?.can_buy || !quantity.checkValidity();
    sizes.querySelectorAll('[data-variant]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.variant === variantId)));
}
function renderChoices() {
    colors.replaceChildren(...product.colors.map(color => new Option(color.name, color.id)));
    if (!product.colors.some(color => color.id === colorId)) {
        colors.prepend(new Option('Featured color is no longer offered - choose another', '', true, true));
        colors.value = '';
    } else colors.value = colorId;
    sizes.replaceChildren();
    const color = selectedColor();
    for (const variant of color?.variants ?? []) {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.variant = String(variant.id);
        button.disabled = pending || !variant.can_buy;
        button.setAttribute('aria-label', variant.label + (variant.can_buy ? '' : ' - ' + variant.disabled_label));
        if (variant.can_buy) button.textContent = variant.label;
        else {
            const crossed = document.createElement('s');
            crossed.textContent = variant.label;
            button.append(crossed, ' ' + variant.disabled_label);
        }
        sizes.append(button);
    }
    if (!color?.variants.some(variant => variant.can_buy)) {
        const explanation = document.createElement('p');
        explanation.textContent = color ? 'This color has no sizes available to order. Choose another color explicitly.' : 'The featured color is no longer offered. Choose another color explicitly.';
        sizes.append(explanation);
    }
    colors.disabled = pending;
    quantity.disabled = pending;
    renderPrice();
}
async function loadChoices(version) {
    const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
    if (!response.ok) throw new Error('Choices could not be loaded. Open the full product page to check availability.');
    const data = await response.json();
    if (version !== generation) return false;
    product = data;
    form.elements.product_id.value = product.id;
    form.elements.currency.value = product.currency;
    dialog.querySelector('#quick-add-title').textContent = product.name;
    renderChoices();
    form.hidden = false;
    return true;
}
export async function openQuickAdd(card) {
    if (pending) { if (!dialog.open) dialog.showModal(); return; }
    const version = ++generation;
    opener = card;
    endpoint = card.dataset.quickAdd;
    colorId = card.dataset.quickColor;
    variantId = null;
    quantity.value = '1';
    product = null;
    form.hidden = true;
    dialog.querySelector('[data-quick-cart]').hidden = true;
    dialog.querySelector('[data-quick-product]').href = card.href;
    dialog.querySelector('#quick-add-title').textContent = 'Choose your size';
    status.textContent = 'Loading current sizes and prices...';
    if (!dialog.open) dialog.showModal();
    try {
        if (await loadChoices(version)) status.textContent = 'Choose a color and size. No size is selected automatically.';
    } catch (error) { if (version === generation) status.textContent = error.message; }
}
colors.addEventListener('change', () => { colorId = colors.value; variantId = null; renderChoices(); });
sizes.addEventListener('click', event => {
    const button = event.target.closest('[data-variant]');
    if (!button || button.disabled || pending) return;
    variantId = button.dataset.variant;
    renderPrice();
});
quantity.addEventListener('input', renderPrice);
form.addEventListener('submit', async event => {
    event.preventDefault();
    if (pending || !selectedVariant()?.can_buy || !form.reportValidity()) return;
    const body = new FormData(form);
    pending = true;
    form.setAttribute('aria-busy', 'true');
    renderChoices();
    status.textContent = 'Adding your selected size...';
    try {
        const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok) {
            dialog.querySelector('[data-quick-cart]').hidden = false;
            status.textContent = Object.values(result.errors ?? {}).flat().join(' ') || 'Could not add this item. Review the selection or refresh the page.';
            if (response.status === 422) {
                try { await loadChoices(generation); }
                catch { status.textContent += ' Open the product page for current availability.'; }
            }
            return;
        }
        document.dispatchEvent(new CustomEvent('sf:commerce', { detail: result.analytics }));
        document.querySelector('.cart-count').textContent = result.count;
        document.querySelector('.header-cart').setAttribute('aria-label', 'Cart, ' + result.count + ' items');
        status.textContent = result.message;
        dialog.querySelector('[data-quick-cart]').hidden = false;
    } catch {
        status.textContent = 'The connection was interrupted. Check your cart before trying again.';
        dialog.querySelector('[data-quick-cart]').hidden = false;
    } finally {
        pending = false;
        form.removeAttribute('aria-busy');
        renderChoices();
    }
});
dialog.querySelector('[data-quick-close]').addEventListener('click', () => dialog.close());
dialog.addEventListener('close', () => { if (!pending) generation++; opener?.focus({ preventScroll: true }); });
