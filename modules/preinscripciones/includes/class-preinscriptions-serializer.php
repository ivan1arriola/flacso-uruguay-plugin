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

        foreach ($edition_posts as $post) {
            $serialized = self::for_edition((int) $post->ID);
            if ($serialized !== null) {
                $targets[] = $serialized;
            }
        }

        return $targets;
    }

    public static function for_cohort(int $cohort_id): ?array {
        $cohort = get_post($cohort_id);
        if (!$cohort || $cohort->post_status !== 'publish') {
            return null;
        }

        $parent_id = (int) get_post_meta($cohort_id, 'parent_oferta_id', true);
        if (!$parent_id) {
            $parent_id = (int) get_post_meta($cohort_id, '_parent_oferta_id', true);
        }
        $parent = $parent_id > 0 ? get_post($parent_id) : null;
        if (!$parent) {
            return null;
        }

        $is_open = class_exists('FLACSO_Cohorte')
            ? FLACSO_Cohorte::accepts_registration($cohort_id)
            : false;

        $raw_inputs = get_post_meta($cohort_id, 'preinscripcion_formulario', true);
        $issues = [];
        $has_invalid_keys = false;

        if (is_array($raw_inputs)) {
            foreach ($raw_inputs as $candidate) {
                if (is_array($candidate) && isset($candidate['key'])) {
                    if (!FLACSO_Preinscriptions_Field_Catalog::has((string) $candidate['key'])) {
                        $has_invalid_keys = true;
                        $issues[] = 'unknown_input';
                        break;
                    }
                }
            }
        }

        $clean_inputs = FLACSO_Preinscriptions_Config::sanitize_inputs($raw_inputs);
        $raw_orientations = get_post_meta($cohort_id, 'preinscripcion_orientaciones', true);
        $clean_orientations = FLACSO_Preinscriptions_Config::sanitize_orientations($raw_orientations);

        $canonical = FLACSO_Preinscriptions_Config::canonical_payload($clean_inputs, $clean_orientations);
        $revision = FLACSO_Preinscriptions_Config::revision($canonical);

        $fecha_limite = get_post_meta($cohort_id, 'fecha_limite_preinscripcion', true);
        $iso_until = null;
        if (!empty($fecha_limite) && is_string($fecha_limite)) {
            $ts = strtotime($fecha_limite);
            if ($ts !== false) {
                $iso_until = date('c', $ts);
            }
        }

        $target_id = sprintf('target_%s', substr(hash('sha256', 'cohorte:' . $cohort_id), 0, 12));
        $cohort_number = get_post_meta($cohort_id, 'numero', true);
        $cohort_name = get_post_meta($cohort_id, 'nombre', true);
        $offer_sigla = get_post_meta($parent->ID, 'sigla', true);

        $form_state = [
            'valid'  => !$has_invalid_keys,
            'inputs' => $clean_inputs,
        ];
        if ($has_invalid_keys) {
            $form_state['issues'] = array_values(array_unique($issues));
        }

        $legacy_link = get_post_meta($cohort_id, 'link_preinscripcion', true);

        return [
            'id'                 => $target_id,
            'kind'               => 'academic_offer',
            'title'              => $cohort->post_title ?: ($parent->post_title . ' - Cohorte ' . $cohort_number),
            'wordpress'          => [
                'offerId'  => (int) $parent->ID,
                'cohortId' => $cohort_id,
            ],
            'offer'              => [
                'id'   => (int) $parent->ID,
                'slug' => (string) $parent->post_name,
                'name' => (string) ($offer_sigla ?: $parent->post_title),
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
            'form'               => $form_state,
            'orientations'       => $clean_orientations,
            'urls'               => [
                'public'             => get_permalink($parent->ID),
                'wordpress'          => get_permalink($cohort_id),
                'edit'               => admin_url('post.php?post=' . $cohort_id . '&action=edit'),
                'legacyRegistration' => !empty($legacy_link) ? (string) $legacy_link : null,
            ],
        ];
    }

    public static function for_edition(int $edition_id): ?array {
        $edition = get_post($edition_id);
        if (!$edition || $edition->post_status !== 'publish') {
            return null;
        }

        $parent_id = (int) get_post_meta($edition_id, 'parent_seminario_id', true);
        if (!$parent_id) {
            $parent_id = (int) get_post_meta($edition_id, '_parent_seminario_id', true);
        }
        $parent = $parent_id > 0 ? get_post($parent_id) : null;
        if (!$parent) {
            return null;
        }

        $is_open = class_exists('FLACSO_Edicion')
            ? FLACSO_Edicion::accepts_registration($edition_id)
            : false;

        $raw_inputs = get_post_meta($edition_id, 'preinscripcion_formulario', true);
        $issues = [];
        $has_invalid_keys = false;

        if (is_array($raw_inputs)) {
            foreach ($raw_inputs as $candidate) {
                if (is_array($candidate) && isset($candidate['key'])) {
                    if (!FLACSO_Preinscriptions_Field_Catalog::has((string) $candidate['key'])) {
                        $has_invalid_keys = true;
                        $issues[] = 'unknown_input';
                        break;
                    }
                }
            }
        }

        $clean_inputs = FLACSO_Preinscriptions_Config::sanitize_inputs($raw_inputs);
        $clean_orientations = []; // Seminars do not use orientations

        $canonical = FLACSO_Preinscriptions_Config::canonical_payload($clean_inputs, $clean_orientations);
        $revision = FLACSO_Preinscriptions_Config::revision($canonical);

        $fecha_limite = get_post_meta($edition_id, 'fecha_limite_preinscripcion', true);
        $iso_until = null;
        if (!empty($fecha_limite) && is_string($fecha_limite)) {
            $ts = strtotime($fecha_limite);
            if ($ts !== false) {
                $iso_until = date('c', $ts);
            }
        }

        $target_id = sprintf('target_%s', substr(hash('sha256', 'edicion:' . $edition_id), 0, 12));
        $edition_number = get_post_meta($edition_id, 'numero', true);
        $edition_name = get_post_meta($edition_id, 'nombre', true);

        $form_state = [
            'valid'  => !$has_invalid_keys,
            'inputs' => $clean_inputs,
        ];
        if ($has_invalid_keys) {
            $form_state['issues'] = array_values(array_unique($issues));
        }

        $legacy_link = get_post_meta($edition_id, 'link_preinscripcion', true);

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
            ],
            'edition'            => [
                'number' => is_numeric($edition_number) ? (int) $edition_number : $edition_number,
                'name'   => (string) ($edition_name ?: ('Edición ' . $edition_number)),
            ],
            'registrationOpen'   => $is_open,
            'registrationWindow' => [
                'from'  => null,
                'until' => $iso_until,
            ],
            'configRevision'     => $revision,
            'form'               => $form_state,
            'orientations'       => [],
            'urls'               => [
                'public'             => get_permalink($parent->ID),
                'wordpress'          => get_permalink($edition_id),
                'edit'               => admin_url('post.php?post=' . $edition_id . '&action=edit'),
                'legacyRegistration' => !empty($legacy_link) ? (string) $legacy_link : null,
            ],
        ];
    }
}
