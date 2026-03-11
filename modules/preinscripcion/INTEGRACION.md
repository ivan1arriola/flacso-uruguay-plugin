# Módulo Preinscripciones - Integración Completa

## 📦 Estado: ✅ INTEGRADO Y OPERATIVO

---

## 🎯 Resumen Ejecutivo

El módulo **Preinscripciones** está completamente integrado en el plugin FLACSO Uruguay con todas las funcionalidades requeridas:

✅ Panel de administración con 3 pasos  
✅ Gestión de webhook (Google Apps Script)  
✅ Definición de categorías de programas  
✅ Activación/desactivación de preinscripción por programa  
✅ URLs virtuales dinámicas (`/programa/preinscripcion/`)  
✅ Formulario automático con validación  
✅ Integración Google Sheets  
✅ Sistema de migración de datos  
✅ Interfaz responsive

---

## 📍 Ubicación

```
flacso-uruguay/
└── modules/
    └── preinscripcion/
        ├── init.php
        ├── GUIA_PREINSCRIPCIONES.md
        ├── FEATURES.md
        ├── includes/
        │   ├── class-formulario-preinscripcion.php
        │   ├── trait-admin.php
        │   ├── trait-render.php
        │   ├── trait-templates.php
        │   ├── trait-assets.php
        │   └── trait-migracion.php
        ├── templates/
        │   └── preinscripcion.php
        └── assets/
            ├── css/
            └── js/
```

---

## 🚀 Activación

**En flacso-uruguay.php:**
```php
$loader->load_module('preinscripcion');
```

**En modules/preinscripcion/init.php:**
```php
add_action('init', function() {
    FLACSO_Formulario_Preinscripcion_Final::get_instance();
}, 5);
```

**Orden de inicialización:** Priority 5 (antes que otros módulos)

---

## 🎨 Panel de Administración

### Ubicación
```
WordPress Admin → Preinscripciones → Gestión de Preinscripciones
```

### Paso 1: Webhook
```
📍 Campo: Webhook URL
   Tipo: URL (text input)
   Validación: esc_url_raw()
   Almacenamiento: wp_options
   Opción: flacso_preinscripciones_webhook_url
```

**Funcionalidad:**
- Guardar URL de Google Apps Script
- Validar URL antes de guardar
- Enviar datos de formularios automáticamente

### Paso 2: Categorías
```
📍 Nombres de Categoría: Dinámicos
   Tipos: Diplomados, Maestrías, Especializaciones
   
   Campos por Categoría:
   - Nombre (text input)
   - Página Padre (dropdown)
   - Validación (✓/✗)
```

**Funcionalidad:**
- Definir categorías principales
- Asignar páginas padre de WordPress
- Validar existencia de páginas
- Agregar nuevas categorías

**Almacenamiento:**
```php
wp_options['flacso_preinscripciones_paginas_padre'] = [
    ['id' => 12294, 'nombre' => 'Diplomas'],
    ['id' => 12320, 'nombre' => 'Maestrías'],
    ...
]
```

### Paso 3: Programas
```
📍 Listado de Programas por Categoría
   Por cada categoría:
   - Muestra todas las páginas hijas
   - Checkbox para activar preinscripción
   - Botón "Ver página"
   - Botón "Ver formulario" (si está activado)
```

**Funcionalidad:**
- Seleccionar programas que tendrán preinscripción
- Vista previa de URLs virtuales
- Grid responsivo (4 columnas desktop)
- Información sobre URLs dinámicas

**Almacenamiento:**
```php
wp_options['flacso_preinscripciones_activas'] = [
    123, 456, 789, ...  // IDs de páginas
]
```

---

## 🔗 Sistema de URLs Virtuales

### Mecanismo

```php
// 1. Registrar rewrite rule
add_rewrite_rule(
    '^([^/]+)/preinscripcion/?$',
    'index.php?pagename=$matches[1]&flacso_preinscripcion_programa=1',
    'top'
);

// 2. Agregar query var
add_filter('query_vars', function($vars) {
    $vars[] = 'flacso_preinscripcion_programa';
    return $vars;
});

// 3. Template loader
add_filter('template_include', function($template) {
    if (get_query_var('flacso_preinscripcion_programa')) {
        return FLACSO_PREINSCRIPCION_MODULE_PATH . 'templates/preinscripcion.php';
    }
});
```

### Ejemplos de URLs

```
/maestria-en-educacion/preinscripcion/
/diplomado-gestion/preinscripcion/
/especialidad-xyz/preinscripcion/
```

### Características

- 📄 No crea páginas reales en la BD
- 🔄 Query var: `?flacso_preinscripcion_programa=ID`
- ⚙️ Rewrite rules automáticas
- 🎯 Template system dinámico
- 🏷️ Título automático: "Preinscripción - [Programa]"

