# Especificación de Diseño: Deprecación de Mailjet y Consolidación de Mautic como Motor Primario en Consultas de Oferta Académica (Fase 5)

- **Fecha**: 2026-09-28
- **Estado**: Aprobado para ejecución
- **Alcance**: `flacso-uruguay-plugin`
- **Fase**: 5 de 5 ("Deprecación de Mailjet y Consolidación de Mautic como Motor Primario")

---

## 1. Contexto y Objetivos

En las Fases 1 a 4 del proyecto se construyó la infraestructura completa de captación, sincronización y seguimiento:
1. **Fase 1**: Resolución contextual de cohorte en tiempo real y persistencia en base de datos PostgreSQL (`offer_inquiries`).
2. **Fase 2**: Cliente API de Mautic (`FLACSO_Mautic_Client`) y sincronización paralela y resiliente de contactos y tags canónicos (`FLACSO_Inquiry_Marketing_Service`).
3. **Fase 3**: Envío transaccional inmediato vía Mautic con compilación de 21 tokens canónicos y conmutación automática de respaldo (fallback a Mailjet).
4. **Fase 4**: Seguimiento automático programado (+X días) con reevaluación dinámica de cohorte en tiempo real vía WP-Cron y controles administrativos.

El objetivo de la **Fase 5** es formalizar y culminar la transición tecnológica:
- **Mautic asume la titularidad total como motor primario y por defecto** para todas las consultas de oferta académica en el ecosistema FLACSO Uruguay.
- **Mailjet queda formalmente deprecado como motor primario** para ofertas académicas, preservándose con un rol vital de **contingencia y respaldo automático (fail-safe)** ante caídas de conectividad o incidencias operativas en Mautic.
- Las consultas de seminarios de posgrado (`seminario_inquiries`) continúan operando de forma estable mediante su pipeline dedicado de Mailjet / HTML interno.
- La acción administrativa de reintento (`wp_ajax_flacso_consultas_retry_email`) en `FLACSO_Consultas_Admin` se actualiza para respetar la primacía de Mautic con su respectivo fallback, en vez de enviar directamente por Mailjet sin sincronización.
- La interfaz de administración en la Consola de Correos (`FLACSO_Mail_Settings`) refleja con precisión la nueva jerarquía: Mautic Primario, Mailjet de Respaldo, y diagnóstico de salud operativo.

---

## 2. Decisiones Técnicas y Arquitectura

```
                        Nueva Consulta de Oferta Académica
                                        │
                                        ▼
                  Persistencia Inmediata en DB (PostgreSQL)
                                        │
                                        ▼
              Motor Primario: Mautic (Por Defecto en Sistema)
                                        │
                        ┌───────────────┴───────────────┐
                        ▼                               ▼
               [Mautic Disponible]             [Mautic con Fallo/Timeout/Sin Plantilla]
                        │                               │
                        ▼                               ▼
                 Despacho Mautic               Conmutación Automática (Fallback)
             (emailSender='mautic')                     │
                                                        ▼
                                                  Despacho Mailjet
                                            (emailSender='mailjet_fallback')
```

### 2.1. Cambio de Motor por Defecto (`OPTION_INQUIRY_EMAIL_ENGINE`)
- La constante `FLACSO_Mail_Settings::OPTION_INQUIRY_EMAIL_ENGINE` (`flacso_inquiry_email_engine`):
  - En `register_settings()`: el valor `default` cambia de `'mailjet'` a `'mautic'`.
  - En `get_settings()`: `$settings['inquiry_email_email']` consulta `get_option(self::OPTION_INQUIRY_EMAIL_ENGINE, 'mautic')`.
  - En `FLACSO_Offer_Inquiry_Service::submit()`:
    ```php
    $engine = function_exists('get_option') ? (string) get_option('flacso_inquiry_email_engine', 'mautic') : 'mautic';
    ```
    Si el valor en base de datos es nulo o vacío, resuelve a `'mautic'`.

### 2.2. Helper de Diagnóstico y Salud (`FLACSO_Mail_Settings::get_offer_inquiry_engine_status()`)
Nuevo método público en `FLACSO_Mail_Settings`:
```php
/**
 * Diagnóstico del estado operativo de los motores de correo para ofertas académicas.
 *
 * @return array{
 *     engine: string,
 *     is_mautic_primary: bool,
 *     mautic_ready: bool,
 *     mailjet_fallback_ready: bool,
 *     status_label: string
 * }
 */
public static function get_offer_inquiry_engine_status(): array;
```
- `is_mautic_primary`: `true` si el motor configurado es `'mautic'`.
- `mautic_ready`: `true` si Mautic está habilitado (`OPTION_MAUTIC_ENABLED === '1'`), tiene base URL y credenciales de autenticación, y cuenta con las plantillas abierta y cerrada configuradas (> 0).
- `mailjet_fallback_ready`: `true` si Mailjet cuenta con API Key, Secret Key y remitente configurados (`is_transactional_ready()`).
- `status_label`: Texto descriptivo para la consola de administración.

