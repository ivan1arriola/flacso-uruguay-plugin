<?php
/**
 * Template Name: FLACSO - Carta de Presentación
 * Description: Template dinámico para la página virtual de carta de presentación
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevenir la indexación de las cartas de presentación en buscadores (ej. Google)
header('X-Robots-Tag: noindex, nofollow', true);
add_action('wp_head', function() {
    echo '<meta name="robots" content="noindex, nofollow">' . "\n";
});

get_header();

global $post;

$current_post = $post instanceof WP_Post ? $post : null;
$current_post_id = $current_post ? (int) $current_post->ID : 0;
$legacy_page_id = ($current_post && $current_post->post_type === 'page') ? $current_post_id : 0;
$post_id = $current_post_id;

if ($legacy_page_id > 0 && class_exists('Oferta_Page_Adapter') && method_exists('Oferta_Page_Adapter', 'get_oferta_id_by_page_id')) {
    $resolved_oferta_id = Oferta_Page_Adapter::get_oferta_id_by_page_id($legacy_page_id);

    if (!empty($resolved_oferta_id)) {
        $post_id = (int) $resolved_oferta_id;
    }
}

$source_post = get_post($post_id);
$source_post_slug = $source_post ? (string) $source_post->post_name : '';
$data = class_exists('Oferta_Data_Schema') ? Oferta_Data_Schema::get_schema($post_id) : [];

if (!function_exists('flacso_carta_bool')) {
    function flacso_carta_bool($value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'si', 'sí', 'yes'], true);
        }

        return !empty($value);
    }
}

if (!function_exists('flacso_carta_array_from_value')) {
    function flacso_carta_array_from_value($value): array {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return array_values(array_filter(array_map('trim', explode('|', (string) $value))));
    }
}

if (!function_exists('flacso_carta_render_feature_card')) {
    function flacso_carta_render_feature_card(string $icon, string $title, string $value): void {
        ?>
        <article class="fc-feature-card">
            <div class="fc-feature-icon">
                <i class="bi <?php echo esc_attr($icon); ?>" aria-hidden="true"></i>
            </div>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html(wp_strip_all_tags($value)); ?></p>
        </article>
        <?php
    }
}

if (!function_exists('flacso_carta_has_meaningful_html')) {
    function flacso_carta_has_meaningful_html($value): bool {
        if (!is_string($value)) {
            return !empty($value);
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/\x{00a0}/u', ' ', $decoded);
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($decoded)));

        return $text !== '';
    }
}

if (!function_exists('flacso_carta_render_requirement')) {
    function flacso_carta_render_requirement(string $icon, string $content, bool $allow_html = false): void {
        ?>
        <div class="fc-requirement">
            <div class="fc-requirement-icon">
                <i class="bi <?php echo esc_attr($icon); ?>" aria-hidden="true"></i>
            </div>
            <div class="fc-requirement-content">
                <?php
                if ($allow_html) {
                    echo wp_kses_post($content);
                } else {
                    echo esc_html($content);
                }
                ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('flacso_carta_rows_have_dollar_prices')) {
    function flacso_carta_rows_have_dollar_prices(array $rows): bool {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $value = trim(wp_strip_all_tags((string) ($row['us'] ?? '')));

            if ($value !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('flacso_carta_determinante')) {
    function flacso_carta_determinante(string $titulo): string {
        $primera = strtolower(trim(strtok($titulo, ' ')));
        return in_array($primera, ['maestría', 'maestria', 'especialización', 'especializacion', 'licenciatura'], true) ? 'de la' : 'del';
    }
}

$child_content = !empty($data['carta_presentacion_html']) ? $data['carta_presentacion_html'] : '';

if (empty($child_content) && $legacy_page_id > 0 && $current_post) {
    $child_page = get_page_by_path($current_post->post_name . '/carta', OBJECT, 'page');

    if ($child_page) {
        $child_content = apply_filters('the_content', $child_page->post_content);
    }
}

$tipo_terms = get_the_terms($post_id, 'tipo-oferta-academica');
$tipo_oferta = '';

if ($tipo_terms && !is_wp_error($tipo_terms)) {
    $tipo_oferta = $tipo_terms[0]->name;
}

$is_maestria = stripos($tipo_oferta, 'maestr') !== false;
$is_diplomado = stripos($tipo_oferta, 'diplomado') !== false || stripos($tipo_oferta, 'diploma') !== false;
$es_especializacion = stripos($tipo_oferta, 'especializa') !== false;

$titulo = get_the_title($post_id);
$abreviacion = $data['abreviacion'] ?? '';
$cohorte = get_post_meta($post_id, 'cohorte', true) ?: '';
$anio = date('Y');
$carta_cta_titulo_global = trim((string) get_option('flacso_carta_cta_titulo_default', ''));
$carta_cta_titulo = $carta_cta_titulo_global !== ''
    ? $carta_cta_titulo_global
    : (!empty($data['carta_cta_titulo'])
        ? $data['carta_cta_titulo']
        : 'Comenzá el año cursando un posgrado en FLACSO Uruguay');

$proximo_inicio_val = $data['proximo_inicio'] ?? '';

if (is_array($proximo_inicio_val)) {
    $proximo_inicio_val = !empty($proximo_inicio_val) ? reset($proximo_inicio_val) : '';
}

$proximo_inicio = 'A definir';

if (!empty($proximo_inicio_val)) {
    $proximo_inicio_str = (string) $proximo_inicio_val;

    if (class_exists('Oferta_Renderer')) {
        $precision = get_post_meta($post_id, 'proximo_inicio_precision', true);
        $formatted = Oferta_Renderer::format_proximo_inicio_text($proximo_inicio_str, (string) $precision);
        if ($formatted !== '') {
            $proximo_inicio = $formatted;
        }
    } else {
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $proximo_inicio_str)) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d|', $proximo_inicio_str, wp_timezone());
            $proximo_inicio = $date ? wp_date('j \d\e F \d\e Y', $date->getTimestamp(), wp_timezone()) : $proximo_inicio_str;
        } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $proximo_inicio_str)) {
            $date = DateTimeImmutable::createFromFormat('Y-m|', $proximo_inicio_str, wp_timezone());
            $proximo_inicio = $date ? wp_date('F Y', $date->getTimestamp(), wp_timezone()) : $proximo_inicio_str;
        } else {
            $proximo_inicio = $proximo_inicio_str;
        }
    }
}

$duracion = !empty($data['duracion_meses']) ? $data['duracion_meses'] . ' meses' : '12 meses';
$modalidad = !empty($data['modalidad_resumen']) ? $data['modalidad_resumen'] : 'Virtual';

$menciones = is_array($data['menciones'] ?? null) ? implode('|', $data['menciones']) : ($data['menciones'] ?? '');
$orientaciones = is_array($data['orientaciones'] ?? null) ? implode('|', $data['orientaciones']) : ($data['orientaciones'] ?? '');

$arr_menciones = flacso_carta_array_from_value($menciones);
$arr_orientaciones = flacso_carta_array_from_value($orientaciones);
$hero_badge_text = trim((string) ($data['carta_hero_etiqueta'] ?? ''));
$mostrar_instancias_presenciales = isset($data['carta_instancias_presenciales'])
    ? flacso_carta_bool($data['carta_instancias_presenciales'])
    : false;
$data['acreditaciones_html'] = flacso_carta_has_meaningful_html($data['acreditaciones_html'] ?? null)
    ? (string) $data['acreditaciones_html']
    : '';

$reconocido_mec = isset($data['reconocido_mec'])
    ? flacso_carta_bool($data['reconocido_mec'])
    : $is_maestria;

$mostrar_carta = isset($data['inscripciones_abiertas'])
    ? flacso_carta_bool($data['inscripciones_abiertas'])
    : flacso_carta_bool(get_post_meta($post_id, 'inscripciones_abiertas', true));
$reconocimiento_int = isset($data['reconocimiento_internacional'])
    ? flacso_carta_bool($data['reconocimiento_internacional'])
    : ($is_maestria || (int) $post_id === 12316);

$mostrar_banner_convenio = !empty($data['convenio_iin_oea']);

$imagen = '';
$imagen_id = get_post_thumbnail_id($post_id);

if ($imagen_id) {
    $imagen_array = wp_get_attachment_image_src($imagen_id, 'large');

    if ($imagen_array && !empty($imagen_array[0])) {
        $imagen = $imagen_array[0];
    }
}

$asistente_slug = '';
$asistente_nombre = '';
$asistente_correo = 'inscripciones@flacso.edu.uy';
$asistente_titulo = 'Asistente Académica';

$override_docente_id = !empty($data['asistente_academica_docente_id']) ? (int) $data['asistente_academica_docente_id'] : 0;
$override_rol = !empty($data['asistente_academica_rol']) ? $data['asistente_academica_rol'] : '';
$override_correo = !empty($data['asistente_academica_correo']) ? $data['asistente_academica_correo'] : '';

if ($override_docente_id > 0) {
    $asistente_post = get_post($override_docente_id);

    if ($asistente_post) {
        $asistente_slug = $asistente_post->post_name;
        $asistente_nombre = get_the_title($asistente_post->ID);
        $asistente_titulo = $override_rol ?: 'Asistente Académica';

        $correo_meta = get_post_meta($override_docente_id, 'correo', true);
        $asistente_correo = $override_correo ?: ($correo_meta ?: 'inscripciones@flacso.edu.uy');
    }
}

if (!$asistente_slug && !empty($data['equipos']) && is_array($data['equipos'])) {
    foreach ($data['equipos'] as $eq) {
        if (!empty($eq['nombre']) && stripos($eq['nombre'], 'asistente') !== false && !empty($eq['docentes'])) {
            $docente_id = $eq['docentes'][0];
            $asistente_post = get_post($docente_id);

            if ($asistente_post) {
                $asistente_slug = $asistente_post->post_name;
                $asistente_nombre = get_the_title($asistente_post->ID);
                $asistente_titulo = $eq['nombre'];

                $correo_meta = get_post_meta($docente_id, 'correo', true);

                if ($correo_meta) {
                    $asistente_correo = $correo_meta;
                }
            }

            break;
        }
    }
}

if (!$asistente_slug) {
    if ($is_maestria && stripos($source_post_slug, 'educacion-innovacion-tecnologias') !== false) {
        $asistente_slug = 'analia-bombau';
        $asistente_nombre = 'Analía Bombau';
        $asistente_correo = 'edutic@flacso.edu.uy';
    } elseif ($is_diplomado && stripos($source_post_slug, 'genero') !== false) {
        $asistente_slug = 'florencia-quartino';
        $asistente_nombre = 'Florencia Quartino';
        $asistente_correo = 'genero@flacso.edu.uy';
    }
}

$bloques_html = [
    'descripcion_html' => 'Información General',
    'acreditaciones_html' => 'Acreditaciones y Validez Oficial',
    'perfil_ingreso_html' => 'Perfil de Ingreso',
    'objetivos_html' => 'Objetivos del Programa',
    'modalidad_html' => 'Modalidad de Cursada',
    'duracion_html' => 'Duración y Carga Horaria',
    'perfil_egreso_html' => 'Perfil del Egresado',
    'requisitos_egreso_html' => 'Requisitos para Titulación',
    'titulos_certificaciones_html' => 'Títulos y Certificaciones',
];

$titulos_intermedios_ids = !empty($data['titulos_intermedios']) && is_array($data['titulos_intermedios'])
    ? $data['titulos_intermedios']
    : [];

echo "<!-- DEBUG TABLA PRECIO: tabla_precio_id=" . (isset($data['tabla_precio_id']) ? esc_html($data['tabla_precio_id']) : 'NOT_SET') . " -->\n";

$precios_filas = [];

if (!empty($data['precios_filas'])) {
    if (is_string($data['precios_filas'])) {
        $raw = wp_unslash($data['precios_filas']);
        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            $decoded = json_decode(html_entity_decode($raw), true);
        }
        $precios_filas = is_array($decoded) ? $decoded : [];
        echo '<!-- DEBUG PRECIOS: raw=' . esc_html($raw) . ' json_last_error=' . json_last_error_msg() . ' count=' . count($precios_filas) . ' -->';
    } elseif (is_array($data['precios_filas'])) {
        $precios_filas = $data['precios_filas'];
    }
}

if (!empty($precios_filas) && is_string($child_content) && $child_content !== '') {
    $child_content = preg_replace(
        '/\[(?:programa_precios|maestria_precios|egccyd_precios|diplomado_especializacion_precios|eapet_precios|diplomas_precios|iape_precios|subjetividad_precios)(?:\s+[^\]]*)?\]/i',
        '',
        $child_content
    );
}

$mostrar_precios_dolares = true;

if (!empty($data['tabla_precio']) && is_array($data['tabla_precio']) && array_key_exists('mostrar_precios_dolares', $data['tabla_precio'])) {
    $mostrar_precios_dolares = flacso_carta_bool($data['tabla_precio']['mostrar_precios_dolares']);
} elseif (!empty($precios_filas)) {
    $mostrar_precios_dolares = flacso_carta_rows_have_dollar_prices($precios_filas);
}

$precios_nota = !empty($data['precios_nota'])
    ? $data['precios_nota']
    : 'Si no puedes adherir a ninguno de los beneficios descritos, podrás solicitar una beca FLACSO Uruguay de hasta un 20% escribiendo a: <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a><br><small>*Sujeto a aprobación por parte de la Comisión Académica FLACSO Uruguay</small>';

$mostrar_expedicion = false;

if (isset($data['mostrar_expedicion_titulo']) && $data['mostrar_expedicion_titulo'] === '1') {
    $mostrar_expedicion = true;
} elseif (isset($data['mostrar_expedicion_titulo']) && $data['mostrar_expedicion_titulo'] === '') {
    $mostrar_expedicion = ($is_maestria || $es_especializacion);
} else {
    $mostrar_expedicion = ($is_maestria || $es_especializacion);
}

$mostrar_costos_envio = !empty($data['mostrar_costos_envio']);
$mostrar_convenio_iin = !empty($data['convenio_iin_oea']);
$url_inscripcion = trailingslashit(get_permalink($post_id)) . 'preinscripcion';
?>

<div id="primary" class="content-area flacso-carta-virtual">
    <a class="fc-skip-link" href="#fc-info-clave">Saltar a la información principal</a>
    <main id="main" class="site-main" aria-labelledby="fc-carta-title">

        <section class="fc-hero" aria-labelledby="fc-carta-title">
            <div class="fc-hero-inner<?php echo $hero_badge_text !== '' ? ' has-hero-ribbon' : ''; ?>">
                <?php if ($hero_badge_text !== '') : ?>
                    <div class="fc-hero-ribbon">
                        <i class="bi bi-bookmark-star-fill" aria-hidden="true"></i>
                        <span><?php echo esc_html($hero_badge_text); ?></span>
                    </div>
                <?php endif; ?>

                <div class="fc-hero-copy">
                    <div class="fc-eyebrow">
                        <i class="bi bi-stars" aria-hidden="true"></i>
                        <span>Formación de excelencia, estés donde estés</span>
                    </div>

                    <?php if ($mostrar_instancias_presenciales) : ?>
                        <div class="fc-hero-highlights fc-hero-highlights--top">
                            <span class="fc-hero-badge fc-hero-badge--accent">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                Con instancias presenciales
                            </span>
                        </div>
                    <?php endif; ?>

                    <h1 id="fc-carta-title"><?php echo esc_html($titulo); ?></h1>

                    <div class="fc-hero-meta">
                        <?php if (!empty($abreviacion)) : ?>
                            <span><?php echo esc_html($abreviacion . ' ' . $anio); ?></span>
                        <?php endif; ?>

                        <?php if (!empty($cohorte)) : ?>
                            <span><?php echo esc_html($cohorte); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($reconocido_mec || $reconocimiento_int) : ?>
                        <div class="fc-certifications">
                            <?php if ($reconocido_mec) : ?>
                                <div class="fc-certification">
                                    <i class="bi bi-award" aria-hidden="true"></i>
                                    <span>Reconocida por el M.E.C</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($reconocimiento_int) : ?>
                                <div class="fc-certification">
                                    <i class="bi bi-globe-americas" aria-hidden="true"></i>
                                    <span>Apostillado de La Haya</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($arr_menciones)) : ?>
                        <div class="fc-chip-block">
                            <p>Con mención en:</p>
                            <div class="fc-chip-grid">
                                <?php foreach ($arr_menciones as $mencion) : ?>
                                    <span><?php echo esc_html($mencion); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($arr_orientaciones)) : ?>
                        <div class="fc-chip-block">
                            <p>Orientaciones metodológicas:</p>
                            <div class="fc-chip-grid">
                                <?php foreach ($arr_orientaciones as $orientacion) : ?>
                                    <span><?php echo esc_html($orientacion); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="fc-hero-actions">
                        <?php if ($mostrar_carta) : ?>
                            <a class="fc-primary-button" href="<?php echo esc_url($url_inscripcion); ?>" aria-label="Abrir formulario de preinscripción para <?php echo esc_attr($titulo); ?>">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                Formulario de preinscripción
                            </a>
                        <?php endif; ?>

                        <a class="fc-secondary-button" href="#fc-info-clave" aria-label="Ver información clave de <?php echo esc_attr($titulo); ?>">
                            <i class="bi bi-arrow-down-circle" aria-hidden="true"></i>
                            Ver información
                        </a>
                    </div>
                </div>

                <div class="fc-hero-visual">
                    <?php if ($mostrar_banner_convenio) : ?>
                        <div class="fc-convenio-pill">
                            <i class="bi bi-handshake" aria-hidden="true"></i>
                            <span>En convenio con el <strong>IIN-OEA</strong></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($imagen) : ?>
                        <div class="fc-cover-card">
                            <img src="<?php echo esc_url($imagen); ?>" alt="<?php echo esc_attr($titulo); ?>">
                        </div>
                    <?php else : ?>
                        <div class="fc-cover-card fc-cover-placeholder">
                            <i class="bi bi-mortarboard" aria-hidden="true"></i>
                            <span><?php echo esc_html($tipo_oferta ?: 'Posgrado'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <?php if (!empty($titulos_intermedios_ids)) : ?>
                <div class="fc-intermediate-strip">
                    <p>Durante la cursada del programa se obtendrán <strong>títulos intermedios</strong>:</p>

                    <div class="fc-intermediate-grid">
                        <?php foreach ($titulos_intermedios_ids as $tid) : ?>
                            <?php
                            $t_post = get_post($tid);

                            if (!$t_post) {
                                continue;
                            }

                            $t_duracion = get_post_meta($tid, 'duracion_html', true);
                            ?>
                            <article class="fc-intermediate-card">
                                <div class="fc-intermediate-icon">
                                    <i class="bi bi-mortarboard" aria-hidden="true"></i>
                                </div>

                                <div>
                                    <h3><?php echo esc_html($t_post->post_title); ?></h3>

                                    <?php if (!empty($t_duracion)) : ?>
                                        <span><?php echo esc_html(wp_strip_all_tags($t_duracion)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_maestria) : ?>
                        <p class="fc-intermediate-note">
                            El título de <strong>Magíster</strong> será obtenido una vez se aprueben los créditos académicos y se defienda la tesis.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="fc-page-container">

            <?php if (!$mostrar_carta) : ?>
                <div class="fc-message-box fc-message-box-standalone" style="text-align:center; padding: 4rem 2rem; margin-top: 2rem; border-color: #fecdd3; background: #fff1f2; border-radius: 12px;">
                    <i class="bi bi-info-circle" style="font-size: 3rem; color: #be123c; margin-bottom: 1rem; display: block;"></i>
                    <h2 style="color: #be123c; margin-bottom: 1rem;">Inscripciones Cerradas</h2>
                    <p style="color: #881337; font-size: 1.15rem; max-width: 600px; margin: 0 auto;">En este momento no estamos recibiendo inscripciones para esta cursada.</p>
                </div>
            <?php else : ?>

            <section id="fc-info-clave" class="fc-feature-grid">
                <?php
                flacso_carta_render_feature_card('bi-calendar-event', 'Próximo inicio', $proximo_inicio);
                flacso_carta_render_feature_card('bi-clock', 'Duración', $duracion);
                flacso_carta_render_feature_card('bi-laptop', 'Modalidad', $modalidad);

                if ($mostrar_instancias_presenciales) {
                    flacso_carta_render_feature_card('bi-geo-alt', 'Cursada', 'Con instancias presenciales');
                }
                ?>
            </section>

            <section class="fc-content-sections" aria-label="Información detallada del programa">
                <?php
                $mensaje_bienvenida = get_option('flacso_mensaje_bienvenida', '');
                $hay_bloques_detallados = false;

                foreach ($bloques_html as $meta_key => $titulo_bloque) {
                    if (!empty($data[$meta_key])) {
                        $hay_bloques_detallados = true;
                        break;
                    }
                }

                if (!empty($mensaje_bienvenida)) {
                    ?>
                    <div class="fc-message-box fc-message-box-standalone">
                        <?php echo wp_kses_post($mensaje_bienvenida); ?>
                    </div>
                    <?php
                }

                if ($hay_bloques_detallados) {
                    ?>
                    <div class="fc-rich-grid">
                        <?php
                        foreach ($bloques_html as $meta_key => $titulo_bloque) {
                            if (!empty($data[$meta_key])) {
                                $panel_id = 'fc-panel-' . sanitize_title($meta_key);
                                ?>
                                <article class="fc-content-panel fc-content-wrapper fc-content-wrapper-compact fc-rich-block" aria-labelledby="<?php echo esc_attr($panel_id); ?>">
                                    <h2 id="<?php echo esc_attr($panel_id); ?>"><?php echo esc_html($titulo_bloque); ?></h2>
                                    <div class="fc-content-panel-body">
                                        <?php echo wp_kses_post($data[$meta_key]); ?>
                                    </div>
                                </article>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    <?php
                }

                if (trim((string) $child_content) !== '') {
                    ?>
                    <article class="fc-content-panel fc-content-wrapper fc-content-wrapper-compact fc-child-content" aria-label="Contenido adicional del programa">
                        <div class="fc-content-panel-body">
                            <?php echo wp_kses_post($child_content); ?>
                        </div>
                    </article>
                    <?php
                }
                ?>
            </section>

            <?php
            if (strpos($child_content, 'flacso-uruguay/dato-malla-curricular') === false) {
                $documentos_json = get_post_meta($post_id, 'documentos', true);
                $documentos = is_string($documentos_json) && !empty($documentos_json) ? json_decode($documentos_json, true) : [];
                $cartamalla = !empty($documentos['cartamalla']['link']) ? trim($documentos['cartamalla']['link']) : '';
                $malla_pdf = !empty($documentos['malla']['link']) ? trim($documentos['malla']['link']) : '';
                $calendario_pdf = !empty($documentos['calendario']['link']) ? trim($documentos['calendario']['link']) : '';

                if ($cartamalla || $malla_pdf || $calendario_pdf) {
                    $fc_cards_count = 0;
                    if ($cartamalla) {
                        $fc_cards_count = 1;
                    } else {
                        if ($calendario_pdf) $fc_cards_count++;
                        if ($malla_pdf) $fc_cards_count++;
                    }
                    ?>
                    <section class="fc-section">
                        <h2 class="fc-section-title">Calendario y Malla Curricular</h2>
                        <div class="fc-more-grid" style="--fc-grid-cols: <?php echo $fc_cards_count; ?>;">
                            <?php if ($cartamalla) : ?>
                                <article class="fc-more-card" style="text-align: center; padding: 2rem;">
                                    <div class="fc-more-icon" style="margin: 0 auto 1rem;"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
                                    <h3>Calendario y Malla</h3>
                                    <p style="margin-bottom: 1.5rem;">Incluye el calendario de cursada y la malla curricular completa.</p>
                                    <a href="<?php echo esc_url($cartamalla); ?>" target="_blank" rel="noopener noreferrer" class="fc-secondary-button fc-card-button" style="display: inline-flex; width: auto; justify-content: center;">
                                        <i class="bi bi-file-earmark-pdf"></i> Ver Documento
                                    </a>
                                </article>
                            <?php else : ?>
                                <?php if ($calendario_pdf) : ?>
                                    <article class="fc-more-card" style="text-align: center; padding: 2rem;">
                                        <div class="fc-more-icon" style="margin: 0 auto 1rem;"><i class="bi bi-calendar2-check" aria-hidden="true"></i></div>
                                        <h3>Calendario Académico</h3>
                                        <p style="margin-bottom: 1.5rem;">Fechas clave, inicios y recesos de la cursada.</p>
                                        <a href="<?php echo esc_url($calendario_pdf); ?>" target="_blank" rel="noopener noreferrer" class="fc-secondary-button fc-card-button" style="display: inline-flex; width: auto; justify-content: center;">
                                            <i class="bi bi-file-earmark-pdf"></i> Ver Calendario
                                        </a>
                                    </article>
                                <?php endif; ?>
                                <?php if ($malla_pdf) : ?>
                                    <article class="fc-more-card" style="text-align: center; padding: 2rem;">
                                        <div class="fc-more-icon" style="margin: 0 auto 1rem;"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></div>
                                        <h3>Malla Curricular</h3>
                                        <p style="margin-bottom: 1.5rem;">Programa completo y asignaturas del posgrado.</p>
                                        <a href="<?php echo esc_url($malla_pdf); ?>" target="_blank" rel="noopener noreferrer" class="fc-secondary-button fc-card-button" style="display: inline-flex; width: auto; justify-content: center;">
                                            <i class="bi bi-file-earmark-pdf"></i> Ver Malla
                                        </a>
                                    </article>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php
                }
            }
            ?>

            <section class="fc-section">
                <h2 class="fc-section-title">Requisitos de Postulación y Admisión</h2>

                <?php if (!empty($data['requisitos_ingreso_html'])) : ?>
                    <div class="fc-content-wrapper fc-content-wrapper-compact">
                        <?php echo wp_kses_post($data['requisitos_ingreso_html']); ?>
                    </div>
                <?php else : ?>
                    <div class="fc-requirements-list">
                        <?php
                        flacso_carta_render_requirement('bi-pencil-square', 'Completar el formulario de preinscripción online');

                        if ($is_maestria) {
                            flacso_carta_render_requirement(
                                'bi-paperclip',
                                '<strong>Añadir en el formulario, de forma escaneada:</strong><ul><li>Documento que acredite estudios de grado con copia legalizada</li><li>Documento de identidad vigente</li><li>Curriculum Vitae</li><li>Carta de motivación</li><li>Dos cartas de referencia</li></ul>',
                                true
                            );

                            flacso_carta_render_requirement('bi-people', 'Asistir a una entrevista de admisión con la coordinación académica');
                        } else {
                            flacso_carta_render_requirement(
                                'bi-paperclip',
                                '<strong>Añadir en el formulario, de forma escaneada:</strong><ul><li>Documento que acredite estudios previos</li><li>Documento de identidad vigente</li><li>Carta de motivación</li></ul>',
                                true
                            );
                        }

                        flacso_carta_render_requirement('bi-award', 'La admisión está sujeta a cupos y selección académica');
                        ?>
                    </div>

                    <div class="fc-alert-box">
                        <h3>Atención</h3>
                        <p>Todos los documentos deben adjuntarse en el formulario de preinscripción.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="fc-section">
                <?php if (!empty($precios_filas) && is_array($precios_filas)) : ?>
                    <div class="fc-card" aria-labelledby="fc-inversion-title">
                        <div class="fc-card-header">
                            <span class="fc-card-kicker">Inversión</span>
                            <h2 id="fc-inversion-title">Beneficios y descuentos acumulables</h2>
                        </div>

                        <div class="fc-table-wrap">
                            <table class="fc-pricing-table<?php echo $mostrar_precios_dolares ? '' : ' is-without-usd'; ?>">
                                <caption class="fc-sr-only">Beneficios, descuentos y valores de la oferta académica</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Concepto</th>
                                        <th scope="col">Valor en $ <span>residentes en Uruguay</span></th>
                                        <?php if ($mostrar_precios_dolares) : ?>
                                            <th scope="col">Valor en U$S <span>residentes en el exterior</span></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($precios_filas as $row) : ?>
                                        <tr class="<?php echo !empty($row['highlight']) ? 'is-highlighted' : ''; ?>">
                                            <td data-label="Concepto"><?php echo wp_kses_post($row['concept'] ?? ''); ?></td>
                                            <td data-label="Valor en pesos para residentes en Uruguay"><?php echo wp_kses_post($row['uy'] ?? ''); ?></td>
                                            <?php if ($mostrar_precios_dolares) : ?>
                                                <td data-label="Valor en dólares para residentes en el exterior"><?php echo wp_kses_post($row['us'] ?? ''); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($precios_nota)) : ?>
                            <div class="fc-pricing-note">
                                <?php echo wp_kses_post($precios_nota); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="fc-empty-pricing">
                        <i class="bi bi-currency-dollar" aria-hidden="true"></i>
                        <p>
                            Para consultar los costos, planes de financiación y políticas de becas, ponte en contacto con
                            <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a>.
                        </p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="fc-section fc-info-important">
                <h2 class="fc-section-title">Información importante</h2>

                <div class="fc-info-list">
                    <?php if ($mostrar_precios_dolares) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-currency-dollar" aria-hidden="true"></i></div>
                            <div>Para pagos en <strong>dólares</strong>, las cuotas se mantendrán fijas durante toda la cursada.</div>
                        </div>
                    <?php endif; ?>

                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-currency-exchange" aria-hidden="true"></i></div>
                        <div>Para pagos en <strong>pesos</strong>, las cuotas permanecerán fijas durante todo 2026, y a partir de 2027 se ajustarán en enero de cada año, por debajo de la inflación interanual.</div>
                    </div>

                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
                        <div>Las cuotas vencen el día <strong>15 de cada mes</strong>.</div>
                    </div>

                    <?php if ($mostrar_expedicion) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-award" aria-hidden="true"></i></div>
                            <div>El título se expide en el exterior y tiene un costo asociado de <strong>USD 150</strong>, que incluye el envío y el trámite de Apostilla de La Haya.</div>
                        </div>
                    <?php endif; ?>

                    <?php if ($mostrar_costos_envio) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-truck" aria-hidden="true"></i></div>
                            <div>
                                <strong>Los certificados tienen costos de envío al exterior:</strong>
                                <ul class="fc-envios-list">
                                    <li><span>USD 30</span> – envío a Argentina</li>
                                    <li><span>USD 50</span> – envío al resto de América Latina</li>
                                    <li><span>USD 70</span> – envío a Europa</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($mostrar_convenio_iin) : ?>
                        <div class="fc-info-row">
                            <div class="fc-info-icon"><i class="bi bi-globe" aria-hidden="true"></i></div>
                            <div><strong>Convenio IIN-OEA:</strong> los funcionarios o referentes de los Estados Miembros que cuenten con aval institucional acceden a bonificaciones especiales para cursar este programa.</div>
                        </div>
                    <?php endif; ?>

                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-envelope" aria-hidden="true"></i></div>
                        <div>
                            <strong>Consultá por otros planes de financiación en</strong>
                            <a href="mailto:inscripciones@flacso.edu.uy">inscripciones@flacso.edu.uy</a>
                        </div>
                    </div>
                </div>

                <div class="fc-action-buttons">
                    <a href="https://flacso.edu.uy/convenios/" target="_blank" rel="noopener" aria-label="Ver convenios de FLACSO Uruguay en una nueva pestaña">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        Ver convenios
                    </a>

                    <a href="https://flacso.edu.uy/formas-de-pago/" target="_blank" rel="noopener" aria-label="Ver formas de pago de FLACSO Uruguay en una nueva pestaña">
                        <i class="bi bi-credit-card" aria-hidden="true"></i>
                        Ver formas de pago
                    </a>
                </div>
            </section>

            <?php
            $carta_mas_info_section_title = trim((string) get_option('flacso_carta_mas_info_section_title', 'Más información'));
            $carta_mas_info_trayectoria_title = trim((string) get_option('flacso_carta_mas_info_trayectoria_title', 'Trayectoria Académica'));
            $carta_mas_info_trayectoria_html = (string) get_option('flacso_carta_mas_info_trayectoria_html', '<p>Nuestra <strong>Facultad de Posgrados</strong> busca formar a sus estudiantes a nivel <strong>académico, profesional y laboral</strong>. Nos distinguen <strong>19 años de trayectoria</strong> a nivel nacional y más de <strong>65 años a nivel internacional</strong>. Además, más de <strong>7000 personas egresadas</strong> de FLACSO Uruguay trabajan en el ámbito público y privado.</p>');
            $carta_mas_info_gestion_title = trim((string) get_option('flacso_carta_mas_info_gestion_title', 'Gestión Académica'));
            $carta_mas_info_gestion_html = (string) get_option('flacso_carta_mas_info_gestion_html', '<p>Nos distingue un sistema de <strong>gestión académica eficiente y cercano</strong>, que acompaña de forma <strong>personalizada</strong> a cada estudiante y garantiza <strong>altos niveles de egreso, superiores al 90%</strong>.</p>');
            $carta_mas_info_financiacion_title = trim((string) get_option('flacso_carta_mas_info_financiacion_title', 'Financiamiento Flexible'));
            $carta_mas_info_financiacion_html = (string) get_option('flacso_carta_mas_info_financiacion_html', '<p>Puedes abonar el posgrado en <strong>cuotas sin recargo</strong> a lo largo de la cursada. Contamos con <strong>múltiples convenios, descuentos de hasta el 25%</strong> y la posibilidad de acceder a <strong>becas</strong>.</p>');
            ?>

            <section class="fc-section">
                <h2 class="fc-section-title"><?php echo esc_html($carta_mas_info_section_title); ?></h2>

                <div class="fc-more-grid">
                    <article class="fc-more-card">
                        <div class="fc-more-icon"><i class="bi bi-award" aria-hidden="true"></i></div>
                        <h3><?php echo esc_html($carta_mas_info_trayectoria_title); ?></h3>
                        <?php echo wp_kses_post($carta_mas_info_trayectoria_html); ?>
                    </article>

                    <article class="fc-more-card">
                        <div class="fc-more-icon"><i class="bi bi-gear" aria-hidden="true"></i></div>
                        <h3><?php echo esc_html($carta_mas_info_gestion_title); ?></h3>
                        <?php echo wp_kses_post($carta_mas_info_gestion_html); ?>
                    </article>

                    <article class="fc-more-card">
                        <div class="fc-more-icon"><i class="bi bi-currency-dollar" aria-hidden="true"></i></div>
                        <h3><?php echo esc_html($carta_mas_info_financiacion_title); ?></h3>
                        <?php echo wp_kses_post($carta_mas_info_financiacion_html); ?>
                    </article>
                </div>
            </section>

            <?php
            if ($asistente_slug) {
                $asist_docente = get_page_by_path($asistente_slug, OBJECT, 'docente');

                if ($asist_docente) {
                    $asist_meta = get_post_meta($asist_docente->ID);
                    $asist_prefijo_raw = !empty($asist_meta['prefijo_abrev'][0]) ? trim((string) $asist_meta['prefijo_abrev'][0]) : '';
                    $asist_prefijo = $asist_prefijo_raw !== '' ? esc_html($asist_prefijo_raw) . ' ' : '';
                    $asist_nombre_base = get_the_title($asist_docente->ID);
                    if (function_exists('dp_strip_prefix_from_name')) {
                        $asist_nombre_base = dp_strip_prefix_from_name($asist_nombre_base, $asist_prefijo_raw);
                    }
                    $asist_nombre_base = esc_html($asist_nombre_base);
                    $asist_nombre_completo = $asist_prefijo . $asist_nombre_base;
                    $asist_imagen_url = get_the_post_thumbnail_url($asist_docente->ID, 'medium');
                    $prep_posgrado = flacso_carta_determinante(get_the_title($post_id));
                    $enlace_correo = '<a href="mailto:' . esc_attr($asistente_correo) . '">' . esc_html($asistente_correo) . '</a>';

                    $presentacion_final = sprintf(
                        'Mi nombre es %s y soy %s %s %s. Si tienes dudas o consultas puedes contactarme al correo electrónico %s.',
                        $asist_nombre_base,
                        esc_html($asistente_titulo),
                        esc_html($prep_posgrado),
                        esc_html(get_the_title($post_id)),
                        $enlace_correo
                    );
                    ?>
                    <section class="fc-assistant-card">
                        <div class="fc-assistant-photo">
                            <?php if ($asist_imagen_url) : ?>
                                <img src="<?php echo esc_url($asist_imagen_url); ?>" alt="<?php echo esc_attr(wp_strip_all_tags($asist_nombre_completo)); ?>">
                            <?php else : ?>
                                <div class="fc-assistant-placeholder">
                                    <?php echo esc_html(strtoupper(substr(wp_strip_all_tags($asist_nombre_completo), 0, 1))); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="fc-assistant-copy">
                            <span class="fc-assistant-label"><?php echo esc_html($asistente_titulo); ?></span>
                            <h2><?php echo $asist_nombre_completo; ?></h2>
                            <p><?php echo wp_kses_post($presentacion_final); ?></p>
                        </div>
                    </section>
                    <?php
                }
            }
            ?>

            <section class="fc-final-cta">
                <div>
                    <span>Preinscripciones <?php echo esc_html($anio); ?></span>
                    <h2><?php echo esc_html($carta_cta_titulo); ?></h2>
                    <p><strong>Formación 100% a distancia</strong></p>
                </div>

                <a href="<?php echo esc_url($url_inscripcion); ?>" aria-label="Abrir formulario de preinscripción para <?php echo esc_attr($titulo); ?>">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    Formulario de Preinscripción
                </a>
            </section>

            <a class="fc-floating-preinscripcion is-hidden" href="<?php echo esc_url($url_inscripcion); ?>" aria-label="Abrir formulario de preinscripción para <?php echo esc_attr($titulo); ?>" aria-hidden="true" tabindex="-1">
                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                <span class="fc-floating-copy">
                    <strong>Formulario de preinscripción</strong>
                    <small>Inscripciones abiertas</small>
                </span>
            </a>

            <?php endif; ?>

        </div>
    </main>
</div>

<style>
.flacso-carta-virtual {
    --fc-primary: var(--global-palette1, #16396f);
    --fc-primary-dark: #102b56;
    --fc-secondary: #e9e50f;
    --fc-text: var(--global-palette3, #172033);
    --fc-muted: var(--global-palette4, #4b5b72);
    --fc-soft: var(--global-palette8, #edf2f6);
    --fc-soft-2: var(--global-palette7, #dce6ef);
    --fc-white: var(--global-palette9, #ffffff);
    --fc-border: rgba(22, 57, 111, .14);
    --fc-shadow: 0 18px 45px rgba(15, 26, 45, .12);
    --fc-shadow-soft: 0 8px 24px rgba(15, 26, 45, .08);
    color: var(--fc-text);
    background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 42%, #ffffff 100%);
}

.flacso-carta-virtual * {
    box-sizing: border-box;
}

.flacso-carta-virtual a {
    transition: all .2s ease;
}

.fc-hero {
    max-width: 1240px;
    margin: 1rem auto 0;
    padding: 0 1rem;
}

.fc-hero-inner {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr);
    gap: 2rem;
    align-items: center;
    overflow: hidden;
    border-radius: 1.5rem;
    padding: clamp(1.5rem, 4vw, 3.25rem);
    background:
        radial-gradient(circle at 12% 18%, rgba(233, 229, 15, .28), transparent 24rem),
        radial-gradient(circle at 88% 10%, rgba(255, 255, 255, .18), transparent 18rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-hero-inner.has-hero-ribbon {
    padding-top: clamp(4rem, 5vw, 4.6rem);
}

.fc-hero-copy {
    position: relative;
    z-index: 2;
}

.fc-hero-ribbon {
    position: absolute;
    top: 0;
    right: clamp(1rem, 3vw, 2rem);
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    min-width: min(100%, 220px);
    padding: .9rem 1.1rem 1.2rem;
    background: linear-gradient(135deg, #ffe76a 0%, #ffd24d 48%, #f5b800 100%);
    color: var(--fc-primary-dark);
    font-size: .88rem;
    font-weight: 900;
    letter-spacing: .02em;
    line-height: 1.15;
    box-shadow: 0 18px 36px rgba(0, 0, 0, .22);
    clip-path: polygon(0 0, 100% 0, 100% 100%, 50% calc(100% - 16px), 0 100%);
}

.fc-hero-ribbon::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    border-top: 12px solid rgba(16, 43, 86, .28);
    border-left: 12px solid transparent;
}

.fc-hero-ribbon i {
    font-size: .95rem;
    flex: 0 0 auto;
}

.fc-hero-ribbon span {
    display: block;
}

.fc-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    width: fit-content;
    margin-bottom: 1.15rem;
    padding: .55rem .85rem;
    border: 1px solid rgba(255, 255, 255, .22);
    border-radius: 999px;
    background: rgba(255, 255, 255, .12);
    color: var(--fc-white);
    font-size: .92rem;
    font-weight: 700;
    backdrop-filter: blur(8px);
}

.fc-eyebrow i {
    color: var(--fc-secondary);
}

.fc-hero h1 {
    max-width: 780px;
    margin: 0;
    color: var(--fc-white);
    font-size: clamp(2.15rem, 5vw, 4.2rem);
    line-height: .98;
    letter-spacing: -.045em;
    font-weight: 850;
}

.fc-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
    margin-top: 1.4rem;
}

.fc-hero-meta span,
.fc-chip-grid span {
    display: inline-flex;
    align-items: center;
    min-height: 2.45rem;
    padding: .65rem .9rem;
    border-radius: .8rem;
    background: rgba(255, 255, 255, .13);
    border: 1px solid rgba(255, 255, 255, .18);
    color: var(--fc-white);
    font-weight: 750;
    line-height: 1.25;
}

.fc-hero-highlights {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    margin-top: 1.1rem;
}

.fc-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    min-height: 2.7rem;
    padding: .75rem 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .24);
    color: var(--fc-white);
    font-weight: 850;
    line-height: 1.15;
    box-shadow: 0 12px 28px rgba(0, 0, 0, .12);
}

.fc-hero-badge i {
    font-size: 1rem;
}

.fc-hero-badge--accent {
    background: linear-gradient(135deg, rgba(248, 178, 29, .98), rgba(255, 213, 79, .95));
    border-color: rgba(255, 239, 190, .45);
    color: var(--fc-primary-dark);
}

.fc-certifications {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: .75rem;
    max-width: 640px;
    margin-top: 1.4rem;
}

.fc-certification {
    display: flex;
    align-items: center;
    gap: .7rem;
    padding: .85rem 1rem;
    border-radius: .95rem;
    background: rgba(255, 255, 255, .95);
    color: var(--fc-primary);
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}

.fc-certification i {
    color: var(--fc-primary);
    font-size: 1.25rem;
}

.fc-chip-block {
    margin-top: 1.4rem;
}

.fc-chip-block p {
    margin: 0 0 .65rem;
    color: rgba(255, 255, 255, .86);
    font-weight: 800;
}

.fc-chip-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
}

.fc-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .8rem;
    margin-top: 1.8rem;
}

.fc-primary-button,
.fc-secondary-button,
.fc-final-cta a,
.fc-action-buttons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .55rem;
    border-radius: .85rem;
    padding: .9rem 1.15rem;
    font-weight: 850;
    text-decoration: none;
    line-height: 1.15;
}

.fc-primary-button {
    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
}

.fc-primary-button:hover {
    transform: translateY(-2px);
    color: var(--fc-primary-dark);
    filter: brightness(.98);
}

.fc-secondary-button {
    color: var(--fc-white);
    border: 1px solid rgba(255, 255, 255, .28);
    background: rgba(255, 255, 255, .10);
}

.fc-secondary-button:hover {
    color: var(--fc-white);
    background: rgba(255, 255, 255, .16);
}

.fc-card-button {
    color: var(--fc-primary);
    border: 1px solid var(--fc-border);
    background: var(--fc-soft);
    box-shadow: 0 10px 24px rgba(15, 26, 45, .08), inset 0 1px 0 rgba(255, 255, 255, .82);
    white-space: normal;
    text-align: center;
}

.fc-card-button:hover {
    color: var(--fc-white);
    background: var(--fc-primary);
    border-color: var(--fc-primary);
}

.fc-hero-visual {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.fc-cover-card {
    width: min(100%, 380px);
    aspect-ratio: 4 / 5;
    padding: .65rem;
    border-radius: 1.35rem;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .22);
    box-shadow: 0 24px 60px rgba(0, 0, 0, .25);
    transform: rotate(1.5deg);
}

.fc-cover-card img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    border-radius: 1rem;
}

.fc-cover-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    color: var(--fc-white);
    background: rgba(255, 255, 255, .10);
}

.fc-cover-placeholder i {
    font-size: 4rem;
    color: var(--fc-secondary);
}

.fc-cover-placeholder span {
    font-size: 1.2rem;
    font-weight: 800;
}

.fc-convenio-pill {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    max-width: 100%;
    padding: .65rem .9rem;
    border-radius: 999px;
    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    font-size: .9rem;
    font-weight: 800;
    box-shadow: 0 12px 30px rgba(0, 0, 0, .16);
}

.fc-intermediate-strip {
    margin-top: 1rem;
    padding: clamp(1rem, 3vw, 1.5rem);
    border: 1px solid var(--fc-border);
    border-radius: 1.25rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-intermediate-strip > p {
    margin: 0 0 1rem;
    text-align: center;
    color: var(--fc-primary);
    font-size: 1.05rem;
    font-weight: 750;
}

.fc-intermediate-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: .9rem;
}

.fc-intermediate-card {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: 1rem;
    border-radius: 1rem;
    background: var(--fc-soft);
    border: 1px solid var(--fc-border);
}

.fc-intermediate-icon,
.fc-feature-icon,
.fc-info-icon,
.fc-more-icon,
.fc-requirement-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    color: var(--fc-primary);
    background: rgba(22, 57, 111, .10);
}

.fc-intermediate-icon {
    width: 42px;
    height: 42px;
    border-radius: 999px;
}

.fc-intermediate-card h3 {
    margin: 0;
    color: var(--fc-primary);
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.2;
}

.fc-intermediate-card span {
    display: block;
    margin-top: .25rem;
    color: var(--fc-muted);
    font-size: .92rem;
    font-weight: 650;
}

.fc-intermediate-note {
    margin-top: 1rem !important;
    margin-bottom: 0 !important;
    color: var(--fc-muted) !important;
}

.fc-page-container {
    width: min(100% - 2rem, 1060px);
    margin: 0 auto;
    padding: clamp(2rem, 5vw, 3.5rem) 0;
}

.fc-feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 2.5rem;
}

.fc-feature-card {
    position: relative;
    overflow: hidden;
    min-height: 190px;
    padding: 1.35rem;
    border: 1px solid var(--fc-border);
    border-radius: 1.15rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-feature-card::after {
    content: "";
    position: absolute;
    inset: auto -2.5rem -2.5rem auto;
    width: 8rem;
    height: 8rem;
    border-radius: 999px;
    background: rgba(22, 57, 111, .06);
}

.fc-feature-icon {
    width: 58px;
    height: 58px;
    margin-bottom: 1rem;
    border-radius: 1rem;
    font-size: 1.45rem;
}

.fc-feature-card h3 {
    margin: 0 0 .45rem;
    color: var(--fc-primary);
    font-size: 1.05rem;
    font-weight: 850;
}

.fc-feature-card p {
    margin: 0;
    color: var(--fc-text);
    font-size: 1.1rem;
    font-weight: 750;
    line-height: 1.35;
}


.fc-content-sections {
    display: grid;
    gap: 1.25rem;
    margin: 0 0 2.75rem;
}

.fc-rich-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.25rem;
    align-items: start;
}

.fc-content-sections > .fc-content-panel,
.fc-rich-grid > .fc-content-panel,
.fc-message-box-standalone {
    margin: 0;
}

.fc-content-panel {
    min-width: 0;
    height: 100%;
}

.fc-content-panel.fc-content-wrapper {
    margin-bottom: 0;
    padding: 0;
    overflow: hidden;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease, background-color .22s ease;
}

.fc-content-panel.fc-content-wrapper:hover {
    transform: translateY(-3px);
    border-color: rgba(22, 57, 111, .22);
    box-shadow: 0 16px 36px rgba(15, 26, 45, .11);
}

.fc-content-panel.fc-content-wrapper > h2 {
    margin: 0;
    padding: 1.35rem 1.5rem 1.25rem;
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .18), transparent 14rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
    font-size: clamp(1.4rem, 2.6vw, 2rem);
    line-height: 1.1;
    font-weight: 900;
    letter-spacing: -.03em;
}

.fc-content-panel.fc-content-wrapper > h2::before {
    content: "Información";
    display: block;
    margin-bottom: .6rem;
    color: var(--fc-secondary);
    font-size: .78rem;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
    line-height: 1.2;
}

.fc-content-panel.fc-content-wrapper > h2::after {
    display: none;
}

.fc-content-panel-body {
    padding: 1.4rem 1.5rem 1.5rem;
}

.fc-content-panel-body > *:last-child,
.fc-content-panel > *:last-child {
    margin-bottom: 0;
}

.fc-child-content {
    width: 100%;
}

.fc-content-wrapper {
    width: 100%;
    margin: 0 0 2.75rem;
    padding: clamp(1.25rem, 3vw, 2rem);
    border: 1px solid var(--fc-border);
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, .86);
    box-shadow: var(--fc-shadow-soft);
    color: var(--fc-muted);
    font-size: 1.08rem;
    line-height: 1.75;
}

.fc-content-wrapper-compact {
    margin-bottom: 0;
}

.fc-content-wrapper h2,
.fc-rich-block h2 {
    margin: 2.25rem 0 1rem;
    color: var(--fc-primary);
    font-size: clamp(1.45rem, 3vw, 2rem);
    line-height: 1.15;
    letter-spacing: -.025em;
    font-weight: 850;
}

.fc-content-wrapper h2:first-child,
.fc-rich-block:first-child h2 {
    margin-top: 0;
}

.fc-content-wrapper h3 {
    margin: 1.75rem 0 .85rem;
    color: var(--fc-primary);
    font-size: 1.35rem;
    font-weight: 800;
}

.fc-content-wrapper p {
    margin: 0 0 1.15rem;
}

.fc-content-wrapper ul,
.fc-content-wrapper ol {
    margin: 0 0 1.35rem 1.25rem;
    padding-left: 1.25rem;
}

.fc-content-wrapper li {
    margin-bottom: .55rem;
}

.fc-content-wrapper a {
    color: var(--fc-primary);
    font-weight: 800;
    text-decoration-thickness: 2px;
    text-underline-offset: 3px;
}

.fc-message-box {
    margin-bottom: 1.75rem;
    padding: 1.25rem 1.35rem;
    border-left: 5px solid var(--fc-primary);
    border-radius: 1rem;
    background: var(--fc-soft);
    color: var(--fc-text);
}

.fc-rich-block {
    margin-bottom: 2rem;
}

.fc-native-blocks {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin: 2.5rem 0;
}

.fc-section {
    margin: 3rem 0;
}

.fc-section-title {
    margin: 0 0 1.25rem;
    color: var(--fc-primary);
    font-size: clamp(1.65rem, 4vw, 2.35rem);
    line-height: 1.05;
    letter-spacing: -.035em;
    font-weight: 900;
}

.fc-requirements-list {
    display: grid;
    gap: .9rem;
}

.fc-requirement {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem;
    border: 1px solid var(--fc-border);
    border-radius: 1rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-requirement-icon {
    width: 44px;
    height: 44px;
    border-radius: .85rem;
    font-size: 1.15rem;
}

.fc-requirement-content {
    color: var(--fc-muted);
    font-size: 1.02rem;
    line-height: 1.55;
}

.fc-requirement-content strong {
    color: var(--fc-text);
}

.fc-requirement-content ul {
    margin: .5rem 0 0 1.1rem;
    padding-left: 1rem;
}

.fc-alert-box {
    margin-top: 1rem;
    padding: 1.15rem 1.25rem;
    border-radius: 1rem;
    background: var(--fc-primary);
    color: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-alert-box h3 {
    margin: 0 0 .35rem;
    color: var(--fc-white);
    font-size: 1.1rem;
    font-weight: 850;
}

.fc-alert-box p {
    margin: 0;
    color: rgba(255, 255, 255, .88);
}

.fc-card {
    overflow: hidden;
    border: 1px solid var(--fc-border);
    border-radius: 1.25rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-card-header {
    padding: 1.4rem 1.5rem;
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .22), transparent 16rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
}

.fc-card-kicker {
    display: inline-flex;
    margin-bottom: .55rem;
    color: var(--fc-secondary);
    font-size: .9rem;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.fc-card-header h2 {
    margin: 0;
    color: var(--fc-white);
    font-size: clamp(1.45rem, 3vw, 2.15rem);
    line-height: 1.1;
    font-weight: 900;
}

.fc-table-wrap {
    overflow-x: auto;
}

.fc-pricing-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
}

.fc-pricing-table.is-without-usd {
    min-width: 560px;
}

.fc-pricing-table th,
.fc-pricing-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--fc-border);
    text-align: left;
    vertical-align: top;
}

.fc-pricing-table th {
    color: var(--fc-primary);
    background: #f7f9fc;
    font-size: .92rem;
    font-weight: 900;
}

.fc-pricing-table th span {
    display: block;
    margin-top: .2rem;
    color: var(--fc-muted);
    font-size: .78rem;
    font-weight: 650;
}

.fc-pricing-table td {
    color: var(--fc-muted);
    font-size: .98rem;
    line-height: 1.45;
}

.fc-pricing-table td:first-child {
    color: var(--fc-text);
    font-weight: 750;
}

.fc-pricing-table tr.is-highlighted td {
    background: rgba(233, 229, 15, .18);
}

.fc-pricing-note {
    padding: 1.15rem 1.5rem;
    background: var(--fc-soft);
    color: var(--fc-muted);
    font-size: .98rem;
    line-height: 1.55;
}

.fc-pricing-note p {
    margin: 0;
}

.fc-pricing-note a {
    color: var(--fc-primary);
    font-weight: 850;
}

.fc-empty-pricing {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 1.4rem;
    border: 1px dashed rgba(22, 57, 111, .28);
    border-radius: 1.15rem;
    background: var(--fc-soft);
}

.fc-empty-pricing i {
    flex: 0 0 auto;
    width: 52px;
    height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    background: var(--fc-white);
    color: var(--fc-primary);
    font-size: 1.35rem;
}

.fc-empty-pricing p {
    margin: 0;
    color: var(--fc-muted);
    font-size: 1.04rem;
    line-height: 1.5;
}

.fc-empty-pricing a {
    color: var(--fc-primary);
    font-weight: 850;
}

.fc-info-important {
    padding: clamp(1.25rem, 3vw, 2rem);
    border-radius: 1.25rem;
    background: var(--fc-white);
    border: 1px solid var(--fc-border);
    box-shadow: var(--fc-shadow-soft);
}

.fc-info-list {
    display: grid;
    gap: .9rem;
}

.fc-info-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    color: var(--fc-muted);
    line-height: 1.55;
}

.fc-info-row strong {
    color: var(--fc-text);
}

.fc-info-row a {
    color: var(--fc-primary);
    font-weight: 850;
    text-decoration: none;
}

.fc-info-row a:hover {
    text-decoration: underline;
}

.fc-info-icon {
    width: 42px;
    height: 42px;
    border-radius: .8rem;
    font-size: 1.05rem;
}

.fc-envios-list {
    margin: .55rem 0 0 1rem;
    padding-left: 1rem;
}

.fc-envios-list li {
    margin-bottom: .35rem;
}

.fc-envios-list span {
    color: var(--fc-primary);
    font-weight: 850;
}

.fc-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: .75rem;
    margin-top: 1.25rem;
}

.fc-action-buttons a {
    color: var(--fc-primary);
    background: var(--fc-soft);
    border: 1px solid var(--fc-border);
}

.fc-action-buttons a:hover {
    transform: translateY(-2px);
    color: var(--fc-primary);
    background: var(--fc-soft-2);
}

.fc-more-grid {
    display: grid;
    grid-template-columns: repeat(var(--fc-grid-cols, 3), minmax(0, 1fr));
    gap: 1rem;
}

.fc-more-card {
    padding: 1.25rem;
    border: 1px solid var(--fc-border);
    border-radius: 1.15rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow-soft);
}

.fc-more-icon {
    width: 50px;
    height: 50px;
    margin-bottom: 1rem;
    border-radius: 1rem;
    font-size: 1.25rem;
}

.fc-more-card h3 {
    margin: 0 0 .65rem;
    color: var(--fc-primary);
    font-size: 1.18rem;
    font-weight: 850;
}

.fc-more-card p {
    margin: 0;
    color: var(--fc-muted);
    line-height: 1.62;
}

.fc-assistant-card {
    display: grid;
    grid-template-columns: 190px minmax(0, 1fr);
    gap: 1.5rem;
    align-items: center;
    margin: 3rem 0;
    padding: clamp(1.25rem, 3vw, 1.75rem);
    border: 1px solid var(--fc-border);
    border-top: 6px solid var(--fc-primary);
    border-radius: 1.25rem;
    background: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-assistant-photo img,
.fc-assistant-placeholder {
    width: 170px;
    height: 170px;
    border-radius: 1.15rem;
    object-fit: cover;
    box-shadow: var(--fc-shadow-soft);
}

.fc-assistant-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--fc-primary);
    color: var(--fc-white);
    font-size: 3rem;
    font-weight: 900;
}

.fc-assistant-label {
    display: inline-flex;
    margin-bottom: .55rem;
    padding: .35rem .7rem;
    border-radius: 999px;
    background: var(--fc-soft);
    color: var(--fc-primary);
    font-size: .85rem;
    font-weight: 850;
}

.fc-assistant-copy h2 {
    margin: 0 0 .3rem;
    color: var(--fc-primary);
    font-size: clamp(1.55rem, 3vw, 2.2rem);
    line-height: 1.1;
    font-weight: 900;
}

.fc-assistant-role {
    display: inline-block;
    margin: 0 0 .85rem !important;
    color: var(--fc-text) !important;
    font-weight: 800;
}

.fc-assistant-copy p {
    margin: 0;
    color: var(--fc-muted);
    font-size: 1.05rem;
    line-height: 1.65;
}

.fc-assistant-copy a {
    color: var(--fc-primary);
    font-weight: 850;
    text-decoration: none;
}

.fc-assistant-copy a:hover {
    text-decoration: underline;
}

.fc-final-cta {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1.5rem;
    align-items: center;
    margin-top: 3rem;
    padding: clamp(1.5rem, 4vw, 2.4rem);
    border-radius: 1.35rem;
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .30), transparent 18rem),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);
    color: var(--fc-white);
    box-shadow: var(--fc-shadow);
}

.fc-final-cta span {
    display: inline-flex;
    margin-bottom: .5rem;
    color: var(--fc-secondary);
    font-size: .9rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.fc-final-cta h2 {
    margin: 0;
    color: var(--fc-white);
    font-size: clamp(1.6rem, 4vw, 2.5rem);
    line-height: 1.05;
    letter-spacing: -.035em;
    font-weight: 900;
}

.fc-final-cta p {
    max-width: 680px;
    margin: .7rem 0 0;
    color: rgba(255, 255, 255, .84);
    font-size: 1.05rem;
    line-height: 1.55;
}

.fc-final-cta a {
    white-space: nowrap;
    color: var(--fc-primary-dark);
    background: var(--fc-secondary);
    box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
}

.fc-final-cta a:hover {
    transform: translateY(-2px);
    color: var(--fc-primary-dark);
}

@media (max-width: 980px) {
    .fc-hero-inner {
        grid-template-columns: 1fr;
    }

    .fc-hero-inner.has-hero-ribbon {
        padding-top: 4.8rem;
    }

    .fc-rich-grid {
        grid-template-columns: 1fr;
    }

    .fc-hero-visual {
        order: -1;
    }

    .fc-cover-card {
        width: min(100%, 340px);
        transform: none;
    }

    .fc-feature-grid,
    .fc-more-grid {
        grid-template-columns: 1fr;
    }

    .fc-final-cta {
        grid-template-columns: 1fr;
    }

    .fc-final-cta a {
        width: fit-content;
    }
}

@media (max-width: 700px) {
    .fc-hero {
        padding: 0 .75rem;
    }

    .fc-content-panel.fc-content-wrapper > h2 {
        padding: 1.1rem 1.15rem 1rem;
    }

    .fc-content-panel-body {
        padding: 1.1rem 1.15rem 1.2rem;
    }

    .fc-hero-inner {
        border-radius: 1rem;
        padding: 1.25rem;
    }

    .fc-hero-inner.has-hero-ribbon {
        padding-top: 4.5rem;
    }

    .fc-hero-ribbon {
        right: 1rem;
        min-width: 0;
        max-width: calc(100% - 2rem);
        padding: .82rem 1rem 1.05rem;
        font-size: .8rem;
    }

    .fc-page-container {
        width: min(100% - 1.5rem, 1060px);
        padding-top: 2rem;
    }

    .fc-hero-meta span,
    .fc-chip-grid span {
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .fc-certifications {
        grid-template-columns: 1fr;
    }

    .fc-hero-actions {
        flex-direction: column;
    }

    .fc-primary-button,
    .fc-secondary-button,
    .fc-final-cta a,
    .fc-action-buttons a {
        width: 100%;
    }

    .fc-feature-card {
        min-height: auto;
    }

    .fc-content-wrapper {
        padding: 1.1rem;
        border-radius: 1rem;
        font-size: 1rem;
    }

    .fc-requirement,
    .fc-info-row,
    .fc-empty-pricing {
        align-items: flex-start;
    }

    .fc-assistant-card {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .fc-assistant-photo {
        display: flex;
        justify-content: center;
    }

    .fc-action-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .fc-hero h1 {
        font-size: 2rem;
    }

    .fc-eyebrow {
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .fc-cover-card {
        aspect-ratio: 16 / 11;
    }

    .fc-intermediate-card,
    .fc-requirement {
        flex-direction: column;
    }

    .fc-info-row {
        gap: .75rem;
    }

    .fc-table-wrap {
        margin-left: -1rem;
        margin-right: -1rem;
    }
}

.flacso-carta-virtual {
    --fc-radius-sm: .85rem;
    --fc-radius-md: 1.15rem;
    --fc-radius-lg: 1.45rem;
    --fc-ring: 0 0 0 4px rgba(233, 229, 15, .34);
    --fc-gradient-primary: linear-gradient(135deg, var(--fc-primary) 0%, #123264 48%, var(--fc-primary-dark) 100%);
    --fc-card-bg: rgba(255, 255, 255, .94);
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
}

.flacso-carta-virtual::before {
    content: "";
    position: fixed;
    inset: 0;
    z-index: -1;
    pointer-events: none;
    background:
        radial-gradient(circle at top left, rgba(22, 57, 111, .07), transparent 26rem),
        radial-gradient(circle at bottom right, rgba(233, 229, 15, .12), transparent 24rem);
}

.flacso-carta-virtual a:focus-visible,
.flacso-carta-virtual button:focus-visible {
    outline: none;
    box-shadow: var(--fc-ring);
}

#fc-info-clave {
    scroll-margin-top: 5rem;
}

.fc-hero-inner {
    isolation: isolate;
    border: 1px solid rgba(255, 255, 255, .16);
    background:
        radial-gradient(circle at 12% 18%, rgba(233, 229, 15, .30), transparent 24rem),
        radial-gradient(circle at 86% 12%, rgba(255, 255, 255, .18), transparent 18rem),
        radial-gradient(circle at 68% 92%, rgba(255, 255, 255, .10), transparent 20rem),
        var(--fc-gradient-primary);
}

.fc-hero-inner::before,
.fc-hero-inner::after {
    content: "";
    position: absolute;
    pointer-events: none;
    border-radius: 999px;
    z-index: 1;
}

.fc-hero-inner::before {
    width: 18rem;
    height: 18rem;
    right: -7rem;
    bottom: -9rem;
    border: 1px solid rgba(255, 255, 255, .18);
    background: rgba(255, 255, 255, .06);
}

.fc-hero-inner::after {
    width: 8rem;
    height: 8rem;
    left: 46%;
    top: -3rem;
    background: rgba(233, 229, 15, .16);
    filter: blur(1px);
}

.fc-eyebrow,
.fc-hero-meta span,
.fc-chip-grid span,
.fc-secondary-button {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .16);
}

.fc-hero h1 {
    text-wrap: balance;
    text-shadow: 0 2px 18px rgba(0, 0, 0, .16);
}

.fc-certification {
    border: 1px solid rgba(255, 255, 255, .6);
}

.fc-primary-button,
.fc-final-cta a {
    border: 1px solid rgba(255, 255, 255, .28);
    box-shadow: 0 14px 34px rgba(0, 0, 0, .22), inset 0 1px 0 rgba(255, 255, 255, .32);
}

.fc-primary-button:hover,
.fc-final-cta a:hover,
.fc-action-buttons a:hover {
    box-shadow: 0 18px 38px rgba(15, 26, 45, .18);
}

.fc-cover-card {
    position: relative;
}

.fc-cover-card::before {
    content: "";
    position: absolute;
    inset: -.45rem;
    z-index: -1;
    border-radius: 1.65rem;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, .24), rgba(255, 255, 255, .04)),
        rgba(255, 255, 255, .06);
}

.fc-cover-card img {
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .2);
}

.fc-convenio-pill {
    border: 1px solid rgba(255, 255, 255, .32);
}

.fc-page-container {
    position: relative;
}

.fc-intermediate-strip,
.fc-feature-card,
.fc-content-wrapper,
.fc-requirement,
.fc-card,
.fc-info-important,
.fc-more-card,
.fc-assistant-card,
.fc-final-cta,
.fc-empty-pricing {
    backdrop-filter: blur(10px);
}

.fc-feature-card,
.fc-intermediate-card,
.fc-requirement,
.fc-more-card,
.fc-empty-pricing {
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease, background-color .22s ease;
}

.fc-feature-card:hover,
.fc-intermediate-card:hover,
.fc-requirement:hover,
.fc-more-card:hover {
    transform: translateY(-3px);
    border-color: rgba(22, 57, 111, .22);
    box-shadow: 0 16px 36px rgba(15, 26, 45, .11);
}

.fc-feature-card::before,
.fc-more-card::before {
    content: "";
    display: block;
    width: 3rem;
    height: .25rem;
    margin-bottom: 1rem;
    border-radius: 999px;
    background: var(--fc-secondary);
}

.fc-feature-card .fc-feature-icon,
.fc-more-card .fc-more-icon {
    margin-top: 0;
}

.fc-feature-icon,
.fc-info-icon,
.fc-more-icon,
.fc-requirement-icon,
.fc-intermediate-icon {
    border: 1px solid rgba(22, 57, 111, .10);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
}

.fc-content-wrapper,
.fc-info-important,
.fc-card,
.fc-assistant-card {
    background:
        linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(255, 255, 255, .90)),
        var(--fc-white);
}

.fc-content-wrapper > *:last-child,
.fc-rich-block > *:last-child,
.fc-more-card > *:last-child,
.fc-info-row > div:last-child > *:last-child {
    margin-bottom: 0;
}

.fc-content-wrapper h2,
.fc-rich-block h2,
.fc-section-title {
    text-wrap: balance;
}

.fc-content-wrapper h2,
.fc-rich-block h2 {
    position: relative;
    padding-bottom: .65rem;
}

.fc-content-wrapper h2::after,
.fc-rich-block h2::after,
.fc-section-title::after {
    content: "";
    display: block;
    width: 4rem;
    height: .28rem;
    margin-top: .7rem;
    border-radius: 999px;
    background: var(--fc-secondary);
}

.fc-content-wrapper h3 {
    padding-left: .85rem;
    border-left: 4px solid var(--fc-secondary);
}

.fc-content-wrapper blockquote {
    margin: 1.4rem 0;
    padding: 1rem 1.25rem;
    border-left: 5px solid var(--fc-primary);
    border-radius: .95rem;
    background: var(--fc-soft);
    color: var(--fc-text);
}

.fc-content-wrapper table {
    width: 100%;
    margin: 1.35rem 0;
    border-collapse: collapse;
    overflow: hidden;
    border: 1px solid var(--fc-border);
    border-radius: 1rem;
    background: var(--fc-white);
}

.fc-content-wrapper th,
.fc-content-wrapper td {
    padding: .9rem 1rem;
    border-bottom: 1px solid var(--fc-border);
    text-align: left;
    vertical-align: top;
}

.fc-content-wrapper th {
    color: var(--fc-primary);
    background: var(--fc-soft);
    font-weight: 850;
}

.fc-content-wrapper tr:last-child td {
    border-bottom: 0;
}

.fc-message-box {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .65);
}

.fc-native-blocks > * {
    min-width: 0;
    border-radius: var(--fc-radius-md);
}

.fc-section {
    scroll-margin-top: 5rem;
}

.fc-section-title {
    position: relative;
}

.fc-requirement {
    background:
        linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(247, 249, 252, .88)),
        var(--fc-white);
}

.fc-alert-box {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .20), transparent 12rem),
        var(--fc-gradient-primary);
}

.fc-alert-box::after {
    content: "";
    position: absolute;
    right: -2.25rem;
    bottom: -2.25rem;
    width: 7rem;
    height: 7rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .08);
}

.fc-card {
    position: relative;
}

.fc-card-header {
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .24), transparent 16rem),
        var(--fc-gradient-primary);
}

.fc-table-wrap {
    background:
        linear-gradient(90deg, rgba(22, 57, 111, .05), transparent 1.2rem) left center / 1.2rem 100% no-repeat,
        var(--fc-white);
}

.fc-pricing-table tbody tr:nth-child(even) td {
    background: rgba(237, 242, 246, .38);
}

.fc-pricing-table tbody tr:hover td {
    background: rgba(22, 57, 111, .045);
}

.fc-pricing-table tr.is-highlighted td,
.fc-pricing-table tr.is-highlighted:hover td {
    background: linear-gradient(180deg, rgba(255, 245, 157, .78), rgba(255, 236, 132, .72)) !important;
    color: var(--fc-text);
}

.fc-pricing-table tr.is-highlighted td:first-child,
.fc-pricing-table tr.is-highlighted:hover td:first-child {
    background: linear-gradient(180deg, rgba(255, 239, 120, .92), rgba(255, 224, 102, .88)) !important;
}

.fc-pricing-note {
    border-top: 1px solid var(--fc-border);
}

.fc-info-important {
    position: relative;
    overflow: hidden;
}

.fc-info-important::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: .35rem;
    background: var(--fc-primary);
}

.fc-info-list {
    position: relative;
    z-index: 1;
}

.fc-info-row {
    padding: .9rem;
    border-radius: 1rem;
    background: rgba(237, 242, 246, .52);
}

.fc-info-row + .fc-info-row {
    margin-top: .05rem;
}

.fc-action-buttons a {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
}

.fc-more-card {
    position: relative;
    overflow: hidden;
}

.fc-more-card::after {
    content: "";
    position: absolute;
    right: -2rem;
    bottom: -2rem;
    width: 6.5rem;
    height: 6.5rem;
    border-radius: 999px;
    background: rgba(22, 57, 111, .045);
}

.fc-more-card > * {
    position: relative;
    z-index: 1;
}

.fc-assistant-card {
    position: relative;
    overflow: hidden;
}

.fc-assistant-card::before {
    content: "";
    position: absolute;
    right: -4rem;
    top: -4rem;
    width: 13rem;
    height: 13rem;
    border-radius: 999px;
    background: rgba(233, 229, 15, .16);
}

.fc-assistant-photo,
.fc-assistant-copy {
    position: relative;
    z-index: 1;
}

.fc-assistant-photo img,
.fc-assistant-placeholder {
    border: 5px solid var(--fc-white);
}

.fc-final-cta {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .18);
    background:
        radial-gradient(circle at 100% 0%, rgba(233, 229, 15, .32), transparent 18rem),
        radial-gradient(circle at 0% 100%, rgba(255, 255, 255, .11), transparent 18rem),
        var(--fc-gradient-primary);
}

.fc-final-cta::after {
    content: "";
    position: absolute;
    right: -3rem;
    bottom: -5rem;
    width: 16rem;
    height: 16rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .08);
}

.fc-final-cta > * {
    position: relative;
    z-index: 1;
}

@media (prefers-reduced-motion: reduce) {
    .flacso-carta-virtual *,
    .flacso-carta-virtual a {
        transition: none !important;
        scroll-behavior: auto !important;
    }

    .fc-primary-button:hover,
    .fc-final-cta a:hover,
    .fc-action-buttons a:hover,
    .fc-feature-card:hover,
    .fc-intermediate-card:hover,
    .fc-requirement:hover,
    .fc-more-card:hover {
        transform: none;
    }
}

@media (max-width: 980px) {
    .fc-hero-inner::after {
        left: auto;
        right: 2rem;
    }

    .fc-feature-card:hover,
    .fc-intermediate-card:hover,
    .fc-requirement:hover,
    .fc-more-card:hover {
        transform: none;
    }
}

@media (max-width: 700px) {
    .fc-feature-card::before,
    .fc-more-card::before {
        margin-bottom: .85rem;
    }

    .fc-info-important::before {
        width: .25rem;
    }

    .fc-info-row {
        padding: .85rem;
    }

    .fc-content-wrapper table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

@media (max-width: 480px) {
    .fc-hero-inner::before,
    .fc-hero-inner::after,
    .fc-final-cta::after {
        opacity: .55;
    }

    .fc-info-row {
        flex-direction: column;
    }

    .fc-info-icon {
        width: 40px;
        height: 40px;
    }
}


/* Accesibilidad y CTA flotante inteligente */
.fc-sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