---

## 📝 Formulario de Preinscripción

### Campos

```json
{
  "nombre": {
    "type": "text",
    "required": true,
    "min_length": 3,
    "placeholder": "Nombre completo"
  },
  "email": {
    "type": "email",
    "required": true,
    "validation": "RFC 5322"
  },
  "telefono": {
    "type": "tel",
    "required": false,
    "pattern": "[0-9+-]"
  },
  "cv": {
    "type": "file",
    "required": false,
    "max_size": "10MB",
    "allowed_types": ["pdf", "doc", "docx"]
  },
  "consulta": {
    "type": "textarea",
    "required": true,
    "min_length": 10,
    "placeholder": "Tu consulta aquí..."
  }
}
```

### Validación (Frontend)

```javascript
✓ Nombre: Mínimo 3 caracteres
✓ Email: Formato válido
✓ Teléfono: Numérico (opcional)
✓ CV: Máximo 10MB, PDF/DOC/DOCX
✓ Consulta: Mínimo 10 caracteres
```

### Validación (Backend)

```php
✓ sanitize_text_field()
✓ is_email() / wp_parse_url()
✓ wp_check_filetype()
✓ Verificar tamaño archivo
✓ Verificar nonce
```

---

## 📤 Envío de Datos

### Flujo AJAX

```
Usuario Submits
    ↓
JavaScript validate
    ↓
AJAX POST /wp-admin/admin-ajax.php?action=flacso_enviar_preinscripcion
    ↓
Backend procesar
    ↓
Enviar POST a Webhook (Google Apps Script)
    ↓
Respuesta JSON
    ↓
Usuario ve mensaje de éxito
```

### Payload JSON

```json
{
  "nombre": "Juan Pérez",
  "email": "juan@example.com",
  "telefono": "+598 123 456",
  "consulta": "Interesado en la maestría",
  "programa": "maestria-en-educacion",
  "programa_id": 123,
  "timestamp": "2026-01-29T15:30:00Z",
  "url_pagina": "https://ejemplo.com/maestria-en-educacion/"
}
```

### Respuesta del Webhook

```php
return [
    'success' => true,
    'mensaje' => 'Preinscripción recibida correctamente',
    'preinscripcion_id' => 12345
];
```

---

## 🔄 Sistema de Migración

### Detección Automática

Al cargar el panel, detecta:
- Páginas del sistema anterior
- Cantidad de registros a migrar
- Estado de cada página

### Procesos de Migración

```php
obtener_estado_migracion() {
    - Busca páginas antiguas
    - Calcula cantidad de migraciones
    - Genera lista de páginas
    - Devuelve estado
}

ejecutar_migracion($opciones) {
    - Migra datos a nueva estructura
    - Opcionalmente elimina antiguas
    - Limpia metadatos
    - Devuelve resultado
}
```

### Opciones

```php
[
    'eliminar_paginas' => true,   // Mover a papelera
    'limpiar_meta' => true        // Limpiar metadatos
]
```

---

## 🛡️ Seguridad

### Nonces
```php
wp_nonce_field('flacso_preinscripciones_guardar', 'flacso_preinscripciones_nonce');
wp_verify_nonce($_POST['flacso_preinscripciones_nonce'] ?? '', 'flacso_preinscripciones_guardar')
```

### Capacidades
```php
current_user_can('manage_options')  // Solo administradores
```

### Sanitización
```php
esc_url_raw()        // URLs
sanitize_text_field()  // Texto
intval()             // IDs
wp_check_filetype()  // Archivos
```

### Validación
```php
is_email()           // Emails
preg_match()         // Formatos
wp_verify_nonce()    // CSRF
```

---

## 🎨 Interfaz de Usuario

### Diseño

```
☐ Panel separado en 3 pasos (wizard)
☐ Cada paso con encabezado y descripción
☐ Grid responsivo para programas (4 cols desktop)
☐ Colores según estado (azul #0073aa, gris, rojo error)
☐ Iconos informativos (📚, 💾, ⚠️, ℹ️)
```

### Estados Visuales

```
✓ Verde        → Página existe y está activa
✗ Rojo         → Página no existe o inactiva
☐ Unchecked    → Preinscripción desactivada
☑ Checked      → Preinscripción activa
```

### Información Importante

```
- Páginas Virtuales: No se crean páginas reales
- URLs dinámicas: Se generan automáticamente
- Títulos automáticos: "Preinscripción - [Nombre]"
- Sin pérdida: Desactivar no elimina datos
- Datos a Sheets: Se envían al webhook
```

---

## 📊 Configuración Almacenada

### wp_options

