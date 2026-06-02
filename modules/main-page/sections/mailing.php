<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_section_mailing_render')) {
    function flacso_section_mailing_render() {
        $settings = Flacso_Main_Page_Settings::get_section('mailing');
        $benefits = [
            __('Novedades', 'flacso-uruguay'),
            __('Actividades', 'flacso-uruguay'),
            __('Convocatorias', 'flacso-uruguay'),
        ];

        if (!class_exists('Flacso_Mailing_Subscription') || !is_callable(['Flacso_Mailing_Subscription', 'render_form'])) {
            if (!current_user_can('manage_options')) {
                return '';
            }

            return '<div class="flacso-content-shell"><div class="flacso-mailing-notice is-warning">' .
                esc_html__('No se pudo cargar el módulo de suscripción a la lista de difusión.', 'flacso-uruguay') .
                '</div></div>';
        }

        $anchor = 'flacso-mailing-home';
        $form_markup = Flacso_Mailing_Subscription::render_form([
            'form_id' => 'home',
            'anchor' => $anchor,
            'form_title' => __('Alta rápida al boletín', 'flacso-uruguay'),
            'form_description' => __('Completá tus datos y empezá a recibir por correo la agenda institucional de FLACSO Uruguay.', 'flacso-uruguay'),
            'button_label' => $settings['button_label'] ?? '',
            'consent_text' => $settings['consent_text'] ?? '',
            'extra_classes' => 'flacso-mailing-home-form-shell',
        ]);

        if ($form_markup === '' && !current_user_can('manage_options')) {
            return '';
        }

        $title = trim((string) ($settings['title'] ?? ''));
        $subtitle = trim((string) ($settings['subtitle'] ?? ''));

        ob_start();
        ?>
        <section class="flacso-mailing-home-section" id="<?php echo esc_attr($anchor); ?>">
            <div class="flacso-content-shell">
                <div class="flacso-mailing-home-card">
                    <div class="flacso-mailing-home-copy">
                        <p class="flacso-mailing-home-eyebrow"><?php esc_html_e('Boletín institucional', 'flacso-uruguay'); ?></p>
                        <?php if ($title !== ''): ?>
                            <h2 class="flacso-mailing-home-title"><?php echo esc_html($title); ?></h2>
                        <?php endif; ?>
                        <?php if ($subtitle !== ''): ?>
                            <p class="flacso-mailing-home-subtitle"><?php echo esc_html($subtitle); ?></p>
                        <?php endif; ?>
                        <ul class="flacso-mailing-home-points" aria-label="<?php esc_attr_e('Temas de la lista de difusión', 'flacso-uruguay'); ?>">
                            <?php foreach ($benefits as $benefit): ?>
                                <li><?php echo esc_html($benefit); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="flacso-mailing-home-note"><?php esc_html_e('Un canal simple para seguir la agenda, las novedades académicas y las comunicaciones más importantes.', 'flacso-uruguay'); ?></p>
                    </div>
                    <div class="flacso-mailing-home-form">
                        <?php echo $form_markup; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php

        return ob_get_clean();
    }
}
