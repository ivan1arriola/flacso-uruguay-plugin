<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Panel de entrada a la gestión institucional y académica. */
final class FLACSO_Admin_Panel {
    public const PAGE_SLUG = 'flacso-panel';
    private const CAPABILITY = 'edit_posts';

    public static function init(): void {
        if (!is_admin()) {
            return;
        }
        add_action('admin_menu', [self::class, 'register_menu'], 1);
        add_action('admin_menu', [self::class, 'sort_submenus'], 999);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_bar_menu', [self::class, 'register_admin_bar_item'], 2);
    }

    public static function register_menu(): void {
        add_menu_page(
            __('FLACSO Uruguay', 'flacso-uruguay'),
            __('FLACSO', 'flacso-uruguay'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [self::class, 'render'],
            'dashicons-building',
            2
        );
        add_submenu_page(
            self::PAGE_SLUG,
            __('Resumen FLACSO', 'flacso-uruguay'),
            __('Resumen', 'flacso-uruguay'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [self::class, 'render']
        );
    }

    public static function register_admin_bar_item(WP_Admin_Bar $admin_bar): void {
        if (!is_admin_bar_showing() || !current_user_can(self::CAPABILITY)) {
            return;
        }
        $admin_bar->add_node([
            'id' => 'flacso-panel',
            'title' => __('FLACSO', 'flacso-uruguay'),
            'href' => admin_url('admin.php?page=' . self::PAGE_SLUG),
            'meta' => ['title' => __('Abrir panel FLACSO', 'flacso-uruguay')],
        ]);
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }
        $relative = 'includes/assets/flacso-admin-panel.css';
        $path = FLACSO_URUGUAY_PATH . $relative;
        wp_enqueue_style(
            'flacso-admin-panel',
            FLACSO_URUGUAY_URL . $relative,
            [],
            file_exists($path) ? (string) filemtime($path) : FLACSO_URUGUAY_VERSION
        );
    }

    public static function sort_submenus(): void {
        global $submenu;
        if (empty($submenu[self::PAGE_SLUG]) || !is_array($submenu[self::PAGE_SLUG])) {
            return;
        }
        $order = [
            self::PAGE_SLUG => 0,
            'edit.php?post_type=programa-academico' => 10,
            'edit.php?post_type=oferta-academica' => 20,
            'edit.php?post_type=cohorte' => 30,
            'edit.php?post_type=seminario' => 40,
            'edit.php?post_type=edicion-seminario' => 50,
            'edit.php?post_type=tabla-precio' => 60,
            'flacso-main-page' => 70,
            'flacso-integrations' => 80,
            'flacso-meta-integration' => 90,
        ];
        usort($submenu[self::PAGE_SLUG], static function (array $left, array $right) use ($order): int {
            return ($order[$left[2]] ?? 500) <=> ($order[$right[2]] ?? 500);
        });
    }

    public static function render(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('No tenés permisos para acceder a este panel.', 'flacso-uruguay'));
        }

        $counts = self::counts();
        $open_registrations = self::open_registration_count();
        $alerts = self::integrity_alerts();
        $upcoming = self::upcoming_items();
        $editor_url = self::external_editor_url();
        ?>
        <div class="wrap flacso-panel">
            <header class="flacso-panel__hero">
                <div class="flacso-panel__hero-copy">
                    <p class="flacso-panel__eyebrow"><?php esc_html_e('Gestión institucional', 'flacso-uruguay'); ?></p>
                    <h1><?php esc_html_e('Panel FLACSO', 'flacso-uruguay'); ?></h1>
                    <p><?php esc_html_e('Una vista clara del catálogo académico, sus cohortes y las ediciones de seminarios.', 'flacso-uruguay'); ?></p>
                </div>
                <div class="flacso-panel__hero-actions">
                    <a class="button button-primary" href="<?php echo esc_url($editor_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Abrir Editor FLACSO', 'flacso-uruguay'); ?>
                    </a>
                    <a class="button" href="https://preinscripciones.flacso.edu.uy" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Ver preinscripciones', 'flacso-uruguay'); ?>
                    </a>
                </div>
            </header>

