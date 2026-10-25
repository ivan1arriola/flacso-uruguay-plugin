# Auditoría técnica de `flacso-uruguay-plugin`

Fecha: 2026-10-25 · Revisión de código estática + suite unitaria + contratos F0 + análisis de dependencias de runtime.  
Alcance: Todo `flacso-uruguay-plugin` (369 archivos PHP, 20 módulos y subdirectorios de arquitectura, excluyendo worktrees git en `.worktrees/`).  

Convención de evidencia: `ruta:línea` relativa a `flacso-uruguay-plugin/`.  
Etiquetas: **[CONFIRMADO]** = verificado inspeccionando el código fuente o ejecutando pruebas; **[A VERIFICAR]** = inferido, requiere validación en entorno de base de datos o staging en producción.

---

## 0. Resumen ejecutivo

**Estado general.** El plugin cuenta con componentes modernos de alta calidad arquitectónica (notablemente el pipeline transaccional de consultas hacia PostgreSQL/Mautic bajo principios de arquitectura limpia, y el serializador del catálogo académico). La suite de pruebas actual aprueba 116 tests unitarios en PHP y 8 pruebas de contratos F0 sin fallos de sintaxis (`php -l` limpio en los 369 archivos).

**Sin embargo, el plugin presenta una acumulación severa de deuda técnica por migraciones incompletas y fallas críticas de seguridad activas en producción:**

| # | Problema | Severidad | Efecto |
|---|---|---|---|
| 1 | **Path Traversal / LFI en Proxy de PDFs (F-V01)** | **Crítica** | Un usuario anónimo puede leer archivos arbitrarios del servidor (`wp-config.php`, claves secretas) vía AJAX. |
| 2 | **Script huérfano de exportación de consultas (F-V03)** | **Crítica** | `fc-export-consultas.php` en la raíz web expone toda la base de datos de consultas (PII) sin autenticación en un JSON público. |
| 3 | **SSRF no autenticado en Proxy de PDFs (F-V02)** | **Alta** | Permite sondeo de red interna y peticiones HTTP hacia servicios de infraestructura privada (`169.254.169.254`, `127.0.0.1`). |
| 4 | **Stored XSS y Escalada de Privilegios en Raw Content API (F-V04)** | **Alta** | Desactiva filtros KSES para cualquier usuario con `edit_posts` sin verificar `unfiltered_html` ni autoría del post. |
| 5 | **HTTP síncrono en Manejador Global de Errores PHP (F-F01)** | **Alta** | Cualquier notice/warning de PHP dispara llamadas HTTP síncronas a Telegram, congelando la carga del sitio. |
| 6 | **Cuádruple subsistema concurrente de consultas (F-D01)** | **Alta** | 4 motores distintos capturan consultas hacia destinos dispares (Postgres, CPT `fc_consulta`, Google Sheets, DB custom). |
| 7 | **Capa completa de arquitectura DDD abandonada (F-M01)** | **Alta** | 29 clases en `modules/academico/logica/` jamás se cargan ni se usan en el plugin en tiempo de ejecución. |
| 8 | **Detección de `REST_REQUEST` prematura en ciclo de vida WP (F-F02)** | **Alta** | Comprobación en `plugins_loaded` antes de que WordPress defina la constante, provocando fallos de carga en rutas REST. |

---

## 1. Método y resultados de las comprobaciones automáticas

| Comprobación | Herramienta / Comando | Resultado |
|---|---|---|
| **Sintaxis PHP** | `Get-ChildItem -Recurse *.php \| php -l` | **369/369 archivos OK** (0 errores sintácticos) |
| **Tests Unitarios PHP** | `tests/run-all-tests.php` | **116/116 tests pasados**, 0 fallos |
| **Contratos F0** | `python -m unittest tests/test_f0_contracts.py` | **8/8 tests pasados**, 0 fallos |
| **Codificación de Archivos** | `python .github/scripts/check_encoding.py` | **OK** (UTF-8 limpio, sin BOM ni mojibake) |
| **Auditoría de Clases Duplicadas** | `python .github/scripts/f0_audit.py` | **Alerta:** Colisión detectada en `Flacso_Main_Page_Unified_Settings` |
| **Advertencias en Pruebas** | Ejecución con PHP 8.5.11 | Advertencias por `ReflectionMethod::setAccessible()` deprecado, `Undefined array key "anio_inicio"` en catálogo y variable no inicializada en `seminario-integrado-workload-registration-test.php:21`. |

