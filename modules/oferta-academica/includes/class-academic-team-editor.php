<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editor administrativo de equipos académicos.
 *
 * Se usa un nombre de clase distinto al editor legado para evitar colisiones
 * con definiciones antiguas que puedan existir en el runtime del servidor.
 *
 * Alcance:
 * - Oferta Académica: Equipo académico estable.
 * - Cohorte: grupos propios de esa cohorte.
 *
 * Cualquier entrada del CPT `docente` puede integrar cualquier grupo.
 */
final class FLACSO_Academic_Team_Editor {
    private const NONCE_ACTION = 'flacso_save_academic_teams_v2';
    private const NONCE_NAME = 'flacso_academic_teams_v2_nonce';

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 6);

        if (!is_admin()) {
            return;
        }

        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
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

    /** @param mixed $value */
    public static function sanitize_academic_team($value): array {
        if (!is_array($value)) {
            return [];
        }

        $groups = FLACSO_Oferta_Academica::sanitize_teams($value);
        if (empty($groups)) {
            return [];
        }

        $group = $groups[0];
        $group['nombre'] = 'Equipo académico';
        $group['importancia'] = '1';

        return [$group];
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'flacso_equipo_academico_oferta',
            __('Equipo académico de la Oferta', 'flacso-uruguay'),
            [self::class, 'render_offer_box'],
            FLACSO_Oferta_Academica::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'flacso_equipos_cohorte',
            __('Equipos de esta Cohorte', 'flacso-uruguay'),
            [self::class, 'render_cohort_box'],
            FLACSO_Cohorte::POST_TYPE,
            'normal',
            'default'
        );
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

    public static function render_offer_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $teams = self::sanitize_academic_team(get_post_meta($post->ID, 'equipo_academico', true));
        $team = $teams[0] ?? [
            'nombre' => 'Equipo académico',
            'descripcion' => '',
            'importancia' => '1',
            'docentes' => [],
        ];
        $people = self::people();
        ?>
        <div class="flacso-team-editor" data-flacso-team-editor="offer">
            <p class="flacso-team-editor__intro">
                <?php esc_html_e('Este equipo pertenece a la Oferta Académica y se mantiene entre cohortes. Cualquier persona del CPT Personas / Equipo puede integrarlo.', 'flacso-uruguay'); ?>
            </p>

            <label class="flacso-team-field">
                <span><?php esc_html_e('Descripción del equipo académico', 'flacso-uruguay'); ?></span>
                <textarea rows="3" name="flacso_equipo_academico[descripcion]"><?php echo esc_textarea((string) ($team['descripcion'] ?? '')); ?></textarea>
            </label>

            <div class="flacso-team-members" data-members-prefix="flacso_equipo_academico[docentes]">
                <div class="flacso-team-members__header">
                    <strong><?php esc_html_e('Integrantes', 'flacso-uruguay'); ?></strong>
                    <button type="button" class="button button-small" data-add-member><?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button>
                </div>
                <div data-member-list>
                    <?php foreach ((array) ($team['docentes'] ?? []) as $index => $member) : ?>
                        <?php self::render_member_row('flacso_equipo_academico[docentes][' . (int) $index . ']', (array) $member, $people); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!metadata_exists('post', $post->ID, 'equipo_academico') && metadata_exists('post', $post->ID, 'equipos')) : ?>
                <p class="flacso-team-editor__legacy">
                    <?php esc_html_e('Hay equipos heredados guardados en la Oferta. No se borran automáticamente; esta sección define únicamente el Equipo académico estable.', 'flacso-uruguay'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php self::render_member_template($people); ?>
        <?php
    }

    public static function render_cohort_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $teams = FLACSO_Oferta_Academica::sanitize_teams(get_post_meta($post->ID, 'equipos', true));
        $people = self::people();
        ?>
        <div class="flacso-team-editor" data-flacso-team-editor="cohort">
            <p class="flacso-team-editor__intro">
                <?php esc_html_e('Estos grupos pertenecen sólo a esta cohorte. Podés crear Coordinación, Asistencia, Docentes, Tutorías u otros grupos. Cualquier persona del CPT Personas / Equipo puede pertenecer a cualquiera de ellos.', 'flacso-uruguay'); ?>
            </p>

            <div data-team-list>
                <?php foreach ($teams as $group_index => $team) : ?>
                    <?php self::render_team_group((int) $group_index, (array) $team, $people); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-secondary" data-add-team><?php esc_html_e('Agregar grupo', 'flacso-uruguay'); ?></button>
        </div>
        <?php self::render_member_template($people); ?>
        <?php self::render_group_template(); ?>
        <?php
    }

    /** @param WP_Post[] $people */
    private static function render_person_options(array $people, int $selected = 0): void {
        ?>
        <option value=""><?php esc_html_e('— Seleccionar persona —', 'flacso-uruguay'); ?></option>
        <?php foreach ($people as $person) : ?>
            <option value="<?php echo esc_attr((string) $person->ID); ?>" <?php selected($selected, $person->ID); ?>><?php echo esc_html(get_the_title($person)); ?></option>
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
                <input type="text" name="<?php echo esc_attr($name_prefix . '[rol]'); ?>" value="<?php echo esc_attr($role); ?>" placeholder="<?php esc_attr_e('Ej.: Coordinación, Docente, Tutoría', 'flacso-uruguay'); ?>">
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
    private static function render_team_group(int $group_index, array $team, array $people): void {
        $prefix = 'flacso_cohorte_equipos[' . $group_index . ']';
        ?>
        <section class="flacso-team-group" data-team-group data-group-index="<?php echo esc_attr((string) $group_index); ?>">
            <div class="flacso-team-group__heading">
                <strong><?php esc_html_e('Grupo', 'flacso-uruguay'); ?></strong>
                <button type="button" class="button-link-delete" data-remove-team><?php esc_html_e('Eliminar grupo', 'flacso-uruguay'); ?></button>
            </div>

            <div class="flacso-team-grid">
                <label class="flacso-team-field">
                    <span><?php esc_html_e('Nombre del grupo', 'flacso-uruguay'); ?></span>
                    <input type="text" name="<?php echo esc_attr($prefix . '[nombre]'); ?>" value="<?php echo esc_attr((string) ($team['nombre'] ?? '')); ?>" placeholder="<?php esc_attr_e('Ej.: Coordinación', 'flacso-uruguay'); ?>">
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

    private static function render_group_template(): void {
        ?>
        <template data-team-template>
            <section class="flacso-team-group" data-team-group data-group-index="__GROUP__">
                <div class="flacso-team-group__heading">
                    <strong><?php esc_html_e('Grupo', 'flacso-uruguay'); ?></strong>
                    <button type="button" class="button-link-delete" data-remove-team><?php esc_html_e('Eliminar grupo', 'flacso-uruguay'); ?></button>
                </div>
                <div class="flacso-team-grid">
                    <label class="flacso-team-field">
                        <span><?php esc_html_e('Nombre del grupo', 'flacso-uruguay'); ?></span>
                        <input type="text" name="flacso_cohorte_equipos[__GROUP__][nombre]" placeholder="<?php esc_attr_e('Ej.: Coordinación', 'flacso-uruguay'); ?>">
                    </label>
                    <label class="flacso-team-field">
                        <span><?php esc_html_e('Importancia visual', 'flacso-uruguay'); ?></span>
                        <select name="flacso_cohorte_equipos[__GROUP__][importancia]">
                            <option value="1"><?php esc_html_e('Alta', 'flacso-uruguay'); ?></option>
                            <option value="2"><?php esc_html_e('Media', 'flacso-uruguay'); ?></option>
                            <option value="3" selected><?php esc_html_e('Normal', 'flacso-uruguay'); ?></option>
                        </select>
                    </label>
                    <label class="flacso-team-field flacso-team-field--full">
                        <span><?php esc_html_e('Descripción', 'flacso-uruguay'); ?></span>
                        <textarea rows="2" name="flacso_cohorte_equipos[__GROUP__][descripcion]"></textarea>
                    </label>
                </div>
                <div class="flacso-team-members" data-members-prefix="flacso_cohorte_equipos[__GROUP__][docentes]">
                    <div class="flacso-team-members__header">
                        <strong><?php esc_html_e('Integrantes', 'flacso-uruguay'); ?></strong>
                        <button type="button" class="button button-small" data-add-member><?php esc_html_e('Agregar integrante', 'flacso-uruguay'); ?></button>
                    </div>
                    <div data-member-list></div>
                </div>
            </section>
        </template>
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
        if (!self::can_save($post_id)) {
            return;
        }

        $raw = isset($_POST['flacso_equipo_academico']) && is_array($_POST['flacso_equipo_academico'])
            ? wp_unslash($_POST['flacso_equipo_academico'])
            : [];

        $team = self::sanitize_academic_team([[
            'nombre' => 'Equipo académico',
            'descripcion' => $raw['descripcion'] ?? '',
            'importancia' => '1',
            'docentes' => $raw['docentes'] ?? [],
        ]]);

        if (empty($team)) {
            delete_post_meta($post_id, 'equipo_academico');
            return;
        }

        update_post_meta($post_id, 'equipo_academico', $team);
    }

    public static function save_cohort(int $post_id, WP_Post $post): void {
        if (!self::can_save($post_id)) {
            return;
        }

        $raw = isset($_POST['flacso_cohorte_equipos']) && is_array($_POST['flacso_cohorte_equipos'])
            ? wp_unslash($_POST['flacso_cohorte_equipos'])
            : [];
        $teams = FLACSO_Oferta_Academica::sanitize_teams($raw);

        if (empty($teams)) {
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
            .flacso-team-editor{display:grid;gap:14px;padding:4px 0}.flacso-team-editor__intro{margin:0;padding:11px 13px;border-left:4px solid #2271b1;background:#f0f6fc;color:#3c434a}.flacso-team-editor__legacy{margin:0;padding:10px 12px;border-left:4px solid #dba617;background:#fcf9e8}.flacso-team-field{display:grid;gap:5px}.flacso-team-field>span,.flacso-team-member label>span{font-weight:600}.flacso-team-field input,.flacso-team-field select,.flacso-team-field textarea,.flacso-team-member input,.flacso-team-member select{width:100%}.flacso-team-field--full{grid-column:1/-1}.flacso-team-group{display:grid;gap:13px;margin-bottom:14px;padding:15px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.flacso-team-group__heading,.flacso-team-members__header{display:flex;align-items:center;justify-content:space-between;gap:12px}.flacso-team-grid{display:grid;grid-template-columns:1fr 220px;gap:12px}.flacso-team-members{display:grid;gap:9px;padding-top:10px;border-top:1px solid #f0f0f1}.flacso-team-member{display:grid;grid-template-columns:minmax(220px,1.4fr) minmax(180px,1fr) minmax(200px,1fr) auto;align-items:end;gap:9px;padding:10px;border:1px solid #e2e4e7;border-radius:6px;background:#f8fafc}.flacso-team-member label{display:grid;gap:4px}.flacso-team-member .button-link-delete{align-self:center}@media(max-width:1000px){.flacso-team-member{grid-template-columns:1fr 1fr}.flacso-team-member .button-link-delete{justify-self:start}.flacso-team-grid{grid-template-columns:1fr}}@media(max-width:700px){.flacso-team-member{grid-template-columns:1fr}}
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
            function templateNear(editor, selector){
                if(!editor){ return document.querySelector(selector); }
                return editor.parentElement.querySelector(selector) || document.querySelector(selector);
            }
            function nextMemberIndex(container){
                var max=-1;
                container.querySelectorAll('[data-member-row] select[name]').forEach(function(select){
                    var match=select.name.match(/\[(\d+)\]\[id\]$/);
                    if(match){ max=Math.max(max,parseInt(match[1],10)); }
                });
                return max+1;
            }
            document.addEventListener('click',function(event){
                var addMember=event.target.closest('[data-add-member]');
                if(addMember){
                    event.preventDefault();
                    var members=addMember.closest('.flacso-team-members');
                    var editor=editorOf(addMember);
                    var template=templateNear(editor,'template[data-member-template]');
                    if(!members||!template){ return; }
                    var index=nextMemberIndex(members);
                    var prefix=members.getAttribute('data-members-prefix')||'';
                    var html=template.innerHTML.split('__MEMBER_NAME__').join(prefix+'['+index+']');
                    members.querySelector('[data-member-list]').insertAdjacentHTML('beforeend',html);
                    return;
                }

                var removeMember=event.target.closest('[data-remove-member]');
                if(removeMember){
                    event.preventDefault();
                    var member=removeMember.closest('[data-member-row]');
                    if(member){ member.remove(); }
                    return;
                }

                var addTeam=event.target.closest('[data-add-team]');
                if(addTeam){
                    event.preventDefault();
                    var editor=editorOf(addTeam);
                    var list=editor&&editor.querySelector('[data-team-list]');
                    var template=templateNear(editor,'template[data-team-template]');
                    if(!list||!template){ return; }
                    var max=-1;
                    list.querySelectorAll('[data-team-group]').forEach(function(group){
                        max=Math.max(max,parseInt(group.getAttribute('data-group-index')||'-1',10));
                    });
                    var index=max+1;
                    list.insertAdjacentHTML('beforeend',template.innerHTML.split('__GROUP__').join(String(index)));
                    return;
                }

                var removeTeam=event.target.closest('[data-remove-team]');
                if(removeTeam){
                    event.preventDefault();
                    var team=removeTeam.closest('[data-team-group]');
                    if(team&&window.confirm('<?php echo esc_js(__('¿Eliminar este grupo de la cohorte?', 'flacso-uruguay')); ?>')){ team.remove(); }
                }
            });
        })();
        </script>
        <?php
    }
}
