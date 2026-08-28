<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Separa de forma explícita la vista pública de la vista editorial de seminarios.
 *
 * - Solo los seminarios publicados son visibles para visitantes.
 * - Usuarios con permiso para editar el seminario pueden previsualizar estados
 *   no públicos.
 * - La previsualización editorial queda marcada de forma visible y no indexable.
 */
final class Seminario_Public_Visibility
{
    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'guard_frontend'], 1);
        add_action('wp_head', [self::class, 'render_notice_styles'], 99);
        add_action('wp_body_open', [self::class, 'render_admin_notice'], 1);
        add_filter('wp_robots', [self::class, 'mark_preview_noindex']);
    }

    /**
     * Si por cualquier ruta WordPress llega a resolver un seminario no publicado,
     * solo un usuario con permisos de edición puede verlo. Para el resto se
     * responde como contenido inexistente.
     */
    public static function guard_frontend(): void
    {
        $post = self::get_current_non_public_seminar();
        if (!$post) {
            return;
        }

        if (current_user_can('edit_post', $post->ID)) {
            // Una vista editorial nunca debe reutilizar una caché pública.
            nocache_headers();
            return;
        }

        self::render_404_and_exit();
    }

    public static function mark_preview_noindex(array $robots): array
    {
        $post = self::get_current_non_public_seminar();
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return $robots;
        }

        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        $robots['noarchive'] = true;

        return $robots;
    }

    public static function render_notice_styles(): void
    {
        $post = self::get_current_non_public_seminar();
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }
        ?>
        <style id="flacso-seminario-admin-preview-styles">
            .flacso-seminario-admin-preview {
                box-sizing: border-box;
                width: 100%;
                background: #fff6d8;
                border-bottom: 1px solid #e5c34f;
                color: #432f00;
                font-family: inherit;
                position: relative;
                z-index: 999;
            }
            .flacso-seminario-admin-preview__inner {
                max-width: 1180px;
                margin: 0 auto;
                padding: 12px 20px;
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .flacso-seminario-admin-preview__badge {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 4px 10px;
                border-radius: 999px;
                background: #7a5700;
                color: #fff;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: .04em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .flacso-seminario-admin-preview__message {
                flex: 1 1 420px;
                line-height: 1.45;
                font-size: 14px;
            }
            .flacso-seminario-admin-preview__message strong {
                color: #2b2100;
            }
            .flacso-seminario-admin-preview__edit {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 36px;
                padding: 7px 12px;
                border: 1px solid #7a5700;
                border-radius: 8px;
                color: #5b4100;
                font-size: 13px;
                font-weight: 700;
                text-decoration: none;
                background: rgba(255, 255, 255, .45);
            }
            .flacso-seminario-admin-preview__edit:hover,
            .flacso-seminario-admin-preview__edit:focus {
                color: #2b2100;
                background: #fff;
            }
            @media (max-width: 600px) {
                .flacso-seminario-admin-preview__inner {
                    padding: 10px 14px;
                    gap: 8px;
                }
                .flacso-seminario-admin-preview__message {
                    flex-basis: 100%;
                    font-size: 13px;
                }
                .flacso-seminario-admin-preview__edit {
                    width: 100%;
                }
            }
        </style>
        <?php
    }

    public static function render_admin_notice(): void
    {
        $post = self::get_current_non_public_seminar();
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return;
        }

        $status_label = self::status_label($post->post_status);
        $edit_url = get_edit_post_link($post->ID, 'raw');
        ?>
        <aside class="flacso-seminario-admin-preview" role="status" aria-label="Estado de publicación del seminario">
            <div class="flacso-seminario-admin-preview__inner">
                <span class="flacso-seminario-admin-preview__badge">Vista administrativa</span>
                <div class="flacso-seminario-admin-preview__message">
                    <strong>Este seminario no es público.</strong>
                    Estado: <?php echo esc_html($status_label); ?>. Solo usuarios con permisos de edición pueden ver esta página; los demás reciben una respuesta 404.
                </div>
                <?php if ($edit_url) : ?>
                    <a class="flacso-seminario-admin-preview__edit" href="<?php echo esc_url($edit_url); ?>">Editar seminario</a>
                <?php endif; ?>
            </div>
        </aside>
        <?php
    }

    private static function get_current_non_public_seminar(): ?WP_Post
    {
        if (!is_singular('seminario')) {
            return null;
        }

        $post = get_queried_object();
        if (!$post instanceof WP_Post || $post->post_type !== 'seminario') {
            return null;
        }

        if ($post->post_status === 'publish') {
            return null;
        }

        return $post;
    }

    private static function render_404_and_exit(): void
    {
        global $wp_query;

        if ($wp_query instanceof WP_Query) {
            $wp_query->set_404();
        }

        status_header(404);
        nocache_headers();

        $template = get_404_template();
        if ($template) {
            include $template;
        } else {
            wp_die(
                esc_html__('Contenido no encontrado.', 'flacso-uruguay'),
                esc_html__('No encontrado', 'flacso-uruguay'),
                ['response' => 404]
            );
        }

        exit;
    }

    private static function status_label(string $status): string
    {
        $labels = [
            'draft' => 'Borrador',
            'pending' => 'Pendiente de revisión',
            'private' => 'Privado',
            'future' => 'Programado',
            'auto-draft' => 'Borrador automático',
            'trash' => 'Papelera',
        ];

        if (isset($labels[$status])) {
            return $labels[$status];
        }

        $status_object = get_post_status_object($status);
        if ($status_object && !empty($status_object->label)) {
            return (string) $status_object->label;
        }

        return ucfirst(str_replace(['-', '_'], ' ', $status));
    }
}

Seminario_Public_Visibility::init();
