<?php
/**
 * Sección: mosaico de oferta educativa.
 *
 * @package FLACSO_Uruguay
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('flacso_section_oferta_educativa_normalize_card')) {
    /**
     * @param array<string,mixed> $card
     * @return array<string,string>
     */
    function flacso_section_oferta_educativa_normalize_card(array $card): array
    {
        $url = class_exists('Flacso_Main_Page_Settings')
            ? Flacso_Main_Page_Settings::normalize_url_output((string) ($card['url'] ?? ''))
            : esc_url((string) ($card['url'] ?? ''));

        return [
            'title' => trim(wp_strip_all_tags((string) ($card['title'] ?? ''))),
            'desc' => (string) ($card['desc'] ?? ''),
            'image' => esc_url((string) ($card['image'] ?? '')),
            'url' => $url !== '' ? $url : '#',
        ];
    }
}

if (!function_exists('flacso_section_oferta_educativa_get_cards')) {
    /**
     * @return array<int,array<string,string>>
     */
    function flacso_section_oferta_educativa_get_cards(): array
    {
        $section = class_exists('Flacso_Main_Page_Settings')
            ? Flacso_Main_Page_Settings::get_section('posgrados')
            : [];
        $cards = [];

        if (!empty($section['cards']) && is_array($section['cards'])) {
            foreach ($section['cards'] as $card) {
                if (!is_array($card)) {
                    continue;
                }

                $normalized = flacso_section_oferta_educativa_normalize_card($card);
                if ($normalized['title'] !== '') {
                    $cards[] = $normalized;
                }
            }
        }

        if ($cards) {
            return $cards;
        }

        $fallback = [
            [
                'title' => 'Maestrías',
                'url' => '/formacion/maestrias/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-9.png',
                'desc' => 'Formación académica avanzada que culmina en un trabajo de investigación y abre el camino hacia estudios doctorales.',
            ],
            [
                'title' => 'Especializaciones',
                'url' => '/formacion/especializaciones/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-1.png',
                'desc' => 'Profundización y actualización de marcos teóricos, metodologías y herramientas profesionales.',
            ],
            [
                'title' => 'Diplomas',
                'url' => '/formacion/diplomas/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-5-1024x1024.png',
                'desc' => 'Propuestas que combinan análisis temático, herramientas prácticas y trayectos de formación articulados.',
            ],
            [
                'title' => 'Diplomados',
                'url' => '/formacion/diplomados/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-3.png',
                'desc' => 'Trayectos académicos orientados a la actualización y continuidad hacia estudios de maestría.',
            ],
            [
                'title' => 'Seminarios',
                'url' => '/formacion/seminarios/',
                'image' => 'https://flacso.edu.uy/wp-content/uploads/2023/08/IMAGE-SITIO-WEB-2.png',
                'desc' => 'Espacios intensivos de actualización con enfoque práctico y acompañamiento docente especializado.',
            ],
        ];

        return array_map('flacso_section_oferta_educativa_normalize_card', $fallback);
    }
}