---

## 2. Código Vulnerable (Seguridad e Integridad)

### [F-V01] Lectura Arbitraria de Archivos (Path Traversal / LFI) en Proxy de PDFs
- **Severidad:** **Crítica** **[CONFIRMADO]**
- **Ubicación:** `modules/shortcodes/includes/pdf-proxy.php:36-44, 261-290, 408-412`
- **Ruta Afectada:** AJAX no autenticado `wp_ajax_nopriv_flacso_view_pdf` y autenticado `wp_ajax_flacso_view_pdf`.
- **Mecánica:**
  La acción AJAX procesa cualquier parámetro `url` recibido por `GET`/`POST`. Si el path contiene `/wp-content/uploads/`, delega en `flacso_map_wp_uploads_file($path)`:
  ```php
  // Líneas 263-268
  $rel = preg_replace('#^/wp-content/uploads/#i', '', $path);
  $file = $uploads['basedir'] . '/' . ltrim($rel, '/');
  if (file_exists($file)) {
      return $file;
  }
  ```
  El código no canóniza la ruta resultante con `realpath()` ni verifica que la ruta resuelta permanezca dentro de `$uploads['basedir']`.
  Posteriormente, `flacso_serve_pdf_file($file)` abre el archivo directamente con `fopen($file, 'rb')` y envía su contenido al navegador con `fread()`.
- **Impacto:** Un usuario anónimo puede solicitar `/wp-admin/admin-ajax.php?action=flacso_view_pdf&url=/wp-content/uploads/../../../../wp-config.php` y obtener las credenciales de la base de datos, contraseñas y claves de encriptación del sitio.
- **Remediación:**
  ```php
  $real_base = wp_normalize_path(realpath($uploads['basedir']));
  $real_file = wp_normalize_path(realpath($file));
  if (!$real_file || strpos($real_file, $real_base . '/') !== 0) {
      wp_die('Acceso denegado.', 'Forbidden', ['response' => 403]);
  }
  ```

---

### [F-V02] SSRF y Sondeo de Red Interna en Proxy de PDFs
- **Severidad:** **Alta** **[CONFIRMADO]**
- **Ubicación:** `modules/shortcodes/includes/pdf-proxy.php:59-71, 402-404`
- **Mecánica:**
  En `flacso_resolve_pdf_export_url()`, la línea 402 evalúa si la URL termina en `.pdf`:
  ```php
  if (preg_match('/\.pdf(\?.*)?$/i', $url)) {
      return $url;
  }
  ```
  Esto se ejecuta **antes** de validar la lista blanca de dominios seguros (Google Docs / Drive). Luego, `flacso_fetch_pdf_url()` descarga el recurso usando `wp_remote_get()` en lugar de `wp_safe_remote_get()`:
  ```php
  $res = wp_remote_get($url, [
      'timeout' => 30,
      'sslverify' => true,
  ]);
  ```
- **Impacto:** Permite a un atacante obligar al servidor a realizar peticiones HTTP hacia servicios locales o de red interna (`127.0.0.1`, endpoints de metadata de nube `169.254.169.254`, instancias Redis, Docker o bases de datos internas).
- **Remediación:**
  1. Utilizar estrictamente `wp_safe_remote_get()` (que descarta peticiones a redes privadas y loopback).
  2. Forzar lista blanca de dominios permitidos independientemente de si la extensión es `.pdf`.

---

### [F-V03] Exfiltración Masiva de Datos Personales (PII) en Raíz Web
- **Severidad:** **Crítica** **[CONFIRMADO]**
- **Ubicación:** `fc-export-consultas.php:1-105` (ubicado directamente en la raíz del plugin).
- **Mecánica:**
  El script no contiene comprobación `defined('ABSPATH')`, no valida sesión de usuario (`is_user_logged_in()`), no comprueba capacidades (`current_user_can()`) ni verifica ejecución por CLI (`php_sapi_name() === 'cli'`).
  Al ser solicitado por HTTP por cualquier atacante:
  1. Ubica y carga `wp-load.php`.
  2. Ejecuta `get_posts(['post_type' => 'fc_consulta', 'posts_per_page' => -1])`.
  3. Extrae nombres, correos electrónicos, teléfonos, mensajes, IPs y user-agents.
  4. Genera el archivo plano `consultas_export.json` en la raíz del plugin (accesible públicamente).
  5. Imprime en pantalla la ruta física absoluta del servidor.
