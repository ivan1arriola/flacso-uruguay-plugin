(function (window, document) {
    "use strict";

    if (window.__flacsoPreinscripcionAnalyticsBooted) return;
    window.__flacsoPreinscripcionAnalyticsBooted = true;

    var root = document.querySelector(".flacso-preinscripcion-seminario-main");
    if (!root) return;

    var form = document.getElementById("form-preinscripcion");
    var titleNode = root.querySelector(".preinsc-title");
    var monetaryContext = window.flacsoMetaMonetaryContext && typeof window.flacsoMetaMonetaryContext === "object"
        ? window.flacsoMetaMonetaryContext
        : {};
    var seminarTitle = String(monetaryContext.content_name || (titleNode ? String(titleNode.textContent || "").trim() : document.title)).trim();
    var query = new URLSearchParams(window.location.search || "");
    var seminarId = String(monetaryContext.content_id || query.get("ID") || "").trim();
    var started = false;
    var submitAttempted = false;
    var viewedSections = {};

    function baseParams() {
        return {
            content_type: "seminario",
            content_category: "seminario",
            content_name: seminarTitle,
            content_ids: seminarId ? ["seminario-" + seminarId] : [],
            seminario_id: seminarId,
            seminario_nombre: seminarTitle,
            flacso_stage: "preinscripcion",
            page_location: window.location.href
        };
    }

    function track(eventName, params) {
        var payload = Object.assign({}, baseParams(), params || {});
        if (typeof window.flacsoMetaTrackCustom === "function") {
            window.flacsoMetaTrackCustom(eventName, payload);
        } else if (typeof window.flacsoMetaTrack === "function") {
            window.flacsoMetaTrack(eventName, payload);
        } else if (typeof window.flacsoTrackGA4 === "function") {
            window.flacsoTrackGA4(eventName, payload);
        }
    }

    function sectionName(section) {
        var heading = section && section.querySelector(".flacso-seccion-title");
        var text = heading ? String(heading.textContent || "").trim().toLowerCase() : "seccion";
        return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "") || "seccion";
    }

    function observeSection(section, index) {
        var key = sectionName(section);
        if (!("IntersectionObserver" in window)) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting || entry.intersectionRatio < 0.5 || viewedSections[key]) return;
                viewedSections[key] = true;
                track("preinscripcion_section_view", {
                    section_name: key,
                    section_index: index + 1,
                    visibility_ratio: Math.round(entry.intersectionRatio * 100) / 100
                });
                observer.disconnect();
            });
        }, { threshold: [0.5] });
        observer.observe(section);
    }

    if (document.querySelector(".success-container")) {
        track("preinscripcion_success_view", { status: "completed", flacso_stage: "preinscripcion_confirmada" });
        return;
    }

    if (!form) return;

    track("preinscripcion_form_view", { form_id: form.id, flacso_stage: "formulario_preinscripcion" });
    Array.prototype.slice.call(form.querySelectorAll(".flacso-seccion")).forEach(observeSection);

    form.addEventListener("focusin", function (event) {
        if (started) return;
        var control = event.target && event.target.matches && event.target.matches("input, select, textarea") ? event.target : null;
        if (!control || control.type === "hidden") return;
        started = true;
        track("preinscripcion_form_start", {
            form_id: form.id,
            first_field: control.name || control.id || "unknown",
            flacso_stage: "formulario_iniciado"
        });
    }, true);

    form.addEventListener("change", function (event) {
        var control = event.target;
        if (!control || control.type !== "file") return;
        track("preinscripcion_document_added", {
            field_name: control.name || control.id || "archivo",
            file_count: control.files ? control.files.length : 0,
            flacso_stage: "documentacion"
        });
    }, true);

    form.addEventListener("submit", function () {
        if (submitAttempted) return;
        submitAttempted = true;
        var invalid = Array.prototype.slice.call(form.querySelectorAll(":invalid"));
        var firstInvalid = invalid.length ? invalid[0] : null;
        track("preinscripcion_submit_attempt", {
            form_id: form.id,
            invalid_fields_count: invalid.length,
            first_invalid_field: firstInvalid ? (firstInvalid.name || firstInvalid.id || "unknown") : "",
            form_valid: invalid.length === 0,
            flacso_stage: invalid.length === 0 ? "envio_intentado" : "validacion_bloqueada"
        });
    }, true);

    var serverError = document.querySelector(".alert.alert-danger");
    if (serverError) {
        track("preinscripcion_server_error", {
            error_message: String(serverError.textContent || "").replace(/\s+/g, " ").trim().slice(0, 250),
            flacso_stage: "error_servidor"
        });
    }

    document.addEventListener("click", function (event) {
        var target = event.target && event.target.closest ? event.target.closest(".flacso-preinscripcion-seminario-main a, .flacso-preinscripcion-seminario-main button") : null;
        if (!target) return;

        var label = String(target.getAttribute("aria-label") || target.textContent || "").replace(/\s+/g, " ").trim();
        var href = target.tagName === "A" ? target.href : "";
        var action = target.type === "submit" ? "enviar_preinscripcion" : "navigation";
        if (/volver al seminario/i.test(label)) action = "volver_seminario";
        if (/volver a seminarios/i.test(label)) action = "volver_listado";

        track("preinscripcion_cta_click", {
            cta_action: action,
            cta_label: label,
            destination_url: href,
            flacso_stage: "interaccion_cta"
        });
    }, true);
})(window, document);