if (!function_exists('flacso_section_oferta_educativa_render')) {
    /**
     * @param array<string,mixed> $atts
     */
    function flacso_section_oferta_educativa_render($atts = []): string
    {
        $section = class_exists('Flacso_Main_Page_Settings')
            ? Flacso_Main_Page_Settings::get_section('posgrados')
            : [];
        $show_title = !array_key_exists('show_title', $section) || !empty($section['show_title']);
        $title = trim(wp_strip_all_tags((string) ($section['title'] ?? 'Nuestra Oferta Educativa')));
        $intro = (string) ($section['intro'] ?? '');
        $cards = flacso_section_oferta_educativa_get_cards();

        if ($title === '' || strcasecmp($title, 'Nuestros Posgrados') === 0) {
            $title = 'Nuestra Oferta Educativa';
        }
        if (!$cards) {
            return '';
        }

        ob_start();
        ?>
        <section class="flacso-oferta-mosaico" aria-labelledby="flacso-oferta-mosaico-title">
            <div class="flacso-content-shell">
                <header class="flacso-oferta-mosaico__header">
                    <?php if ($show_title && $title !== '') : ?>
                        <h2 id="flacso-oferta-mosaico-title"><?php echo esc_html($title); ?></h2>
                    <?php endif; ?>
                    <?php if ($intro !== '') : ?>
                        <div class="flacso-oferta-mosaico__intro"><?php echo wp_kses_post($intro); ?></div>
                    <?php endif; ?>
                </header>

                <div class="flacso-oferta-mosaico__grid">
                    <?php foreach ($cards as $index => $card) : ?>
                        <?php
                        $image_id = $card['image'] !== '' ? attachment_url_to_postid($card['image']) : 0;
                        $size_class = $index % 5 === 0
                            ? 'flacso-oferta-mosaico__card--featured'
                            : ($index % 5 === 3 ? 'flacso-oferta-mosaico__card--wide' : '');
                        ?>
                        <a class="flacso-oferta-mosaico__card <?php echo esc_attr($size_class); ?>" href="<?php echo esc_url($card['url']); ?>">
                            <div class="flacso-oferta-mosaico__media">
                                <?php if ($image_id) : ?>
                                    <?php
                                    echo wp_get_attachment_image(
                                        $image_id,
                                        'large',
                                        false,
                                        [
                                            'alt' => $card['title'],
                                            'loading' => 'lazy',
                                            'decoding' => 'async',
                                            'sizes' => '(max-width: 700px) 100vw, (max-width: 1100px) 50vw, 40vw',
                                        ]
                                    );
                                    ?>
                                <?php elseif ($card['image'] !== '') : ?>
                                    <img src="<?php echo esc_url($card['image']); ?>" alt="<?php echo esc_attr($card['title']); ?>" loading="lazy" decoding="async" width="900" height="600">
                                <?php endif; ?>
                            </div>
                            <div class="flacso-oferta-mosaico__content">
                                <h3><?php echo esc_html($card['title']); ?></h3>
                                <?php if (trim(wp_strip_all_tags($card['desc'])) !== '') : ?>
                                    <div class="flacso-oferta-mosaico__description"><?php echo wp_kses_post($card['desc']); ?></div>
                                <?php endif; ?>
                                <span class="flacso-oferta-mosaico__link"><?php esc_html_e('Conocer la propuesta', 'flacso-main-page'); ?> <span aria-hidden="true">→</span></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_section_home_academic_shortcuts_render')) {
    /**
     * Accesos breves de la portada alimentados por la misma configuración
     * de la oferta educativa. Evita duplicar títulos y destinos.
     */
    function flacso_section_home_academic_shortcuts_render(): string
    {
        $cards = flacso_section_oferta_educativa_get_cards();
        if (!$cards) {
            return '';
        }

        $degree_cards = [];
        $seminar_card = null;
        foreach ($cards as $card) {
            if (stripos($card['title'], 'seminario') !== false) {
                $seminar_card = $card;
                continue;
            }
            if (count($degree_cards) < 4) {
                $degree_cards[] = $card;
            }
        }

        if (!$degree_cards) {
            return '';
        }

        ob_start();
        ?>
        <nav class="flacso-academic-shortcuts" aria-label="<?php esc_attr_e('Accesos a la oferta académica', 'flacso-main-page'); ?>">
            <div class="flacso-content-shell">
                <div class="flacso-academic-shortcuts__panel">
                    <div class="flacso-academic-shortcuts__header">
                        <strong><?php esc_html_e('Encontrá tu formación', 'flacso-main-page'); ?></strong>
                        <span><?php esc_html_e('Accesos directos a la oferta académica', 'flacso-main-page'); ?></span>
                    </div>
                    <div class="flacso-academic-shortcuts__grid">
                        <?php foreach ($degree_cards as $card) : ?>
                            <?php $type_label = strcasecmp($card['title'], 'Diplomas') === 0 ? __('Formación', 'flacso-main-page') : __('Posgrado', 'flacso-main-page'); ?>
                            <a class="flacso-academic-shortcuts__link" href="<?php echo esc_url($card['url']); ?>">
                                <span><?php echo esc_html($type_label); ?></span>
                                <strong><?php echo esc_html($card['title']); ?> <span aria-hidden="true">↗</span></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($seminar_card) : ?>
                        <a class="flacso-academic-shortcuts__seminars" href="<?php echo esc_url($seminar_card['url']); ?>">
                            <span>
                                <strong><?php echo esc_html($seminar_card['title']); ?></strong>
                                <small><?php esc_html_e('Formación intensiva y de corta duración · consultá los próximos inicios', 'flacso-main-page'); ?></small>
                            </span>
                            <span aria-hidden="true">→</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('flacso_section_posgrados_normalize_card')) {
    function flacso_section_posgrados_normalize_card(array $card): array
    {
        return flacso_section_oferta_educativa_normalize_card($card);
    }
}

if (!function_exists('flacso_section_posgrados_get_cards')) {
    function flacso_section_posgrados_get_cards(): array
    {
        return flacso_section_oferta_educativa_get_cards();
    }
}

if (!function_exists('flacso_section_posgrados_render')) {
    function flacso_section_posgrados_render($atts = []): string
    {
        return flacso_section_oferta_educativa_render($atts);
    }
}