- **Impacto:** Violación flagrante de protección de datos personales y fuga íntegra de la base histórica de prospectos de FLACSO Uruguay.
- **Remediación:** Eliminar el archivo de inmediato del repositorio y servidores productivos, o bloquear su acceso web:
  ```php
  if (php_sapi_name() !== 'cli' || !defined('ABSPATH')) {
      http_response_code(403);
      exit('Acceso denegado.');
  }
  ```

---

### [F-V04] Stored XSS y Escalada de Privilegios en Raw Content API
- **Severidad:** **Alta** **[CONFIRMADO]**
- **Ubicación:** `modules/main-page/includes/flacso-raw-content-api.php:116, 134-174`
- **Endpoint:** `POST /wp-json/flacso/v1/update-content`
- **Mecánica:**
  El endpoint solo comprueba la capacidad genérica `edit_posts`:
  ```php
  'permission_callback' => function() {
      return current_user_can('edit_posts');
  }
  ```
  No comprueba si el usuario puede editar el ID específico (`edit_post`, `$post_id`). Además, en las líneas 154-155:
  ```php
  kses_remove_filters(); // Desactiva completamente wp_filter_post_kses
  $post_id = wp_update_post([...]);
  kses_init_filters();
  ```
- **Impacto:** Cualquier usuario con rol de Colaborador o Autor puede actualizar posts ajenos o páginas estáticas institucionales inyectando payloads de JavaScript malicioso (`<script>`, `<iframe>`, atributos `onload`), atacando las sesiones de administradores.
- **Remediación:**
  Exigir `current_user_can('edit_post', $post_id)` y condicionar la eliminación de filtros KSES a `current_user_can('unfiltered_html')`.

---

### [F-V05] IDOR y Exposición de Metadatos Privados
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `modules/main-page/includes/flacso-raw-content-api.php:13-23, 110-132`
  - `modules/preinscripciones/includes/class-preinscriptions-serializer.php:188, 282`
- **Mecánica:**
  - `flacso-raw-content-api.php` expone el campo `raw_content` en el contexto público de lectura (`view`) y permite a cualquier redactor leer borradores privados sin validar autoría.
  - El catálogo público de preinscripciones (`/wp-json/flacso/v1/preinscripciones`) expone la URL interna de edición administrativa:
    `$urls['edit'] = admin_url('post.php?post=' . $edition_id . '&action=edit')`.
- **Impacto:** Divulgación de la topología interna del panel de administración y fuga de contenido de borradores no publicados.
- **Remediación:** Omitir `$urls['edit']` en respuestas públicas de la API REST a menos que el usuario tenga privilegios de edición autenticados.

---

### [F-V06] Suplantación de Dirección IP (IP Spoofing) en Consultas
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicación:** `modules/main-page/includes/flacso-consultas.php:80-100`
- **Mecánica:**
  La función `flacso_get_user_ip()` prioriza cabeceras cliente arbitrarias (`HTTP_X_REAL_IP`, `HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`) por sobre `REMOTE_ADDR`, sin validar una lista de proxys inversos autorizados.
- **Impacto:** Falseamiento de registros de auditoría y evasión de filtros geográficos o listas de exclusión.
- **Remediación:** Utilizar exclusivamente `$_SERVER['REMOTE_ADDR']` o validar proxies confiables mediante configuración explícita.

---

### [F-V07] Omisión Permanente de Nonces CSRF en Consultas
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicación:** `modules/main-page/includes/flacso-consultas.php:53, 56, 1042-1050`
- **Mecánica:**
  Constantes configuradas por defecto:
  ```php
  define('FLACSO_USE_NONCE', false);
  define('FLACSO_RELAXED_MODE', true);
  ```
  La línea 1042 implementa un modo relajado donde, aun fallando la verificación del token, solo emite un mensaje de advertencia al log y continúa con la inserción y despacho de la consulta.
