import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-main-page-unified-settings.php"
with open(file_path, "r") as f:
    content = f.read()

# 1. Add instagram to SECTIONS
content = re.sub(
    r"('novedades' => \[\s*'label' => 'Novedades',\s*'icon' => 'dashicons-admin-post',\s*\],)",
    r"\1\n        'instagram' => [\n            'label' => 'Instagram',\n            'icon' => 'dashicons-camera',\n        ],",
    content
)

# 2. Add instagram to switch case in render_section_content
content = re.sub(
    r"(case 'novedades':\s*self::render_novedades_section\(\$settings\);\s*break;)",
    r"\1\n            case 'instagram':\n                self::render_instagram_section($settings);\n                break;",
    content
)

# 3. Add render_instagram_section method
instagram_method = """    private static function render_instagram_section(array $settings): void {
        $instagram = $settings['instagram'] ?? [];
        ?>
        <h3><?php esc_html_e('Configuración de Instagram', 'flacso-main-page'); ?></h3>
        
        <div class="flacso-form-group">
            <label for="instagram_title"><?php esc_html_e('Título de la sección', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="instagram_title" 
                name="instagram[title]" 
                class="regular-text" 
                value="<?php echo esc_attr($instagram['title'] ?? 'Seguinos en Instagram'); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="instagram_description"><?php esc_html_e('Descripción', 'flacso-main-page'); ?></label>
            <textarea 
                id="instagram_description" 
                name="instagram[description]" 
                rows="4" 
                class="regular-text"><?php echo esc_textarea($instagram['description'] ?? ''); ?></textarea>
        </div>

        <div class="flacso-form-group">
            <label for="instagram_profile_url"><?php esc_html_e('URL del perfil de Instagram', 'flacso-main-page'); ?></label>
            <input 
                type="url" 
                id="instagram_profile_url" 
                name="instagram[profile_url]" 
                class="regular-text" 
                value="<?php echo esc_attr($instagram['profile_url'] ?? 'https://www.instagram.com/flacsouruguay/'); ?>">
        </div>
        
        <div class="flacso-form-group">
            <label for="instagram_cta_label"><?php esc_html_e('Etiqueta del botón de perfil', 'flacso-main-page'); ?></label>
            <input 
                type="text" 
                id="instagram_cta_label" 
                name="instagram[cta_label]" 
                class="regular-text" 
                value="<?php echo esc_attr($instagram['cta_label'] ?? 'Ir a @flacsouruguay'); ?>">
        </div>

        <h3><?php esc_html_e('Integración con la API', 'flacso-main-page'); ?></h3>
        <p class="description">
            <?php esc_html_e('Configura las credenciales de la API de Instagram para mostrar automáticamente el feed más reciente. Si el token está vacío o es inválido, se mostrará una tarjeta estática como alternativa (fallback).', 'flacso-main-page'); ?>
        </p>

        <div class="flacso-form-group">
            <label for="instagram_access_token"><?php esc_html_e('Token de Acceso de Instagram (Access Token)', 'flacso-main-page'); ?></label>
            <input 
                type="password" 
                id="instagram_access_token" 
                name="instagram[access_token]" 
                class="regular-text" 
                value="<?php echo esc_attr($instagram['access_token'] ?? ''); ?>"
                placeholder="<?php esc_attr_e('Pega aquí tu Token de Acceso de Larga Duración', 'flacso-main-page'); ?>">
        </div>

        <div class="flacso-form-group">
            <label for="instagram_api_type"><?php esc_html_e('Tipo de API', 'flacso-main-page'); ?></label>
            <select id="instagram_api_type" name="instagram[api_type]">
                <option value="basic" <?php selected(($instagram['api_type'] ?? 'basic'), 'basic'); ?>><?php esc_html_e('Instagram Basic Display API (Personal/Creador)', 'flacso-main-page'); ?></option>
                <option value="graph" <?php selected(($instagram['api_type'] ?? 'basic'), 'graph'); ?>><?php esc_html_e('Instagram Graph API (Cuenta Profesional vinculada a FB)', 'flacso-main-page'); ?></option>
            </select>
        </div>

        <div class="flacso-form-group">
            <label for="instagram_count"><?php esc_html_e('Cantidad de publicaciones a mostrar', 'flacso-main-page'); ?></label>
            <input 
                type="number" 
                id="instagram_count" 
                name="instagram[count]" 
                value="<?php echo esc_attr(intval($instagram['count'] ?? 6)); ?>"
                min="1"
                max="24">
        </div>
        <?php
    }

"""

content = re.sub(
    r"(    private static function render_posgrados_section\(array \$settings\): void {)",
    instagram_method + r"\1",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched successfully")
