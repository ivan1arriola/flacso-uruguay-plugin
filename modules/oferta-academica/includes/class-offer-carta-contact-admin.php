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

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);

        if (is_admin()) {
            add_action('add_meta_boxes_' . FLACSO_Oferta_Academica::POST_TYPE, [self::class, 'add_meta_box']);
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

    public static function add_meta_box(): void {
        add_meta_box(
            'flacso_offer_carta_contact',
            __('Contacto de la carta de presentación', 'flacso-uruguay'),
            [self::class, 'render'],
            FLACSO_Oferta_Academica::POST_TYPE,
            'normal',
            'high'
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

    private static function person_email(int $id): string {
        return $id > 0 ? sanitize_email((string) get_post_meta($id, 'correo', true)) : '';
    }

    private static function person_avatar(int $id): string {
        return $id > 0 ? (string) (get_the_post_thumbnail_url($id, 'thumbnail') ?: '') : '';
    }

    public static function render(WP_Post $post): void {
        $person_id = absint(get_post_meta($post->ID, self::META_PERSON_ID, true));
        $title = trim((string) get_post_meta($post->ID, self::META_TITLE, true));
        $email_override = sanitize_email((string) get_post_meta($post->ID, self::META_EMAIL, true));
        $people = self::people();
        $selected = $person_id > 0 ? get_post($person_id) : null;
        $selected_name = $selected && $selected->post_type === 'docente' ? get_the_title($selected) : '';
        $selected_email = $email_override !== '' ? $email_override : self::person_email($person_id);
        $selected_avatar = self::person_avatar($person_id);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <style>
            #flacso_offer_carta_contact .inside{padding:18px;margin:0;background:#f6f7f7}
            .flacso-carta-contact{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.7fr);gap:20px;align-items:start}
            .flacso-carta-contact__intro{margin:0 0 16px;color:#50575e;line-height:1.5}
            .flacso-carta-contact__fields{display:grid;gap:16px;padding:18px;border:1px solid #dcdcde;border-radius:10px;background:#fff}
            .flacso-carta-contact__field label{display:block;margin-bottom:6px;font-weight:600}
            .flacso-carta-contact__field select,.flacso-carta-contact__field input{width:100%;max-width:none;min-height:40px}
            .flacso-carta-contact__help{margin:5px 0 0;color:#646970;line-height:1.4}
            .flacso-carta-contact__preview{border:1px solid #c3c4c7;border-top:4px solid #25457d;border-radius:10px;padding:18px;background:#fff;box-shadow:0 3px 12px rgba(0,0,0,.05)}
            .flacso-carta-contact__preview-title{margin:0 0 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#646970}
            .flacso-carta-contact__person{display:flex;gap:14px;align-items:center}
            .flacso-carta-contact__avatar{width:68px;height:68px;border-radius:9px;background:#25457d;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;overflow:hidden;flex:none}
            .flacso-carta-contact__avatar img{width:100%;height:100%;object-fit:cover}
            .flacso-carta-contact__badge{display:inline-block;margin-bottom:5px;padding:3px 8px;border-radius:999px;background:#f0f4fb;color:#25457d;font-size:11px;font-weight:700}
            .flacso-carta-contact__name{font-size:17px;font-weight:700;color:#1d2327}
            .flacso-carta-contact__email{margin-top:4px;color:#50575e;font-size:13px;word-break:break-word}
            .flacso-carta-contact__fallback{margin-top:12px;padding:10px 12px;border-left:4px solid #72aee6;background:#f0f6fc;color:#3c434a;line-height:1.45}
            @media(max-width:900px){.flacso-carta-contact{grid-template-columns:1fr}.flacso-carta-contact__preview{order:-1}}
            @media(max-width:782px){#flacso_offer_carta_contact .inside{padding:12px}.flacso-carta-contact__fields,.flacso-carta-contact__preview{padding:14px}}
        </style>

        <p class="flacso-carta-contact__intro"><?php esc_html_e('Define quién aparece como contacto en la carta pública de esta Oferta Académica. Es un dato estable de la oferta y se mantiene entre cohortes.', 'flacso-uruguay'); ?></p>
        <div class="flacso-carta-contact">
            <div class="flacso-carta-contact__fields">
                <div class="flacso-carta-contact__field">
                    <label for="flacso-carta-contact-person"><?php esc_html_e('Persona que aparece', 'flacso-uruguay'); ?></label>
                    <select id="flacso-carta-contact-person" name="flacso_carta_contact[person_id]">
                        <option value=""><?php esc_html_e('— Usar contacto automático actual —', 'flacso-uruguay'); ?></option>
                        <?php foreach ($people as $person) : ?>
                            <option value="<?php echo esc_attr((string) $person->ID); ?>"
                                data-name="<?php echo esc_attr(get_the_title($person)); ?>"
                                data-email="<?php echo esc_attr(self::person_email((int) $person->ID)); ?>"
                                data-avatar="<?php echo esc_attr(self::person_avatar((int) $person->ID)); ?>"
                                <?php selected($person_id, (int) $person->ID); ?>><?php echo esc_html(get_the_title($person)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="flacso-carta-contact__help"><?php esc_html_e('Puede ser cualquier persona del CPT Personas / Equipo.', 'flacso-uruguay'); ?></p>
                </div>

                <div class="flacso-carta-contact__field">
                    <label for="flacso-carta-contact-title"><?php esc_html_e('Título que se muestra', 'flacso-uruguay'); ?></label>
                    <input id="flacso-carta-contact-title" type="text" name="flacso_carta_contact[title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Ej.: Asistencia Académica', 'flacso-uruguay'); ?>">
                    <p class="flacso-carta-contact__help"><?php esc_html_e('Solo cambia el título de esta tarjeta; no modifica el rol global de la persona.', 'flacso-uruguay'); ?></p>
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
                            <span class="flacso-carta-contact__badge" data-contact-role><?php echo esc_html($title !== '' ? $title : __('Asistencia Académica', 'flacso-uruguay')); ?></span>
                            <div class="flacso-carta-contact__name" data-contact-name><?php echo esc_html($selected_name !== '' ? $selected_name : __('Contacto automático', 'flacso-uruguay')); ?></div>
                            <div class="flacso-carta-contact__email" data-contact-email><?php echo esc_html($selected_email !== '' ? $selected_email : __('Se mantiene el correo actual', 'flacso-uruguay')); ?></div>
                        </div>
                    </div>
                </div>
                <div class="flacso-carta-contact__fallback" data-contact-fallback <?php echo $person_id > 0 ? 'hidden' : ''; ?>><?php esc_html_e('Mientras no selecciones una persona, la carta conserva exactamente el comportamiento actual. Las ofertas existentes no cambian automáticamente.', 'flacso-uruguay'); ?></div>
            </div>
        </div>

        <script>
        (() => {
            const root = document.getElementById('flacso_offer_carta_contact');
            if (!root) return;
            const select = root.querySelector('#flacso-carta-contact-person');
            const title = root.querySelector('#flacso-carta-contact-title');
            const email = root.querySelector('#flacso-carta-contact-email');
            const nameOut = root.querySelector('[data-contact-name]');
            const roleOut = root.querySelector('[data-contact-role]');
            const emailOut = root.querySelector('[data-contact-email]');
            const avatarOut = root.querySelector('[data-contact-avatar]');
            const fallback = root.querySelector('[data-contact-fallback]');
            const update = () => {
                const option = select.options[select.selectedIndex];
                const hasPerson = Boolean(select.value);
                const name = option?.dataset?.name || 'Contacto automático';
                const personEmail = option?.dataset?.email || '';
                const avatar = option?.dataset?.avatar || '';
                nameOut.textContent = name;
                roleOut.textContent = title.value.trim() || 'Asistencia Académica';
                emailOut.textContent = hasPerson ? (email.value.trim() || personEmail || 'Sin correo cargado') : 'Se mantiene el correo actual';
                fallback.hidden = hasPerson;
                avatarOut.innerHTML = avatar ? `<img src="${avatar.replace(/"/g, '&quot;')}" alt="">` : `<span>${(name.trim().charAt(0) || 'I').toUpperCase()}</span>`;
            };
            select.addEventListener('change', update);
            title.addEventListener('input', update);
            email.addEventListener('input', update);
        })();
        </script>
        <?php
    }

    public static function save(int $post_id, WP_Post $post): void {
        if ($post->post_type !== FLACSO_Oferta_Academica::POST_TYPE || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        $payload = isset($_POST['flacso_carta_contact']) && is_array($_POST['flacso_carta_contact']) ? wp_unslash($_POST['flacso_carta_contact']) : [];
        self::save_or_delete($post_id, self::META_PERSON_ID, self::sanitize_person_id($payload['person_id'] ?? 0));
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
}