### 2.3. Actualización de Reintento Administrativo (`FLACSO_Consultas_Admin::ajax_retry_email()`)
Actualmente, el botón "Reintentar Envío" en el modal de detalle para registros de `offer_inquiries` llamaba directamente a `FLACSO_Mailjet_Client::send_offer_inquiry()`.
En Fase 5:
- Si el registro corresponde a `offer_inquiries` y el motor activo es `'mautic'`:
  1. Si `FLACSO_Mautic_Client::is_configured()`, resuelve la plantilla correspondiente (abierta vs cerrada) según el estado de la oferta y realiza la compilación de tokens canónicos mediante `FLACSO_Inquiry_Marketing_Service::compile_tokens()`.
  2. Obtiene o sincroniza el `contact_id` en Mautic.
  3. Ejecuta `FLACSO_Mautic_Client::send_email_to_contact()`.
  4. Si tiene éxito: actualiza `emailStatus = 'sent'` y `emailSender = 'mautic'`.
  5. Si falla: activa conmutación a `FLACSO_Mailjet_Client::send_offer_inquiry()`, actualizando `emailStatus` y marcando `emailSender = 'mailjet_fallback'`.
- Si el registro corresponde a `seminario_inquiries`:
  - Mantiene intacta la llamada a `FLACSO_Mailjet_Client::send_seminar_inquiry()` con `emailSender = 'mailjet'`.

### 2.4. Refactorización de la Consola de Correos (`FLACSO_Mail_Settings::render_page()`)
1. **Tarjeta de KPIs:**
   - Se incorpora un KPI de Motor de Consultas que muestra:
     - `MOTOR DE CONSULTAS`: **Mautic (Primario)**.
     - Badge: `OPTIMIZADO` (si Mautic y Mailjet fallback están listos) o `CONFIGURACIÓN PENDIENTE`.
     - Subtexto: Indicar que el respaldo por conmutación automática de Mailjet se encuentra activo.
2. **Selector de Motor:**
   - La opción de Mautic se presenta como:
     `Mautic (Motor Principal y Recomendado) — Automatización completa, tags y seguimiento (+X días)`.
   - La opción de Mailjet se presenta como:
     `Mailjet Directo (Modo Legado / Contingencia) — Despacho transaccional clásico sin orquestación avanzada`.
3. **Rotulación de Plantillas Mailjet:**
   - Las plantillas de Mailjet de "Consulta de Oferta Abierta" y "Consulta de Oferta Cerrada" se etiquetan visualmente como:
     `Plantilla Mailjet de Respaldo (Fallback de Emergencia)`.
     Se aclara en la descripción que se emplearán en caso de conmutación por incidencia de Mautic, o si se dejaran vacías, se despachará el HTML institucional interno.

---

## 3. Plan de Verificación y Pruebas Unitarias

1. **Pruebas de Configuración por Defecto (`tests/mail-console-and-consultas-admin-test.php`):**
   - Verificar que `FLACSO_Mail_Settings::register_settings()` registra `flacso_inquiry_email_engine` con default `'mautic'`.
   - Verificar que `FLACSO_Mail_Settings::get_settings()['inquiry_email_engine']` retorna `'mautic'` cuando la opción de WordPress no existe.
   - Verificar `FLACSO_Mail_Settings::get_offer_inquiry_engine_status()`.
2. **Pruebas de Despacho por Defecto (`tests/inquiry-services-test.php`):**
   - Verificar que una nueva consulta sin opción configurada en base de datos ejecuta el pipeline de Mautic como motor primario (intentando despacho Mautic o fallback si no está configurada la plantilla).
3. **Pruebas de Reintento Administrativo (`tests/mail-console-and-consultas-admin-test.php`):**
   - Probar `ajax_retry_email()` para oferta con motor Mautic activo y fallback automático.
   - Probar `ajax_retry_email()` para seminario manteniendo Mailjet sin cambios.
4. **Regresión Completa:**
   - Ejecución exitosa de todas las suites en `tests/*-test.php`.