.fc-skip-link {
    position: fixed;
    top: .75rem;
    left: .75rem;
    z-index: 100000;
    transform: translateY(-140%);
    padding: .85rem 1rem;
    border-radius: .75rem;
    background: var(--fc-primary-dark);
    color: var(--fc-white);
    font-weight: 850;
    text-decoration: none;
    box-shadow: var(--fc-shadow);
}

.fc-skip-link:focus,
.fc-skip-link:focus-visible {
    transform: translateY(0);
    color: var(--fc-white);
    outline: 3px solid var(--fc-secondary);
    outline-offset: 3px;
}

.flacso-carta-virtual a:focus-visible,
.flacso-carta-virtual button:focus-visible,
.flacso-carta-virtual [tabindex]:focus-visible {
    outline: 3px solid var(--fc-secondary);
    outline-offset: 4px;
    box-shadow: 0 0 0 6px rgba(22, 57, 111, .28);
}

.fc-primary-button,
.fc-secondary-button,
.fc-final-cta a,
.fc-action-buttons a,
.fc-floating-preinscripcion {
    min-height: 44px;
}

.fc-content-wrapper :where(p, li),
.fc-requirement-content,
.fc-info-row,
.fc-more-card p,
.fc-assistant-copy p,
.fc-pricing-note,
.fc-pricing-table td {
    text-wrap: pretty;
}

