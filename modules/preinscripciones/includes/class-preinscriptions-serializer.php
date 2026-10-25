<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class FLACSO_Preinscriptions_Serializer {
    public static function all_targets(): array {
        $targets = [];

        // 1. Cohortes
        $cohort_posts = get_posts([
            'post_type'      => 'cohorte',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        self::prime_parent_caches($cohort_posts, defined('FLACSO_Cohorte::META_PARENT_ID')
            ? FLACSO_Cohorte::META_PARENT_ID
            : 'oferta_academica_id');

        foreach ($cohort_posts as $post) {
            $serialized = self::for_cohort((int) $post->ID);
            if ($serialized !== null) {
                $targets[] = $serialized;
            }
        }
        $targets = self::resolve_cohort_availability($targets);

        // 2. Ediciones de Seminario
        $edition_posts = get_posts([
            'post_type'      => 'edicion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        self::prime_parent_caches($edition_posts, defined('FLACSO_Edicion::META_PARENT_ID')
            ? FLACSO_Edicion::META_PARENT_ID
            : 'seminario_id');

        // La preinscripción de un Seminario Integrado corresponde al integrado,
        // no a sus ediciones componentes individuales.
        $component_edition_ids = [];
        if (class_exists('FLACSO_Seminario_Integrado')) {
            $parent_key = defined('FLACSO_Edicion::META_PARENT_ID')
                ? FLACSO_Edicion::META_PARENT_ID
                : 'seminario_id';
            foreach ($edition_posts as $post) {
                $edition_id = (int) $post->ID;
                $seminar_id = (int) get_post_meta($edition_id, $parent_key, true);
                if ($seminar_id > 0 && FLACSO_Seminario_Integrado::is_integrated($seminar_id)) {
                    foreach (FLACSO_Seminario_Integrado::component_edition_ids($edition_id) as $comp_id) {
                        $component_edition_ids[(int) $comp_id] = true;
                    }
                }
            }
        }

        $edition_targets = [];
        foreach ($edition_posts as $post) {
            if (isset($component_edition_ids[(int) $post->ID])) {
                continue;
            }
            $serialized = self::for_edition((int) $post->ID);
            if ($serialized !== null) {
                $edition_targets[] = $serialized;
            }
        }

        $targets = array_merge($targets, self::resolve_edition_availability($edition_targets));

        return $targets;
    }

    public static function for_cohort(int $cohort_id): ?array {
        $cohort = get_post($cohort_id);
        if (!$cohort || $cohort->post_status !== 'publish') {
            return null;
        }

        $parent_key = defined('FLACSO_Cohorte::META_PARENT_ID')
            ? FLACSO_Cohorte::META_PARENT_ID
            : 'oferta_academica_id';
        $parent_id = (int) get_post_meta($cohort_id, $parent_key, true);
        $parent = $parent_id > 0 ? get_post($parent_id) : null;
        if (!$parent) {
            return null;
        }

        $is_open = class_exists('FLACSO_Cohorte')
            ? FLACSO_Cohorte::accepts_registration($cohort_id)
            : false;

        $offer_type = '';
        if (class_exists('FLACSO_Oferta_Academica') && method_exists('FLACSO_Oferta_Academica', 'get_tipo')) {
            $offer_type = FLACSO_Oferta_Academica::get_tipo((int) $parent->ID);
        }
        if ($offer_type === '' && function_exists('wp_get_object_terms')) {
            $terms = wp_get_object_terms((int) $parent->ID, 'tipo-oferta-academica');
            if (!is_wp_error($terms) && !empty($terms) && isset($terms[0]->slug)) {
                $offer_type = sanitize_key((string) $terms[0]->slug);
            }
        }
        if ($offer_type === '') {
            $offer_type = sanitize_key((string) get_post_meta((int) $parent->ID, 'tipo', true));
        }

        // Destinatarios de notificaciones: "Correo de la Oferta Académica", nunca el correo de contacto.
        $notification_emails = class_exists('FLACSO_Oferta_Academica') && method_exists('FLACSO_Oferta_Academica', 'get_offer_emails')
            ? FLACSO_Oferta_Academica::get_offer_emails((int) $parent->ID)
            : [];

        $raw_orientations = get_post_meta((int) $parent->ID, 'orientaciones', true);
        $clean_orientations = FLACSO_Preinscriptions_Config::sanitize_text_list($raw_orientations);

        $raw_mentions = get_post_meta((int) $parent->ID, 'menciones', true);
        $clean_mentions = FLACSO_Preinscriptions_Config::sanitize_text_list($raw_mentions);

        $legacy_link = get_post_meta($cohort_id, 'link_preinscripcion', true);
        $public_url = get_permalink((int) $parent->ID);
        $wordpress_url = get_permalink($cohort_id);
        $edit_url = admin_url('post.php?post=' . $cohort_id . '&action=edit');

        $canonical = [
            'kind'               => 'academic_offer',
            'type'               => $offer_type,
            'orientations'       => $clean_orientations,
            'mentions'           => $clean_mentions,
            'registrationOpen'   => $is_open,
            // Las cohortes no tienen ventana de fechas: se abren y cierran manualmente.
            'registrationWindow' => [
                'from'  => null,
                'until' => null,
            ],
            'urls'               => [
                'public'             => $public_url,
                'wordpress'          => $wordpress_url,
                'legacyRegistration' => !empty($legacy_link) ? (string) $legacy_link : null,
            ],
        ];
        $revision = FLACSO_Preinscriptions_Config::revision($canonical);

        $target_id = sprintf('target_%s', substr(hash('sha256', 'cohorte:' . $cohort_id), 0, 12));
        $cohort_number = get_post_meta($cohort_id, 'numero', true);
        $cohort_name = get_post_meta($cohort_id, 'nombre', true);
        $offer_abbreviation = get_post_meta((int) $parent->ID, 'abreviacion', true);
        $cohort_title = trim((string) $cohort->post_title);
        $is_generic_cohort_title = $cohort_title !== '' && preg_match('/^cohorte(?:\\s|$)/ui', $cohort_title) === 1;
        $public_title = $cohort_title;
        if ($public_title === '' || $is_generic_cohort_title) {
            $public_title = trim((string) $parent->post_title) . ' — ' . ($cohort_title ?: ($cohort_name ?: ('Cohorte ' . $cohort_number)));
        }

        return [
            'id'                 => $target_id,
            'kind'               => 'academic_offer',
            'title'              => $public_title,
            'notificationEmail'  => $notification_emails[0] ?? null,
            'notificationEmails' => $notification_emails,
            'wordpress'          => [
                'offerId'  => (int) $parent->ID,
                'cohortId' => $cohort_id,
            ],
            'offer'              => [
                'id'           => (int) $parent->ID,
                'slug'         => (string) $parent->post_name,
                'name'         => (string) ($offer_abbreviation ?: $parent->post_title),
                // Código estable de la oferta (tags de Mautic); vacío si no se cargó.
                'abbreviation' => (string) $offer_abbreviation,
                'type'         => $offer_type,
                'orientations' => $clean_orientations,
                'mentions'     => $clean_mentions,
            ],
            'cohort'             => [
                'number' => is_numeric($cohort_number) ? (int) $cohort_number : $cohort_number,
                'name'   => (string) ($cohort_name ?: ('Cohorte ' . $cohort_number)),
            ],
            'registrationOpen'   => $is_open,
            'registrationAvailability' => $is_open ? 'open' : 'closed',
            'registrationWindow' => [
                'from'  => null,
                'until' => null,
            ],
            'configRevision'     => $revision,
            'orientations'       => $clean_orientations,
            'mentions'           => $clean_mentions,
            'urls'               => [
                'public'             => $public_url,
                'wordpress'          => $wordpress_url,
                'edit'               => $edit_url,
                'legacyRegistration' => !empty($legacy_link) ? (string) $legacy_link : null,
            ],
        ];
    }

    public static function for_edition(int $edition_id): ?array {
        $edition = get_post($edition_id);
        if (!$edition || $edition->post_status !== 'publish') {
            return null;
        }

        $parent_key = defined('FLACSO_Edicion::META_PARENT_ID')
            ? FLACSO_Edicion::META_PARENT_ID
            : 'seminario_id';
        $parent_id = (int) get_post_meta($edition_id, $parent_key, true);
        $parent = $parent_id > 0 ? get_post($parent_id) : null;
        if (!$parent) {
            return null;
        }

        $availability = class_exists('FLACSO_Edicion')
            ? FLACSO_Edicion::registration_availability($edition_id)
            : [
                'status' => 'closed',
                'from' => null,
                'until' => null,
            ];

        // Destinatarios de notificaciones: "Correo del Seminario", nunca el correo de contacto.
        $notification_emails = class_exists('FLACSO_Seminario') && method_exists('FLACSO_Seminario', 'get_notification_emails')
            ? FLACSO_Seminario::get_notification_emails((int) $parent->ID)
            : [];

        $legacy_link = get_post_meta($edition_id, 'link_preinscripcion', true);
        $public_url = get_permalink((int) $parent->ID);
        $wordpress_url = get_permalink($edition_id);
        $edit_url = admin_url('post.php?post=' . $edition_id . '&action=edit');

        $canonical = [
            'kind'               => 'seminar',
            'type'               => 'seminar',
            'orientations'       => [],
            'mentions'           => [],
            'registrationOpen'   => $availability['status'] === 'open',
            'registrationAvailability' => $availability['status'],
            'registrationWindow' => [
                'from'  => $availability['from'] ?? null,
                'until' => $availability['until'] ?? null,
            ],
            'urls'               => [
                'public'             => $public_url,
                'wordpress'          => $wordpress_url,
                'legacyRegistration' => !empty($legacy_link) ? (string) $legacy_link : null,
            ],
        ];
        $revision = FLACSO_Preinscriptions_Config::revision($canonical);

        $target_id = sprintf('target_%s', substr(hash('sha256', 'edicion:' . $edition_id), 0, 12));
        $edition_number = get_post_meta($edition_id, 'numero', true);
        $edition_name = get_post_meta($edition_id, 'nombre', true);

        return [
            'id'                 => $target_id,
            'kind'               => 'seminar',
            'title'              => $edition->post_title ?: ($parent->post_title . ' - Edición ' . $edition_number),
            'notificationEmail'  => $notification_emails[0] ?? null,
            'notificationEmails' => $notification_emails,
            'wordpress'          => [
                'seminarId' => (int) $parent->ID,
                'editionId' => $edition_id,
            ],
            'seminar'            => [
                'id'   => (int) $parent->ID,
                'slug' => (string) $parent->post_name,
                'name' => (string) $parent->post_title,
                'type' => 'seminar',
            ],
            'edition'            => [
                'number' => is_numeric($edition_number) ? (int) $edition_number : $edition_number,
                'name'   => (string) ($edition_name ?: ('Edición ' . $edition_number)),
            ],
            'registrationOpen'   => $availability['status'] === 'open',
            'registrationAvailability' => $availability['status'],
            'registrationWindow' => [
                'from'  => $availability['from'] ?? null,
                'until' => $availability['until'] ?? null,
            ],
            'configRevision'     => $revision,
            'orientations'       => [],
            'mentions'           => [],
            'urls'               => [
                'public'             => $public_url,
                'wordpress'          => $wordpress_url,
                'edit'               => $edit_url,
                'legacyRegistration' => !empty($legacy_link) ? (string) $legacy_link : null,
            ],
        ];
    }

    /**
     * Una misma oferta no puede tener dos cohortes abiertas detrás del mismo enlace
     * de preinscripción: el portal no sabría cuál mostrar.
     */
    private static function resolve_cohort_availability(array $cohort_targets): array {
        $open_by_link = [];
        foreach ($cohort_targets as $index => $target) {
            $path = self::registration_path($target['urls']['legacyRegistration'] ?? null);
            if ($path !== null && ($target['registrationAvailability'] ?? null) === 'open') {
                $open_by_link[(int) ($target['wordpress']['offerId'] ?? 0) . '|' . $path][] = $index;
            }
        }

        foreach ($open_by_link as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            foreach ($indexes as $index) {
                $cohort_targets[$index]['registrationOpen'] = false;
                $cohort_targets[$index]['registrationAvailability'] = 'conflict';
                $cohort_targets[$index] = self::refresh_target_revision($cohort_targets[$index]);
            }
        }

        return $cohort_targets;
    }

    private static function resolve_edition_availability(array $edition_targets): array {
        $groups = [];
        foreach ($edition_targets as $index => $target) {
            $seminar_id = (int) ($target['wordpress']['seminarId'] ?? 0);
            if ($seminar_id > 0) {
                $groups[$seminar_id][] = $index;
            }
        }

        foreach ($groups as $indexes) {
            $paths = [];
            $open_indexes = [];
            foreach ($indexes as $index) {
                $target = $edition_targets[$index];
                $path = self::registration_path($target['urls']['legacyRegistration'] ?? null);
                if ($path !== null) {
                    $paths[$path] = true;
                }
                if (($target['registrationAvailability'] ?? null) === 'open') {
                    $open_indexes[] = $index;
                }
            }

            $has_conflict = count($paths) > 1 || count($open_indexes) > 1;
            foreach ($indexes as $index) {
                if ($has_conflict) {
                    $edition_targets[$index]['registrationOpen'] = false;
                    $edition_targets[$index]['registrationAvailability'] = 'conflict';
                    $edition_targets[$index] = self::refresh_target_revision($edition_targets[$index]);
                    continue;
                }

                $edition_targets[$index]['registrationOpen'] = in_array($index, $open_indexes, true);
                $edition_targets[$index]['registrationAvailability'] = $edition_targets[$index]['registrationOpen']
                    ? 'open'
                    : 'closed';
                $edition_targets[$index] = self::refresh_target_revision($edition_targets[$index]);
            }
        }

        return $edition_targets;
    }

    /**
     * Hidrata en bloque posts y metadatos de los padres (ofertas/seminarios) para
     * evitar una consulta por cada cohorte o edición al serializar el catálogo.
     */
    private static function prime_parent_caches(array $posts, string $parent_key): void {
        if (!function_exists('_prime_post_caches')) {
            return;
        }

        $parent_ids = [];
        foreach ($posts as $post) {
            $parent_id = (int) get_post_meta((int) $post->ID, $parent_key, true);
            if ($parent_id > 0) {
                $parent_ids[$parent_id] = $parent_id;
            }
        }

        if ($parent_ids !== []) {
            _prime_post_caches(array_values($parent_ids), true, true);
        }
    }

    private static function refresh_target_revision(array $target): array {
        $is_seminar = ($target['kind'] ?? '') === 'seminar';
        $canonical = [
            'kind'               => $target['kind'] ?? '',
            'type'               => $is_seminar ? 'seminar' : ($target['offer']['type'] ?? ''),
            'orientations'       => $target['orientations'] ?? [],
            'mentions'           => $target['mentions'] ?? [],
            'registrationOpen'   => (bool) ($target['registrationOpen'] ?? false),
            'registrationWindow' => [
                'from'  => $target['registrationWindow']['from'] ?? null,
                'until' => $target['registrationWindow']['until'] ?? null,
            ],
            'urls'               => [
                'public'             => $target['urls']['public'] ?? null,
                'wordpress'          => $target['urls']['wordpress'] ?? null,
                'legacyRegistration' => $target['urls']['legacyRegistration'] ?? null,
            ],
        ];
        if ($is_seminar) {
            $canonical['registrationAvailability'] = $target['registrationAvailability'] ?? 'closed';
        }
        $target['configRevision'] = FLACSO_Preinscriptions_Config::revision($canonical);
        return $target;
    }

    private static function registration_path($url): ?string {
        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . trim($path, '/') . '/';
    }
}