            <section class="flacso-panel__metrics" aria-label="<?php esc_attr_e('Resumen del catálogo', 'flacso-uruguay'); ?>">
                <?php self::metric(__('Programas', 'flacso-uruguay'), $counts['programa-academico'], 'dashicons-networking'); ?>
                <?php self::metric(__('Ofertas', 'flacso-uruguay'), $counts['oferta-academica'], 'dashicons-welcome-learn-more'); ?>
                <?php self::metric(__('Seminarios', 'flacso-uruguay'), $counts['seminario'], 'dashicons-book-alt'); ?>
                <?php self::metric(__('Preinscripciones abiertas', 'flacso-uruguay'), $open_registrations, 'dashicons-yes-alt'); ?>
            </section>

            <div class="flacso-panel__layout">
                <main>
                    <section class="flacso-panel__section" aria-labelledby="flacso-workflows-title">
                        <div class="flacso-panel__section-heading">
                            <div>
                                <p class="flacso-panel__eyebrow"><?php esc_html_e('Modelo académico', 'flacso-uruguay'); ?></p>
                                <h2 id="flacso-workflows-title"><?php esc_html_e('Dos recorridos, sin mezclar conceptos', 'flacso-uruguay'); ?></h2>
                            </div>
                        </div>
                        <div class="flacso-panel__workflows">
                            <?php self::workflow_card(
                                __('Oferta académica', 'flacso-uruguay'),
                                __('Carreras y trayectos organizados por programa y abiertos mediante cohortes.', 'flacso-uruguay'),
                                [
                                    ['Programa', 'programa-academico', $counts['programa-academico']],
                                    ['Oferta', 'oferta-academica', $counts['oferta-academica']],
                                    ['Cohorte', 'cohorte', $counts['cohorte']],
                                ],
                                'flacso-panel__workflow--offer'
                            ); ?>
                            <?php self::workflow_card(
                                __('Seminarios', 'flacso-uruguay'),
                                __('Definiciones académicas estables que se dictan en ediciones concretas.', 'flacso-uruguay'),
                                [
                                    ['Programa', 'programa-academico', $counts['programa-academico']],
                                    ['Seminario', 'seminario', $counts['seminario']],
                                    ['Edición', 'edicion-seminario', $counts['edicion-seminario']],
                                ],
                                'flacso-panel__workflow--seminar'
                            ); ?>
                        </div>
                    </section>

                    <section class="flacso-panel__section" aria-labelledby="flacso-resources-title">
                        <div class="flacso-panel__section-heading">
                            <div>
                                <p class="flacso-panel__eyebrow"><?php esc_html_e('Recursos compartidos', 'flacso-uruguay'); ?></p>
                                <h2 id="flacso-resources-title"><?php esc_html_e('Personas, precios y sitio público', 'flacso-uruguay'); ?></h2>
                            </div>
                        </div>
                        <div class="flacso-panel__resources">
                            <?php self::resource_card('dashicons-groups', __('Docentes', 'flacso-uruguay'), __('Perfiles y referencias académicas.', 'flacso-uruguay'), admin_url('admin.php?page=docentes_panel')); ?>
                            <?php self::resource_card('dashicons-money-alt', __('Tablas de precios', 'flacso-uruguay'), __('Aranceles reutilizados por cohortes y ediciones.', 'flacso-uruguay'), admin_url('edit.php?post_type=tabla-precio')); ?>
                            <?php self::resource_card('dashicons-admin-home', __('Portada', 'flacso-uruguay'), __('Contenido y orden de la página principal.', 'flacso-uruguay'), admin_url('admin.php?page=flacso-main-page')); ?>
                            <?php self::resource_card('dashicons-admin-generic', __('Integraciones', 'flacso-uruguay'), __('Conexiones y servicios externos.', 'flacso-uruguay'), admin_url('admin.php?page=flacso-integrations')); ?>
                        </div>
                    </section>
                </main>