- **Impacto:** Exposición al envío de consultas no consentidas vía Cross-Site Request Forgery (CSRF).
- **Remediación:** Forzar `FLACSO_USE_NONCE` a `true` y responder con error HTTP 403 ante nonces inválidos.

---

### [F-V08] DoS y Amplificación por Consultas Múltiples no Limitadas
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicación:** `modules/main-page/includes/flacso-consultas.php:1117-1216`
- **Mecánica:**
  El endpoint AJAX acepta arrays sin límite en `selected_offers` o `selected_offer_ids`. Para cada elemento del array ejecuta inserciones en BD, llamadas síncronas a webhooks externos y notificaciones.
- **Impacto:** Un payload malicioso con 100 IDs agota los workers de PHP-FPM y genera bloqueos en la base de datos.
- **Remediación:** Imponer un límite estricto (ej. máximo 5 ofertas por solicitud) antes de iniciar el procesamiento.

---

## 3. Código Muerto (Huérfano, Inaccesible y No Invocado)

### [F-M01] Capa Completa de Arquitectura DDD Abandonada
- **Severidad:** **Alta** **[CONFIRMADO]**
- **Ubicación:** `modules/academico/logica/` (29 archivos de lógica de dominio).
- **Entidades:** `Cohorte.php`, `Edicion.php`, `Diploma.php`, `Matricula.php`, `PeriodoAcademico.php`, `CrearCohorte.php`, `MatricularEstudiante.php`, interfaces y repositorios.
- **Diagnóstico:**
  En `flacso-uruguay.php:166-186`, el módulo `academico` **no es cargado por `$loader->load_module()`**. El sistema utiliza en producción la implementación basada en Custom Post Types de `modules/oferta-academica/` y `modules/seminarios/`. La capa DDD entera solo es consumida por 2 tests aislados en `tests/`.
- **Acción:** Purgar el directorio o trasladarlo formalmente a un paquete de dominio externo si forma parte de un refactor futuro.

---

### [F-M02] Módulos "Shim" / Fantasmas Desconectados
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `modules/site/init.php`
  - `modules/telegram/init.php`
  - `modules/instagram/init.php`
- **Diagnóstico:**
  Módulos no inicializados en el núcleo. La integración de Telegram está en `modules/main-page/includes/class-flacso-telegram-manager.php`, y la de Instagram en `modules/main-page/includes/class-flacso-instagram-post-importer.php`.
- **Acción:** Eliminar estos 3 directorios obsoletos.

---

### [F-M03] Administrador Legacy y Seeder Huérfano en Posgrados
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `modules/posgrados/includes/admin/class-flacso-posgrados-admin-page.php` (1.304 líneas)
  - `modules/posgrados/includes/admin/class-flacso-posgrados-seeder.php`
- **Diagnóstico:**
  `modules/posgrados/init.php` jamás requiere ni instancia estas clases. El seeder contiene IDs hardcodeados de un entorno de pruebas inexistente.
- **Acción:** Eliminar ambos archivos del módulo `posgrados`.

---

### [F-M04] Scripts de Depuración y Diagnóstico en Raíz Web
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `debug-blocks.php`
  - `debug-oferta.php`
  - `test-schema.php`
- **Diagnóstico:**
  Archivos de prueba manual utilizados durante desarrollo, expuestos públicamente en el directorio raíz del plugin.
- **Acción:** Eliminar o mover a un directorio no accesible por web (`bin/` o `scripts/debug/`).

---

### [F-M05] Cliente Google Gmail API OAuth2 Inerte
- **Severidad:** **Baja** **[CONFIRMADO]**
- **Ubicación:** `modules/formularios/includes/gmail.php:1-180`
- **Diagnóstico:**
  Cliente manual de autenticación JWT para cuentas de servicio de Google. Solo es llamado por un botón de prueba en la interfaz de ajustes de `formularios`. No participa en el flujo real de entrega de consultas.
- **Acción:** Retirar el archivo y utilizar el transporte de correo estándar de WordPress.

---

### [F-M06] Metadatos y Columnas Obsoletas de Mailjet
- **Severidad:** **Baja** **[CONFIRMADO]**
- **Ubicación:** `includes/database/repositories/class-flacso-base-inquiry-repository.php:106-146`
- **Diagnóstico:**
  Mapeos residuales de columnas `mailjetMessageId` y `mailjetMessageUuid`, remanentes de una integración discontinuada en favor de Mautic.
