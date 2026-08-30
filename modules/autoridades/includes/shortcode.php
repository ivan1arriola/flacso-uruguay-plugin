<?php
/**
 * Shortcode [flacso_autoridades] para el frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('flacso_autoridades', function () {
    $data = function_exists('flacso_autoridades_get_data') ? flacso_autoridades_get_data() : [];

    if (empty($data['direccion']) && empty($data['secciones'])) {
        return '';
    }

    ob_start();

    // Función auxiliar DRY para renderizar tarjetas de autoridad
    $render_autoridad_card = function ($persona) {
        $doc_id   = intval($persona['docente_id'] ?? 0);
        $cargo    = esc_html($persona['cargo'] ?? '');
        $programa = esc_html($persona['programa'] ?? '');
        $enlace   = esc_url($persona['enlace'] ?? '');

        // Datos para ingreso manual
        $prefijo_manual = trim($persona['prefijo'] ?? '');
        $nombre_manual  = trim($persona['nombre_manual'] ?? '');
        $titulo_manual  = trim($persona['titulo_academico'] ?? '');
        $cv_manual      = trim($persona['cv'] ?? '');

        $nombre_completo  = '';
        $titulo_academico = '';
        $cv_raw           = '';
        $avatar_html      = '';
        $correos          = [];
        $redes            = [];
        $has_docente      = ($doc_id > 0 && get_post_status($doc_id) === 'publish');

        if ($has_docente) {
            $nombre_base = function_exists('dp_nombre_completo') ? dp_nombre_completo($doc_id, false) : get_the_title($doc_id);
            if (!$nombre_base || $nombre_base === (string)$doc_id) {
                $nombre_base = get_the_title($doc_id);
            }
            
            // Tomar siempre los datos oficiales del perfil oficial del docente
            $prefijo = trim((string) get_post_meta($doc_id, 'prefijo_abrev', true));
            
            if ($prefijo !== '' && mb_strpos($nombre_base, $prefijo) === false) {
                $nombre_completo = trim($prefijo . ' ' . trim($nombre_base));
            } else {
                $nombre_completo = trim($nombre_base);
            }

            $titulo_academico = (string) get_post_meta($doc_id, 'titulo_academico', true);
            $cv_raw           = (string) get_post_meta($doc_id, 'cv', true);
            
            if (function_exists('dp_avatar_markup')) {
                $avatar_html = dp_avatar_markup($doc_id, $nombre_completo, 140, 'flacso-autoridad__avatar-img');
            } else {
                $thumb_url = get_the_post_thumbnail_url($doc_id, 'medium');
                if ($thumb_url) {
                    $avatar_html = '<div class="flacso-autoridad__avatar-img"><img src="' . esc_url($thumb_url) . '" alt="' . esc_attr($nombre_completo) . '"></div>';
                }
            }

            if (function_exists('dp_get_docente_socials')) {
                $redes = dp_get_docente_socials($doc_id);
            }
        } else {
            // Ingreso Manual
            $nombre_completo  = trim(($prefijo_manual !== '' ? $prefijo_manual . ' ' : '') . trim($nombre_manual));
            $titulo_academico = $titulo_manual;
            $cv_raw           = $cv_manual;

            $iniciales = function_exists('dp_iniciales') ? dp_iniciales($nombre_manual ?: 'Autoridad', '') : mb_substr($nombre_manual ?: 'FL', 0, 2);
            $avatar_html = '<div class="flacso-autoridad__avatar-img" style="background: linear-gradient(135deg, #1d3a72 0%, #0f1e3b 100%); color: #fed222;">' . esc_html($iniciales) . '</div>';
        }

        $out = '<article class="flacso-autoridad-card">';
        if ($cargo) {
            $out .= '<span class="flacso-autoridad__kicker" style="margin-bottom: 0.75rem;">' . $cargo . '</span>';
        }

        if ($programa) {
            if ($enlace) {
                $out .= '<a href="' . $enlace . '" target="_blank" rel="noopener noreferrer" class="flacso-autoridad__programa" style="margin-bottom: 1.5rem;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 58h14M6 12h14"/></svg> ' . $programa . '</a>';
            } else {
                $out .= '<div class="flacso-autoridad__programa" style="margin-bottom: 1.5rem;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;vertical-align:middle;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg> ' . $programa . '</div>';
            }
        }

        $out .= '<div class="flacso-autoridad__avatar">' . $avatar_html . '</div>';
        $out .= '<h3 class="flacso-autoridad__name">' . esc_html($nombre_completo) . '</h3>';

        if ($titulo_academico) {
            $out .= '<p class="flacso-autoridad__title" style="margin-bottom: 0;">' . esc_html($titulo_academico) . '</p>';
        }

        if ($cv_raw !== '') {
            $clean_cv = function_exists('dp_safe_cv_html') ? dp_safe_cv_html(wpautop($cv_raw)) : wpautop(wp_kses_post($cv_raw));
            $out .= '<div class="flacso-autoridad__cv">' . $clean_cv . '</div>';
        }

        if (!empty($redes)) {
            $out .= '<div class="flacso-autoridad__contact-bar">';
            foreach ($redes as $r) {
                if (!empty($r['url'])) {
                    $out .= '<a href="' . esc_url($r['url']) . '" target="_blank" rel="noopener noreferrer" class="flacso-contact-tag" title="' . esc_attr($r['label'] ?: 'Red social') . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;vertical-align:middle;"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>' . esc_html($r['label'] ?: 'Perfil') . '</a>';
                }
            }
            $out .= '</div>';
        }

        $out .= '</article>';
        return $out;
    };
    ?>

    <style>
        .flacso-autoridades-wrapper {
            --p1: var(--global-palette1, #1d3a72);
            --p2: var(--global-palette2, #fed222);
            --p3: var(--global-palette3, #2563eb);
            --text-main: var(--global-palette4, #1f2937);
            --text-muted: var(--global-palette5, #64748b);
            --bg-card: var(--global-palette8, #ffffff);
            --bg-page: var(--global-palette9, #f8fafc);
            --border-color: #e2e8f0;
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 8px;

            font-family: var(--global-body-font-family, system-ui, -apple-system, sans-serif);
            color: var(--text-main);
            margin: 2rem 0 4rem;
        }

        .flacso-autoridades-hero {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(29, 58, 114, 0.92) 100%),
                        url("<?php echo esc_url($data['imagen_fondo']); ?>");
            background-size: cover;
            background-position: center;
            padding: 4.5rem 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(29, 58, 114, 0.15);
            margin-bottom: 3rem;
        }

        .flacso-autoridades-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, rgba(254, 210, 34, 0.15), transparent 70%);
            pointer-events: none;
        }

        .flacso-autoridades-hero h1 {
            position: relative;
            z-index: 2;
            color: #ffffff;
            font-size: clamp(2.25rem, 4vw, 3.5rem);
            font-weight: 850;
            line-height: 1.15;
            margin: 0 0 1rem;
            letter-spacing: -0.025em;
        }

        .flacso-autoridades-hero p {
            position: relative;
            z-index: 2;
            color: #cbd5e1;
            font-size: clamp(1.1rem, 2vw, 1.35rem);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.5;
        }

        .flacso-hero-accent {
            display: inline-block;
            width: 80px;
            height: 4px;
            background: var(--p2);
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .flacso-autoridades-tabs-nav {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
            padding: 0.5rem;
            background: rgba(241, 245, 249, 0.8);
            backdrop-filter: blur(12px);
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            max-width: fit-content;
            margin-inline: auto;
        }

        .flacso-tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 1.05rem;
            padding: 0.85rem 1.75rem;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: inherit;
        }

        .flacso-tab-btn:hover {
            color: var(--p1);
            background: rgba(255, 255, 255, 0.6);
        }

        .flacso-tab-btn.active {
            background: var(--p1);
            color: #ffffff;
            box-shadow: 0 10px 25px rgba(29, 58, 114, 0.3);
            transform: translateY(-2px);
        }

        .flacso-tab-panel {
            display: none;
            animation: panelFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .flacso-tab-panel.active {
            display: block;
        }

        @keyframes panelFadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .flacso-autoridades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2.5rem;
            align-items: start;
        }

        .flacso-direccion-hero-grid {
            display: grid;
            grid-template-columns: minmax(320px, 600px);
            justify-content: center;
            margin-bottom: 3rem;
        }

        .flacso-autoridad-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            z-index: 1;
            box-sizing: border-box;
            width: 100%;
        }

        .flacso-autoridad-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--p1);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .flacso-autoridad-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(29, 58, 114, 0.12);
            border-color: rgba(29, 58, 114, 0.2);
        }

        .flacso-autoridad-card:hover::after {
            opacity: 1;
        }

        .flacso-autoridad__kicker {
            background: var(--p2);
            color: var(--p1);
            font-size: 0.75rem;
            font-weight: 850;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.4rem 1rem;
            border-radius: 999px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(254, 210, 34, 0.25);
        }

        .flacso-autoridad__avatar {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .flacso-autoridad__avatar-img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffffff;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
            font-weight: bold;
            background: var(--p1);
            color: var(--p2);
            overflow: hidden;
            margin-inline: auto;
        }

        .flacso-autoridad__avatar-img img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block;
        }

        .flacso-autoridad__name {
            color: var(--p1);
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.25;
            margin: 0 0 0.5rem;
            text-wrap: balance;
        }

        .flacso-autoridad__title {
            color: var(--text-muted);
            font-size: 0.98rem;
            font-weight: 600;
            line-height: 1.4;
            margin: 0 0 1.5rem;
        }

        .flacso-autoridad__programa {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm);
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            color: var(--p1);
            font-weight: 600;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        a.flacso-autoridad__programa:hover {
            background: var(--p1);
            color: #ffffff;
            border-color: var(--p1);
            transform: scale(1.02);
        }

        .flacso-autoridad__cv {
            margin-top: 0.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            text-align: left;
            font-size: 0.98rem;
            line-height: 1.7;
            color: #334155;
            width: 100%;
        }

        .flacso-autoridad__cv p {
            margin-bottom: 1rem;
        }
        .flacso-autoridad__cv p:last-child {
            margin-bottom: 0;
        }

        .flacso-autoridad__contact-bar {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
        }

        .flacso-contact-tag {
            background: #f1f5f9;
            color: var(--p1);
            font-size: 0.82rem;
            font-weight: 700;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .flacso-contact-tag:hover {
            background: var(--p1);
            color: #ffffff;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .flacso-autoridades-hero {
                padding: 3rem 1.5rem;
            }
            .flacso-autoridades-tabs-nav {
                border-radius: var(--radius-md);
                padding: 0.75rem;
                width: 100%;
                box-sizing: border-box;
            }
            .flacso-tab-btn {
                width: 100%;
                text-align: center;
                border-radius: var(--radius-sm);
                padding: 0.75rem 1rem;
            }
            .flacso-autoridad-card {
                padding: 1.75rem;
            }
        }
    </style>

    <div class="flacso-autoridades-wrapper">
        <section class="flacso-autoridades-hero" aria-label="<?php esc_attr_e('Cabecera de Autoridades', 'flacso-uruguay'); ?>">
            <div class="flacso-hero-accent"></div>
            <h1><?php esc_html_e('Autoridades de FLACSO Uruguay', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Conoce el equipo directivo, académico y administrativo que guía nuestra misión en la docencia e investigación.', 'flacso-uruguay'); ?></p>
        </section>

        <nav class="flacso-autoridades-tabs-nav" role="tablist" aria-label="<?php esc_attr_e('Navegación de Secciones', 'flacso-uruguay'); ?>">
            <button
                type="button"
                class="flacso-tab-btn active"
                role="tab"
                aria-selected="true"
                aria-controls="flacso-tabpanel-direccion"
                id="flacso-tab-direccion"
                data-tab-target="flacso-tabpanel-direccion"
            >
                <?php esc_html_e('Dirección', 'flacso-uruguay'); ?>
            </button>
            <?php foreach ($data['secciones'] as $i => $seccion): ?>
                <button
                    type="button"
                    class="flacso-tab-btn"
                    role="tab"
                    aria-selected="false"
                    aria-controls="flacso-tabpanel-<?php echo esc_attr($i); ?>"
                    id="flacso-tab-<?php echo esc_attr($i); ?>"
                    data-tab-target="flacso-tabpanel-<?php echo esc_attr($i); ?>"
                >
                    <?php echo esc_html($seccion['titulo']); ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="flacso-autoridades-panels">
            <div id="flacso-tabpanel-direccion" class="flacso-tab-panel active" role="tabpanel" aria-labelledby="flacso-tab-direccion">
                <div class="flacso-direccion-hero-grid">
                    <?php echo $render_autoridad_card($data['direccion']); ?>
                </div>
            </div>

            <?php foreach ($data['secciones'] as $i => $seccion): ?>
                <div
                    id="flacso-tabpanel-<?php echo esc_attr($i); ?>"
                    class="flacso-tab-panel"
                    role="tabpanel"
                    aria-labelledby="flacso-tab-<?php echo esc_attr($i); ?>"
                >
                    <div class="flacso-autoridades-grid">
                        <?php 
                        if (!empty($seccion['incluir_direccion'])) {
                            echo $render_autoridad_card($data['direccion']);
                        }

                        foreach ($seccion['personas'] as $persona) {
                            echo $render_autoridad_card($persona);
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    (function () {
        const wrapper = document.querySelector('.flacso-autoridades-wrapper');
        if (!wrapper) return;

        const tabBtns = wrapper.querySelectorAll('.flacso-tab-btn');
        const tabPanels = wrapper.querySelectorAll('.flacso-tab-panel');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-tab-target');
                tabBtns.forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
                tabPanels.forEach(p => p.classList.remove('active'));

                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
                const targetPanel = document.getElementById(targetId);
                if (targetPanel) targetPanel.classList.add('active');
            });
        });
    })();
    </script>

    <?php
    return ob_get_clean();
});
