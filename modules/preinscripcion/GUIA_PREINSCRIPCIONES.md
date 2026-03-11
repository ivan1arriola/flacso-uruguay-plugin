# Guía Rápida - Gestión de Preinscripciones FLACSO

## Acceso al Panel de Administración

**URL:** `tu-dominio.com/wp-admin/admin.php?page=flacso-preinscripciones`

En el menú lateral de WordPress verás: **Preinscripciones** → Gestión de Preinscripciones

---

## Paso 1: Configurar Webhook (Google Apps Script)

### ¿Qué es el Webhook?
Es la URL donde se **enviarán automáticamente** los datos de todos los formularios de preinscripción en tiempo real.

### Pasos:
1. **Obtén tu URL de Google Apps Script**
   - En Google Drive, crea un nuevo Apps Script
   - Escribe tu función `doPost(e)` para procesar datos
   - Publicar → Publicar como app web
   - Copiar la URL (termina en `/usercontent`)

2. **Ingresa la URL en el formulario**
   - Campo: "URL del Webhook"
   - Ejemplo: `https://script.google.com/macros/s/AKfycbxMPc7-8FOP-5Hkrv_x_dPZMtpAUHArGpjdTg2tjnV5MzO2wOAbu2jJDoXdGU3MPyZA/exec`
   - **Guardar Webhook**

### Validación
✅ Si la URL es válida, los formularios enviarán datos automáticamente a Google Sheets

---

## Paso 2: Definir Categorías de Programas

### ¿Qué son las Categorías?
Organizan los programas académicos en tipos (Diplomados, Maestrías, etc.). Se usan como páginas padre en WordPress.

### Parámetros:

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre de Categoría** | Cómo se llama el grupo | "Maestrías" |
| **Página Padre** | Página WordPress que es el contenedor | "Página: Maestrías" |

### Pasos:
1. Para cada categoría existente, asegúrate que tenga una página padre asignada
2. Para **agregar una nueva categoría**:
   - Nombre: "Cursos" (por ejemplo)
   - Página Padre: Selecciona la página WordPress correspondiente
   - La página debe estar **publicada**
3. **Guardar Categorías**

### ✅ Validación
- ✓ verde = página padre existe y está activa
- ✗ roja = página no existe o está en borrador

---

## Paso 3: Activar Preinscripción en Programas

