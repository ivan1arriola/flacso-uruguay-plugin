<?php

if (!function_exists('flacso_view_pdf')) {
    /**
     * AJAX handler que proxea el PDF y lo sirve inline.
     */
    function flacso_view_pdf()
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $src_b64 = isset($_GET['src']) ? (string) $_GET['src'] : '';
        $fn_b64  = isset($_GET['fn']) ? (string) $_GET['fn'] : '';

        // Asegurarse de que los espacios convertidos por + en query strings vuelvan a ser +.
        $src_b64 = str_replace(' ', '+', $src_b64);
        $fn_b64  = str_replace(' ', '+', $fn_b64);

        $src_raw = base64_decode($src_b64, true);
        $name    = base64_decode($fn_b64, true);

        if (!$src_raw) {
            status_header(400);
            echo 'URL inválida.';
            exit;
        }

        $resolved = flacso_resolve_pdf_export_url($src_raw);
        if (is_wp_error($resolved)) {
            status_header(400);
            echo esc_html($resolved->get_error_message());
            exit;
        }

        if ($local = flacso_map_wp_uploads_file($resolved)) {
            if (!is_readable($local)) {
                status_header(404);
                echo 'Archivo no encontrado.';
                exit;
            }
            flacso_serve_pdf_file($local, $name ?: 'documento');
            exit;
        }

        $upload = wp_get_upload_dir();
        $dir    = trailingslashit($upload['basedir']) . 'flacso-temp-pdf';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        $key  = md5($resolved);
        $file = "{$dir}/{$key}.pdf";

        $ttl_hours = 12;
        $expired   = !file_exists($file) || (time() - filemtime($file) > $ttl_hours * 3600);

        if ($expired) {
            $fetch_remote = static function ($url, $cookies = array()) {
                return wp_remote_get(
                    $url,
                    array(
                        'timeout'     => 45,
                        'redirection' => 5,
                        'cookies'     => $cookies,
                        'headers'     => array(
                            'User-Agent' => 'FLACSO-PDF-Proxy/1.0',
                        ),
                    )
                );
            };

            $is_pdf_payload = static function ($ctype, $body) {
                if (stripos((string) $ctype, 'pdf') !== false) {
                    return true;
                }

                return substr((string) $body, 0, 4) === '%PDF';
            };

            $res = $fetch_remote($resolved);

            if (is_wp_error($res)) {
                status_header(502);
                echo 'No se pudo descargar el PDF (error de conexión).';
                exit;
            }

            $code  = wp_remote_retrieve_response_code($res);
            $body  = wp_remote_retrieve_body($res);
            $ctype = wp_remote_retrieve_header($res, 'content-type');

            if ($code !== 200 || empty($body)) {
                status_header(502);
                echo 'Origen no disponible.';
                exit;
            }

            $looks_like_pdf = $is_pdf_payload($ctype, $body);

            // Google Drive a veces devuelve HTML de confirmación en lugar del PDF directo.
            if (!$looks_like_pdf) {
                $resolved_parts = wp_parse_url($resolved);
                $resolved_host  = !empty($resolved_parts['host']) ? strtolower($resolved_parts['host']) : '';

                if ($resolved_host === 'drive.google.com') {
                    $query = isset($resolved_parts['query']) ? $resolved_parts['query'] : '';
                    parse_str($query, $q);
                    $drive_id = !empty($q['id']) ? preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $q['id']) : '';

                    $retry_urls = array();
                    $initial_cookies = wp_remote_retrieve_cookies($res);

                    if (preg_match('~confirm=([0-9A-Za-z_\-]+)~', (string) $body, $m_confirm)) {
                        $confirm = $m_confirm[1];
                        if ($drive_id !== '') {
                            $retry_urls[] = sprintf('https://drive.google.com/uc?export=download&id=%s&confirm=%s', $drive_id, rawurlencode($confirm));
                            $retry_urls[] = sprintf('https://drive.usercontent.google.com/download?id=%s&export=download&confirm=%s', $drive_id, rawurlencode($confirm));
                        }
                    }

                    if (preg_match('~href=["\']([^"\']*?/uc\?export=download[^"\']*)["\']~i', (string) $body, $m)) {
                        $candidate = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                        if (strpos($candidate, 'http') !== 0) {
                            $candidate = 'https://drive.google.com' . $candidate;
                        }
                        $retry_urls[] = $candidate;
                    }

                    if ($drive_id !== '') {
                        $retry_urls[] = sprintf('https://drive.google.com/uc?export=download&id=%s&confirm=t', $drive_id);
                        $retry_urls[] = sprintf('https://drive.usercontent.google.com/download?id=%s&export=download&confirm=t', $drive_id);
                    }

                    $retry_urls = array_values(array_unique(array_filter($retry_urls)));

                    foreach ($retry_urls as $retry_url) {
                        $retry_res = $fetch_remote($retry_url, $initial_cookies);
                        if (is_wp_error($retry_res)) {
                            continue;
                        }

                        $retry_code  = wp_remote_retrieve_response_code($retry_res);
                        $retry_body  = wp_remote_retrieve_body($retry_res);
                        $retry_ctype = wp_remote_retrieve_header($retry_res, 'content-type');

                        if ($retry_code === 200 && !empty($retry_body) && $is_pdf_payload($retry_ctype, $retry_body)) {
                            $body = $retry_body;
                            $looks_like_pdf = true;
                            break;
                        }
                    }
                }
            }

            if (!$looks_like_pdf) {
                status_header(502);
                echo 'El enlace proporcionado no devuelve un PDF.';
                exit;
            }

            file_put_contents($file, $body);
        }

        foreach (glob($dir . '/*.pdf') as $f) {
            if (time() - filemtime($f) > $ttl_hours * 3600) {
                @unlink($f);
            }
        }

        flacso_serve_pdf_file($file, $name ?: 'documento');
        exit;
    }
}

