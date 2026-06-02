(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }

        fn();
    }

    function setNotice(slot, html) {
        if (!slot) {
            return;
        }

        slot.innerHTML = html || '';
    }

    function toggleSubmitting(form, button, isSubmitting) {
        if (!form || !button) {
            return;
        }

        var defaultLabel = button.getAttribute('data-default-label') || button.textContent || '';
        var submittingLabel = (window.flacsoMailingSettings && window.flacsoMailingSettings.submittingLabel) || 'Enviando...';

        button.disabled = !!isSubmitting;
        form.classList.toggle('is-submitting', !!isSubmitting);
        form.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
        form.dataset.mailingSubmitting = isSubmitting ? '1' : '0';
        button.textContent = isSubmitting ? submittingLabel : defaultLabel;
    }

    function getMailingContext(form) {
        var button = form.querySelector('[data-mailing-submit]');
        var shell = form.closest('.flacso-mailing-form-shell');
        var noticeSlot = shell ? shell.querySelector('[data-mailing-notice]') : null;
        var ajaxUrl = form.getAttribute('data-mailing-ajax-url') || (window.flacsoMailingSettings && window.flacsoMailingSettings.ajaxUrl) || '';

        if (!button || !noticeSlot || !ajaxUrl || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
            return null;
        }

        return {
            button: button,
            noticeSlot: noticeSlot,
            ajaxUrl: ajaxUrl
        };
    }

    function submitMailingForm(form, context) {
        context = context || getMailingContext(form);
        if (!context) {
            return Promise.resolve();
        }

        var button = context.button;
        var noticeSlot = context.noticeSlot;
        var ajaxUrl = context.ajaxUrl;

        if (form.dataset.mailingSubmitting === '1') {
            return Promise.resolve();
        }

        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            return Promise.resolve();
        }

        toggleSubmitting(form, button, true);

        var formData = new FormData(form);

        return fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        })
            .then(function (response) {
                return response.json().catch(function () {
                    return {
                        success: false,
                        data: {
                            noticeHtml: '<div class="flacso-mailing-notice is-error" role="status">No pudimos procesar la respuesta del servidor.</div>'
                        }
                    };
                });
            })
            .then(function (payload) {
                var data = payload && payload.data ? payload.data : {};
                setNotice(noticeSlot, data.noticeHtml || '');

                if (payload && payload.success) {
                    form.reset();
                }
            })
            .catch(function () {
                setNotice(
                    noticeSlot,
                    '<div class="flacso-mailing-notice is-error" role="status">No se pudo enviar el formulario en este momento. Probá nuevamente.</div>'
                );
            })
            .finally(function () {
                toggleSubmitting(form, button, false);
            });
    }

    ready(function () {
        document.addEventListener(
            'submit',
            function (event) {
                var form = event.target;
                var context;

                if (!form || !form.matches || !form.matches('[data-mailing-form]')) {
                    return;
                }

                context = getMailingContext(form);
                if (!context) {
                    return;
                }

                event.preventDefault();
                submitMailingForm(form, context);
            },
            true
        );
    });
})();
