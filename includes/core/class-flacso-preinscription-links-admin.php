<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Editor masivo de enlaces de preinscripción para Cohortes y Ediciones. */
final class FLACSO_Preinscription_Links_Admin {
    public const PAGE_SLUG = 'flacso-preinscripcion-links';
    private const CAPABILITY = 'edit_posts';
    private const NONCE_ACTION = 'flacso_save_preinscription_links';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }
        add_action('admin_menu', [self::class, 'register_menu'], 20);
        add_action('admin_post_flacso_save_preinscription_links', [self::class, 'save']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            FLACSO_Admin_Panel::PAGE_SLUG,
            __('Links de preinscripción', 'flacso-uruguay'),
            __('Links de preinscripción', 'flacso-uruguay'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('No tenés permisos para editar links de preinscripción.', 'flacso-uruguay'));
        }

        $rows = self::rows();
        $updated = isset($_GET['updated']) ? absint($_GET['updated']) : 0;
        $invalid = isset($_GET['invalid']) ? absint($_GET['invalid']) : 0;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Links de preinscripción', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Editá varias cohortes y ediciones en una sola operación. Los links deben usar https://preinscripciones.flacso.edu.uy.', 'flacso-uruguay'); ?></p>

            <?php if ($updated || $invalid) : ?>
                <div class="notice <?php echo $invalid ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p>
                    <?php echo esc_html(sprintf(__('Actualizados: %1$d. Rechazados: %2$d.', 'flacso-uruguay'), $updated, $invalid)); ?>
                </p></div>
            <?php endif; ?>

            <p><input type="search" id="flacso-links-filter" class="regular-text" placeholder="<?php esc_attr_e('Filtrar por nombre, tipo o ID…', 'flacso-uruguay'); ?>"></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="flacso_save_preinscription_links">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <table class="widefat striped" id="flacso-links-table">
                    <thead><tr>
                        <th><?php esc_html_e('Tipo', 'flacso-uruguay'); ?></th>
                        <th><?php esc_html_e('Oferta / Seminario', 'flacso-uruguay'); ?></th>
                        <th><?php esc_html_e('Instancia', 'flacso-uruguay'); ?></th>
                        <th><?php esc_html_e('ID', 'flacso-uruguay'); ?></th>
                        <th><?php esc_html_e('Link de preinscripción', 'flacso-uruguay'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr data-search="<?php echo esc_attr(strtolower($row['tipo'] . ' ' . $row['padre'] . ' ' . $row['instancia'] . ' ' . $row['id'])); ?>">
                            <td><strong><?php echo esc_html($row['tipo']); ?></strong></td>
                            <td><?php echo esc_html($row['padre']); ?></td>
                            <td><?php echo esc_html($row['instancia']); ?></td>
                            <td><?php echo esc_html((string) $row['id']); ?></td>
                            <td><input type="url" name="links[<?php echo esc_attr($row['post_type']); ?>][<?php echo esc_attr((string) $row['id']); ?>]" value="<?php echo esc_attr($row['link']); ?>" placeholder="https://preinscripciones.flacso.edu.uy/..." style="width:100%;min-width:360px"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(__('Guardar todos los cambios', 'flacso-uruguay')); ?>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('flacso-links-filter');
            const rows = document.querySelectorAll('#flacso-links-table tbody tr');
            if (!input) return;
            input.addEventListener('input', function () {
                const needle = input.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    row.hidden = needle && !String(row.dataset.search || '').includes(needle);
                });
            });
        });
        </script>
        <?php
    }

    public static function save(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('No tenés permisos para editar links de preinscripción.', 'flacso-uruguay'));
        }
        check_admin_referer(self::NONCE_ACTION);

        $raw = isset($_POST['links']) && is_array($_POST['links']) ? wp_unslash($_POST['links']) : [];
        $updated = 0;
        $invalid = 0;

        foreach (['cohorte', 'edicion'] as $post_type) {
            $items = isset($raw[$post_type]) && is_array($raw[$post_type]) ? $raw[$post_type] : [];
            foreach ($items as $id => $value) {
                $id = absint($id);
                if (!$id || get_post_type($id) !== $post_type || !current_user_can('edit_post', $id)) {
                    $invalid++;
                    continue;
                }

                $value = trim((string) $value);
                if ($value === '') {
                    if ((string) get_post_meta($id, 'link_preinscripcion', true) !== '') {
                        delete_post_meta($id, 'link_preinscripcion');
                        $updated++;
                    }
                    continue;
                }

                $sanitized = $post_type === 'cohorte'
                    ? FLACSO_Cohorte::sanitize_registration_url($value)
                    : FLACSO_Edicion::sanitize_registration_url($value);

                if ($sanitized === '') {
                    $invalid++;
                    continue;
                }

                if ((string) get_post_meta($id, 'link_preinscripcion', true) !== $sanitized) {
                    update_post_meta($id, 'link_preinscripcion', $sanitized);
                    $updated++;
                }
            }
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE_SLUG,
            'updated' => $updated,
            'invalid' => $invalid,
        ], admin_url('admin.php')));
        exit;
    }

    private static function rows(): array {
        $rows = [];
        foreach (get_posts([
            'post_type' => 'cohorte',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]) as $post) {
            $parent_id = absint(get_post_meta($post->ID, FLACSO_Cohorte::META_PARENT_ID, true));
            $rows[] = [
                'post_type' => 'cohorte',
                'tipo' => __('Cohorte', 'flacso-uruguay'),
                'padre' => $parent_id ? get_the_title($parent_id) : __('Sin oferta', 'flacso-uruguay'),
                'instancia' => FLACSO_Cohorte::display_name(absint(get_post_meta($post->ID, 'numero', true))),
                'id' => (int) $post->ID,
                'link' => (string) get_post_meta($post->ID, 'link_preinscripcion', true),
            ];
        }

        foreach (get_posts([
            'post_type' => 'edicion',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]) as $post) {
            $parent_id = absint(get_post_meta($post->ID, FLACSO_Edicion::META_PARENT_ID, true));
            $rows[] = [
                'post_type' => 'edicion',
                'tipo' => __('Edición', 'flacso-uruguay'),
                'padre' => $parent_id ? get_the_title($parent_id) : __('Sin seminario', 'flacso-uruguay'),
                'instancia' => get_the_title($post->ID) ?: ('#' . $post->ID),
                'id' => (int) $post->ID,
                'link' => (string) get_post_meta($post->ID, 'link_preinscripcion', true),
            ];
        }

        return $rows;
    }
}

FLACSO_Preinscription_Links_Admin::init();
