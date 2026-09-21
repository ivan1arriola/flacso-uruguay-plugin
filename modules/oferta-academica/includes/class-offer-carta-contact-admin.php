<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Contacto visible en la carta pública de una Oferta Académica. */
final class FLACSO_Offer_Carta_Contact_Admin {
    public const META_PERSON_ID = 'carta_contacto_docente_id';
    public const META_TITLE = 'carta_contacto_titulo';
    public const META_EMAIL = 'carta_contacto_correo';

    private const NONCE_ACTION = 'flacso_save_offer_carta_contact';
    private const NONCE_NAME = 'flacso_offer_carta_contact_nonce';
    private const PRESERVE_VALUE = '__preserve__';

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);

        if (is_admin()) {
            add_action('save_post_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'save'], 20, 2);
        }
    }

    public static function register_meta(): void {
        register_post_meta(FLACSO_Oferta_Academica::POST_TYPE, self::META_PERSON_ID, [
            'single' => true,
            'type' => 'integer',
            'show_in_rest' => false,
            'sanitize_callback' => [self::class, 'sanitize_person_id'],
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
        ]);
        register_post_meta(FLACSO_Oferta_Academica::POST_TYPE, self::META_TITLE, [
            'single' => true,
            'type' => 'string',
            'show_in_rest' => false,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
        ]);
        register_post_meta(FLACSO_Oferta_Academica::POST_TYPE, self::META_EMAIL, [
            'single' => true,
            'type' => 'string',
            'show_in_rest' => false,
            'sanitize_callback' => 'sanitize_email',
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
        ]);
    }

    public static function sanitize_person_id($value): int {
        $id = absint($value);
        return $id > 0 && get_post_type($id) === 'docente' ? $id : 0;
    }

    /**
     * Valida el contacto contra Coordinación académica.
     *
     * Si se reciben los grupos enviados por el formulario, se validan esos
     * valores para permitir agregar un coordinador y elegirlo como contacto en
     * el mismo guardado.
     *
     * @param mixed $submitted_groups
     */
    public static function is_valid_contact_person(int $offer_id, int $person_id, $submitted_groups = null): bool {
        $person_id = self::sanitize_person_id($person_id);
        if ($offer_id < 1 || $person_id < 1) {
            return false;
        }

        $member_ids = $submitted_groups !== null
            ? FLACSO_Academic_Team_Editor::coordination_member_ids_from_groups($submitted_groups)
            : FLACSO_Academic_Team_Editor::offer_coordination_member_ids($offer_id);

        return in_array($person_id, $member_ids, true);
    }

    /** @return WP_Post[] */
    private static function coordination_people(int $offer_id): array {
        $people = [];
        foreach (FLACSO_Academic_Team_Editor::offer_coordination_member_ids($offer_id) as $person_id) {
            $person = get_post($person_id);
            if ($person && $person->post_type === 'docente') {
                $people[] = $person;
            }
        }
        return $people;
    }

    private static function person_email(int $id): string {
        return $id > 0 ? sanitize_email((string) get_post_meta($id, 'correo', true)) : '';
    }

    private static function person_avatar(int $id): string {
        return $id > 0 ? (string) (get_the_post_thumbnail_url($id, 'thumbnail') ?: '') : '';
    }

    private static function error_key(int $post_id): string {
        return 'flacso_carta_contact_error_' . get_current_user_id() . '_' . $post_id;
    }

    private static function remember_error(int $post_id, string $message): void {
        set_transient(self::error_key($post_id), $message, MINUTE_IN_SECONDS);
    }

    private static function consume_error(int $post_id): string {
        $key = self::error_key($post_id);
        $message = (string) get_transient($key);
        if ($message !== '') {
            delete_transient($key);
        }
        return $message;
    }

    public static function render_section(WP_Post $post): void {
        $person_id = absint(get_post_meta($post->ID, self::META_PERSON_ID, true));
        $title = trim((string) get_post_meta($post->ID, self::META_TITLE, true));
        $email_override = sanitize_email((string) get_post_meta($post->ID, self::META_EMAIL, true));
        $people = self::coordination_people((int) $post->ID);
        $coordination_ids = array_map(static fn(WP_Post $person): int => (int) $person->ID, $people);
        $current_is_valid = $person_id > 0 && in_array($person_id, $coordination_ids, true);

        $selected = $person_id > 0 ? get_post($person_id) : null;
        $selected_name = $selected && $selected->post_type === 'docente' ? get_the_title($selected) : '';
        $selected_email = $email_override !== '' ? $email_override : self::person_email($person_id);
        $selected_avatar = self::person_avatar($person_id);
        $error = self::consume_error((int) $post->ID);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        FLACSO_Academic_Admin_UI::section_start(
            'flacso-oferta-contacto-carta',
            __('Contacto de la carta', 'flacso-uruguay'),
            __('Persona de Coordinación académica que se muestra como contacto, con título y correo específicos de la Oferta.', 'flacso-uruguay'),
            'dashicons-id',
            $selected_name !== '' ? $selected_name : __('Sin completar', 'flacso-uruguay')
        );
        ?>
        <div
            class="flacso-carta-contact"
            id="flacso-offer-carta-contact"
            data-saved-person-id="<?php echo esc_attr((string) $person_id); ?>"
            data-saved-person-name="<?php echo esc_attr($selected_name); ?>"
            data-saved-person-email="<?php echo esc_attr(self::person_email($person_id)); ?>"
            data-saved-person-avatar="<?php echo esc_attr($selected_avatar); ?>"
        >
            <?php if ($error !== '') : ?>
                <div class="notice notice-error inline flacso-carta-contact__error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php if ($person_id > 0 && !$current_is_valid) : ?>
                <div class="flacso-carta-contact__warning">
                    <?php esc_html_e('El contacto guardado actualmente no integra Coordinación académica. Se conserva sin cambios hasta que elijas un integrante válido o limpies el contacto.', 'flacso-uruguay'); ?>
                </div>
            <?php endif; ?>

            <div class="flacso-carta-contact__layout">
                <div class="flacso-carta-contact__fields">
                    <div class="flacso-carta-contact__field">
                        <label for="flacso-carta-contact-person"><?php esc_html_e('Persona que aparece', 'flacso-uruguay'); ?></label>
                        <select id="flacso-carta-contact-person" name="flacso_carta_contact[person_id]">
                            <option value=""><?php esc_html_e('— Sin contacto específico —', 'flacso-uruguay'); ?></option>
                            <?php if ($person_id > 0 && !$current_is_valid) : ?>
                                <option value="<?php echo esc_attr(self::PRESERVE_VALUE); ?>" selected>
                                    <?php echo esc_html(sprintf(__('Conservar contacto actual: %s', 'flacso-uruguay'), $selected_name !== '' ? $selected_name : ('#' . $person_id))); ?>
                                </option>
                            <?php endif; ?>
                            <?php foreach ($people as $person) : ?>
                                <option
                                    value="<?php echo esc_attr((string) $person->ID); ?>"
                                    data-name="<?php echo esc_attr(get_the_title($person)); ?>"
                                    data-email="<?php echo esc_attr(self::person_email((int) $person->ID)); ?>"
                                    data-avatar="<?php echo esc_attr(self::person_avatar((int) $person->ID)); ?>"
                                    <?php selected($current_is_valid ? $person_id : 0, (int) $person->ID); ?>
                                ><?php echo esc_html(get_the_title($person)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="flacso-carta-contact__help"><?php esc_html_e('El selector incluye únicamente integrantes de Coordinación académica. Si modificás ese grupo arriba, la lista se actualiza en esta misma pantalla.', 'flacso-uruguay'); ?></p>
                    </div>

                    <div class="flacso-carta-contact__field">
                        <label for="flacso-carta-contact-title"><?php esc_html_e('Título que se muestra', 'flacso-uruguay'); ?></label>
                        <input id="flacso-carta-contact-title" type="text" name="flacso_carta_contact[title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Ej.: Coordinación académica', 'flacso-uruguay'); ?>">
                        <p class="flacso-carta-contact__help"><?php esc_html_e('Solo cambia el título de la tarjeta de contacto de esta Oferta.', 'flacso-uruguay'); ?></p>
                    </div>

                    <div class="flacso-carta-contact__field">
                        <label for="flacso-carta-contact-email"><?php esc_html_e('Correo para esta oferta (opcional)', 'flacso-uruguay'); ?></label>
                        <input id="flacso-carta-contact-email" type="email" name="flacso_carta_contact[email]" value="<?php echo esc_attr($email_override); ?>" placeholder="<?php esc_attr_e('Si queda vacío, usa el correo de la persona', 'flacso-uruguay'); ?>">
                    </div>
                </div>

                <div>
                    <div class="flacso-carta-contact__preview" aria-live="polite">
                        <p class="flacso-carta-contact__preview-title"><?php esc_html_e('Vista previa del contacto', 'flacso-uruguay'); ?></p>
                        <div class="flacso-carta-contact__person">
                            <div class="flacso-carta-contact__avatar" data-contact-avatar>
                                <?php if ($selected_avatar !== '') : ?><img src="<?php echo esc_url($selected_avatar); ?>" alt=""><?php else : ?><span><?php echo esc_html($selected_name !== '' ? mb_substr($selected_name, 0, 1) : 'I'); ?></span><?php endif; ?>
                            </div>
                            <div>
                                <span class="flacso-carta-contact__badge" data-contact-role><?php echo esc_html($title !== '' ? $title : __('Coordinación académica', 'flacso-uruguay')); ?></span>
                                <div class="flacso-carta-contact__name" data-contact-name><?php echo esc_html($selected_name !== '' ? $selected_name : __('Sin contacto específico', 'flacso-uruguay')); ?></div>
                                <div class="flacso-carta-contact__email" data-contact-email><?php echo esc_html($selected_email !== '' ? $selected_email : __('Sin correo específico', 'flacso-uruguay')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        FLACSO_Academic_Admin_UI::section_end();
    }

    public static function save(int $post_id, WP_Post $post): void {
        if (
            $post->post_type !== FLACSO_Oferta_Academica::POST_TYPE
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if (!isset($_POST['flacso_carta_contact']) || !is_array($_POST['flacso_carta_contact'])) {
            return;
        }

        $payload = wp_unslash($_POST['flacso_carta_contact']);
        $raw_person = (string) ($payload['person_id'] ?? '');

        if ($raw_person === self::PRESERVE_VALUE) {
            return;
        }

        if ($raw_person === '') {
            delete_post_meta($post_id, self::META_PERSON_ID);
            delete_post_meta($post_id, self::META_TITLE);
            delete_post_meta($post_id, self::META_EMAIL);
            return;
        }

        $person_id = self::sanitize_person_id($raw_person);
        $submitted_groups = isset($_POST['flacso_oferta_equipos']) && is_array($_POST['flacso_oferta_equipos'])
            ? wp_unslash($_POST['flacso_oferta_equipos'])
            : null;

        if ($person_id < 1 || !self::is_valid_contact_person($post_id, $person_id, $submitted_groups)) {
            self::remember_error(
                $post_id,
                __('No se guardó el contacto de la carta porque la persona seleccionada no integra Coordinación académica. Se conservó el contacto previamente guardado.', 'flacso-uruguay')
            );
            return;
        }

        self::save_or_delete($post_id, self::META_PERSON_ID, $person_id);
        self::save_or_delete($post_id, self::META_TITLE, sanitize_text_field((string) ($payload['title'] ?? '')));
        self::save_or_delete($post_id, self::META_EMAIL, sanitize_email((string) ($payload['email'] ?? '')));
    }

    private static function save_or_delete(int $post_id, string $key, $value): void {
        if ($value === '' || $value === 0) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }

    public static function render_styles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== FLACSO_Oferta_Academica::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .flacso-carta-contact{display:grid;gap:14px}
            .flacso-carta-contact__layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.7fr);gap:20px;align-items:start}
            .flacso-carta-contact__fields{display:grid;gap:16px}
            .flacso-carta-contact__field label{display:block;margin-bottom:6px;font-weight:600}
            .flacso-carta-contact__field select,.flacso-carta-contact__field input{width:100%;max-width:none;min-height:40px}
            .flacso-carta-contact__help{margin:5px 0 0;color:#646970;line-height:1.4}
            .flacso-carta-contact__preview{border:1px solid #c3c4c7;border-top:4px solid #2c4777;border-radius:10px;padding:18px;background:#fff;box-shadow:0 3px 12px rgba(0,0,0,.05)}
            .flacso-carta-contact__preview-title{margin:0 0 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#646970}
            .flacso-carta-contact__person{display:flex;gap:14px;align-items:center}
            .flacso-carta-contact__avatar{width:68px;height:68px;border-radius:9px;background:#2c4777;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;overflow:hidden;flex:none}
            .flacso-carta-contact__avatar img{width:100%;height:100%;object-fit:cover}
            .flacso-carta-contact__badge{display:inline-block;margin-bottom:5px;padding:3px 8px;border-radius:999px;background:#f0f4fb;color:#2c4777;font-size:11px;font-weight:700}
            .flacso-carta-contact__name{font-size:17px;font-weight:700;color:#1d2327}
            .flacso-carta-contact__email{margin-top:4px;color:#50575e;font-size:13px;word-break:break-word}
            .flacso-carta-contact__warning{padding:10px 12px;border-left:4px solid #dba617;background:#fcf9e8;color:#3c434a}
            .flacso-carta-contact__error{margin:0}
            @media(max-width:900px){.flacso-carta-contact__layout{grid-template-columns:1fr}.flacso-carta-contact__preview{order:-1}}
        </style>
        <?php
    }

    public static function render_script(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== FLACSO_Oferta_Academica::POST_TYPE) {
            return;
        }
        ?>
        <script>
        (function(){
            var root=document.getElementById('flacso-offer-carta-contact');
            if(!root){ return; }

            var select=root.querySelector('#flacso-carta-contact-person');
            var title=root.querySelector('#flacso-carta-contact-title');
            var email=root.querySelector('#flacso-carta-contact-email');
            var nameOut=root.querySelector('[data-contact-name]');
            var roleOut=root.querySelector('[data-contact-role]');
            var emailOut=root.querySelector('[data-contact-email]');
            var avatarOut=root.querySelector('[data-contact-avatar]');
            var savedId=root.getAttribute('data-saved-person-id')||'';
            var savedName=root.getAttribute('data-saved-person-name')||'';
            var savedEmail=root.getAttribute('data-saved-person-email')||'';
            var savedAvatar=root.getAttribute('data-saved-person-avatar')||'';

            function escapeHtml(value){
                return String(value).replace(/[&<>"']/g,function(char){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
                });
            }

            function coordinationCandidates(){
                var teamEditor=document.querySelector('[data-flacso-team-editor="offer"]');
                var group=teamEditor&&teamEditor.querySelector('[data-team-group][data-team-type="coordinacion_academica"]');
                if(!group){ return []; }

                var seen={};
                var result=[];
                group.querySelectorAll('[data-member-row] select[name$="[id]"]').forEach(function(memberSelect){
                    var option=memberSelect.options[memberSelect.selectedIndex];
                    var id=memberSelect.value;
                    if(!id||seen[id]){ return; }
                    seen[id]=true;
                    result.push({
                        id:id,
                        name:(option&&option.dataset.name)||option.textContent||'',
                        email:(option&&option.dataset.email)||'',
                        avatar:(option&&option.dataset.avatar)||''
                    });
                });
                return result;
            }

            function rebuildCandidates(){
                var previous=select.value;
                var candidates=coordinationCandidates();
                var hasSaved=candidates.some(function(candidate){ return candidate.id===savedId; });
                var hasPrevious=candidates.some(function(candidate){ return candidate.id===previous; });

                select.innerHTML='';
                var empty=document.createElement('option');
                empty.value='';
                empty.textContent='<?php echo esc_js(__('— Sin contacto específico —', 'flacso-uruguay')); ?>';
                select.appendChild(empty);

                candidates.forEach(function(candidate){
                    var option=document.createElement('option');
                    option.value=candidate.id;
                    option.textContent=candidate.name;
                    option.dataset.name=candidate.name;
                    option.dataset.email=candidate.email;
                    option.dataset.avatar=candidate.avatar;
                    select.appendChild(option);
                });

                if(hasPrevious){
                    select.value=previous;
                }else if(savedId&&hasSaved){
                    select.value=savedId;
                }else if(savedId){
                    var preserve=document.createElement('option');
                    preserve.value='<?php echo esc_js(self::PRESERVE_VALUE); ?>';
                    preserve.textContent='<?php echo esc_js(__('Conservar contacto actual', 'flacso-uruguay')); ?>'+(savedName?' — '+savedName:'');
                    select.appendChild(preserve);
                    select.value=preserve.value;
                }else{
                    select.value='';
                }

                updatePreview();
            }

            function updatePreview(){
                var option=select.options[select.selectedIndex];
                var preserve=select.value==='<?php echo esc_js(self::PRESERVE_VALUE); ?>';
                var hasPerson=Boolean(select.value)&&!preserve;
                var name=preserve?savedName:((option&&option.dataset.name)||'<?php echo esc_js(__('Sin contacto específico', 'flacso-uruguay')); ?>');
                var personEmail=preserve?savedEmail:((option&&option.dataset.email)||'');
                var avatar=preserve?savedAvatar:((option&&option.dataset.avatar)||'');

                nameOut.textContent=name||'<?php echo esc_js(__('Sin contacto específico', 'flacso-uruguay')); ?>';
                roleOut.textContent=title.value.trim()||'<?php echo esc_js(__('Coordinación académica', 'flacso-uruguay')); ?>';
                emailOut.textContent=(email.value.trim()||personEmail||'<?php echo esc_js(__('Sin correo específico', 'flacso-uruguay')); ?>');
                avatarOut.innerHTML=avatar
                    ? '<img src="'+escapeHtml(avatar)+'" alt="">'
                    : '<span>'+escapeHtml((name.trim().charAt(0)||'I').toUpperCase())+'</span>';

                title.disabled=preserve;
                email.disabled=preserve;
            }

            select.addEventListener('change',updatePreview);
            title.addEventListener('input',updatePreview);
            email.addEventListener('input',updatePreview);
            document.addEventListener('flacso:academic-team-changed',rebuildCandidates);

            rebuildCandidates();
        })();
        </script>
        <?php
    }
}
