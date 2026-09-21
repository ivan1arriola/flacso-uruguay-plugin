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
            return [];
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

    /** @return WP_Post[] */
    private static function people(): array {
        return get_posts([
            'post_type' => 'docente',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);
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
        <div class="flacso-team-editor" data-flacso-team-editor="offer">
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

            <div data-team-list>
                <?php foreach ($teams as $group_index => $team) : ?>
                    <?php self::render_offer_group((string) $group_index, (array) $team, $people); ?>
                <?php endforeach; ?>
            </div>

            <div class="flacso-team-actions">
                <button type="button" class="button button-secondary" data-add-team="offer-coordination" <?php echo $has_coordination ? 'hidden' : ''; ?>>
                    <?php esc_html_e('Agregar Coordinación académica', 'flacso-uruguay'); ?>
                </button>
                <button type="button" class="button button-secondary" data-add-team="offer-custom">
                    <?php esc_html_e('Agregar grupo estable', 'flacso-uruguay'); ?>
                </button>
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
        <div class="flacso-team-editor" data-flacso-team-editor="cohort">
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
        <?php foreach ($people as $person) : ?>
            <option
                value="<?php echo esc_attr((string) $person->ID); ?>"
                data-name="<?php echo esc_attr(get_the_title($person)); ?>"
                data-email="<?php echo esc_attr(self::person_email((int) $person->ID)); ?>"
                data-avatar="<?php echo esc_attr(self::person_avatar((int) $person->ID)); ?>"
                <?php selected($selected, $person->ID); ?>
            ><?php echo esc_html(get_the_title($person)); ?></option>
        <?php endforeach; ?>
        <?php
    }

    /** @param WP_Post[] $people */
    private static function render_member_row(string $name_prefix, array $member, array $people): void {
        $selected = absint($member['id'] ?? 0);
        $role = (string) ($member['rol'] ?? '');
        $email = (string) ($member['correo'] ?? '');
        ?>
        <div class="flacso-team-member" data-member-row>
            <label>
                <span><?php esc_html_e('Persona', 'flacso-uruguay'); ?></span>
                <select name="<?php echo esc_attr($name_prefix . '[id]'); ?>">
                    <?php self::render_person_options($people, $selected); ?>
                </select>
            </label>
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
                <button type="button" class="button-link-delete" data-remove-team><?php esc_html_e('Eliminar grupo', 'flacso-uruguay'); ?></button>
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
                    <button type="button" class="button button-small" data-add-member><?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button>
                </div>
                <div data-member-list>
                    <?php foreach ((array) ($team['docentes'] ?? []) as $member_index => $member) : ?>
                        <?php self::render_member_row($prefix . '[docentes][' . (int) $member_index . ']', (array) $member, $people); ?>
                    <?php endforeach; ?>
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
                    <button type="button" class="button button-small" data-add-member><?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button>
                </div>
                <div data-member-list>
                    <?php foreach ((array) ($team['docentes'] ?? []) as $member_index => $member) : ?>
                        <?php self::render_member_row($prefix . '[docentes][' . (int) $member_index . ']', (array) $member, $people); ?>
                    <?php endforeach; ?>
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
            .flacso-team-editor{display:grid;gap:14px}
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
                    var added=members.querySelector('[data-member-list] [data-member-row]:last-child select');
                    if(added){ added.focus(); }
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
                    var index=nextGroupIndex(list);
                    list.insertAdjacentHTML('beforeend',template.innerHTML.split('__GROUP__').join(String(index)));
                    syncCoordinationButton(editor);
                    var group=list.querySelector('[data-team-group]:last-child');
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
        })();
        </script>
        <?php
    }
}
