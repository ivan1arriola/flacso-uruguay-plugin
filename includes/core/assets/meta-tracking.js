(function (window, document) {
    "use strict";

    var config = window.flacsoMetaConfig || {};

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

        try {
            var url = new URL(window.location.href);
            var fbclid = (url.searchParams.get("fbclid") || "").trim();
            if (!fbclid) {
                return "";
            }

            current = "fb.1." + Date.now() + "." + fbclid;
            setCookie("_fbc", current, 90 * 24 * 60 * 60);
            return current;
        } catch (error) {
            return "";
        }
    }

    function normalizeParams(params) {
        if (!params || typeof params !== "object" || Array.isArray(params)) {
            return {};
        }

        return params;
    }

    function sendServerEvent(eventType, eventName, params, eventId) {
        if (!config.capiEnabled || !config.ajaxUrl || !config.ajaxAction) {
            return;
        }

        var body = new URLSearchParams();
        body.append("action", config.ajaxAction);
        body.append("event_type", eventType);
        body.append("event_name", eventName);
        body.append("event_id", eventId);
        body.append("event_source_url", window.location.href);
        body.append("params", JSON.stringify(params || {}));

        var fbp = getCookie("_fbp");
        var fbc = ensureFbcCookie() || getCookie("_fbc");

        if (fbp) {
            body.append("fbp", fbp);
        }

        if (fbc) {
            body.append("fbc", fbc);
        }

        if (navigator.sendBeacon) {
            try {
                if (navigator.sendBeacon(config.ajaxUrl, body)) {
                    return;
                }
            } catch (error) {
                if (config.debug && window.console && typeof window.console.warn === "function") {
                    console.warn("[FLACSO Meta] sendBeacon falló", error);
                }
            }
        }

        if (typeof window.fetch === "function") {
            window.fetch(config.ajaxUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: body.toString(),
                credentials: "same-origin",
                keepalive: true
            }).catch(function (error) {
                if (config.debug && window.console && typeof window.console.warn === "function") {
                    console.warn("[FLACSO Meta] fetch falló", error);
                }
            });
        }
    }

    function ensurePixelBootstrap() {
        if (!config.enabled || !config.pixelId) {
            return;
        }

        ensureFbcCookie();

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

        if (config.enabled && typeof window.fbq === "function") {
            try {
                if (eventType === "trackCustom") {
                    window.fbq("trackCustom", eventName, params, options);
                } else {
                    window.fbq("track", eventName, params, options);
                }
            } catch (error) {
                if (config.debug && window.console && typeof window.console.warn === "function") {
                    console.warn("[FLACSO Meta] fbq falló", error);
                }
            }
        }

        sendServerEvent(eventType, eventName, params, eventId);

        return eventId;
    }

    window.flacsoMetaTrack = function (eventName, params, options) {
        return track("track", eventName, params, options);
    };

    window.flacsoMetaTrackCustom = function (eventName, params, options) {
        return track("trackCustom", eventName, params, options);
    };

    if (!config.enabled || !config.trackPageView) {
        return;
    }

    if (!window.__flacsoMetaPageViewSent) {
        window.__flacsoMetaPageViewSent = true;
        window.flacsoMetaTrack("PageView", {});
    }
})(window, document);