.fc-content-wrapper a:not(.fc-primary-button):not(.fc-secondary-button),
.fc-pricing-note a,
.fc-empty-pricing a,
.fc-info-row a,
.fc-assistant-copy a {
    text-decoration: underline;
    text-decoration-thickness: 2px;
    text-underline-offset: 3px;
}

.fc-content-wrapper a:hover,
.fc-pricing-note a:hover,
.fc-empty-pricing a:hover,
.fc-info-row a:hover,
.fc-assistant-copy a:hover {
    text-decoration-thickness: 3px;
}

.fc-pricing-table th {
    line-height: 1.25;
}

.fc-pricing-table th,
.fc-pricing-table td {
    word-break: normal;
    overflow-wrap: anywhere;
}

.fc-floating-preinscripcion {
    position: fixed;
    right: max(1.5rem, env(safe-area-inset-right));
    bottom: max(1.5rem, env(safe-area-inset-bottom));
    z-index: 9999;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .75rem;
    padding: 1rem 1.6rem 1rem 1rem;
    border-radius: 999px;
    font-size: 1.08rem;
    font-weight: 900;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    text-decoration: none;
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    pointer-events: auto;
    transition: transform .24s ease, box-shadow .24s ease, opacity .24s ease, visibility .24s ease;
    backdrop-filter: blur(10px);
}

