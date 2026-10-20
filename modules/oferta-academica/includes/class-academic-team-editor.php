<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editor administrativo de equipos académicos.
 *
 * - Oferta Académica: grupos estables en equipo_academico.
 * - Cohorte: grupos variables en equipos.
 *
 * Ambos alcances son independientes y no se sincronizan.
 */
final class FLACSO_Academic_Team_Editor {
    public const TYPE_COORDINATION = 'coordinacion_academica';
    public const TYPE_CUSTOM = 'personalizado';

    private const NONCE_ACTION = 'flacso_save_academic_teams_v2';
    private const NONCE_NAME = 'flacso_academic_teams_v2_nonce';

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 6);

        if (!is_admin()) {
            return;
        }

        add_action('save_post_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'save_offer'], 20, 2);
        add_action('save_post_' . FLACSO_Cohorte::POST_TYPE, [self::class, 'save_cohort'], 20, 2);
        add_action('admin_head-post.php', [self::class, 'render_styles']);
        add_action('admin_head-post-new.php', [self::class, 'render_styles']);
        add_action('admin_footer-post.php', [self::class, 'render_script']);
        add_action('admin_footer-post-new.php', [self::class, 'render_script']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_person_search']);
        add_action('wp_ajax_flacso_search_team_people', [self::class, 'search_people_ajax']);
    }

    public static function register_meta(): void {
        register_post_meta(FLACSO_Oferta_Academica::POST_TYPE, 'equipo_academico', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_academic_team'],
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]);

        register_post_meta(FLACSO_Cohorte::POST_TYPE, 'equipos', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => false,
            'sanitize_callback' => [FLACSO_Oferta_Academica::class, 'sanitize_teams'],
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        ]);
    }

    /**
     * Normaliza los grupos estables de una Oferta.
     *
     * Compatibilidad: un grupo histórico sin "tipo" se conserva como grupo
     * personalizado "Equipo académico". Nunca se presume Coordinación académica.
     *
     * @param mixed $value
     * @return array<int,array<string,mixed>>
     */
    public static function sanitize_academic_team($value): array {
        if (!is_array($value)) {
            $value = [];
        }

        $coordination = [];
        $custom = [];
        $coordination_seen = false;

        foreach ($value as $raw_group) {
            if (!is_array($raw_group)) {
                continue;
            }

            $base_groups = FLACSO_Oferta_Academica::sanitize_teams([$raw_group]);
            if (!$base_groups) {
                continue;
            }

            $group = $base_groups[0];
            $raw_type = sanitize_key((string) ($raw_group['tipo'] ?? ''));
            $is_legacy = $raw_type === '';

            if ($is_legacy) {
                $group['tipo'] = self::TYPE_CUSTOM;
                $group['nombre'] = 'Equipo académico';
                $custom[] = $group;
                continue;
            }

            if ($raw_type === self::TYPE_COORDINATION && !$coordination_seen) {
                $group['tipo'] = self::TYPE_COORDINATION;
                $group['nombre'] = 'Coordinación académica';
                $coordination[] = $group;
                $coordination_seen = true;
                continue;
            }

            // Un segundo grupo marcado como coordinación no puede persistir como tal.
            // Se conserva su contenido como grupo personalizado, sin perder integrantes.
            $group['tipo'] = self::TYPE_CUSTOM;
            $group['nombre'] = sanitize_text_field((string) ($raw_group['nombre'] ?? $group['nombre'] ?? ''));
            $custom[] = $group;
        }

        if (!$coordination) {
            $coordination[] = [
                'tipo' => self::TYPE_COORDINATION,
                'nombre' => 'Coordinación académica',
                'descripcion' => '',
                'importancia' => '1',
                'docentes' => [],
            ];
        }
        return array_merge($coordination, $custom);
    }

    /** @return array<int,array<string,mixed>> */
    public static function offer_groups(int $post_id): array {
        return self::sanitize_academic_team(get_post_meta($post_id, 'equipo_academico', true));
    }

    /** @return array<int,array<string,mixed>> */
    public static function offer_coordination_members(int $post_id): array {
        foreach (self::offer_groups($post_id) as $group) {
            if (($group['tipo'] ?? '') === self::TYPE_COORDINATION) {
                return array_values((array) ($group['docentes'] ?? []));
            }
        }
        return [];
    }

    /** @return int[] */
    public static function offer_coordination_member_ids(int $post_id): array {
        return self::coordination_member_ids_from_groups(self::offer_groups($post_id));
    }

    /**
     * @param mixed $groups
     * @return int[]
     */
    public static function coordination_member_ids_from_groups($groups): array {
        $groups = self::sanitize_academic_team($groups);
        foreach ($groups as $group) {
            if (($group['tipo'] ?? '') !== self::TYPE_COORDINATION) {
                continue;
            }

            $ids = [];
            foreach ((array) ($group['docentes'] ?? []) as $member) {
                $id = absint($member['id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
            return array_values($ids);
        }

        return [];
    }

    /**
     * Mantiene el contrato de renderizado sin cargar todas las personas.
     * Únicamente se renderiza la persona seleccionada en cada fila.
     */
    private static function people(): array {
        return [];
    }

    public static function enqueue_person_search(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if ($screen && in_array($screen->post_type, [FLACSO_Oferta_Academica::POST_TYPE, FLACSO_Cohorte::POST_TYPE], true)) {
            wp_enqueue_script('jquery-ui-autocomplete');
        }
    }

    /** Búsqueda autenticada por nombre o correo, en páginas de 20 docentes. */
    public static function search_people_ajax(): void {
        if (!check_ajax_referer('flacso_team_people_search', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sesión vencida. Recargá el editor.', 'flacso-uruguay')], 403);
            return;
        }
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $post_type = $post_id ? get_post_type($post_id) : '';
        if (!in_array($post_type, [FLACSO_Oferta_Academica::POST_TYPE, FLACSO_Cohorte::POST_TYPE], true)
            || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('Sin permisos de edición.', 'flacso-uruguay')], 403);
            return;
        }

        $raw_term = $_GET['term'] ?? '';
        $term = is_string($raw_term) ? trim(sanitize_text_field(wp_unslash($raw_term))) : '';
        if (strlen($term) < 2) {
            wp_send_json_success(['results' => [], 'more' => false]);
            return;
        }
        $term = substr($term, 0, 100);
        $page = max(1, min(100, absint($_GET['page'] ?? 1)));
        $per_page = 20;

        global $wpdb;
        $like = '%' . $wpdb->esc_like($term) . '%';
        $query = $wpdb->prepare(
            "SELECT p.ID, p.post_title
             FROM {$wpdb->posts} p
             WHERE p.post_type = %s
               AND p.post_status IN ('publish','draft','pending','private')
               AND (
                 p.post_title LIKE %s
                 OR EXISTS (
                   SELECT 1 FROM {$wpdb->postmeta} pm
                   WHERE pm.post_id = p.ID AND pm.meta_key = 'correo' AND pm.meta_value LIKE %s
                 )
               )
             ORDER BY p.post_title ASC, p.ID ASC
             LIMIT %d OFFSET %d",
            'docente', $like, $like, $per_page + 1, ($page - 1) * $per_page
        );
        $rows = $wpdb->get_results($query);
        if (!is_array($rows)) {
            wp_send_json_error(['message' => __('Error al buscar docentes.', 'flacso-uruguay')], 500);
            return;
        }
        $results = [];
        foreach (array_slice($rows, 0, $per_page) as $person) {
            $id = (int) $person->ID;
            $results[] = [
                'id' => $id,
                'name' => (string) $person->post_title,
                'email' => self::person_email($id),
                'avatar' => self::person_avatar($id),
            ];
        }
        wp_send_json_success(['results' => $results, 'more' => count($rows) > $per_page]);
    }

    private static function person_email(int $id): string {
        return $id > 0 ? sanitize_email((string) get_post_meta($id, 'correo', true)) : '';
    }

    private static function person_avatar(int $id): string {
        return $id > 0 ? (string) (get_the_post_thumbnail_url($id, 'thumbnail') ?: '') : '';
    }

    public static function render_offer_section(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $teams = self::offer_groups((int) $post->ID);
        $people = self::people();
        $has_coordination = false;
        foreach ($teams as $team) {
            if (($team['tipo'] ?? '') === self::TYPE_COORDINATION) {
                $has_coordination = true;
                break;
            }
        }

        $summary = $teams
            ? sprintf(_n('%d grupo estable', '%d grupos estables', count($teams), 'flacso-uruguay'), count($teams))
            : __('Sin completar', 'flacso-uruguay');

        FLACSO_Academic_Admin_UI::section_start(
            'flacso-oferta-equipos-estables',
            __('Equipos estables', 'flacso-uruguay'),
            __('Coordinación académica y otros grupos propios de la Oferta que no cambian entre cohortes.', 'flacso-uruguay'),
            'dashicons-groups',
            $summary
        );
        ?>
        <div class="flacso-team-editor" data-flacso-team-editor="offer" data-post-id="<?php echo esc_attr((string) $post->ID); ?>" data-person-nonce="<?php echo esc_attr(wp_create_nonce('flacso_team_people_search')); ?>">
            <p class="flacso-team-editor__intro">
                <?php esc_html_e('Estos grupos pertenecen a la Oferta. La Coordinación académica puede existir una sola vez; los demás grupos tienen nombre libre. Los equipos de Cohorte se gestionan por separado.', 'flacso-uruguay'); ?>
            </p>

            <?php if ($teams && !array_filter($teams, static function (array $team): bool {
                return ($team['tipo'] ?? '') === self::TYPE_COORDINATION;
            })) : ?>
                <p class="flacso-team-editor__legacy">
                    <?php esc_html_e('Los datos históricos se muestran como el grupo personalizado “Equipo académico”. No se reclasifican automáticamente como Coordinación académica.', 'flacso-uruguay'); ?>
                </p>
            <?php endif; ?>

            <div class="flacso-team-zones" data-team-list>
                <section class="flacso-team-zone flacso-team-zone--coordination" data-team-zone="coordination">
                    <header class="flacso-team-zone__header">
                        <div>
                            <h3><?php esc_html_e('Coordinación académica', 'flacso-uruguay'); ?></h3>
                            <p><?php esc_html_e('Grupo institucional único. Sus integrantes pueden ser contactos de la carta.', 'flacso-uruguay'); ?></p>
                        </div>
                        <span class="flacso-team-zone__badge"><?php esc_html_e('Grupo obligatorio', 'flacso-uruguay'); ?></span>
                    </header>
                    <div data-team-zone-list>
                        <?php foreach ($teams as $group_index => $team) : ?>
                            <?php if (($team['tipo'] ?? '') === self::TYPE_COORDINATION) : ?>
                                <?php self::render_offer_group((string) $group_index, (array) $team, $people); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button button-secondary" data-add-team="offer-coordination" <?php echo $has_coordination ? 'hidden' : ''; ?>>
                        <?php esc_html_e('Agregar Coordinación académica', 'flacso-uruguay'); ?>
                    </button>
                </section>
                <section class="flacso-team-zone flacso-team-zone--custom" data-team-zone="custom">
                    <header class="flacso-team-zone__header">
                        <div>
                            <h3><?php esc_html_e('Otros grupos docentes', 'flacso-uruguay'); ?></h3>
                            <p><?php esc_html_e('Equipos, comités y otros grupos de la Oferta, separados de la Coordinación académica.', 'flacso-uruguay'); ?></p>
                        </div>
                    </header>
                    <div data-team-zone-list>
                        <?php foreach ($teams as $group_index => $team) : ?>
                            <?php if (($team['tipo'] ?? '') !== self::TYPE_COORDINATION) : ?>
                                <?php self::render_offer_group((string) $group_index, (array) $team, $people); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="flacso-team-actions">
                        <button type="button" class="button button-secondary" data-add-team="offer-custom">
                            <?php esc_html_e('Agregar grupo docente', 'flacso-uruguay'); ?>
                        </button>
                    </div>
                </section>
            </div>

            <?php self::render_member_template($people); ?>
            <?php self::render_offer_group_template(self::TYPE_COORDINATION); ?>
            <?php self::render_offer_group_template(self::TYPE_CUSTOM); ?>
        </div>
        <?php
        FLACSO_Academic_Admin_UI::section_end();
    }

    public static function render_cohort_section(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $teams = FLACSO_Oferta_Academica::sanitize_teams(get_post_meta($post->ID, 'equipos', true));
        $people = self::people();
        $summary = $teams
            ? sprintf(_n('%d grupo variable', '%d grupos variables', count($teams), 'flacso-uruguay'), count($teams))
            : __('Sin completar', 'flacso-uruguay');

        FLACSO_Academic_Admin_UI::section_start(
            'flacso-cohorte-equipos',
            __('Equipos de la cohorte', 'flacso-uruguay'),
            __('Grupos cuyos integrantes cambian para esta apertura, sin duplicar la Coordinación académica estable.', 'flacso-uruguay'),
            'dashicons-groups',
            $summary
        );
        ?>
        <div class="flacso-team-editor" data-flacso-team-editor="cohort" data-post-id="<?php echo esc_attr((string) $post->ID); ?>" data-person-nonce="<?php echo esc_attr(wp_create_nonce('flacso_team_people_search')); ?>">
            <p class="flacso-team-editor__intro">
                <?php esc_html_e('Estos grupos pertenecen únicamente a esta cohorte. No se copian ni se sincronizan con los equipos estables de la Oferta.', 'flacso-uruguay'); ?>
            </p>

            <div data-team-list>
                <?php foreach ($teams as $group_index => $team) : ?>
                    <?php self::render_cohort_group((string) $group_index, (array) $team, $people); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-secondary" data-add-team="cohort">
                <?php esc_html_e('Agregar grupo de cohorte', 'flacso-uruguay'); ?>
            </button>

            <?php self::render_member_template($people); ?>
            <?php self::render_cohort_group_template(); ?>
        </div>
        <?php
        FLACSO_Academic_Admin_UI::section_end();
    }

    /** @param WP_Post[] $people */
    private static function render_person_options(array $people, int $selected = 0): void {
        ?>
        <option value=""><?php esc_html_e('— Seleccionar persona —', 'flacso-uruguay'); ?></option>
        <?php if ($selected > 0 && get_post_type($selected) === 'docente') : ?>
            <option value="<?php echo esc_attr((string) $selected); ?>"
                data-name="<?php echo esc_attr(get_the_title($selected)); ?>"
                data-email="<?php echo esc_attr(self::person_email($selected)); ?>"
                data-avatar="<?php echo esc_attr(self::person_avatar($selected)); ?>" selected><?php echo esc_html(get_the_title($selected)); ?></option>
        <?php endif; ?>
        <?php
    }

    /** @param WP_Post[] $people */
    private static function render_member_row(string $name_prefix, array $member, array $people): void {
        $selected = absint($member['id'] ?? 0);
        $role = (string) ($member['rol'] ?? '');
        $email = (string) ($member['correo'] ?? '');
        ?>
        <div class="flacso-team-member" data-member-row>
            <div class="flacso-team-person-field">
                <label>
                    <span><?php esc_html_e('Persona', 'flacso-uruguay'); ?></span>
                    <input type="search" data-person-search
                        value="<?php echo esc_attr($selected && get_post_type($selected) === 'docente' ? get_the_title($selected) : ''); ?>"
                        autocomplete="off" aria-autocomplete="list"
                        placeholder="<?php esc_attr_e('Buscar por nombre o correo', 'flacso-uruguay'); ?>">
                </label>
                <select data-person-select class="flacso-team-person-select" name="<?php echo esc_attr($name_prefix . '[id]'); ?>" tabindex="-1" aria-hidden="true">
                    <?php self::render_person_options($people, $selected); ?>
                </select>
                <small data-person-feedback aria-live="polite"><?php esc_html_e('Escribí al menos 2 caracteres y elegí un resultado.', 'flacso-uruguay'); ?></small>
            </div>
            <label>
                <span><?php esc_html_e('Rol en este grupo', 'flacso-uruguay'); ?></span>
                <input type="text" name="<?php echo esc_attr($name_prefix . '[rol]'); ?>" value="<?php echo esc_attr($role); ?>" placeholder="<?php esc_attr_e('Ej.: Dirección, Coordinación, Docencia', 'flacso-uruguay'); ?>">
            </label>
            <label>
                <span><?php esc_html_e('Correo específico (opcional)', 'flacso-uruguay'); ?></span>
                <input type="email" name="<?php echo esc_attr($name_prefix . '[correo]'); ?>" value="<?php echo esc_attr($email); ?>">
            </label>
            <button type="button" class="button-link-delete" data-remove-member><?php esc_html_e('Quitar', 'flacso-uruguay'); ?></button>
        </div>
        <?php
    }

    /** @param WP_Post[] $people */
    private static function render_member_template(array $people): void {
        ob_start();
        self::render_member_row('__MEMBER_NAME__', [], $people);
        $html = (string) ob_get_clean();
        ?>
        <template data-member-template><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
        <?php
    }

    /** @param WP_Post[] $people */
    private static function render_offer_group(string $group_index, array $team, array $people): void {
        $type = ($team['tipo'] ?? '') === self::TYPE_COORDINATION ? self::TYPE_COORDINATION : self::TYPE_CUSTOM;
        $prefix = 'flacso_oferta_equipos[' . $group_index . ']';
        $name = $type === self::TYPE_COORDINATION
            ? __('Coordinación académica', 'flacso-uruguay')
            : (string) ($team['nombre'] ?? '');
        ?>
        <section class="flacso-team-group" data-team-group data-team-type="<?php echo esc_attr($type); ?>" data-group-index="<?php echo esc_attr($group_index); ?>">
            <input type="hidden" name="<?php echo esc_attr($prefix . '[tipo]'); ?>" value="<?php echo esc_attr($type); ?>">
            <div class="flacso-team-group__heading">
                <strong><?php echo esc_html($name !== '' ? $name : __('Grupo estable', 'flacso-uruguay')); ?></strong>
                <?php if ($type !== self::TYPE_COORDINATION) : ?>
                <button type="button" class="button-link-delete" data-remove-team><?php esc_html_e('Eliminar grupo', 'flacso-uruguay'); ?></button>
                <?php else : ?>
                    <span><?php esc_html_e('Grupo obligatorio', 'flacso-uruguay'); ?></span>
                <?php endif; ?>
            </div>

            <div class="flacso-team-grid">
                <?php if ($type === self::TYPE_COORDINATION) : ?>
                    <input type="hidden" name="<?php echo esc_attr($prefix . '[nombre]'); ?>" value="Coordinación académica">
                    <div class="flacso-team-field">
                        <span><?php esc_html_e('Tipo de grupo', 'flacso-uruguay'); ?></span>
                        <strong><?php esc_html_e('Coordinación académica', 'flacso-uruguay'); ?></strong>
                        <small><?php esc_html_e('Es el único grupo habilitado para seleccionar el contacto de la carta.', 'flacso-uruguay'); ?></small>
                    </div>
                <?php else : ?>
                    <label class="flacso-team-field">
                        <span><?php esc_html_e('Nombre del grupo', 'flacso-uruguay'); ?></span>
                        <input type="text" name="<?php echo esc_attr($prefix . '[nombre]'); ?>" value="<?php echo esc_attr((string) ($team['nombre'] ?? '')); ?>" placeholder="<?php esc_attr_e('Ej.: Comité académico', 'flacso-uruguay'); ?>">
                    </label>
                <?php endif; ?>

                <label class="flacso-team-field">
                    <span><?php esc_html_e('Importancia visual', 'flacso-uruguay'); ?></span>
                    <select name="<?php echo esc_attr($prefix . '[importancia]'); ?>">
                        <option value="1" <?php selected((string) ($team['importancia'] ?? '3'), '1'); ?>><?php esc_html_e('Alta', 'flacso-uruguay'); ?></option>
                        <option value="2" <?php selected((string) ($team['importancia'] ?? '3'), '2'); ?>><?php esc_html_e('Media', 'flacso-uruguay'); ?></option>
                        <option value="3" <?php selected((string) ($team['importancia'] ?? '3'), '3'); ?>><?php esc_html_e('Normal', 'flacso-uruguay'); ?></option>
                    </select>
                </label>
                <label class="flacso-team-field flacso-team-field--full">
                    <span><?php esc_html_e('Descripción', 'flacso-uruguay'); ?></span>
                    <textarea rows="2" name="<?php echo esc_attr($prefix . '[descripcion]'); ?>"><?php echo esc_textarea((string) ($team['descripcion'] ?? '')); ?></textarea>
                </label>
            </div>

            <div class="flacso-team-members" data-members-prefix="<?php echo esc_attr($prefix . '[docentes]'); ?>">
                <div class="flacso-team-members__header">
                    <strong><?php esc_html_e('Integrantes', 'flacso-uruguay'); ?></strong>
                </div>
                <div data-member-list>
                    <?php foreach ((array) ($team['docentes'] ?? []) as $member_index => $member) : ?>
                        <?php self::render_member_row($prefix . '[docentes][' . (int) $member_index . ']', (array) $member, $people); ?>
                    <?php endforeach; ?>
                </div>
                <div class="flacso-team-members__actions">
                    <button type="button" class="button button-secondary" data-add-member><?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button>
                </div>
            </div>
        </section>
        <?php
    }

    private static function render_offer_group_template(string $type): void {
        $group = [
            'tipo' => $type,
            'nombre' => $type === self::TYPE_COORDINATION ? 'Coordinación académica' : '',
            'descripcion' => '',
            'importancia' => $type === self::TYPE_COORDINATION ? '1' : '3',
            'docentes' => [],
        ];
        $people = self::people();
        ob_start();
        self::render_offer_group('__GROUP__', $group, $people);
        $html = (string) ob_get_clean();
        ?>
        <template data-team-template="<?php echo esc_attr($type === self::TYPE_COORDINATION ? 'offer-coordination' : 'offer-custom'); ?>"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
        <?php
    }

    /** @param WP_Post[] $people */
    private static function render_cohort_group(string $group_index, array $team, array $people): void {
        $prefix = 'flacso_cohorte_equipos[' . $group_index . ']';
        ?>
        <section class="flacso-team-group" data-team-group data-group-index="<?php echo esc_attr($group_index); ?>">
            <div class="flacso-team-group__heading">
                <strong><?php echo esc_html((string) ($team['nombre'] ?? __('Grupo de cohorte', 'flacso-uruguay'))); ?></strong>
                <button type="button" class="button-link-delete" data-remove-team><?php esc_html_e('Eliminar grupo', 'flacso-uruguay'); ?></button>
            </div>

            <div class="flacso-team-grid">
                <label class="flacso-team-field">
                    <span><?php esc_html_e('Nombre del grupo', 'flacso-uruguay'); ?></span>
                    <input type="text" name="<?php echo esc_attr($prefix . '[nombre]'); ?>" value="<?php echo esc_attr((string) ($team['nombre'] ?? '')); ?>" placeholder="<?php esc_attr_e('Ej.: Docentes de esta cohorte', 'flacso-uruguay'); ?>">
                </label>
                <label class="flacso-team-field">
                    <span><?php esc_html_e('Importancia visual', 'flacso-uruguay'); ?></span>
                    <select name="<?php echo esc_attr($prefix . '[importancia]'); ?>">
                        <option value="1" <?php selected((string) ($team['importancia'] ?? '3'), '1'); ?>><?php esc_html_e('Alta', 'flacso-uruguay'); ?></option>
                        <option value="2" <?php selected((string) ($team['importancia'] ?? '3'), '2'); ?>><?php esc_html_e('Media', 'flacso-uruguay'); ?></option>
                        <option value="3" <?php selected((string) ($team['importancia'] ?? '3'), '3'); ?>><?php esc_html_e('Normal', 'flacso-uruguay'); ?></option>
                    </select>
                </label>
                <label class="flacso-team-field flacso-team-field--full">
                    <span><?php esc_html_e('Descripción', 'flacso-uruguay'); ?></span>
                    <textarea rows="2" name="<?php echo esc_attr($prefix . '[descripcion]'); ?>"><?php echo esc_textarea((string) ($team['descripcion'] ?? '')); ?></textarea>
                </label>
            </div>

            <div class="flacso-team-members" data-members-prefix="<?php echo esc_attr($prefix . '[docentes]'); ?>">
                <div class="flacso-team-members__header">
                    <strong><?php esc_html_e('Integrantes', 'flacso-uruguay'); ?></strong>
                </div>
                <div data-member-list>
                    <?php foreach ((array) ($team['docentes'] ?? []) as $member_index => $member) : ?>
                        <?php self::render_member_row($prefix . '[docentes][' . (int) $member_index . ']', (array) $member, $people); ?>
                    <?php endforeach; ?>
                </div>
                <div class="flacso-team-members__actions">
                    <button type="button" class="button button-secondary" data-add-member><?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button>
                </div>
            </div>
        </section>
        <?php
    }

    private static function render_cohort_group_template(): void {
        $people = self::people();
        ob_start();
        self::render_cohort_group('__GROUP__', [
            'nombre' => '',
            'descripcion' => '',
            'importancia' => '3',
            'docentes' => [],
        ], $people);
        $html = (string) ob_get_clean();
        ?>
        <template data-team-template="cohort"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
        <?php
    }

    private static function can_save(int $post_id): bool {
        return isset($_POST[self::NONCE_NAME])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
            && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            && !wp_is_post_revision($post_id)
            && current_user_can('edit_post', $post_id);
    }

    public static function save_offer(int $post_id, WP_Post $post): void {
        if (!self::can_save($post_id) || !isset($_POST['flacso_oferta_equipos']) || !is_array($_POST['flacso_oferta_equipos'])) {
            return;
        }

        $raw = wp_unslash($_POST['flacso_oferta_equipos']);
        $teams = self::sanitize_academic_team($raw);

        if (!$teams) {
            delete_post_meta($post_id, 'equipo_academico');
            return;
        }

        update_post_meta($post_id, 'equipo_academico', $teams);
    }

    public static function save_cohort(int $post_id, WP_Post $post): void {
        if (!self::can_save($post_id) || !isset($_POST['flacso_cohorte_equipos']) || !is_array($_POST['flacso_cohorte_equipos'])) {
            return;
        }

        $raw = wp_unslash($_POST['flacso_cohorte_equipos']);
        $teams = FLACSO_Oferta_Academica::sanitize_teams($raw);

        if (!$teams) {
            delete_post_meta($post_id, 'equipos');
            return;
        }

        update_post_meta($post_id, 'equipos', $teams);
    }

    public static function render_styles(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [FLACSO_Oferta_Academica::POST_TYPE, FLACSO_Cohorte::POST_TYPE], true)) {
            return;
        }
        ?>
        <style>
            .flacso-team-editor{display:grid;gap:14px;min-width:0}
            .flacso-team-zones{display:grid;gap:24px}
            .flacso-team-zone{min-width:0;display:grid;gap:14px}
            .flacso-team-zone--coordination{padding:16px;border:1px solid #b8cbea;border-left:5px solid #2c4777;border-radius:10px;background:#f7faff}
            .flacso-team-zone--custom{padding-top:22px;border-top:2px solid #e2e8f0}
            .flacso-team-zone__header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px}
            .flacso-team-zone__header h3{margin:0 0 5px;font-size:16px;color:#172c49}
            .flacso-team-zone__header p{margin:0;color:#526074;font-size:12px;line-height:1.5}
            .flacso-team-zone__badge{display:inline-flex;padding:4px 10px;border-radius:99px;background:#e1ebf9;color:#29456d;font-size:11px;font-weight:700}
            .flacso-team-zone--coordination .flacso-team-group{background:#fff;border-color:#d3ddeb}
            .flacso-team-person-field{display:grid;gap:5px;min-width:0}
            .flacso-team-person-field label{display:grid;gap:5px;min-width:0}
            .flacso-team-person-field input[type=search]{width:100%;max-width:none;min-height:40px}
            .flacso-team-person-field small{color:#64748b;line-height:1.4;font-size:11px}
            .flacso-team-person-select{display:none!important}
            .flacso-team-members__actions{display:flex;justify-content:flex-start;padding-top:4px}
            .flacso-team-members__actions .button{min-height:38px}
            .ui-autocomplete.flacso-team-autocomplete{z-index:100100!important;max-height:260px;max-width:min(520px,calc(100vw - 40px));overflow-y:auto;overflow-x:hidden;background:#fff;border:1px solid #cbd5e1;box-shadow:0 8px 20px rgba(15,23,42,.16)}
            .ui-autocomplete.flacso-team-autocomplete .ui-menu-item-wrapper{padding:9px 11px;cursor:pointer;white-space:normal;line-height:1.4}
            .ui-autocomplete.flacso-team-autocomplete .ui-state-active{background:#edf4fc;border-color:#cbd5e1;color:#1c456b}
            .ui-autocomplete.flacso-team-autocomplete .flacso-team-person-email{display:block;color:#64748b;font-size:11px}
            @media(max-width:782px){.flacso-team-zone--coordination{padding:12px}.flacso-team-members__actions .button{width:100%;min-height:42px}.flacso-team-zone__header{flex-direction:column}}

            .flacso-team-editor__intro{margin:0;padding:11px 13px;border-left:4px solid #2c4777;background:#f0f4f9;color:#3c434a}
            .flacso-team-editor__legacy{margin:0;padding:10px 12px;border-left:4px solid #dba617;background:#fcf9e8}
            .flacso-team-actions{display:flex;flex-wrap:wrap;gap:8px}
            .flacso-team-field{display:grid;gap:5px;min-width:0}
            .flacso-team-field>span,.flacso-team-member label>span{font-weight:600}
            .flacso-team-field small{color:#646970}
            .flacso-team-field input,.flacso-team-field select,.flacso-team-field textarea,.flacso-team-member input,.flacso-team-member select{width:100%;max-width:none}
            .flacso-team-field--full{grid-column:1/-1}
            .flacso-team-group{display:grid;gap:13px;margin-bottom:12px;padding:15px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
            .flacso-team-group[data-team-type="coordinacion_academica"]{border-left:4px solid #2c4777}
            .flacso-team-group__heading,.flacso-team-members__header{display:flex;align-items:center;justify-content:space-between;gap:12px}
            .flacso-team-grid{display:grid;grid-template-columns:minmax(0,1fr) 220px;gap:12px}
            .flacso-team-members{display:grid;gap:9px;padding-top:10px;border-top:1px solid #f0f0f1}
            .flacso-team-member{display:grid;grid-template-columns:minmax(220px,1.4fr) minmax(180px,1fr) minmax(200px,1fr) auto;align-items:end;gap:9px;padding:10px;border:1px solid #e2e4e7;border-radius:6px;background:#f8fafc}
            .flacso-team-member label{display:grid;gap:4px}
            .flacso-team-member .button-link-delete{align-self:center}
            @media(max-width:1000px){.flacso-team-member{grid-template-columns:1fr 1fr}.flacso-team-member .button-link-delete{justify-self:start}.flacso-team-grid{grid-template-columns:1fr}}
            @media(max-width:700px){.flacso-team-member{grid-template-columns:1fr}.flacso-team-group__heading,.flacso-team-members__header{align-items:flex-start;flex-direction:column}}
        </style>
        <?php
    }

    public static function render_script(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, [FLACSO_Oferta_Academica::POST_TYPE, FLACSO_Cohorte::POST_TYPE], true)) {
            return;
        }
        ?>
        <script>
        (function(){
            function editorOf(el){ return el.closest('[data-flacso-team-editor]'); }

            function nextMemberIndex(container){
                var max=-1;
                container.querySelectorAll('[data-member-row] select[name]').forEach(function(select){
                    var match=select.name.match(/\[(\d+)\]\[id\]$/);
                    if(match){ max=Math.max(max,parseInt(match[1],10)); }
                });
                return max+1;
            }

            function nextGroupIndex(list){
                var max=-1;
                list.querySelectorAll('[data-team-group]').forEach(function(group){
                    var value=parseInt(group.getAttribute('data-group-index')||'-1',10);
                    if(!isNaN(value)){ max=Math.max(max,value); }
                });
                return max+1;
            }

            function syncCoordinationButton(editor){
                if(!editor || editor.getAttribute('data-flacso-team-editor')!=='offer'){ return; }
                var button=editor.querySelector('[data-add-team="offer-coordination"]');
                if(!button){ return; }
                button.hidden=Boolean(editor.querySelector('[data-team-group][data-team-type="coordinacion_academica"]'));
            }

            /**
             * Autocompletado con jQuery UI (incluido en WordPress).
             * El select oculto mantiene la estructura exacta que guardan los
             * formularios y lee el selector del contacto de la carta.
             */
            function initPersonSearch(input){
                var $=window.jQuery;
                if(!$||!$.fn.autocomplete||input.dataset.personReady){ return; }
                var row=input.closest('[data-member-row]');
                var select=row&&row.querySelector('[data-person-select]');
                var feedback=row&&row.querySelector('[data-person-feedback]');
                if(!select){ return; }
                input.dataset.personReady='1';
                var selectedName=input.value;

                $(input).on('input',function(){
                    if(input.value!==selectedName && select.value){
                        select.value='';
                        selectedName='';
                        select.dispatchEvent(new Event('change',{bubbles:true}));
                    }
                });

                $(input).autocomplete({
                    minLength:2,
                    delay:250,
                    classes:{'ui-autocomplete':'flacso-team-autocomplete'},
                    source:function(request,respond){
                        var editor=editorOf(input);
                        if(!editor||!window.ajaxurl){ respond([]); return; }
                        if(feedback){ feedback.textContent='Buscando personas…'; }
                        $.ajax({
                            url:window.ajaxurl,
                            dataType:'json',
                            timeout:10000,
                            data:{
                                action:'flacso_search_team_people',
                                nonce:editor.dataset.personNonce,
                                post_id:editor.dataset.postId,
                                term:request.term,
                                page:1
                            }
                        }).done(function(payload){
                            if(!payload||!payload.success){
                                if(feedback){ feedback.textContent=(payload&&payload.data&&payload.data.message)||'No se pudo realizar la búsqueda.'; }
                                respond([]);
                                return;
                            }
                            var group=input.closest('[data-team-group]');
                            var otherIds=Array.from(group.querySelectorAll('[data-person-select]'))
                                .filter(function(other){ return other!==select && other.value; })
                                .map(function(other){ return String(other.value); });
                            var results=(payload.data.results||[]).filter(function(person){
                                return otherIds.indexOf(String(person.id))===-1;
                            }).map(function(person){
                                return {
                                    label:person.name+(person.email?' — '+person.email:''),
                                    value:person.name,
                                    id:person.id,
                                    name:person.name,
                                    email:person.email||'',
                                    avatar:person.avatar||''
                                };
                            });
                            if(feedback){
                                feedback.textContent=payload.data.more
                                    ?'Mostrando las primeras coincidencias. Escribí más para afinar la búsqueda.'
                                    :(results.length?'Elegí una persona de la lista.':'No hay personas disponibles con ese criterio.');
                            }
                            respond(results);
                        }).fail(function(){
                            if(feedback){ feedback.textContent='No se pudo conectar. Intentá nuevamente.'; }
                            respond([]);
                        });
                    },
                    select:function(event,ui){
                        event.preventDefault();
                        var person=ui.item;
                        select.replaceChildren();
                        select.add(new Option('— Seleccionar persona —',''));
                        var option=new Option(person.name,String(person.id),true,true);
                        option.dataset.name=person.name;
                        option.dataset.email=person.email;
                        option.dataset.avatar=person.avatar;
                        select.add(option);
                        input.value=person.name;
                        selectedName=person.name;
                        if(feedback){ feedback.textContent='Persona seleccionada: '+person.name; }
                        select.dispatchEvent(new Event('change',{bubbles:true}));
                        document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
                    }
                });
                var autocomplete=$(input).autocomplete('instance');
                if(autocomplete){
                    autocomplete._renderItem=function(ul,item){
                        var node=$('<li>');
                        var wrapper=$('<div>').text(item.name);
                        if(item.email){ $('<small>').addClass('flacso-team-person-email').text(item.email).appendTo(wrapper); }
                        return node.append(wrapper).appendTo(ul);
                    };
                }
            }

            document.addEventListener('click',function(event){
                var addMember=event.target.closest('[data-add-member]');
                if(addMember){
                    event.preventDefault();
                    var members=addMember.closest('.flacso-team-members');
                    var editor=editorOf(addMember);
                    var template=editor&&editor.querySelector('template[data-member-template]');
                    if(!members||!template){ return; }
                    var index=nextMemberIndex(members);
                    var prefix=members.getAttribute('data-members-prefix')||'';
                    var html=template.innerHTML.split('__MEMBER_NAME__').join(prefix+'['+index+']');
                    members.querySelector('[data-member-list]').insertAdjacentHTML('beforeend',html);
                    var added=members.querySelector('[data-member-list] [data-member-row]:last-child [data-person-search]');
                    if(added){ initPersonSearch(added); added.focus(); }
                    document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
                    return;
                }

                var removeMember=event.target.closest('[data-remove-member]');
                if(removeMember){
                    event.preventDefault();
                    var member=removeMember.closest('[data-member-row]');
                    if(member){ member.remove(); }
                    document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
                    return;
                }

                var addTeam=event.target.closest('[data-add-team]');
                if(addTeam){
                    event.preventDefault();
                    var editor=editorOf(addTeam);
                    var list=editor&&editor.querySelector('[data-team-list]');
                    var kind=addTeam.getAttribute('data-add-team');
                    var template=editor&&editor.querySelector('template[data-team-template="'+kind+'"]');
                    if(!list||!template){ return; }
                    var zone=kind==='offer-coordination'?'coordination':'custom';
                    var target=editor.getAttribute('data-flacso-team-editor')==='offer'
                        ?list.querySelector('[data-team-zone="'+zone+'"] [data-team-zone-list]')
                        :list;
                    if(!target){ return; }
                    var index=nextGroupIndex(list);
                    target.insertAdjacentHTML('beforeend',template.innerHTML.split('__GROUP__').join(String(index)));
                    syncCoordinationButton(editor);
                    var group=target.querySelector('[data-team-group]:last-child');
                    if(group){
                        var first=group.querySelector('input:not([type="hidden"]),select,textarea,button');
                        if(first){ first.focus(); }
                    }
                    document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
                    return;
                }

                var removeTeam=event.target.closest('[data-remove-team]');
                if(removeTeam){
                    event.preventDefault();
                    var team=removeTeam.closest('[data-team-group]');
                    var editor=editorOf(removeTeam);
                    if(team&&window.confirm('<?php echo esc_js(__('¿Eliminar este grupo?', 'flacso-uruguay')); ?>')){
                        team.remove();
                        syncCoordinationButton(editor);
                        document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
                    }
                }
            });

            document.addEventListener('change',function(event){
                if(event.target.closest('[data-flacso-team-editor]')){
                    document.dispatchEvent(new CustomEvent('flacso:academic-team-changed'));
                }
            });

            document.querySelectorAll('[data-flacso-team-editor="offer"]').forEach(syncCoordinationButton);
            document.querySelectorAll('[data-flacso-team-editor] [data-person-search]').forEach(initPersonSearch);
        })();
        </script>
        <?php
    }
}
