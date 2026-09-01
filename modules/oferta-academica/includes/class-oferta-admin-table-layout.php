<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mantiene legible el listado administrativo de Oferta Académica.
 *
 * Las columnas SEO/visibilidad de terceros ocupaban gran parte del ancho y
 * comprimían el título hasta una letra por línea. Esos datos siguen
 * disponibles en la edición individual; el listado prioriza la operación
 * académica y las cohortes.
 */
final class FLACSO_Oferta_Admin_Table_Layout {
    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_filter(
            'manage_' . CPT_Oferta_Academica::POST_TYPE . '_posts_columns',
            [self::class, 'filter_columns'],
            100
        );
        add_action('admin_head-edit.php', [self::class, 'render_styles'], 100);
    }

    public static function filter_columns(array $columns): array {
        $hidden_labels = [
            'esquema',
            'schema',
            'metadescripción',
            'metadescripcion',
            'meta description',
            'buscar',
            'search',
            'visible',
            'visibility',
        ];

        $result = [];
        foreach ($columns as $key => $label) {
            $normalized = strtolower(trim(remove_accents(wp_strip_all_tags((string) $label))));
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            $third_party_key = preg_match('/rank.?math|yoast|seo|schema/i', (string) $key) === 1;
            $third_party_label = in_array($normalized, $hidden_labels, true);

            if ($third_party_key || $third_party_label) {
                continue;
            }

            $result[$key] = $label;
        }

        return $result;
    }

    public static function render_styles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== CPT_Oferta_Academica::POST_TYPE) {
            return;
        }
        ?>
        <style>
            .post-type-oferta-academica .wp-list-table {
                table-layout: fixed;
            }

            .post-type-oferta-academica .wp-list-table .column-cb {
                width: 36px;
            }

            .post-type-oferta-academica .wp-list-table .column-title {
                width: 31%;
                min-width: 260px;
            }

            .post-type-oferta-academica .wp-list-table .column-cohortes {
                width: 39%;
                min-width: 330px;
            }

            .post-type-oferta-academica .wp-list-table .column-taxonomy-tipo-oferta-academica {
                width: 12%;
                min-width: 120px;
            }

            .post-type-oferta-academica .wp-list-table .column-date {
                width: 145px;
            }

            .post-type-oferta-academica .wp-list-table th,
            .post-type-oferta-academica .wp-list-table td {
                vertical-align: top;
            }

            .post-type-oferta-academica .wp-list-table .column-title .row-title {
                display: inline-block;
                max-width: 100%;
                overflow-wrap: normal;
                word-break: normal;
                hyphens: auto;
                line-height: 1.35;
            }

            .post-type-oferta-academica .wp-list-table .column-title .row-actions {
                white-space: normal;
            }

            .post-type-oferta-academica .flacso-cohort-list {
                min-width: 0;
            }

            .post-type-oferta-academica .flacso-cohort-card {
                box-sizing: border-box;
                width: 100%;
            }

            .post-type-oferta-academica .flacso-cohort-card__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            @media screen and (max-width: 1200px) {
                .post-type-oferta-academica .wp-list-table .column-date {
                    display: none;
                }

                .post-type-oferta-academica .wp-list-table .column-title {
                    width: 36%;
                }

                .post-type-oferta-academica .wp-list-table .column-cohortes {
                    width: 46%;
                }

                .post-type-oferta-academica .wp-list-table .column-taxonomy-tipo-oferta-academica {
                    width: 16%;
                }
            }
        </style>
        <?php
    }
}
