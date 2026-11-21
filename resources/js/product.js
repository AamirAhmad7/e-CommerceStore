const root = document.querySelector('#product-data');
if (root) {
    const data = JSON.parse(root.textContent);
    const gallery = document.querySelector('#color-gallery');
    const sizes = document.querySelector('#sizes');
    let color = data.colors.find(item => item.id === data.selected);
    let selectedSize = data.size;

    function renderSize() {
        const variant = color?.variants.find(item => item.size === selectedSize && item.can_buy);
        if (!variant) selectedSize = null;
        const whatsapp = document.querySelector('#ask-whatsapp');
        if (whatsapp) whatsapp.href = variant?.whatsapp_url ?? color?.whatsapp_url ?? whatsapp.href;
        document.querySelector('#selected-price').textContent = variant?.price_label ?? color?.price_label ?? 'Currently unavailable';
        document.querySelector('#selected-stock').textContent = variant?.availability ?? (data.preview && !color?.variants.length ? 'No active sizes. Activate a size in the product form.' : (color?.availability ?? 'No sizes are currently available to order. Browse other products or contact the store.'));
        const variantInput = document.querySelector('#cart-variant');
        if (variantInput) {
            variantInput.value = variant?.id ?? '';
            const quantity = document.querySelector('#add-to-cart input[name="quantity"]');
            quantity.disabled = !variant?.can_buy;
            quantity.max = Math.min(100, variant?.available ?? 0);
            document.querySelector('#cart-submit').disabled = !variant?.can_buy || !quantity.checkValidity();
        }
        document.querySelector('#size-name').textContent = variant?.label ?? 'Choose a size.';
        document.querySelector('#variant-status').textContent = variant
            ? `Selected: ${color.name} / ${variant.label} · SKU ${variant.sku}`
            : '';
        sizes.querySelectorAll('[data-size]').forEach(link => {
            if (link.dataset.size === selectedSize) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
    }

    function renderAlert() {
        const disclosure = document.querySelector('#restock-disclosure');
        if (!disclosure) return;
        const select = document.querySelector('#restock-variant');
        const previous = select.value;
        const variants = color.variants.filter(item => item.can_notify);
        disclosure.hidden = !variants.length;
        select.replaceChildren(...variants.map(variant => new Option(variant.label, variant.id)));
        if (variants.some(variant => String(variant.id) === previous)) select.value = previous;
        document.querySelector('#restock-color').textContent = color.name;
        renderAlertLabel();
    }
    function renderAlertLabel() {
        const select = document.querySelector('#restock-variant');
        const form = document.querySelector('#restock-form');
        if (!select || !form) return;
        document.querySelector('#restock-label').textContent = 'Notify me for ' + form.dataset.productName + ' / ' + color.name + ' / ' + (select.selectedOptions[0]?.textContent ?? '') + '.';
    }
    document.querySelector('#restock-variant')?.addEventListener('change', renderAlertLabel);

    function selectColor(next, size = selectedSize, maySelectOnlySize = true) {
        if (!next) return;
        const changed = color?.id !== next.id;
        color = next;
        if (changed) renderAlert();
        selectedSize = next.variants.some(item => item.size === size && item.can_buy) ? size : null;
        if (changed && !size && maySelectOnlySize) {
            const available = next.variants.filter(item => item.can_buy);
            if (available.length === 1) selectedSize = available[0].size;
        }
        document.querySelector('#unavailable-size')?.remove();
        document.querySelector('#color-name').textContent = color.name;
        document.querySelectorAll('[data-color]').forEach(link => {
            if (link.dataset.color === color.id) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
        if (changed) {
            const template = document.querySelector(`[data-gallery-template="${color.id}"]`);
            gallery.replaceChildren(template.content.cloneNode(true));
        }
        sizes.replaceChildren();
        color.variants.forEach(variant => {
            const link = document.createElement(variant.can_buy ? 'a' : 'button');
            const url = new URL(color.url);
            url.searchParams.set('size', variant.size);
            if (variant.can_buy) {
                link.href = url;
                link.dataset.size = variant.size;
                link.textContent = variant.label;
            } else {
                link.type = 'button';
                link.disabled = true;
                link.setAttribute('aria-label', variant.label + ' - ' + variant.disabled_label);
                const label = document.createElement('s');
                label.textContent = variant.label;
                link.append(label, ' ' + variant.disabled_label);
            }
            sizes.append(link);
        });
        if (!color.variants.length) {
            const message = document.createElement('p');
            message.textContent = 'No sizes are currently offered for this color.';
            sizes.append(message);
        }
        renderSize();
    }

    function updateUrl() {
        const url = new URL(color.url);
        if (selectedSize) url.searchParams.set('size', selectedSize);
        history.pushState(null, '', url);
    }

    function ordinaryClick(event) {
        return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
    }

    document.querySelector('.colors').addEventListener('click', event => {
        const link = event.target.closest('[data-color]');
        if (!link || !ordinaryClick(event)) return;
        event.preventDefault();
        selectColor(data.colors.find(item => item.id === link.dataset.color));
        updateUrl();
    });
    sizes.addEventListener('click', event => {
        const link = event.target.closest('[data-size]');
        if (!link || !ordinaryClick(event)) return;
        event.preventDefault();
        document.querySelector('#unavailable-size')?.remove();
        selectedSize = link.dataset.size;
        renderSize(); // Deliberately leaves the gallery DOM and image sources untouched.
        updateUrl();
    });
    gallery.addEventListener('click', event => {
        const link = event.target.closest('[data-photo]');
        if (!link || !ordinaryClick(event)) return;
        event.preventDefault();
        const index = Number(link.dataset.photo);
        const photo = color.photos[index];
        const image = gallery.querySelector('.main-photo');
        if (photo.srcset) image.setAttribute('srcset', photo.srcset);
        else image.removeAttribute('srcset');
        image.src = photo.src;
        image.alt = photo.alt;
        image.width = photo.width;
        image.height = photo.height;
        gallery.querySelector('.large-photo').href = photo.original;
        gallery.querySelector('.large-photo').setAttribute('aria-label', `Open larger image: ${photo.purpose ?? color.name} in a new tab`);
        gallery.querySelector('.photo-count').textContent = `${index + 1} / ${color.photos.length}`;
        gallery.querySelectorAll('[data-photo]').forEach(item => {
            if (item === link) item.setAttribute('aria-current', 'true');
            else item.removeAttribute('aria-current');
        });
    });
    gallery.addEventListener('keydown', event => {
        if (!event.target.closest('[data-photo]') || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const links = [...gallery.querySelectorAll('[data-photo]')];
        const current = links.indexOf(event.target.closest('[data-photo]'));
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + links.length) % links.length;
        event.preventDefault();
        links[next].focus();
        links[next].click();
    });
    document.querySelector('#add-to-cart input[name="quantity"]')?.addEventListener('input', renderSize);
    window.addEventListener('popstate', () => {
        const url = new URL(location.href);
        const requestedId = url.searchParams.get('color');
        const requestedColor = data.colors.find(item => item.id === requestedId);
        const missingColor = Boolean(requestedId && !requestedColor);
        selectColor(requestedColor ?? data.colors.find(item => item.id === data.selected), missingColor ? null : url.searchParams.get('size'), !missingColor);
        if (missingColor) document.querySelector('#selected-stock').textContent = 'That color is not currently offered. Choose a color and an available size.';
    });
}
// Convert display values from their immutable original unit; no request or selection reset.
document.querySelectorAll('[data-measurements]').forEach(guide => {
    guide.querySelector('[data-unit-control]').hidden = false;
    guide.querySelector('[data-guide-unit]').addEventListener('change', event => {
        const unit = event.target.value;
        const original = guide.dataset.originalUnit;
        guide.querySelector('[data-unit-label]').textContent = unit;
        guide.querySelectorAll('[data-measure-value]').forEach(cell => {
            const value = Number(cell.dataset.measureValue);
            cell.textContent = unit === original ? cell.dataset.measureValue : (original === 'cm' ? value / 2.54 : value * 2.54).toFixed(1);
        });
    });
});
