<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contacto visible en la carta pública de una Oferta Académica.
 *
 * El contacto se elige dentro de Coordinación académica (editor de equipos
 * estables): se usa el integrante marcado, su rol y su correo. Este objeto solo
 * registra los metas canónicos que lee la carta y los sincroniza al guardar.
 */
final class FLACSO_Offer_Carta_Contact_Admin {
    public const META_PERSON_ID = 'carta_contacto_docente_id';
    public const META_TITLE = 'carta_contacto_titulo';
    public const META_EMAIL = 'carta_contacto_correo';

    /** @var array<int,bool> */
    private static array $completed_saves = [];

    public static function init(): void {
        add_action('init', [self::class, 'register_meta'], 12);
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

    public static function did_complete_save(int $post_id): bool {
        return !empty(self::$completed_saves[$post_id]);
    }

    /**
     * Deriva los metas de la carta desde el integrante de Coordinación marcado
     * como contacto: persona, rol (título que se muestra) y correo.
     *
     * Sin integrante marcado, el contacto guardado solo se limpia si se pidió
     * explícitamente o si ya pertenecía a Coordinación. Un contacto histórico
     * ajeno a Coordinación se conserva hasta que se elija otro.
     *
     * @param mixed $groups Grupos de Oferta ya normalizados o enviados por el formulario.
     */
    public static function sync_from_groups(int $post_id, $groups, bool $clear_requested = false): void {
        $member = FLACSO_Academic_Team_Editor::coordination_contact_member($groups);
        $person_id = $member ? self::sanitize_person_id($member['id'] ?? 0) : 0;

        if ($person_id > 0) {
            self::save_or_delete($post_id, self::META_PERSON_ID, $person_id);
            self::save_or_delete($post_id, self::META_TITLE, sanitize_text_field((string) ($member['rol'] ?? '')));
            self::save_or_delete($post_id, self::META_EMAIL, sanitize_email((string) ($member['correo'] ?? '')));
            self::$completed_saves[$post_id] = true;
            return;
        }

        $saved_id = absint(get_post_meta($post_id, self::META_PERSON_ID, true));
        $saved_in_coordination = $saved_id > 0
            && in_array($saved_id, FLACSO_Academic_Team_Editor::coordination_member_ids_from_groups($groups), true);

        if ($clear_requested || $saved_in_coordination) {
            delete_post_meta($post_id, self::META_PERSON_ID);
            delete_post_meta($post_id, self::META_TITLE);
            delete_post_meta($post_id, self::META_EMAIL);
            self::$completed_saves[$post_id] = true;
        }
    }

    private static function save_or_delete(int $post_id, string $key, $value): void {
        if ($value === '' || $value === 0) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}
