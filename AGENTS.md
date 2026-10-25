# Reglas Maestras para Agentes de IA - FLACSO Uruguay Plugin

Este documento es la **fuente de verdad y directiva principal** para cualquier agente de inteligencia artificial (o desarrollador) que interactúe, diagnostique, edite o extienda el código de `flacso-uruguay-plugin`.

---

## 1. Identidad y Misión del Plugin

`flacso-uruguay-plugin` es el plugin central de WordPress que reúne todas las funcionalidades de negocio institucionales del sitio de **FLACSO Uruguay** (`flacso.edu.uy`):
- Modelo académico institucional: Programas, Ofertas Académicas, Cohortes, Seminarios, Ediciones, Docentes y Tablas de Precios.
- Portada modular (`main-page`) integrada con Kadence Theme y bloques dinámicos.
- Integración con el portal externo de preinscripciones (`https://preinscripciones.flacso.edu.uy`).
- Integración exclusiva con **Mautic** para recepción, seguimiento y automatización de consultas y campañas.

---

## 2. Flujo de Acción: Cómo hacer cambios en la web con solo pedírselo al agente

Cuando el usuario pide: *"Hacé tal cambio en la web"*, el agente debe identificar inmediatamente a cuál de las dos vías corresponde el pedido:

### Vía 1: Cambios de Código, Diseño, Lógica, Bloques o Plantillas
Aplica cuando el usuario pide: cambiar estilos, modificar cómo se muestra un componente, agregar un botón, crear un bloque de Gutenberg, modificar la lógica de un formulario o corregir un error del plugin.

1. **Editar el código**: Modifica los archivos en `modules/<dominio>/` o `includes/`.
2. **Validar automáticamente**: Ejecuta:
   ```bash
   python scripts/agent_check.py --diff
   ```
   Asegúrate de que el resultado sea `RESULTADO: [EXITO]`.
3. **Publicar a producción**:
   ```bash
   git add .
   git commit -m "Descripción clara del cambio"
   git push origin HEAD:main
   ```
4. **Despliegue automático**: El push a `main` activa `.github/workflows/deploy-plugin.yml`, que compila, verifica e instala automáticamente el plugin en el servidor de producción (`/var/www/clients/client2/web5/web/wp-content/plugins/flacso-uruguay-plugin`) vía SSH. En ~60 segundos el cambio está **EN VIVO** en `https://flacso.edu.uy`.

---

### Vía 2: Cambios de Contenido, Datos o Configuración de WordPress en Vivo
Aplica cuando el usuario pide: cambiar textos cargados en WordPress, actualizar el Hero de portada, modificar fechas de inicio de un seminario, crear o editar una Cohorte, cambiar aranceles de una tabla de precios o editar el contenido de una página/post.

El agente utiliza el cliente CLI integrado [`scripts/flacso_api.py`](scripts/flacso_api.py) (que ya cuenta con credenciales administrativas automáticas de WordPress REST API):

- **Verificar conexión y permisos**:
  ```bash
  python scripts/flacso_api.py whoami
  ```
- **Consultar datos actuales**:
  ```bash
  python scripts/flacso_api.py get /ofertas
  python scripts/flacso_api.py get /seminarios
  python scripts/flacso_api.py get /cohortes?parent_id=123
  python scripts/flacso_api.py get /main-page
  ```
- **Modificar datos en vivo**:
  ```bash
  # Modificar configuración de portada:
  python scripts/flacso_api.py post /main-page --json '{"hero":{"title":"Nuevo Titulo"}}'

  # Modificar una cohorte (fechas, estado, etc.):
  python scripts/flacso_api.py patch /cohortes/456 --json '{"fecha_inicio":"2027-03-15"}'

  # Actualizar contenido HTML directo de cualquier post o página:
  python scripts/flacso_api.py post /update-content --json '{"id": 123, "content": "<p>Nuevo contenido...</p>"}'
  ```
El cambio impacta **INMEDIATAMENTE en la base de datos de producción** sin necesidad de abrir el navegador ni acceder manualmente a `wp-admin`.

---

## 3. Principios de Arquitectura No Negociables

### A. Organización por Dominios (Domain-Driven)
El código se organiza estrictamente por **dominios de negocio de FLACSO**, nunca por tecnología o mecanismo de exposición:
- Cada dominio tiene su propio módulo dentro de `modules/` (ej. `oferta-academica/`, `seminarios/`, `docentes/`, `main-page/`, `formularios/`, `eventos/`).
- Dentro de la carpeta del módulo conviven sus endpoints REST, bloques de Gutenberg, metaboxes de administración, shortcodes y lógica de persistencia.
- **PROHIBIDO**: Crear carpetas globales agrupadas por tipo de archivo (ej. `shortcodes/shortcode-de-oferta.php`).
- **Módulos LEGACY**: `modules/shortcodes/` existe únicamente por compatibilidad con instalaciones anteriores. **NUNCA agregar código nuevo a este módulo**.

