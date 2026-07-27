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
