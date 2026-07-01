(function (window, document) {
    "use strict";

    if (window.__flacsoGA4EventsBooted) {
        return;
    }
    window.__flacsoGA4EventsBooted = true;

    var config = window.flacsoGA4Config || {};
    var startedForms = {};

    function safeText(value) {
        if (value === null || value === undefined) {
            return "";
        }
        return String(value).trim();
    }

    function normalizeEventName(value) {
        var text = safeText(value)
            .toLowerCase()
            .replace(/[^a-z0-9_]+/g, "_")
            .replace(/^_+|_+$/g, "");

        return text ? text.slice(0, 40) : "flacso_event";
    }

    function normalizeParams(params) {
        var normalized = {};
        var key;
        var value;

        if (!params || typeof params !== "object" || Array.isArray(params)) {
            return normalized;
        }

        for (key in params) {
            if (!Object.prototype.hasOwnProperty.call(params, key)) {
                continue;
            }

            value = params[key];
            if (value === null || value === undefined || typeof value === "function") {
                continue;
            }

            if (typeof value === "string") {
                value = value.trim();
                if (!value) {
                    continue;
                }
                normalized[key] = value.slice(0, 500);
            } else if (typeof value === "number" || typeof value === "boolean") {
                normalized[key] = value;
            } else {
                try {
                    normalized[key] = JSON.stringify(value).slice(0, 500);
                } catch (error) {}
            }
        }

        return normalized;
    }

    function currentPageParams() {
        return {
            page_title: document.title || "",
            page_location: window.location.href,
            site_area: safeText(config.siteArea || "flacso_wordpress")
        };
    }

    function trackGA4(eventName, params) {
        var normalizedName = normalizeEventName(eventName);
        var payload = Object.assign({}, currentPageParams(), normalizeParams(params));

        if (typeof window.gtag === "function") {
            try {
                window.gtag("event", normalizedName, payload);
                return true;
            } catch (error) {}
        }

        if (Array.isArray(window.dataLayer)) {
            try {
                window.dataLayer.push(Object.assign({ event: normalizedName }, payload));
                return true;
            } catch (error) {}
        }

        return false;
    }

    function getClosestLink(target) {
        return target && typeof target.closest === "function" ? target.closest("a[href]") : null;
    }

    function getLinkText(link) {
        return safeText(link.getAttribute("aria-label") || link.textContent || link.title || link.href);
    }

    function isDownloadUrl(href) {
        return /\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip)(\?|#|$)/i.test(href || "");
    }

    function trackOfferClick(link) {
        var offerTitle = safeText(link.getAttribute("data-offer-title") || link.getAttribute("data-flacso-title") || getLinkText(link));
        var offerStatus = safeText(link.getAttribute("data-offer-status"));
        var termTitle = safeText(link.getAttribute("data-offer-type") || link.getAttribute("data-flacso-category") || config.termTitle);

        trackGA4("oferta_academica_click", {
            event_category: "oferta_academica",
            event_label: offerTitle,
            oferta_nombre: offerTitle,
            oferta_estado: offerStatus,
            tipo_oferta: termTitle,
            destination_url: link.href
        });
    }

    function trackDownload(link) {
        var href = link.href || "";
        var fileName = href.split("/").pop().split("?")[0].split("#")[0];

        trackGA4("file_download", {
            event_category: "documento",
            event_label: getLinkText(link),
            file_name: decodeURIComponent(fileName || "documento"),
            file_extension: (fileName.split(".").pop() || "").toLowerCase(),
            link_url: href
        });
    }

    function trackContactClick(link) {
        var href = link.getAttribute("href") || "";
        var method = href.indexOf("mailto:") === 0 ? "email" : "phone";

        trackGA4("contact_click", {
            event_category: "contacto",
            contact_method: method,
            event_label: getLinkText(link),
            link_url: href
        });
    }

    function trackPreinscriptionClick(link) {
        trackGA4("preinscripcion_click", {
            event_category: "preinscripcion",
            event_label: getLinkText(link),
            destination_url: link.href
        });
    }

    function trackFormStart(form) {
        var formId = safeText(form.id || form.getAttribute("name") || form.className || "formulario");
        if (startedForms[formId]) {
            return;
        }
        startedForms[formId] = true;

        trackGA4("form_start", {
            event_category: "formulario",
            form_id: formId,
            form_name: safeText(form.getAttribute("data-form-name") || form.getAttribute("aria-label") || formId)
        });
    }

    function trackFormSubmitAttempt(form) {
        var formId = safeText(form.id || form.getAttribute("name") || form.className || "formulario");

        trackGA4("form_submit_attempt", {
            event_category: "formulario",
            form_id: formId,
            form_name: safeText(form.getAttribute("data-form-name") || form.getAttribute("aria-label") || formId)
        });
    }

    window.flacsoTrackGA4 = trackGA4;

    window.flacsoTrackGA4FormStep = function (stepName, params) {
        trackGA4("preinscripcion_step", Object.assign({
            event_category: "preinscripcion",
            step_name: safeText(stepName)
        }, normalizeParams(params)));
    };

    document.addEventListener("click", function (event) {
        var link = getClosestLink(event.target);
        var manualTarget = event.target && typeof event.target.closest === "function"
            ? event.target.closest("[data-flacso-ga4-event]")
            : null;
        var eventName;
        var params = {};
        var attr;

        if (manualTarget) {
            eventName = manualTarget.getAttribute("data-flacso-ga4-event");
            Array.prototype.slice.call(manualTarget.attributes || []).forEach(function (item) {
                if (item && item.name.indexOf("data-flacso-ga4-param-") === 0) {
                    attr = item.name.replace("data-flacso-ga4-param-", "").replace(/-/g, "_");
                    params[attr] = item.value;
                }
            });
            trackGA4(eventName, params);
        }

        if (!link) {
            return;
        }

        if (link.hasAttribute("data-flacso-offer-click") && typeof window.flacsoMetaTrackCustom !== "function") {
            trackOfferClick(link);
        }

        if (isDownloadUrl(link.href) || link.classList.contains("flacso-meta-doc-link")) {
            trackDownload(link);
        }

        if (/^(mailto:|tel:)/i.test(link.getAttribute("href") || "")) {
            trackContactClick(link);
        }

        if (/\/preinscripcion\/?(?:[?#].*)?$/i.test(link.pathname || "") || link.href.indexOf("/preinscripcion/") !== -1) {
            trackPreinscriptionClick(link);
        }
    }, true);

    document.addEventListener("focusin", function (event) {
        var form = event.target && typeof event.target.closest === "function" ? event.target.closest("form") : null;
        if (form) {
            trackFormStart(form);
        }
    }, true);

    document.addEventListener("submit", function (event) {
        if (event.target && event.target.tagName === "FORM") {
            trackFormSubmitAttempt(event.target);
        }
    }, true);

    document.addEventListener("flacso:ga4-event", function (event) {
        var detail = event.detail || {};
        trackGA4(detail.eventName || detail.name, detail.params || {});
    });
})(window, document);
