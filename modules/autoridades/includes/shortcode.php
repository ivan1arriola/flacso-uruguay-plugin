<?php
/**
 * Shortcode [flacso_autoridades] para el frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('flacso_autoridades', function () {
    $data = function_exists('flacso_autoridades_get_data') ? flacso_autoridades_get_data() : [];

    if (empty($data['secciones']) || !is_array($data['secciones'])) {
        return '';
    }

    ob_start();
    ?>

    <style>
        /* Ocultar elementos heredados del tema que puedan interferir con la cabecera full width */
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

        /* Banner de Cabecera / Hero */
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

        /* Navegación por Pestañas (Tabs) */
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

        /* Paneles y Grilla */
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
            gap: 2rem;
            align-items: stretch;
        }

        /* Tarjeta de Autoridad */
        .flacso-autoridad-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 2.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            z-index: 1;
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
            transform: translateY(-6px);
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
            width: 130px;
            height: 130px;
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
        }

        .flacso-autoridad__avatar-img img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block;
        }

        .flacso-autoridad__name {
            color: var(--p1);
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin: 0 0 0.5rem;
            text-wrap: balance;
        }

        .flacso-autoridad__title {
            color: var(--text-muted);
            font-size: 0.95rem;
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

        .flacso-autoridad__bio-btn {
            margin-top: auto;
            background: #f1f5f9;
            color: var(--p1);
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.25s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .flacso-autoridad__bio-btn:hover {
            background: var(--p1);
            color: #ffffff;
            border-color: var(--p1);
            box-shadow: 0 8px 20px rgba(29, 58, 114, 0.2);
        }

        /* Modal HTML5 Nativo (<dialog>) */
        dialog.flacso-autoridad-modal {
            padding: 0;
            border: none;
            border-radius: var(--radius-lg);
            background: #ffffff;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            max-width: 720px;
            width: 92%;
            max-height: 85vh;
            overflow: hidden;
            color: var(--text-main);
            font-family: inherit;
        }

        dialog.flacso-autoridad-modal::backdrop {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(10px);
            animation: backdropFade 0.3s ease;
        }

        dialog.flacso-autoridad-modal[open] {
            animation: modalPopIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }

        @keyframes backdropFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes modalPopIn {
            from { opacity: 0; transform: scale(0.92) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .flacso-modal__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2rem 2.5rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .flacso-modal__header-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .flacso-modal__header-avatar {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #ffffff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            flex-shrink: 0;
            background: var(--p1);
            color: var(--p2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: bold;
        }

        .flacso-modal__header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .flacso-modal__header-text h3 {
            margin: 0 0 0.25rem;
            color: var(--p1);
            font-size: 1.65rem;
            font-weight: 850;
            line-height: 1.15;
        }

        .flacso-modal__kicker {
            display: inline-block;
            background: var(--p2);
            color: var(--p1);
            font-size: 0.7rem;
            font-weight: 850;
            text-transform: uppercase;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            margin-bottom: 0.5rem;
        }

        .flacso-modal__title-acad {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
        }

        .flacso-modal__close {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }

        .flacso-modal__close:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
            transform: rotate(90deg);
        }

        .flacso-modal__body {
            padding: 2.5rem;
            overflow-y: auto;
            max-height: calc(85vh - 140px);
            line-height: 1.75;
            font-size: 1.05rem;
            color: var(--text-main);
        }

        .flacso-modal__body p {
            margin-bottom: 1.25rem;
        }
        .flacso-modal__body p:last-child {
            margin-bottom: 0;
        }

        .flacso-modal__contact {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .flacso-modal__contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            color: var(--p1);
            padding: 0.6rem 1.25rem;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .flacso-modal__contact-btn:hover {
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
            .flacso-modal__header {
                flex-direction: column;
                align-items: flex-start;
                padding: 2rem 1.5rem;
            }
            .flacso-modal__body {
                padding: 1.5rem;
            }
        }
    </style>

    <div class="flacso-autoridades-wrapper">
        <!-- Banner Hero -->
        <section class="flacso-autoridades-hero" aria-label="<?php esc_attr_e('Cabecera de Autoridades', 'flacso-uruguay'); ?>">
            <div class="flacso-hero-accent"></div>
            <h1><?php esc_html_e('Autoridades de FLACSO Uruguay', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Conoce el equipo directivo, académico y administrativo que guía nuestra misión en la docencia e investigación.', 'flacso-uruguay'); ?></p>
        </section>

        <!-- Navegación por Pestañas -->
        <nav class="flacso-autoridades-tabs-nav" role="tablist" aria-label="<?php esc_attr_e('Navegación de Secciones', 'flacso-uruguay'); ?>">
            <?php foreach ($data['secciones'] as $i => $seccion): ?>
                <button
                    type="button"
                    class="flacso-tab-btn <?php echo ($i === 0) ? 'active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo ($i === 0) ? 'true' : 'false'; ?>"
                    aria-controls="flacso-tabpanel-<?php echo esc_attr($i); ?>"
                    id="flacso-tab-<?php echo esc_attr($i); ?>"
                    data-tab-target="flacso-tabpanel-<?php echo esc_attr($i); ?>"
                >
                    <?php echo esc_html($seccion['titulo']); ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <!-- Contenedor de Paneles -->
        <div class="flacso-autoridades-panels">
            <?php foreach ($data['secciones'] as $i => $seccion): ?>
                <div
                    id="flacso-tabpanel-<?php echo esc_attr($i); ?>"
                    class="flacso-tab-panel <?php echo ($i === 0) ? 'active' : ''; ?>"
                    role="tabpanel"
                    aria-labelledby="flacso-tab-<?php echo esc_attr($i); ?>"
                >
                    <div class="flacso-autoridades-grid">
                        <?php foreach ($seccion['personas'] as $j => $persona): 
                            $doc_id = intval($persona['docente_id']);
                            $cargo  = esc_html($persona['cargo']);
                            $programa = esc_html($persona['programa']);
                            $enlace   = esc_url($persona['enlace']);

                            $nombre_completo  = '';
                            $titulo_academico = '';
                            $cv_raw           = '';
                            $avatar_html      = '';
                            $correos_json     = '[]';
                            $redes_json       = '[]';
                            $has_docente      = ($doc_id > 0 && get_post_status($doc_id) === 'publish');

                            if ($has_docente) {
                                $nombre_completo  = function_exists('dp_nombre_completo') ? dp_nombre_completo($doc_id, true) : get_the_title($doc_id);
                                $titulo_academico = (string) get_post_meta($doc_id, 'titulo_academico', true);
                                $cv_raw           = (string) get_post_meta($doc_id, 'cv', true);
                                
                                if (function_exists('dp_avatar_markup')) {
                                    $avatar_html = dp_avatar_markup($doc_id, $nombre_completo, 130, 'flacso-autoridad__avatar-img');
                                } else {
                                    $thumb_url = get_the_post_thumbnail_url($doc_id, 'medium');
                                    if ($thumb_url) {
                                        $avatar_html = '<div class="flacso-autoridad__avatar-img"><img src="' . esc_url($thumb_url) . '" alt="' . esc_attr($nombre_completo) . '"></div>';
                                    }
                                }

                                if (function_exists('dp_get_docente_emails')) {
                                    $correos_json = wp_json_encode(dp_get_docente_emails($doc_id));
                                }
                                if (function_exists('dp_get_docente_socials')) {
                                    $redes_json = wp_json_encode(dp_get_docente_socials($doc_id));
                                }
                            } else {
                                $nombre_completo = esc_html($persona['nombre_manual']);
                                $iniciales = function_exists('dp_iniciales') ? dp_iniciales($nombre_completo, '') : substr($nombre_completo, 0, 2);
                                $avatar_html = '<div class="flacso-autoridad__avatar-img" style="background: linear-gradient(135deg, #1d3a72 0%, #0f1e3b 100%); color: #fed222;">' . esc_html($iniciales) . '</div>';
                            }
                            ?>
                            <article class="flacso-autoridad-card">
                                <span class="flacso-autoridad__kicker"><?php echo $cargo; ?></span>
                                
                                <div class="flacso-autoridad__avatar">
                                    <?php echo $avatar_html; ?>
                                </div>

                                <h3 class="flacso-autoridad__name"><?php echo esc_html($nombre_completo); ?></h3>

                                <?php if ($titulo_academico): ?>
                                    <p class="flacso-autoridad__title"><?php echo esc_html($titulo_academico); ?></p>
                                <?php endif; ?>

                                <?php if ($programa): ?>
                                    <?php if ($enlace): ?>
                                        <a href="<?php echo $enlace; ?>" target="_blank" rel="noopener" class="flacso-autoridad__programa">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 58h14M6 12h14"/></svg>
                                            <?php echo $programa; ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="flacso-autoridad__programa">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                            <?php echo $programa; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($cv_raw !== ''): ?>
                                    <button
                                        type="button"
                                        class="flacso-autoridad__bio-btn open-modal-btn"
                                        data-nombre="<?php echo esc_attr($nombre_completo); ?>"
                                        data-cargo="<?php echo esc_attr($cargo); ?>"
                                        data-titulo="<?php echo esc_attr($titulo_academico); ?>"
                                        data-cv="<?php echo esc_attr(function_exists('dp_safe_cv_html') ? dp_safe_cv_html(wpautop($cv_raw)) : wpautop(wp_kses_post($cv_raw))); ?>"
                                        data-correos="<?php echo esc_attr($correos_json); ?>"
                                        data-redes="<?php echo esc_attr($redes_json); ?>"
                                    >
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                        <?php esc_html_e('Ver Biografía y CV', 'flacso-uruguay'); ?>
                                    </button>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal Nativo HTML5 -->
        <dialog id="flacso-autoridades-modal" class="flacso-autoridad-modal" aria-modal="true">
            <div class="flacso-modal__header">
                <div class="flacso-modal__header-info">
                    <div id="flacso-modal-avatar" class="flacso-modal__header-avatar"></div>
                    <div class="flacso-modal__header-text">
                        <span id="flacso-modal-cargo" class="flacso-modal__kicker"></span>
                        <h3 id="flacso-modal-nombre"></h3>
                        <p id="flacso-modal-titulo" class="flacso-modal__title-acad"></p>
                    </div>
                </div>
                <button type="button" id="flacso-modal-close" class="flacso-modal__close" aria-label="Cerrar modal">&times;</button>
            </div>
            <div class="flacso-modal__body">
                <div id="flacso-modal-cv" class="flacso-modal__cv-content"></div>
                <div id="flacso-modal-contact" class="flacso-modal__contact"></div>
            </div>
        </dialog>
    </div>

    <!-- Script de interactividad Vanilla JS -->
    <script>
    (function () {
        const wrapper = document.querySelector('.flacso-autoridades-wrapper');
        if (!wrapper) return;

        // Pestañas
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

        // Modal
        const modal = document.getElementById('flacso-autoridades-modal');
        const modalClose = document.getElementById('flacso-modal-close');
        const modalAvatar = document.getElementById('flacso-modal-avatar');
        const modalCargo = document.getElementById('flacso-modal-cargo');
        const modalNombre = document.getElementById('flacso-modal-nombre');
        const modalTitulo = document.getElementById('flacso-modal-titulo');
        const modalCv = document.getElementById('flacso-modal-cv');
        const modalContact = document.getElementById('flacso-modal-contact');

        if (!modal) return;

        wrapper.addEventListener('click', function (e) {
            const openBtn = e.target.closest('.open-modal-btn');
            if (!openBtn) return;

            const card = openBtn.closest('.flacso-autoridad-card');
            const avatarElem = card.querySelector('.flacso-autoridad__avatar-img');

            modalCargo.textContent = openBtn.getAttribute('data-cargo') || '';
            modalNombre.textContent = openBtn.getAttribute('data-nombre') || '';
            modalTitulo.textContent = openBtn.getAttribute('data-titulo') || '';
            modalCv.innerHTML = openBtn.getAttribute('data-cv') || '';
            modalAvatar.innerHTML = avatarElem ? avatarElem.outerHTML : '';

            // Limpiar y cargar contactos
            modalContact.innerHTML = '';
            try {
                const correos = JSON.parse(openBtn.getAttribute('data-correos') || '[]');
                const redes = JSON.parse(openBtn.getAttribute('data-redes') || '[]');

                correos.forEach(c => {
                    if (c.email) {
                        const link = document.createElement('a');
                        link.className = 'flacso-modal__contact-btn';
                        link.href = 'mailto:' + c.email;
                        link.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' + (c.label ? c.label + ': ' : '') + c.email;
                        modalContact.appendChild(link);
                    }
                });

                redes.forEach(r => {
                    if (r.url) {
                        const link = document.createElement('a');
                        link.className = 'flacso-modal__contact-btn';
                        link.href = r.url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>' + (r.label || 'Perfil en red');
                        modalContact.appendChild(link);
                    }
                });
            } catch (err) {
                console.error(err);
            }

            modal.showModal();
        });

        if (modalClose) {
            modalClose.addEventListener('click', () => modal.close());
        }

        // Cerrar modal al hacer clic en el backdrop
        modal.addEventListener('click', function (e) {
            const rect = modal.getBoundingClientRect();
            const isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height && rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
            if (!isInDialog) {
                modal.close();
            }
        });
    })();
    </script>

    <?php
    return ob_get_clean();
});
