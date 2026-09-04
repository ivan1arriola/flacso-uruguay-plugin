<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permite elegir entre una URL externa y un PDF de la Biblioteca de medios
 * para la malla curricular de la Oferta y el calendario de la Cohorte.
 *
 * El dato canónico continúa siendo una URL (`malla_curricular` o
 * `calendario_academico`) para no romper API, bloques ni frontend existentes.
 */
final class FLACSO_Academic_Document_Source_Admin {
    private const NONCE_ACTION = 'flacso_save_academic_document_source';
    private const NONCE_NAME = 'flacso_academic_document_source_nonce';

    private const MODE_LINK = 'enlace';
    private const MODE_PDF = 'pdf';

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 7);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_media']);
        add_action('admin_footer-post.php', [self::class, 'render_editor']);
        add_action('admin_footer-post-new.php', [self::class, 'render_editor']);

        add_action('save_post_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'save_offer'], 30, 2);
        add_action('save_post_' . FLACSO_Cohorte::POST_TYPE, [self::class, 'save_cohort'], 30, 2);
    }

    public static function register_meta(): void {
        self::register_source_meta(
            FLACSO_Oferta_Academica::POST_TYPE,
            'malla_curricular_modo',
            'malla_curricular_pdf_id'
        );
        self::register_source_meta(
            FLACSO_Cohorte::POST_TYPE,
            'calendario_modo',
            'calendario_pdf_id'
        );
    }

    private static function register_source_meta(string $post_type, string $mode_key, string $attachment_key): void {
        register_post_meta($post_type, $mode_key, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_mode'],
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]);
        register_post_meta($post_type, $attachment_key, [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => 'absint',
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function sanitize_mode($value): string {
        $mode = sanitize_key((string) $value);
        return in_array($mode, [self::MODE_LINK, self::MODE_PDF], true) ? $mode : self::MODE_LINK;
    }

    public static function enqueue_media(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [FLACSO_Oferta_Academica::POST_TYPE, FLACSO_Cohorte::POST_TYPE], true)) {
            return;
        }
        wp_enqueue_media();
    }

    public static function render_editor(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [FLACSO_Oferta_Academica::POST_TYPE, FLACSO_Cohorte::POST_TYPE], true)) {
            return;
        }

        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }

        if ($screen->post_type === FLACSO_Oferta_Academica::POST_TYPE) {
            $config = self::source_config(
                $post->ID,
                'malla',
                'malla_curricular',
                'malla_curricular_modo',
                'malla_curricular_pdf_id',
                'input[name="flacso_oferta[malla_curricular]"]',
                __('Malla curricular', 'flacso-uruguay')
            );
        } else {
            $config = self::source_config(
                $post->ID,
                'calendario',
                'calendario_academico',
                'calendario_modo',
                'calendario_pdf_id',
                'input[name="calendario_academico"]',
                __('Calendario académico', 'flacso-uruguay')
            );
        }

        $config['nonceName'] = self::NONCE_NAME;
        $config['nonce'] = wp_create_nonce(self::NONCE_ACTION);
        $config['labels'] = [
            'source' => __('Fuente del documento', 'flacso-uruguay'),
            'link' => __('Usar enlace', 'flacso-uruguay'),
            'pdf' => __('Subir / elegir PDF', 'flacso-uruguay'),
            'selectPdf' => __('Seleccionar o subir PDF', 'flacso-uruguay'),
            'replacePdf' => __('Cambiar PDF', 'flacso-uruguay'),
            'removePdf' => __('Quitar PDF', 'flacso-uruguay'),
            'choosePdf' => __('Elegir PDF', 'flacso-uruguay'),
            'invalidPdf' => __('Seleccioná un archivo PDF.', 'flacso-uruguay'),
            'linkHelp' => __('Pegá una URL pública. Puede ser un PDF externo u otra página pública.', 'flacso-uruguay'),
            'pdfHelp' => __('El PDF se guarda en la Biblioteca de medios de WordPress y se usa su URL pública.', 'flacso-uruguay'),
            'noPdf' => __('Todavía no hay un PDF seleccionado.', 'flacso-uruguay'),
        ];
        ?>
        <style>
            .flacso-document-source { margin:10px 0 8px; padding:12px; border:1px solid #cbd5e1; border-radius:7px; background:#fff; }
            .flacso-document-source__title { display:block; margin-bottom:8px; font-weight:700; }
            .flacso-document-source__modes { display:flex; flex-wrap:wrap; gap:14px; margin-bottom:10px; }
            .flacso-document-source__modes label { display:flex; align-items:center; gap:6px; font-weight:600; }
            .flacso-document-source__pdf { display:flex; align-items:center; flex-wrap:wrap; gap:8px; padding-top:2px; }
            .flacso-document-source__file { color:#475569; font-size:12px; word-break:break-all; }
            .flacso-document-source__help { display:block; margin-top:7px; color:#646970; line-height:1.4; }
            .flacso-document-source.is-pdf + input,
            .flacso-document-source.is-pdf ~ input[type="url"] { background:#f6f7f7; }
        </style>
        <script>
        (function () {
            var cfg = <?php echo wp_json_encode($config); ?>;
            var input = document.querySelector(cfg.selector);
            var form = document.getElementById('post');
            if (!input || !form || typeof wp === 'undefined' || !wp.media) return;
            if (input.dataset.flacsoDocumentSourceReady === '1') return;
            input.dataset.flacsoDocumentSourceReady = '1';

            function hidden(name, value) {
                var field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = value == null ? '' : String(value);
                form.appendChild(field);
                return field;
            }

            hidden(cfg.nonceName, cfg.nonce);
            var modeInput = hidden('flacso_document_source[' + cfg.key + '][mode]', cfg.mode);
            var attachmentInput = hidden('flacso_document_source[' + cfg.key + '][attachment_id]', cfg.attachmentId || 0);

            var panel = document.createElement('div');
            panel.className = 'flacso-document-source';
            panel.innerHTML =
                '<span class="flacso-document-source__title"></span>' +
                '<div class="flacso-document-source__modes">' +
                    '<label><input type="radio" name="flacso_document_source_mode_ui_' + cfg.key + '" value="enlace"> <span class="mode-link"></span></label>' +
                    '<label><input type="radio" name="flacso_document_source_mode_ui_' + cfg.key + '" value="pdf"> <span class="mode-pdf"></span></label>' +
                '</div>' +
                '<div class="flacso-document-source__pdf">' +
                    '<button type="button" class="button flacso-document-source__choose"></button>' +
                    '<button type="button" class="button-link-delete flacso-document-source__remove"></button>' +
                    '<span class="flacso-document-source__file"></span>' +
                '</div>' +
                '<small class="flacso-document-source__help"></small>';

            input.parentNode.insertBefore(panel, input);
            panel.querySelector('.flacso-document-source__title').textContent = cfg.labels.source + ' — ' + cfg.title;
            panel.querySelector('.mode-link').textContent = cfg.labels.link;
            panel.querySelector('.mode-pdf').textContent = cfg.labels.pdf;

            var chooseButton = panel.querySelector('.flacso-document-source__choose');
            var removeButton = panel.querySelector('.flacso-document-source__remove');
            var fileLabel = panel.querySelector('.flacso-document-source__file');
            var pdfArea = panel.querySelector('.flacso-document-source__pdf');
            var help = panel.querySelector('.flacso-document-source__help');
            var radios = panel.querySelectorAll('input[type="radio"]');
            var frame = null;

            function selectedMode() {
                return modeInput.value === 'pdf' ? 'pdf' : 'enlace';
            }

            function render() {
                var mode = selectedMode();
                panel.classList.toggle('is-pdf', mode === 'pdf');
                input.readOnly = mode === 'pdf';
                pdfArea.style.display = mode === 'pdf' ? 'flex' : 'none';
                help.textContent = mode === 'pdf' ? cfg.labels.pdfHelp : cfg.labels.linkHelp;
                radios.forEach(function (radio) { radio.checked = radio.value === mode; });

                var hasPdf = parseInt(attachmentInput.value || '0', 10) > 0;
                chooseButton.textContent = hasPdf ? cfg.labels.replacePdf : cfg.labels.selectPdf;
                removeButton.textContent = cfg.labels.removePdf;
                removeButton.style.display = hasPdf ? 'inline-block' : 'none';
                fileLabel.textContent = hasPdf ? (input.value || cfg.currentUrl || '') : cfg.labels.noPdf;
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    modeInput.value = radio.value;
                    render();
                });
            });

            chooseButton.addEventListener('click', function () {
                if (!frame) {
                    frame = wp.media({
                        title: cfg.labels.choosePdf + ' — ' + cfg.title,
                        button: { text: cfg.labels.choosePdf },
                        library: { type: 'application/pdf' },
                        multiple: false
                    });
                    frame.on('select', function () {
                        var selected = frame.state().get('selection').first();
                        var attachment = selected ? selected.toJSON() : null;
                        if (!attachment || attachment.mime !== 'application/pdf') {
                            window.alert(cfg.labels.invalidPdf);
                            return;
                        }
                        attachmentInput.value = attachment.id || 0;
                        input.value = attachment.url || '';
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        modeInput.value = 'pdf';
                        render();
                    });
                }
                frame.open();
            });

            removeButton.addEventListener('click', function () {
                attachmentInput.value = '0';
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                render();
            });

            render();
        })();
        </script>
        <?php
    }

    /** @return array<string,mixed> */
    private static function source_config(
        int $post_id,
        string $key,
        string $url_key,
        string $mode_key,
        string $attachment_key,
        string $selector,
        string $title
    ): array {
        $url = trim((string) get_post_meta($post_id, $url_key, true));
        $attachment_id = absint(get_post_meta($post_id, $attachment_key, true));
        $mode = self::sanitize_mode(get_post_meta($post_id, $mode_key, true));

        // Compatibilidad: si antes se guardó la URL de un PDF local, detectarlo
        // sin exigir una migración de datos.
        if ($attachment_id === 0 && $url !== '') {
            $candidate = attachment_url_to_postid($url);
            if (self::is_pdf_attachment($candidate)) {
                $attachment_id = $candidate;
                if (!metadata_exists('post', $post_id, $mode_key)) {
                    $mode = self::MODE_PDF;
                }
            }
        }

        if ($attachment_id > 0 && self::is_pdf_attachment($attachment_id)) {
            $mode = self::MODE_PDF;
        } elseif ($mode === self::MODE_PDF) {
            $mode = self::MODE_LINK;
        }

        return [
            'key' => $key,
            'title' => $title,
            'selector' => $selector,
            'mode' => $mode,
            'attachmentId' => $attachment_id,
            'currentUrl' => $url,
        ];
    }

    public static function save_offer(int $post_id, WP_Post $post): void {
        self::save_source(
            $post_id,
            'malla',
            'malla_curricular',
            'malla_curricular_modo',
            'malla_curricular_pdf_id'
        );
    }

    public static function save_cohort(int $post_id, WP_Post $post): void {
        self::save_source(
            $post_id,
            'calendario',
            'calendario_academico',
            'calendario_modo',
            'calendario_pdf_id'
        );
    }

    private static function save_source(
        int $post_id,
        string $payload_key,
        string $url_key,
        string $mode_key,
        string $attachment_key
    ): void {
        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $payload = isset($_POST['flacso_document_source']) && is_array($_POST['flacso_document_source'])
            ? wp_unslash($_POST['flacso_document_source'])
            : [];
        $source = isset($payload[$payload_key]) && is_array($payload[$payload_key]) ? $payload[$payload_key] : [];
        if ($source === []) {
            return;
        }

        $mode = self::sanitize_mode($source['mode'] ?? self::MODE_LINK);
        $attachment_id = absint($source['attachment_id'] ?? 0);

        if ($mode === self::MODE_PDF && self::is_pdf_attachment($attachment_id)) {
            $url = wp_get_attachment_url($attachment_id);
            if (is_string($url) && $url !== '') {
                update_post_meta($post_id, $url_key, esc_url_raw($url));
                update_post_meta($post_id, $mode_key, self::MODE_PDF);
                update_post_meta($post_id, $attachment_key, $attachment_id);
                return;
            }
        }

        // Enlace externo, o fallback seguro si se intentó guardar modo PDF sin
        // un attachment PDF válido. El campo canónico ya fue saneado por el
        // editor original de Oferta/Cohorte antes de este hook.
        update_post_meta($post_id, $mode_key, self::MODE_LINK);
        delete_post_meta($post_id, $attachment_key);
    }

    private static function is_pdf_attachment(int $attachment_id): bool {
        return $attachment_id > 0
            && get_post_type($attachment_id) === 'attachment'
            && get_post_mime_type($attachment_id) === 'application/pdf';
    }
}
