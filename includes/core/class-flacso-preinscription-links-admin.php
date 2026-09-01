<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Generador masivo de enlaces canónicos de preinscripción para Cohortes y Ediciones. */
final class FLACSO_Preinscription_Links_Admin {
    public const PAGE_SLUG = 'flacso-preinscripcion-links';
    private const CAPABILITY = 'edit_posts';
    private const NONCE_ACTION = 'flacso_save_preinscription_links';
    private const BASE_URL = 'https://preinscripciones.flacso.edu.uy';

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
            wp_die(esc_html__('No tenés permisos para generar links de preinscripción.', 'flacso-uruguay'));
        }

        $expected = self::expected_links();
        $rows = self::rows($expected);
        $updated = isset($_GET['updated']) ? absint($_GET['updated']) : 0;
        $invalid = isset($_GET['invalid']) ? absint($_GET['invalid']) : 0;
        $pending = 0;
        foreach ($rows as $row) {
            if ($row['expected'] !== '' && $row['link'] !== $row['expected']) {
                $pending++;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Links de preinscripción', 'flacso-uruguay'); ?></h1>
            <p><?php esc_html_e('Los links se generan automáticamente a partir del slug canónico de cada Oferta Académica o Seminario y se guardan en sus Cohortes y Ediciones.', 'flacso-uruguay'); ?></p>

            <?php if (isset($_GET['updated']) || isset($_GET['invalid'])) : ?>
                <div class="notice <?php echo $invalid ? 'notice-warning' : 'notice-success'; ?> is-dismissible"><p>
                    <?php echo esc_html(sprintf(__('Actualizados: %1$d. No generables: %2$d.', 'flacso-uruguay'), $updated, $invalid)); ?>
                </p></div>
            <?php endif; ?>

            <div class="notice notice-info inline"><p>
                <?php echo esc_html(sprintf(__('%d links necesitan actualización.', 'flacso-uruguay'), $pending)); ?>
            </p></div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:16px 0 20px">
                <input type="hidden" name="action" value="flacso_save_preinscription_links">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <?php submit_button(__('Generar y guardar todos los links', 'flacso-uruguay'), 'primary', 'submit', false); ?>
            </form>

            <p><input type="search" id="flacso-links-filter" class="regular-text" placeholder="<?php esc_attr_e('Filtrar por nombre, tipo, ID o estado…', 'flacso-uruguay'); ?>"></p>

            <table class="widefat striped" id="flacso-links-table">
                <thead><tr>
                    <th><?php esc_html_e('Tipo', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Oferta / Seminario', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Instancia', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('ID', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Link guardado', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Link esperado', 'flacso-uruguay'); ?></th>
                    <th><?php esc_html_e('Estado', 'flacso-uruguay'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row) :
                    $ok = $row['expected'] !== '' && $row['link'] === $row['expected'];
                    $status = $row['expected'] === '' ? __('No generable', 'flacso-uruguay') : ($ok ? __('Correcto', 'flacso-uruguay') : __('Pendiente', 'flacso-uruguay'));
                    ?>
                    <tr data-search="<?php echo esc_attr(strtolower($row['tipo'] . ' ' . $row['padre'] . ' ' . $row['instancia'] . ' ' . $row['id'] . ' ' . $status)); ?>">
                        <td><strong><?php echo esc_html($row['tipo']); ?></strong></td>
                        <td><?php echo esc_html($row['padre']); ?></td>
                        <td><?php echo esc_html($row['instancia']); ?></td>
                        <td><?php echo esc_html((string) $row['id']); ?></td>
                        <td><code><?php echo esc_html($row['link'] ?: '—'); ?></code></td>
                        <td><code><?php echo esc_html($row['expected'] ?: '—'); ?></code></td>
                        <td><strong><?php echo esc_html($status); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('flacso-links-filter');
            const rows = document.querySelectorAll('#flacso-links-table tbody tr');
            if (!input) return;
            input.addEventListener('input', function () {
                const needle = input.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    row.hidden = Boolean(needle && !String(row.dataset.search || '').includes(needle));
                });
            });
        });
        </script>
        <?php
    }

    /** Genera y persiste todos los links canónicos de forma idempotente. */
    public static function save(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('No tenés permisos para generar links de preinscripción.', 'flacso-uruguay'));
        }
        check_admin_referer(self::NONCE_ACTION);

        $expected = self::expected_links();
        $updated = 0;
        $invalid = 0;

        foreach (['cohorte', 'edicion'] as $post_type) {
            foreach (self::post_ids($post_type) as $id) {
                if (!current_user_can('edit_post', $id)) {
                    $invalid++;
                    continue;
                }

                $url = $expected[$post_type][$id] ?? '';
                if ($url === '') {
                    $invalid++;
                    continue;
                }

                $sanitized = $post_type === 'cohorte'
                    ? FLACSO_Cohorte::sanitize_registration_url($url)
                    : FLACSO_Edicion::sanitize_registration_url($url);

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

    /**
     * Calcula los links esperados sin escribir en la base.
     *
     * Cohorte:  /oferta/<slug-oferta>/
     * Edición:  /seminario/<slug-seminario>/
     *
     * Las Ediciones componentes de un Seminario Integrado heredan el link de la
     * Edición integrada, de acuerdo con el modelo académico vigente.
     */
    private static function expected_links(): array {
        $result = ['cohorte' => [], 'edicion' => []];

        foreach (self::post_ids('cohorte') as $id) {
            $parent_id = absint(get_post_meta($id, FLACSO_Cohorte::META_PARENT_ID, true));
            $result['cohorte'][$id] = self::canonical_parent_url($parent_id, 'oferta');
        }

        $integrated = [];
        $component_ids = [];
        foreach (self::post_ids('edicion') as $id) {
            $seminar_id = absint(get_post_meta($id, FLACSO_Edicion::META_PARENT_ID, true));
            $result['edicion'][$id] = self::canonical_parent_url($seminar_id, 'seminario');
            if ($seminar_id && FLACSO_Seminario_Integrado::is_integrated($seminar_id)) {
                $integrated[] = $id;
                foreach (FLACSO_Seminario_Integrado::component_edition_ids($id) as $component_id) {
                    $component_ids[(int) $component_id] = true;
                }
            }
        }

        // Propagar primero desde Ediciones integradas raíz. Así una integración
        // anidada hereda el URL de la raíz y después lo transmite a sus componentes.
        $visited = [];
        foreach ($integrated as $edition_id) {
            if (!isset($component_ids[$edition_id])) {
                self::inherit_integrated_expected_url($edition_id, $result['edicion'], $visited);
            }
        }
        // Protección ante datos legacy/cíclicos sin raíz detectable.
        foreach ($integrated as $edition_id) {
            self::inherit_integrated_expected_url($edition_id, $result['edicion'], $visited);
        }

        return $result;
    }

    private static function inherit_integrated_expected_url(int $edition_id, array &$urls, array &$visited): void {
        if (isset($visited[$edition_id])) {
            return;
        }
        $visited[$edition_id] = true;

        $url = $urls[$edition_id] ?? '';
        if ($url === '') {
            return;
        }

        foreach (FLACSO_Seminario_Integrado::component_edition_ids($edition_id) as $component_id) {
            $component_id = (int) $component_id;
            $urls[$component_id] = $url;
            $seminar_id = absint(get_post_meta($component_id, FLACSO_Edicion::META_PARENT_ID, true));
            if ($seminar_id && FLACSO_Seminario_Integrado::is_integrated($seminar_id)) {
                self::inherit_integrated_expected_url($component_id, $urls, $visited);
            }
        }
    }

    private static function canonical_parent_url(int $parent_id, string $kind): string {
        if (!$parent_id) {
            return '';
        }
        $parent = get_post($parent_id);
        if (!$parent || $parent->post_status === 'trash') {
            return '';
        }
        $slug = sanitize_title((string) $parent->post_name);
        if ($slug === '') {
            return '';
        }
        return self::BASE_URL . '/' . $kind . '/' . rawurlencode($slug) . '/';
    }

    private static function post_ids(string $post_type): array {
        return array_map('intval', get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]));
    }

    private static function rows(array $expected): array {
        $rows = [];
        foreach (self::post_ids('cohorte') as $id) {
            $parent_id = absint(get_post_meta($id, FLACSO_Cohorte::META_PARENT_ID, true));
            $rows[] = [
                'post_type' => 'cohorte',
                'tipo' => __('Cohorte', 'flacso-uruguay'),
                'padre' => $parent_id ? get_the_title($parent_id) : __('Sin oferta', 'flacso-uruguay'),
                'instancia' => FLACSO_Cohorte::display_name(absint(get_post_meta($id, 'numero', true))),
                'id' => $id,
                'link' => (string) get_post_meta($id, 'link_preinscripcion', true),
                'expected' => $expected['cohorte'][$id] ?? '',
            ];
        }

        foreach (self::post_ids('edicion') as $id) {
            $parent_id = absint(get_post_meta($id, FLACSO_Edicion::META_PARENT_ID, true));
            $rows[] = [
                'post_type' => 'edicion',
                'tipo' => __('Edición', 'flacso-uruguay'),
                'padre' => $parent_id ? get_the_title($parent_id) : __('Sin seminario', 'flacso-uruguay'),
                'instancia' => get_the_title($id) ?: ('#' . $id),
                'id' => $id,
                'link' => (string) get_post_meta($id, 'link_preinscripcion', true),
                'expected' => $expected['edicion'][$id] ?? '',
            ];
        }

        return $rows;
    }
}

FLACSO_Preinscription_Links_Admin::init();
