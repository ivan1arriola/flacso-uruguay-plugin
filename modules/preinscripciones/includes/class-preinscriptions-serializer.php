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

        foreach ($cohort_posts as $post) {
            $serialized = self::for_cohort((int) $post->ID);
            if ($serialized !== null) {
                $targets[] = $serialized;
            }
        }

        // 2. Ediciones de Seminario
        $edition_posts = get_posts([
            'post_type'      => 'edicion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        $edition_targets = [];
        foreach ($edition_posts as $post) {
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

        $raw_orientations = get_post_meta((int) $parent->ID, 'orientaciones', true);
        $clean_orientations = FLACSO_Preinscriptions_Config::sanitize_text_list($raw_orientations);

        $raw_mentions = get_post_meta((int) $parent->ID, 'menciones', true);
        $clean_mentions = FLACSO_Preinscriptions_Config::sanitize_text_list($raw_mentions);

        $fecha_limite = get_post_meta($cohort_id, 'fecha_limite_preinscripcion', true);
        $iso_until = null;
        if (!empty($fecha_limite) && is_string($fecha_limite)) {
            $ts = strtotime($fecha_limite);
            if ($ts !== false) {
                $iso_until = date('c', $ts);
            }
        }

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
            'registrationWindow' => [
                'from'  => null,
                'until' => $iso_until,
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
        $offer_sigla = get_post_meta((int) $parent->ID, 'sigla', true);
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
            'wordpress'          => [
                'offerId'  => (int) $parent->ID,
                'cohortId' => $cohort_id,
            ],
            'offer'              => [
                'id'           => (int) $parent->ID,
                'slug'         => (string) $parent->post_name,
                'name'         => (string) ($offer_sigla ?: $parent->post_title),
                'type'         => $offer_type,
                'orientations' => $clean_orientations,
                'mentions'     => $clean_mentions,
            ],
            'cohort'             => [
                'number' => is_numeric($cohort_number) ? (int) $cohort_number : $cohort_number,
                'name'   => (string) ($cohort_name ?: ('Cohorte ' . $cohort_number)),
            ],
            'registrationOpen'   => $is_open,
            'registrationWindow' => [
                'from'  => null,
                'until' => $iso_until,
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
                    continue;
                }

                $edition_targets[$index]['registrationOpen'] = in_array($index, $open_indexes, true);
                $edition_targets[$index]['registrationAvailability'] = $edition_targets[$index]['registrationOpen']
                    ? 'open'
                    : 'closed';
            }
        }

        return $edition_targets;
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
