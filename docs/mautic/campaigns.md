# Consultas: correo transaccional y campañas

## Acuse transaccional

- Plantilla Mautic: Acuse de recibo de consulta académica
- ID: 3
- Uso: envío directo a un contacto desde la cola transaccional.
- Fuente de datos: tokens persistidos con cada InquirySnapshot.
- Campaña Mautic: **no interviene en el acuse**.

La plantilla debe resolver datos como oferta, cohorte, fecha, modalidad y enlaces desde los tokens de la entrega. No debe reconstruirlos desde campos mutables del contacto.

El manifiesto mantiene deliberadamente vacío content_sha256 hasta completar la prueba controlada descrita en token-delivery-proof.md. Mientras la huella no esté aprobada, el validador devuelve blocked y la cola no puede enviar.

## Campaña comercial

- Campaña existente: Consultas web FLACSO
- ID: 2
- Rol nuevo: seguimiento/marketing posterior, no acuse.
- Opción de WordPress: flacso_mautic_campaign_enabled
- Estado operativo durante la transición: desactivada.

Aunque la opción se active posteriormente, sync_commercial_contact() exige consentimiento completo (granted, acceptedAt, source, textVersion) antes de añadir un contacto a la campaña.

## Estado de la transición

1. Consulta, snapshot y entrega se persisten atómicamente.
2. La entrega empieza en pending.
3. El worker sólo procesa si flacso_inquiry_delivery_queue_enabled = 1.
4. El contrato Mautic debe validar campos, plantilla y SHA-256.
5. Un timeout después de iniciar el POST de envío pasa a acceptance_unknown y requiere conciliación manual.
6. No existe fallback automático a Mailjet ni a wp_mail en el flujo nuevo.

Los bloques Mailjet heredados permanecen inalcanzables únicamente hasta que el piloto sea aprobado. Su retirada se realiza después de esa evidencia, tal como indica el plan de implementación.

La aceptación HTTP de Mautic confirma aceptación API, no entrega efectiva del mensaje.
