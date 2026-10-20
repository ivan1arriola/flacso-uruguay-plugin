<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Editor independiente de planes articulados, sin alterar las matrículas. */
final class FLACSO_Academic_Articulations_Admin {
    public static function init(): void {
        if (!is_admin()) {
            return;
        }
        add_action('add_meta_boxes_' . FLACSO_Academic_Articulations::POST_TYPE, [self::class, 'register_box']);
        add_action('save_post_' . FLACSO_Academic_Articulations::POST_TYPE, [self::class, 'save'], 10, 2);
        add_action('admin_notices', [self::class, 'notice']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_filter('use_block_editor_for_post_type', [self::class, 'classic_editor'], 10, 2);
    }

    public static function classic_editor(bool $enabled, string $post_type): bool {
        return $post_type === FLACSO_Academic_Articulations::POST_TYPE ? false : $enabled;
    }

    public static function assets(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== FLACSO_Academic_Articulations::POST_TYPE || !in_array($screen->base, ['post', 'post-new'], true)) {
            return;
        }
        $base = 'modules/oferta-academica/assets/';
        wp_enqueue_style(
            'flacso-articulaciones-admin',
            FLACSO_OFERTA_ACADEMICA_URL . 'assets/articulaciones-admin.css',
            [],
            (string) filemtime(FLACSO_OFERTA_ACADEMICA_PATH . 'assets/articulaciones-admin.css')
        );
        wp_enqueue_script(
            'flacso-articulaciones-admin',
            FLACSO_OFERTA_ACADEMICA_URL . 'assets/articulaciones-admin.js',
            [],
            (string) filemtime(FLACSO_OFERTA_ACADEMICA_PATH . 'assets/articulaciones-admin.js'),
            true
        );
    }

    public static function register_box(): void {
        add_meta_box(
            'flacso_articulacion_editor',
            __('Estructura académica y presentación pública', 'flacso-uruguay'),
            [self::class, 'render'],
            FLACSO_Academic_Articulations::POST_TYPE,
            'normal',
            'high'
        );
    }