### B. Registro Central de Módulos (Single Source of Truth)
- `includes/core/class-flacso-module-registry.php` declara todos los módulos, dependencias obligatorias (`depends`), dependencias opcionales (`optional_depends`) y criticidad (`required`).
- Al crear un módulo nuevo, debe registrarse en `class-flacso-module-registry.php`.
- Usar `flacso_require()` para archivos críticos y `flacso_optional_require()` para dependencias opcionales.

### C. Entidades Débiles y Derivación de Etiquetas
- **`Cohorte`** pertenece a una **`OfertaAcademica`**. No tiene URL pública propia ni menú suelto (`show_in_menu => false`). Se administra desde la Oferta. Su número entero se deriva obligatoriamente a números romanos (`Cohorte VII`).
- **`Edicion`** pertenece a un **`Seminario`**. No tiene URL pública propia ni menú suelto (`show_in_menu => false`). Se administra desde el Seminario.
- Los docentes se asignan a la **Edición**, no a la cohorte.
- En `Seminario Integrado`, docentes, encuentros y horas se obtienen transitivamente de los componentes.

### D. Canales de Comunicación y Preinscripción
- **Mautic es el único canal operativo de comunicaciones**. WordPress recibe la consulta, la persiste para auditoría, sincroniza el contacto con Mautic y le asigna tags/segmentos. Los correos y secuencias se ejecutan desde Mautic.
- Las preinscripciones se realizan exclusivamente en `https://preinscripciones.flacso.edu.uy`. El plugin no tiene formularios de preinscripción locales ni lógica de pago local; solo expone el catálogo canónico en `/wp-json/flacso/v1/preinscripciones`.

---

## 3. Reglas Críticas de Integridad y Seguridad

1. **Codificación de Archivos (UTF-8 estricto sin BOM)**:
   - Todo archivo de texto debe guardarse en **UTF-8 sin BOM**.
   - Cero caracteres corruptos o mojibake (doble codificación UTF-8/ISO-8859). Un carácter corrupto rompe la serialización en base de datos y la exportación JSON.
   - Ejecutar siempre la verificación antes de confirmar cambios.
2. **Compatibilidad PHP**:
   - El código debe ser compatible con **PHP 7.4** y superior (hasta PHP 8.x).
   - No usar características exclusivas de PHP 8.1+ sin fallback (como enums nativos, `readonly` properties o intersection types).
3. **Seguridad en Base de Datos**:
   - **CERO queries SQL interpoladas directamente**: Usar `$wpdb->prepare()` para TODA consulta dinámica.
   - **Condiciones WHERE**: No interpolar `$where_sql` dentro de otra condición si ya contiene la palabra `WHERE`. Construir y reutilizar por separado el cuerpo de condiciones para evitar errores como `AND (WHERE ...)`.
4. **Manejo de Errores y Excepciones**:
   - Las excepciones de consultas analíticas o APIs no deben convertirse silenciosamente en `0 de 0`: registrar el error técnico detallado sin datos personales (sin PII) y mostrar un aviso operativo claro.
5. **Seguridad WordPress**:
   - Sanitizar siempre los datos de entrada (`sanitize_text_field`, `sanitize_email`, `absint`, `wp_kses_post`).
   - Escapar siempre al renderizar (`esc_html`, `esc_attr`, `esc_url`).
   - Verificar nonces (`check_ajax_referer`, `wp_verify_nonce`) y capacidades de usuario (`current_user_can('edit_posts')`) en peticiones administrativas y REST.

---

## 4. Estructura del Código

```text
flacso-uruguay-plugin/
├── flacso-uruguay.php                 # Bootstrap principal y constantes globales
├── AGENTS.md                          # ESTE DOCUMENTO (Guía maestra del agente)
├── API.md                             # Contrato oficial de la REST API
├── CHANGELOG.md                       # Historial de cambios
├── includes/
│   ├── core/
│   │   ├── loader.php                 # Motor de carga con resolución de dependencias
│   │   ├── class-flacso-module-registry.php # Declaración de módulos y dependencias
│   │   ├── requires.php               # Funciones flacso_require / flacso_optional_require
│   │   ├── helpers.php                # Helpers transversales
│   │   └── class-flacso-admin-panel.php # Menú administrativo FLACSO
│   └── database/                      # Repositorios y capas de acceso a datos
├── modules/
│   ├── oferta-academica/              # CPTs oferta-academica y cohorte
│   ├── seminarios/                    # CPTs seminario y edicion
│   ├── docentes/                      # CPT flacso_docente
│   ├── eventos/                       # CPT flacso_evento
│   ├── main-page/                     # Portada institucional, composición y Hero
│   ├── preinscripciones/              # Catálogo /wp-json/flacso/v1/preinscripciones
│   ├── consultas/                     # Bandeja de consultas y sync Mautic
│   ├── mailing/                       # Integración operativa Mautic
│   ├── formularios/                   # Formularios web y validaciones
│   ├── site/                          # Utilidades generales del sitio
│   └── shortcodes/                    # [LEGACY] Solo compatibilidad
├── scripts/
│   ├── agent_check.py                 # Herramienta de verificación para agentes
│   └── ...
├── tests/                             # Suites de pruebas de contrato y regresión
└── docs/                              # Especificaciones de arquitectura y dominio
    ├── agent-reference.md             # Referencia técnica rápida de CPTs, metas y hooks
    └── modelo-academico-final.md      # Modelo de datos final
```

