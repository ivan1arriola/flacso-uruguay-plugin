<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode: [calendario_maestriagenero c1="1" c2="1" c3="1" abierto="1" clase=""]
 * - Ejes con color:
 *   PPI #cfe2f3, VBG #dad2e9, CCD #d8ead2, Salud #fbe5cd, Común var(--global-palette7), Metodológico var(--global-palette6).
 * - Borde izquierdo + fondo y chips teñidos por eje (C1, C2 y C3).
 * - "Entrega del Trabajo Final": título fijo y solo Semana + Fecha (neutro).
 * - Links de Ciclo 2 en PDF inline (proxy temporal en /uploads/flacso-temp-pdf).
 */
if (!function_exists('flacso_shortcode_calendario_maestriagenero')) {
    add_shortcode('calendario_maestriagenero', 'flacso_shortcode_calendario_maestriagenero');

    function flacso_shortcode_calendario_maestriagenero($atts = []) {
        $a = shortcode_atts([
            'c1'      => '1',
            'c2'      => '1',
            'c3'      => '1',
            'abierto' => '0',
            'clase'   => '',
        ], $atts, 'calendario_maestriagenero');

        $is_true = fn($v) => in_array(strtolower(trim((string)$v)), ['1','true','yes','si','sí','on'], true);
        $open = $is_true($a['abierto']) ? ' show' : '';

        // === Normalizadores de eje ===
        $lower = function($s){ return function_exists('mb_strtolower') ? mb_strtolower($s,'UTF-8') : strtolower($s); };
        $eje_class = function($eje) use ($lower){
            $t = $lower((string)$eje);
            if (strpos($t,'entrega del trabajo final') !== false) return 'eje-entrega';
            if (strpos($t,'políticas públicas integrales') !== false || strpos($t,'poli') !== false) return 'eje-ppi';
            if (strpos($t,'violencia basada en género') !== false || strpos($t,'violencia') !== false) return 'eje-vbg';
            if (strpos($t,'cambio climático') !== false || strpos($t,'desastres') !== false || strpos($t,'clim') !== false) return 'eje-ccd';
            if (strpos($t,'salud integral') !== false || preg_match('~\bsalud\b~u',$t)) return 'eje-salud';
            if (strpos($t,'temático común') !== false || strpos($t,'común') !== false) return 'eje-comun';
            if (strpos($t,'metodológico') !== false) return 'eje-met';
            return 'eje-otro';
        };
        $eje_nombre = function($cls, $fallback=''){
            return [
                'eje-entrega' => 'Entrega del Trabajo Final',
                'eje-ppi'     => 'Temático Específico — Políticas Públicas Integrales',
                'eje-vbg'     => 'Temático Específico — Violencia basada en Género',
                'eje-ccd'     => 'Temático Específico — Cambio Climático y Desastres',
                'eje-salud'   => 'Salud Integral',
                'eje-comun'   => 'Temático Común',
                'eje-met'     => 'Metodológico',
                'eje-otro'    => $fallback ?: 'Eje',
            ][$cls] ?? ($fallback ?: 'Eje');
        };

        // === CICLO 1 ===
        $c1_items = [
            ['eje'=>'Temático Común','nota'=>'Lo cursa todo el grupo','semana'=>'0','nombre'=>'Bienvenida y presentación','periodo'=>'08 al 14 de abril','sesiones'=>[['fecha'=>'sábado  11 de abril','hora'=>'10:00 a 12:00 horas (Uruguay)']]],
            ['eje'=>'Temático Común','nota'=>'Lo cursa todo el grupo','semana'=>'1, 2, 3, 4','nombre'=>'Género, interseccionalidad, igualdad y no discriminación','periodo'=>'15 de abril al 12 de mayo','sesiones'=>[['fecha'=>'sábado 25 de abril','hora'=>'10:00 a 12:00 horas (Uruguay)']]],

            ['eje'=>'*Temático Específico — Políticas Públicas Integrales','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'5,6,7,8,9','nombre'=>'Políticas Públicas con enfoque de Género e Interseccionalidad','periodo'=>'13 de mayo al 16 de junio','sesiones'=>[['fecha'=>'sábados  16 de mayo y  13 de junio','hora'=>'10:00 a 12:00 horas (Uruguay)']]],

            ['eje'=>'*Temático Específico — Violencia basada en Género','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'5,6,7','nombre'=>'Aproximación a la Violencia Basada en Género','periodo'=>'13 de mayo al 02 de junio','sesiones'=>[['fecha'=>'sábado  23 de mayo','hora'=>'10:00 a 12:00 horas (Uruguay)']]],
            ['eje'=>'*Temático Específico — Violencia basada en Género','nota'=>'(continuación)','semana'=>'8,9','nombre'=>'Violencia en las relaciones de pareja. Principios rectores para la intervención','periodo'=>'03 de junio al 16 de junio','sesiones'=>[['fecha'=>'sábado 13 de junio','hora'=>'10:00 a 12:00 horas (Uruguay)']]],

            ['eje'=>'*Temático Específico — Cambio Climático y Desastres','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'5 y 6','nombre'=>'Género y Desarrollo','periodo'=>'13 al 26 de mayo','sesiones'=>[['fecha'=>'sábado 23 de mayo','hora'=>'10:00 a 12:00 horas (Uruguay)']]],
            ['eje'=>'*Temático Específico — Cambio Climático y Desastres','nota'=>'(continuación)','semana'=>'7,8,9','nombre'=>'De las palabras a la acción: implementación género responsiva en la agenda climática','periodo'=>'27 de mayo al 16 de junio','sesiones'=>[['fecha'=>'sábado 06 de junio','hora'=>'10:00 a 12:00 horas (Uruguay)']]],

            ['eje'=>'*Salud Integral','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'5,6','nombre'=>'Introducción a género, interseccionalidad y salud','periodo'=>'13 al 26 de mayo','sesiones'=>[['fecha'=>'sábado 23 de mayo','hora'=>'10:00 a 12:00 horas (Uruguay)']]],
            ['eje'=>'*Salud Integral','nota'=>'(continuación)','semana'=>'7,8,9','nombre'=>'Género, interseccionalidad y determinación social de la salud','periodo'=>'27 de mayo al 16 de junio','sesiones'=>[
                ['fecha'=>'sábado 06 de junio','hora'=>'10:00 a 12:00 horas (Uruguay)'],
                ['fecha'=>'jueves 11 de junio','hora'=>'18:00 a 20:00 horas (Uruguay)'],
            ]],

            ['eje'=>'Metodológico','nota'=>'Lo cursa todo el grupo','semana'=>'9,10','nombre'=>'Seminario de metodología','periodo'=>'17 al 30 de junio','sesiones'=>[['fecha'=>'sábado 27 de junio','hora'=>'10:00 a 12:00 horas (Uruguay)']]],
            ['eje'=>'Metodológico','nota'=>'Lo cursa todo el grupo','semana'=>'11,12','nombre'=>'Talleres de Metodología','periodo'=>'1 al 14 de julio','sesiones'=>[['fecha'=>'sábado 4 y sábado 11 de julio','hora'=>'10:00 a 12:00 horas (Uruguay)']]],

            ['eje'=>'Entrega del Trabajo Final','nota'=>'','semana'=>'13','nombre'=>'21 de julio','periodo'=>'','sesiones'=>[]],
        ];

        // Agrupar por eje
        $groups = [];
        foreach ($c1_items as $it) {
            $cls = $eje_class($it['eje']);
            $key = $cls;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'cls'   => $cls,
                    'title' => $eje_nombre($cls, $it['eje']),
                    'note'  => $it['nota'] === '(continuación)' ? '' : ($it['nota'] ?? ''),
                    'items' => [],
                ];
            }
            $groups[$key]['items'][] = $it;
        }

        // === CICLO 2: Docs a PDF proxy (con color por orientación) ===
        $c2_docs = [
            ['titulo'=>'Diplomado de Especialización en Género – Políticas Públicas Integrales','cohorte'=>'Cohorte IV','id'=>'1lDjCXUbXyYDD54csNS5Zg2e00olbIceX'],
            ['titulo'=>'Diplomado de Especialización en Género – Violencia basada en Género','cohorte'=>'Cohorte IV','id'=>'1gCf9xZsHI4VX9oiEeUOCpQRhZEqVnbP1'],
            ['titulo'=>'Diplomado de Especialización en Género – Salud Integral','cohorte'=>'Cohorte III','id'=>'1IngR8XqRwm9MX2JpBhNvtqMWfMxfUOwz'],
            ['titulo'=>'Especialización en Género, Cambio Climático y Desastres','cohorte'=>'Cohorte V','id'=>'1s39lb_-yB4lUPPEP7dlqSvsJNuPW7nrm'],
        ];
        $make_pdf_proxy = function($doc){
            if (!is_array($doc)) {
                return '';
            }

            $source = '';

            if (!empty($doc['url'])) {
                $source = esc_url_raw((string) $doc['url']);
            } elseif (!empty($doc['id'])) {
                $doc_id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $doc['id']);
                if ($doc_id !== '') {
                    $source = sprintf('https://drive.google.com/file/d/%s/view', $doc_id);
                }
            }

            if ($source === '') {
                return '';
            }

            if (function_exists('flacso_get_pdf_proxy_url')) {
                $proxy = flacso_get_pdf_proxy_url($source, 'documento');
                if (!empty($proxy)) {
                    return esc_url($proxy);
                }
            }

            if (!empty($doc['id'])) {
                $doc_id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $doc['id']);
                if ($doc_id !== '') {
                    // Fallback legacy para mantener compatibilidad.
                    return esc_url(add_query_arg(['flacso_pdf_proxy' => 1, 'doc_id' => $doc_id], site_url('/')));
                }
            }

            return esc_url($source);
        };

        // === CICLO 3 (resumen con color por eje) ===
        $c3_items = [
            ['eje'=>'Común','nota'=>'Lo cursa todo el grupo','semana'=>'—','nombre'=>'Clase de bienvenida','periodo'=>'Abril 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Metodológico','nota'=>'Lo cursa todo el grupo','semana'=>'—','nombre'=>'Seminario Metodológico III','periodo'=>'Abril y Mayo 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'*Temático Específico — Políticas Públicas Integrales','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'—','nombre'=>'Construcción e interpretación de indicadores con PEG e interseccionalidad','periodo'=>'Junio 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'*Temático Específico — Violencia basada en Género','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'—','nombre'=>'Políticas Públicas sobre violencia basada en género I','periodo'=>'Junio 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'*Temático Específico — Cambio Climático y Desastres','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'—','nombre'=>'Género en el marco de los acuerdos multilaterales de cambio climático y gestión del riesgo de desastres','periodo'=>'Junio 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'*Salud Integral','nota'=>'Lo cursa quien ha elegido este eje','semana'=>'—','nombre'=>'Ciencia y medicina como tecnología de género','periodo'=>'Junio 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Metodológico','nota'=>'Lo cursa todo el grupo','semana'=>'—','nombre'=>'Seminario Metodológico IV','periodo'=>'Julio 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Metodológico','nota'=>'','semana'=>'—','nombre'=>'Taller de Tesis I','periodo'=>'Agosto a Noviembre 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Libre elección','nota'=>'Lo cursa todo el grupo','semana'=>'—','nombre'=>'Un seminario a elección','periodo'=>'Septiembre 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Metodológico','nota'=>'','semana'=>'—','nombre'=>'Un seminario a elección','periodo'=>'Noviembre 2027','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Metodológico','nota'=>'Lo cursa todo el grupo','semana'=>'—','nombre'=>'Taller de Tesis II','periodo'=>'Febrero a abril 2028','sesiones'=>[['fecha'=>'a confirmar','hora'=>'a confirmar']]],
            ['eje'=>'Entrega del borrador final de tesis','nota'=>'','semana'=>'ABRIL 2028','nombre'=>'Entrega del borrador final de tesis','periodo'=>'ABRIL 2028','sesiones'=>[]],
        ];

        $c1_title = 'Diploma en Género – Cohorte VII';
        $c2_title = 'Ciclo de Especialización en Género';
        $c3_title = 'Maestría en Género';

        $calendar_config = get_option('flacso_cal_maestriagenero_data', []);
        if (is_array($calendar_config)) {
            if (!empty($calendar_config['c1_title'])) {
                $c1_title = (string) $calendar_config['c1_title'];
            }
            if (!empty($calendar_config['c2_title'])) {
                $c2_title = (string) $calendar_config['c2_title'];
            }
            if (!empty($calendar_config['c3_title'])) {
                $c3_title = (string) $calendar_config['c3_title'];
            }

            if (!empty($calendar_config['c1_items']) && is_array($calendar_config['c1_items'])) {
                $c1_items = $calendar_config['c1_items'];
            }
            if (!empty($calendar_config['c2_docs']) && is_array($calendar_config['c2_docs'])) {
                $c2_docs = $calendar_config['c2_docs'];
            }
            if (!empty($calendar_config['c3_items']) && is_array($calendar_config['c3_items'])) {
                $c3_items = $calendar_config['c3_items'];
            }
        }

        ob_start(); ?>
        <section class="cal-maestria-genero <?php echo esc_attr($a['clase']); ?>">
            <div class="container px-0">

                <div class="accordion" id="calAccordion">
                    <?php if ($is_true($a['c1'])): ?>
                    <!-- ============== CICLO 1 ============== -->
                    <div class="accordion-item cal-acc">
                        <h2 class="accordion-header" id="c1head">
                            <button class="accordion-button cal-acc-btn<?php echo $open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#c1" aria-expanded="<?php echo $open ? 'true':'false'; ?>" aria-controls="c1">
                                <span class="chip chip-amber me-2">Ciclo 1</span>
                                <strong><?php echo esc_html($c1_title); ?></strong>
                            </button>
                        </h2>
                        <div id="c1" class="accordion-collapse collapse<?php echo $open; ?>" aria-labelledby="c1head" data-bs-parent="#calAccordion">
                            <div class="accordion-body">

                                <!-- Leyenda de colores -->
                                <div class="cal-legend d-flex flex-wrap gap-2 mb-3">
                                    <span class="legend-box legend-ppi">Políticas Públicas Integrales</span>
                                    <span class="legend-box legend-vbg">Violencia basada en Género</span>
                                    <span class="legend-box legend-ccd">Cambio Climático y Desastres</span>
                                    <span class="legend-box legend-salud">Salud Integral</span>
                                    <span class="legend-box legend-comun">Temático Común</span>
                                    <span class="legend-box legend-met">Metodológico</span>
                                </div>

                                <?php foreach ($groups as $g): $cls = $g['cls']; $isEntrega = ($cls === 'eje-entrega'); ?>
                                    <div class="eje-group <?php echo esc_attr($cls); ?>">
                                        <div class="eje-header">
                                            <div class="eje-title"><?php echo esc_html($g['title']); ?></div>
                                            <?php if(!empty($g['note'])): ?>
                                                <div class="eje-note chip chip-amber-emph"><?php echo esc_html($g['note']); ?></div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($isEntrega): ?>
                                            <div class="eje-items">
                                                <div class="cal-item">
                                                    <div class="cal-line">
                                                        <div class="cal-label">Semana</div>
                                                        <div class="cal-value"><span class="chip chip-week">13</span></div>
                                                    </div>
                                                    <div class="cal-line">
                                                        <div class="cal-label">Fecha</div>
                                                        <div class="cal-value"><?php echo esc_html($g['items'][0]['nombre']); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="eje-items">
                                                <?php foreach ($g['items'] as $it): ?>
                                                    <div class="cal-item">
                                                        <div class="cal-line">
                                                            <div class="cal-label">Semana</div>
                                                            <div class="cal-value"><span class="chip chip-week"><?php echo esc_html($it['semana']); ?></span></div>
                                                        </div>
                                                        <div class="cal-line">
                                                            <div class="cal-label">Nombre del seminario</div>
                                                            <div class="cal-value cal-title"><?php echo esc_html($it['nombre']); ?></div>
                                                        </div>
                                                        <div class="cal-line">
                                                            <div class="cal-label">Período de cursada en campus virtual**</div>
                                                            <div class="cal-value"><?php echo $it['periodo'] !== '' ? esc_html($it['periodo']) : '—'; ?></div>
                                                        </div>
                                                        <div class="cal-line">
                                                            <div class="cal-label">Clase sincrónica vía ZOOM***</div>
                                                            <?php if (!empty($it['sesiones'])): ?>
                                                                <div class="cal-sessions">
                                                                    <?php foreach ($it['sesiones'] as $s): ?>
                                                                        <div class="cal-session">
                                                                            <div class="cal-session-pair">
                                                                                <div class="cal-session-label">Fecha</div>
                                                                                <div class="cal-session-value"><?php echo esc_html($s['fecha']); ?></div>
                                                                            </div>
                                                                            <div class="cal-session-pair">
                                                                                <div class="cal-session-label">Horario</div>
                                                                                <div class="cal-session-value"><?php echo esc_html($s['hora']); ?></div>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="cal-value">—</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_true($a['c2'])): ?>
                    <!-- ============== CICLO 2 (con color por orientación) ============== -->
                    <div class="accordion-item cal-acc">
                        <h2 class="accordion-header" id="c2head">
                            <button class="accordion-button cal-acc-btn<?php echo $open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#c2" aria-expanded="<?php echo $open ? 'true':'false'; ?>" aria-controls="c2">
                                <span class="chip chip-amber me-2">Ciclo 2</span>
                                <strong><?php echo esc_html($c2_title); ?></strong>
                            </button>
                        </h2>
                        <div id="c2" class="accordion-collapse collapse<?php echo $open; ?>" aria-labelledby="c2head" data-bs-parent="#calAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <?php foreach ($c2_docs as $lk):
                                        $titulo = isset($lk['titulo']) ? (string) $lk['titulo'] : '';
                                        $cohorte = isset($lk['cohorte']) ? (string) $lk['cohorte'] : '';
                                        $pdf = $make_pdf_proxy($lk);
                                        $cls = $eje_class($titulo); ?>
                                        <div class="col-12">
                                            <div class="card cal-link-card eje-card <?php echo esc_attr($cls); ?>">
                                                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                    <div>
                                                        <div class="fw-semibold cal-title"><?php echo esc_html($titulo); ?></div>
                                                        <div class="small text-muted"><?php echo esc_html($cohorte); ?></div>
                                                    </div>
                                                    <?php if (!empty($pdf)) : ?>
                                                        <a class="btn btn-cal" href="<?php echo esc_url($pdf); ?>" target="_blank" rel="noopener">
                                                            Abrir documento
                                                        </a>
                                                    <?php else : ?>
                                                        <span class="small text-muted">Sin enlace</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_true($a['c3'])): ?>
                    <!-- ============== CICLO 3 (con color por eje) ============== -->
                    <div class="accordion-item cal-acc">
                        <h2 class="accordion-header" id="c3head">
                            <button class="accordion-button cal-acc-btn<?php echo $open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#c3" aria-expanded="<?php echo $open ? 'true':'false'; ?>" aria-controls="c3">
                                <span class="chip chip-amber me-2">Ciclo 3</span>
                                <strong><?php echo esc_html($c3_title); ?></strong>
                            </button>
                        </h2>
                        <div id="c3" class="accordion-collapse collapse<?php echo $open; ?>" aria-labelledby="c3head" data-bs-parent="#calAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <?php foreach ($c3_items as $it):
                                        $cls = $eje_class($it['eje']); ?>
                                    <div class="col-12">
                                        <div class="card cal-card eje-card <?php echo esc_attr($cls); ?>">
                                            <div class="card-body">
                                                <div class="cal-line">
                                                    <div class="cal-label">Eje</div>
                                                    <div class="cal-value d-flex align-items-center flex-wrap gap-2">
                                                        <span class="chip chip-week"><?php echo esc_html($it['eje']); ?></span>
                                                        <?php if (!empty($it['nota'])): ?>
                                                            <span class="chip chip-amber-emph"><?php echo esc_html($it['nota']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="cal-line">
                                                    <div class="cal-label">Nombre del seminario o taller</div>
                                                    <div class="cal-value cal-title"><?php echo esc_html($it['nombre']); ?></div>
                                                </div>
                                                <div class="cal-line">
                                                    <div class="cal-label">Período de cursada en campus virtual**</div>
                                                    <div class="cal-value"><?php echo $it['periodo'] !== '' ? esc_html($it['periodo']) : '—'; ?></div>
                                                </div>
                                                <div class="cal-line">
                                                    <div class="cal-label">Clase sincrónica</div>
                                                    <?php if (!empty($it['sesiones'])): ?>
                                                        <div class="cal-sessions">
                                                            <?php foreach ($it['sesiones'] as $s): ?>
                                                                <div class="cal-session">
                                                                    <div class="cal-session-pair">
                                                                        <div class="cal-session-label">Fecha</div>
                                                                        <div class="cal-session-value"><?php echo esc_html($s['fecha']); ?></div>
                                                                    </div>
                                                                    <div class="cal-session-pair">
                                                                        <div class="cal-session-label">Horario</div>
                                                                        <div class="cal-session-value"><?php echo esc_html($s['hora']); ?></div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="cal-value">a confirmar</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </section>

        <style>
            /* ===== Kadence palette ===== */
            .cal-maestria-genero{
                --c1: var(--global-palette1, #1d3a72);
                --c2: var(--global-palette2, #fed222);
                --c3: var(--global-palette3, #0f1a2d);
                --c4: var(--global-palette4, #2e2f34);
                --c5: var(--global-palette5, #7a8696);
                --c6: var(--global-palette6, #c9d2de);
                --c7: var(--global-palette7, #e9edf2);
                --c8: var(--global-palette8, #f8f9fa);
                --c9: var(--global-palette9, #ffffff);

                /* Ejes temáticos (hex + rgb) */
                --eje-ppi:   #cfe2f3; --eje-ppi-rgb: 207,226,243;
                --eje-vbg:   #dad2e9; --eje-vbg-rgb: 218,210,233;
                --eje-ccd:   #d8ead2; --eje-ccd-rgb: 216,234,210;
                --eje-salud: #fbe5cd; --eje-salud-rgb: 251,229,205;

                /* Común y Metodológico (marca) */
                --eje-comun: var(--c7);  --eje-comun-rgb: 233,237,242;
                --eje-met:   var(--c6);  --eje-met-rgb:   201,210,222;

                --btn-bg: var(--global-palette-btn-bg, #248138);
                --btn-bg-hover: var(--global-palette-btn-bg-hover, #1b6d2b);
                --btn-fg: var(--global-palette-btn, #fff);
                font-family: var(--global-body-font-family, Roboto, sans-serif);
            }

            /* Accordion */
            .cal-acc{ border:1px solid rgba(15,26,45,.12); border-radius:14px; overflow:hidden; margin-bottom:.8rem; background:var(--c9); }
            .cal-acc .cal-acc-btn{
                background: linear-gradient(180deg, rgba(29,58,114,.06), rgba(29,58,114,.03));
                color: var(--c3);
            }
            .cal-acc .cal-acc-btn:not(.collapsed){
                background: linear-gradient(180deg, rgba(29,58,114,.10), rgba(29,58,114,.05));
                box-shadow: inset 0 -1px 0 rgba(15,26,45,.08);
            }
            .accordion-button:focus { box-shadow: 0 0 0 0.2rem rgba(253,210,34,.35); }

            /* Leyenda */
            .cal-legend .legend-box{
                display:inline-block; padding:.35rem .6rem; border-radius:10px; font-weight:600; font-size:.85rem;
                border:1px solid rgba(15,26,45,.08);
            }
            .legend-ppi   { background: var(--eje-ppi); }
            .legend-vbg   { background: var(--eje-vbg); }
            .legend-ccd   { background: var(--eje-ccd); }
            .legend-salud { background: var(--eje-salud); }
            .legend-comun { background: var(--eje-comun); }
            .legend-met   { background: var(--eje-met); }

            /* Chips */
            .chip{ display:inline-block; padding:.28rem .6rem; border-radius:999px; font-weight:600; font-size:.84rem; line-height:1; border:1px solid transparent; }
            .chip-amber{ background: var(--c2); color:#000; }
            .chip-amber-emph{ background: var(--c2); color:#000; border-color: rgba(0,0,0,.12); }
            .chip-outline{ background: transparent; color: var(--c1); border:1px solid var(--c1); }
            .chip-week{ background: rgba(29,58,114,.06); border:1px solid rgba(29,58,114,.25); color: var(--c3); }

            /* Eje group (C1) */
            .eje-group{
                border-left: 10px solid var(--c6);
                background: var(--c9);
                border: 1px solid var(--c6);
                border-radius: 14px;
                padding: 1rem 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 10px rgba(15,26,45,.05);
            }
            .eje-group .eje-header{
                display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:.6rem;
                padding:.5rem .75rem; border-radius:10px;
                background: rgba(15,26,45,.03);
            }
            .eje-title{ font-weight:800; color: var(--c3); }
            .eje-note{ font-size:.78rem; }

            /* Tintes por eje (bordes, headers, items, sesiones) */
            .eje-group.eje-ppi   { border-left-color: var(--eje-ppi);   border-color: rgba(var(--eje-ppi-rgb), .65); }
            .eje-group.eje-vbg   { border-left-color: var(--eje-vbg);   border-color: rgba(var(--eje-vbg-rgb), .65); }
            .eje-group.eje-ccd   { border-left-color: var(--eje-ccd);   border-color: rgba(var(--eje-ccd-rgb), .65); }
            .eje-group.eje-salud { border-left-color: var(--eje-salud); border-color: rgba(var(--eje-salud-rgb), .65); }
            .eje-group.eje-comun { border-left-color: var(--eje-comun); border-color: rgba(var(--eje-comun-rgb), .65); }
            .eje-group.eje-met   { border-left-color: var(--eje-met);   border-color: rgba(var(--eje-met-rgb), .65); }

            .eje-group.eje-ppi   .eje-header{ background: rgba(var(--eje-ppi-rgb), .45); }
            .eje-group.eje-vbg   .eje-header{ background: rgba(var(--eje-vbg-rgb), .45); }
            .eje-group.eje-ccd   .eje-header{ background: rgba(var(--eje-ccd-rgb), .45); }
            .eje-group.eje-salud .eje-header{ background: rgba(var(--eje-salud-rgb), .45); }
            .eje-group.eje-comun .eje-header{ background: rgba(var(--eje-comun-rgb), .45); }
            .eje-group.eje-met   .eje-header{ background: rgba(var(--eje-met-rgb), .45); }

            .eje-items{ display:grid; gap:.9rem; }
            .cal-item{
                background: var(--c8);
                border: 1px solid var(--c7);
                border-radius: 12px;
                padding: .8rem .9rem;
            }

            .eje-group.eje-ppi   .cal-item{ background: rgba(var(--eje-ppi-rgb), .22); border-color: rgba(var(--eje-ppi-rgb), .55); }
            .eje-group.eje-vbg   .cal-item{ background: rgba(var(--eje-vbg-rgb), .22); border-color: rgba(var(--eje-vbg-rgb), .55); }
            .eje-group.eje-ccd   .cal-item{ background: rgba(var(--eje-ccd-rgb), .22); border-color: rgba(var(--eje-ccd-rgb), .55); }
            .eje-group.eje-salud .cal-item{ background: rgba(var(--eje-salud-rgb), .22); border-color: rgba(var(--eje-salud-rgb), .55); }
            .eje-group.eje-comun .cal-item{ background: rgba(var(--eje-comun-rgb), .22); border-color: rgba(var(--eje-comun-rgb), .55); }
            .eje-group.eje-met   .cal-item{ background: rgba(var(--eje-met-rgb), .22);   border-color: rgba(var(--eje-met-rgb), .55); }

            .eje-group.eje-ppi   .cal-session{ background: rgba(var(--eje-ppi-rgb), .35); border-color: rgba(var(--eje-ppi-rgb), .65); }
            .eje-group.eje-vbg   .cal-session{ background: rgba(var(--eje-vbg-rgb), .35); border-color: rgba(var(--eje-vbg-rgb), .65); }
            .eje-group.eje-ccd   .cal-session{ background: rgba(var(--eje-ccd-rgb), .35); border-color: rgba(var(--eje-ccd-rgb), .65); }
            .eje-group.eje-salud .cal-session{ background: rgba(var(--eje-salud-rgb), .35); border-color: rgba(var(--eje-salud-rgb), .65); }
            .eje-group.eje-comun .cal-session{ background: rgba(var(--eje-comun-rgb), .35); border-color: rgba(var(--eje-comun-rgb), .65); }
            .eje-group.eje-met   .cal-session{ background: rgba(var(--eje-met-rgb), .35);   border-color: rgba(var(--eje-met-rgb), .65); }

            .cal-line{ display:grid; gap:.25rem; margin-bottom:.4rem; }
            .cal-item .cal-line:first-child .cal-label{ color: var(--c3); font-weight:700; }
            .cal-label{ font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color: var(--c5); }
            .cal-value{ color: var(--c4); font-weight:600; }
            .cal-title{ color: var(--c3); font-weight:800; }

            .cal-sessions{ display:grid; gap:.6rem; margin-top:.25rem; }
            .cal-session{ border: 1px dashed var(--c6); border-radius:10px; padding:.7rem .8rem; }
            .cal-session-pair{ display:grid; grid-template-columns: 110px 1fr; gap:.4rem; }
            .cal-session-label{ font-size:.82rem; color: var(--c5); }
            .cal-session-value{ color: var(--c3); font-weight:700; }
            @media (min-width: 768px){ .cal-session-pair{ grid-template-columns: 120px 1fr; } }

            /* ===== C2 & C3: tarjetas coloreadas por eje ===== */
            .eje-card{
                border-left: 10px solid var(--c6);
                border: 1px solid var(--c6);
                border-radius: 14px;
                box-shadow: 0 2px 10px rgba(15,26,45,.05);
                overflow: hidden;
            }
            .eje-card .card-body{ background: var(--c9); }

            .eje-card.eje-ppi   { border-left-color: var(--eje-ppi);   border-color: rgba(var(--eje-ppi-rgb), .65); }
            .eje-card.eje-vbg   { border-left-color: var(--eje-vbg);   border-color: rgba(var(--eje-vbg-rgb), .65); }
            .eje-card.eje-ccd   { border-left-color: var(--eje-ccd);   border-color: rgba(var(--eje-ccd-rgb), .65); }
            .eje-card.eje-salud { border-left-color: var(--eje-salud); border-color: rgba(var(--eje-salud-rgb), .65); }
            .eje-card.eje-comun { border-left-color: var(--eje-comun); border-color: rgba(var(--eje-comun-rgb), .65); }
            .eje-card.eje-met   { border-left-color: var(--eje-met);   border-color: rgba(var(--eje-met-rgb), .65); }

            /* Suaves fondos internos por eje para C2/C3 */
            .eje-card.eje-ppi   .card-body{ background: rgba(var(--eje-ppi-rgb), .22); }
            .eje-card.eje-vbg   .card-body{ background: rgba(var(--eje-vbg-rgb), .22); }
            .eje-card.eje-ccd   .card-body{ background: rgba(var(--eje-ccd-rgb), .22); }
            .eje-card.eje-salud .card-body{ background: rgba(var(--eje-salud-rgb), .22); }
            .eje-card.eje-comun .card-body{ background: rgba(var(--eje-comun-rgb), .22); }
            .eje-card.eje-met   .card-body{ background: rgba(var(--eje-met-rgb), .22); }

            /* Botón PDF */
            .cal-link-card{ background: var(--c9); }
            .btn.btn-cal{ background: var(--btn-bg); color: var(--btn-fg); border:none; border-radius:10px; padding:.55rem 1rem; box-shadow:0 2px 8px rgba(36,129,56,.25); }
            .btn.btn-cal:hover{ background: var(--btn-bg-hover); color: var(--btn-fg); box-shadow:0 4px 14px rgba(36,129,56,.35); }

            /* Entrega del Trabajo Final: neutro */
            .eje-group.eje-entrega .eje-header{ background: var(--c8); }
            .eje-group.eje-entrega .cal-item{ background: var(--c9); border-color: var(--c7); }
        </style>

        <?php
        return ob_get_clean();
    }
}
