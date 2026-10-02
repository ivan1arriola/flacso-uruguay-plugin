# Especificación de Diseño: Envío de Correos Inmediatos vía Mautic y Fallback Transaccional (Fase 3)

- **Fecha:** 2026-09-28
- **Estado:** Validado y Aprobado
- **Módulo:** `flacso-uruguay-plugin` (`includes/integrations`, `modules/consultas`, `modules/mailing`, `includes/database`)

---

## 1. Contexto y Objetivos

En las fases anteriores se construyeron los cimientos:
- **Fase 1:** Captura de cohortes y persistencia inmutable de snapshots en PostgreSQL (`offer_inquiries`).
- **Fase 2:** Cliente API Mautic nativo (`FLACSO_Mautic_Client`), generación de tags canónicos y sincronización en paralelo de contactos en `https://envios.flacso.edu.uy` sin enviar correos.

En esta **Fase 3**, el objetivo es habilitar a **Mautic como motor de envío de correos transaccionales inmediatos** para consultas de ofertas académicas, reemplazando progresivamente a Mailjet, pero incorporando un **mecanismo de selector con conmutación por error (fallback automático a Mailjet)** para garantizar que ningún estudiante quede desatendido si la API de Mautic sufriera un timeout o caída temporal.

### Objetivos Principales de la Fase 3
1. **Despacho de Correo en `FLACSO_Mautic_Client`**:
   - Incorporar el método `send_email_to_contact(int $email_id, int $contact_id, array $tokens = []): array`.
   - Endpoint: `POST /api/emails/{email_id}/contact/{contact_id}/send`.
   - Timeout estricto de 4 segundos.
2. **Compilador de Tokens Canónicos**:
   - Función en `FLACSO_Inquiry_Marketing_Service` para generar el mapa clave-valor de tokens `{token}` compatibles con el constructor de plantillas de Mautic.
3. **Selector de Motor y Fallback Automático en `FLACSO_Offer_Inquiry_Service::submit()`**:
   - Opción `flacso_inquiry_email_engine` (`'mailjet'` | `'mautic'`, por defecto `'mailjet'`).
   - Flujo cuando el motor es Mautic:
     1. Guarda consulta en PostgreSQL ("guardar primero").
     2. Sincroniza / recupera el contacto en Mautic para obtener `$contact_id`.
     3. Resuelve el ID de plantilla de Mautic según el estado de la oferta (`flacso_mautic_template_consulta_abierta` o `flacso_mautic_template_consulta_cerrada`).
     4. Intenta enviar el correo vía Mautic.
     5. **Si Mautic envía exitosamente**: registra `emailStatus = 'sent'`, `emailSender = 'mautic'` en `offer_inquiries`.
     6. **Si Mautic falla, no tiene plantilla configurada o agota el timeout**: activa el **fallback automático a Mailjet**, despacha inmediatamente el correo por Mailjet y registra `emailStatus = $status`, `emailSender = 'mailjet_fallback'` en `offer_inquiries`.
   - Flujo cuando el motor es Mailjet:
     - Mantiene el comportamiento actual: envía por Mailjet (`emailSender = 'mailjet'`) y sincroniza contacto/tags a Mautic en paralelo.
4. **Configuración en Consola de Correos (`FLACSO_Mail_Settings`)**:
   - Selector visual de motor de correo para consultas académicas.
   - Campos para los IDs de plantillas de Mautic: *Consulta Abierta* y *Consulta Cerrada*.
   - Probador de envío de correo de plantilla Mautic en vivo.
5. **Visibilidad en Consola de Consultas (`FLACSO_Consultas_Admin`)**:
   - La columna de estado de correo y el modal de detalle indican claramente el motor emisor: `Mautic`, `Mailjet` o `Mailjet (Fallback)`.

---

## 2. Hoja de Ruta de Fases

