import re
import sys

file_path = "modules/oferta-academica/includes/class-oferta-renderer.php"

with open(file_path, "r") as f:
    content = f.read()

# Replace block 1 (render_program_card date parsing)
block1_regex = r"\s*\$proximo_raw = get_post_meta\(\$post_id, 'proximo_inicio', true\);\n\s*\$proximo_ts  = \$proximo_raw \? strtotime\(\$proximo_raw\) \: 0;\n\s*\$proximo_fmt = \$proximo_ts \? date_i18n\('j \\\\d\\\\e F Y', \$proximo_ts\) \: '';"

block1_new = """        $proximo_raw = get_post_meta($post_id, 'proximo_inicio', true);
        if (is_array($proximo_raw)) {
            $proximo_raw = reset($proximo_raw);
        }
        $proximo_raw = (string) $proximo_raw;

        $proximo_fmt = '';
        $is_exact_date = false;
        $proximo_ts = 0;

        if (!empty($proximo_raw)) {
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $proximo_raw)) {
                $proximo_ts = strtotime($proximo_raw);
                $proximo_fmt = date_i18n('j \\\\d\\\\e F Y', $proximo_ts);
                $is_exact_date = true;
            } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $proximo_raw)) {
                $proximo_fmt = 'próximo inicio en ' . date_i18n('F Y', strtotime($proximo_raw . '-01'));
            } elseif (preg_match('/^[0-9]{4}$/', $proximo_raw)) {
                $proximo_fmt = 'próximo inicio en ' . $proximo_raw;
            } else {
                $proximo_fmt = 'próximo inicio: ' . $proximo_raw;
            }
        }"""

# Replace block 2 (render_program_card html formatting)
block2_regex = r"\s*<\?php if \(\$proximo_fmt\) : \?>\n\s*<div class=\"flacso-oa-card__meta mb-2\">\n\s*<i class=\"bi bi-calendar3 text-primary\" aria-hidden=\"true\"></i>\n\s*<time datetime=\"<\?php echo esc_attr\(date\('Y-m-d', \$proximo_ts\)\); \?>\"><\?php echo esc_html\(\$proximo_fmt\); \?></time>\n\s*</div>\n\s*<div class=\"flacso-oferta-countdown\" data-countdown=\"<\?php echo esc_attr\(date\('Y-m-d', \$proximo_ts\)\); \?>\" aria-live=\"polite\">\n\s*<i class=\"bi bi-clock\" aria-hidden=\"true\"></i>\n\s*<span class=\"flacso-oferta-countdown__text\"><\?php esc_html_e\('Cargando', 'flacso-oferta-academica'\); \?></span>\n\s*</div>\n\s*<\?php endif; \?>"

block2_new = """                    <?php if ($proximo_fmt) : ?>
                        <div class="flacso-oa-card__meta mb-2">
                            <i class="bi bi-calendar3 text-primary" aria-hidden="true"></i>
                            <?php if ($is_exact_date) : ?>
                                <time datetime="<?php echo esc_attr(date('Y-m-d', $proximo_ts)); ?>"><?php echo esc_html($proximo_fmt); ?></time>
                            <?php else : ?>
                                <span><?php echo esc_html(ucfirst($proximo_fmt)); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_exact_date) : ?>
                        <div class="flacso-oferta-countdown" data-countdown="<?php echo esc_attr(date('Y-m-d', $proximo_ts)); ?>" aria-live="polite">
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <span class="flacso-oferta-countdown__text"><?php esc_html_e('Cargando', 'flacso-oferta-academica'); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>"""

