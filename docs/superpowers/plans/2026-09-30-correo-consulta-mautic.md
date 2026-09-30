# Correo íntegro de consultas en Mautic Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enviar desde Mautic el acuse íntegro de solicitudes de información usando todos los datos del formulario público de WordPress.

**Architecture:** El constructor canónico de payload añadirá los tres campos de perfil que faltan bajo aliases `flacso_`. El MJML histórico se conservará como fuente versionada y sustituirá las variables de Mailjet por tokens Mautic. La configuración de Mautic crea los campos, el email transaccional y el nodo de campaña, mientras WordPress mantiene la campaña desactivada hasta la prueba aprobada.

**Tech Stack:** PHP 7.4+, WordPress, Mautic 7.2, MJML/GrapesJS, pruebas PHP autónomas.

**Spec:** `docs/superpowers/specs/2026-09-30-correo-consulta-mautic-design.md`

## Global Constraints

- Conservar el MJML histórico completo y sustituir solo variables de Mailjet por tokens de Mautic.
- Usar aliases `flacso_pais`, `flacso_nivel_academico` y `flacso_profesion`, de tipo texto y opcionales.
- No registrar valores de consultas en logs de integración.
- Mantener la campaña inactiva durante esta entrega.
- No eliminar los correos históricos ni la campaña existente.

## Review Focus

- Consultas con valores vacíos de país, nivel o profesión deben sincronizarse y renderizar campos vacíos sin bloquear el contacto.
- Las claves camelCase y snake_case de país, nivel y profesión deben resolver al mismo alias Mautic.
- El token de URL de preinscripción vacío no debe impedir que el email se guarde ni que se genere su vista previa.
- Una consulta de oferta histórica sin fecha completa debe conservar vacío `flacso_fecha_inicio` y permitir el envío.
- El email nuevo debe ser de tipo evento/transaccional y no puede activar la campaña ni enviar a segmentos de producción.

---

## File Structure

- `modules/consultas/services/class-flacso-mautic-payload-builder.php`: construye el snapshot de contacto con aliases Mautic.
- `tests/mautic-payload-builder-test.php`: prueba los aliases y normalización del snapshot.
- `docs/mautic/data-contract.md`: documenta los campos de contacto que WordPress sincroniza.
- `docs/mautic/acuse-consulta-academica.mjml`: conserva la plantilla de acuse migrada, lista para importar/copiar a Mautic.
- `docs/mautic/campaigns.md`: identifica el correo transaccional de la campaña y su estado seguro de publicación.

### Task 1: Extender el contrato de perfil de consulta

**Files:**
- Modify: `modules/consultas/services/class-flacso-mautic-payload-builder.php:8-47`
- Modify: `tests/mautic-payload-builder-test.php:18-39`
- Modify: `docs/mautic/data-contract.md:3-23`

**Interfaces:**
- Consumes: `FLACSO_Mautic_Payload_Builder::build(array $inquiry): array` y las claves `country|pais`, `education_level|educationLevel|nivel_academico`, `profession|profesion`.
- Produces: `fields['flacso_pais']`, `fields['flacso_nivel_academico']` y `fields['flacso_profesion']` como cadenas.

- [ ] **Step 1: Escribir pruebas de payload para los tres campos de perfil**

```php
payload_assert($open['fields']['flacso_pais'] === 'Uruguay', 'mapea país');
payload_assert($open['fields']['flacso_nivel_academico'] === 'Título universitario', 'mapea nivel académico');
payload_assert($open['fields']['flacso_profesion'] === 'Docente', 'mapea profesión');
```

Añadir un caso con claves `pais`, `nivel_academico` y `profesion` vacías y comprobar que los tres aliases existen con cadena vacía.

- [ ] **Step 2: Ejecutar la prueba para verificar que falla**

Run: `php tests/mautic-payload-builder-test.php`

Expected: FAIL al no existir alguno de los aliases `flacso_*` nuevos.