add_action('wp_ajax_flacso_view_pdf', 'flacso_view_pdf');
add_action('wp_ajax_nopriv_flacso_view_pdf', 'flacso_view_pdf');

if (!function_exists('flacso_is_google_document_url')) {
    /**
     * Determina si la URL apunta a un recurso de Google Docs/Drive.
     */
    function flacso_is_google_document_url($url)
    {
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        if (strpos($host, 'docs.google.com') !== false) {
            return true;
        }
        if (strpos($host, 'drive.google.com') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('flacso_get_pdf_proxy_url')) {
    /**
     * Construye la URL al proxy AJAX para documentos de Google.
     *
     * Devuelve false si la URL no corresponde a Google o si no se puede normalizar.
     */
    function flacso_get_pdf_proxy_url($url, $friendly_name = '')
    {
        if (!flacso_is_google_document_url($url)) {
            return false;
        }

        $resolved = flacso_resolve_pdf_export_url($url);
        if (is_wp_error($resolved)) {
            return false;
        }

        $query = array(
            'action' => 'flacso_view_pdf',
            'src'    => base64_encode($resolved),
        );

        if ($friendly_name !== '') {
            $query['fn'] = base64_encode($friendly_name);
        }

        return add_query_arg($query, admin_url('admin-ajax.php'));
    }
}

if (!function_exists('flacso_map_wp_uploads_file')) {
    /**
     * Mapea una URL que apunta a /wp-content/uploads/... a su ruta local en disco.
     * Devuelve string con ruta absoluta o false si no aplica.
     */
    function flacso_map_wp_uploads_file($url)
    {
        $uploads = wp_get_upload_dir();
        if (empty($uploads['baseurl']) || empty($uploads['basedir'])) {
            return false;
        }

        $u_url  = wp_parse_url($url);
        $u_base = wp_parse_url($uploads['baseurl']);

        if (!$u_url || !$u_base || empty($u_url['path']) || empty($u_base['path'])) {
            return false;
        }

        $base_path = rtrim($u_base['path'], '/') . '/';
        if (strpos($u_url['path'], $base_path) !== 0) {
            return false;
        }

        $rel  = ltrim(substr($u_url['path'], strlen($base_path)), '/');
        $file = trailingslashit($uploads['basedir']) . $rel;

        return file_exists($file) ? $file : false;
    }
}

if (!function_exists('flacso_serve_pdf_file')) {
    /**
     * Sirve un PDF local con soporte de rangos (HTTP Range) para vista inline.
     */
    function flacso_serve_pdf_file($file, $name = 'documento')
    {
        $download_name = sanitize_file_name($name . '.pdf');
        $size          = @filesize($file);
        if (!$size) {
            status_header(500);
            echo 'Archivo inválido.';
            exit;
        }

        @set_time_limit(0);
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $download_name . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Accept-Ranges: bytes');

        $fp = fopen($file, 'rb');
        if (!$fp) {
            status_header(500);
            echo 'No se pudo abrir el archivo.';
            exit;
        }

        if (isset($_SERVER['HTTP_RANGE'])) {
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes */$size");
                fclose($fp);
                exit;
            }

            $range = explode('-', $range);
            $start = max(0, intval($range[0]));
            $end   = isset($range[1]) && is_numeric($range[1]) ? intval($range[1]) : $size - 1;
            $end   = min($size - 1, $end);

            if ($start > $end) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                fclose($fp);
                exit;
            }

            $length = $end - $start + 1;

            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: $length");
            header("Content-Range: bytes $start-$end/$size");

            fseek($fp, $start);
            $buf = 8192;
            while (!feof($fp) && ftell($fp) <= $end) {
                if (ftell($fp) + $buf > $end) {
                    $buf = $end - ftell($fp) + 1;
                }
                echo fread($fp, $buf);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            fclose($fp);
            exit;
        }

        header("Content-Length: $size");
        while (!feof($fp)) {
            echo fread($fp, 8192);
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
        fclose($fp);
        exit;
    }
}

if (!function_exists('flacso_resolve_pdf_export_url')) {
    /**
     * Normaliza URL a un endpoint PDF visualizable.
     */
    function flacso_resolve_pdf_export_url($url)
    {
        $url   = trim($url);
        $parts = wp_parse_url($url);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return new WP_Error('bad_url', 'URL inválida.');
        }

        $host  = strtolower($parts['host']);
        $path  = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? $parts['query'] : '';

        if (preg_match('~\.pdf(\?.*)?$~i', $url)) {
            return $url;
        }

        $site_host = parse_url(home_url(), PHP_URL_HOST);
        if (!empty($site_host)
            && $host === strtolower($site_host)
            && strpos($path, '/wp-content/uploads/') === 0
        ) {
            return $url;
        }

        $allowed = array('docs.google.com', 'drive.google.com');
        if (!in_array($host, $allowed, true)) {
            return new WP_Error('forbidden', 'Origen no permitido. Solo Google Docs/Drive o PDFs directos.');
        }

        if ($host === 'docs.google.com') {
            if (preg_match('~/document/d/([a-zA-Z0-9_-]+)~', $path, $m)) {
                return "https://docs.google.com/document/d/{$m[1]}/export?format=pdf";
            }
            if (preg_match('~/spreadsheets/d/([a-zA-Z0-9_-]+)~', $path, $m)) {
                return "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=pdf";
            }
            if (preg_match('~/presentation/d/([a-zA-Z0-9_-]+)~', $path, $m)) {
                return "https://docs.google.com/presentation/d/{$m[1]}/export?format=pdf";
            }
            if (preg_match('~/document/d/e/([a-zA-Z0-9_-]+)/pub~', $path, $m)) {
                return "https://docs.google.com/document/d/e/{$m[1]}/download?format=pdf";
            }
            if (trim($path, '/') === 'uc') {
                parse_str($query, $q);
                if (!empty($q['id'])) {
                    $id = preg_replace('~[^a-zA-Z0-9_-]~', '', $q['id']);
                    return "https://drive.google.com/file/d/{$id}/preview";
                }
            }

            return new WP_Error('unsupported', 'Tipo de documento de Google no soportado para PDF.');
        }

        if ($host === 'drive.google.com') {
            parse_str($query, $q);

            $build_download = static function ($id) {
                $id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $id);
                return $id ? sprintf('https://drive.google.com/uc?export=download&id=%s', $id) : '';
            };

            if (preg_match('~/file/d/([a-zA-Z0-9_-]+)(?:/.*)?~', $path, $m)) {
                $url = $build_download($m[1]);
                if ($url) {
                    return $url;
                }
            }

            if (!empty($q['id'])) {
                $url = $build_download($q['id']);
                if ($url) {
                    return $url;
                }
            }

            if (trim($path, '/') === 'uc' && !empty($q['id'])) {
                $url = $build_download($q['id']);
                if ($url) {
                    return $url;
                }
            }

            return new WP_Error('unsupported', 'Enlace de Drive no válido.');
        }

        return new WP_Error('unsupported', 'Origen no soportado.');
    }
}

if (!function_exists('flacso_handle_legacy_pdf_proxy_query')) {
    /**
     * Compatibilidad con enlaces legacy: ?flacso_pdf_proxy=1&doc_id=...
     * Redirige al endpoint AJAX existente para servir el PDF inline.
     */
    function flacso_handle_legacy_pdf_proxy_query()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (empty($_GET['flacso_pdf_proxy'])) {
            return;
        }

        $doc_id = isset($_GET['doc_id']) ? sanitize_text_field(wp_unslash($_GET['doc_id'])) : '';
        $doc_id = preg_replace('~[^a-zA-Z0-9_-]~', '', (string) $doc_id);

        if ($doc_id === '') {
            status_header(400);
            echo 'Falta doc_id en la URL.';
            exit;
        }

        $source_url = sprintf('https://drive.google.com/file/d/%s/view', $doc_id);
        $proxy_url  = flacso_get_pdf_proxy_url($source_url, 'documento');

        if (!$proxy_url) {
            status_header(400);
            echo 'No se pudo construir la URL del proxy PDF.';
            exit;
        }

        wp_safe_redirect($proxy_url, 302);
        exit;
    }
}

add_action('template_redirect', 'flacso_handle_legacy_pdf_proxy_query', 0);
