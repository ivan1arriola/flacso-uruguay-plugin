<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_Admin {
    public const META_INPUTS       = 'preinscripcion_formulario';
    public const META_ORIENTATIONS = 'preinscripcion_orientaciones';
    public const NONCE_ACTION      = 'flacso_preinscripcion_save';
    public const NONCE_FIELD       = 'preinscripcion_admin_nonce';

    public static function init(): void {
        if (function_exists('add_action')) {
            add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
            add_action('save_post_cohorte', [self::class, 'save_cohorte'], 10, 2);
            add_action('save_post_edicion', [self::class, 'save_edicion'], 10, 2);
        }
    }

    public static function add_meta_boxes(): void {
        if (!function_exists('add_meta_box')) {
            return;
        }

        add_meta_box(
            'flacso_preinscripcion_meta_cohorte',
            __('Formulario de Preinscripción', 'flacso-uruguay'),
            [self::class, 'render_meta_box'],
            'cohorte',
            'normal',
            'default'
        );

        add_meta_box(
            'flacso_preinscripcion_meta_edicion',
            __('Formulario de Preinscripción', 'flacso-uruguay'),
            [self::class, 'render_meta_box'],
            'edicion',
            'normal',
            'default'
        );
    }

    public static function render_meta_box($post): void {
        $post_id = is_object($post) ? (int) $post->ID : (int) $post;
        $post_type = get_post_type($post);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $saved_inputs = get_post_meta($post_id, self::META_INPUTS, true);
        if (!is_array($saved_inputs)) {
            $saved_inputs = [];
        }
        $saved_orientations = get_post_meta($post_id, self::META_ORIENTATIONS, true);
        if (!is_array($saved_orientations)) {
            $saved_orientations = [];
        }

        $catalog_labels = FLACSO_Preinscriptions_Field_Catalog::labels();
        $indexed_inputs = [];
        foreach ($saved_inputs as $item) {
            if (isset($item['key'])) {
                $indexed_inputs[$item['key']] = $item;
            }
        }

        ?>
        <div class="flacso-preinscriptions-admin-wrapper" style="margin-top: 10px;">
            <p class="description">
                <?php esc_html_e('Seleccione los campos requeridos para este destino. Los datos universales (nombre, apellido, email, teléfono) se solicitan siempre de manera predeterminada.', 'flacso-uruguay'); ?>
            </p>

            <table class="widefat striped" style="margin-top: 12px; margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><?php esc_html_e('Activo', 'flacso-uruguay'); ?></th>
                        <th><?php esc_html_e('Campo', 'flacso-uruguay'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Posición', 'flacso-uruguay'); ?></th>
                        <th style="width: 120px; text-align: center;"><?php esc_html_e('Obligatorio', 'flacso-uruguay'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row_index = 0;
                    foreach ($catalog_labels as $key => $label):
                        $is_configured = isset($indexed_inputs[$key]);
                        $position = $is_configured ? (int) $indexed_inputs[$key]['position'] : ($row_index + 1) * 10;
                        $is_required = $is_configured ? !empty($indexed_inputs[$key]['required']) : true;
                    ?>
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox" name="<?php echo esc_attr(self::META_INPUTS); ?>[<?php echo $row_index; ?>][active]" value="1" <?php checked($is_configured, true); ?> />
                            <input type="hidden" name="<?php echo esc_attr(self::META_INPUTS); ?>[<?php echo $row_index; ?>][key]" value="<?php echo esc_attr($key); ?>" />
                        </td>
                        <td>
                            <strong><?php echo esc_html($label); ?></strong>
                            <code>(<?php echo esc_html($key); ?>)</code>
                        </td>
                        <td>
                            <input type="number" min="1" step="1" name="<?php echo esc_attr(self::META_INPUTS); ?>[<?php echo $row_index; ?>][position]" value="<?php echo esc_attr($position); ?>" class="small-text" />
                        </td>
                        <td style="text-align: center;">
                            <input type="checkbox" name="<?php echo esc_attr(self::META_INPUTS); ?>[<?php echo $row_index; ?>][required]" value="1" <?php checked($is_required, true); ?> />
                        </td>
                    </tr>
                    <?php
                        $row_index++;
                    endforeach;
                    ?>
                </tbody>
            </table>

            <div class="flacso-orientations-section" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                <h4><?php esc_html_e('Orientaciones y Menciones Académicas', 'flacso-uruguay'); ?></h4>
                <p class="description">
                    <?php esc_html_e('Estructura opcional de orientaciones disponibles para esta cohorte/edición.', 'flacso-uruguay'); ?>
                </p>

                <?php if (empty($saved_orientations)): ?>
                    <p><em><?php esc_html_e('No hay orientaciones configuradas.', 'flacso-uruguay'); ?></em></p>
                    <input type="hidden" name="<?php echo esc_attr(self::META_ORIENTATIONS); ?>" value="" />
                <?php else: ?>
                    <?php foreach ($saved_orientations as $o_idx => $orient): ?>
                        <div style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border: 1px solid #e5e5e5; border-radius: 4px;">
                            <input type="hidden" name="<?php echo esc_attr(self::META_ORIENTATIONS); ?>[<?php echo $o_idx; ?>][id]" value="<?php echo esc_attr($orient['id'] ?? ''); ?>" />
                            <input type="hidden" name="<?php echo esc_attr(self::META_ORIENTATIONS); ?>[<?php echo $o_idx; ?>][name]" value="<?php echo esc_attr($orient['name'] ?? ''); ?>" />
                            <strong><?php echo esc_html($orient['name'] ?? ''); ?></strong> <code>(<?php echo esc_html($orient['id'] ?? ''); ?>)</code>

                            <?php if (!empty($orient['mentions'])): ?>
                                <ul style="margin: 8px 0 0 20px; list-style: disc;">
                                    <?php foreach ($orient['mentions'] as $m_idx => $mention): ?>
                                        <li>
                                            <input type="hidden" name="<?php echo esc_attr(self::META_ORIENTATIONS); ?>[<?php echo $o_idx; ?>][mentions][<?php echo $m_idx; ?>][id]" value="<?php echo esc_attr($mention['id'] ?? ''); ?>" />
                                            <input type="hidden" name="<?php echo esc_attr(self::META_ORIENTATIONS); ?>[<?php echo $o_idx; ?>][mentions][<?php echo $m_idx; ?>][name]" value="<?php echo esc_attr($mention['name'] ?? ''); ?>" />
                                            <?php echo esc_html($mention['name'] ?? ''); ?> <code>(<?php echo esc_html($mention['id'] ?? ''); ?>)</code>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function save_cohorte(int $post_id, $post): void {
        self::save($post_id, $post);
    }

    public static function save_edicion(int $post_id, $post): void {
        self::save($post_id, $post);
    }

    public static function save(int $post_id, $post): void {
        if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post_id)) {
            return;
        }
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) {
            return;
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !function_exists('wp_verify_nonce') ||
            !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)
        ) {
            return;
        }

        if (
            function_exists('current_user_can') &&
            !current_user_can('manage_options') &&
            !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        // Process inputs
        $raw_inputs = $_POST[self::META_INPUTS] ?? [];
        if (is_array($raw_inputs)) {
            $filtered_inputs = [];
            foreach ($raw_inputs as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                // In admin UI, check if active is checked or if direct array format without active flag
                $is_active = isset($candidate['active']) ? !empty($candidate['active']) : true;
                if ($is_active && isset($candidate['key'])) {
                    $filtered_inputs[] = [
                        'key'      => $candidate['key'],
                        'position' => $candidate['position'] ?? 10,
                        'required' => !empty($candidate['required']),
                    ];
                }
            }
            $clean_inputs = FLACSO_Preinscriptions_Config::sanitize_inputs($filtered_inputs);
            update_post_meta($post_id, self::META_INPUTS, $clean_inputs);
        }

        // Process orientations
        if (isset($_POST[self::META_ORIENTATIONS])) {
            $raw_orientations = $_POST[self::META_ORIENTATIONS];
            $clean_orientations = FLACSO_Preinscriptions_Config::sanitize_orientations($raw_orientations);
            update_post_meta($post_id, self::META_ORIENTATIONS, $clean_orientations);
        }
    }
}
