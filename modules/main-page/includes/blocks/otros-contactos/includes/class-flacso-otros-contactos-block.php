<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('FLACSO_OTROS_CONTACTOS_BLOCK_PATH')) {
    define('FLACSO_OTROS_CONTACTOS_BLOCK_PATH', dirname(__DIR__, 1) . '/');
}

if (!defined('FLACSO_OTROS_CONTACTOS_BLOCK_FILE')) {
    define('FLACSO_OTROS_CONTACTOS_BLOCK_FILE', FLACSO_OTROS_CONTACTOS_BLOCK_PATH . 'block.php');
}

if (!defined('FLACSO_OTROS_CONTACTOS_BLOCK_URL')) {
    define('FLACSO_OTROS_CONTACTOS_BLOCK_URL', plugin_dir_url(FLACSO_OTROS_CONTACTOS_BLOCK_FILE));
}

class Flacso_Otros_Contactos_Block {
    public const VERSION = '1.0.0';
    public const BLOCK_NAME = 'flacso-uruguay/otros-contactos';

    /** @var self|null */
    private static $instance = null;

    public static function init(): void {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    private function __construct() {
        add_action('init', [$this, 'register_assets'], 5);
        add_action('init', [$this, 'register_block'], 20);
    }

    public function register_assets(): void {
        $version = defined('FLACSO_MAIN_PAGE_VERSION') ? FLACSO_MAIN_PAGE_VERSION : self::VERSION;

        $style_path = FLACSO_OTROS_CONTACTOS_BLOCK_PATH . 'assets/style.css';
        $editor_style_path = FLACSO_OTROS_CONTACTOS_BLOCK_PATH . 'assets/style-editor.css';
        $script_path = FLACSO_OTROS_CONTACTOS_BLOCK_PATH . 'assets/block.js';

        wp_register_style(
            'flacso-otros-contactos-block-style',
            FLACSO_OTROS_CONTACTOS_BLOCK_URL . 'assets/style.css',
            [],
            file_exists($style_path) ? (string) filemtime($style_path) : $version
        );

        wp_register_style(
            'flacso-otros-contactos-block-editor-style',
            FLACSO_OTROS_CONTACTOS_BLOCK_URL . 'assets/style-editor.css',
            ['flacso-otros-contactos-block-style'],
            file_exists($editor_style_path) ? (string) filemtime($editor_style_path) : $version
        );

        wp_register_script(
            'flacso-otros-contactos-block-editor',
            FLACSO_OTROS_CONTACTOS_BLOCK_URL . 'assets/block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-block-editor',
                'wp-i18n',
                'wp-server-side-render',
            ],
            file_exists($script_path) ? (string) filemtime($script_path) : $version,
            true
        );
    }

