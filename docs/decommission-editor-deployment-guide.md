# Guía de Despliegue y Validación de Consultas Directas

Esta guía documenta el procedimiento ordenado, verificado y por fases para migrar la recepción de consultas (Ofertas Académicas y Seminarios) directamente al plugin WordPress de FLACSO Uruguay. No autoriza ni describe el apagado de `flacso-editor.service`: ese servicio conserva dependencias ajenas a estas consultas y exige un plan de retiro independiente.

---

## 1. Arquitectura y Objetivos

- **Antes:** WordPress actuaba de pasarela delegando consultas vía HTTP al servicio local `flacso-editor.service` (Next.js en Node.js), el cual escribía en PostgreSQL y despachaba correos vía Mailjet.
- **Ahora:** El plugin de WordPress (`flacso-uruguay-plugin`) gestiona de forma autónoma:
  - Verificación de idempotencia y persistencia directa en PostgreSQL (`offer_inquiries`, `seminar_inquiries`) mediante `FLACSO_DB` (PDO PostgreSQL).
  - Envío transaccional y templating directo vía Mailjet API v3.1 (`FLACSO_Mailjet_Client`).
  - Actualización de estado del correo (`emailStatus = 'sent'`, `mailjetMessageId`, `mailjetMessageUuid`) directamente en la base de datos relacional.
  - Sin fallback al webhook histórico del Editor: si el servicio interno no está disponible, el formulario responde con un error explícito y no entrega datos a un destino alternativo.
  - Los reenvíos manuales se reservan atómicamente desde `failed` a `processing` antes de contactar Mailjet, para impedir despachos concurrentes duplicados. Un rechazo determinista vuelve a `failed`; timeout, red, HTTP 5xx o cualquier resultado no confirmable conserva `processing` y debe conciliarse en Mailjet antes de permitir otro envío.

---

## 2. Variables de Entorno y Configuración en Producción

Antes de comenzar el despliegue, verifique que `wp-config.php` contenga las credenciales necesarias:

```php
// Conexión directa a PostgreSQL (FLACSO Database)
define('FLACSO_PG_HOST', '127.0.0.1'); // o IP del cluster PostgreSQL
define('FLACSO_PG_PORT', '5432');
define('FLACSO_PG_DATABASE', 'flacso_db');
define('FLACSO_PG_USER', 'flacso_user');
define('FLACSO_PG_PASSWORD', 'tu_password_seguro');

// Mailjet NO se lee desde constantes FLACSO_MAILJET_* en esta implementación.
// Se reutiliza la configuración centralizada del plugin, guardada en WordPress.
```

Verifique en **FLACSO → Integraciones** que estén configurados:
- API Key de Mailjet.
- Secret Key de Mailjet.
- Sender Email verificado (por ejemplo, `noreply@envios.flacso.edu.uy`).
- Sender Name.

Los Template ID de `consulta_abierta`, `consulta_cerrada` y
`consulta_seminario` se leen de las opciones WordPress
`flacso_mailjet_template_consulta_abierta`,
`flacso_mailjet_template_consulta_cerrada` y
`flacso_mailjet_template_consulta_seminario`. Si no están configuradas, el
plugin usa el fallback HTML institucional; no se debe asumir que las constantes
de `wp-config.php` configuran Mailjet.

Asegúrese además de que la extensión PHP `pdo_pgsql` esté habilitada en el servidor:
```bash
php -m | grep pdo_pgsql
```

---

## 3. Secuencia de Despliegue en 12 Pasos

### Paso 1: Clases implementadas en el plugin
Confirmar que todos los archivos nuevos y modificados se encuentran presentes en el plugin:
- `includes/database/class-flacso-db.php`
- `includes/database/repositories/class-flacso-base-inquiry-repository.php`
- `includes/database/repositories/class-flacso-offer-inquiry-repository.php`
- `includes/database/repositories/class-flacso-seminar-inquiry-repository.php`
- `includes/integrations/class-flacso-mailjet-client.php`
- `modules/consultas/init.php`
- `modules/consultas/services/class-flacso-offer-inquiry-service.php`
- `modules/consultas/services/class-flacso-seminar-inquiry-service.php`
- `modules/main-page/includes/flacso-consultas.php`
- `modules/posgrados/includes/class-flacso-posgrados-consultas-form.php`
- `modules/formularios/includes/helpers.php`
- `modules/oferta-academica/includes/class-academic-api.php`
- `scripts/verify-postgres-connection.php`


### Paso 2: Suite de tests unitarios al 100%
Ejecutar la suite completa de pruebas automáticas (ejecutada con base en memoria SQLite para aislar dependencias):
```bash
for test in tests/inquiry-*.php; do
    echo "Ejecutando $test..."
    php "$test" || exit 1
done
```
**Criterio de éxito:** Todos los tests deben finalizar con código de salida `0` y mostrar `OK <nombre-del-test>`.

### Paso 3: Diagnóstico en producción contra PostgreSQL real
Antes de habilitar el tráfico de usuarios en producción, ejecutar el script no destructivo de diagnóstico contra la base de datos real:
```bash
# Vía WP-CLI:
wp eval-file wp-content/plugins/flacso-uruguay-plugin/scripts/verify-postgres-connection.php

# O alternativamente vía CLI directo:
php wp-content/plugins/flacso-uruguay-plugin/scripts/verify-postgres-connection.php
```

