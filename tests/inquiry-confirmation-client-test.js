const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync('modules/main-page/includes/flacso-consultas.php', 'utf8');
const callback = source.split('success: function(response) {')[1].split('\n\t\t\t\terror: function(xhr) {')[0].replace(/\},\s*$/, '}');

function memoryStorage() {
    const map = new Map();
    return { map, getItem: k => (map.has(k) ? map.get(k) : null), setItem: (k, v) => { map.set(k, String(v)); }, removeItem: k => { map.delete(k); } };
}

function scenario(response, throws = false, storage = null) {
    const events = [];
    const delays = [];
    const window = { location: { href: 'https://example.org/form' }, flacsoMetaTrack(name, params, options) {
        events.push({ name, params, options });
        if (throws) throw Error('tracker');
    } };
    const context = {
        response, window, URL, console, metaUserData: { em: 'hash' }, submitKey: '123', submitKeyStorageKey: 'key', memorySubmit: null, programa: 'Programa', offerCodeParams: () => ({ offer_code: 'mesyp', content_ids: ['mesyp'] }),
        sessionStorage: storage || { setItem() { throw Error('blocked'); }, removeItem() { throw Error('blocked'); } },
        setTimeout(fn, ms) { delays.push(ms); fn(); },
        $form: { find(selector) { return { val: () => ({ '[name="id_pagina"]': '1', '[name="url_base"]': 'https://example.org', '[name="url_gracias"]': 'https://example.org/gracias/', '[name="titulo_posgrado"]': 'Programa' }[selector] || ''), map: () => ({ get: () => [] }) }; } },
        buildGraciasUrl: () => 'https://example.org/gracias/', showMessage: () => events.push({ name: 'error' })
    };
    vm.runInNewContext('(function(response) {' + callback + ')(response)', context);
    return { events, href: window.location.href, delays };
}

function trackerIntegration() {
    const calls = [];
    const document = { cookie: '', addEventListener() {} };
    const window = {
        location: { href: 'https://example.org/form', protocol: 'https:', search: '' },
        flacsoMetaConfig: { enabled: true, pixelId: 'pixel', capiEnabled: true, ajaxUrl: '/ajax', ajaxAction: 'relay' },
        __flacsoMetaInitialized: true,
        fbq(...args) { calls.push(['pixel', ...args]); },
        gtag(...args) { calls.push(['ga4', ...args]); },
        fetch(url, options) { calls.push(['capi', url, options]); return new Promise(() => {}); },
        localStorage: { getItem() { throw Error('blocked'); }, setItem() { throw Error('blocked'); } }
    };
    const context = { window, document, navigator: {}, URLSearchParams, console };
    vm.runInNewContext(fs.readFileSync('includes/core/assets/meta-tracking.js', 'utf8'), context);
    window.flacsoMetaTrack('Lead', { content_name: 'Programa' }, { eventID: 'info-request-123', userData: { em: 'hash' } });
    const pixel = calls.find(call => call[0] === 'pixel' && call[1] === 'track');
    const ga4 = calls.find(call => call[0] === 'ga4');
    const capi = calls.find(call => call[0] === 'capi');
    assert.strictEqual(pixel[4].eventID, 'info-request-123');
    assert.strictEqual(ga4[2], 'generate_lead');
    assert(!JSON.stringify(ga4).includes('hash'));
    assert.strictEqual(new URLSearchParams(capi[2].body).get('event_id'), 'info-request-123');
    assert.strictEqual(capi[2].keepalive, true);
}