    public function register_block(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK_NAME, [
            'api_version' => 2,
            'title' => __('FLACSO - Otros contactos', 'flacso-main-page'),
            'description' => __('Directorio institucional de contactos para insertar en páginas.', 'flacso-main-page'),
            'category' => 'flacso-uruguay',
            'icon' => 'id-alt',
            'keywords' => ['contactos', 'directorio', 'flacso'],
            'attributes' => [
                'title' => [
                    'type' => 'string',
                    'default' => __('Otros contactos', 'flacso-main-page'),
                ],
                'contactos' => [
                    'type' => 'array',
                    'default' => self::get_default_contacts(),
                ],
            ],
            'supports' => [
                'html' => false,
                'align' => ['full', 'wide'],
                'inserter' => true,
                'multiple' => true,
                'reusable' => true,
            ],
                'style' => 'flacso-otros-contactos-block-style',
            'editor_style' => 'flacso-otros-contactos-block-editor-style',
            'editor_script' => 'flacso-otros-contactos-block-editor',
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render_block(array $attributes = []): string {
        $title = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : '';
        if ($title === '') {
            $title = __('Otros contactos', 'flacso-main-page');
        }

        $raw_contacts = $attributes['contactos'] ?? [];
        $contacts = self::sanitize_contacts($raw_contacts);
        if (empty($contacts)) {
            $contacts = self::sanitize_contacts(self::get_default_contacts());
        }

        $uid = function_exists('wp_unique_id')
            ? wp_unique_id('flacso-otros-contactos-')
            : ('flacso-otros-contactos-' . wp_rand(1000, 9999));

        ob_start();
        ?>
        <section id="<?php echo esc_attr($uid); ?>" class="flacso-otros-contactos" aria-label="<?php echo esc_attr($title); ?>">
            <div class="foc-shell">
                <header class="foc-header">
                    <h2 class="foc-title"><?php echo esc_html($title); ?></h2>
                </header>

                <div class="foc-grid">
                    <?php foreach ($contacts as $item) : ?>
                        <article class="foc-card">
                            <h3 class="foc-area"><?php echo esc_html($item['area']); ?></h3>

                            <?php if ($item['persona'] !== '') : ?>
                                <p class="foc-persona"><?php echo esc_html($item['persona']); ?></p>
                            <?php endif; ?>

                            <?php if ($item['email'] !== '') : ?>
                                <a class="foc-email" href="<?php echo esc_url('mailto:' . $item['email']); ?>">
                                    <?php echo esc_html($item['email']); ?>
                                </a>
                            <?php else : ?>
                                <span class="foc-email foc-email--empty"><?php esc_html_e('Correo no disponible', 'flacso-main-page'); ?></span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param mixed $raw
     * @return array<int,array{area:string,persona:string,email:string}>
     */
    private static function sanitize_contacts($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $area = sanitize_text_field((string) ($item['area'] ?? ''));
            $persona = sanitize_text_field((string) ($item['persona'] ?? ''));
            $email = sanitize_email((string) ($item['email'] ?? ''));

            if ($area === '' && $persona === '' && $email === '') {
                continue;
            }

            if ($email !== '' && !is_email($email)) {
                $email = '';
            }

            $clean[] = [
                'area' => $area,
                'persona' => $persona,
                'email' => $email,
            ];
        }

        return $clean;
    }

    /**
     * @return array<int,array{area:string,persona:string,email:string}>
     */
    private static function get_default_contacts(): array {
        return [
            [
                'area' => 'Secretaría Académica',
                'persona' => 'Lena Fontela',
                'email' => 'lfontela@flacso.edu.uy',
            ],
            [
                'area' => 'Inscripciones',
                'persona' => '',
                'email' => 'inscripciones@flacso.edu.uy',
            ],
            [
                'area' => 'Asistente Académica Maestría en Educación, Innovación y Tecnología',
                'persona' => 'Analía Bombau',
                'email' => 'edutic@flacso.edu.uy',
            ],
            [
                'area' => 'Asistente Académica Diploma en Género y Políticas de Igualdad',
                'persona' => 'Florencia Quartino',
                'email' => 'genero@flacso.edu.uy',
            ],
            [
                'area' => 'Soporte Web',
                'persona' => 'Ivan Arriola',
                'email' => 'web@flacso.edu.uy',
            ],
            [
                'area' => 'Asistente Académica de la Especialización en Análisis, Producción y Edición de Textos',
                'persona' => 'Lourdes García',
                'email' => 'producciontextual@flacso.edu.uy',
            ],
            [
                'area' => 'Diplomado Superior en Género y Políticas de Igualdad',
                'persona' => 'Florencia Quartino',
                'email' => 'genero@flacso.edu.uy',
            ],
            [
                'area' => 'Asistente Académica de Maestría en Género y Políticas de Igualdad',
                'persona' => 'Diva Seluja',
                'email' => 'maestriagenero@flacso.edu.uy',
            ],
            [
                'area' => 'Asistente Académica Maestría en Educación, Sociedad y Política',
                'persona' => 'Alexis Larrosa',
                'email' => 'mesyp@flacso.edu.uy',
            ],
            [
                'area' => 'Soporte Virtual',
                'persona' => '',
                'email' => 'soportevirtual@flacso.edu.uy',
            ],
        ];
    }
}
