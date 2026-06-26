import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-main-page-unified-settings.php"
with open(file_path, "r") as f:
    content = f.read()

content = re.sub(
    r"('instagram' => \[\n\s*'label' => 'Instagram',\n\s*'icon' => 'dashicons-camera',\n\s*\],)",
    r"\1\n        'reels' => [\n            'label' => 'Reels',\n            'icon' => 'dashicons-video-alt3',\n        ],",
    content
)

content = re.sub(
    r"(case 'instagram':\n\s*self::render_instagram_section\(\$settings\);\n\s*break;)",
    r"\1\n            case 'reels':\n                self::render_reels_section($settings);\n                break;",
    content
)

reels_method = """    private static function render_reels_section(array $settings): void {
        $reels = $settings['reels'] ?? [];
        ?>
        <h3><?php esc_html_e('Configuración de Reels', 'flacso-main-page'); ?></h3>
        <p class="description">
            <?php esc_html_e('Esta sección mostrará automáticamente los Reels más recientes utilizando la misma configuración de API de la pestaña Instagram.', 'flacso-main-page'); ?>
        </p>

        <div class="flacso-form-group">
            <label for="reels_title"><?php esc_html_e('Título de la sección', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="reels_title" 
                name="reels[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($reels['title'] ?? 'Reels Destacados'); ?>">
        </div>
        <?php
    }
"""

content = re.sub(
    r"(    private static function render_instagram_section\(array \$settings\): void {)",
    reels_method + r"\n\1",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched unified settings successfully")
