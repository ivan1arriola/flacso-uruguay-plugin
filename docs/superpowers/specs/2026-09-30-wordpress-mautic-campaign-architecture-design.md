# WordPress a Mautic: campana de consultas y contrato canonico

## Estado

Diseno aprobado para planificacion. No autoriza mutaciones en Mautic, envios ni eliminacion de comportamiento legacy. La implementacion empieza unicamente tras aprobar el plan por fases que deriva de este documento.

## Objetivo

WordPress conserva la captura, validacion, snapshot academico y persistencia de una consulta. Mautic recibe el ultimo snapshot del contacto, tags acumulativos y la incorporacion a una unica campana de consultas. Mautic es responsable de la automatizacion y Amazon SES de la entrega. Mailjet permanece como contingencia temporal.

El formulario publico no depende de una respuesta exitosa de Mautic. Un fallo de Mautic conserva la consulta y deja evidencia reintentable.

## Hechos verificados

- La instancia `mautic_web` y su contenedor de cron estan activos en produccion.
- Existen 44.361 contactos. Este trabajo no los migra ni los modifica masivamente.
- No hay campos cuyo alias empiece por `flacso_`.
- Hay campos legacy que deben preservarse, incluidos `last_program_*`, `last_record_*`, `intereses`, `acquisition_source` y campos de origen Meta.
- Solo existe la campana publicada `Primera Campana de prueba` (ID 1), con un unico evento inmediato. No es la campana de consultas.
- La implementacion actual sincroniza un contacto y envia correos por IDs de plantilla Mautic; tambien mantiene seguimiento con WP-Cron.

## Limites de responsabilidad

| Sistema | Es responsable de |
| --- | --- |
| WordPress | formulario, validacion, snapshot, persistencia, normalizacion, sincronizacion, tags, alta a campana, diagnostico y fallback |
| Mautic | contacto, segmentacion, campana, emails, esperas, condiciones, aperturas, clics, rebotes |
| Amazon SES | transporte final de correo de Mautic |
| Mailjet | contingencia temporal ante un fallo real de Mautic |

WordPress no replica el Campaign Builder ni programa esperas de marketing. Mautic no pasa a ser fuente de verdad del snapshot de una consulta persistida en WordPress.

## Contrato canonico

Los datos estandar usan `email`, `firstname` y `lastname`. Los nuevos campos de la integracion usan `flacso_` y `snake_case`. No se renombran aliases legacy: el contrato nuevo se agrega en paralelo.

| Alias | Tipo Mautic | Fuente WordPress | Regla |
| --- | --- | --- | --- |
| `flacso_consulta_id` | text | id persistido | requerido, inmutable por consulta |
| `flacso_consulta_fecha` | datetime | `inquiryAt` | ISO 8601 UTC |
| `flacso_origen` | text | flujo publico | fijo: `web-consultas` |
| `flacso_tipo` | select | tipo de consulta | `oferta` o `seminario` |
| `flacso_oferta_codigo` | text | abreviacion normalizada | kebab-case; vacio si no aplica |
| `flacso_oferta_nombre` | text | snapshot | texto de la propuesta |
| `flacso_oferta_articulo` | text | snapshot | opcional; no inferir gramatica |
| `flacso_oferta_url` | url | snapshot | URL publica valida |
| `flacso_cohorte_codigo` | text | codigo normalizado | `{codigo}-c{numero}` o vacio |
| `flacso_cohorte_numero` | number | snapshot | positivo o vacio |
| `flacso_cohorte_nombre` | text | snapshot | opcional |
| `flacso_cohorte_estado` | select | resolucion academica | `abierta`, `cerrada` o `sin_cohorte` |
| `flacso_modalidad` | select | snapshot | `virtual`, `presencial` o `hibrida`; vacio si no hay equivalencia real |
| `flacso_fecha_inicio` | date | snapshot | `YYYY-MM-DD` solo con precision suficiente |
| `flacso_duracion` | text | snapshot | opcional |
| `flacso_creditos` | number | snapshot | opcional |
| `flacso_preinscripcion_url` | url | snapshot | URL valida o vacia |
| `flacso_consulta_texto` | textarea | mensaje | opcional; nunca en logs operativos |

El payload se construye en una unica clase `FLACSO_Mautic_Payload_Builder`. Ningun formulario ni controlador arma aliases Mautic directamente.

## Tags

Los tags nuevos son acumulativos y se agregan sin borrar los existentes:

```
interes-{codigo}
{codigo}-c{numero}
origen-web-consultas
```

`{codigo}-c{numero}` solo existe cuando hay cohorte. El estado de cohorte no es un tag; es `flacso_cohorte_estado`. Tags legacy como `interes:DAVIA`, `interes-davia-c10` y `consulta-abierta-*` se preservan durante la transicion, pero no se vuelven a generar por el flujo nuevo.