- **Acción:** Purgar las referencias del repositorio base.

---

## 4. Código Divergente (Implementaciones Duplicadas y Bifurcación)

### [F-D01] Cuádruple Subsistema Concurrente de Gestión de Consultas
- **Severidad:** **Alta** **[CONFIRMADO]**
- **Diagnóstico:**
  Existen cuatro motores concurrentes y desconectados para registrar consultas de usuarios:
  1. **Motor Moderno (`modules/consultas/`):** Clean Architecture, conexión PostgreSQL mediante PDO, DTOs y cola hacia Mautic.
  2. **Motor CPT Legacy (`modules/formularios/`):** Almacena entradas en CPT `fc_consulta` (`wp_posts`), envía correos por `wp_mail` y notifica a Telegram.
  3. **Motor Procedimental (`modules/main-page/includes/flacso-consultas.php`):** 2.211 líneas procedimentales que guardan en tablas SQL personalizadas y despachan a Google Sheets vía webhooks síncronos.
  4. **Formulario de Oferta Académica (`modules/oferta-academica/includes/class-oferta-consulta-form.php`):** Captura desacoplada de los repositorios centrales.
- **Impacto:** Las consultas de los estudiantes se bifurcan entre bases de datos distintas (Postgres vs MySQL), provocando inconsistencias en métricas y pérdida de prospectos.
- **Acción:** Consolidar todos los formularios para que despachen exclusivamente a través del servicio transaccional de `modules/consultas/`.

---

### [F-D02] Modelo Dual de Tablas de Precios
- **Severidad:** **Media** **[CONFIRMADO]**
- **Diagnóstico:**
  - En `modules/oferta-academica/`, los precios se gestionan mediante el CPT `tabla-precio` asociado a cohortes.
  - En `modules/shortcodes/init.php` y `shortcodes-precios.php`, los precios se leen de la opción serializada `flacso_price_tables` en `wp_options`.
- **Impacto:** Actualizar los precios en la oferta académica no actualiza los shortcodes de precios en el sitio.

---

### [F-D03] Triple Implementación de Guardado en `modules/main-page/`
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `Flacso_AJAX_Handler::save_section` (`class-flacso-ajax-handler.php`)
  - `Flacso_AJAX_Settings::save_settings_section` (`class-flacso-ajax-settings.php`)
  - `Flacso_Main_Page_REST_API::update_settings` (`class-flacso-main-page-rest-api.php`)
- **Diagnóstico:**
  Tres rutas distintas guardan las mismas opciones de portada con validaciones, sanitizaciones y nonces dispares (`flacso-settings-ajax` vs `flacso-settings-nonce`).
- **Acción:** Unificar el guardado en la API REST y retirar los endpoints AJAX legacy.

---

### [F-D04] Colisión de Clases Idénticas en el Repositorio
- **Severidad:** **Baja** **[CONFIRMADO]**
- **Diagnóstico:**
  La clase `Flacso_Main_Page_Unified_Settings` se declara simultáneamente en `class-flacso-main-page-editor-bridge.php` y `class-flacso-main-page-unified-settings.php`, mitigado precariamente con `if (!class_exists(...))`.

---

## 5. Código Repetido (Redundancias y Copy-Paste)

### [F-R01] Validación Telefónica y de Países Replicada 4 Veces
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `modules/formularios/includes/validation.php:12-48`
  - `modules/main-page/includes/flacso-consultas.php:140-195`
  - `modules/charlas-abiertas/includes/rest.php:45-72`
  - `modules/consultas/services/class-flacso-inquiry-context-service.php:60-95`
- **Diagnóstico:**
  La normalización de prefijos internacionales (`+598`) y expresiones regulares de números uruguayos (`^09[0-9]{7}$`) están copiadas y pegadas en 4 módulos con divergencias menores.
- **Acción:** Centralizar en `Flacso_Phone_Normalizer` en `includes/core/helpers.php`.

---

### [F-R02] Notificador de Telegram Reimplementado en 3 Capas
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicaciones:**
  - `includes/core/class-flacso-error-notifier.php:75-97`
  - `modules/main-page/includes/class-flacso-telegram-manager.php:110-145`
  - `modules/formularios/includes/telegram.php:20-65`
