<?php

if (!defined('ABSPATH')) {
    exit;
}

class Flacso_Main_Page_Migrations {
    private const MENU_SLUG = 'flacso-main-page-migrations';
    private const ACTION_MIGRATE_INSCRIPCIONES_BANNER = 'flacso_migrate_inscripciones_banner_blocks';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_' . self::ACTION_MIGRATE_INSCRIPCIONES_BANNER, [__CLASS__, 'handle_migration_request']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'flacso-main-page',
            __('Migraciones', 'flacso-main-page'),
            __('Migraciones', 'flacso-main-page'),
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'flacso-main-page'));
        }

        $scanned = isset($_GET['scanned']) ? (int) $_GET['scanned'] : null;
        $updated = isset($_GET['updated']) ? (int) $_GET['updated'] : null;
        $replaced = isset($_GET['replaced']) ? (int) $_GET['replaced'] : null;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Migraciones de bloques FLACSO', 'flacso-main-page'); ?></h1>

            <p><?php esc_html_e('Utiliza estas herramientas para normalizar bloques antiguos al formato actual del plugin.', 'flacso-main-page'); ?></p>

            <h2><?php esc_html_e('Migrar Banner de Inscripciones', 'flacso-main-page'); ?></h2>
            <p>
                <?php esc_html_e('Convierte bloques legacy con namespace flacso/inscripciones-banner al namespace actual flacso-uruguay/inscripciones-banner.', 'flacso-main-page'); ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(self::ACTION_MIGRATE_INSCRIPCIONES_BANNER, '_wpnonce_migrate_inscripciones_banner'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION_MIGRATE_INSCRIPCIONES_BANNER); ?>">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Ejecutar migracion', 'flacso-main-page'); ?>
                </button>
            </form>

            <?php if ($scanned !== null && $updated !== null && $replaced !== null) : ?>
                <hr>
                <h3><?php esc_html_e('Resultado', 'flacso-main-page'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Posts escaneados:', 'flacso-main-page'); ?></strong> <?php echo esc_html((string) $scanned); ?></li>
                    <li><strong><?php esc_html_e('Posts actualizados:', 'flacso-main-page'); ?></strong> <?php echo esc_html((string) $updated); ?></li>
                    <li><strong><?php esc_html_e('Bloques reemplazados:', 'flacso-main-page'); ?></strong> <?php echo esc_html((string) $replaced); ?></li>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_migration_request(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para ejecutar esta migracion.', 'flacso-main-page'));
        }

        check_admin_referer(self::ACTION_MIGRATE_INSCRIPCIONES_BANNER, '_wpnonce_migrate_inscripciones_banner');

        $result = self::migrate_inscripciones_banner_block_names();

        $redirect_url = add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'scanned' => (int) $result['scanned_posts'],
                'updated' => (int) $result['updated_posts'],
                'replaced' => (int) $result['replaced_blocks'],
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * @return array<string,int>
     */
    public static function migrate_inscripciones_banner_block_names(): array {
        global $wpdb;

        $result = [
            'scanned_posts' => 0,
            'updated_posts' => 0,
            'replaced_blocks' => 0,
        ];

        $statuses = ['publish', 'private', 'draft', 'pending', 'future'];
        $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));

        $query = "SELECT ID, post_content
            FROM {$wpdb->posts}
            WHERE post_status IN ({$placeholders})
                AND post_type NOT IN ('revision', 'nav_menu_item')
                AND post_content LIKE %s";

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                array_merge($statuses, ['%wp:flacso/inscripciones-banner%'])
            ),
            ARRAY_A
        );

        if (!is_array($rows) || $rows === []) {
            return $result;
        }

        foreach ($rows as $row) {
            $post_id = isset($row['ID']) ? (int) $row['ID'] : 0;
            $content = isset($row['post_content']) ? (string) $row['post_content'] : '';

            if ($post_id <= 0 || $content === '') {
                continue;
            }

            $result['scanned_posts']++;

            $count = 0;
            $updated_content = str_replace(
                'wp:flacso/inscripciones-banner',
                'wp:flacso-uruguay/inscripciones-banner',
                $content,
                $count
            );

            if ($count <= 0 || $updated_content === $content) {
                continue;
            }

            $updated = wp_update_post(
                [
                    'ID' => $post_id,
                    'post_content' => wp_slash($updated_content),
                ],
                true
            );

            if (is_wp_error($updated)) {
                continue;
            }

            $result['updated_posts']++;
            $result['replaced_blocks'] += (int) $count;
        }

        return $result;
    }
}

if (!function_exists('flacso_migrate_inscripciones_banner_blocks')) {
    /**
     * Funcion utilitaria para ejecutar la migracion programaticamente.
     *
     * @return array<string,int>
     */
    function flacso_migrate_inscripciones_banner_blocks(): array {
        return Flacso_Main_Page_Migrations::migrate_inscripciones_banner_block_names();
    }
}
