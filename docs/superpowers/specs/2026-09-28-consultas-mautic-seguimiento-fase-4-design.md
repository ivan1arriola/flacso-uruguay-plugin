# Especificación de Diseño: Seguimiento Automático (+X días) con Reevaluación de Cohorte (Fase 4)

- **Fecha**: 2026-09-28
- **Estado**: Aprobado por el usuario
- **Alcance**: `flacso-uruguay-plugin`
- **Fase**: 4 de 5 ("Seguimiento Automático vía WP-Cron con Reevaluación de Cohorte")

---

## 1. Contexto y Objetivos

En las fases anteriores se consolidó el registro en base de datos PostgreSQL (`offer_inquiries`, Fase 1), la sincronización paralela de prospectos y etiquetas en Mautic (Fase 2), y el despacho transaccional inmediato con fallback automático a Mailjet (Fase 3).

El objetivo de la **Fase 4** es implementar el ciclo de vida de **seguimiento automático programado (+X días)**:
1. Al ingresar una consulta de oferta académica, si el módulo está habilitado, calcular y programar en la base de datos la fecha de vencimiento (`followupDueAt = inquiryAt + X días`) con estado inicial `'pending'`.
2. Ejecutar un ciclo periódico mediante **WP-Cron** (`flacso_inquiry_followup_cron`) que reclame atómicamente los registros vencidos.
3. **Reevaluar la cohorte en tiempo real**: al momento del seguimiento (ej: 5 días después), consultar el catálogo de WordPress (`FLACSO_Academic_Catalog::get_offer()`) para verificar si la cohorte abrió sus inscripciones, cambió de fecha de inicio o cuenta con un nuevo enlace de preinscripción.
4. **Reglas de descarte inteligente**: Omitir el seguimiento si el aspirante registró una consulta más reciente para esa misma oferta o si la oferta fue despublicada.
5. **Despacho y Fallback Resiliente**: Enviar la plantilla correspondiente de Mautic (abierta vs cerrada) con tokens actualizados, aplicando conmutación inmediata a Mailjet ante caídas de la API de Mautic.
6. **Visibilidad y Control Administrativo**: Mostrar estados y badges en la consola administrativa (`FLACSO_Consultas_Admin`) y permitir el disparo manual inmediato desde el modal de detalle.

---

## 2. Modelo de Datos y Repositorio (`offer_inquiries`)

La tabla `offer_inquiries` en PostgreSQL ya cuenta con los campos requeridos:
- `followupDueAt` (TIMESTAMP / TEXT ISO-8601): Momento en que debe ejecutarse el seguimiento (`inquiryAt + X días`).
- `followupStatus` (TEXT, default `'none'`):
  - `'none'`: Seguimiento no programado.
  - `'pending'`: Programado, en espera de que `followupDueAt <= NOW()`.
  - `'processing'`: Reclamado atómicamente por el scheduler para evitar procesamiento concurrente.
  - `'sent'`: Seguimiento enviado con éxito (Mautic o Mailjet fallback).
  - `'skipped'`: Descartado legítimamente (consulta más reciente o programa inactivo).
  - `'failed'`: Envío fallido tras agotar intentos máximos.
- `followupSentAt` (TIMESTAMP / TEXT ISO-8601): Fecha y hora efectiva del envío.
- `followupAttempts` (INTEGER, default 0): Contador de ejecuciones.
- `followupLastError` (TEXT): Motivo del fallo o del descarte.

### Métodos en `FLACSO_Offer_Inquiry_Repository`