let result = scenario({ success: true, data: { persisted: true } });
assert.strictEqual(result.events[0].name, 'Lead');
assert.strictEqual(result.events[0].options.eventID, 'info-request-123');
assert.strictEqual(result.events[0].options.userData.em, 'hash');
assert.strictEqual(result.events[0].params.offer_code, 'mesyp');
assert.strictEqual(result.href, 'https://example.org/gracias/');
assert.deepStrictEqual(result.delays, [300], 'fallback difiere la redireccion 300 ms');
for (const response of [{ success: false }, { success: true, data: {} }]) {
    result = scenario(response);
    assert.strictEqual(result.events[0].name, 'error');
    assert.strictEqual(result.href, 'https://example.org/form');
}
result = scenario({ success: true, data: { persisted: false } });
assert.strictEqual(result.events.length, 0);
assert.strictEqual(result.href, 'https://example.org/gracias/');
assert.deepStrictEqual(result.delays, []);
result = scenario({ success: true, data: { persisted: true } }, true);
assert.strictEqual(result.href, 'https://example.org/gracias/');
assert.deepStrictEqual(result.delays, [300]);
// Con storage disponible el Lead se difiere a /gracias.
let storage = memoryStorage();
result = scenario({ success: true, data: { persisted: true, first_offer_id: '7' } }, false, storage);
assert.strictEqual(result.events.length, 0, 'el formulario no dispara Lead si puede diferirlo');
assert.strictEqual(result.href, 'https://example.org/gracias/');
assert.deepStrictEqual(result.delays, [], 'sin fallback la redireccion es inmediata');
const pending = JSON.parse(storage.map.get('flacsoLeadPendiente'));
assert.strictEqual(pending.eventID, 'info-request-123');
assert.strictEqual(pending.userData.em, 'hash');
assert.strictEqual(pending.pid, '7');
storage = memoryStorage();
result = scenario({ success: true, data: { persisted: false } }, false, storage);
assert.strictEqual(result.events.length, 0);
assert(!storage.map.has('flacsoLeadPendiente'), 'persisted:false no guarda Lead');
// Script de /gracias.
const graciasScript = source.split('// flacso-lead-pendiente:')[1].split('// /flacso-lead-pendiente')[0].replace(/^.*/, '');
function graciasRun(storage, search = '?pid=7', hasTracker = true) {
    const events = [];
    const window = hasTracker ? { flacsoMetaTrack(name, params, options) { events.push({ name, params, options }); } } : {};
    const location = { search };
    vm.runInNewContext('(function() {' + graciasScript + '})()', { window, location, URLSearchParams, sessionStorage: storage, Date, JSON });
    return events;
}
storage = memoryStorage();
scenario({ success: true, data: { persisted: true, first_offer_id: '7' } }, false, storage);
let events = graciasRun(storage, '?pid=7');
assert.strictEqual(events.length, 1);
assert.strictEqual(events[0].name, 'Lead');
assert.strictEqual(events[0].options.eventID, 'info-request-123');
assert(!storage.map.has('flacsoLeadPendiente'), 'gracias borra la clave');
assert.strictEqual(graciasRun(storage, '?pid=7').length, 0, 'un reload no repite el Lead');
storage = memoryStorage();
scenario({ success: true, data: { persisted: true, first_offer_id: '7' } }, false, storage);
const expired = JSON.parse(storage.map.get('flacsoLeadPendiente'));
expired.exp = Date.now() - 1;
storage.setItem('flacsoLeadPendiente', JSON.stringify(expired));
assert.strictEqual(graciasRun(storage, '?pid=7').length, 0, 'Lead vencido no se emite');
storage = memoryStorage();
scenario({ success: true, data: { persisted: true, first_offer_id: '7' } }, false, storage);
assert.strictEqual(graciasRun(storage, '?pid=99').length, 0, 'pid distinto no dispara Lead');
assert(storage.map.has('flacsoLeadPendiente'), 'pid distinto no borra la clave');
assert.strictEqual(graciasRun(storage, '?pid=7&tipo=preinscripcion_oferta').length, 0, 'preinscripcion no dispara Lead');
assert(storage.map.has('flacsoLeadPendiente'), 'preinscripcion no borra la clave');
assert.strictEqual(graciasRun(storage, '?pid=7', false).length, 0, 'tracker ausente no dispara Lead');
assert(storage.map.has('flacsoLeadPendiente'), 'tracker ausente no borra la clave');
assert.strictEqual(graciasRun(storage, '?pid=7', true).length, 1, 'con tracker disponible vuelve a disparar');
assert(!storage.map.has('flacsoLeadPendiente'), 'borra la clave al disparar');
trackerIntegration();
const keyFunctions = source.split('const newSubmitKey = function() {')[1].split('const buildGraciasUrl = function')[0];
const keys = vm.runInNewContext(`(function() { let memorySubmit = null; const submitKeyStorageKey = 'key'; const newSubmitKey = function() {${keyFunctions} return [submitKeyFor('a'), submitKeyFor('a'), submitKeyFor('b')]; })()`, {
    window: { crypto: { randomUUID: (() => { let n = 0; return () => String(++n); })() } },
    sessionStorage: { getItem() { throw Error('blocked'); }, setItem() { throw Error('blocked'); } }, Date, Math, JSON
});
assert.strictEqual(keys[0], keys[1]);
assert.notStrictEqual(keys[1], keys[2]);
console.log('inquiry confirmation client: OK');
