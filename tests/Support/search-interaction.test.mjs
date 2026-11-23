import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function setup() {
    class Element {
        constructor(tag = 'div') { this.tag = tag; this.attributes = {}; this.children = []; this.events = {}; this.hidden = true; this.dataset = {}; this.value = ''; }
        setAttribute(key, value) { this.attributes[key] = value; }
        getAttribute(key) { return this.attributes[key]; }
        removeAttribute(key) { delete this.attributes[key]; }
        append(...children) { this.children.push(...children); }
        replaceChildren(...children) { this.children = children; }
        addEventListener(name, callback) { (this.events[name] ??= []).push(callback); }
        fire(name, details = {}) { const event = { target: this, preventDefault() { this.prevented = true; }, stopPropagation() {}, ...details }; for (const callback of this.events[name] ?? []) callback(event); return event; }
        querySelectorAll() { return this.children.flatMap(child => [child, ...child.querySelectorAll()]).filter(child => child.getAttribute('role') === 'option'); }
        scrollIntoView() {}
    }
    const input = new Element('input'), panel = new Element(), list = new Element('ul'), message = new Element(), announcement = new Element(), form = new Element('form'), root = new Element();
    input.setAttribute('aria-controls', 'suggestions');
    input.dataset.searchSuggest = '/shop/suggestions';
    input.form = form;
    form.action = 'http://store.test/collections/winter';
    form.elements = { currency: { value: 'PKR' } };
    panel.id = 'suggestions';
    panel.querySelector = selector => selector === '[role=listbox]' ? list : message;
    root.querySelector = selector => selector === '[data-search-suggest]' ? input : announcement;
    root.contains = target => target === input || target === panel;
    const requests = [], timers = new Map(), navigations = [];
    let timerId = 0;
    const document = { activeElement: input, getElementById: () => panel, createElement: tag => new Element(tag), addEventListener() {} };
    const context = vm.createContext({ document, location: { origin: 'http://store.test', assign: url => navigations.push(url) }, URL, URLSearchParams, AbortController,
        FormData: class { *[Symbol.iterator]() { yield ['q', input.value]; yield ['currency', 'PKR']; yield ['color[]', 'Black']; } },
        setTimeout: (callback, delay) => { assert.equal(delay, 250); timers.set(++timerId, callback); return timerId; }, clearTimeout: id => timers.delete(id), requestAnimationFrame: callback => callback(),
        fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })),
    });
    vm.runInContext(readFileSync(new URL('../../resources/js/search.js', import.meta.url), 'utf8').replace('export function ', 'function '), context);
    vm.runInContext('initSuggestions', context)(root);
    async function response(index, name = 'Black jacket') { requests[index].resolve({ ok: true, json: async () => ({ items: [{ name, url: 'http://store.test/products/black' }] }) }); await new Promise(resolve => setImmediate(resolve)); }
    function type(value) { input.value = value; input.fire('input'); }
    function flush() { for (const [id, callback] of timers) { timers.delete(id); callback(); } }
    return { input, panel, list, message, requests, timers, navigations, response, type, flush };
}

test('typing is debounced; arrow selection and Enter open the exact suggestion', async () => {
    const ui = setup();
    ui.type('b'); assert.equal(ui.timers.size, 0);
    ui.type('bl'); ui.type('black'); assert.equal(ui.timers.size, 1);
    ui.flush(); assert.equal(ui.requests.length, 1);
    assert.equal(ui.requests[0].url.searchParams.get('q'), 'black');
    await ui.response(0);
    assert.equal(ui.input.getAttribute('aria-expanded'), 'true');
    ui.input.fire('keydown', { key: 'ArrowDown' });
    assert.equal(ui.input.getAttribute('aria-activedescendant'), 'suggestions-option-0');
    assert.equal(ui.input.fire('keydown', { key: 'Enter' }).prevented, true);
    assert.deepEqual(ui.navigations, ['http://store.test/products/black']);
});
test('late responses cannot replace newer results and Escape dismisses locally', async () => {
    const ui = setup();
    ui.type('black'); ui.flush();
    ui.type('brown'); ui.flush();
    assert.equal(ui.requests[0].options.signal.aborted, true);
    await ui.response(1, 'Brown jacket');
    await ui.response(0, 'Outdated black jacket');
    assert.equal(ui.list.querySelectorAll()[0].textContent, 'Brown jacket');
    ui.input.fire('keydown', { key: 'Escape' });
    assert.equal(ui.panel.hidden, true);
    assert.equal(ui.input.getAttribute('aria-expanded'), 'false');
    assert.equal(ui.requests.length, 2);
});
test('the all-results link retains collection filters and plain Enter still submits', async () => {
    const ui = setup();
    ui.type('black'); ui.flush(); await ui.response(0);
    const url = new URL(ui.list.querySelectorAll()[1].href);
    assert.equal(url.pathname, '/collections/winter');
    assert.equal(url.searchParams.get('color[]'), 'Black');
    assert.equal(url.searchParams.get('q'), 'black');
    assert.equal(ui.input.fire('keydown', { key: 'Enter' }).prevented, undefined);
    assert.equal(ui.navigations.length, 0);
});
