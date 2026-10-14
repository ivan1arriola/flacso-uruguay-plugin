# Formulario de Contacto

El formulario general guarda una consulta privada (`fc_consulta`, menú Consultas)
en WordPress y envía una notificación mediante las credenciales Mautic existentes.
No usa el webhook del Editor ni Mailjet para entregar esta notificación.

- Para: `inscripciones@flacso.edu.uy` (contacto institucional en Mautic).
- BCC: `web@flacso.edu.uy`.
- Reply-To: correo validado de la persona que consulta.
- Remitente: configuración global de Mautic.
- Contenido: nombre, apellido, correo, teléfono, asunto y consulta.

Cada consulta prepara un email privado e independiente mediante `/api/emails/new`
y lo envía con `/api/emails/{id}/contact/{id}/send`. No se modifica una plantilla
compartida ni se agrega al contacto a campañas. La cuenta API necesita permisos
para buscar/crear/actualizar contactos, crear emails y enviarlos. El transporte
de Mautic puede ser SES; WordPress no necesita credenciales SES.

El éxito confirma aceptación por la API, no recepción en la bandeja de destino.
Un fallo definido permite reintentar; un timeout durante el envío o una excepción
deja el registro en `acceptance_unknown` y bloquea el reenvío idéntico hasta que
un administrador concilie el envío. Revisar primero Mautic y su transporte.

La consulta conserva `_fc_event_id`, `_fc_reference`, `_fc_delivery_lock`,
`_fc_delivery_status` y `_fc_mautic_email_id`. El código visible de seguimiento
permite buscar el registro por `_fc_reference`; `_fc_delivery_lock` identifica
exactamente la opción que debe conciliarse.
El bloqueo se guarda como opción `fc_form_duplicate_{sha256}_lock`, donde el hash
usa correo, asunto y mensaje normalizados igual que el handler. Si se comprueba
que no hubo envío, eliminar esa opción permite reintentar. No desbloquear sin
conciliar: podría duplicar una notificación ya aceptada. Un proceso interrumpido
también conserva su bloqueo para revisión manual.

El formulario no envía un acuse automático al contacto; Inscripciones responde
usando Reply-To. Las consultas generales de otros canales que aún ingresan al
Editor conservan su propio flujo.
