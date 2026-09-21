<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Patrón visual compartido para los editores académicos de wp-admin.
 *
 * No guarda datos ni altera contratos: solo centraliza cabecera, secciones,
 * estados vacíos y controles de apertura/cierre.
 */
final class FLACSO_Academic_Admin_UI {
    private const POST_TYPES = [
        'programa-academico',
        'oferta-academica',
        'cohorte',
        'tabla-precio',
    ];

    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_head-post.php', [self::class, 'render_styles']);
        add_action('admin_head-post-new.php', [self::class, 'render_styles']);
        add_action('admin_footer-post.php', [self::class, 'render_scripts']);
        add_action('admin_footer-post-new.php', [self::class, 'render_scripts']);
    }

    public static function render_header(
        WP_Post $post,
        string $eyebrow,
        string $title,
        string $description,
        array $pills = [],
        bool $show_public_link = false
    ): void {
        ?>
        <header class="flacso-academic-workspace">
            <div class="flacso-academic-workspace__copy">
                <p class="flacso-academic-eyebrow"><?php echo esc_html($eyebrow); ?></p>
                <h3><?php echo esc_html($title); ?></h3>
                <p><?php echo esc_html($description); ?></p>
                <?php if ($show_public_link && 'auto-draft' !== $post->post_status) :
                    $permalink = get_permalink($post);
                    if ($permalink) : ?>
                        <p class="flacso-academic-public-link">
                            <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener noreferrer">
                                <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                <?php esc_html_e('Ver página pública', 'flacso-uruguay'); ?>
                            </a>
                        </p>
                    <?php endif;
                endif; ?>
            </div>
            <div class="flacso-academic-workspace__aside">
                <?php if ($pills) : ?>
                    <div class="flacso-academic-pills" aria-label="<?php esc_attr_e('Resumen', 'flacso-uruguay'); ?>">
                        <?php foreach ($pills as $pill) :
                            $label = (string) ($pill['label'] ?? '');
                            if ($label === '') {
                                continue;
                            }
                            $icon = sanitize_html_class((string) ($pill['icon'] ?? 'dashicons-info-outline'));
                            ?>
                            <span class="flacso-academic-pill">
                                <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                                <?php echo esc_html($label); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="flacso-academic-toolbar" role="group" aria-label="<?php esc_attr_e('Controles de secciones', 'flacso-uruguay'); ?>">
                    <button type="button" class="button button-small" data-flacso-academic-sections="open"><?php esc_html_e('Abrir todas', 'flacso-uruguay'); ?></button>
                    <button type="button" class="button button-small" data-flacso-academic-sections="close"><?php esc_html_e('Cerrar todas', 'flacso-uruguay'); ?></button>
                </div>
            </div>
        </header>
        <?php
    }

    public static function section_start(
        string $id,
        string $title,
        string $description,
        string $icon,
        string $summary = '',
        bool $open = false
    ): void {
        ?>
        <details class="flacso-academic-section" id="<?php echo esc_attr($id); ?>" <?php echo $open ? 'open' : ''; ?>>
            <summary class="flacso-academic-section__summary">
                <span class="flacso-academic-section__icon dashicons <?php echo esc_attr(sanitize_html_class($icon)); ?>" aria-hidden="true"></span>
                <span class="flacso-academic-section__copy">
                    <strong><?php echo esc_html($title); ?></strong>
                    <small><?php echo esc_html($description); ?></small>
                </span>
                <span class="flacso-academic-section__status"><?php echo esc_html($summary !== '' ? $summary : __('Sin completar', 'flacso-uruguay')); ?></span>
            </summary>
            <div class="flacso-academic-section__body">
        <?php
    }

    public static function section_end(): void {
        echo '</div></details>';
    }

    public static function display_value($value, string $empty = ''): string {
        if ($empty === '') {
            $empty = __('Sin completar', 'flacso-uruguay');
        }

        if (is_array($value)) {
            return $value ? sprintf(_n('%d elemento', '%d elementos', count($value), 'flacso-uruguay'), count($value)) : $empty;
        }

        $text = trim(wp_strip_all_tags((string) $value));
        return $text !== '' ? $text : $empty;
    }

    public static function render_empty(string $message): void {
        ?>
        <div class="flacso-academic-empty">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <span><?php echo esc_html($message); ?></span>
        </div>
        <?php
    }

    public static function render_styles(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, self::POST_TYPES, true)) {
            return;
        }
        ?>
        <style>
            .flacso-academic-editor{display:grid;gap:14px;color:#1d2327}
            .flacso-academic-workspace{display:flex;justify-content:space-between;gap:24px;padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff}
            .flacso-academic-workspace__copy{min-width:0}
            .flacso-academic-workspace h3{margin:2px 0 7px;font-size:20px}
            .flacso-academic-workspace p{max-width:760px;margin:0;color:#50575e;font-size:13px;line-height:1.5}
            .flacso-academic-eyebrow{margin-bottom:2px!important;color:#2c4777!important;font-size:11px!important;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
            .flacso-academic-public-link{margin-top:10px!important}
            .flacso-academic-public-link a{display:inline-flex;align-items:center;gap:5px;text-decoration:none}
            .flacso-academic-public-link .dashicons{width:16px;height:16px;font-size:16px}
            .flacso-academic-workspace__aside{display:flex;min-width:250px;flex-direction:column;align-items:flex-end;justify-content:space-between;gap:14px}
            .flacso-academic-pills{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:7px}
            .flacso-academic-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border:1px solid #c3c4c7;border-radius:999px;background:#f6f7f7;font-size:12px;font-weight:600;white-space:nowrap}
            .flacso-academic-pill .dashicons{width:15px;height:15px;font-size:15px;color:#2c4777}
            .flacso-academic-toolbar{display:flex;gap:7px}
            .flacso-academic-section{border:1px solid #dcdcde;border-radius:9px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.03);overflow:hidden}
            .flacso-academic-section[open]{border-color:#b8c2cc;box-shadow:0 3px 12px rgba(0,0,0,.055)}
            .flacso-academic-section__summary{display:flex;align-items:center;gap:12px;padding:16px 18px;cursor:pointer;list-style:none;user-select:none}
            .flacso-academic-section__summary::-webkit-details-marker{display:none}
            .flacso-academic-section__summary::after{content:"\f347";width:20px;height:20px;margin-left:2px;font:normal 20px/1 dashicons;color:#646970;transition:transform .16s ease}
            .flacso-academic-section[open]>.flacso-academic-section__summary::after{transform:rotate(180deg)}
            .flacso-academic-section__summary:hover{background:#f8fafc}
            .flacso-academic-section__summary:focus-visible{outline:2px solid #2271b1;outline-offset:-2px}
            .flacso-academic-section__icon{display:grid;width:36px;height:36px;flex:0 0 36px;place-items:center;border-radius:8px;color:#2c4777;background:#eef2f8;font-size:20px;line-height:36px}
            .flacso-academic-section__copy{display:grid;flex:1;gap:3px;min-width:0}
            .flacso-academic-section__copy strong{font-size:14px}
            .flacso-academic-section__copy small{color:#646970;font-size:12px;font-weight:400}
            .flacso-academic-section__status{max-width:260px;padding:4px 8px;border-radius:999px;color:#50575e;background:#f0f0f1;font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .flacso-academic-section__body{padding:16px 18px 20px;border-top:1px solid #f0f0f1}
            .flacso-academic-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
            .flacso-academic-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}
            .flacso-academic-field{display:grid;gap:6px;min-width:0}
            .flacso-academic-field>span,.flacso-academic-label{font-weight:600}
            .flacso-academic-field input,.flacso-academic-field select,.flacso-academic-field textarea{width:100%;max-width:none}
            .flacso-academic-field input,.flacso-academic-field select{min-height:38px}
            .flacso-academic-field small,.flacso-academic-help{color:#646970;line-height:1.4}
            .flacso-academic-field--full{grid-column:1/-1}
            .flacso-academic-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
            .flacso-academic-list{display:grid;gap:8px;margin:0}
            .flacso-academic-list>li{display:flex;justify-content:space-between;gap:14px;align-items:center;margin:0;padding:10px 12px;border:1px solid #dcdcde;border-radius:6px;background:#fafafa}
            .flacso-academic-list__copy{display:grid;gap:3px;min-width:0}
            .flacso-academic-list__copy small{color:#646970}
            .flacso-academic-empty{display:flex;align-items:center;gap:8px;padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#646970;background:#f6f7f7}
            .flacso-academic-preview{margin:12px 0 0;padding:10px 12px;border-left:3px solid #2c4777;background:#f0f4f9}
            .flacso-academic-editor .wp-editor-wrap{max-width:100%}
            @media(max-width:1100px){
                .flacso-academic-workspace{flex-direction:column}
                .flacso-academic-workspace__aside{min-width:0;align-items:flex-start}
                .flacso-academic-pills{justify-content:flex-start}
                .flacso-academic-grid--three{grid-template-columns:1fr}
            }
            @media(max-width:782px){
                .flacso-academic-workspace{padding:16px}
                .flacso-academic-grid{grid-template-columns:1fr}
                .flacso-academic-field--full{grid-column:auto}
                .flacso-academic-section__summary{align-items:flex-start;padding:14px}
                .flacso-academic-section__copy small{display:none}
                .flacso-academic-section__status{margin-left:auto;max-width:120px}
                .flacso-academic-section__body{padding:14px}
                .flacso-academic-list>li{align-items:flex-start;flex-direction:column}
            }
        </style>
        <?php
    }

    public static function render_scripts(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, self::POST_TYPES, true)) {
            return;
        }
        ?>
        <script>
        (function () {
            document.querySelectorAll('.flacso-academic-editor').forEach(function (root) {
                root.querySelectorAll('[data-flacso-academic-sections]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var open = button.getAttribute('data-flacso-academic-sections') === 'open';
                        root.querySelectorAll('details.flacso-academic-section').forEach(function (section) {
                            section.open = open;
                        });
                        window.dispatchEvent(new Event('resize'));
                    });
                });
                root.querySelectorAll('details.flacso-academic-section').forEach(function (section) {
                    section.addEventListener('toggle', function () {
                        if (section.open) {
                            window.dispatchEvent(new Event('resize'));
                        }
                    });
                });
            });
        }());
        </script>
        <?php
    }
}