    /** @return array<int,WP_Post> */
    private static function offers(): array {
        return get_posts([
            'post_type' => FLACSO_Oferta_Academica::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    private static function offer_selector(array $offers, string $name, int $selected = 0): void {
        ?>
        <select name="<?php echo esc_attr($name); ?>" data-art-offer required>
            <option value=""><?php esc_html_e('Elegir Oferta Académica', 'flacso-uruguay'); ?></option>
            <?php foreach ($offers as $offer) : ?>
                <?php
                $mentions = get_post_meta($offer->ID, 'menciones', true);
                $mentions = is_array($mentions) ? FLACSO_Oferta_Academica::sanitize_text_list($mentions) : [];
                ?>
                <option value="<?php echo esc_attr((string) $offer->ID); ?>"
                        data-mentions="<?php echo esc_attr(wp_json_encode($mentions)); ?>"
                        <?php selected($selected, (int) $offer->ID); ?>>
                    <?php echo esc_html($offer->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private static function node_row(array $item, string $index, array $offers): void {
        $prefix = 'flacso_articulacion[etapas][' . $index . ']';
        $offer_id = absint($item['oferta_id'] ?? 0);
        $mentions = $offer_id ? get_post_meta($offer_id, 'menciones', true) : [];
        $mentions = is_array($mentions) ? FLACSO_Oferta_Academica::sanitize_text_list($mentions) : [];
        ?>
        <article class="flacso-art-row" data-art-row="node">
            <div class="flacso-art-row__heading">
                <strong><?php esc_html_e('Etapa curricular', 'flacso-uruguay'); ?></strong>
                <button type="button" class="button-link-delete" data-art-remove><?php esc_html_e('Quitar etapa', 'flacso-uruguay'); ?></button>
            </div>
            <div class="flacso-art-fields">
                <label><span><?php esc_html_e('Identificador estable', 'flacso-uruguay'); ?></span>
                    <input data-art-key type="text" name="<?php echo esc_attr($prefix . '[clave]'); ?>" value="<?php echo esc_attr((string) ($item['clave'] ?? '')); ?>" placeholder="ciclo-2-rama-a" required></label>
                <label><span><?php esc_html_e('N.º de ciclo', 'flacso-uruguay'); ?></span>
                    <input type="number" min="1" max="99" name="<?php echo esc_attr($prefix . '[ciclo]'); ?>" value="<?php echo esc_attr((string) ($item['ciclo'] ?? '')); ?>" required></label>
                <label><span><?php esc_html_e('Nombre de la etapa', 'flacso-uruguay'); ?></span>
                    <input type="text" name="<?php echo esc_attr($prefix . '[nombre]'); ?>" value="<?php echo esc_attr((string) ($item['nombre'] ?? '')); ?>" placeholder="Ciclo 1 — formación inicial" required></label>
                <label><span><?php esc_html_e('Oferta Académica que otorga el título', 'flacso-uruguay'); ?></span>
                    <?php self::offer_selector($offers, $prefix . '[oferta_id]', $offer_id); ?></label>
                <label><span><?php esc_html_e('Mención del título (opcional)', 'flacso-uruguay'); ?></span>
                    <select name="<?php echo esc_attr($prefix . '[mencion]'); ?>" data-art-mention>
                        <option value=""><?php esc_html_e('Sin mención específica', 'flacso-uruguay'); ?></option>
                        <?php foreach ($mentions as $mention) : ?>
                            <option value="<?php echo esc_attr($mention); ?>" <?php selected((string) ($item['mencion'] ?? ''), $mention); ?>><?php echo esc_html($mention); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label class="flacso-art-field--wide"><span><?php esc_html_e('Calendario del ciclo (URL HTTPS)', 'flacso-uruguay'); ?></span>
                    <input type="url" name="<?php echo esc_attr($prefix . '[calendario_url]'); ?>" value="<?php echo esc_attr((string) ($item['calendario_url'] ?? '')); ?>" placeholder="https://..."></label>
                <label class="flacso-art-field--wide"><span><?php esc_html_e('Descripción académica', 'flacso-uruguay'); ?></span>
                    <textarea rows="2" name="<?php echo esc_attr($prefix . '[descripcion]'); ?>"><?php echo esc_textarea((string) ($item['descripcion'] ?? '')); ?></textarea></label>
            </div>
        </article>
        <?php
    }

    private static function edge_row(array $item, string $index, array $nodes): void {
        $prefix = 'flacso_articulacion[transiciones][' . $index . ']';
        ?>
        <article class="flacso-art-row" data-art-row="edge">
            <div class="flacso-art-row__heading">
                <strong><?php esc_html_e('Aprobación del ciclo anterior', 'flacso-uruguay'); ?></strong>
                <button type="button" class="button-link-delete" data-art-remove><?php esc_html_e('Quitar vínculo', 'flacso-uruguay'); ?></button>
            </div>
            <div class="flacso-art-fields">
                <?php foreach (['origen' => __('Ciclo que debe aprobarse', 'flacso-uruguay'), 'destino' => __('Ciclo que queda habilitado', 'flacso-uruguay')] as $field => $label) : ?>
                    <label><span><?php echo esc_html($label); ?></span>
                        <select data-art-edge="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($prefix . '[' . $field . ']'); ?>" required>
                            <option value=""><?php esc_html_e('Seleccionar etapa', 'flacso-uruguay'); ?></option>
                            <?php foreach ($nodes as $node) : ?>
                                <option value="<?php echo esc_attr((string) $node['clave']); ?>" <?php selected((string) ($item[$field] ?? ''), (string) $node['clave']); ?>>
                                    <?php echo esc_html('Ciclo ' . $node['ciclo'] . ' · ' . $node['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
                <label class="flacso-art-field--wide"><span><?php esc_html_e('Mención de origen requerida (opcional)', 'flacso-uruguay'); ?></span>
                    <select data-art-edge-mention name="<?php echo esc_attr($prefix . '[mencion]'); ?>">
                        <option value=""><?php esc_html_e('Cualquier mención', 'flacso-uruguay'); ?></option>
                        <?php
                        $node_map = array_column($nodes, null, 'clave');
                        $source = $node_map[$item['origen'] ?? ''] ?? null;
                        $available = $source ? get_post_meta((int) $source['oferta_id'], 'menciones', true) : [];
                        foreach (is_array($available) ? $available : [] as $mention) : ?>
                            <option value="<?php echo esc_attr((string) $mention); ?>" <?php selected((string) ($item['mencion'] ?? ''), (string) $mention); ?>><?php echo esc_html((string) $mention); ?></option>
                        <?php endforeach; ?>
                    </select></label>
            </div>
        </article>
        <?php
    }

    private static function presentation_row(array $item, string $index, array $offers): void {
        $prefix = 'flacso_articulacion[presentaciones][' . $index . ']';
        ?>
        <article class="flacso-art-row" data-art-row="presentation">
            <div class="flacso-art-row__heading">
                <strong><?php esc_html_e('Texto por Oferta Académica', 'flacso-uruguay'); ?></strong>
                <button type="button" class="button-link-delete" data-art-remove><?php esc_html_e('Quitar texto', 'flacso-uruguay'); ?></button>
            </div>
            <div class="flacso-art-fields">
                <label class="flacso-art-field--wide"><span><?php esc_html_e('Oferta a la que se muestra', 'flacso-uruguay'); ?></span>
                    <?php self::offer_selector($offers, $prefix . '[oferta_id]', absint($item['oferta_id'] ?? 0)); ?></label>
                <label class="flacso-art-field--wide"><span><?php esc_html_e('Cómo se integra al recorrido', 'flacso-uruguay'); ?></span>
                    <textarea rows="3" name="<?php echo esc_attr($prefix . '[introduccion]'); ?>" placeholder="<?php esc_attr_e('Explicá en qué ciclos participa esta Oferta.', 'flacso-uruguay'); ?>"><?php echo esc_textarea((string) ($item['introduccion'] ?? '')); ?></textarea></label>
                <label class="flacso-art-field--wide"><span><?php esc_html_e('Condiciones de continuidad y contratación', 'flacso-uruguay'); ?></span>
                    <textarea rows="3" name="<?php echo esc_attr($prefix . '[continuidad]'); ?>" placeholder="<?php esc_attr_e('Ej.: el plan de cuotas de la Maestría contempla los tres ciclos; quienes ingresan por el Diploma contratan la continuación por separado.', 'flacso-uruguay'); ?>"><?php echo esc_textarea((string) ($item['continuidad'] ?? '')); ?></textarea></label>
            </div>
        </article>
        <?php
    }

    public static function render(WP_Post $post): void {
        wp_nonce_field('flacso_save_articulacion', FLACSO_Academic_Articulations::NONCE);
        $nodes = get_post_meta($post->ID, FLACSO_Academic_Articulations::META_NODES, true);
        $nodes = is_array($nodes) ? $nodes : [];
        $edges = get_post_meta($post->ID, FLACSO_Academic_Articulations::META_EDGES, true);
        $edges = is_array($edges) ? $edges : [];
        $presentations = get_post_meta($post->ID, FLACSO_Academic_Articulations::META_NOTES, true);
        $presentations = is_array($presentations) ? $presentations : [];
        $offers = self::offers();
        ?>
        <div class="flacso-art-editor" data-art-editor>
            <p class="flacso-art-intro">
                <?php esc_html_e('Este plan organiza ciclos compartidos, sus ramificaciones y las titulaciones existentes. No modifica cohortes, preinscripciones ni facturación. Publicá el plan únicamente cuando la configuración esté completa.', 'flacso-uruguay'); ?>
            </p>
            <section class="flacso-art-section">
                <h3><?php esc_html_e('1. Etapas y calendarios', 'flacso-uruguay'); ?></h3>
                <p><?php esc_html_e('Cada etapa referencia una Oferta Académica real. Varias etapas pueden tener el mismo número de ciclo para representar ramificaciones.', 'flacso-uruguay'); ?></p>
                <div data-art-list="node">
                    <?php foreach ($nodes as $index => $node) : self::node_row($node, (string) $index, $offers); endforeach; ?>
                </div>
                <button type="button" class="button button-secondary" data-art-add="node"><?php esc_html_e('Agregar etapa', 'flacso-uruguay'); ?></button>
                <template data-art-template="node"><?php self::node_row([], '__INDEX__', $offers); ?></template>
            </section>
            <section class="flacso-art-section">
                <h3><?php esc_html_e('2. Requisitos de progresión', 'flacso-uruguay'); ?></h3>
                <p><?php esc_html_e('Vinculá ciclos consecutivos. La aprobación del origen es requisito para cursar el destino. La mención puede limitar cada camino.', 'flacso-uruguay'); ?></p>
                <div data-art-list="edge">
                    <?php foreach ($edges as $index => $edge) : self::edge_row($edge, (string) $index, $nodes); endforeach; ?>
                </div>
                <button type="button" class="button button-secondary" data-art-add="edge"><?php esc_html_e('Agregar transición', 'flacso-uruguay'); ?></button>
                <template data-art-template="edge"><?php self::edge_row([], '__INDEX__', $nodes); ?></template>
            </section>
            <section class="flacso-art-section">
                <h3><?php esc_html_e('3. Información específica en cartas y web', 'flacso-uruguay'); ?></h3>
                <p><?php esc_html_e('Podés describir el alcance del ingreso y las condiciones económicas de continuación de cada Oferta del plan. Son textos informativos, no reglas de matrícula ni de cobro.', 'flacso-uruguay'); ?></p>
                <div data-art-list="presentation">
                    <?php foreach ($presentations as $index => $item) : self::presentation_row($item, (string) $index, $offers); endforeach; ?>
                </div>
                <button type="button" class="button button-secondary" data-art-add="presentation"><?php esc_html_e('Agregar texto por oferta', 'flacso-uruguay'); ?></button>
                <template data-art-template="presentation"><?php self::presentation_row([], '__INDEX__', $offers); ?></template>
            </section>
        </div>
        <?php
    }

    public static function save(int $post_id, WP_Post $post): void {
        if (!isset($_POST[FLACSO_Academic_Articulations::NONCE])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[FLACSO_Academic_Articulations::NONCE])), 'flacso_save_articulacion')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $raw = isset($_POST['flacso_articulacion']) && is_array($_POST['flacso_articulacion'])
            ? wp_unslash($_POST['flacso_articulacion'])
            : [];
        $validated = FLACSO_Academic_Articulations::validate($raw);
        if (is_wp_error($validated)) {
            set_transient('flacso_articulacion_error_' . $post_id, $validated->get_error_message(), 90);
            return;
        }
        update_post_meta($post_id, FLACSO_Academic_Articulations::META_NODES, $validated['etapas']);
        update_post_meta($post_id, FLACSO_Academic_Articulations::META_EDGES, $validated['transiciones']);
        update_post_meta($post_id, FLACSO_Academic_Articulations::META_NOTES, $validated['presentaciones']);
        update_post_meta($post_id, FLACSO_Academic_Articulations::META_INDEX, FLACSO_Academic_Articulations::offer_index($validated['etapas']));
    }

    public static function notice(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== FLACSO_Academic_Articulations::POST_TYPE) {
            return;
        }
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$post_id) {
            return;
        }
        $message = get_transient('flacso_articulacion_error_' . $post_id);
        if ($message) {
            delete_transient('flacso_articulacion_error_' . $post_id);
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        }
    }
}
