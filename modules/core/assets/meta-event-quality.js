(function (window, document) {
    "use strict";

    if (window.__flacsoMetaEventQualityBooted) {
        return;
    }
    window.__flacsoMetaEventQualityBooted = true;

    var USER_DATA_KEY = "flacso_meta_user_data";
    var USER_DATA_TTL_MS = 30 * 60 * 1000;

    function setCookie(name, value, maxAgeSeconds) {
        var parts = [name + "=" + encodeURIComponent(value), "path=/", "SameSite=Lax"];
        if (window.location.protocol === "https:") parts.push("Secure");
        if (maxAgeSeconds) parts.push("max-age=" + String(maxAgeSeconds));
        document.cookie = parts.join("; ");
    }

    function getCookie(name) {
        var safe = name.replace(/[$()*+.?[\]^{|}]/g, "\\$&");
        var match = document.cookie.match(new RegExp("(?:^|; )" + safe + "=([^;]*)"));
        return match ? decodeURIComponent(match[1]) : "";
    }

    function persistFbc() {
        try {
            var params = new URLSearchParams(window.location.search || "");
            var fbclid = String(params.get("fbclid") || "").trim();
            if (!fbclid || getCookie("_fbc")) return;
            var fbc = "fb.1." + String(Date.now()) + "." + fbclid;
            setCookie("_fbc", fbc, 90 * 24 * 60 * 60);
        } catch (error) {}
    }

    function fieldValue(form, selectors) {
        for (var i = 0; i < selectors.length; i += 1) {
            var node = form.querySelector(selectors[i]);
            if (!node) continue;
            if (node.type === "radio") {
                node = form.querySelector(selectors[i] + ":checked") || node;
            }
            var value = String(node.value || "").trim();
            if (value) return value;
        }
        return "";
    }

    function collectUserData(form) {
        var fullName = fieldValue(form, ["[name='nombre']", "[name='name']"]);
        var fullParts = fullName.split(/\s+/).filter(Boolean);
        var firstName = fieldValue(form, ["[name='nombre1']", "[name='first_name']", "[name='firstname']"]) || (fullParts[0] || "");
        var lastName = fieldValue(form, ["[name='apellido1']", "[name='last_name']", "[name='lastname']"]) || (fullParts.length > 1 ? fullParts[fullParts.length - 1] : "");
        var phone = fieldValue(form, ["[name='celular_e164']", "[name='telefono']", "[name='phone']", "[name='celular']"]);
        var country = fieldValue(form, ["[name='pais_residencia']", "[name='pais']", "[name='country']"]);
        var gender = fieldValue(form, ["[name='genero']:checked", "[name='genero']", "[name='gender']:checked", "[name='gender']"]);

        return {
            em: fieldValue(form, ["[name='correo']", "[name='email']"]),
            ph: phone,
            fn: firstName,
            ln: lastName,
            country: country,
            db: fieldValue(form, ["[name='fecha_nacimiento']", "[name='birth_date']", "[name='birthday']"]),
            ge: gender
        };
    }

    function saveUserData(userData) {
        try {
            var cleaned = {};
            Object.keys(userData || {}).forEach(function (key) {
                var value = String(userData[key] || "").trim();
                if (value) cleaned[key] = value;
            });
            if (!Object.keys(cleaned).length) return;
            window.sessionStorage.setItem(USER_DATA_KEY, JSON.stringify({ createdAt: Date.now(), data: cleaned }));
        } catch (error) {}
    }

    function readUserData() {
        try {
            var raw = window.sessionStorage.getItem(USER_DATA_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.data || (Date.now() - Number(parsed.createdAt || 0)) > USER_DATA_TTL_MS) {
                window.sessionStorage.removeItem(USER_DATA_KEY);
                return null;
            }
            return parsed.data;
        } catch (error) {
            return null;
        }
    }

    function normalizeMonetaryParams(eventName, params) {
        var payload = Object.assign({}, params || {});
        if (eventName !== "Lead" && eventName !== "SubmitApplication") return payload;

        var numericValue = Number(payload.value);
        payload.value = Number.isFinite(numericValue) && numericValue >= 0 ? numericValue : 0;

        var currency = String(payload.currency || "").trim().toUpperCase();
        payload.currency = /^[A-Z]{3}$/.test(currency) ? currency : "USD";
        return payload;
    }

    function wrapTracker(name) {
        var original = window[name];
        if (typeof original !== "function" || original.__flacsoQualityWrapped) return;

        var wrapped = function (eventName, params, options) {
            var nextParams = normalizeMonetaryParams(String(eventName || ""), params);
            var nextOptions = options && typeof options === "object" ? Object.assign({}, options) : {};
            if ((eventName === "Lead" || eventName === "SubmitApplication") && !nextOptions.userData) {
                var cached = readUserData();
                if (cached) nextOptions.userData = cached;
            }
            return original.call(window, eventName, nextParams, nextOptions);
        };
        wrapped.__flacsoQualityWrapped = true;
        wrapped.__flacsoOriginal = original;
        window[name] = wrapped;
    }

    persistFbc();

    document.addEventListener("submit", function (event) {
        var form = event.target;
        if (!form || form.tagName !== "FORM") return;
        saveUserData(collectUserData(form));
    }, true);

    wrapTracker("flacsoMetaTrack");
    wrapTracker("flacsoMetaTrackCustom");
})(window, document);
