(function () {
  'use strict';

  var cfg = window.flacsoUtmConfig || {};
  var FORM_HOST = cfg.formHost || 'preinscripciones.flacso.edu.uy';
  var KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'fbclid'];
  var STORE_KEY = 'flacso_utm';
  var TTL_MS = 30 * 24 * 60 * 60 * 1000; // La campaña que trajo a la persona vale 30 días.

  // Sin cookies: localStorage sólo vive en este navegador y no viaja al servidor.
  function read() {
    try {
      var saved = JSON.parse(window.localStorage.getItem(STORE_KEY) || 'null');
      return saved && saved.exp > Date.now() ? saved.utm : {};
    } catch (e) {
      return {};
    }
  }

  function capture() {
    var params = new URLSearchParams(window.location.search);
    var found = {};
    KEYS.forEach(function (key) {
      var value = (params.get(key) || '').trim().slice(0, 200);
      if (value) found[key] = value;
    });
    if (!Object.keys(found).length) return read();
    // utm_id/fbclid solos (clic orgánico de Meta) no deben borrar la campaña guardada.
    var campaign = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].some(function (key) { return found[key]; });
    var next = campaign ? found : Object.assign({}, read(), found);
    try {
      window.localStorage.setItem(STORE_KEY, JSON.stringify({ utm: next, exp: Date.now() + TTL_MS }));
    } catch (e) { /* storage bloqueado: sólo esta página conserva el UTM */ }
    return next;
  }

  var utm = capture();
  if (!Object.keys(utm).length) return;

  function decorate(link) {
    if (!link || !link.href || link.hostname !== FORM_HOST) return;
    var url = new URL(link.href);
    Object.keys(utm).forEach(function (key) {
      if (!url.searchParams.has(key)) url.searchParams.set(key, utm[key]); // un UTM puesto a mano en el link gana
    });
    link.href = url.toString();
  }

  document.querySelectorAll('a[href]').forEach(decorate);

  // Cubre links creados después de cargar la página: se completan justo antes de usarlos.
  ['pointerdown', 'click', 'focusin'].forEach(function (type) {
    document.addEventListener(type, function (event) {
      var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
      decorate(link);
    }, true);
  });
})();
