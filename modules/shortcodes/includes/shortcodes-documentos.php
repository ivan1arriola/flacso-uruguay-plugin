<?php

if (!function_exists('programa_calendario_malla_shortcode')) {
    /**
     * Shortcode principal para Calendario y Malla.
     */
    function programa_calendario_malla_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'url_calendario'   => '',
                'url_malla'        => '',
                'texto_calendario' => 'Calendario Académico',
                'texto_malla'      => 'Malla Curricular',
                'clase'            => '',
            ),
            $atts,
            'programa_calendario_malla'
        );

        $calendar_url = $atts['url_calendario'];
        if (!empty($calendar_url) && function_exists('flacso_get_pdf_proxy_url')) {
            $proxied = flacso_get_pdf_proxy_url($calendar_url, $atts['texto_calendario']);
            if ($proxied) {
                $calendar_url = $proxied;
            }
        }

        $malla_url = $atts['url_malla'];
        if (!empty($malla_url) && function_exists('flacso_get_pdf_proxy_url')) {
            $proxied = flacso_get_pdf_proxy_url($malla_url, $atts['texto_malla']);
            if ($proxied) {
                $malla_url = $proxied;
            }
        }

        $shortcode_id = 'gc-calendario-malla-' . wp_rand(1000, 9999);

        ob_start(); ?>
        <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-calendario-malla-wrapper <?php echo esc_attr($atts['clase']); ?>">
            <div class="gc-documentos-grid">
                <?php if (!empty($calendar_url)) : ?>
                <div class="gc-documento-item">
                    <div class="gc-documento-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="gc-documento-content">
                        <h3 class="gc-documento-title"><?php echo esc_html($atts['texto_calendario']); ?></h3>
                        <p class="gc-documento-desc"><?php esc_html_e('Abrí el cronograma del programa como PDF en tu navegador.', 'flacso-shortcodes'); ?></p>
                        <div class="gc-button-container">
                            <a href="<?php echo esc_url($calendar_url); ?>"
                               class="gc-action-button"
                               rel="noopener"
                               target="_blank">
                                <i class="bi bi-filetype-pdf"></i>
                                <?php esc_html_e('Ver PDF', 'flacso-shortcodes'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($malla_url)) : ?>
                <div class="gc-documento-item">
                    <div class="gc-documento-icon">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <div class="gc-documento-content">
                        <h3 class="gc-documento-title"><?php echo esc_html($atts['texto_malla']); ?></h3>
                        <p class="gc-documento-desc"><?php esc_html_e('Abrí la malla curricular como PDF en tu navegador.', 'flacso-shortcodes'); ?></p>
                        <div class="gc-button-container">
                            <a href="<?php echo esc_url($malla_url); ?>"
                               class="gc-action-button"
                               rel="noopener"
                               target="_blank">
                                <i class="bi bi-filetype-pdf"></i>
                                <?php esc_html_e('Ver PDF', 'flacso-shortcodes'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        #<?php echo esc_attr($shortcode_id); ?> {
            margin: 2rem 0;
            padding: 0 1rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-documentos-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-documento-item {
            background: var(--global-palette8);
            border-radius: 0.75rem;
            padding: 1.5rem;
            border-left: 6px solid var(--global-palette1);
            border-top: 1px solid rgba(15,26,45,.08);
            border-right: 1px solid rgba(15,26,45,.08);
            border-bottom: 1px solid rgba(15,26,45,.08);
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-documento-icon { margin-bottom: 1rem; }
        #<?php echo esc_attr($shortcode_id); ?> .gc-documento-icon i {
            font-size: 2rem; color: var(--global-palette1);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-documento-content {
            flex: 1; display: flex; flex-direction: column; height: 100%;
        }
        #<?php echo esc_attr($shortcode_id); ?> .gc-documento-title {
            color: var(--global-palette1); font-weight: 800; margin: 0 0 .5rem 0; font-size: 1.25rem;
        }
        #<?php echo esc_attr($shortcode_id); ?> .gc-documento-desc {
            color: var(--global-palette4); line-height: 1.55; margin: 0 0 1.25rem 0; font-size: 1rem; flex: 1;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-button-container {
            margin-top: auto; min-height: 48px; display: flex; align-items: flex-end;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-action-button {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            background: var(--global-palette-btn-bg); color: var(--global-palette-btn);
            padding: .75rem 1.25rem; border-radius: .6rem; text-decoration: none; font-weight: 700; font-size: 1rem;
            transition: all .25s ease; border: 2px solid var(--global-palette-btn-bg); width: 100%; min-height: 48px; box-sizing: border-box;
        }
        #<?php echo esc_attr($shortcode_id); ?> .gc-action-button:hover {
            background: var(--global-palette-btn-bg-hover); color: var(--global-palette-btn-hover);
            transform: translateY(-2px); border-color: var(--global-palette-btn-bg-hover);
        }

        @media (min-width: 768px) {
            #<?php echo esc_attr($shortcode_id); ?> .gc-documentos-grid {
                grid-template-columns: repeat(2, 1fr); gap: 1.75rem;
            }
        }
        @media (min-width: 1024px) {
            #<?php echo esc_attr($shortcode_id); ?> { margin: 3rem 0; padding: 0; }
            #<?php echo esc_attr($shortcode_id); ?> .gc-documento-item { padding: 1.75rem; }
        }
        @media (max-width: 480px) {
            #<?php echo esc_attr($shortcode_id); ?> { padding: 0 .5rem; }
            #<?php echo esc_attr($shortcode_id); ?> .gc-documento-item { padding: 1.25rem; }
            #<?php echo esc_attr($shortcode_id); ?> .gc-action-button { padding: .875rem 1.1rem; min-height: 46px; }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('programa_calendario_malla', 'programa_calendario_malla_shortcode');

if (!function_exists('programa_iape_calendario_malla_shortcode')) {
    /**
     * Shortcode especial para el programa IAPE (calendario + malla en tabla).
     */
    function programa_iape_calendario_malla_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'url_calendario'   => '',
                'texto_calendario' => 'Calendario Académico',
            ),
            $atts,
            'programa_iape_calendario_malla'
        );

        $calendar_url = $atts['url_calendario'];
        if (!empty($calendar_url) && function_exists('flacso_get_pdf_proxy_url')) {
            $proxied = flacso_get_pdf_proxy_url($calendar_url, $atts['texto_calendario']);
            if ($proxied) {
                $calendar_url = $proxied;
            }
        }

        $shortcode_id = 'gc-iape-calendario-malla-' . wp_rand(1000, 9999);

        $malla_data = array(
            array('Módulo', 'Duración', 'Créditos', 'Encuentros sincrónicos', 'Horas totales'),
            array('Introducción a la IA Generativa y su Potencial Educativo', '4 semanas', '2', '1 (2 hs)', '32'),
            array('IA como Asistente Pedagógico: Planificación, Diseño y Evaluación', '6 semanas', '3', '1 (2 hs)', '48'),
            array('Ética, Desafíos y el Futuro de la IA en la Educación Universitaria', '2 semanas', '1', '1 (2 hs)', '16'),
            array('Trabajo final', '2 semanas', '1', '—', '16'),
            array('Totales', '14 semanas', '7', '6 horas', '112'),
        );

        ob_start();
        ?>
        <div id="<?php echo esc_attr($shortcode_id); ?>" class="gc-iape-calendario-malla-wrapper">
            <div class="gc-iape-documentos-grid">
                <?php if (!empty($calendar_url)) : ?>
                <div class="gc-iape-documento-item">
                    <div class="gc-iape-documento-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="gc-iape-documento-content">
                        <h3 class="gc-iape-documento-title"><?php echo esc_html($atts['texto_calendario']); ?></h3>
                        <p class="gc-iape-documento-desc">Consulta las fechas importantes y cronograma del programa</p>
                        <div class="gc-iape-button-container">
                            <a href="<?php echo esc_url($calendar_url); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="gc-iape-action-button">
                                <i class="bi bi-file-earmark-text"></i>
                                Ver Calendario
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="gc-iape-malla-item">
                    <div class="gc-iape-malla-header">
                        <div class="gc-iape-malla-icon">
                            <i class="bi bi-journal-bookmark"></i>
                        </div>
                        <div class="gc-iape-malla-title-content">
                            <h3 class="gc-iape-malla-title">Malla Curricular</h3>
                            <p class="gc-iape-malla-desc">Estructura completa del plan de estudios</p>
                        </div>
                    </div>

                    <div class="gc-iape-table-container">
                        <table class="gc-iape-malla-table">
                            <thead>
                                <tr>
                                    <?php foreach ($malla_data[0] as $header) : ?>
                                        <th><?php echo esc_html($header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i < count($malla_data); $i++) : ?>
                                    <tr class="<?php echo ($i === count($malla_data) - 1) ? 'gc-iape-totales-row' : ''; ?>">
                                        <?php foreach ($malla_data[$i] as $cell) : ?>
                                            <td>
                                                <?php if ($i === count($malla_data) - 1) : ?>
                                                    <strong><?php echo esc_html($cell); ?></strong>
                                                <?php else : ?>
                                                    <?php echo esc_html($cell); ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <style>
        #<?php echo esc_attr($shortcode_id); ?> {
            margin: 2rem 0;
            padding: 0 1rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documentos-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-item {
            background: var(--global-palette8);
            border-radius: 0.75rem;
            padding: 2rem;
            border-top: 4px solid var(--global-palette1);
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-icon {
            margin-bottom: 1.25rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-icon i {
            font-size: 2.5rem;
            color: var(--global-palette1);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-title {
            color: var(--global-palette1);
            font-weight: 700;
            margin: 0 0 0.75rem 0;
            font-size: 1.375rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-desc {
            color: var(--global-palette4);
            line-height: 1.5;
            margin: 0 0 1.5rem 0;
            font-size: 1.05rem;
            flex: 1;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-button-container {
            margin-top: auto;
            min-height: 50px;
            display: flex;
            align-items: flex-end;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--global-palette-btn-bg);
            color: var(--global-palette-btn);
            padding: 0.875rem 1.75rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            border: 2px solid var(--global-palette-btn-bg);
            width: 100%;
            min-height: 50px;
            box-sizing: border-box;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-action-button:hover {
            background: var(--global-palette-btn-bg-hover);
            color: var(--global-palette-btn-hover);
            transform: translateY(-2px);
            border-color: var(--global-palette-btn-bg-hover);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-item {
            background: var(--global-palette8);
            border-radius: 0.75rem;
            padding: 2rem;
            border-top: 4px solid var(--global-palette2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-icon i {
            font-size: 2.5rem;
            color: var(--global-palette2);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-title-content {
            flex: 1;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-title {
            color: var(--global-palette1);
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            font-size: 1.375rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-desc {
            color: var(--global-palette4);
            line-height: 1.5;
            margin: 0;
            font-size: 1.05rem;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-table-container {
            overflow-x: auto;
            border-radius: 0.5rem;
            border: 1px solid var(--global-palette7);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            min-width: 600px;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table th {
            background: var(--global-palette1);
            color: var(--global-palette9);
            font-weight: 600;
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--global-palette2);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table td {
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid var(--global-palette7);
            color: var(--global-palette4);
            line-height: 1.4;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table tr:last-child td {
            border-bottom: none;
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table tr:nth-child(even) {
            background: var(--global-palette8);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table tr:nth-child(odd) {
            background: var(--global-palette9);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table tr:hover {
            background: var(--global-palette7);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-totales-row {
            background: var(--global-palette1) !important;
            color: var(--global-palette9);
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-totales-row td {
            color: var(--global-palette9);
            font-weight: 600;
            border-bottom: none;
        }

        @media only screen and (min-width: 768px) {
            #<?php echo esc_attr($shortcode_id); ?> {
                margin: 2.5rem 0;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documentos-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2.5rem;
                align-items: stretch;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table {
                font-size: 1rem;
                min-width: auto;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table th,
            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table td {
                padding: 1rem;
            }
        }

        @media only screen and (min-width: 1024px) {
            #<?php echo esc_attr($shortcode_id); ?> {
                margin: 3rem 0;
                padding: 0;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-item,
            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-item {
                padding: 2.5rem;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documentos-grid {
                gap: 3rem;
            }
        }

        @media only screen and (max-width: 480px) {
            #<?php echo esc_attr($shortcode_id); ?> {
                padding: 0 0.5rem;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-documento-item,
            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-item {
                padding: 1.5rem;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-header {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-action-button {
                padding: 0.875rem 1.25rem;
                min-height: 48px;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table {
                font-size: 0.85rem;
            }

            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table th,
            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-malla-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        #<?php echo esc_attr($shortcode_id); ?> .gc-iape-action-button:focus {
            outline: 2px solid var(--global-palette2);
            outline-offset: 2px;
        }

        @media (prefers-reduced-motion: reduce) {
            #<?php echo esc_attr($shortcode_id); ?> .gc-iape-action-button {
                transition: none;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('programa_iape_calendario_malla', 'programa_iape_calendario_malla_shortcode');