---

## 5. Servidor MCP de Base de Datos (`flacso-db`)

El agente cuenta con acceso al servidor MCP **`flacso-db`** configurado en el workspace (`.agents/mcp_config.json`):
- Permite inspeccionar esquemas de tablas (`read_table_schema`, `get_schema_erd`).
- Permite buscar tablas y columnas (`find_tables_by_keyword`, `search_schema`).
- Permite ejecutar consultas de solo lectura (`run_select_query`).
- **Instrucción para el agente**: Si necesitas conocer los nombres de columnas o datos de prueba reales, utiliza las herramientas MCP de `flacso-db` en lugar de adivinar o asumir la estructura de la base de datos.

---

## 6. Playbooks de Desarrollo para Agentes

### Playbook 1: Añadir o modificar un Endpoint REST
1. Ubica el módulo de dominio correspondiente en `modules/<dominio>/`.
2. Registra la ruta en el hook `rest_api_init`:
   ```php
   register_rest_route('flacso/v1', '/mi-recurso', [
       'methods' => WP_REST_Server::READABLE,
       'callback' => [$this, 'get_items'],
       'permission_callback' => '__return_true', // o [$this, 'check_permission']
   ]);
   ```
3. Documenta el endpoint y sus parámetros en `API.md`.
4. Ejecuta `python scripts/agent_check.py --diff` para validar sintaxis y contratos.

### Playbook 2: Modificar o registrar una Sección de Portada (`main-page`)
1. Todo componente de portada pertenece a un módulo de dominio.
2. Registra la sección usando el filtro `flacso_homepage_sections`:
   ```php
   add_filter('flacso_homepage_sections', function (array $sections): array {
       $sections['mi_seccion'] = [
           'label' => __('Mi Sección', 'flacso-uruguay'),
           'function' => 'flacso_render_mi_seccion',
           'owner' => 'mi-modulo',
       ];
       return $sections;
   });
   ```
3. Asegura que la función de renderizado maneje estados vacíos de manera elegante y segura.

### Playbook 3: Modificar consultas o bandejas de datos
1. Cada cambio en `get_paginated_inquiries()` debe probar los modos `raw` y `grouped`, con y sin rango de fechas. La vista agrupada debe probar también la consulta de hijos.
2. Construir cláusulas `$where_sql` sin duplicar prefijos `WHERE`.
3. Probar la vista con la base `offer_inquiries` antes de darla por buena.

---

## 7. Protocolo Obligatorio de Verificación (Definition of Done)

Un agente de IA **NUNCA debe dar por terminada una tarea o hacer commit** sin ejecutar el verificador de calidad del proyecto.

### Comando de verificación rápida durante el trabajo:
```bash
python scripts/agent_check.py --diff
```
Analiza exclusivamente los archivos modificados en git, validando encoding, mojibake y sintaxis PHP en menos de 2 segundos.

### Comando de verificación completa antes de finalizar:
```bash
python scripts/agent_check.py
```
Ejecuta la suite completa:
1. Codificación UTF-8 limpia, sin BOM ni mojibake.
2. Validación de sintaxis PHP (`php -l`) en todos los archivos.
3. Pruebas de arquitectura F0 (Python unittest).
4. Suite de pruebas de contrato y regresión (95+ pruebas en `tests/*.php`).

**Criterio de aprobación**: El script debe devolver `RESULTADO: [EXITO]`. Si falla cualquier prueba, el agente debe leer el diagnóstico, corregir el código y volver a ejecutar la verificación.

---

## 8. Protocolo de Git y Despliegue a Producción

- **Rama principal**: `main` es la rama de producción.
- **Despliegue automatizado**: Cada push a `main` activa el workflow `.github/workflows/deploy-plugin.yml`, que calcula la versión, valida sintaxis y empaqueta y despliega el plugin por SSH/rsync en el servidor de producción (`/var/www/clients/client2/web5/web/wp-content/plugins/flacso-uruguay-plugin`).
- **Antes de publicar**:
  1. Ejecutar `git status` y `git diff` para revisar cambios no deseados.
  2. Ejecutar `python scripts/agent_check.py` y verificar que pase al 100%.
  3. Comparar con `origin/main`.
  4. Realizar commit con mensaje descriptivo y enviar con `git push origin HEAD:main`.
- **Después de publicar**:
  1. Verificar la ejecución del workflow de GitHub Actions.
  2. Verificar que no haya errores de activación o fatales en WordPress.
  3. Las ramas temporales o worktrees solo se eliminan después de verificar que sus cambios están integrados en `main`.