### ¿Qué se logra?
Cada programa seleccionado tendrá un formulario accesible en: **[url-programa]/preinscripcion/**

### Características:
- 📄 **URL virtual**: No crea página real en la BD
- 🎯 **Automático**: El título se genera dinámicamente
- 🔄 **Sin perder datos**: Desactivar un programa solo oculta el acceso
- 📊 **Datos en Sheets**: Cada envío va al webhook configurado

### Pasos:
1. **Por cada categoría**, verás todos los programas disponibles
2. Marca el **checkbox** del programa para activar preinscripción
3. Cuando está activado:
   - ✓ Aparece checkbox marcado
   - 📄 Botón "Ver formulario" (vista previa)
   - 🌐 Botón "Ver página" (programa principal)
4. **Guardar Cambios** al finalizar

### Ejemplo de uso:
- Programa: "Maestría en Educación"
- Página: `/maestria-en-educacion/`
- Formulario: `/maestria-en-educacion/preinscripcion/` ← Aquí se accede

---

## Migración de Datos (Opcional)

Si tenías un sistema anterior, puedes **migrar automáticamente**:

### ¿Qué migra?
- Todas las páginas antiguas de preinscripción
- Se organizan en las categorías nuevas automáticamente
- Las páginas antiguas se mueven a papelera (recuperables)

### Pasos:
1. Aparecerá advertencia: ⚠️ "Migración de Datos Disponible"
2. Marca: "Mover páginas antiguas a papelera"
3. Clic en: "▶ Ejecutar Migración"
4. ✅ Se completará automáticamente

---

## Campos del Formulario de Preinscripción

El formulario automático solicita:

```
[Nombre completo]
[Email]
[Teléfono]
[CV/Documento]  ← Upload de archivo
[Consulta]      ← Mensaje adicional
```

### Validaciones:
- ✓ Nombre: Mínimo 3 caracteres
- ✓ Email: Formato válido
- ✓ CV: Máximo 10MB, formatos: PDF, DOC, DOCX
- ✓ Todos los campos son obligatorios

---

## Recibir Datos en Google Sheets

### Configuración mínima en Google Apps Script:

```javascript
function doPost(e) {
  const data = JSON.parse(e.postData.contents);
  
  // Abrir hoja de cálculo
  const sheet = SpreadsheetApp.getActiveSheet();
  
  // Agregar fila
  sheet.appendRow([
    new Date().toLocaleString(),
    data.nombre,
    data.email,
    data.telefono,
    data.consulta,
    data.programa
  ]);
  
  return ContentService.createTextOutput(JSON.stringify({success: true}))
    .setMimeType(ContentService.MimeType.JSON);
}
```

### Encabezados sugeridos en Sheets:
| Fecha | Nombre | Email | Teléfono | Consulta | Programa |
|-------|--------|-------|----------|----------|----------|

---

## Soporte de URLs Virtuales

### ¿Cómo funcionan sin crear páginas?
1. WordPress recibe solicitud: `/maestria-en-educacion/preinscripcion/`
2. Rewrite rules capturan la URL
3. Se pasa como query var: `?flacso_preinscripcion_programa=ID`
4. Template system renderiza el formulario

### URLs válidas:
```
https://ejemplo.com/maestria-en-educacion/preinscripcion/
https://ejemplo.com/diplomado-gestion/preinscripcion/
https://ejemplo.com/especialidad-xyz/preinscripcion/
```

### Si no funciona:
1. Clic en "Guardar Cambios" → Flush rewrite rules
2. Ir a Configuración → Enlaces permanentes → Guardar
3. Vuelve a intentar

---

## Troubleshooting

| Problema | Solución |
|----------|----------|
| URL `/preinscripcion/` devuelve 404 | Guardar enlaces permanentes en Configuración |
| Webhook no recibe datos | Verifica que la URL sea correcta y que Google Apps Script esté publicado como web app |
| Categoría con ✗ roja | Crea o publica la página padre en WordPress |
| Programas no aparecen | Asegúrate que sean hijas de la página padre y estén publicadas |
| Formulario rechaza archivo | Máximo 10MB, debe ser PDF, DOC o DOCX |

---

## Información Importante

- 🔒 **Privacidad**: Los datos se envían directamente a tu Google Sheets
- 📱 **Responsive**: El formulario funciona en móvil y desktop
- 🔄 **Sin límites**: Puedes tener preinscripción en 100+ programas
- 🗑️ **Seguro**: Al desactivar un programa, no se pierden datos
- 📈 **Escalable**: Soporta miles de preinscripciones

---

## Preguntas Frecuentes

**P: ¿Se crean páginas reales en WordPress?**
R: No, son virtuales. Se generan dinámicamente sin ocupar espacio en la BD.

**P: ¿Puedo cambiar los campos del formulario?**
R: Los campos principales son fijos (Nombre, Email, Teléfono, CV, Consulta). Para campos personalizados, edita `trait-render.php`.

**P: ¿Qué pasa si desactivo un programa?**
R: La URL `/preinscripcion/` ya no será accesible, pero los datos quedan guardados.

**P: ¿Cómo respaldar los datos?**
R: Los datos están en tu Google Sheets. Descárgalos en cualquier momento como Excel.

**P: ¿Puedo enviar emails automáticos?**
R: Sí, en tu Apps Script agrega lógica con `GmailApp.sendEmail()`.

---

**Última actualización:** 31 de enero de 2026
**Versión:** 1.0.1
