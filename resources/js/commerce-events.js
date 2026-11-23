const config = JSON.parse(document.querySelector('#commerce-events').textContent);
const banner = document.querySelector('[data-analytics-consent]');
const read = key => { try { return localStorage.getItem(key); } catch { return null; } };
const write = (key, value) => { try { localStorage.setItem(key, value); } catch { /* Browser storage may be disabled. */ } };
const blocked = navigator.globalPrivacyControl || navigator.doNotTrack === '1';
let consent = !blocked && read('sf-analytics-consent') === 'yes';
let initialized = false;
const queue = [...config.events];
const sent = new Set();
function initialize() {
    if (initialized || !consent) return;
    initialized = true;
    if (config.ga4) {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', config.ga4, {send_page_view:false, page_location:config.page, page_title:config.title, page_referrer:location.origin, allow_google_signals:false});
        const script = document.createElement('script'); script.async = true; script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(config.ga4)}`; document.head.append(script);
    }
    if (config.meta) {
        window.fbq = window.fbq || function () { if (window.fbq.callMethod) window.fbq.callMethod.apply(window.fbq, arguments); else window.fbq.queue.push(arguments); };
        window.fbq.queue = window.fbq.queue || []; window.fbq.loaded = true; window.fbq.version = '2.0';
        window.fbq('set', 'autoConfig', false, config.meta);
        window.fbq('init', config.meta);
        const script = document.createElement('script'); script.async = true; script.src = 'https://connect.facebook.net/en_US/fbevents.js'; document.head.append(script);
    }
}
function emit(event) {
    if (!event || !consent) return;
    initialize();
    const {event:name, ...data} = event;
    if (!['view_item','add_to_cart','begin_checkout','purchase'].includes(name)) return;
    const id = name === 'purchase' ? `sf-purchase-${data.transaction_id}` : null;
    if (id && (sent.has(id) || read(id))) return;
    if (config.ga4) window.gtag('event', name, {...data, send_to:config.ga4, page_location:config.page, page_title:config.title, page_referrer:location.origin});
    if (config.meta) window.fbq('trackSingle', config.meta, {view_item:'ViewContent',add_to_cart:'AddToCart',begin_checkout:'InitiateCheckout',purchase:'Purchase'}[name], {currency:data.currency, value:data.value, content_type:'product', content_ids:data.items.map(item => item.item_id), contents:data.items.map(item => ({id:item.item_id, quantity:item.quantity, item_price:item.price}))}, data.transaction_id ? {eventID:data.transaction_id} : {});
    if (id && (config.ga4 || config.meta)) { sent.add(id); write(id, 'sent'); }
}
function flush() { if (consent) { queue.splice(0).forEach(emit); } }
document.addEventListener('sf:commerce', event => { if (consent) emit(event.detail); });
document.querySelector('[data-consent-allow]').addEventListener('click', () => { consent = !blocked; write('sf-analytics-consent', consent ? 'yes':'no'); banner.hidden = true; flush(); });
document.querySelector('[data-consent-deny]').addEventListener('click', () => { consent = false; write('sf-analytics-consent','no'); banner.hidden = true; if (initialized) location.reload(); });
document.querySelector('[data-consent-settings]').addEventListener('click', () => { banner.hidden = false; });
if (!blocked && !read('sf-analytics-consent') && (config.ga4 || config.meta)) banner.hidden = false;
flush();