.fc-floating-preinscripcion:hover {
    transform: translateY(-4px) scale(1.02);
}

.fc-floating-preinscripcion.is-hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateY(1rem) scale(.96);
    pointer-events: none;
}

@media (max-width: 700px) {
    .fc-floating-preinscripcion {
        right: .85rem;
        bottom: .85rem;
        left: .85rem;
        width: auto;
        justify-content: center;
        border-radius: 1.05rem;
        padding: 1rem 1.15rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fc-floating-preinscripcion,
    .fc-skip-link {
        transition: none !important;
    }
}


/* =========================================================
   Ajustes finales: accesibilidad, ancho uniforme, cards y tabla móvil
   ========================================================= */
.flacso-carta-virtual {
    --fc-page-width: 1060px;
    --fc-page-gutter: 2rem;
    --fc-card-radius: 1.15rem;
    --fc-card-padding: 1.25rem;
    --fc-cta-warm-1: #fff682;
    --fc-cta-warm-2: #fff200;
    --fc-cta-warm-3: #ffd400;
    --fc-cta-ring: rgba(255, 231, 92, .28);
    --fc-cta-shadow: rgba(15, 26, 45, .24);
}

.fc-hero,
.fc-page-container {
    width: min(100% - var(--fc-page-gutter), var(--fc-page-width));
    max-width: var(--fc-page-width);
    margin-left: auto;
    margin-right: auto;
}

.fc-hero {
    padding-left: 0;
    padding-right: 0;
}

.fc-page-container {
    padding-inline: 0;
}

.fc-hero-inner,
.fc-intermediate-strip,
.fc-feature-card,
.fc-content-wrapper,
.fc-requirement,
.fc-card,
.fc-info-important,
.fc-more-card,
.fc-assistant-card,
.fc-final-cta,
.fc-empty-pricing {
    max-width: 100%;
}

.fc-feature-grid,
.fc-more-grid,
.fc-intermediate-grid,
.fc-rich-grid {
    align-items: stretch;
}

.fc-feature-grid,
.fc-more-grid {
    grid-auto-rows: 1fr;
}

.fc-feature-card,
.fc-more-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-width: 0;
    border-radius: var(--fc-card-radius);
}

