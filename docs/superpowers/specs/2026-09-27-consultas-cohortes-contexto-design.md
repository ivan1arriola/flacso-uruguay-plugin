# Especificación de Diseño: Consultas de Ofertas Académicas, Contexto de Cohortes y Persistencia Snapshot (Fase 1)

- **Fecha:** 2026-09-27
- **Estado:** Validado y Aprobado
- **Módulo:** `flacso-uruguay-plugin` (`modules/consultas`, `modules/oferta-academica`, `includes/database`) & `kadence-child-flacso` (`inc/academic-public-model.php`, `template-parts/oferta/default.php`)

---

## 1. Contexto y Objetivos

El sistema de FLACSO Uruguay gestiona consultas de prospectos a través de formularios en la web, persistiendo cada interacción en una base de datos PostgreSQL local y despachando notificaciones por correo. 

Actualmente, el plugin registra la consulta con un estado general de oferta (`offerStatus` como `'abierta'` o `'cerrada'`), pero carece de la atribución formal a una cohorte específica, de la captura inmutable del período de preinscripción vigente en ese instante, y de la validación de la abreviación de la oferta requerida para la futura sincronización con Mautic.

### Objetivos Principales de la Fase 1
1. **Atribución determinística de cohorte**: Incorporar en `FLACSO_Academic_Catalog` el concepto de **cohorte de consulta** (`get_inquiry_cohort`), diferenciando la cohorte activa/futura de la presentación visual genérica.
2. **Normalización y unicidad de abreviación**: Utilizar el campo nativo `abreviacion` de `OfertaAcademica` (normalizado en minúsculas y formato slug, ej: `'davia'`, `'mg'`) y verificar su unicidad en el panel de administración.
3. **Persistencia inmutable de snapshots en DB**: Ampliar la tabla `offer_inquiries` con los campos de contexto (`offerAbbreviation`, `cohortWpId`, `cohortNumber`, `cohortName`, `registrationOpenAt`, `registrationCloseAt`, `offerStatus`) y dejar preparadas las columnas de integración para Mautic y seguimiento futuro.
4. **Separación de responsabilidades**:
   - `FLACSO_Inquiry_Context_Service`: Encapsula la resolución y validación del contexto de la consulta.
   - `FLACSO_Offer_Inquiry_Service`: Orquesta "guardar primero, enviar después".
   - `kadence-child-flacso`: Solo consume el View Model para mostrar opcionalmente un aviso de contexto sobre el formulario, sin ejecutar consultas SQL ni reglas de negocio.

---

## 2. Hoja de Ruta Arquitectónica (5 Fases)

Para garantizar un despliegue seguro sin interrupción operativa, la integración con Mautic se divide en 5 fases:

```
[Fase 1: Contexto de Cohorte y Snapshots en DB]  <--- ALCANCE ACTUAL
       │
       ▼
[Fase 2: Cliente Mautic y Sincronización en Paralelo (sin envíos)]
       │
       ▼
[Fase 3: Correo Inmediato por Mautic (reemplaza Mailjet para consultas)]
       │
       ▼
[Fase 4: Seguimiento Automático (+X días vía Scheduler del Plugin)]
       │
       ▼
[Fase 5: Retiro Definitivo de Mailjet en Consultas]
```

---

## 3. Arquitectura y Componentes de la Fase 1

### 3.1. Catálogo Académico (`FLACSO_Academic_Catalog`)
Se incorpora el método público:
```php
public static function get_inquiry_cohort(int $offer_id): ?array
```
**Reglas de resolución**:
1. Obtiene todas las cohortes asociadas a `$offer_id` vía `FLACSO_Academic_Repository::list('cohortes', ['parent_id' => $offer_id])`.
2. **Paso 1: Cohorte con inscripción abierta**:
   - Filtra cohortes donde `FLACSO_Cohorte::accepts_registration($cohort_id)` sea `true` (o `!empty($cohort['preinscripcion']['abierta'])`).
   - Si existen varias, desempata eligiendo la de `fecha_inicio` más próxima a la fecha actual; ante empate o falta de fecha, elige la de mayor `numero` de cohorte.
3. **Paso 2: Cohorte planificada futura**:
   - Si ninguna está abierta, busca cohortes cuyo estado académico sea `'planificada'` y cuya `fecha_inicio` sea posterior o igual a la fecha actual (`fecha_inicio >= hoy`).
   - Si existen varias, desempata eligiendo la de `fecha_inicio` más cercana en el futuro.
4. **Paso 3: Sin cohorte**:
   - Si no hay cohortes abiertas ni futuras planificadas, retorna `null`. No se inventa una asociación con cohortes pasadas o finalizadas.
