const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync('modules/main-page/includes/flacso-consultas.php', 'utf8');
const callback = source.split('success: function(response) {')[1].split('\n\t\t\t\terror: function(xhr) {')[0].replace(/\},\s*$/, '}');

function scenario(response, throws = false) {
    const events = [];
    const window = { location: { href: 'https://example.org/form' }, flacsoMetaTrack(name, params, options) {
        events.push({ name, params, options });
        if (throws) throw Error('tracker');
    } };
    const context = {
        response, window, URL, console, metaUserData: { em: 'hash' }, submitKey: '123', submitKeyStorageKey: 'key', memorySubmit: null, programa: 'Programa',
        sessionStorage: { setItem() { throw Error('blocked'); }, removeItem() { throw Error('blocked'); } },
        $form: { find(selector) { return { val: () => ({ '[name="id_pagina"]': '1', '[name="url_base"]': 'https://example.org', '[name="url_gracias"]': 'https://example.org/gracias/', '[name="titulo_posgrado"]': 'Programa' }[selector] || ''), map: () => ({ get: () => [] }) }; } },
        buildGraciasUrl: () => 'https://example.org/gracias/', showMessage: () => events.push({ name: 'error' })
    };
    vm.runInNewContext('(function(response) {' + callback + ')(response)', context);
    return { events, href: window.location.href };
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
assert.strictEqual(result.href, 'https://example.org/gracias/');
for (const response of [{ success: false }, { success: true, data: {} }]) {
    result = scenario(response);
    assert.strictEqual(result.events[0].name, 'error');
    assert.strictEqual(result.href, 'https://example.org/form');
}
result = scenario({ success: true, data: { persisted: false } });
assert.strictEqual(result.events.length, 0);
assert.strictEqual(result.href, 'https://example.org/gracias/');
result = scenario({ success: true, data: { persisted: true } }, true);
assert.strictEqual(result.href, 'https://example.org/gracias/');
assert(!source.includes("consultaMetaLeadPending"), 'gracias no vuelve a emitir Lead');
trackerIntegration();
const keyFunctions = source.split('const newSubmitKey = function() {')[1].split('const buildGraciasUrl = function')[0];
const keys = vm.runInNewContext(`(function() { let memorySubmit = null; const submitKeyStorageKey = 'key'; const newSubmitKey = function() {${keyFunctions} return [submitKeyFor('a'), submitKeyFor('a'), submitKeyFor('b')]; })()`, {
    window: { crypto: { randomUUID: (() => { let n = 0; return () => String(++n); })() } },
    sessionStorage: { getItem() { throw Error('blocked'); }, setItem() { throw Error('blocked'); } }, Date, Math, JSON
});
assert.strictEqual(keys[0], keys[1]);
assert.notStrictEqual(keys[1], keys[2]);
console.log('inquiry confirmation client: OK');