# Replace block 3 (render_seminario_card_bootstrap date parsing)
block3_regex = r"\s*\$fecha_raw   = get_post_meta\(\$post_id, '_seminario_periodo_inicio', true\) \?: get_post_meta\(\$post_id, 'periodo_inicio', true\);\n\s*\$modalidad   = get_post_meta\(\$post_id, 'modalidad', true\);\n\s*\$creditos    = get_post_meta\(\$post_id, 'creditos', true\);\n\n\s*\$ts          = \$fecha_raw \? strtotime\(\$fecha_raw\) : 0;\n\s*\$fecha_fmt   = \$ts \? date_i18n\('l j \\\\d\\\\e F Y', \$ts\) : '';\n\s*\$fecha_iso   = \$ts \? date\('Y-m-d', \$ts\) : '';\n\s*\$faltan_dias = \$ts \? floor\(\(\$ts - current_time\('timestamp'\)\) / DAY_IN_SECONDS\) : null;\n\s*\$faltan_txt  = is_int\(\$faltan_dias\) && \$faltan_dias >= 0\n\s*\? sprintf\(__\('Faltan %d días', 'flacso-oferta-academica'\), \$faltan_dias\)\n\s*: '';"

block3_new = """        $fecha_raw   = get_post_meta($post_id, '_seminario_periodo_inicio', true) ?: get_post_meta($post_id, 'periodo_inicio', true);
        if (is_array($fecha_raw)) {
            $fecha_raw = reset($fecha_raw);
        }
        $fecha_raw = (string) $fecha_raw;
        
        $modalidad   = get_post_meta($post_id, 'modalidad', true);
        $creditos    = get_post_meta($post_id, 'creditos', true);

        $fecha_fmt = '';
        $is_exact_date = false;
        $ts = 0;
        $fecha_iso = '';
        $faltan_dias = null;
        $faltan_txt = '';

        if (!empty($fecha_raw)) {
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $fecha_raw)) {
                $ts = strtotime($fecha_raw);
                $fecha_fmt = date_i18n('l j \\\\d\\\\e F Y', $ts);
                $fecha_iso = date('Y-m-d', $ts);
                $faltan_dias = floor(($ts - current_time('timestamp')) / DAY_IN_SECONDS);
                $faltan_txt = is_int($faltan_dias) && $faltan_dias >= 0
                    ? sprintf(__('Faltan %d días', 'flacso-oferta-academica'), $faltan_dias)
                    : '';
                $is_exact_date = true;
            } elseif (preg_match('/^[0-9]{4}-[0-9]{2}$/', $fecha_raw)) {
                $fecha_fmt = 'próximo inicio en ' . date_i18n('F Y', strtotime($fecha_raw . '-01'));
            } elseif (preg_match('/^[0-9]{4}$/', $fecha_raw)) {
                $fecha_fmt = 'próximo inicio en ' . $fecha_raw;
            } else {
                $fecha_fmt = 'próximo inicio: ' . $fecha_raw;
            }
        }"""

# Replace block 4 (render_seminario_card_bootstrap html formatting)
block4_regex = r"\s*<\?php if \(\$fecha_fmt\) : \?>\n\s*<div class=\"flacso-oa-card__meta mb-2\">\n\s*<i class=\"bi bi-calendar3 text-primary\" aria-hidden=\"true\"></i>\n\s*<time datetime=\"<\?php echo esc_attr\(\$fecha_iso\); \?>\"><\?php echo esc_html\(\$fecha_fmt\); \?></time>\n\s*</div>\n\s*<\?php endif; \?>"

block4_new = """                    <?php if ($fecha_fmt) : ?>
                        <div class="flacso-oa-card__meta mb-2">
                            <i class="bi bi-calendar3 text-primary" aria-hidden="true"></i>
                            <?php if ($is_exact_date) : ?>
                                <time datetime="<?php echo esc_attr($fecha_iso); ?>"><?php echo esc_html($fecha_fmt); ?></time>
                            <?php else : ?>
                                <span><?php echo esc_html(ucfirst($fecha_fmt)); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>"""

for idx, (regex, new) in enumerate([(block1_regex, block1_new), (block2_regex, block2_new), (block3_regex, block3_new), (block4_regex, block4_new)]):
    count = len(re.findall(regex, content))
    if count == 0:
        print(f"Could not find block {idx+1}")
    elif count > 1:
        print(f"Found multiple matches for block {idx+1}")
    else:
        content = re.sub(regex, "\\n" + new, content, count=1)
        print(f"Successfully replaced block {idx+1}")

with open(file_path, "w") as f:
    f.write(content)
print("Done.")
