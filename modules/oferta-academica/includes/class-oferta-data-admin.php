<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Página administrativa de documentación de la API académica. */
class Oferta_Data_Admin {
    public static function init(): void {
        if (is_admin()) {
            add_action('admin_menu', [self::class, 'add_admin_menu'], 11);
        }
    }

    public static function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=oferta-academica',
            __('API Oferta Académica', 'flacso-oferta-academica'),
            __('API Oferta Académica', 'flacso-oferta-academica'),
            'manage_options',
            'flacso-oferta-api-docs',
            [self::class, 'render_api_doc_page']
        );
    }

    public static function render_api_doc_page(): void {
        $base_api = rest_url('flacso/v1/');
        $list_endpoint = rest_url('flacso/v1/oferta-academica');
        $item_endpoint_example = rest_url('flacso/v1/oferta-academica/12330');
        $wp_me_endpoint = rest_url('wp/v2/users/me?context=edit');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('API de Oferta Academica', 'flacso-oferta-academica'); ?></h1>
            <p><?php esc_html_e('Consulta y edicion de los campos estructurados del CPT oferta-academica.', 'flacso-oferta-academica'); ?></p>

            <h2><?php esc_html_e('Base URL', 'flacso-oferta-academica'); ?></h2>
            <p><code><?php echo esc_html($base_api); ?></code></p>

            <h2><?php esc_html_e('Rutas disponibles', 'flacso-oferta-academica'); ?></h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Metodo', 'flacso-oferta-academica'); ?></th>
                        <th><?php esc_html_e('Ruta', 'flacso-oferta-academica'); ?></th>
                        <th><?php esc_html_e('Descripcion', 'flacso-oferta-academica'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/flacso/v1/oferta-academica</code></td>
                        <td><?php esc_html_e('Lista ofertas. Acepta filtro ?tipo=maestria|especializacion|diplomado|diploma.', 'flacso-oferta-academica'); ?></td>
                    </tr>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code>/wp-json/flacso/v1/oferta-academica/{id}</code></td>
                        <td><?php esc_html_e('Obtiene una oferta especifica por ID.', 'flacso-oferta-academica'); ?></td>
                    </tr>
                    <tr>
                        <td><code>PUT / PATCH / POST</code></td>
                        <td><code>/wp-json/flacso/v1/oferta-academica/{id}</code></td>
                        <td><?php esc_html_e('Actualiza una oferta por ID (requiere autenticacion y permisos de edicion).', 'flacso-oferta-academica'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php esc_html_e('Endpoints utiles', 'flacso-oferta-academica'); ?></h2>
            <p><a href="<?php echo esc_url($list_endpoint); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html($list_endpoint); ?></code></a></p>
            <p><a href="<?php echo esc_url($item_endpoint_example); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html($item_endpoint_example); ?></code></a></p>

            <h2><?php esc_html_e('Campos disponibles', 'flacso-oferta-academica'); ?></h2>
            <p><?php esc_html_e('En el payload JSON de actualizacion se aceptan:', 'flacso-oferta-academica'); ?></p>
            <ul>
                <li><code>titulo</code></li>
                <li><code>duracion_meses</code></li>
                <li><code>proximo_inicio</code> (string)</li>
                <li><code>proximo_inicio_precision</code> (day/month/year)</li>
                <li><code>proximo_inicio</code> tambien puede enviarse como objeto: <code>{"valor":"2026-04","precision":"month"}</code></li>
                <li><?php esc_html_e('Secciones HTML como <code>modalidad_html</code>, <code>objetivos_html</code>, etc.', 'flacso-oferta-academica'); ?></li>
                <li><code>menciones</code>, <code>orientaciones</code> (arrays de strings)</li>
                <li><code>coordinacion_academica</code>, <code>equipos</code> (arrays de objetos con <code>rol</code>/<code>nombre</code>, <code>descripcion</code> opcional y <code>docentes</code> como lista de IDs)</li>
            </ul>

            <h2><?php esc_html_e('Autenticacion con Password de aplicacion', 'flacso-oferta-academica'); ?></h2>
            <p><?php esc_html_e('El usuario debe tener permiso para editar ese ID de oferta-academica.', 'flacso-oferta-academica'); ?></p>
            <p><?php esc_html_e('Importante: si tu password de aplicacion tiene espacios, usa comillas en -u.', 'flacso-oferta-academica'); ?></p>
            <pre><code>curl -u "usuario:xxxx xxxx xxxx xxxx xxxx xxxx" \
  "<?php echo esc_html($wp_me_endpoint); ?>"</code></pre>
            <p><?php esc_html_e('Si el comando anterior devuelve tu usuario, la autenticacion funciona.', 'flacso-oferta-academica'); ?></p>

            <h3><?php esc_html_e('Ejemplo de actualizacion (PUT)', 'flacso-oferta-academica'); ?></h3>
            <pre><code>curl -X PUT "<?php echo esc_html($item_endpoint_example); ?>" \
  -u "usuario:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Nueva titulacion","duracion_meses":"24","proximo_inicio":{"valor":"2026-04","precision":"month"}}'</code></pre>

            <h3><?php esc_html_e('Fallback si tu servidor bloquea Authorization', 'flacso-oferta-academica'); ?></h3>
            <p><?php esc_html_e('Tambien puedes autenticar con estos headers (solo HTTPS):', 'flacso-oferta-academica'); ?></p>
            <pre><code>curl -X PUT "<?php echo esc_html($item_endpoint_example); ?>" \
  -H "X-FLACSO-App-User: usuario" \
  -H "X-FLACSO-App-Password: xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Nueva titulacion"}'</code></pre>

            <h2><?php esc_html_e('Errores frecuentes', 'flacso-oferta-academica'); ?></h2>
            <ul>
                <li><code>401 rest_forbidden</code>: credenciales invalidas o cabecera Authorization no llega al servidor.</li>
                <li><code>403 rest_forbidden</code>: el usuario autentica, pero no puede editar ese ID.</li>
                <li><code>404 oferta_not_found</code>: el ID no existe o no es post_type <code>oferta-academica</code>.</li>
                <li><code>400 rest_invalid_json</code>: JSON invalido en el body.</li>
            </ul>

            <h2><?php esc_html_e('Respuesta de ejemplo', 'flacso-oferta-academica'); ?></h2>
            <pre><code>{
  "id": 12330,
  "titulo": "MAESTRIA EN EDUCACION, INNOVACION Y TECNOLOGIAS",
  "duracion_meses": "24",
  "proximo_inicio": { "valor": "2026-04", "precision": "month" },
  "modalidad_html": "...",
  "coordinacion_academica": [...]
}</code></pre>
        </div>
        <?php
    }
}