## Cliente Mautic

`FLACSO_Mautic_Client` conserva autenticacion y operaciones HTTP. Gana operaciones explicitas:

- buscar y hacer upsert por email;
- obtener tags existentes y fusionarlos con tags nuevos;
- agregar un contacto a la campana configurada de consultas de manera idempotente;
- consultar la pertenencia del contacto a la campana para diagnostico;
- devolver resultados tipados sin credenciales ni payloads sensibles.

Una respuesta exitosa de API significa sincronizacion aceptada, no entrega de un email. Solo un error real de API, autenticacion, validacion o red habilita el fallback Mailjet. La espera normal de la campana nunca habilita fallback.

## Persistencia y diagnostico

La tabla de consultas mantiene el snapshot existente y gana estado de integracion de campana sin almacenar secretos:

- `mauticSyncStatus`, `mauticContactId`, `mauticSyncedAt`, `mauticLastError` se conservan;
- se agregan estado, ID, fecha de intento, contador y error de incorporacion a campana;
- los reintentos son acotados, idempotentes y administrables;
- los logs estructurados guardan consulta, contacto, campana, operacion, resultado, HTTP, error clasificado y fecha;
- los logs nunca guardan tokens, claves, passwords, cuerpo de consulta ni email completo.

Las clases actuales que intervienen son `class-flacso-offer-inquiry-service.php`, `class-flacso-inquiry-marketing-service.php`, `class-flacso-mautic-client.php`, `class-flacso-consultas-admin.php` y `class-flacso-mail-settings.php`.

## Configuracion y consola

La unica configuracion nueva de negocio en WordPress es `flacso_mautic_campaign_consultas_id`, un entero positivo. Sustituye gradualmente el conocimiento de IDs de emails de Mautic para consultas de oferta.

La consola se renombra conceptualmente como Comunicaciones y separa:

1. resumen operativo;
2. conexion y sincronizacion Mautic;
3. campana de consultas y prueba integral controlada;
4. Mailjet como contingencia;
5. diagnostico y errores recientes sin datos personales.

Credenciales siguen ocultas en campos password y nunca se renderizan en respuestas AJAX o logs.

## Campana Mautic

Se crea manualmente, no mediante WordPress, una campana publicada llamada `Consulta academica`. En la primera activacion solo contiene el email inicial y ramas por `flacso_cohorte_estado` si el contenido aprobado las requiere.

Antes de activar el flujo se deben crear los campos de este documento, aprobar el contenido/remitente y probar mediante un contacto controlado. La campana de prueba existente no se reutiliza como productiva.

## Seguimiento diferido: bloqueo explicito

No se migra ni activa seguimiento con espera durante las primeras fases. Los campos de contacto representan el ultimo snapshot: una segunda consulta puede sobrescribir el contexto requerido por una espera anterior.

Antes de cualquier seguimiento se investigara y aprobara un modelo por consulta compatible con la version instalada de Mautic: objeto personalizado, evento persistente u otro mecanismo que preserve el snapshot. Quedan prohibidos campos numerados, campanas por oferta y contactos falsos.

Mientras tanto, el seguimiento WP-Cron actual permanece apagado por defecto y no se elimina. Se retirara solo despues de una solucion aprobada, piloto y rollback verificado.

## Fases y puertas

1. Contrato y pruebas: DTO, builder, normalizacion, tags nuevos y documentacion; sin enviar ni cambiar configuracion productiva.
2. Cliente y persistencia: tags no destructivos, alta idempotente a campana, estado y logs; flag apagado.
3. Piloto Mautic: crear campos/campana y ejecutar prueba controlada; confirmar API, campos, tags y membership.
4. Activacion inicial: habilitar sincronizacion y entrada a campana; Mailjet queda disponible solo ante fallo real de Mautic.
5. Consola y observacion: diagnostico, reintentos y evidencia de estabilidad.
6. Seguimiento: diseno separado del contexto por consulta, piloto y solo entonces retiro del cron legacy.
7. Retiro de Mailjet: decision posterior basada en estabilidad observada y plan de reversa.

Ninguna fase posterior empieza sin validar la anterior. El rollback de las fases 1-4 es desactivar el flag de campana y volver al flujo transaccional actual; no se eliminan campos, campanas ni datos durante el piloto.

## Criterios de aceptacion de la activacion inicial

- la consulta se guarda aunque Mautic este caido;
- el payload usa exactamente los aliases y valores definidos arriba;
- los tags nuevos se agregan sin remover intereses previos;
- el contacto entra una sola vez a la campana configurada;
- WordPress no conoce IDs de emails de Mautic para el nuevo flujo;
- el fallback no se ejecuta por una demora normal de la campana;
- no se activan seguimientos diferidos;
- pruebas, CI, despliegue y verificacion de la integracion real se informan por separado.