- **Diagnóstico:**
  Tres clases independientes formatean mensajes y llaman a `https://api.telegram.org/bot{token}/sendMessage`.
- **Acción:** Unificar en una única clase de servicio `Flacso_Telegram_Client`.

---

### [F-R03] Registro CPT y Metaboxes Duplicados en Cohortes y Ediciones
- **Severidad:** **Baja** **[CONFIRMADO]**
- **Ubicaciones:**
  - `modules/oferta-academica/includes/class-cohorte.php`
  - `modules/seminarios/includes/class-edicion.php`
- **Diagnóstico:**
  Más de 200 líneas idénticas de registro de metaboxes, sanitización de fechas de inicio y guardado de cupos.

---

## 6. Código Frágil (Fragilidad, Concurrencia y Performance)

### [F-F01] HTTP Síncrono Bloqueante en Manejador Global de Errores PHP
- **Severidad:** **Alta** **[CONFIRMADO]**
- **Ubicación:** `includes/core/class-flacso-error-notifier.php:24, 75-97`
- **Mecánica:**
  En `flacso-uruguay.php`, el plugin registra `set_error_handler([$this, 'handle_error'])`. Ante cualquier `E_USER_WARNING` o `E_NOTICE`, el manejador ejecuta de forma inmediata una llamada HTTP `wp_remote_post()` síncrona hacia Telegram.
- **Impacto:** Si la API de Telegram experimenta latencia o cortes, la carga de cualquier página de WordPress se congela por 5-10 segundos por cada advertencia de PHP. Si ocurre un bucle con notices, agota las conexiones del servidor web.
- **Remediación:** Eliminar peticiones de red del interior del error handler. Almacenar los eventos en un buffer de memoria y despacharlos en `register_shutdown_function` o mediante una cola asíncrona con limitación de frecuencia.

---

### [F-F02] Fallo de Temporización de Ciclo de Vida: Detección Prematura de `REST_REQUEST`
- **Severidad:** **Alta** **[CONFIRMADO]**
- **Ubicación:** `modules/main-page/init.php:74-77`
- **Mecánica:**
  ```php
  // Ejecutado en plugins_loaded:
  if (defined('REST_REQUEST') && REST_REQUEST) {
      $this->load_rest_api();
  }
  ```
- **Diagnóstico:**
  La constante `REST_REQUEST` **no existe en `plugins_loaded`**; WordPress la define durante `parse_request`. Como resultado, la API REST de portada no se inicializa de forma fiable bajo el ciclo de vida estándar.
- **Remediación:** Enganchar la carga a `add_action('rest_api_init', [$this, 'load_rest_api'])`.

---

### [F-F03] Problema N+1 en Serializador de Preinscripciones
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicación:** `modules/preinscripciones/includes/class-preinscriptions-serializer.php:14-68`
- **Mecánica:**
  El método ejecuta `get_posts(['posts_per_page' => -1])` y luego, en un bucle `foreach`, realiza consultas individuales `get_post_meta()` y `get_post()` por cada edición y cohorte.
- **Impacto:** Genera cientos de queries SQL por cada consulta al endpoint `/wp-json/flacso/v1/preinscripciones`.
- **Remediación:** Utilizar `update_meta_cache('post', $edition_ids)` antes del bucle para hidratar la caché de WordPress en una sola consulta SQL.

---

### [F-F04] Acoplamiento Frágil a WP-Cron para Entrega de Consultas
- **Severidad:** **Media** **[CONFIRMADO]**
- **Ubicación:** `modules/consultas/services/class-flacso-inquiry-delivery-worker.php:18-45`
- **Mecánica:**
  La sincronización con Mautic y PostgreSQL depende exclusivamente del evento programado de WP-Cron `flacso_inquiry_process_queue`.
- **Impacto:** Si el sitio no recibe visitas frecuentes, WP-Cron no despierta, acumulando consultas sin sincronizar durante horas.
- **Remediación:** Documentar y configurar la invocación de `wp-cron.php` desde el crontab del sistema operativo cada 5 minutos.

---

## 7. Código Incongruente (Contradicciones Arquitectónicas)