```
[Fase 1: Contexto de Cohorte y Snapshots en DB]       <--- COMPLETADA
       │
       ▼
[Fase 2: Cliente Mautic y Sincronización en Paralelo] <--- COMPLETADA
       │
       ▼
[Fase 3: Correo Inmediato por Mautic + Fallback]      <--- ALCANCE ACTUAL
       │ (Mautic asume el envío con conmutación automática a Mailjet ante fallos)
       ▼
[Fase 4: Seguimiento Automático (+X días vía WP-Cron)]
       │
       ▼
[Fase 5: Retiro Definitivo de Mailjet en Consultas]
```

---

## 3. Arquitectura y Componentes Detallados

### 3.1. Método de Envío en `FLACSO_Mautic_Client`
- **Ubicación:** `includes/integrations/class-flacso-mautic-client.php`
- **Firma:**
  ```php
  public static function send_email_to_contact(int $email_id, int $contact_id, array $tokens = []): array
  ```
- **Comportamiento:**
  - Valida que `$email_id > 0` y `$contact_id > 0`.
  - Construye la petición `POST /api/emails/{$email_id}/contact/{$contact_id}/send`.
  - En el cuerpo JSON envía: `['tokens' => $tokens]`.
  - Aplica timeout de 4 segundos y encabezados de autenticación (`Authorization: Basic` o `Bearer`).
  - Parsea respuesta de Mautic:
    - Éxito si código HTTP es 200 y el JSON contiene `success: true` o `result: true`.
    - Retorna: `['ok' => true, 'status' => 'sent', 'email_id' => $email_id, 'contact_id' => $contact_id, 'error' => null]`.
    - Fallo si código HTTP >= 400 o `success: false`: extrae mensaje de error o destinatarios fallidos.
    - Retorna: `['ok' => false, 'status' => 'failed', 'email_id' => $email_id, 'contact_id' => $contact_id, 'error' => $error_message]`.
  - Captura `WP_Error` y excepciones sin propagarlas hacia arriba.

### 3.2. Compilador de Tokens en `FLACSO_Inquiry_Marketing_Service`
- **Ubicación:** `modules/consultas/services/class-flacso-inquiry-marketing-service.php`
- **Firma:**
  ```php
  public static function compile_tokens(array $inquiry, array $program, bool $is_open): array
  ```
- **Mapeo de Tokens:**
  - `{nombre}`: Nombre de pila del prospecto.
  - `{apellido}`: Apellido del prospecto.
  - `{nombre_completo}`: Nombre completo.
  - `{correo}`: Email.
  - `{pais}`: País o `'Prefiere no responder'`.
  - `{profesion}`: Profesión o `'Prefiere no responder'`.
  - `{nivel_academico}`: Nivel académico o `'Prefiere no responder'`.
  - `{programa}` / `{oferta_academica_nombre}`: Nombre oficial de la oferta académica.
  - `{oferta_academica_url}`: URL de la oferta en la web.
  - `{cohorte_nombre}`: Nombre de la cohorte resuelta (ej: `'Cohorte 4'`).
  - `{cohorte_numero}`: Número de cohorte.
  - `{fecha_inicio}` / `{oferta_academica_fecha_inicio}`: Fecha de inicio formateada y legible.
  - `{modalidad}` / `{oferta_academica_modalidad}`: Modalidad legible.
  - `{url_preinscripcion}` / `{oferta_academica_url_preinscripcion}`: URL de preinscripción externa (si aplica).
  - `{url_carta}`: Enlace a la carta de presentación o brochure.

### 3.3. Orquestación del Envío y Fallback en `FLACSO_Offer_Inquiry_Service::submit()`
El pipeline se actualiza determinísticamente:
1. **Guardar primero**: Se inserta el registro en PostgreSQL (`offer_inquiries`). Si falla, aborta.
2. **Evaluación del motor activo**:
   - Se consulta `get_option('flacso_inquiry_email_engine', 'mailjet')`.
