(function (window, document) {
    "use strict";

    var config = window.flacsoMetaConfig || {};
    var queueStorageKey = "flacso_meta_pending_events";
    var queueMaxItems = 100;
    var queueTtlMs = 7 * 24 * 60 * 60 * 1000;
    var inflightEvents = {};

    function generateEventId() {
        if (window.crypto && typeof window.crypto.randomUUID === "function") {
            return window.crypto.randomUUID();
        }

        return "flacso-" + Date.now() + "-" + Math.random().toString(16).slice(2);
    }

    function getCookie(name) {
        var pattern = new RegExp("(?:^|; )" + name.replace(/[$()*+.?[\\\]^{|}]/g, "\\$&") + "=([^;]*)");
        var match = document.cookie.match(pattern);
        return match ? decodeURIComponent(match[1]) : "";
    }

    function setCookie(name, value, maxAgeSeconds) {
        var parts = [
            name + "=" + encodeURIComponent(value),
            "path=/",
            "SameSite=Lax"
        ];

        if (window.location.protocol === "https:") {
            parts.push("Secure");
        }

        if (typeof maxAgeSeconds === "number" && maxAgeSeconds > 0) {
            parts.push("max-age=" + String(Math.round(maxAgeSeconds)));
        }

        document.cookie = parts.join("; ");
    }

    function ensureFbcCookie() {
        var current = getCookie("_fbc");
        if (current) {
            return current;
        }

        return "";
    }

    function ensureExternalIdCookie() {
        var current = getCookie("flacso_external_id");
        if (current) {
            return current;
        }

        var newId;
        if (window.crypto && typeof window.crypto.randomUUID === "function") {
            newId = window.crypto.randomUUID();
        } else {
            newId = "flacso-" + Date.now() + "-" + Math.random().toString(16).slice(2) + "-" + Math.random().toString(36).slice(2, 10);
        }

        setCookie("flacso_external_id", newId, 365 * 24 * 60 * 60);
        return newId;
    }

    function normalizeParams(params) {
        if (!params || typeof params !== "object" || Array.isArray(params)) {
            return {};
        }

        return params;
    }

    function nowTs() {
        return Date.now ? Date.now() : (new Date()).getTime();
    }

    function canUseStorage() {
        try {
            return !!window.localStorage;
        } catch (error) {
            return false;
        }
    }

    function pruneQueue(queue) {
        var cutoff = nowTs() - queueTtlMs;
        var filtered = [];
        var seen = {};
        var i;
        var item;

        if (!Array.isArray(queue)) {
            return [];
        }

        for (i = queue.length - 1; i >= 0; i -= 1) {
            item = queue[i];
            if (!item || typeof item !== "object" || !item.eventId || seen[item.eventId]) {
                continue;
            }

            if (item.createdAt && item.createdAt < cutoff) {
                continue;
            }

            seen[item.eventId] = true;
            filtered.unshift(item);
        }

        if (filtered.length > queueMaxItems) {
            filtered = filtered.slice(filtered.length - queueMaxItems);
        }

        return filtered;
    }

    function readQueue() {
        var raw;

        if (!canUseStorage()) {
            return [];
        }

        try {
            raw = window.localStorage.getItem(queueStorageKey);
            return pruneQueue(raw ? JSON.parse(raw) : []);
        } catch (error) {
            return [];
        }
    }

    function writeQueue(queue) {
        var nextQueue = pruneQueue(queue);

        if (!canUseStorage()) {
            return nextQueue;
        }

        try {
            if (nextQueue.length) {
                window.localStorage.setItem(queueStorageKey, JSON.stringify(nextQueue));
            } else {
                window.localStorage.removeItem(queueStorageKey);
            }
        } catch (error) {}

        return nextQueue;
    }

    function upsertQueuedEvent(payload) {
        var queue;
        var i;
        var existing;

        if (!payload || !payload.eventId) {
            return payload;
        }

        queue = readQueue();

        for (i = 0; i < queue.length; i += 1) {
            if (queue[i] && queue[i].eventId === payload.eventId) {
                existing = queue[i];
                queue[i] = Object.assign({}, existing, payload, {
                    createdAt: existing.createdAt || payload.createdAt || nowTs(),
                    attempts: existing.attempts || 0,
                    lastAttemptAt: existing.lastAttemptAt || 0
                });
                writeQueue(queue);
                return queue[i];
            }
        }

        payload.createdAt = payload.createdAt || nowTs();
        payload.attempts = payload.attempts || 0;
        payload.lastAttemptAt = payload.lastAttemptAt || 0;
        queue.push(payload);
        writeQueue(queue);
        return payload;
    }

    function removeQueuedEvent(eventId) {
        var queue;

        if (!eventId) {
            return;
        }

        queue = readQueue().filter(function (item) {
            return item && item.eventId !== eventId;
        });

        writeQueue(queue);
    }

    function markQueuedEventAttempt(eventId) {
        var queue = readQueue();
        var i;

        for (i = 0; i < queue.length; i += 1) {
            if (queue[i] && queue[i].eventId === eventId) {
                queue[i].attempts = (queue[i].attempts || 0) + 1;
                queue[i].lastAttemptAt = nowTs();
                break;
            }
        }

        writeQueue(queue);
    }

    function buildRequestBody(payload) {
        var body = new URLSearchParams();

        body.append("action", config.ajaxAction);
        body.append("event_type", payload.eventType);
        body.append("event_name", payload.eventName);
        body.append("event_id", payload.eventId);
        body.append("event_source_url", payload.eventSourceUrl || window.location.href);
        body.append("params", JSON.stringify(payload.params || {}));

        if (payload.fbp) {
            body.append("fbp", payload.fbp);
        }

        if (payload.fbc) {
            body.append("fbc", payload.fbc);
        }

        if (payload.externalId) {
            body.append("external_id", payload.externalId);
        }

        if (payload.userData && typeof payload.userData === "object") {
            body.append("user_data", JSON.stringify(payload.userData));
        }

        return body;
    }

    function trySendBeacon(body) {
        if (!navigator.sendBeacon) {
            return false;
        }

        try {
            return navigator.sendBeacon(config.ajaxUrl, body);
        } catch (error) {
            if (config.debug && window.console && typeof window.console.warn === "function") {
                console.warn("[FLACSO Meta] sendBeacon falló", error);
            }
            return false;
        }
    }

    function transportQueuedEvent(payload, allowBeaconFallback) {
        var body;
        var request;

        if (!payload || !payload.eventId || inflightEvents[payload.eventId]) {
            return inflightEvents[payload && payload.eventId] || null;
        }

        if (!config.capiEnabled || !config.ajaxUrl || !config.ajaxAction) {
            removeQueuedEvent(payload.eventId);
            return null;
        }

        body = buildRequestBody(payload);
        markQueuedEventAttempt(payload.eventId);

        if (typeof window.fetch !== "function") {
            if (allowBeaconFallback) {
                trySendBeacon(body);
            }
            return null;
        }

        request = window.fetch(config.ajaxUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: body.toString(),
            credentials: "same-origin",
            keepalive: true
        }).then(function (response) {
            if (!response || !response.ok) {
                throw new Error("HTTP " + (response ? response.status : 0));
            }

            removeQueuedEvent(payload.eventId);
            return true;
        }).catch(function (error) {
            if (config.debug && window.console && typeof window.console.warn === "function") {
                console.warn("[FLACSO Meta] fetch falló", error);
            }

            if (allowBeaconFallback) {
                trySendBeacon(body);
            }

            return false;
        });

        inflightEvents[payload.eventId] = request.then(function (result) {
            delete inflightEvents[payload.eventId];
            return result;
        }, function (error) {
            delete inflightEvents[payload.eventId];
            throw error;
        });

        return inflightEvents[payload.eventId];
    }

    function flushPendingServerEvents() {
        var queue;
        var i;

        if (!config.capiEnabled || !config.ajaxUrl || !config.ajaxAction || typeof window.fetch !== "function") {
            return;
        }

        queue = readQueue();
        for (i = 0; i < queue.length; i += 1) {
            if (queue[i] && queue[i].eventId && !inflightEvents[queue[i].eventId]) {
                transportQueuedEvent(queue[i], false);
            }
        }
    }

    function sendServerEvent(eventType, eventName, params, eventId, userData) {
        var payload;
        var fbp;
        var fbc;

        if (!config.capiEnabled || !config.ajaxUrl || !config.ajaxAction) {
            return;
        }

        fbp = getCookie("_fbp");
        fbc = ensureFbcCookie() || getCookie("_fbc");

        payload = upsertQueuedEvent({
            eventType: eventType,
            eventName: eventName,
            eventId: eventId,
            eventSourceUrl: window.location.href,
            params: normalizeParams(params),
            fbp: fbp || "",
            fbc: fbc || "",
            userData: userData && typeof userData === "object" ? Object.assign({}, userData) : null,
            externalId: ensureExternalIdCookie()
        });

        transportQueuedEvent(payload, true);
    }

    function ensurePixelBootstrap() {
        if (!config.enabled || !config.pixelId) {
            return;
        }

        if (typeof window.fbq !== "function") {
            (function (f, b, e, v, n, t, s) {
                if (f.fbq) {
                    return;
                }

                n = f.fbq = function () {
                    if (n.callMethod) {
                        n.callMethod.apply(n, arguments);
                    } else {
                        n.queue.push(arguments);
                    }
                };

                if (!f._fbq) {
                    f._fbq = n;
                }

                n.push = n;
                n.loaded = true;
                n.version = "2.0";
                n.queue = [];
                t = b.createElement(e);
                t.async = true;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s);
            })(window, document, "script", "https://connect.facebook.net/en_US/fbevents.js");
        }

        if (!window.__flacsoMetaInitialized) {
            try {
                window.fbq("init", config.pixelId);
                window.__flacsoMetaInitialized = true;
            } catch (error) {
                if (config.debug && window.console && typeof window.console.warn === "function") {
                    console.warn("[FLACSO Meta] init falló", error);
                }
            }
        }
    }

    function trackGA4Equivalent(eventType, eventName, params) {
        var normalizedName = String(eventName || "");
        var ga4Name = normalizedName;
        var payload = normalizeParams(params);
        var category = String(payload.content_category || payload.event_category || "").toLowerCase();

        if (normalizedName === "PageView") {
            ga4Name = "page_view";
        } else if (normalizedName === "ViewContent") {
            ga4Name = category.indexOf("listado") !== -1 ? "view_item_list" : "view_item";
        } else if (normalizedName === "Lead" || normalizedName === "Contact") {
            ga4Name = "generate_lead";
        } else if (normalizedName === "SubmitApplication") {
            ga4Name = "submit_application";
        } else if (normalizedName === "InitiateCheckout") {
            ga4Name = "begin_checkout";
        } else if (normalizedName === "OfertaAcademicaClick") {
            ga4Name = "select_item";
        } else if (normalizedName === "ConvenioClick") {
            ga4Name = "convenio_click";
        } else if (normalizedName === "SeminarioClick") {
            ga4Name = "seminario_click";
        } else if (normalizedName === "InfoRequestFormView") {
            ga4Name = "form_view";
        } else {
            ga4Name = normalizedName
                .toLowerCase()
                .replace(/[^a-z0-9_]+/g, "_")
                .replace(/^_+|_+$/g, "");
        }

        payload.meta_event_name = normalizedName;
        payload.meta_event_type = eventType;

        if (typeof window.flacsoTrackGA4 === "function") {
            try {
                window.flacsoTrackGA4(ga4Name, payload);
                return;
            } catch (error) {}
        }

        if (typeof window.gtag === "function") {
            try {
                window.gtag("event", ga4Name, payload);
                return;
            } catch (error) {}
        }

        if (Array.isArray(window.dataLayer)) {
            try {
                window.dataLayer.push(Object.assign({ event: ga4Name }, payload));
            } catch (error) {}
        }
    }

    function track(eventType, eventName, params, options) {
        if (!eventName || typeof eventName !== "string") {
            return null;
        }

        params = normalizeParams(params);
        options = options && typeof options === "object" ? Object.assign({}, options) : {};

        if (!options.eventID && !options.eventId) {
            options.eventID = generateEventId();
        } else if (!options.eventID && options.eventId) {
            options.eventID = options.eventId;
        }

        var eventId = options.eventID;

        ensurePixelBootstrap();

        var userData = options.userData && typeof options.userData === "object" ? options.userData : null;

        var pixelParams = Object.assign({}, params);
        if (userData) {
            delete pixelParams.em;
            delete pixelParams.ph;
            delete pixelParams.fn;
            delete pixelParams.ln;
            delete pixelParams.db;
            delete pixelParams.country;
            delete pixelParams.ge;
        }

        if (config.enabled && typeof window.fbq === "function") {
            try {
                if (eventType === "trackCustom") {
                    window.fbq("trackCustom", eventName, pixelParams, options);
                } else {
                    window.fbq("track", eventName, pixelParams, options);
                }
            } catch (error) {
                if (config.debug && window.console && typeof window.console.warn === "function") {
                    console.warn("[FLACSO Meta] fbq falló", error);
                }
            }
        }

        trackGA4Equivalent(eventType, eventName, params);

        sendServerEvent(eventType, eventName, params, eventId, userData);

        return eventId;
    }

    window.flacsoMetaTrack = function (eventName, params, options) {
        return track("track", eventName, params, options);
    };

    window.flacsoMetaTrackCustom = function (eventName, params, options) {
        return track("trackCustom", eventName, params, options);
    };

    if (typeof window.addEventListener === "function") {
        window.addEventListener("online", flushPendingServerEvents);
        window.addEventListener("pageshow", flushPendingServerEvents);
    }

    if (document && typeof document.addEventListener === "function") {
        document.addEventListener("visibilitychange", function () {
            if (document.visibilityState === "visible") {
                flushPendingServerEvents();
            }
        });
    }

    flushPendingServerEvents();

    if (!config.enabled || !config.trackPageView) {
        return;
    }

    if (!window.__flacsoMetaPageViewSent) {
        window.__flacsoMetaPageViewSent = true;
        window.flacsoMetaTrack("PageView", {});
    }
})(window, document);