```php
/**
 * Reclama atómicamente hasta $limit consultas vencidas transicionándolas de 'pending' a 'processing'.
 *
 * @param int $limit Máximo de registros a reclamar por ciclo.
 * @return array Lista de registros reclamados.
 */
public function claim_due_followups(int $limit = 25): array;

/**
 * Actualiza el resultado del seguimiento de una consulta.
 *
 * @param string      $id       ID CUID del registro.
 * @param string      $status   'sent' | 'skipped' | 'failed' | 'pending'
 * @param string|null $error    Mensaje de error o motivo de descarte.
 * @param string|null $sent_at  Timestamp ISO-8601 del envío.
 * @return bool
 */
public function update_followup_status(string $id, string $status, ?string $error = null, ?string $sent_at = null): bool;

/**
 * Determina si existe una consulta posterior del mismo correo para la misma oferta.
 *
 * @param string $email_normalized
 * @param int    $offer_wp_id
 * @param string $current_inquiry_at
 * @return bool
 */
public function has_newer_inquiry_for_offer(string $email_normalized, int $offer_wp_id, string $current_inquiry_at): bool;
```

---

## 3. Configuración en `FLACSO_Mail_Settings`

En `modules/mailing/includes/class-flacso-mail-settings.php`, se registran y renderizan los siguientes controles:
- `flacso_inquiry_followup_enabled` (bool, default `false`).
- `flacso_inquiry_followup_days` (int, default `5`, min `1`, max `60`).
- `flacso_mautic_template_seguimiento_abierta` (int, default `0`).
- `flacso_mautic_template_seguimiento_cerrada` (int, default `0`).

---

## 4. Programación al Ingresar la Consulta

En `FLACSO_Offer_Inquiry_Service::submit()`:
1. Al recibir la consulta y antes de la inserción en base de datos:
   - Se consulta `get_option('flacso_inquiry_followup_enabled', false)`.
   - Si está activo:
     - `$days = max(1, (int) get_option('flacso_inquiry_followup_days', 5));`
     - `$due_timestamp = strtotime("+{$days} days", strtotime($inquiry_at));`
     - `$followup_due_at = gmdate('Y-m-d H:i:s', $due_timestamp);`
     - Se asigna en `$record`:
       - `'followupStatus' => 'pending'`
       - `'followupDueAt'  => $followup_due_at`
   - Si está inactivo:
     - `'followupStatus' => 'none'`
     - `'followupDueAt'  => null`
2. La consulta se inserta y el flujo transaccional inmediato continúa inalterado.

---

## 5. Servicio de Seguimiento (`FLACSO_Inquiry_Followup_Service`)

Ubicación: `modules/consultas/services/class-flacso-inquiry-followup-service.php`.

### 5.1. Programación del Cron
- Evento WP-Cron: `flacso_inquiry_followup_cron`.
- Recurrencia: `hourly`.
- En `init`:
  ```php
  if (!wp_next_scheduled('flacso_inquiry_followup_cron')) {
      wp_schedule_event(time(), 'hourly', 'flacso_inquiry_followup_cron');
  }
  ```

