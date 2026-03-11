# Preinscripciones FLACSO - Referencia Rápida

## 🎯 Acceso

**Panel Admin:** `/wp-admin/admin.php?page=flacso-preinscripciones`

---

## 📋 Funcionalidades

### 1️⃣ Webhook Configuration
```
✅ URL de Google Apps Script
✅ Envío automático de datos
✅ Validación de URL
```

**Estado:** Configurado como `flacso_preinscripciones_webhook_url`

---

### 2️⃣ Categorías de Programas

```
📚 Diplomas       → Página Padre: /diplomas/
📚 Maestrías      → Página Padre: /maestrias/
📚 Especializaciones → Página Padre: /especializaciones/
📚 Diplomados     → Página Padre: /diplomados/
```

**Características:**
- ✅ Agregar nuevas categorías dinámicamente
- ✅ Validar existencia de páginas padre
- ✅ Reorganizar clasificación

**Almacenamiento:** `flacso_preinscripciones_paginas_padre` (wp_options)

---

### 3️⃣ Gestión de Programas

**Activación por Categoría:**

```
📚 Diplomas (7 programas)
  ☐ Diploma Comprendiendo China
  ☐ Diploma Abordaje de Violencias
  ☐ Diploma Género
  ☐ Diploma IA y Prácticas
  ☐ Diploma Infancias
  ☐ Diploma Metodología
  ☐ Diploma Salud Mental

📚 Maestrías (3 programas)
  ☐ Maestría en Educación
  ☐ Maestría Sociedad y Política
  ☐ Maestría Género

📚 Especializaciones (2 programas)
  ☐ Especialización Análisis de Textos
  ☐ Especialización Género-Cambio Climático

📚 Diplomados (4 programas)
  ☐ Diplomado Género - Políticas Públicas
  ☐ Diplomado Género - Salud
  ☐ Diplomado Género - Violencia
  ☐ Diplomado Violencias Infancia
```

**Almacenamiento:** `flacso_preinscripciones_activas` (wp_options)

---

## 🔗 URLs Virtuales

**Patrón:**
```
[página-programa]/preinscripcion/
```

**Ejemplos:**
```
/maestria-en-educacion/preinscripcion/
/diplomado-gestion/preinscripcion/
/especialidad-xyz/preinscripcion/
```

**Características:**
- 📄 No crea páginas reales en BD
- 🔄 Query var: `?flacso_preinscripcion_programa=ID`
- ⚙️ Rewrite rules automáticas
- 🎯 Template system dinámico

---

## 📝 Campos de Formulario

```json
{
  "nombre": "string (requerido, min 3)",
  "email": "email (requerido, válido)",
  "telefono": "string (opcional)",
  "cv": "file (opcional, max 10MB, PDF/DOC/DOCX)",
  "consulta": "text (requerido)"
}
```

---

## 🔄 Flujo de Datos

```
Usuario rellena formulario
        ↓
Validación AJAX (frontend)
        ↓
Procesamiento PHP (backend)
        ↓
Envío POST a Webhook
        ↓
Google Apps Script recibe
        ↓
Datos en Google Sheets
```

---

## 🛠️ Configuración Técnica

**Constantes:**
```php
FLACSO_PREINSCRIPCION_MODULE_PATH
FLACSO_PREINSCRIPCION_MODULE_URL
```

**Hooks:**
```php
init (priority 5)       → Inicializar módulo
admin_menu              → Registrar menú admin
wp_enqueue_scripts      → Assets frontend
wp_ajax_*               → Procesar formularios
rest_api_init           → API REST
```

**Acciones AJAX:**
```php
wp_ajax_flacso_enviar_preinscripcion
wp_ajax_nopriv_flacso_enviar_preinscripcion
```

---

## 📊 Opciones de WordPress

| Opción | Descripción |
|--------|-------------|
| `flacso_preinscripciones_webhook_url` | URL de Google Apps Script |
| `flacso_preinscripciones_paginas_padre` | Configuración de categorías |
| `flacso_preinscripciones_activas` | Programas con preinscripción activa |

---

## 🔐 Capacidades Requeridas

- `manage_options` → Acceso al panel admin

---

## 📱 Responsive

- ✅ Desktop (grid 1 columna)
- ✅ Tablet (grid 2 columnas)
- ✅ Mobile (grid 1 columna)
- ✅ Formulario adaptable

---

## ⚠️ Validaciones

```
✅ Nombre: Mínimo 3 caracteres
✅ Email: Formato RFC 5322
✅ Teléfono: Numérico (opcional)
✅ CV: Max 10MB
✅ Consulta: Requerida, min 10 caracteres
```

---

## 🔍 Requerimientos

- WordPress 6.0+
- PHP 7.4+
- Acceso a rewrite rules
- Google Apps Script (para webhook)

---

## 📚 Archivos Principales

```
modules/preinscripcion/
├── init.php
├── GUIA_PREINSCRIPCIONES.md
├── FEATURES.md (este archivo)
├── includes/
│   ├── class-formulario-preinscripcion.php
│   ├── trait-admin.php          ← Panel admin
│   ├── trait-render.php          ← Formulario
│   ├── trait-templates.php       ← URLs virtuales
│   ├── trait-assets.php          ← CSS/JS
│   └── trait-migracion.php       ← Datos antiguos
└── templates/
    └── preinscripcion.php        ← Template principal
```

---

## 🚀 Inicialización

**Orden de carga:**
1. **Preinscripción** (init, priority 5)
2. Registra rewrite rules
3. Registra menú admin
4. Carga assets
5. Habilita AJAX handlers

---

## 💾 Persistencia

**Datos guardados:**
- ✅ Webhook URL (wp_options)
- ✅ Categorías (wp_options)
- ✅ Programas activos (wp_options)
- ✅ Envíos de formularios (Google Sheets)

---

## 🐛 Debug

**Modo debug habilitado en:**
```php
$estado_migracion = $this->obtener_estado_migracion();
$paginas_disponibles = $this->obtener_paginas_disponibles();
```

**Logs:**
- Error 404 en rewrite rules → Guardar enlaces permanentes
- Webhook no recibe → Verificar URL
- Programas no aparecen → Verificar páginas padre publicadas

---

## ✨ Características Especiales

### 🔄 Sistema de Migración
Migra automáticamente preinscripciones antiguas sin perder datos

### 📄 URLs Virtuales
No ocupa espacio en BD, se generan dinámicamente

### 🎯 Títulos Automáticos
"Preinscripción - [Nombre Programa]"

### 🛡️ Sin Pérdida de Datos
Desactivar = ocultar acceso, no eliminar datos

### 📊 Integración Google Sheets
Envío automático de todos los formularios

---

**Versión:** 1.0.1  
**Última actualización:** 31 de enero de 2026  
**Estado:** ✅ Operativo
