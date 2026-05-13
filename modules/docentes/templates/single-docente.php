<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $docente_id = get_the_ID();

    $prefijo_abrev = (string) get_post_meta($docente_id, 'prefijo_abrev', true);
    $titulo_academico = (string) get_post_meta($docente_id, 'titulo_academico', true);
    $nombre = (string) get_post_meta($docente_id, 'nombre', true);
    $apellido = (string) get_post_meta($docente_id, 'apellido', true);
    $cv_raw = (string) get_post_meta($docente_id, 'cv', true);

    $nombre_completo = function_exists('dp_nombre_completo')
        ? dp_nombre_completo($docente_id, true)
        : get_the_title($docente_id);
    
    // Si no hay prefijo en el nombre completo pero existe el abreviado, lo agregamos
    if ($prefijo_abrev && strpos($nombre_completo, $prefijo_abrev) === false) {
        $nombre_completo = $prefijo_abrev . ' ' . $nombre_completo;
    }

    $iniciales = function_exists('dp_iniciales')
        ? dp_iniciales($nombre, $apellido, 'DP')
        : strtoupper(substr($nombre_completo, 0, 2));

    $docentes_url = get_post_type_archive_link('docente');
    if (!$docentes_url) {
        $docentes_url = home_url('/docentes/');
    }

    $docente_correos = function_exists('dp_get_docente_emails') ? dp_get_docente_emails($docente_id) : [];
    $docente_redes = function_exists('dp_get_docente_socials') ? dp_get_docente_socials($docente_id) : [];

    // Color institucional FLACSO con gradiente moderno
    $hero_gradient = 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)';
    ?>

    <div class="flacso-docente-page">
        <div class="docente-container">
            
            <!-- Breadcrumb Moderno -->
            <nav class="docente-breadcrumb" aria-label="Navegación">
                <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
                <span class="sep">/</span>
                <a href="<?php echo esc_url($docentes_url); ?>">Docentes</a>
                <span class="sep">/</span>
                <span class="current"><?php echo esc_html($nombre_completo); ?></span>
            </nav>

            <!-- Hero Section Premium -->
            <header class="docente-hero">
                <div class="hero-bg" style="background: <?php echo esc_attr($hero_gradient); ?>;"></div>
                <div class="hero-content">
                    <div class="hero-main">
                        <div class="hero-avatar-wrapper">
                            <?php if (has_post_thumbnail($docente_id)) : ?>
                                <?php echo get_the_post_thumbnail($docente_id, 'large', ['class' => 'hero-avatar-img']); ?>
                            <?php else : ?>
                                <div class="hero-avatar-fallback"><?php echo esc_html($iniciales); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="hero-text">
                            <span class="hero-badge">Perfil Académico</span>
                            <h1 class="hero-name"><?php echo esc_html($nombre_completo); ?></h1>
                            <?php if ($titulo_academico) : ?>
                                <p class="hero-title"><?php echo esc_html($titulo_academico); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="hero-actions">
                        <a href="<?php echo esc_url($docentes_url); ?>" class="btn-back">
                            <span class="icon">←</span> Volver al listado
                        </a>
                    </div>
                </div>
            </header>

            <div class="docente-grid">
                <!-- Columna Principal: CV -->
                <main class="docente-main">
                    <section class="docente-card profile-section">
                        <div class="card-header">
                            <span class="icon">📜</span>
                            <h2>Trayectoria Profesional</h2>
                        </div>
                        <div class="cv-content">
                            <?php if ($cv_raw) : ?>
                                <?php echo wp_kses_post(wpautop($cv_raw)); ?>
                            <?php else : ?>
                                <p class="empty-state">No hay información de trayectoria disponible para este perfil.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                </main>

                <!-- Sidebar: Contacto y Redes -->
                <aside class="docente-sidebar">
                    <section class="docente-card contact-section">
                        <div class="card-header">
                            <h3>Información de Contacto</h3>
                        </div>
                        
                        <?php if (!empty($docente_correos)) : ?>
                            <div class="contact-methods">
                                <?php foreach ($docente_correos as $correo) : 
                                    $email = $correo['email'] ?? '';
                                    if (!$email) continue;
                                    $label = !empty($correo['label']) ? $correo['label'] : 'Email';
                                ?>
                                    <div class="contact-item">
                                        <span class="label"><?php echo esc_html($label); ?></span>
                                        <a href="mailto:<?php echo esc_attr(antispambot($email)); ?>" class="value">
                                            <?php echo esc_html(antispambot($email)); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="empty-compact">Sin correos públicos</p>
                        <?php endif; ?>

                        <?php if (!empty($docente_redes)) : ?>
                            <div class="social-grid">
                                <?php foreach ($docente_redes as $red) : 
                                    $url = $red['url'] ?? '';
                                    if (!$url) continue;
                                    $label = !empty($red['label']) ? $red['label'] : 'Perfil';
                                ?>
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener" class="social-btn">
                                        <?php echo esc_html($label); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if (current_user_can('edit_post', $docente_id)) : ?>
                        <div class="admin-notice">
                            <p>Estás viendo este perfil como administrador.</p>
                            <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="btn-admin">Editar en WordPress</a>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </div>

    <style>
        .flacso-docente-page {
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e4e7;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --primary: #1d3a72;
            --accent: #fed222;
            
            background-color: var(--slate-50);
            min-height: 100vh;
            padding-bottom: 5rem;
            font-family: 'Inter', -apple-system, sans-serif;
            color: var(--slate-800);
        }

        .docente-container {
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Breadcrumb */
        .docente-breadcrumb {
            padding: 1.5rem 0;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--slate-400);
        }
        .docente-breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .docente-breadcrumb .current { color: var(--slate-600); }

        /* Hero Section */
        .docente-hero {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }
        .hero-main {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }
        .hero-avatar-wrapper {
            width: 160px;
            height: 160px;
            border-radius: 20%;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .hero-avatar-img { width: 100%; height: 100%; object-fit: cover; }
        .hero-avatar-fallback {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            font-size: 3rem; font-weight: 800; color: #fff; background: var(--primary);
        }
        .hero-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--accent);
            color: var(--slate-900);
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .hero-name {
            color: #fff;
            font-size: clamp(2rem, 4vw, 3rem);
            margin: 0;
            font-weight: 800;
            line-height: 1.1;
        }
        .hero-title {
            color: var(--slate-300);
            font-size: 1.2rem;
            margin-top: 0.5rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .btn-back:hover { background: rgba(255, 255, 255, 0.2); transform: translateX(-5px); }

        /* Grid */
        .docente-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 2rem;
        }

        /* Cards */
        .docente-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.5rem;
            border: 1px solid var(--slate-200);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--slate-100);
        }
        .card-header h2, .card-header h3 { margin: 0; color: var(--primary); font-size: 1.5rem; font-weight: 700; }
        .card-header h3 { font-size: 1.1rem; }

        .cv-content { line-height: 1.8; color: var(--slate-700); font-size: 1.1rem; }
        .cv-content p { margin-bottom: 1.25rem; }

        /* Sidebar */
        .docente-sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
        .contact-methods { display: flex; flex-direction: column; gap: 1rem; }
        .contact-item {
            background: var(--slate-50);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--slate-200);
        }
        .contact-item .label { display: block; font-size: 0.75rem; color: var(--slate-400); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem; }
        .contact-item .value { color: var(--primary); text-decoration: none; font-weight: 600; word-break: break-all; }
        
        .social-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
        .social-btn {
            background: #fff;
            border: 1px solid var(--slate-200);
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            text-decoration: none;
            color: var(--slate-700);
            transition: all 0.2s ease;
        }
        .social-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--slate-50); }

        .admin-notice {
            background: var(--primary);
            color: #fff;
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
        }
        .admin-notice p { margin-bottom: 1rem; font-size: 0.9rem; opacity: 0.9; }
        .btn-admin { background: var(--accent); color: var(--slate-900); padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.85rem; display: block; }

        /* Responsive */
        @media (max-width: 992px) {
            .docente-grid { grid-template-columns: 1fr; }
            .hero-content { flex-direction: column; text-align: center; padding: 2rem; }
            .hero-main { flex-direction: column; gap: 1.5rem; }
            .docente-card { padding: 1.5rem; }
        }
    </style>

<?php
endwhile;
get_footer();

