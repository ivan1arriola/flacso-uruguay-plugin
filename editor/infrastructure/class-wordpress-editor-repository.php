<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adaptador WordPress para los contratos de /editor.
 *
 * Único punto de lectura de CPT, post_meta y funciones del dominio
 * académico. No expone WP_Post ni escribe datos; los formularios
 * existentes siguen siendo la única vía de guardado en esta etapa.
 */
final class FLACSO_WordPress_Editor_Repository implements FLACSO_Editor_Repository_Interface {
    private const TYPES = ['oferta-academica', 'cohorte'];

    public function find(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        $post = get_post($id);
        if (!$post instanceof WP_Post || !in_array($post->post_type, self::TYPES, true)) {
            return null;
        }

        $fields = [];
        if ($post->post_type === 'oferta-academica') {
            $thumbnail_id = get_post_thumbnail_id($id);
            $fields = [
                'abbreviation' => (string) get_post_meta($id, 'abreviacion', true),
                'contact_email' => (string) get_post_meta($id, 'correo', true),
                'thumbnail_id' => $thumbnail_id ? (int) $thumbnail_id : 0,
                'thumbnail_url' => $thumbnail_id ? (string) (wp_get_attachment_image_url($thumbnail_id, 'medium') ?: '') : '',
            ];
        } else {
            $number = absint(get_post_meta($id, 'numero', true));
            $fields = [
                'parent_id' => absint(get_post_meta($id, 'oferta_academica_id', true)),
                'number_label' => $number > 0 && class_exists('FLACSO_Cohorte')
                    ? FLACSO_Cohorte::display_name($number)
                    : '',
                'academic_status' => (string) get_post_meta($id, 'estado', true),
                'modality' => (string) get_post_meta($id, 'modalidad', true),
                'start' => class_exists('FLACSO_Cohorte')
                    ? FLACSO_Cohorte::format_dates($id)
                    : '',
            ];
        }

        return [
            'id' => (int) $post->ID,
            'type' => (string) $post->post_type,
            'title' => (string) get_the_title($post),
            'status' => (string) $post->post_status,
            'fields' => $fields,
        ];
    }

    public function can_edit(int $id): bool {
        return $id > 0 && current_user_can('edit_post', $id);
    }

    public function can_publish(int $id): bool {
        if (!$this->can_edit($id)) {
            return false;
        }
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            return false;
        }
        $type = get_post_type_object($post->post_type);
        return $type && isset($type->cap->publish_posts)
            ? current_user_can((string) $type->cap->publish_posts)
            : false;
    }

    public function legacy_edit_url(int $id): string {
        if (!$this->can_edit($id)) {
            return '';
        }
        return (string) get_edit_post_link($id, 'raw');
    }

    public function cohorts_for_offer(int $offer_id): array {
        $offer = $this->find($offer_id);
        if ($offer === null || $offer['type'] !== 'oferta-academica' || !$this->can_edit($offer_id)) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'cohorte',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 100,
            'meta_key' => 'numero',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => 'oferta_academica_id',
                'value' => $offer_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
        ]);

        $result = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post || !$this->can_edit((int) $post->ID)) {
                continue;
            }
            $cohort = $this->find((int) $post->ID);
            if ($cohort !== null && $cohort['type'] === 'cohorte') {
                $result[] = $cohort;
            }
        }
        return $result;
    }
}
