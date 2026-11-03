# Meta CAPI en ISPConfig (EC2 t3a.large)

## Motivación

El servidor `ISPConfig` (EC2 `i-076bdbb899b710abe`, `us-east-1`) comparte 2 vCPU y 8 GiB RAM entre varios sitios WordPress. El sitio principal usa el usuario `web5` y PHP-FPM 8.3 con `pm=ondemand` y `pm.max_children=10`. Tiene `DISABLE_WP_CRON=true` y hoy ejecuta su cron externo a los minutos 0,10,20,30,40,50 de cada hora.

El PR #93 evitó la espera síncrona de Meta, pero generaba un trabajo asíncrono por evento; si los eventos se acumulan antes del cron, el procesamiento podría volver a saturar PHP.

## Comportamiento en este PR

- Las solicitudes AJAX insertan el evento en `wp_flacso_meta_queue`, sin realizar HTTP saliente a Meta.
- El identificador de evento se conserva para deduplicación con Meta Pixel.
- Un solo worker puede ejecutarse simultáneamente, utilizando un lock de MySQL.
- En cada ejecución se envían como máximo 3 lotes de 20 eventos (hasta 60 eventos), en vez de 60 peticiones HTTP.
- Reintenta errores temporales con demora; errores 4xx permanentes (excepto 429) se descartan.
- Se descartan registros con más de 48 horas. El máximo de cola es 5000 registros.
- El envío de prueba de un administrador sigue siendo síncrono deliberadamente.

## Cambio de operación REQUERIDO en ISPConfig

El sistema ya dispone de cron externo y `DISABLE_WP_CRON=true`, por lo que **no alcanza con fusionar el plugin**. El administrador debe reemplazar la tarea cron del sitio principal:

Actual:

```cron
0,10,20,30,40,50 * * * * web5 curl -L https://flacso.edu.uy/wp-cron.php
```

Propuesta (a nivel de `/etc/cron.d`, con campo usuario):

```cron
* * * * * web5 curl --silent --show-error --fail --max-time 45 -L https://flacso.edu.uy/wp-cron.php >/dev/null
```

No instalar un segundo cron paralelo. El cron del resto de sitios no cambia. Mantener un proceso por minuto y monitorear picos. Alternativamente usar WP-CLI con controles equivalentes si se coordina con el administrador.

**Capacidad estimada:** 60 eventos/minuto (3600/h) con cron minuto a minuto, si Meta responde y los eventos están listos. No es una garantía de throughput; 10 s de timeout por lote implican hasta 30 s de espera por tick.

## Validación previa y posterior

1. Ejecutar `php -l includes/core/class-flacso-meta-event-queue.php`, `php -l includes/core/class-flacso-meta-tracking.php`, `php -l flacso-uruguay.php` y `node --check includes/core/assets/meta-tracking.js`.
2. Confirmar en WP-CLI que existe la tabla del prefijo activo, que aparece el hook `flacso_meta_process_batch` y que el cron se ejecuta de verdad cada minuto.
3. Probar un `PageView` y un `ViewContent` en Events Manager. Comprobar que AJAX responde `queued: true` y que los eventos salen de la cola sin duplicar.
4. Probar indisponibilidad temporal de Meta y reintentos; comprobar que no bloquea AJAX.
5. Comprobar `CPUUtilization`, créditos CPU, procesos PHP-FPM y cola pendiente durante una hora.
6. Aplicar rate limit para la acción `flacso_meta_track_event` en Nginx/WAF con cuidado de **no bloquear todo `admin-ajax.php`**: allí también se procesan formularios y otras acciones.

## Consulta de diagnóstico (WP-CLI)

Con WP-CLI, adaptar el prefijo de WordPress si no es `wp_`:

```bash
sudo -u web5 wp --path=/var/www/clients/client2/web5/web cron event list | grep flacso_meta_process_batch
```

Consultas SQL para un operador autorizado:

```sql
SELECT COUNT(*) AS pending, MIN(created_at) AS oldest FROM wp_flacso_meta_queue;
SELECT attempts, COUNT(*) FROM wp_flacso_meta_queue GROUP BY attempts;
```

La tabla contiene datos del evento incluyendo IP y User-Agent. Restringir su acceso y retención, y no incluir su contenido en logs de diagnósticos.

## Despliegue seguro

No aumentar `pm.max_children` ni la instancia EC2 como primera respuesta. Desplegar con una ventana de observación, configurar el cron **antes o inmediatamente después** de habilitar el nuevo código y tener preparado rollback del plugin. La tabla es aditiva y no requiere destruir datos de otros módulos.