5. **Integración en `get_offer()`**:
   - El resultado de `self::get_inquiry_cohort($offer_id)` se adjunta bajo la clave `'cohorte_consulta'` en el array devuelto por `FLACSO_Academic_Catalog::get_offer($offer_id)`.

---

### 3.2. Normalización y Validación de Abreviación (`FLACSO_Oferta_Academica`)
1. **Normalización canónica**:
   ```php
   public static function normalize_abbreviation(string $raw): string {
       return sanitize_title(strtolower(trim($raw)));
   }
   ```
2. **Comprobación de disponibilidad**:
   ```php
   public static function is_abbreviation_available(string $abbr, int $exclude_post_id = 0): bool
   ```
   Realiza una consulta a la base de datos de WordPress para verificar que ninguna otra entrada del tipo `oferta-academica` posea el meta `abreviacion` con ese mismo valor normalizado.
3. **Guardado en `FLACSO_Oferta_Admin_Fields::save()`**:
   - Si el usuario administrador ingresa una abreviación que colisiona con otra oferta existente, no se guarda el valor duplicado y se registra un aviso de error en la sesión de administración (`admin_notices`).

---

### 3.3. Servicio de Contexto de Consulta (`FLACSO_Inquiry_Context_Service`)
Archivo: `modules/consultas/services/class-flacso-inquiry-context-service.php`

**Firma**:
```php
public static function resolve(int $offer_id, array $inquiry_data = []): array
```
**Estructura del DTO de retorno**:
```php
[
    'offerWpId'           => int,
    'offerName'           => string,
    'offerType'           => ?string,
    'offerAbbreviation'   => ?string, // 'davia', 'mg', o null con warning
    'cohortWpId'          => ?int,
    'cohortNumber'        => ?int,
    'cohortName'          => ?string,
    'registrationOpenAt'  => ?string, // ISO-8601 o fecha de inicio de preinscripción
    'registrationCloseAt' => ?string, // ISO-8601 o fecha de cierre de preinscripción
    'offerStatus'         => string,  // 'abierta' | 'cerrada' | 'sin_cohorte'
    'preinscripcionUrl'   => ?string,
    'replyToEmail'        => ?string,
    'programUrl'          => ?string,
    'cartaUrl'            => ?string,
    'startValue'          => string,
    'startPrecision'      => string,
    'modalityLabel'       => string,
]
```
**Reglas de negocio**:
- Si la oferta no tiene configurada una abreviación válida, asigna `offerAbbreviation = null` y emite un `error_log('[FLACSO] Oferta ID X sin abreviacion configurada...')`. **No interrumpe ni rechaza el envío del usuario**.
- Si `cohorte_consulta` es no nulo:
  - `offerStatus` = `!empty($cohort['preinscripcion']['abierta']) ? 'abierta' : 'cerrada'`
  - Los campos de snapshot toman los valores de `preinscripcion_desde` y `preinscripcion_hasta`.
- Si `cohorte_consulta` es nulo:
  - `offerStatus` = `'sin_cohorte'`
  - Campos de cohorte en `null`.

---

### 3.4. Esquema de Base de Datos y Persistencia

#### Archivo de Migración SQL
Ubicación: `scripts/migrations/2026-09-27-offer-inquiries-cohort-and-mautic.sql`

```sql
-- Migración: Ampliación de offer_inquiries para Cohortes, Snapshots y Mautic
ALTER TABLE offer_inquiries
    ADD COLUMN IF NOT EXISTS "offerAbbreviation" VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS "cohortWpId" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "cohortNumber" INTEGER NULL,
    ADD COLUMN IF NOT EXISTS "cohortName" VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS "registrationOpenAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "registrationCloseAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticContactId" VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS "mauticSyncStatus" VARCHAR(32) NOT NULL DEFAULT 'skipped',
    ADD COLUMN IF NOT EXISTS "mauticSyncedAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "mauticLastError" TEXT NULL,
    ADD COLUMN IF NOT EXISTS "followupDueAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "followupStatus" VARCHAR(32) NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS "followupSentAt" TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS "followupAttempts" INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS "followupLastError" TEXT NULL;

-- Índices de consulta eficiente
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_offer_abbr ON offer_inquiries ("offerAbbreviation");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_cohort ON offer_inquiries ("cohortWpId");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_mautic_sync ON offer_inquiries ("mauticSyncStatus");
CREATE INDEX IF NOT EXISTS idx_offer_inquiries_followup ON offer_inquiries ("followupStatus", "followupDueAt");
```

#### Adaptación de `FLACSO_Offer_Inquiry_Repository`
- `insert(array $data)`: Mapea y persiste los nuevos campos.
- El filtrado dinámico vía `get_table_columns()` previene fallos si la base de datos corre temporalmente con un esquema previo.
- Se implementa el método `find_pending_by_email_and_cohort(string $email_normalized, int $cohort_id): ?array`.

