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
        button.textContent = isSubmitting ? submittingLabel : defaultLabel;
    }

    function initMailingForm(form) {
        if (!form || form.dataset.mailingAjaxBound === '1') {
            return;
        }

        form.dataset.mailingAjaxBound = '1';

        var button = form.querySelector('[data-mailing-submit]');
        var shell = form.closest('.flacso-mailing-form-shell');
        var noticeSlot = shell ? shell.querySelector('[data-mailing-notice]') : null;
        var ajaxUrl = (window.flacsoMailingSettings && window.flacsoMailingSettings.ajaxUrl) || '';

        if (!button || !noticeSlot || !ajaxUrl) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                return;
            }

            toggleSubmitting(form, button, true);

            var formData = new FormData(form);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
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
        });
    }

    ready(function () {
        var forms = document.querySelectorAll('[data-mailing-form]');
        Array.prototype.forEach.call(forms, initMailingForm);
    });
})();