- [ ] **Step 3: Extender `FLACSO_Mautic_Payload_Builder::build(array $inquiry): array`**

Añadir los tres aliases al arreglo `fields`. Priorizar `country`, `education_level` y `profession`; aceptar luego sus aliases de formulario. No modificar la lista de etiquetas ni añadir datos de consulta a los logs.

- [ ] **Step 4: Documentar los aliases y su regla de valor vacío**

Insertar las tres filas en `docs/mautic/data-contract.md`, de tipo `text`, con fuentes `pais`, `nivel_academico` y `profesion`; indicar que son opcionales y se sincronizan como cadena vacía si faltan.

- [ ] **Step 5: Ejecutar la prueba para verificar que pasa**

Run: `php tests/mautic-payload-builder-test.php`

Expected: `OK mautic payload builder`.

- [ ] **Step 6: Commit**

```bash
git add modules/consultas/services/class-flacso-mautic-payload-builder.php tests/mautic-payload-builder-test.php docs/mautic/data-contract.md
git commit -m "feat(mautic): sincronizar perfil de consulta"
```

### Task 2: Versionar el MJML adaptado a Mautic

**Files:**
- Create: `docs/mautic/acuse-consulta-academica.mjml`

**Interfaces:**
- Consumes: los campos y aliases definidos en Task 1 y los aliases de oferta existentes.
- Produces: un documento MJML válido para el editor de correo de Mautic.

- [ ] **Step 1: Crear la plantilla MJML desde el correo histórico completo**

Conservar todas las secciones, estilos, enlaces y textos institucionales del correo pegado por el usuario. Cambiar solamente estas variables:

| Mailjet | Mautic |
| --- | --- |
| `{{var:oferta_academica_articulo}}` | `{contactfield=flacso_oferta_articulo}` |
| `{{var:oferta_academica_nombre}}` | `{contactfield=flacso_oferta_nombre}` |
| `{{var:oferta_academica_modalidad}}` | `{contactfield=flacso_modalidad}` |
| `{{var:oferta_academica_fecha_inicio}}` | `{contactfield=flacso_fecha_inicio}` |
| `{{var:oferta_academica_url_preinscripcion}}` | `{contactfield=flacso_preinscripcion_url}` |
| `{{var:oferta_academica_url}}` | `{contactfield=flacso_oferta_url}` |
| `{{var:nombre}}` / `{{var:apellido}}` | `{contactfield=firstname}` / `{contactfield=lastname}` |
| `{{var:pais}}` | `{contactfield=flacso_pais}` |
| `{{var:nivel_academico}}` | `{contactfield=flacso_nivel_academico}` |
| `{{var:correo}}` | `{contactfield=email}` |
| `{{var:profesion}}` | `{contactfield=flacso_profesion}` |

Conservar el sufijo `carta` del botón de información sobre `{contactfield=flacso_oferta_url}`.

- [ ] **Step 2: Validar el contenido de la plantilla**

Run: `! rg -F '{{var:' docs/mautic/acuse-consulta-academica.mjml && rg -n 'contactfield=(flacso_pais|flacso_nivel_academico|flacso_profesion)' docs/mautic/acuse-consulta-academica.mjml`

Expected: la primera búsqueda no devuelve variables Mailjet; la segunda devuelve los tres campos nuevos.

- [ ] **Step 3: Commit**

```bash
git add docs/mautic/acuse-consulta-academica.mjml
git commit -m "docs(mautic): migrar acuse de consulta a MJML"
```

### Task 3: Crear campos y correo transaccional en Mautic

**Files:**
- Modify: `docs/mautic/campaigns.md:1-7`

**Interfaces:**
- Consumes: aliases y MJML de Tasks 1-2; administrador autenticado de `envios.flacso.edu.uy`.
- Produces: tres campos de contacto Mautic y el correo publicado `Acuse de recibo de consulta académica`.

- [ ] **Step 1: Crear los campos de contacto en Mautic**

