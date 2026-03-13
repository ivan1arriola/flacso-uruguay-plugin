(function () {
    function setStatus(statusNode, message, type) {
        if (!statusNode) {
            return;
        }

        statusNode.textContent = message || '';
        statusNode.classList.remove('is-success', 'is-error');

        if (type === 'success') {
            statusNode.classList.add('is-success');
        } else if (type === 'error') {
            statusNode.classList.add('is-error');
        }
    }

    function setupConsultaForm(root) {
        var openBtn = root.querySelector('[data-oa-consulta-open]');
        var overlay = root.querySelector('[data-oa-consulta-overlay]');
        var closeBtn = root.querySelector('[data-oa-consulta-close]');
        var form = root.querySelector('[data-oa-consulta-form]');
        var submitBtn = root.querySelector('[data-oa-consulta-submit]');
        var statusNode = root.querySelector('[data-oa-consulta-status]');

        if (!openBtn || !overlay || !closeBtn || !form || !submitBtn) {
            return;
        }

        var ajaxUrl = root.getAttribute('data-ajax-url') || '';
        var nonce = root.getAttribute('data-nonce') || '';
        var endpointConfigured = root.getAttribute('data-endpoint-configured') === '1';
        var defaultSubmitLabel = submitBtn.textContent;
        var returnFocusNode = null;

        function openModal() {
            returnFocusNode = document.activeElement;
            overlay.hidden = false;
            document.body.classList.add('flacso-oa-consulta-open');
            setStatus(statusNode, '', null);

            var firstField = form.querySelector('input, select, textarea');
            if (firstField) {
                setTimeout(function () {
                    firstField.focus();
                }, 30);
            }
        }

        function closeModal() {
            overlay.hidden = true;
            document.body.classList.remove('flacso-oa-consulta-open');
            if (returnFocusNode && typeof returnFocusNode.focus === 'function') {
                returnFocusNode.focus();
            }
        }

        openBtn.addEventListener('click', function () {
            openModal();
        });

        closeBtn.addEventListener('click', function () {
            closeModal();
        });

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (overlay.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            setStatus(statusNode, '', null);

            if (!endpointConfigured) {
                setStatus(statusNode, 'El formulario no está disponible en este momento.', 'error');
                return;
            }

            if (!ajaxUrl || !nonce) {
                setStatus(statusNode, 'No se pudo enviar la consulta. Recargá la página e intentá nuevamente.', 'error');
                return;
            }

            if (!form.reportValidity()) {
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';

            var formData = new FormData(form);
            var payload = new URLSearchParams();

            payload.append('action', 'flacso_oferta_consulta_submit');
            payload.append('nonce', nonce);
            payload.append('nombre', String(formData.get('nombre') || ''));
            payload.append('apellido', String(formData.get('apellido') || ''));
            payload.append('correo', String(formData.get('correo') || ''));
            payload.append('oferta_id', String(formData.get('oferta_id') || ''));
            payload.append('consulta', String(formData.get('consulta') || ''));

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString()
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return {
                            success: false,
                            data: {
                                message: 'Respuesta no válida del servidor.'
                            }
                        };
                    });
                })
                .then(function (result) {
                    var message = (result && result.data && result.data.message)
                        ? result.data.message
                        : 'No se pudo enviar la consulta.';

                    if (result && result.success) {
                        setStatus(statusNode, message, 'success');
                        form.reset();
                        setTimeout(function () {
                            closeModal();
                        }, 900);
                        return;
                    }

                    setStatus(statusNode, message, 'error');
                })
                .catch(function () {
                    setStatus(statusNode, 'No se pudo enviar la consulta. Revisá tu conexión e intentá de nuevo.', 'error');
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = defaultSubmitLabel;
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var roots = document.querySelectorAll('[data-flacso-oa-consulta]');
        if (!roots.length) {
            return;
        }

        roots.forEach(setupConsultaForm);
    });
})();