| Clave | Tipo | Descripción |
|-------|------|-------------|
| `flacso_preinscripciones_webhook_url` | string | URL del webhook |
| `flacso_preinscripciones_paginas_padre` | array | Categorías configuradas |
| `flacso_preinscripciones_activas` | array | IDs de programas activos |

### Ejemplo

```php
// Webhook
'https://script.google.com/macros/s/AKfycbxMPc7.../exec'

// Categorías
[
    ['id' => 12294, 'nombre' => 'Diplomas'],
    ['id' => 12320, 'nombre' => 'Maestrías']
]

// Programas activos
[123, 456, 789, 1011, 1213, 1415]
```

---

## 🔧 Métodos Principales

### FLACSO_Formulario_Preinscripcion_Final

```php
// Administración
registrar_menu_admin()
render_pagina_admin()
procesar_formulario_admin()

// Obtención de datos
obtener_paginas_disponibles()
obtener_paginas_activas()
obtener_paginas_padre_permitidas()

// Procesamiento de formularios
procesar_formulario()           // AJAX handler

// URLs virtuales
registrar_rewrite_rules()
registrar_templates()
agregar_query_vars()
```

### Traits

**trait-admin.php**
```php
registrar_menu_admin()
render_pagina_admin()
procesar_formulario_admin()
obtener_paginas_disponibles()
obtener_paginas_activas()
```

**trait-render.php**
```php
render_formulario()
render_campo_nombre()
render_campo_email()
render_campo_telefono()
render_campo_cv()
render_campo_consulta()
```

**trait-templates.php**
```php
registrar_templates()
registrar_rewrite_rules()
```

**trait-assets.php**
```php
enqueue_assets()
enqueue_styles()
enqueue_scripts()
```

**trait-migracion.php**
```php
ejecutar_migracion()
obtener_estado_migracion()
```

---

## ✅ Funcionalidades Completadas

- ✅ Panel de administración con 3 pasos (Webhook, Categorías, Programas)
- ✅ Gestión dinámica de categorías
- ✅ Activación/desactivación por programa
- ✅ URLs virtuales sin crear páginas
- ✅ Formulario con validación
- ✅ Integración Google Apps Script
- ✅ Sistema de migración de datos
- ✅ Interfaz responsive
- ✅ Seguridad (nonces, sanitización, capacidades)
- ✅ Documentación completa

---

## 📋 Checklist de Uso

### Administrador Inicial

- [ ] 1. Acceder a Preinscripciones (panel admin)
- [ ] 2. Paso 1: Ingresar webhook URL
- [ ] 3. Paso 2: Verificar categorías y asignar páginas padre
- [ ] 4. Paso 3: Activar programas que tendrán preinscripción
- [ ] 5. Guardar cambios
- [ ] 6. Guardar enlaces permanentes (Settings → Permalinks)
- [ ] 7. Probar acceso a `/programa/preinscripcion/`

### Usuario Final

- [ ] 1. Navegar a `/programa/preinscripcion/`
- [ ] 2. Rellenar formulario
- [ ] 3. Adjuntar CV
- [ ] 4. Enviar formulario
- [ ] 5. Recibir confirmación
- [ ] 6. Datos aparecen en Google Sheets

---

## 🐛 Troubleshooting Rápido

| Error | Solución |
|-------|----------|
| URL devuelve 404 | Guardar enlaces permanentes en Settings |
| Webhook no recibe datos | Verificar URL es correcta y Apps Script está publicado |
| Categoría muestra ✗ | Crear/publicar la página padre en WordPress |
| Programas no aparecen | Asegurar que sean hijas de página padre y publicadas |
| Archivo muy grande | Máximo 10MB, debe ser PDF/DOC/DOCX |

---

## 📞 Soporte

**Documentación:**
- `GUIA_PREINSCRIPCIONES.md` - Guía de uso completa
- `FEATURES.md` - Características técnicas
- `INTEGRACION.md` - Este archivo

**Archivos clave:**
- `includes/class-formulario-preinscripcion.php` - Clase principal
- `includes/trait-admin.php` - Panel admin
- `includes/trait-render.php` - Formulario

---

## 📈 Estadísticas

- 📦 Archivos: 9 (init.php + 6 traits + 1 template + docs)
- 🔧 Métodos: 20+
- 💾 Configuraciones wp_options: 3
- 🔗 Rewrite rules: 1
- 📱 Responsive: Sí
- 🔐 Seguro: Sí (nonces, sanitización, capacidades)

---

**Estado:** ✅ LISTO PARA PRODUCCIÓN  
**Versión:** 1.0.1  
**Última actualización:** 31 de enero de 2026  
**Compatibilidad:** WordPress 6.0+, PHP 7.4+
