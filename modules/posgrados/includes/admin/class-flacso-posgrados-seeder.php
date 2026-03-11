<?php

if (!class_exists('FLACSO_Posgrados_Seeder')) {
    class FLACSO_Posgrados_Seeder {
        private const SEED_MAP = [
            12330 => ['EDUTIC', 'Maestria'],
            12336 => ['MESYP', 'Maestria'],
            12343 => ['MG', 'Maestria'],
            12310 => ['EAPET', 'Especializacion'],
            12316 => ['EGCCD', 'Especializacion'],
            12278 => ['DEPPI', 'Diplomado'],
            14444 => ['DESI', 'Diplomado'],
            12282 => ['DEVBG', 'Diplomado'],
            12288 => ['DEVNNA', 'Diplomado'],
            13202 => ['DCCH', 'Diploma'],
            12295 => ['DAVIA', 'Diploma'],
            12299 => ['DG', 'Diploma'],
            20668 => ['IAPE', 'Diploma'],
            12302 => ['DIDYP', 'Diploma'],
            14657 => ['DSMSYT', 'Diploma'],
            12304 => ['DMIC', 'Diploma'],
            13185 => ['DIAMHU', 'Diploma'],
        ];

        public static function maybe_seed_map(): void {
            if (!is_admin() || !current_user_can('manage_options')) {
                return;
            }

            if (empty($_GET['flacso_sync_posgrados'])) {
                return;
            }

            $result = ['ok' => [], 'fail' => []];

            foreach (self::get_seed_map() as $post_id => $pair) {
                $post = get_post($post_id);
                if (!$post) {
                    $result['fail'][] = $post_id;
                    continue;
                }

                $abreviacion = FLACSO_Posgrados_Fields::sanitize_abreviacion($pair[0] ?? '');
                $tipo        = FLACSO_Posgrados_Fields::sanitize_tipo($pair[1] ?? '');

                if ($abreviacion) {
                    update_post_meta($post_id, 'abreviacion', $abreviacion);
                }

                if ($tipo) {
                    update_post_meta($post_id, 'tipo_posgrado', $tipo);
                }

                $result['ok'][] = $post_id;
            }

            add_action('admin_notices', function () use ($result) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>FLACSO:</strong> <?php esc_html_e('Sincronizacion de Tipo y Abreviacion finalizada.', 'flacso-posgrados-docentes'); ?></p>
                    <p><strong><?php esc_html_e('Actualizados:', 'flacso-posgrados-docentes'); ?></strong> <?php echo esc_html(implode(', ', $result['ok']) ?: '-'); ?></p>
                    <?php if (!empty($result['fail'])): ?>
                        <p><strong><?php esc_html_e('No encontrados:', 'flacso-posgrados-docentes'); ?></strong> <?php echo esc_html(implode(', ', $result['fail'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php
            });
        }

        public static function maybe_notice_seed_needed(): void {
            if (!is_admin()) {
                return;
            }
            if (empty($_GET['page']) || $_GET['page'] !== FLACSO_POSGRADOS_SLUG) {
                return;
            }
            if (!current_user_can('manage_options')) {
                return;
            }

            $missing = [];
            foreach (array_keys(self::get_seed_map()) as $post_id) {
                if (!get_post($post_id)) {
                    continue;
                }

                if (!get_post_meta($post_id, 'tipo_posgrado', true)) {
                    $missing[] = $post_id;
                }
            }

            if (!$missing) {
                return;
            }

            $url = admin_url('admin.php?page=' . FLACSO_POSGRADOS_SLUG . '&flacso_sync_posgrados=1');
            echo '<div class="notice notice-warning"><p>[!] ' .
                esc_html__('Hay paginas semilla sin "Tipo de Posgrado":', 'flacso-posgrados-docentes') . ' ' .
                esc_html(implode(', ', $missing)) . '. ' .
                sprintf(
                    '<a href="%s">%s</a>',
                    esc_url($url),
                    esc_html__('Sincronizar ahora', 'flacso-posgrados-docentes')
                ) .
                '</p></div>';
        }

        private static function get_seed_map(): array {
            return apply_filters('flacso_pos_seed_map', self::SEED_MAP);
        }
    }
}