                <aside class="flacso-panel__sidebar">
                    <section class="flacso-panel__side-card" aria-labelledby="flacso-upcoming-title">
                        <div class="flacso-panel__side-heading">
                            <h2 id="flacso-upcoming-title"><?php esc_html_e('Próximos comienzos', 'flacso-uruguay'); ?></h2>
                            <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                        </div>
                        <?php self::render_upcoming($upcoming); ?>
                    </section>

                    <section class="flacso-panel__side-card" aria-labelledby="flacso-integrity-title">
                        <div class="flacso-panel__side-heading">
                            <h2 id="flacso-integrity-title"><?php esc_html_e('Calidad de datos', 'flacso-uruguay'); ?></h2>
                            <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                        </div>
                        <?php self::render_alerts($alerts); ?>
                    </section>
                </aside>
            </div>
        </div>
        <?php
    }

    private static function counts(): array {
        $result = [];
        foreach (['programa-academico', 'oferta-academica', 'cohorte', 'seminario', 'edicion-seminario', 'tabla-precio'] as $post_type) {
            $counts = wp_count_posts($post_type);
            $total = 0;
            foreach (['publish', 'draft', 'pending', 'private', 'future'] as $status) {
                $total += isset($counts->{$status}) ? (int) $counts->{$status} : 0;
            }
            $result[$post_type] = $total;
        }
        return $result;
    }

    private static function open_registration_count(): int {
        $total = 0;
        foreach (get_posts(['post_type' => 'cohorte', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']) as $id) {
            if (get_post_meta($id, 'link_preinscripcion', true) && FLACSO_Cohorte::accepts_registration((int) $id)) {
                $total++;
            }
        }
        foreach (get_posts(['post_type' => 'edicion-seminario', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']) as $id) {
            if (get_post_meta($id, 'link_preinscripcion', true) && FLACSO_Edicion_Seminario::accepts_registration((int) $id)) {
                $total++;
            }
        }
        return $total;
    }

    private static function integrity_alerts(): array {
        $alerts = [];
        $checks = [
            ['oferta-academica', 'programa_academico_id', __('Ofertas sin programa', 'flacso-uruguay')],
            ['seminario', 'programa_academico_id', __('Seminarios sin programa', 'flacso-uruguay')],
            ['cohorte', 'numero', __('Cohortes sin número', 'flacso-uruguay')],
            ['cohorte', 'link_preinscripcion', __('Cohortes sin enlace', 'flacso-uruguay')],
            ['edicion-seminario', 'link_preinscripcion', __('Ediciones sin enlace', 'flacso-uruguay')],
        ];
        foreach ($checks as $check) {
            $count = self::count_missing_meta($check[0], $check[1]);
            if ($count > 0) {
                $alerts[] = [
                    'label' => $check[2],
                    'count' => $count,
                    'url' => admin_url('edit.php?post_type=' . $check[0]),
                ];
            }
        }
        return $alerts;
    }

    private static function count_missing_meta(string $post_type, string $meta_key): int {
        return count(get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => $meta_key, 'compare' => 'NOT EXISTS'],
                ['key' => $meta_key, 'value' => '', 'compare' => '='],
                ['key' => $meta_key, 'value' => '0', 'compare' => '='],
            ],
        ]));
    }

    private static function upcoming_items(): array {
        $items = [];
        foreach ([
            ['cohorte', 'Cohorte'],
            ['edicion-seminario', 'Edición'],
        ] as $definition) {
            $posts = get_posts([
                'post_type' => $definition[0],
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 6,
                'meta_key' => 'fecha_inicio',
                'meta_value' => current_time('Y-m-d'),
                'meta_compare' => '>=',
                'meta_type' => 'DATE',
                'orderby' => 'meta_value',
                'order' => 'ASC',
            ]);
            foreach ($posts as $post) {
                $parent_id = $definition[0] === 'cohorte'
                    ? absint(get_post_meta($post->ID, 'oferta_academica_id', true))
                    : absint(get_post_meta($post->ID, 'seminario_id', true));
                $items[] = [
                    'id' => (int) $post->ID,
                    'post_type' => $definition[0],
                    'kind' => $definition[1],
                    'title' => get_the_title($post),
                    'parent' => $parent_id ? get_the_title($parent_id) : '',
                    'date' => (string) get_post_meta($post->ID, 'fecha_inicio', true),
                ];
            }
        }
        usort($items, static function (array $left, array $right): int {
            return strcmp($left['date'], $right['date']);
        });
        return array_slice($items, 0, 6);
    }

    private static function external_editor_url(): string {
        $url = (string) get_option('flacso_external_editor_url', 'https://editor-flacso-uy.vercel.app');
        return $url !== '' ? $url : 'https://editor-flacso-uy.vercel.app';
    }

    private static function metric(string $label, int $value, string $icon): void {
        ?>
        <article class="flacso-panel__metric">
            <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <div><strong><?php echo esc_html(number_format_i18n($value)); ?></strong><span><?php echo esc_html($label); ?></span></div>
        </article>
        <?php
    }

    private static function workflow_card(string $title, string $description, array $steps, string $class): void {
        ?>
        <article class="flacso-panel__workflow <?php echo esc_attr($class); ?>">
            <div class="flacso-panel__workflow-copy"><h3><?php echo esc_html($title); ?></h3><p><?php echo esc_html($description); ?></p></div>
            <ol class="flacso-panel__flow">
                <?php foreach ($steps as $index => $step): ?>
                    <li>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $step[1])); ?>">
                            <span class="flacso-panel__step-number"><?php echo esc_html((string) ($index + 1)); ?></span>
                            <span><strong><?php echo esc_html($step[0]); ?></strong><small><?php echo esc_html(sprintf(_n('%s registro', '%s registros', $step[2], 'flacso-uruguay'), number_format_i18n($step[2]))); ?></small></span>
                            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </article>
        <?php
    }

    private static function resource_card(string $icon, string $title, string $description, string $url): void {
        ?>
        <a class="flacso-panel__resource" href="<?php echo esc_url($url); ?>">
            <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            <span><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></span>
            <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
        </a>
        <?php
    }

    private static function render_upcoming(array $items): void {
        if (!$items) {
            echo '<p class="flacso-panel__empty">' . esc_html__('No hay comienzos próximos con fecha definida.', 'flacso-uruguay') . '</p>';
            return;
        }
        echo '<ul class="flacso-panel__upcoming">';
        foreach ($items as $item) {
            $date = strtotime($item['date']);
            echo '<li><time datetime="' . esc_attr($item['date']) . '"><strong>' . esc_html($date ? date_i18n('d', $date) : '') . '</strong><span>' . esc_html($date ? date_i18n('M', $date) : '') . '</span></time>';
            echo '<div><span class="flacso-panel__kind">' . esc_html($item['kind']) . '</span><a href="' . esc_url(admin_url('post.php?post=' . $item['id'] . '&action=edit')) . '">' . esc_html($item['title']) . '</a>';
            if ($item['parent'] !== '') {
                echo '<small>' . esc_html($item['parent']) . '</small>';
            }
            echo '</div></li>';
        }
        echo '</ul>';
    }

    private static function render_alerts(array $alerts): void {
        if (!$alerts) {
            echo '<div class="flacso-panel__success"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><p><strong>' . esc_html__('Todo en orden', 'flacso-uruguay') . '</strong><span>' . esc_html__('No encontramos datos esenciales incompletos.', 'flacso-uruguay') . '</span></p></div>';
            return;
        }
        echo '<ul class="flacso-panel__alerts">';
        foreach ($alerts as $alert) {
            echo '<li><span class="flacso-panel__alert-count">' . esc_html(number_format_i18n($alert['count'])) . '</span><a href="' . esc_url($alert['url']) . '">' . esc_html($alert['label']) . '</a></li>';
        }
        echo '</ul>';
    }
}

FLACSO_Admin_Panel::init();