El script ejecuta:
1. Verificación de extensión `pdo_pgsql` y constantes `FLACSO_PG_HOST`, `FLACSO_PG_PORT`, etc.
2. `SELECT COUNT(*)` inicial sobre `offer_inquiries` y `seminar_inquiries`.
3. Apertura de transacción con `BEGIN`.
4. `INSERT` diagnóstico con `consultaId` temporal en `offer_inquiries`.
5. `UPDATE` diagnóstico de estado de email en `offer_inquiries`.
6. `SELECT` de confirmación de lectura en `offer_inquiries`.
7. `INSERT` diagnóstico en `seminar_inquiries`.
8. `UPDATE` diagnóstico de estado de email en `seminar_inquiries`.
9. `SELECT` de confirmación de lectura en `seminar_inquiries`.
10. `ROLLBACK` total de la transacción.
11. Verificación final de que los conteos no sufrieron cambios y ningún registro diagnóstico persiste.

**Salida esperada:**
```text
CONEXION=OK
offer_inquiries=<count>
seminar_inquiries=<count>
TRANSACCION_PRUEBA=OK
ROLLBACK=OK
PERMISOS_ESCRITURA=OK
```

### Paso 4: Despliegue del plugin actualizado
Publicar el commit aprobado en `main` y usar exclusivamente el workflow oficial **Deploy WordPress Plugin**. Registrar el SHA que dispara el workflow y confirmar que su job de despliegue termina correctamente; el workflow aplica el paquete de ese SHA, conserva un backup transitorio y ejecuta su smoke test. No copiar archivos con `git pull` o `rsync` manual.

### Paso 5: Consulta real controlada en Oferta Académica
Desde un navegador o herramienta de pruebas, acceder a una página pública de Oferta Académica activa y enviar una consulta real de prueba con datos controlados (ej. email del equipo técnico `control-test@flacso.edu.uy`).

### Paso 6: Confirmación de INSERT en `offer_inquiries`
Verificar directamente en PostgreSQL que la consulta fue insertada correctamente:
```sql
SELECT id, "consultaId", "offerName", email, "emailStatus", "createdAt"
FROM offer_inquiries
ORDER BY "createdAt" DESC
LIMIT 1;
```

### Paso 7: Confirmación de envío en Mailjet
Confirmar que el registro de la consulta en PostgreSQL tiene los identificadores de despacho asignados por Mailjet:
```sql
SELECT "consultaId", "emailStatus", "emailSender", "mailjetMessageId", "mailjetMessageUuid"
FROM offer_inquiries
WHERE "email" = 'control-test@flacso.edu.uy'
ORDER BY "createdAt" DESC
LIMIT 1;
```
**Criterio:** `emailStatus` debe tener el valor `'sent'`, y `mailjetMessageId` debe contener un identificador numérico de mensaje válido de Mailjet. Verificar además la recepción del correo en la bandeja de entrada destinataria.

### Paso 8: Activación y verificación del flujo de Seminarios
Verificar que la configuración de seminarios esté operativa en el sitio web y el endpoint AJAX/REST responda adecuadamente.

### Paso 9: Consulta real controlada en Seminario
Enviar una consulta de prueba desde el formulario de un Seminario activo en el sitio.

### Paso 10: Confirmación de INSERT en `seminar_inquiries` + Mailjet sent
Revisar en PostgreSQL la persistencia de la consulta de seminario y su correspondiente estado de correo:
```sql
SELECT id, "consultaId", "seminarName", email, "emailStatus", "mailjetMessageId", "mailjetMessageUuid"
FROM seminar_inquiries
ORDER BY "createdAt" DESC
LIMIT 1;
```

### Paso 11: Ventana de Observación Inicial
Mantener la observación durante **24 a 48 horas** hábiles. Esta ventana valida solamente la captura directa de consultas; no constituye evidencia para detener ni deshabilitar `flacso-editor.service`.

Monitorear durante este período:
- Errores en el log de WordPress (`wp-content/debug.log` o logs de PHP-FPM).
- Flujo regular de consultas reales de estudiantes en `offer_inquiries` y `seminar_inquiries`.
- Ausencia de caídas o registros en estado inesperado.
- Métricas de entrega de Mailjet.

Comandos útiles de monitoreo durante la ventana de observación:
```bash
# Monitoreo de consultas entrantes en tiempo real
sudo -u postgres psql -d flacso_db -c 'SELECT "createdAt", "offerName", email, "emailStatus" FROM offer_inquiries ORDER BY "createdAt" DESC LIMIT 10;'

# Monitoreo de logs de PHP-FPM
sudo tail -f /var/log/php*-fpm.log | grep -i flacso

```

### Paso 12: Cierre de esta fase
Documentar los resultados, el SHA desplegado y las evidencias de persistencia y entrega. Cualquier propuesta de retiro de Editor debe iniciar un plan nuevo que inventarie y migre su RBAC, administración académica, instancias `legacy_editor` y demás dependencias antes de modificar el servicio.

---

## 4. Plan de Contingencia y Rollback

En caso de detectarse cualquier inconveniente crítico durante la ventana de observación:

1. Para revertir el plugin, seleccionar el SHA previamente validado y volver a desplegarlo mediante el workflow oficial; no usar `git checkout` ni cambios manuales en producción.
2. Ninguna de las operaciones de diagnóstico en el paso 3 modifica datos persistentes gracias a la ejecución obligatoria bajo `ROLLBACK`.