---

### 3.5. Orquestación del Flujo (`FLACSO_Offer_Inquiry_Service::submit()`)

```
Usuario envía Formulario Web
           │
           ▼
FLACSO_Offer_Inquiry_Service::submit($data)
           │
           ├── 1. Extrae consultaId / verifica idempotencia temprana en DB
           ├── 2. Valida datos requeridos (email con formato válido, nombre)
           ├── 3. FLACSO_Inquiry_Context_Service::resolve($offer_id, $data)
           │      └── Resuelve: oferta, abreviación, cohorte_consulta, snapshots y offerStatus
           ├── 4. FLACSO_Offer_Inquiry_Repository::insert($snapshot)  <--- GUARDAR PRIMERO
           │      └── Si falla DB: retorna 500 y ABORTA (no envía correo)
           ├── 5. FLACSO_Mailjet_Client::send_offer_inquiry()         <--- ENVIAR DESPUÉS
           ├── 6. FLACSO_Offer_Inquiry_Repository::update_email_status()
           └── 7. Retorna respuesta estructurada {ok, consulta_id, offer_status, cohort_number}
```

---

### 3.6. Visualización y Auditoría en Admin (`FLACSO_Consultas_Admin`)
- En el listado de consultas de ofertas académicas:
  - Columna **Oferta**: Muestra el nombre de la oferta junto a un badge de la abreviación (ej: `davia`).
  - Columna **Cohorte**: Muestra `Cohorte X` (o `—` si no tiene cohorte).
  - Columna **Al consultar**: Badge con el estado inmutable capturado:
    - `🟢 Abierta`
    - `⚪ Cerrada`
    - `➖ Sin cohorte`
  - Filtro por estado al consultar (`abierta`, `cerrada`, `sin_cohorte`).

---

### 3.7. Presentación en Tema (`kadence-child-flacso`)
1. **`inc/academic-public-model.php`**:
   - `flacso_theme_get_offer_context(int $offer_id)` procesa `'cohorte_consulta'` desde `FLACSO_Academic_Catalog::get_offer()` y lo agrega a su View Model:
     ```php
     $context['inquiry_cohort'] = [
         'has_cohort' => (bool) $cohort,
         'name'       => (string) ($cohort['nombre'] ?? ''),
         'number'     => (int) ($cohort['numero'] ?? 0),
         'is_open'    => !empty($cohort['preinscripcion']['abierta']),
         'label'      => !empty($cohort['preinscripcion']['abierta'])
             ? sprintf(__('Inscripciones abiertas — %s', 'flacso-uruguay'), $cohort['nombre'] ?? '')
             : sprintf(__('Próxima cohorte — %s', 'flacso-uruguay'), $cohort['nombre'] ?? ''),
     ];
     ```
2. **`template-parts/oferta/default.php`**:
   - Renderiza un encabezado textual o badge informativo sutil inmediatamente antes del formulario de consulta si `has_cohort` es `true`.
   - Cero lógica de negocio ni llamadas directas a WordPress post meta en la plantilla.

---

## 4. Plan de Pruebas Automatizadas

1. **`tests/inquiry-context-test.php` (Nuevo)**:
   - Cohorte abierta $\to$ `offerStatus = 'abierta'`, snapshots poblados desde la cohorte.
   - Cohorte planificada futura $\to$ `offerStatus = 'cerrada'`.
   - Sin cohortes vigentes $\to$ `offerStatus = 'sin_cohorte'`, campos de cohorte nulos.
   - Desempate entre cohortes abiertas $\to$ más próxima a iniciar.
   - Normalización de abreviaciones $\to$ mayúsculas y espacios convertidos a slug minúscula.
   - Abreviación no configurada $\to$ `null`, registra advertencia sin excepción.
   - Validación de unicidad de abreviación.

2. **`tests/inquiry-repositories-test.php` (Actualizado)**:
   - Inserción y lectura de todos los campos de snapshot en SQLite / PostgreSQL.

3. **`tests/inquiry-services-test.php` (Actualizado)**:
   - Ejecución completa de `FLACSO_Offer_Inquiry_Service::submit()` verificando que el registro resultante en base de datos contiene los snapshots de cohorte y estado.

4. **`kadence-child-flacso/tests/academic-public-model-test.php` (Actualizado)**:
   - Proyección fiel del bloque `inquiry_cohort` en el View Model del tema.

5. **`kadence-child-flacso/bin/check-theme.sh`**:
   - Verificación de lint y límites de capas arquitectónicas en el tema.