3. **Flujo cuando el motor es `'mautic'`**:
   - **Paso A (Sincronización de Contacto)**: Se ejecuta `FLACSO_Inquiry_Marketing_Service::sync_inquiry($record_id, $record, $repo)` para asegurar que el prospecto existe y tiene sus etiquetas. Se obtiene el `$contact_id`.
   - **Paso B (Resolución de Plantilla)**: Si `$is_open` es true, lee `flacso_mautic_template_consulta_abierta`; si no, lee `flacso_mautic_template_consulta_cerrada`.
   - **Paso C (Intento de Despacho Mautic)**:
     - Si `$contact_id > 0` y `$template_id > 0`:
       - Invoca `FLACSO_Mautic_Client::send_email_to_contact($template_id, $contact_id, $tokens)`.
       - Si es exitoso:
         - Actualiza BD: `update_email_status($consulta_id, 'sent', 'mautic', (string)$template_id)`.
         - Marca email entregado por Mautic.
     - **Paso D (Fallback a Mailjet si Mautic no pudo enviar)**:
       - Si Mautic no está configurado, no devolvió contact ID, la plantilla está vacía o el envío falló:
         - Emite `error_log` con aviso descriptivo del fallback.
         - Ejecuta `FLACSO_Mailjet_Client::send_offer_inquiry($inquiry_payload, $program_payload)`.
         - Actualiza BD: `update_email_status($consulta_id, $mail_res['status'], 'mailjet_fallback', $mail_res['message_id'], $mail_res['message_uuid'])`.
4. **Flujo cuando el motor es `'mailjet'`**:
   - Envía vía `FLACSO_Mailjet_Client::send_offer_inquiry()` con `emailSender = 'mailjet'`.
   - Sincroniza contacto y tags con Mautic en paralelo (comportamiento de Fase 2).
5. **Retorno al prospecto**:
   - Retorna siempre HTTP 200 con los metadatos del envío y motor utilizado (`'email_engine' => $active_engine`, `'email_sender' => $final_sender`).

### 3.4. Configuración en `FLACSO_Mail_Settings` (`admin.php?page=flacso-correos`)
- **Nuevas Opciones**:
  - `flacso_inquiry_email_engine`: `'mailjet'` (default) o `'mautic'`.
  - `flacso_mautic_template_consulta_abierta`: ID numérico de plantilla de correo en Mautic.
  - `flacso_mautic_template_consulta_cerrada`: ID numérico de plantilla de correo en Mautic.
- **Interfaz Gráfica**:
  - Selector de radio buttons o dropdown: "Motor de envío para consultas de oferta académica".
  - Inputs numéricos para las plantillas de Mautic.
  - Probador de envío de prueba en vivo de Mautic conectado a `wp_ajax_flacso_mautic_send_test_email`.

### 3.5. Visualización en `FLACSO_Consultas_Admin` (`admin.php?page=flacso-consultas`)
- En la columna "Estado Email", si `emailSender` es `'mautic'`, se acompaña con un distintivo visual (ej: `<span class="flacso-badge sent" title="Enviado vía Mautic">sent (Mautic)</span>`).
- Si `emailSender` es `'mailjet_fallback'`, se indica claramente en el badge y en el detalle modal que se ejecutó conmutación de emergencia por fallo de Mautic.

---

## 4. Estrategia de Pruebas Automatizadas

1. **`tests/mautic-client-email-send-test.php`**:
   - Pruebas unitarias de `FLACSO_Mautic_Client::send_email_to_contact()`.
   - Mock HTTP de `POST /api/emails/{id}/contact/{contactId}/send` verificando éxito 200 (`success: true`), fallo HTTP 500, timeout y validación de parámetros inválidos.
2. **`tests/inquiry-tokens-test.php`**:
   - Pruebas del compilador de tokens `compile_tokens()` verificando presencia de `{nombre}`, `{programa}`, `{url_preinscripcion}`, `{cohorte_nombre}`, etc.
3. **`tests/inquiry-services-test.php`**:
   - Actualización del pipeline `submit()`:
     - Caso 1: Motor Mautic exitoso -> Mautic envía el correo, no se llama a Mailjet, `emailSender == 'mautic'`.
     - Caso 2: Motor Mautic con fallo -> se activa fallback a Mailjet, Mailjet despacha el correo, `emailSender == 'mailjet_fallback'`.
     - Caso 3: Motor Mailjet seleccionado -> Mailjet despacha, Mautic recibe sincronización paralela.
4. **Regresión completa**:
   - Verificación de las 48+ suites de pruebas unitarias.