.fc-feature-card,
.fc-more-card,
.fc-requirement,
.fc-intermediate-card {
    overflow-wrap: anywhere;
    word-break: normal;
}

.fc-feature-card {
    min-height: 210px;
}

.fc-more-card {
    min-height: 235px;
    padding: var(--fc-card-padding);
}

.fc-feature-icon,
.fc-more-icon {
    width: 54px;
    height: 54px;
    border-radius: 1rem;
}

.fc-feature-card h3,
.fc-more-card h3 {
    min-height: 2.35em;
    text-wrap: balance;
}

.fc-feature-card p,
.fc-more-card p {
    line-height: 1.6;
}

.fc-more-card p {
    flex: 1;
}

.fc-content-panel.fc-content-wrapper,
.fc-rich-block {
    height: 100%;
}

.fc-primary-button,
.fc-secondary-button,
.fc-final-cta a,
.fc-action-buttons a,
.fc-floating-preinscripcion {
    min-height: 44px;
}

.fc-primary-button,
.fc-final-cta a,
.fc-floating-preinscripcion {
    position: relative;
    isolation: isolate;
    border: 2px solid rgba(16, 43, 86, .74);
    background: linear-gradient(180deg, var(--fc-cta-warm-1) 0%, var(--fc-cta-warm-2) 52%, var(--fc-cta-warm-3) 100%);
    color: var(--fc-primary-dark);
    box-shadow:
        0 10px 0 rgba(16, 43, 86, .16),
        0 20px 34px var(--fc-cta-shadow),
        0 0 0 10px var(--fc-cta-ring);
    transition:
        transform .22s ease,
        box-shadow .22s ease,
        background .22s ease,
        border-color .22s ease,
        color .22s ease;
}