### 5.2. Ciclo de Ejecución (`run_followup_cycle(int $limit = 25): array`)
1. **Reclamación**: Invoca `$repo->claim_due_followups($limit)`.
2. **Procesamiento de cada registro**:
   - **Regla 1 (Deduplicación por consulta más reciente)**:
     - Si `$repo->has_newer_inquiry_for_offer($email, $offer_id, $inquiry_at)` es true:
       - Actualiza: `update_followup_status($id, 'skipped', 'Existe consulta más reciente para esta oferta')`.
       - Pasa al siguiente registro.
   - **Regla 2 (Existencia de la oferta)**:
     - Si la oferta ya no existe en WordPress o está en `trash`:
       - Actualiza: `update_followup_status($id, 'skipped', 'Oferta académica despublicada o eliminada')`.
       - Pasa al siguiente registro.
   - **Regla 3 (Reevaluación de cohorte en tiempo real)**:
     - Invoca `FLACSO_Academic_Catalog::get_offer($offer_id)`.
     - Determina `$is_open = !empty($offer['cohorte_consulta']['preinscripcion']['abierta']);`.
     - Construye `$program_payload` con los datos vigentes de la cohorte:
       - `name`: Título del programa.
       - `cohortName`: Nombre de la cohorte actual.
       - `cohortNumber`: Número de la cohorte.
       - `startValue`: Fecha de inicio.
       - `startPrecision`: Precisión de inicio (`dia`, `mes`, etc.).
       - `modalityLabel`: Modalidad actual.
       - `preinscripcionUrl`: URL externa activa para preinscribirse.
       - `cartaUrl`: URL del brochure / carta.
   - **Regla 4 (Selección de plantilla)**:
     - Si `$is_open`: `$template_id = (int) get_option('flacso_mautic_template_seguimiento_abierta', 0);`
     - Si no: `$template_id = (int) get_option('flacso_mautic_template_seguimiento_cerrada', 0);`
   - **Regla 5 (Compilación de Tokens y Despacho)**:
     - Compila los tokens canónicos: `FLACSO_Inquiry_Marketing_Service::compile_tokens($inquiry, $program_payload, $is_open)`.
     - Despacha mediante `FLACSO_Mautic_Client::send_email_to_contact($template_id, $contact_id, $tokens)`.
   - **Regla 6 (Fallback Automático a Mailjet)**:
     - Si Mautic no está configurado, la plantilla es 0 o el envío de Mautic falla:
       - Dispara `FLACSO_Mailjet_Client::send_offer_inquiry($inquiry, $program_payload)`.
       - Si Mailjet entrega con éxito:
         - Actualiza: `update_followup_status($id, 'sent', 'Enviado vía Mailjet (Fallback)', gmdate('c'))`.
       - Si ambos fallan:
         - Actualiza: `update_followup_status($id, 'failed', $error_message)`.

---

## 6. Visualización y Control Administrativo (`FLACSO_Consultas_Admin`)

1. **Tabla de Consultas (`admin.php?page=flacso-consultas`)**:
   - En la columna de acciones o seguimiento, se visualizan badges semánticos:
     - `pending`: `<span class="flacso-badge followup-pending" title="Vencimiento: YYYY-MM-DD HH:MM">⏰ Seg. d-X</span>`
     - `sent`: `<span class="flacso-badge followup-sent" title="Seguimiento enviado: YYYY-MM-DD HH:MM">✅ Seg. enviado</span>`
     - `skipped`: `<span class="flacso-badge followup-skipped" title="Omitido: [Motivo]">⏭ Seg. omitido</span>`
     - `failed`: `<span class="flacso-badge followup-failed" title="Error: [Motivo]">❌ Seg. falló</span>`
2. **Modal de Detalle**:
   - Muestra la sección dedicada al seguimiento: estado, fecha programada, fecha de envío, intentos y errores.
   - Botón interactivo: `[ 🚀 Enviar Seguimiento Ahora ]` que invoca `wp_ajax_flacso_consultas_trigger_followup`.
3. **Endpoint AJAX Manual (`wp_ajax_flacso_consultas_trigger_followup`)**:
   - Verifica permisos de administrador y CSRF nonce.
   - Ejecuta de inmediato el flujo de reevaluación y despacho para el registro solicitado.
   - Devuelve JSON estructurado con el nuevo estado del seguimiento.

---

## 7. Estrategia de Pruebas Automatizadas

1. **`tests/inquiry-followup-repository-test.php`**:
   - Creación de registros de prueba con fechas pasadas y futuras.
   - Verificación de la reclamación atómica (`claim_due_followups`) y exclusión de registros no vencidos o ya procesados.
   - Verificación de `update_followup_status()` y `has_newer_inquiry_for_offer()`.
2. **`tests/inquiry-followup-scheduler-test.php`**:
   - Mock de `FLACSO_Academic_Catalog` con transiciones de cohorte (cerrada -> abierta).
   - Verificación de envío de plantilla abierta con tokens frescos cuando la cohorte abrió.
   - Verificación de descarte por consulta posterior.
   - Verificación de fallback automático a Mailjet en seguimiento ante fallo de Mautic.
   - Verificación de AJAX `flacso_consultas_trigger_followup`.
3. **Regresión Completa**:
   - Ejecución de las 48 suites de pruebas del plugin y suites del tema child.