En Configuración → Campos personalizados, crear `flacso_pais`, `flacso_nivel_academico` y `flacso_profesion` como campos de contacto de texto, públicos no requeridos y sin valores predeterminados. Confirmar que los aliases se muestran exactamente como se documentaron.

- [ ] **Step 2: Crear el correo de tipo evento/transaccional**

En Canales → Correos Electrónicos, seleccionar **Email por evento**, el tema **Blank/Transaccional**, idioma español y estado publicado. Usar el asunto `Recibimos tu consulta sobre {contactfield=flacso_oferta_nombre}` y copiar el contenido de `docs/mautic/acuse-consulta-academica.mjml` en el editor de código.

- [ ] **Step 3: Verificar el correo sin enviarlo**

Abrir la vista previa con un contacto interno que contenga los campos de oferta y perfil. Confirmar asunto, encabezado, los dos botones y el bloque de datos ingresados. No utilizar envío de prueba a terceros.

- [ ] **Step 4: Documentar el ID real del correo y la acción de campaña pendiente**

Actualizar `docs/mautic/campaigns.md` con el nombre e ID del email creado, el ID de campaña configurado en WordPress y la condición explícita de mantenerla inactiva hasta una prueba interna.

- [ ] **Step 5: Commit**

```bash
git add docs/mautic/campaigns.md
git commit -m "docs(mautic): registrar correo de consulta"
```

### Task 4: Conectar el correo a la campaña sin activarla

**Files:**
- Modify: `docs/mautic/campaigns.md:1-12`

**Interfaces:**
- Consumes: ID del correo creado en Task 3 y campaña de consultas configurada en Mautic.
- Produces: un flujo `segmento Prueba → Acuse de recibo de consulta académica` guardado e inactivo.

- [ ] **Step 1: Reemplazar el nodo de correo de prueba**

En el constructor de la campaña `Consultas web FLACSO` (ID 2), añadir la acción **Enviar correo electrónico** para el email de Task 3 con ejecución inmediata, conectar el segmento `Prueba` al nuevo nodo y retirar únicamente el nodo que envía `Es una prueba de campaña`.

- [ ] **Step 2: Verificar el estado de campaña**

Confirmar que `Permitir reinicio` y `Activo` siguen en `No`, que la fuente sigue siendo `Prueba` y que el único nodo de envío activo referencia el acuse nuevo.

- [ ] **Step 3: Guardar y documentar la configuración final**

Guardar el constructor y registrar en `docs/mautic/campaigns.md` el ID 2, el nombre visible y el estado inactivo.

- [ ] **Step 4: Commit**

```bash
git add docs/mautic/campaigns.md
git commit -m "docs(mautic): dejar lista campaña de consultas"
```

### Task 5: Verificación y publicación del plugin

**Files:**
- Verify: `tests/mautic-payload-builder-test.php`
- Verify: `tests/inquiry-marketing-tags-test.php`
- Verify: `docs/mautic/acuse-consulta-academica.mjml`

**Interfaces:**
- Consumes: Tasks 1-4.
- Produces: evidencia local de contrato, evidencia visual de plantilla y publicación de commits del plugin.

- [ ] **Step 1: Ejecutar las pruebas PHP y las comprobaciones de formato**

Run: `php tests/mautic-payload-builder-test.php && php tests/inquiry-marketing-tags-test.php && git diff --check`

Expected: ambos tests terminan correctamente y no hay errores de espacios.

- [ ] **Step 2: Revisar el diff y el estado de Git**

Run: `git status --short && git diff origin/main...HEAD --check`

Expected: solo cambios del contrato, pruebas y documentación de Mautic; ninguna credencial o dato personal.

- [ ] **Step 3: Publicar los commits de plugin aprobados**

Run: `git push origin main`

Expected: `main` remoto contiene los commits de la implementación.

- [ ] **Step 4: Reportar límites de verificación**

Separar validación local, publicación Git, estado de la campaña, vista previa del correo y la futura prueba de entrega. No afirmar entrega ni activación sin ejecutar y comprobar el flujo con un contacto interno.