.fc-primary-button i,
.fc-final-cta a i,
.fc-floating-preinscripcion i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    background: rgba(16, 43, 86, .14);
    color: var(--fc-primary-dark);
    flex: 0 0 auto;
    transition: transform .22s ease, background-color .22s ease, color .22s ease;
}

.fc-primary-button:hover,
.fc-final-cta a:hover,
.fc-floating-preinscripcion:hover {
    color: var(--fc-primary-dark);
    border-color: rgba(16, 43, 86, .96);
    background: linear-gradient(180deg, #fff9b5 0%, #ffe85b 48%, #ffcb05 100%);
    transform: translateY(-5px);
    box-shadow:
        0 14px 0 rgba(16, 43, 86, .12),
        0 28px 42px rgba(15, 26, 45, .28),
        0 0 0 12px rgba(255, 231, 92, .36);
}

.fc-primary-button:hover i,
.fc-final-cta a:hover i,
.fc-floating-preinscripcion:hover i {
    background: rgba(16, 43, 86, .92);
    color: var(--fc-white);
    transform: scale(1.06);
}

.fc-primary-button:active,
.fc-final-cta a:active,
.fc-floating-preinscripcion:active {
    transform: translateY(-1px);
    box-shadow:
        0 6px 0 rgba(16, 43, 86, .14),
        0 12px 24px rgba(15, 26, 45, .22),
        0 0 0 8px rgba(255, 231, 92, .22);
}

.fc-primary-button:focus-visible,
.fc-secondary-button:focus-visible,
.fc-final-cta a:focus-visible,
.fc-action-buttons a:focus-visible,
.fc-floating-preinscripcion:focus-visible {
    outline: 3px solid var(--fc-secondary);
    outline-offset: 4px;
    box-shadow: 0 0 0 6px rgba(16, 43, 86, .32);
}

.fc-table-wrap {
    overflow-x: visible;
}

.fc-pricing-table {
    width: 100%;
    min-width: 0;
    table-layout: fixed;
}

.fc-pricing-table th,
.fc-pricing-table td {
    overflow-wrap: anywhere;
    word-break: normal;
}

.fc-pricing-table th:first-child,
.fc-pricing-table td:first-child {
    width: 42%;
}

.fc-pricing-table.is-without-usd th:first-child,
.fc-pricing-table.is-without-usd td:first-child {
    width: 58%;
}

.fc-pricing-table.is-without-usd th:nth-child(2),
.fc-pricing-table.is-without-usd td:nth-child(2) {
    width: 42%;
}

.fc-pricing-table th:nth-child(2),
.fc-pricing-table td:nth-child(2),
.fc-pricing-table th:nth-child(3),
.fc-pricing-table td:nth-child(3) {
    width: 29%;
}

@media (prefers-contrast: more) {
    .flacso-carta-virtual {
        --fc-muted: #243044;
        --fc-border: rgba(16, 43, 86, .42);
    }

    .fc-card,
    .fc-feature-card,
    .fc-more-card,
    .fc-content-wrapper,
    .fc-info-important,
    .fc-requirement {
        border-width: 2px;
    }
}

@media (max-width: 980px) {
    .fc-feature-grid,
    .fc-more-grid {
        grid-auto-rows: auto;
    }

    .fc-feature-card,
    .fc-more-card {
        min-height: 0;
    }
}

@media (max-width: 700px) {
    .flacso-carta-virtual {
        --fc-page-gutter: 1.5rem;
    }

    .fc-hero,
    .fc-page-container {
        width: min(100% - var(--fc-page-gutter), var(--fc-page-width));
    }

    .fc-card-header,
    .fc-pricing-note {
        padding-inline: 1.1rem;
    }

    .fc-table-wrap {
        margin-left: 0;
        margin-right: 0;
        overflow: visible;
        background: var(--fc-white);
    }

    .fc-pricing-table {
        display: block;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: auto;
    }

    .fc-pricing-table thead {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }

    .fc-pricing-table tbody {
        display: grid;
        gap: .9rem;
        padding: 1rem;
    }

    .fc-pricing-table tr {
        display: block;
        overflow: hidden;
        border: 1px solid var(--fc-border);
        border-radius: 1rem;
        background: var(--fc-white);
        box-shadow: var(--fc-shadow-soft);
    }

    .fc-pricing-table th,
    .fc-pricing-table td,
    .fc-pricing-table th:first-child,
    .fc-pricing-table td:first-child,
    .fc-pricing-table th:nth-child(2),
    .fc-pricing-table td:nth-child(2),
    .fc-pricing-table th:nth-child(3),
    .fc-pricing-table td:nth-child(3) {
        width: 100%;
    }

    .fc-pricing-table td {
        display: grid;
        grid-template-columns: minmax(7.25rem, 42%) minmax(0, 1fr);
        gap: .75rem;
        align-items: start;
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--fc-border);
        background: var(--fc-white) !important;
        color: var(--fc-text);
        font-size: .98rem;
    }

    .fc-pricing-table td:last-child {
        border-bottom: 0;
    }

    .fc-pricing-table td::before {
        content: attr(data-label);
        color: var(--fc-primary);
        font-size: .78rem;
        font-weight: 900;
        line-height: 1.25;
        letter-spacing: .045em;
        text-transform: uppercase;
    }

    .fc-pricing-table td:first-child {
        display: block;
        background: rgba(237, 242, 246, .72) !important;
        color: var(--fc-primary);
        font-weight: 900;
    }

    .fc-pricing-table td:first-child::before {
        display: block;
        margin-bottom: .25rem;
    }

    .fc-pricing-table tr.is-highlighted {
        border-color: rgba(22, 57, 111, .32);
        box-shadow: 0 0 0 3px rgba(255, 224, 102, .45), var(--fc-shadow-soft);
    }

    .fc-pricing-table tr.is-highlighted td {
        background: linear-gradient(180deg, rgba(255, 245, 157, .82), rgba(255, 236, 132, .76)) !important;
    }

    .fc-pricing-table tr.is-highlighted td:first-child {
        background: linear-gradient(180deg, rgba(255, 239, 120, .94), rgba(255, 224, 102, .9)) !important;
    }
}

@media (max-width: 480px) {
    .flacso-carta-virtual {
        --fc-page-gutter: 1rem;
    }

    .fc-hero,
    .fc-page-container {
        width: min(100% - var(--fc-page-gutter), var(--fc-page-width));
    }

    .fc-pricing-table tbody {
        padding: .75rem;
    }

    .fc-pricing-table td {
        grid-template-columns: 1fr;
        gap: .25rem;
    }

    .fc-pricing-table td::before {
        margin-bottom: .1rem;
    }

    .fc-feature-card h3,
    .fc-more-card h3 {
        min-height: 0;
    }
}



/* =========================================================
   Botón flotante de preinscripción: versión final
   Este bloque debe quedar al final para no ser pisado por estilos previos.
   ========================================================= */
