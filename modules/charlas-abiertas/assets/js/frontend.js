(function () {
    var FLACSO_CHARLAS_DEBUG = false;

    function decodeBase64Utf8(value) {
        try {
            var binary = atob(String(value || ""));
            var bytes = [];
            for (var i = 0; i < binary.length; i++) {
                bytes.push(binary.charCodeAt(i));
            }
            if (window.TextDecoder) {
                return new TextDecoder("utf-8").decode(new Uint8Array(bytes));
            }
            return binary;
        } catch (e) {
            return "";
        }
    }

    function toIsoWithOffset(date) {
        var tzOffset = -date.getTimezoneOffset();
        var sign = tzOffset >= 0 ? "+" : "-";
        var abs = Math.abs(tzOffset);
        var hh = String(Math.floor(abs / 60)).padStart(2, "0");
        var mm = String(abs % 60).padStart(2, "0");
        var yyyy = date.getFullYear();
        var mon = String(date.getMonth() + 1).padStart(2, "0");
        var dd = String(date.getDate()).padStart(2, "0");
        var h = String(date.getHours()).padStart(2, "0");
        var m = String(date.getMinutes()).padStart(2, "0");
        var s = String(date.getSeconds()).padStart(2, "0");
        return yyyy + "-" + mon + "-" + dd + "T" + h + ":" + m + ":" + s + sign + hh + ":" + mm;
    }

    function detectDeviceType() {
        var ua = navigator.userAgent || "";
        if (/iPad|Tablet/i.test(ua)) {
            return "tablet";
        }
        if (/Mobi|Android|iPhone|iPod/i.test(ua)) {
            return "mobile";
        }
        return "desktop";
    }

    function bindIntlTelInput(input) {
        if (!input || !window.intlTelInput) {
            return null;
        }

        var options = {
            initialCountry: "auto",
            countryOrder: ["uy", "ar", "br", "cl", "py", "bo", "mx", "es", "us"],
            countryNameLocale: "es",
            nationalMode: true,
            formatAsYouType: true,
            formatOnDisplay: true,
            autoPlaceholder: "polite",
            allowNumberExtensions: false,
            allowPhonewords: false,
            strictMode: false,
            geoIpLookup: function (success, failure) {
                fetch("https://ipapi.co/json")
                    .then(function (res) {
                        return res.json();
                    })
                    .then(function (data) {
                        if (data && data.country_code) {
                            success(String(data.country_code).toLowerCase());
                            return;
                        }
                        success("uy");
                    })
                    .catch(function () {
                        success("uy");
                    });
            },
            loadUtils: function () {
                return import("https://cdn.jsdelivr.net/npm/intl-tel-input@26.5.1/build/js/utils.js");
            },
            allowedNumberTypes: ["MOBILE", "FIXED_LINE"],
        };

        try {
            return window.intlTelInput(input, options);
        } catch (e) {
            return window.intlTelInput(input, {
                initialCountry: "uy",
                countryOrder: ["uy", "ar", "br"],
                nationalMode: true,
                formatAsYouType: true,
                formatOnDisplay: true,
                autoPlaceholder: "polite",
                strictMode: false,
            });
        }
    }

    function waitIntlTelReady(iti) {
        if (!iti || !iti.promise || typeof iti.promise.then !== "function") {
            return Promise.resolve();
        }
        return iti.promise.catch(function () {
            return null;
        });
    }

    function parseJsonSafe(text) {
        if (!text) {
            return null;
        }
        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function debugLog(label, value) {
        if (!FLACSO_CHARLAS_DEBUG) {
            return;
        }
        try {
            console.log("[CharlasAbiertas] " + label, value);
        } catch (e) {
            // no-op
        }
    }

    function debugWarn(label, value) {
        if (!FLACSO_CHARLAS_DEBUG) {
            return;
        }
        try {
            console.log("[CharlasAbiertas] " + label, value);
        } catch (e) {
            // no-op
        }
    }

    function toPublicErrorMessage(message) {
        var msg = String(message || "").toLowerCase();

        // Never expose infrastructure/configuration details in public UI.
        if (
            msg.indexOf("webhook") !== -1 ||
            msg.indexOf("internal_error") !== -1 ||
            msg.indexOf("http webhook") !== -1 ||
            msg.indexOf("script.google.com") !== -1 ||
            msg.indexOf("/exec") !== -1
        ) {
            return "No pudimos procesar tu inscripción en este momento. Intenta nuevamente en unos minutos.";
        }

        if (msg.indexOf("validation_error") !== -1) {
            return "Revisa los datos ingresados e intenta nuevamente.";
        }

        return String(message || "No se pudo enviar la inscripción.");
    }

    function getPhoneValidationMessage(iti) {
        if (!iti || typeof iti.getValidationError !== "function") {
            return "El número de celular no es válido para el país seleccionado.";
        }
        var utils = window.intlTelInput && window.intlTelInput.utils ? window.intlTelInput.utils : null;
        var errors = utils && utils.validationError ? utils.validationError : null;
        var code = iti.getValidationError();

        if (!errors) {
            return "El número de celular no es válido para el país seleccionado.";
        }
        if (code === errors.TOO_SHORT) {
            return "El número de celular es demasiado corto.";
        }
        if (code === errors.TOO_LONG) {
            return "El número de celular es demasiado largo.";
        }
        if (code === errors.INVALID_COUNTRY_CODE) {
            return "El código de país no es válido.";
        }
        return "El número de celular no es válido para el país seleccionado.";
    }

    function bindForm(wrapper) {
        var form = wrapper.querySelector("form.flacso-charla-form");
        var result = wrapper.querySelector(".flacso-charla-form-result");
        var statusScreen = wrapper.querySelector(".flacso-charla-status-screen");
        var endpoint = wrapper.dataset.endpoint;
        var modalidadEvento = String(wrapper.dataset.eventoModalidad || "virtual").toLowerCase();
        var modalidadSelect = form ? form.querySelector('select[name="modalidad_asistencia"]') : null;
        var modalidadLocknote = form ? form.querySelector(".flacso-modalidad-locknote") : null;
        var correoInput = form ? form.querySelector('input[name="correo"]') : null;
        var countryInput = form ? form.querySelector(".flacso-pais-residencia") : null;
        var celularInput = form ? form.querySelector(".flacso-celular") : null;
        var celularGroup = form ? form.querySelector(".flacso-celular-group") : null;
        var correoFeedback = form ? form.querySelector(".flacso-correo-feedback") : null;
        var modalidadFeedback = form ? form.querySelector(".flacso-modalidad-feedback") : null;
        var celularFeedback = form ? form.querySelector(".flacso-celular-feedback") : null;

        if (!form || !result || !endpoint) {
            return;
        }

        if (!statusScreen) {
            statusScreen = document.createElement("div");
            statusScreen.className = "flacso-charla-status-screen";
            statusScreen.setAttribute("aria-live", "polite");
            statusScreen.hidden = true;
            wrapper.appendChild(statusScreen);
        }

        function showLoadingState() {
            form.hidden = true;
            result.innerHTML = "";
            statusScreen.hidden = false;
            statusScreen.innerHTML = [
                '<div class="flacso-charla-status-card">',
                '<div class="flacso-charla-loader" aria-hidden="true"></div>',
                "<h3>Enviando tu inscripción...</h3>",
                "<p>Estamos procesando tus datos. Esto puede demorar unos segundos.</p>",
                "</div>",
            ].join("");
        }

        function showSuccessState(messageHtml) {
            form.hidden = true;
            result.innerHTML = "";
            statusScreen.hidden = false;
            statusScreen.innerHTML = [
                '<div class="flacso-charla-status-card success">',
                "<h3>Gracias por inscribirte</h3>",
                '<p class="flacso-charla-status-message">' + messageHtml + "</p>",
                "</div>",
            ].join("");
        }

        function showFormWithError(message) {
            statusScreen.hidden = true;
            statusScreen.innerHTML = "";
            form.hidden = false;
            result.innerHTML = '<div class="error">' + escapeHtml(message || "No se pudo enviar la inscripción.") + "</div>";
        }

        var iti = bindIntlTelInput(celularInput);

        function setFieldError(field, feedbackEl, message) {
            if (!field) {
                return;
            }
            var msg = String(message || "").trim();
            field.setCustomValidity(msg);
            if (msg) {
                field.classList.add("is-invalid");
                field.classList.remove("is-valid");
            } else {
                field.classList.remove("is-invalid");
                if (field.value && field.value.trim()) {
                    field.classList.add("is-valid");
                } else {
                    field.classList.remove("is-valid");
                }
            }
            if (feedbackEl) {
                feedbackEl.textContent = msg;
            }
        }

        function syncFieldFromValidity(field, feedbackEl, fallbackMessage) {
            if (!field) {
                return;
            }
            // Clear previous custom error before re-checking native validity.
            field.setCustomValidity("");
            if (field.checkValidity()) {
                setFieldError(field, feedbackEl, "");
                return;
            }
            setFieldError(field, feedbackEl, field.validationMessage || fallbackMessage || "Campo inválido.");
        }

        function setCelularError(message) {
            if (!celularInput) {
                return;
            }
            var msg = String(message || "").trim();
            celularInput.setCustomValidity(msg);
            if (msg) {
                celularInput.classList.add("is-invalid");
                celularInput.classList.remove("is-valid");
                if (celularGroup) {
                    celularGroup.classList.add("is-invalid");
                }
            } else {
                celularInput.classList.remove("is-invalid");
                if (celularGroup) {
                    celularGroup.classList.remove("is-invalid");
                }
                if (celularInput.value && celularInput.value.trim()) {
                    celularInput.classList.add("is-valid");
                } else {
                    celularInput.classList.remove("is-valid");
                }
            }
            if (celularFeedback) {
                celularFeedback.textContent = msg;
            }
        }

        if (celularInput) {
            celularInput.addEventListener("countrychange", function () {
                setCelularError("");
                result.innerHTML = "";
            });
            celularInput.addEventListener("input", function () {
                setCelularError("");
            });
        }

        if (modalidadSelect) {
            if (modalidadEvento === "hibrida") {
                modalidadSelect.disabled = false;
                modalidadSelect.required = true;
                modalidadSelect.classList.remove("flacso-modalidad-readonly");
                if (modalidadLocknote) {
                    modalidadLocknote.hidden = true;
                }
            } else {
                modalidadSelect.value = modalidadEvento === "presencial" ? "presencial" : "virtual";
                modalidadSelect.disabled = true;
                modalidadSelect.required = false;
                modalidadSelect.classList.add("flacso-modalidad-readonly");
                if (modalidadLocknote) {
                    modalidadLocknote.hidden = false;
                }
                if (modalidadFeedback) {
                    modalidadFeedback.textContent = "";
                }
            }
        }

        if (correoInput) {
            correoInput.addEventListener("input", function () {
                syncFieldFromValidity(correoInput, correoFeedback, "Ingresa un correo válido.");
            });
        }

        if (modalidadSelect) {
            modalidadSelect.addEventListener("change", function () {
                if (modalidadSelect.disabled) {
                    setFieldError(modalidadSelect, modalidadFeedback, "");
                    return;
                }
                syncFieldFromValidity(modalidadSelect, modalidadFeedback, "Selecciona una modalidad de asistencia.");
            });
        }

        form.addEventListener("submit", async function (ev) {
            ev.preventDefault();
            form.classList.add("was-validated");

            if (correoInput) {
                syncFieldFromValidity(correoInput, correoFeedback, "Ingresa un correo válido.");
            }
            if (modalidadSelect) {
                if (modalidadSelect.disabled) {
                    setFieldError(modalidadSelect, modalidadFeedback, "");
                } else {
                    syncFieldFromValidity(modalidadSelect, modalidadFeedback, "Selecciona una modalidad de asistencia.");
                }
            }

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var fd = new FormData(form);
            var modalidadAsistencia = String(fd.get("modalidad_asistencia") || "").trim();
            if (!modalidadAsistencia && modalidadEvento !== "hibrida") {
                modalidadAsistencia = modalidadEvento === "presencial" ? "presencial" : "virtual";
            }

            var nombre = String(fd.get("nombre") || "").trim();
            var apellido = String(fd.get("apellido") || "").trim();
            var nombreApellido = (nombre + " " + apellido).trim();

            var paisResidencia = String(fd.get("pais_residencia") || "").trim();

            var celular = String(fd.get("celular") || "").trim();
            if (iti && celular) {
                await waitIntlTelReady(iti);

                var hasPlusPrefix = /^\s*\+/.test(celular);
                var selected = typeof iti.getSelectedCountryData === "function" ? iti.getSelectedCountryData() : null;
                if (!hasPlusPrefix && (!selected || !selected.iso2) && typeof iti.setCountry === "function") {
                    iti.setCountry("uy");
                }

                if (typeof iti.isValidNumber === "function" && !iti.isValidNumber()) {
                    setCelularError(getPhoneValidationMessage(iti));
                    form.reportValidity();
                    return;
                }
                setCelularError("");
                if (typeof iti.getNumber === "function") {
                    var normalizedCell = iti.getNumber();
                    if (normalizedCell) {
                        celular = normalizedCell;
                    }
                }
            } else {
                setCelularError("");
            }

            var eventoData = {
                id: Number(wrapper.dataset.eventoId || 0),
                titulo: wrapper.dataset.eventoTitulo || "",
                inicio: wrapper.dataset.eventoInicio || "",
                modalidad: wrapper.dataset.eventoModalidad || "virtual",
                zoom_join_url: String(wrapper.dataset.eventoZoomJoinUrl || "").trim(),
                direccion: String(wrapper.dataset.eventoDireccion || "").trim(),
                descripcion: decodeBase64Utf8(wrapper.dataset.eventoDescripcionB64 || ""),
            };

            var inscripcionData = {
                nombre: nombre,
                apellido: apellido,
                correo: String(fd.get("correo") || "").trim(),
                pais_residencia: paisResidencia,
                profesion: String(fd.get("profesion") || "").trim(),
                institucion: String(fd.get("institucion") || "").trim(),
                celular: celular,
                modalidad_asistencia: modalidadAsistencia,
            };

            var payload = {
                evento: eventoData,
                inscripcion: inscripcionData,
                device: {
                    ip: "",
                    user_agent: navigator.userAgent || "",
                    referer: document.referrer || "",
                    origin: window.location.origin || "",
                    device_type: detectDeviceType(),
                    screen_width: window.screen && window.screen.width ? window.screen.width : "",
                    screen_height: window.screen && window.screen.height ? window.screen.height : "",
                    language: navigator.language || "",
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || "",
                },
                meta: {
                    wp_user_logged_in: wrapper.dataset.wpUserLoggedIn === "true",
                    timestamp_client: toIsoWithOffset(new Date()),
                    host_post_id: Number(wrapper.dataset.hostPostId || 0),
                    post_featured_image: wrapper.dataset.hostPostFeaturedImage || "",
                },
            };

            debugLog("Payload de inscripción", payload);
            debugLog("Endpoint", endpoint);
            var submitStartedAt = Date.now();
            var minErrorDelayMs = 30000;
            showLoadingState();

            fetch(endpoint, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(payload),
            })
                .then(function (res) {
                    debugLog("HTTP status", res.status);
                    return res.text().then(function (rawText) {
                        debugLog("Raw response", rawText);
                        var data = parseJsonSafe(rawText);
                        debugLog("Parsed response", data);
                        if (data) {
                            debugLog("Parsed response JSON", JSON.stringify(data));
                        }

                        if (!res.ok) {
                            var msg = data && data.error && data.error.message ? data.error.message : "";
                            var details = data && data.error && data.error.details ? data.error.details : null;
                            if (details && typeof details === "object") {
                                var extra = [];
                                if (details.status) {
                                    extra.push("HTTP webhook: " + details.status);
                                }
                                if (details.body) {
                                    extra.push(String(details.body).slice(0, 280));
                                }
                                if (extra.length) {
                                    msg = (msg ? msg + " " : "") + "(" + extra.join(" | ") + ")";
                                }
                            }
                            if (!msg && rawText) {
                                msg = String(rawText).trim();
                            }
                            var detailedErr = new Error(msg || ("Error HTTP " + res.status + " al enviar la inscripción."));
                            detailedErr.data = data || null;
                            detailedErr.status = res.status;
                            detailedErr.raw = rawText;
                            throw detailedErr;
                        }

                        if (!data) {
                            throw new Error("El servidor devolvió una respuesta inválida.");
                        }

                        return data;
                    });
                })
                .then(function (response) {
                    debugLog("Processing ms (frontend)", Date.now() - submitStartedAt);
                    debugLog("Flow OK", response);
                    var correoMostrado = inscripcionData.correo || "";
                    var nombreMostrado = nombreApellido || "participante";
                    var eventoTitulo = eventoData.titulo || "la actividad";
                    if (response && response.code === "DUPLICADA") {
                        showSuccessState(
                            "Hola <strong>" + escapeHtml(nombreMostrado) + "</strong>, ya contábamos con tu inscripción para <strong>" +
                            escapeHtml(eventoTitulo) + '</strong> con el correo <strong>' + escapeHtml(correoMostrado) + "</strong>."
                        );
                        return;
                    }
                    showSuccessState(
                        "Hola <strong>" + escapeHtml(nombreMostrado) + "</strong>, tu inscripción a <strong>" + escapeHtml(eventoTitulo) +
                        '</strong> fue confirmada. Te enviamos la confirmación a <strong>' + escapeHtml(correoMostrado) +
                        "</strong>. Si no lo encuentras en tu bandeja principal, revisa también Spam y Promociones."
                    );
                    form.reset();
                    form.classList.remove("was-validated");
                    if (correoInput) {
                        setFieldError(correoInput, correoFeedback, "");
                    }
                    if (modalidadSelect) {
                        setFieldError(modalidadSelect, modalidadFeedback, "");
                    }
                    setCelularError("");
                    if (iti) {
                        iti.setCountry("uy");
                    }
                })
                .catch(function (err) {
                    var message = err && err.message ? err.message : String(err || "");
                    debugWarn("Flow ERROR message", message);
                    var elapsed = Date.now() - submitStartedAt;
                    var remaining = Math.max(0, minErrorDelayMs - elapsed);
                    debugLog("Processing ms (frontend)", elapsed);
                    debugLog("Error delay ms", remaining);
                    if (err && err.data) {
                        debugWarn("Flow ERROR data", err.data);
                        if (err.data.error && err.data.error.details) {
                            debugWarn("Flow ERROR details", err.data.error.details);
                        }
                    }
                    setTimeout(function () {
                        showFormWithError(toPublicErrorMessage(message));
                    }, remaining);
                });
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".flacso-charla-form-wrapper").forEach(bindForm);
    });
})();
