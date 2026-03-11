<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_nonce_field('flacso_seminario_save', 'flacso_seminario_nonce');
?>
<table class="form-table">
    <?php foreach ($fields as $key => $label) :
        $meta_key = '_seminario_' . $key;
        $value = get_post_meta($post->ID, $meta_key, true);
        ?>
        <tr>
            <th><label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <?php if ($key === 'acredita_maestria' || $key === 'acredita_doctorado') : ?>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr($meta_key); ?>" value="1" <?php checked($value, true); ?>>
                        <?php esc_html_e('Si'); ?>
                    </label>
                <?php elseif ($key === 'forma_aprobacion' || $key === 'objetivo_general' || $key === 'presentacion_seminario') : ?>
                    <textarea class="large-text" rows="4" name="<?php echo esc_attr($meta_key); ?>" id="<?php echo esc_attr($meta_key); ?>"><?php echo esc_textarea($value); ?></textarea>
                <?php elseif ($key === 'periodo_inicio' || $key === 'periodo_fin') : ?>
                    <input type="date" class="regular-text" name="<?php echo esc_attr($meta_key); ?>" id="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php elseif ($key === 'creditos') : ?>
                    <input type="number" step="0.1" min="0" class="regular-text" name="<?php echo esc_attr($meta_key); ?>" id="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php elseif ($key === 'carga_horaria') : ?>
                    <input type="number" step="1" min="0" class="regular-text" name="<?php echo esc_attr($meta_key); ?>" id="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php else : ?>
                    <input type="text" class="regular-text" name="<?php echo esc_attr($meta_key); ?>" id="<?php echo esc_attr($meta_key); ?>" value="<?php echo esc_attr($value); ?>">
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<h3>Encuentros sincronicos</h3>
<table class="widefat striped" id="seminario-encuentros">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Hora inicio</th>
            <th>Hora fin</th>
            <th>Plataforma</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $encuentros_rows = !empty($encuentros) ? $encuentros : array(array('fecha' => '', 'hora_inicio' => '', 'hora_fin' => '', 'plataforma' => 'zoom', 'plataforma_otro' => ''));
        foreach ($encuentros_rows as $index => $row) :
            $fecha = isset($row['fecha']) ? $row['fecha'] : '';
            $hora_inicio = isset($row['hora_inicio']) ? $row['hora_inicio'] : '';
            $hora_fin = isset($row['hora_fin']) ? $row['hora_fin'] : '';
            $plataforma = isset($row['plataforma']) ? $row['plataforma'] : 'zoom';
            $plataforma_otro = isset($row['plataforma_otro']) ? $row['plataforma_otro'] : '';
            ?>
            <tr>
                <td><input type="date" name="_seminario_encuentros_sincronicos[<?php echo esc_attr($index); ?>][fecha]" value="<?php echo esc_attr($fecha); ?>" class="regular-text"></td>
                <td><input type="time" name="_seminario_encuentros_sincronicos[<?php echo esc_attr($index); ?>][hora_inicio]" value="<?php echo esc_attr($hora_inicio); ?>" class="regular-text"></td>
                <td><input type="time" name="_seminario_encuentros_sincronicos[<?php echo esc_attr($index); ?>][hora_fin]" value="<?php echo esc_attr($hora_fin); ?>" class="regular-text"></td>
                <td>
                    <select name="_seminario_encuentros_sincronicos[<?php echo esc_attr($index); ?>][plataforma]" class="plataforma-select" style="width: 120px;">
                        <option value="zoom" <?php selected($plataforma, 'zoom'); ?>>Zoom</option>
                        <option value="otro" <?php selected($plataforma, 'otro'); ?>>Otro</option>
                    </select>
                    <input type="text" name="_seminario_encuentros_sincronicos[<?php echo esc_attr($index); ?>][plataforma_otro]" placeholder="Especificar..." value="<?php echo esc_attr($plataforma_otro); ?>" class="regular-text" style="width: 120px; margin-top: 4px; display: <?php echo $plataforma === 'otro' ? 'block' : 'none'; ?>;">
                </td>
                <td><button type="button" class="button link-button seminario-remove-row">Eliminar</button></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p><button type="button" class="button" id="seminario-add-row">Agregar encuentro</button></p>

<h3>Objetivos especificos</h3>
<table class="widefat striped" id="seminario-objetivos">
    <thead>
        <tr>
            <th style="width:24px;"></th>
            <th>Objetivo</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $objetivos_rows = !empty($objetivos) ? $objetivos : array('');
        foreach ($objetivos_rows as $index => $item) :
            ?>
            <tr>
                <td class="seminario-drag-handle">&#x2630;</td>
                <td><input type="text" name="_seminario_objetivos_especificos[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($item); ?>" class="large-text"></td>
                <td><button type="button" class="button link-button seminario-remove-objetivo">Eliminar</button></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p><button type="button" class="button" id="seminario-add-objetivo">Agregar objetivo</button></p>

<h3>Unidades academicas</h3>
<table class="widefat striped" id="seminario-unidades">
    <thead>
        <tr>
            <th style="width:24px;"></th>
            <th>Titulo de la unidad</th>
            <th>Contenido</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $unidades_rows = !empty($unidades) ? $unidades : array(array('titulo' => '', 'contenido' => ''));
        foreach ($unidades_rows as $index => $row) :
            $titulo = isset($row['titulo']) ? $row['titulo'] : '';
            $contenido = isset($row['contenido']) ? $row['contenido'] : '';
            ?>
            <tr>
                <td class="seminario-drag-handle">&#x2630;</td>
                <td><input type="text" name="_seminario_unidades_academicas[<?php echo esc_attr($index); ?>][titulo]" value="<?php echo esc_attr($titulo); ?>" class="regular-text"></td>
                <td><textarea name="_seminario_unidades_academicas[<?php echo esc_attr($index); ?>][contenido]" class="large-text" rows="3"><?php echo esc_textarea($contenido); ?></textarea></td>
                <td><button type="button" class="button link-button seminario-remove-unidad">Eliminar</button></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p><button type="button" class="button" id="seminario-add-unidad">Agregar unidad</button></p>

<h3>Docentes relacionados</h3>
<p><label for="seminario-docente-search">Buscar docente</label></p>
<input type="text" id="seminario-docente-search" class="regular-text" placeholder="Escribe para buscar">
<div id="seminario-docente-results" style="margin:8px 0;"></div>
<ul id="seminario-docentes-selected" style="margin:0;padding-left:20px;">
    <?php foreach ($docentes_posts as $docente) : ?>
        <li data-id="<?php echo esc_attr($docente->ID); ?>">
            <?php echo esc_html(get_the_title($docente)); ?>
            <button type="button" class="button-link seminario-remove-docente">Quitar</button>
            <input type="hidden" name="_seminario_docentes[]" value="<?php echo esc_attr($docente->ID); ?>">
        </li>
    <?php endforeach; ?>
</ul>