.flacso-carta-virtual .fc-floating-preinscripcion {
    position: fixed !important;
    right: max(1.25rem, env(safe-area-inset-right));
    bottom: max(1.25rem, env(safe-area-inset-bottom));
    left: auto;
    z-index: 99999;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .85rem;

    width: auto;
    max-width: min(92vw, 360px);
    min-height: 54px;
    padding: .9rem 1.15rem .9rem .9rem;

    border: 1px solid rgba(255, 255, 255, .22);
    border-radius: 999px;
    background:
        radial-gradient(circle at 18% 20%, rgba(254, 210, 34, .22), transparent 38%),
        linear-gradient(135deg, var(--fc-primary) 0%, var(--fc-primary-dark) 100%);

    color: var(--fc-white);
    text-decoration: none;
    text-transform: none;
    letter-spacing: 0;
    line-height: 1.15;
    isolation: isolate;

    box-shadow:
        0 18px 40px rgba(15, 26, 45, .32),
        0 0 0 6px rgba(22, 57, 111, .10);

    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    pointer-events: auto;

    transition:
        transform .22s ease,
        box-shadow .22s ease,
        opacity .22s ease,
        visibility .22s ease,
        background .22s ease;
}

.flacso-carta-virtual .fc-floating-preinscripcion i {
    width: 2.55rem;
    height: 2.55rem;
    border-radius: 999px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;

    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    font-size: 1.15rem;
    transform: none;
}

.flacso-carta-virtual .fc-floating-copy {
    display: grid;
    gap: .12rem;
    min-width: 0;
}

.flacso-carta-virtual .fc-floating-copy strong {
    display: block;
    color: var(--fc-white);
    font-size: .98rem;
    font-weight: 900;
    white-space: nowrap;
}

.flacso-carta-virtual .fc-floating-copy small {
    display: block;
    color: rgba(255, 255, 255, .78);
    font-size: .76rem;
    font-weight: 750;
    white-space: nowrap;
}

.flacso-carta-virtual .fc-floating-preinscripcion:hover {
    color: var(--fc-white);
    border-color: rgba(255, 255, 255, .30);
    background:
        radial-gradient(circle at 18% 20%, rgba(254, 210, 34, .28), transparent 38%),
        linear-gradient(135deg, #1d3f7b 0%, var(--fc-primary-dark) 100%);
    transform: translateY(-4px) scale(1.015);
    box-shadow:
        0 22px 48px rgba(15, 26, 45, .38),
        0 0 0 8px rgba(254, 210, 34, .18);
}

.flacso-carta-virtual .fc-floating-preinscripcion:hover i {
    background: #ffe76a;
    color: var(--fc-primary-dark);
    transform: none;
}

.flacso-carta-virtual .fc-floating-preinscripcion:active {
    transform: translateY(-1px) scale(.995);
}

.flacso-carta-virtual .fc-floating-preinscripcion:focus-visible {
    outline: 3px solid var(--fc-secondary);
    outline-offset: 4px;
    box-shadow:
        0 0 0 6px rgba(16, 43, 86, .32),
        0 18px 40px rgba(15, 26, 45, .32);
}

.flacso-carta-virtual .fc-floating-preinscripcion.is-hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateY(1rem) scale(.96);
    pointer-events: none;
}

@media (max-width: 700px) {
    .flacso-carta-virtual .fc-floating-preinscripcion {
        right: .85rem;
        bottom: max(.85rem, env(safe-area-inset-bottom));
        left: .85rem;
        max-width: none;
        width: auto;
        border-radius: 1.1rem;
        padding: .85rem 1rem;
    }

    .flacso-carta-virtual .fc-floating-copy strong {
        font-size: .94rem;
    }

    .flacso-carta-virtual .fc-floating-copy small {
        font-size: .73rem;
    }
}


/* =========================================================
   Mejora visual de tabla de inversión
   Este bloque va al final para pisar estilos anteriores.
========================================================= */
.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] {
    overflow: hidden;
    border: 1px solid rgba(22, 57, 111, .12);
    border-radius: 1.45rem;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(247, 249, 252, .95));
    box-shadow:
        0 24px 60px rgba(15, 26, 45, .11),
        0 1px 0 rgba(255, 255, 255, .9) inset;
}

.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-card-header {
    position: relative;
    overflow: hidden;
    padding: clamp(1.35rem, 3vw, 1.9rem) clamp(1.25rem, 3vw, 1.8rem);
    background:
        radial-gradient(circle at 92% 12%, rgba(254, 210, 34, .34), transparent 13rem),
        radial-gradient(circle at 8% 0%, rgba(255, 255, 255, .14), transparent 14rem),
        linear-gradient(135deg, #17366c 0%, #102b56 58%, #223d39 100%);
}

.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-card-header::after {
    content: "";
    position: absolute;
    right: -3rem;
    bottom: -4.75rem;
    width: 12rem;
    height: 12rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, .08);
}

.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-card-kicker {
    position: relative;
    z-index: 1;
    align-items: center;
    gap: .45rem;
    margin-bottom: .65rem;
    padding: .38rem .65rem;
    border-radius: 999px;
    background: rgba(254, 210, 34, .14);
    color: var(--fc-secondary);
    font-size: .78rem;
    letter-spacing: .09em;
}

.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-card-kicker::before {
    content: "";
    width: .48rem;
    height: .48rem;
    border-radius: 999px;
    background: var(--fc-secondary);
    box-shadow: 0 0 0 4px rgba(254, 210, 34, .16);
}

.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-card-header h2 {
    position: relative;
    z-index: 1;
    max-width: 850px;
    font-size: clamp(1.65rem, 3.1vw, 2.35rem);
    letter-spacing: -.04em;
}

.flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-table-wrap {
    overflow-x: visible;
    padding: 1rem 1.15rem .65rem;
    background:
        linear-gradient(180deg, rgba(247, 249, 252, .92), rgba(255, 255, 255, .98));
}

.flacso-carta-virtual .fc-pricing-table {
    width: 100%;
    min-width: 0;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0 .7rem;
}

.flacso-carta-virtual .fc-pricing-table thead th {
    padding: .35rem .95rem .55rem;
    border: 0;
    background: transparent;
    color: var(--fc-primary);
    font-size: .78rem;
    font-weight: 950;
    letter-spacing: .055em;
    text-transform: uppercase;
}

.flacso-carta-virtual .fc-pricing-table thead th span {
    margin-top: .18rem;
    color: var(--fc-muted);
    font-size: .72rem;
    font-weight: 750;
    letter-spacing: 0;
    text-transform: none;
}

.flacso-carta-virtual .fc-pricing-table tbody tr {
    position: relative;
}

.flacso-carta-virtual .fc-pricing-table tbody td {
    padding: 1.05rem .95rem;
    border-top: 1px solid rgba(22, 57, 111, .11);
    border-bottom: 1px solid rgba(22, 57, 111, .11);
    background: rgba(255, 255, 255, .96) !important;
    color: var(--fc-muted);
    font-size: 1rem;
    line-height: 1.45;
    vertical-align: middle;
    box-shadow: 0 10px 22px rgba(15, 26, 45, .045);
}

.flacso-carta-virtual .fc-pricing-table tbody td:first-child {
    position: relative;
    width: 50%;
    padding-left: 1.2rem;
    border-left: 1px solid rgba(22, 57, 111, .11);
    border-radius: 1rem 0 0 1rem;
    color: var(--fc-text);
    font-size: 1.02rem;
    font-weight: 900;
}

.flacso-carta-virtual .fc-pricing-table tbody td:last-child {
    border-right: 1px solid rgba(22, 57, 111, .11);
    border-radius: 0 1rem 1rem 0;
}

.flacso-carta-virtual .fc-pricing-table tbody td:nth-child(2),
.flacso-carta-virtual .fc-pricing-table tbody td:nth-child(3) {
    color: var(--fc-primary-dark);
    font-size: 1.08rem;
    font-weight: 850;
}

.flacso-carta-virtual .fc-pricing-table tbody tr:hover td {
    background: #ffffff !important;
    border-color: rgba(22, 57, 111, .20);
    box-shadow: 0 14px 30px rgba(15, 26, 45, .075);
}

.flacso-carta-virtual .fc-pricing-table tr.is-highlighted td,
.flacso-carta-virtual .fc-pricing-table tr.is-highlighted:hover td {
    border-top-color: rgba(210, 146, 0, .34);
    border-bottom-color: rgba(210, 146, 0, .34);
    background:
        linear-gradient(180deg, rgba(255, 248, 184, .98), rgba(255, 239, 132, .88)) !important;
    color: #182033;
    box-shadow:
        0 18px 40px rgba(210, 146, 0, .16),
        0 1px 0 rgba(255, 255, 255, .8) inset;
}

.flacso-carta-virtual .fc-pricing-table tr.is-highlighted td:first-child,
.flacso-carta-virtual .fc-pricing-table tr.is-highlighted:hover td:first-child {
    border-left-color: rgba(210, 146, 0, .34);
    background:
        linear-gradient(90deg, rgba(254, 210, 34, .95) 0 .42rem, rgba(255, 245, 157, .98) .42rem, rgba(255, 239, 132, .88) 100%) !important;
}

.flacso-carta-virtual .fc-pricing-table tr.is-highlighted td:last-child,
.flacso-carta-virtual .fc-pricing-table tr.is-highlighted:hover td:last-child {
    border-right-color: rgba(210, 146, 0, .34);
}

.flacso-carta-virtual .fc-pricing-table tr.is-highlighted td:first-child strong:first-child,
.flacso-carta-virtual .fc-pricing-table tr.is-highlighted td:first-child b:first-child {
    color: #102b56;
}

.flacso-carta-virtual .fc-pricing-note {
    position: relative;
    margin: 0 1.15rem 1.15rem;
    padding: 1rem 1.1rem 1rem 3.15rem;
    border: 1px solid rgba(22, 57, 111, .10);
    border-radius: 1rem;
    background: #ffffff;
    color: var(--fc-muted);
    box-shadow: 0 10px 24px rgba(15, 26, 45, .045);
}

.flacso-carta-virtual .fc-pricing-note::before {
    content: "i";
    position: absolute;
    left: 1rem;
    top: 1rem;
    width: 1.45rem;
    height: 1.45rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(22, 57, 111, .10);
    color: var(--fc-primary);
    font-weight: 950;
    font-style: italic;
}

.flacso-carta-virtual .fc-pricing-note a {
    color: var(--fc-primary);
    font-weight: 900;
    text-decoration-thickness: 2px;
    text-underline-offset: 3px;
}

@media (min-width: 701px) {
    .flacso-carta-virtual .fc-pricing-table th:first-child,
    .flacso-carta-virtual .fc-pricing-table td:first-child,
    .flacso-carta-virtual .fc-pricing-table.is-without-usd th:first-child,
    .flacso-carta-virtual .fc-pricing-table.is-without-usd td:first-child {
        width: 56%;
    }

    .flacso-carta-virtual .fc-pricing-table th:nth-child(2),
    .flacso-carta-virtual .fc-pricing-table td:nth-child(2),
    .flacso-carta-virtual .fc-pricing-table.is-without-usd th:nth-child(2),
    .flacso-carta-virtual .fc-pricing-table.is-without-usd td:nth-child(2) {
        width: 44%;
    }

    .flacso-carta-virtual .fc-pricing-table th:nth-child(3),
    .flacso-carta-virtual .fc-pricing-table td:nth-child(3) {
        width: 22%;
    }

    .flacso-carta-virtual .fc-pricing-table:not(.is-without-usd) th:first-child,
    .flacso-carta-virtual .fc-pricing-table:not(.is-without-usd) td:first-child {
        width: 46%;
    }

    .flacso-carta-virtual .fc-pricing-table:not(.is-without-usd) th:nth-child(2),
    .flacso-carta-virtual .fc-pricing-table:not(.is-without-usd) td:nth-child(2),
    .flacso-carta-virtual .fc-pricing-table:not(.is-without-usd) th:nth-child(3),
    .flacso-carta-virtual .fc-pricing-table:not(.is-without-usd) td:nth-child(3) {
        width: 27%;
    }
}

@media (max-width: 700px) {
    .flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] {
        border-radius: 1.2rem;
    }

    .flacso-carta-virtual .fc-card[aria-labelledby="fc-inversion-title"] .fc-table-wrap {
        padding: .45rem;
        overflow: visible;
        background: rgba(247, 249, 252, .92);
    }

    .flacso-carta-virtual .fc-pricing-table {
        display: block;
        border-spacing: 0;
        table-layout: auto;
    }

    .flacso-carta-virtual .fc-pricing-table tbody {
        display: grid;
        gap: .85rem;
        padding: .55rem;
    }

    .flacso-carta-virtual .fc-pricing-table tbody tr {
        display: block;
        overflow: hidden;
        border: 1px solid rgba(22, 57, 111, .12);
        border-radius: 1rem;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 26, 45, .07);
    }

    .flacso-carta-virtual .fc-pricing-table tbody td,
    .flacso-carta-virtual .fc-pricing-table tbody td:first-child,
    .flacso-carta-virtual .fc-pricing-table tbody td:last-child {
        display: grid;
        grid-template-columns: minmax(7rem, 42%) minmax(0, 1fr);
        gap: .75rem;
        width: 100%;
        padding: .9rem 1rem;
        border: 0;
        border-bottom: 1px solid rgba(22, 57, 111, .10);
        border-radius: 0;
        box-shadow: none;
    }

    .flacso-carta-virtual .fc-pricing-table tbody td:last-child {
        border-bottom: 0;
    }

    .flacso-carta-virtual .fc-pricing-table tbody td::before {
        content: attr(data-label);
        color: var(--fc-primary);
        font-size: .72rem;
        font-weight: 950;
        line-height: 1.25;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .flacso-carta-virtual .fc-pricing-table tbody td:first-child {
        background: rgba(237, 242, 246, .74) !important;
        color: var(--fc-primary);
        font-size: 1rem;
    }

    .flacso-carta-virtual .fc-pricing-table tr.is-highlighted td:first-child,
    .flacso-carta-virtual .fc-pricing-table tr.is-highlighted:hover td:first-child {
        background:
            linear-gradient(90deg, rgba(254, 210, 34, .95) 0 .32rem, rgba(255, 245, 157, .98) .32rem, rgba(255, 239, 132, .88) 100%) !important;
    }

    .flacso-carta-virtual .fc-pricing-note {
        margin: .35rem 1rem 1rem;
        padding: .9rem 1rem .9rem 2.85rem;
        font-size: .94rem;
    }
}


/* =========================================================
   Ajuste final del HERO
   Objetivo: equilibrar portada + CTA sin afectar el botón flotante
========================================================= */

.flacso-carta-virtual .fc-hero {
    max-width: 1200px;
}

