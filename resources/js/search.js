const initialized = new WeakSet();
export function initSuggestions(root) {
    if (initialized.has(root)) return;
    initialized.add(root);
    const input = root.querySelector('[data-search-suggest]');
    const panel = document.getElementById(input.getAttribute('aria-controls'));
    const list = panel.querySelector('[role=listbox]');
    const message = panel.querySelector('.suggestion-message');
    const announcement = root.querySelector('[data-search-announcement]');
    const form = input.form;
    let timer, controller, sequence = 0, active = -1;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    list.id = panel.id + '-list';
    input.setAttribute('aria-controls', list.id);
    function close() {
        clearTimeout(timer);
        controller?.abort();
        sequence++;
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }
    function choose(index) {
        const links = [...list.querySelectorAll('[role=option]')];
        active = index;
        links.forEach((link, i) => link.setAttribute('aria-selected', String(i === active)));
        if (links[active]) {
            input.setAttribute('aria-activedescendant', links[active].id);
            links[active].scrollIntoView({ block: 'nearest' });
        }
    }
    function option(text, href, index) {
        const item = document.createElement('li');
        item.setAttribute('role', 'presentation');
        const link = document.createElement('a');
        link.textContent = text;
        link.href = href;
        link.id = panel.id + '-option-' + index;
        link.tabIndex = -1;
        link.setAttribute('role', 'option');
        link.setAttribute('aria-selected', 'false');
        item.append(link);
        list.append(item);
    }
    function schedule(event) {
        close();
        if (event?.isComposing) return;
        const query = input.value.trim();
        if (query.length < 2) { announcement.textContent = ''; return; }
        const current = sequence;
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(input.dataset.searchSuggest, location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('currency', form.elements.currency?.value ?? 'PKR');
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error('Search unavailable');
                const data = await response.json();
                if (current !== sequence || document.activeElement !== input) return;
                list.replaceChildren();
                data.items.forEach((item, i) => option(item.name, item.url, i));
                const results = new URL(form.action, location.origin);
                results.search = new URLSearchParams(new FormData(form)).toString();
                results.searchParams.delete('page');
                option('Show results for "' + query + '"', results.href, data.items.length);
                message.textContent = data.items.length ? 'Product suggestions from the store. Use arrow keys and Enter to choose.' : 'No product suggestions. Try another word or view search results.';
                announcement.textContent = data.items.length + ' product suggestions available.';
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            } catch (error) {
                if (error.name !== 'AbortError' && current === sequence) announcement.textContent = 'Suggestions are unavailable. Press Enter to search.';
            }
        }, 250);
    }
    input.addEventListener('input', schedule);
    input.addEventListener('compositionend', schedule);
    input.addEventListener('focus', schedule);
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); event.stopPropagation(); close(); return; }
        if (panel.hidden) return;
        const links = [...list.querySelectorAll('[role=option]')];
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            choose((active + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            location.assign(links[active].href);
        } else if (event.key === 'Tab') close();
    });
    root.addEventListener('focusout', () => requestAnimationFrame(() => { if (!root.contains(document.activeElement)) close(); }));
    document.addEventListener('pointerdown', event => { if (!root.contains(event.target)) close(); });
    form.addEventListener('submit', close);
    schedule();
}