### [F-I01] "Mautic Exclusivo" Contradicho por Código Activo
- **Diagnóstico:** **[CONFIRMADO]**
  Los documentos de arquitectura establecen que Mautic es la única plataforma transaccional de contactos. Sin embargo, persisten conectores directos a la API de Gmail en `modules/formularios/includes/gmail.php`, envíos concurrentes mediante `wp_mail()` y campos para APIs de Mailjet en el repositorio base.

---

### [F-I02] Violación de Responsabilidades en Shortcodes
- **Diagnóstico:** **[CONFIRMADO]**
  El archivo `README.md` prohíbe que el módulo `shortcodes` contenga lógica de negocio. Sin embargo, `modules/shortcodes/init.php` contiene 911 líneas con cálculos de precios, validación de condiciones de admisión y parsing de esquemas.

---

### [F-I03] Incompatibilidad de Versión PHP Declarada
- **Diagnóstico:** **[CONFIRMADO]**
  El encabezado del plugin declara `Requires PHP: 7.4`. No obstante, el código actual incluye tipados y estructuras de PHP 8.1+, mientras que la suite de pruebas arroja advertencias de deprecación en PHP 8.4+ por parámetros opcionales mal ordenados (`class-academic-settings-api.php:50`).

---

## 8. Plan de Remediación Priorizado (Roadmap)

### Fase 1: Hotfixes de Seguridad Críticos (Plazo: 0 a 48 horas)
- [ ] **[F-V01]** Parchear `pdf-proxy.php` aplicando `realpath()` estricto contra `$uploads['basedir']`.
- [ ] **[F-V02]** Cambiar `wp_remote_get()` por `wp_safe_remote_get()` e imponer lista blanca de dominios en `pdf-proxy.php`.
- [ ] **[F-V03]** Eliminar `fc-export-consultas.php` de la raíz del plugin.
- [ ] **[F-V04]** Añadir verificación de capacidad `edit_post` y restringir `kses_remove_filters()` a usuarios con `unfiltered_html` en `flacso-raw-content-api.php`.
- [ ] **[F-F01]** Desactivar peticiones HTTP síncronas en `class-flacso-error-notifier.php`.

### Fase 2: Purgado de Código Muerto y Huérfano (Plazo: Semana 1)
- [ ] **[F-M01]** Retirar el subdirectorio `modules/academico/logica/` inactivo.
- [ ] **[F-M02]** Eliminar módulos fantasmas `modules/site/`, `modules/telegram/`, `modules/instagram/`.
- [ ] **[F-M03]** Purgar `class-flacso-posgrados-admin-page.php` y su seeder en `modules/posgrados/`.
- [ ] **[F-M04]** Mover scripts de depuración de raíz (`debug-*.php`, `test-schema.php`) a `scripts/debug/`.
- [ ] **[F-M05]** Eliminar el cliente Google Gmail API en `modules/formularios/includes/gmail.php`.

### Fase 3: Unificación y Desacoplamiento (Plazo: Semanas 2 a 3)
- [ ] **[F-D01]** Unificar la captura de formularios dirigiendo todo tráfico al repositorio de `modules/consultas/`.
- [ ] **[F-D03]** Consolidar endpoints de configuración de portada en la API REST, eliminando AJAX duplicado.
- [ ] **[F-F02]** Mover el registro REST de portada al hook canónico `rest_api_init`.
- [ ] **[F-F03]** Implementar `update_meta_cache()` en `class-preinscriptions-serializer.php`.
- [ ] **[F-R01]** Centralizar la normalización telefónica en `Flacso_Phone_Normalizer`.


---

## 9. Estado de remediación (2026-10-25)

Verificación posterior a los cambios: `python scripts/agent_check.py` → **EXITO** (366 archivos PHP, 8 contratos F0, 119 tests PHP; se añadieron 3 suites de regresión).
Los cambios están en el árbol de trabajo, **sin commit ni despliegue**.

### Corregido