.flacso-carta-virtual .fc-hero-inner {
    grid-template-columns: minmax(0, 1.08fr) minmax(300px, .92fr);
    gap: clamp(1.5rem, 4vw, 3.4rem);
    align-items: center;
    min-height: min(76vh, 680px);
    padding: clamp(2rem, 4.5vw, 3.6rem);
    border-radius: 1.35rem;
    background:
        radial-gradient(circle at 10% 8%, rgba(254, 210, 34, .18), transparent 20rem),
        radial-gradient(circle at 78% 8%, rgba(255, 255, 255, .13), transparent 18rem),
        radial-gradient(circle at 95% 100%, rgba(255, 255, 255, .10), transparent 18rem),
        linear-gradient(135deg, #183b70 0%, #122d59 52%, #0f254b 100%);
}

.flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
    padding-top: clamp(3.4rem, 5vw, 4.4rem);
}

.flacso-carta-virtual .fc-hero-copy {
    max-width: 720px;
}

.flacso-carta-virtual .fc-hero h1 {
    max-width: 720px;
    font-size: clamp(2.45rem, 4.7vw, 4.15rem);
    line-height: 1.01;
    letter-spacing: -.052em;
}

.flacso-carta-virtual .fc-eyebrow {
    margin-bottom: 1.25rem;
    padding: .52rem .82rem;
    background: rgba(255, 255, 255, .11);
}

.flacso-carta-virtual .fc-hero-meta {
    margin-top: 1.45rem;
}

.flacso-carta-virtual .fc-hero-meta span {
    min-height: 2.35rem;
    padding: .58rem .86rem;
    border-radius: .72rem;
}

.flacso-carta-virtual .fc-hero-badge--accent {
    min-height: 2.55rem;
    padding: .68rem .95rem;
    box-shadow: 0 10px 22px rgba(0, 0, 0, .12);
}

.flacso-carta-virtual .fc-hero-actions {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-top: 1.45rem;
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button,
.flacso-carta-virtual .fc-hero-actions .fc-secondary-button {
    position: relative;
    min-height: 3.55rem;
    border-radius: .95rem;
    padding: .82rem 1.05rem;
    font-size: .98rem;
    font-weight: 900;
    letter-spacing: -.01em;
    box-shadow: none;
    transform: none;
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button {
    min-width: 255px;
    justify-content: flex-start;
    border: 1px solid rgba(255, 255, 255, .50);
    background: linear-gradient(135deg, #fed222 0%, #ffe66f 100%);
    color: var(--fc-primary-dark);
    box-shadow: 0 14px 28px rgba(0, 0, 0, .18);
}

.flacso-carta-virtual .fc-hero-actions .fc-secondary-button {
    min-width: 175px;
    border: 1px solid rgba(255, 255, 255, .24);
    background: rgba(255, 255, 255, .10);
    color: var(--fc-white);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button i,
.flacso-carta-virtual .fc-hero-actions .fc-secondary-button i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: .72rem;
    transform: none;
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button i {
    background: rgba(16, 43, 86, .12);
    color: var(--fc-primary-dark);
}

.flacso-carta-virtual .fc-hero-actions .fc-secondary-button i {
    background: rgba(255, 255, 255, .10);
    color: var(--fc-white);
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button:hover {
    color: var(--fc-primary-dark);
    border-color: rgba(255, 255, 255, .72);
    background: linear-gradient(135deg, #ffe15a 0%, #fff1a2 100%);
    transform: translateY(-2px);
    box-shadow: 0 18px 32px rgba(0, 0, 0, .22);
}

.flacso-carta-virtual .fc-hero-actions .fc-secondary-button:hover {
    color: var(--fc-white);
    border-color: rgba(255, 255, 255, .38);
    background: rgba(255, 255, 255, .16);
    transform: translateY(-2px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .18), 0 14px 28px rgba(0, 0, 0, .14);
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button:hover i,
.flacso-carta-virtual .fc-hero-actions .fc-secondary-button:hover i {
    transform: none;
}

.flacso-carta-virtual .fc-hero-visual {
    align-items: center;
    justify-content: center;
}

.flacso-carta-virtual .fc-cover-card {
    width: min(100%, 350px);
    transform: rotate(.8deg);
    box-shadow: 0 20px 46px rgba(0, 0, 0, .24);
}

.flacso-carta-virtual .fc-cover-card::before {
    inset: -.38rem;
    border-radius: 1.45rem;
}

.flacso-carta-virtual .fc-hero-ribbon {
    right: clamp(1.25rem, 3vw, 2rem);
    min-width: min(100%, 205px);
    padding: .82rem 1rem 1.08rem;
    font-size: .86rem;
}

@media (max-width: 980px) {
    .flacso-carta-virtual .fc-hero-inner {
        grid-template-columns: 1fr;
        min-height: auto;
    }

    .flacso-carta-virtual .fc-hero-copy {
        max-width: none;
    }

    .flacso-carta-virtual .fc-hero h1 {
        max-width: 820px;
    }

    .flacso-carta-virtual .fc-hero-visual {
        align-items: flex-start;
    }

    .flacso-carta-virtual .fc-cover-card {
        width: min(100%, 320px);
    }
}

@media (max-width: 640px) {
    .flacso-carta-virtual .fc-hero {
        padding: 0 .75rem;
    }

    .flacso-carta-virtual .fc-hero-inner {
        padding: 1.4rem;
        border-radius: 1.15rem;
    }

    .flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
        padding-top: 3.8rem;
    }

    .flacso-carta-virtual .fc-hero h1 {
        font-size: clamp(2rem, 12vw, 3rem);
        letter-spacing: -.045em;
    }

    .flacso-carta-virtual .fc-hero-actions {
        display: grid;
        grid-template-columns: 1fr;
        gap: .65rem;
    }

    .flacso-carta-virtual .fc-hero-actions .fc-primary-button,
    .flacso-carta-virtual .fc-hero-actions .fc-secondary-button {
        width: 100%;
        min-width: 0;
        justify-content: center;
        min-height: 3.35rem;
    }

    .flacso-carta-virtual .fc-cover-card {
        width: min(100%, 290px);
    }
}




/* =========================================================
   Ajuste final: etiqueta superior del hero (ej. Primera edición)
   La versión anterior funcionaba como una cinta pesada. Esta versión
   la convierte en un distintivo compacto, más integrado al hero.
========================================================= */
.flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
    padding-top: clamp(2rem, 4vw, 3.05rem) !important;
}

.flacso-carta-virtual .fc-hero-ribbon {
    position: absolute !important;
    top: clamp(1rem, 2.2vw, 1.35rem) !important;
    right: clamp(1rem, 2.2vw, 1.35rem) !important;
    left: auto !important;
    z-index: 6;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .48rem;

    min-width: 0 !important;
    max-width: calc(100% - 2rem);
    padding: .48rem .82rem .48rem .52rem !important;

    border: 1px solid rgba(255, 255, 255, .48);
    border-radius: 999px;
    background: rgba(255, 255, 255, .92) !important;
    color: var(--fc-primary-dark) !important;
    clip-path: none !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);

    box-shadow:
        0 14px 32px rgba(0, 0, 0, .18),
        inset 0 1px 0 rgba(255, 255, 255, .75) !important;

    font-size: .83rem !important;
    font-weight: 900;
    line-height: 1;
    letter-spacing: .005em;
}

.flacso-carta-virtual .fc-hero-ribbon::after {
    display: none !important;
}

.flacso-carta-virtual .fc-hero-ribbon i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;

    width: 1.72rem;
    height: 1.72rem;
    border-radius: 999px;

    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    font-size: .82rem !important;
    box-shadow: 0 5px 12px rgba(22, 57, 111, .16);
}

.flacso-carta-virtual .fc-hero-ribbon span {
    display: block;
    color: var(--fc-primary-dark);
    font-size: .83rem;
    font-weight: 900;
    white-space: nowrap;
}

@media (max-width: 980px) {
    .flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
        padding-top: 4rem !important;
    }
}

@media (max-width: 640px) {
    .flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
        padding-top: 4.35rem !important;
    }

    .flacso-carta-virtual .fc-hero-ribbon {
        top: 1rem !important;
        right: 1rem !important;
        left: 1rem !important;
        width: auto;
        max-width: none;
        padding: .55rem .75rem !important;
    }

    .flacso-carta-virtual .fc-hero-ribbon span {
        white-space: normal;
        text-align: center;
    }
}



/* =========================================================
   Ajuste final: badge de instancias + botones del hero
========================================================= */
.flacso-carta-virtual .fc-hero-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.flacso-carta-virtual .fc-hero-highlights--top {
    margin: .95rem 0 1.1rem;
}

.flacso-carta-virtual .fc-hero-highlights--top .fc-hero-badge--accent {
    min-height: 2.45rem;
    padding: .62rem .95rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #fed222 0%, #ffe66f 100%);
    border: 1px solid rgba(255, 245, 191, .46);
    color: var(--fc-primary-dark);
    box-shadow: 0 10px 22px rgba(0, 0, 0, .14);
}

.flacso-carta-virtual .fc-hero-highlights--top .fc-hero-badge--accent i {
    color: var(--fc-primary-dark);
}

.flacso-carta-virtual .fc-hero-actions {
    display: grid !important;
    grid-template-columns: max-content max-content;
    align-items: stretch;
    justify-content: flex-start;
    gap: .75rem;
    flex-wrap: nowrap !important;
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button,
.flacso-carta-virtual .fc-hero-actions .fc-secondary-button {
    width: auto;
    min-width: 0;
    white-space: nowrap;
}

.flacso-carta-virtual .fc-hero-actions .fc-primary-button {
    min-width: 236px;
}

.flacso-carta-virtual .fc-hero-actions .fc-secondary-button {
    min-width: 170px;
}

@media (max-width: 980px) {
    .flacso-carta-virtual .fc-hero-actions {
        grid-template-columns: max-content max-content;
    }
}

@media (max-width: 640px) {
    .flacso-carta-virtual .fc-hero-highlights--top {
        margin: .85rem 0 1rem;
    }

    .flacso-carta-virtual .fc-hero-actions {
        grid-template-columns: 1fr !important;
    }

    .flacso-carta-virtual .fc-hero-actions .fc-primary-button,
    .flacso-carta-virtual .fc-hero-actions .fc-secondary-button {
        width: 100%;
        min-width: 0;
        white-space: normal;
    }
}



/* =========================================================
   Ajuste final: restaurar etiqueta tipo cinta/colgante
   Mantiene el efecto de cinta para textos como "Primera edición".
========================================================= */
.flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
    padding-top: clamp(3.35rem, 5vw, 4.35rem) !important;
}

.flacso-carta-virtual .fc-hero-ribbon {
    position: absolute !important;
    top: 0 !important;
    right: clamp(1.15rem, 3vw, 2rem) !important;
    left: auto !important;
    z-index: 8;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;

    min-width: 180px !important;
    max-width: min(280px, calc(100% - 2rem));
    width: auto !important;
    padding: .9rem 1rem 1.22rem !important;

    border: 0 !important;
    border-radius: 0 !important;
    background: linear-gradient(135deg, #ffe76a 0%, #fed222 48%, #f4bd00 100%) !important;
    color: var(--fc-primary-dark) !important;
    clip-path: polygon(0 0, 100% 0, 100% 100%, 50% calc(100% - 15px), 0 100%) !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;

    box-shadow:
        0 16px 34px rgba(0, 0, 0, .20),
        inset 0 1px 0 rgba(255, 255, 255, .40) !important;

    font-size: .85rem !important;
    font-weight: 950;
    line-height: 1.12;
    letter-spacing: .01em;
    text-align: center;
}

.flacso-carta-virtual .fc-hero-ribbon::after {
    content: "" !important;
    display: block !important;
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-top: 13px solid rgba(16, 43, 86, .24);
    border-left: 13px solid transparent;
}

.flacso-carta-virtual .fc-hero-ribbon i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;

    width: auto !important;
    height: auto !important;
    border-radius: 0 !important;

    background: transparent !important;
    color: var(--fc-primary-dark) !important;
    font-size: .92rem !important;
    box-shadow: none !important;
}

.flacso-carta-virtual .fc-hero-ribbon span {
    display: block;
    color: var(--fc-primary-dark) !important;
    font-size: .85rem !important;
    font-weight: 950;
    white-space: normal !important;
}

@media (max-width: 640px) {
    .flacso-carta-virtual .fc-hero-inner.has-hero-ribbon {
        padding-top: 4.8rem !important;
    }

    .flacso-carta-virtual .fc-hero-ribbon {
        right: .9rem !important;
        left: auto !important;
        min-width: 165px !important;
        max-width: calc(100% - 1.8rem);
        padding: .82rem .9rem 1.15rem !important;
    }
}

</style>


<script>
(function () {
    const floatingButton = document.querySelector('.fc-floating-preinscripcion');

    if (!floatingButton || !('IntersectionObserver' in window)) {
        return;
    }

    const preinscriptionButtons = Array.from(document.querySelectorAll('.fc-primary-button, .fc-final-cta a, a[href*="/preinscripcion"]'))
        .filter((button) => button !== floatingButton);

    if (!preinscriptionButtons.length) {
        floatingButton.classList.remove('is-hidden');
        floatingButton.removeAttribute('aria-hidden');
        floatingButton.removeAttribute('tabindex');
        return;
    }

    const visibleButtons = new Set();

    const setFloatingVisibility = () => {
        const shouldHide = visibleButtons.size > 0;

        floatingButton.classList.toggle('is-hidden', shouldHide);

        if (shouldHide) {
            floatingButton.setAttribute('aria-hidden', 'true');
            floatingButton.setAttribute('tabindex', '-1');
        } else {
            floatingButton.removeAttribute('aria-hidden');
            floatingButton.removeAttribute('tabindex');
        }
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.35) {
                visibleButtons.add(entry.target);
            } else {
                visibleButtons.delete(entry.target);
            }
        });

        setFloatingVisibility();
    }, {
        threshold: [0, 0.35, 0.65],
        rootMargin: '0px 0px -8% 0px'
    });

    preinscriptionButtons.forEach((button) => observer.observe(button));
    setFloatingVisibility();
}());
</script>

<script>
(function() {
    if (typeof window.fbq !== 'function') return;
    try {
        window.fbq('track', 'ViewContent', {
            content_name: <?php echo wp_json_encode((string) $titulo); ?>,
            content_category: 'oferta_academica',
            content_ids: ['oferta-' + <?php echo wp_json_encode((string) $post_id); ?>],
            flacso_stage: 'carta_informacion'
        });
    } catch (e) {}
})();
</script>
<?php
get_footer();
