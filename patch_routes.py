import re

with open("modules/oferta-academica/includes/class-oferta-seminarios-routes.php", "r") as f:
    content = f.read()

# 1. Quitar la redireccion
content = re.sub(r"        add_action\('template_redirect', \[__CLASS__, 'maybe_redirect_oferta_singular'\]\);\n", "", content)

# 2. Agregar logica para is_tax
replacement_template = """    public static function template_include(string $template): string {
        if (is_tax('tipo-oferta-academica')) {
            $plugin_template = self::template_path('taxonomy-tipo-oferta-academica.php');
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        $is_seminarios_endpoint = self::is_seminarios_endpoint_request();"""
content = re.sub(r"    public static function template_include\(string \$template\): string \{\n        \$is_seminarios_endpoint = self::is_seminarios_endpoint_request\(\);", replacement_template, content)

with open("modules/oferta-academica/includes/class-oferta-seminarios-routes.php", "w") as f:
    f.write(content)