| Hallazgo | Cambio |
|---|---|
| F-V01 | `flacso_map_wp_uploads_file()` canoniza con `realpath()`, exige que el archivo siga dentro de `uploads` y que sea `.pdf`; decodifica la ruta y compara el host. Nota: la URL llega por `src` (base64), no por `url`. |
| F-V02 | La lista blanca de hosts (sitio, `docs.google.com`, `drive.google.com`) se evalúa **antes** del atajo `.pdf`; los hosts externos se piden con `wp_safe_remote_get()`. El host propio sigue con `wp_remote_get()` por si resuelve a IP interna. |
| F-V03 | Eliminado `fc-export-consultas.php`. El `rsync --delete` del despliegue lo quita también de producción. |
| F-V04 | Se exige `edit_post` sobre el ID concreto y se dejó de desactivar KSES (WordPress ya lo omite para quien tiene `unfiltered_html`). |
| F-V05 (parcial) | La lectura de `raw-content` exige `edit_post`. Nuevo: el campo REST `raw_content`/`plain_text` ya no devuelve contenido de posts protegidos con contraseña. |
| F-V06 | `flacso_get_user_ip()` usa `REMOTE_ADDR` y solo acepta cabeceras de reenvío si la conexión viene de red privada/loopback o de un proxy declarado en el filtro `flacso_trusted_proxies`. |
| F-V08 | Máximo 5 ofertas por solicitud (`flacso_consultas_max_offers`) y límite de 20 envíos/10 min por IP (`flacso_consultas_rate_limit`). |
| F-F01 | `handle_php_error()` ya no hace red: encola (máx. 5 por petición, sin duplicados) y envía al cierre, tras `fastcgi_finish_request()`. Si todos los canales fallan se pausa el envío 5 min. |
| F-F02 | Nuevo helper `flacso_is_rest_request()` (inspecciona la URL) usado en `main-page/init.php` y en el loader de portada. |
| F-F03 | Precalentado en bloque de los posts padre (`_prime_post_caches`). El endpoint ya estaba cacheado en transient y los metas de cohortes/ediciones ya venían precargados. |
| F-M03 | Eliminados admin legacy, seeder y `class-flacso-posgrados-plugin.php` (ya retirados por contrato y sin ningún `require`). |
| F-D04 | Eliminado `class-flacso-main-page-editor-bridge.php`: ningún archivo lo cargaba; la clase vigente es `unified-settings`. |
| F-I03 (parcial) | Nullable implícito en `get_settings()`, índice `anio_inicio` indefinido en el catálogo, y avisos de PHP 8.5 en tests. |
| Nuevo | `docentes/class-docente-meta.php`: `save_meta` guardaba sin nonce ni capacidad; ahora exige ambos. |

### Corrección de afirmaciones del informe

- **F-M02:** `site`, `instagram` y `telegram` **están** en `class-flacso-module-registry.php` (`site` es `required`, `main-page` depende opcionalmente de `instagram`, y `instagram/init.php` registra una sección de portada). No se eliminaron.
- **F-M04:** `debug-*.php` y `test-schema.php` están en `.gitignore` y excluidos del rsync de despliegue; solo existen en el equipo local.
- **F-I03:** no se encontró sintaxis exclusiva de PHP 8 en el código del plugin; `str_contains` y similares están cubiertos por el polyfill de WordPress 6.0.
- **F-D04:** no había `class_exists()` mitigando; el segundo archivo simplemente no se cargaba.
- **F-V05 (`urls.wordpress`):** no se tocó. Esa URL forma parte del contrato con el portal externo de preinscripciones y `class-preinscriptions-serializer.php` tiene trabajo en curso.

### No modificado (requiere decisión)

- **F-V07 (nonce):** forzar `FLACSO_USE_NONCE` rompería el formulario en páginas con caché (el propio código de `novedades` lo evita por esa razón). Conviene resolverlo junto con F-D01.
- **F-M01** (`modules/academico/logica/`): es una refactorización en progreso (confirmado por el equipo), no código muerto. No se elimina; el informe lo clasificó mal.
- **F-M05** (`gmail.php`): lo carga `formularios/init.php` y lo usa un botón de prueba de ajustes.
- **F-M06** (columnas Mailjet): tocar el mapeo del repositorio puede afectar lecturas de filas existentes.
- **F-D01, F-D02, F-D03, F-R01–R03, F-I01, F-I02, F-F04:** refactors o decisiones de arquitectura/infraestructura; se mantienen en el roadmap (F-F04: configurar `wp-cron.php` desde el crontab del sistema).
