<?php
/**
 * Template Name: FLACSO - Carta de Presentación
 * Description: Template dinámico para la página virtual de carta de presentación
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $post;

$post_id = $post->ID;
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

if (!function_exists('flacso_carta_determinante')) {
    function flacso_carta_determinante(string $titulo): string {
        $primera = strtolower(trim(strtok($titulo, ' ')));
        return in_array($primera, ['maestría', 'maestria', 'especialización', 'especializacion', 'licenciatura'], true) ? 'de la' : 'del';
    }
}

$child_content = !empty($data['carta_presentacion_html']) ? $data['carta_presentacion_html'] : '';

if (empty($child_content)) {
    $child_page = get_page_by_path($post->post_name . '/carta', OBJECT, 'page');

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

    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $proximo_inicio_str)) {
        $proximo_inicio = wp_date('j \d\e F \d\e Y', strtotime($proximo_inicio_str));
    } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $proximo_inicio_str)) {
        $proximo_inicio = wp_date('F Y', strtotime($proximo_inicio_str . '-01'));
    } else {
        $proximo_inicio = $proximo_inicio_str;
    }
}

$duracion = !empty($data['duracion_meses']) ? $data['duracion_meses'] . ' meses' : '12 meses';
$modalidad = !empty($data['modalidad_resumen']) ? $data['modalidad_resumen'] : 'Virtual';

$menciones = is_array($data['menciones'] ?? null) ? implode('|', $data['menciones']) : ($data['menciones'] ?? '');
$orientaciones = is_array($data['orientaciones'] ?? null) ? implode('|', $data['orientaciones']) : ($data['orientaciones'] ?? '');

$arr_menciones = flacso_carta_array_from_value($menciones);
$arr_orientaciones = flacso_carta_array_from_value($orientaciones);

$reconocido_mec = isset($data['reconocido_mec'])
    ? flacso_carta_bool($data['reconocido_mec'])
    : $is_maestria;

$visibilidad_carta = true;
if (isset($data['visibilidad_carta']) && $data['visibilidad_carta'] !== '') {
    $visibilidad_carta = flacso_carta_bool($data['visibilidad_carta']);
} elseif (isset($data['ocultar_carta']) && $data['ocultar_carta'] !== '') {
    $visibilidad_carta = !flacso_carta_bool($data['ocultar_carta']);
}
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
    if ($is_maestria && stripos($post->post_name, 'educacion-innovacion-tecnologias') !== false) {
        $asistente_slug = 'analia-bombau';
        $asistente_nombre = 'Analía Bombau';
        $asistente_correo = 'edutic@flacso.edu.uy';
    } elseif ($is_diplomado && stripos($post->post_name, 'genero') !== false) {
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

$precios_filas = [];

if (!empty($data['precios_filas'])) {
    if (is_string($data['precios_filas'])) {
        $decoded = json_decode($data['precios_filas'], true);
        $precios_filas = is_array($decoded) ? $decoded : [];
    } elseif (is_array($data['precios_filas'])) {
        $precios_filas = $data['precios_filas'];
    }
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
            <div class="fc-hero-inner">

                <div class="fc-hero-copy">
                    <div class="fc-eyebrow">
                        <i class="bi bi-stars" aria-hidden="true"></i>
                        <span>Formación de excelencia, estés donde estés</span>
                    </div>

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
                        <?php if ($visibilidad_carta) : ?>
                            <a class="fc-primary-button" href="<?php echo esc_url($url_inscripcion); ?>" aria-label="Preinscribirme a <?php echo esc_attr($titulo); ?>">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            Preinscribirme
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

            <?php if (!$visibilidad_carta) : ?>
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
            if (class_exists('Oferta_Blocks') && strpos($child_content, 'flacso-uruguay/dato-malla-curricular') === false) {
                $documentos_json = get_post_meta($post_id, 'documentos', true);
                $documentos = is_string($documentos_json) && !empty($documentos_json) ? json_decode($documentos_json, true) : [];
                $cartamalla = isset($documentos['cartamalla']) && !empty($documentos['cartamalla']['link']);

                // Comprobar si existe el documento unificado (prioridad 1)
                if ($cartamalla) {
                    $calendario_html = Oferta_Blocks::render_dato_unificado_calendario_malla(['ofertaId' => $post_id]);
                    $malla_html = '';
                } else {
                    $calendario_html = Oferta_Blocks::render_dato_calendario(['ofertaId' => $post_id]);
                    $malla_html = Oferta_Blocks::render_dato_malla_curricular(['ofertaId' => $post_id]);
                }

                if (trim($calendario_html) !== '' || trim($malla_html) !== '') {
                    ?>
                    <section class="fc-native-blocks">
                        <?php
                        if (trim($calendario_html) !== '') {
                            echo $calendario_html;
                        }

                        if (trim($malla_html) !== '') {
                            echo $malla_html;
                        }
                        ?>
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
                            <table class="fc-pricing-table">
                                <caption class="fc-sr-only">Beneficios, descuentos y valores de la oferta académica</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Concepto</th>
                                        <th scope="col">Valor en $ <span>residentes en Uruguay</span></th>
                                        <th scope="col">Valor en U$S <span>residentes en el exterior</span></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($precios_filas as $row) : ?>
                                        <tr class="<?php echo !empty($row['highlight']) ? 'is-highlighted' : ''; ?>">
                                            <td data-label="Concepto"><?php echo wp_kses_post($row['concept'] ?? ''); ?></td>
                                            <td data-label="Valor en pesos para residentes en Uruguay"><?php echo wp_kses_post($row['uy'] ?? ''); ?></td>
                                            <td data-label="Valor en dólares para residentes en el exterior"><?php echo wp_kses_post($row['us'] ?? ''); ?></td>
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
                    <div class="fc-info-row">
                        <div class="fc-info-icon"><i class="bi bi-currency-dollar" aria-hidden="true"></i></div>
                        <div>Para pagos en <strong>dólares</strong>, las cuotas se mantendrán fijas durante toda la cursada.</div>
                    </div>

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
                    $asist_prefijo = !empty($asist_meta['prefijo_abrev'][0]) ? esc_html($asist_meta['prefijo_abrev'][0]) . ' ' : '';
                    $asist_nombre_completo = $asist_prefijo . esc_html(get_the_title($asist_docente->ID));
                    $asist_imagen_url = get_the_post_thumbnail_url($asist_docente->ID, 'medium');
                    $prep_posgrado = flacso_carta_determinante(get_the_title($post_id));
                    $enlace_correo = '<a href="mailto:' . esc_attr($asistente_correo) . '">' . esc_html($asistente_correo) . '</a>';

                    $presentacion_final = sprintf(
                        'Mi nombre es %s y soy %s %s %s. Si tienes dudas o consultas puedes contactarme al correo electrónico %s.',
                        $asist_nombre_completo,
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

            <a class="fc-floating-preinscripcion is-hidden" href="<?php echo esc_url($url_inscripcion); ?>" aria-label="Preinscribirme a <?php echo esc_attr($titulo); ?>" aria-hidden="true" tabindex="-1">
                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                <span>Preinscribirme</span>
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

.fc-hero-copy {
    position: relative;
    z-index: 2;
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
    background: rgba(233, 229, 15, .22);
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

@keyframes fc-pulse-button {
    0% {
        box-shadow: 0 0 0 0 rgba(226, 218, 0, 0.6);
    }
    70% {
        box-shadow: 0 0 0 20px rgba(226, 218, 0, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(226, 218, 0, 0);
    }
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
    padding: 1.25rem 2.25rem;
    border: 2px solid rgba(255, 255, 255, .6);
    border-radius: 999px;
    background: var(--fc-secondary);
    color: var(--fc-primary-dark);
    font-size: 1.15rem;
    font-weight: 900;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    text-decoration: none;
    box-shadow: 0 15px 35px rgba(15, 26, 45, .35);
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    pointer-events: auto;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fc-pulse-button 2.5s infinite;
}

.fc-floating-preinscripcion:hover {
    color: var(--fc-primary-dark);
    transform: translateY(-4px) scale(1.03);
    filter: brightness(.97);
    box-shadow: 0 20px 45px rgba(15, 26, 45, .45);
    animation: none;
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
        border-radius: .95rem;
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
        box-shadow: 0 0 0 3px rgba(233, 229, 15, .22), var(--fc-shadow-soft);
    }

    .fc-pricing-table tr.is-highlighted td:first-child {
        background: rgba(233, 229, 15, .32) !important;
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

<?php
get_footer();