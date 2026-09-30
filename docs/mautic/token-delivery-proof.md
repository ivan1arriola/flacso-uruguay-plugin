# Prueba de tokens para el acuse transaccional

Estado: **pendiente de autorización operativa**.

La cola transaccional permanece desactivada y el manifiesto mantiene la huella
SHA-256 vacía hasta completar esta prueba. Un push del código no autoriza
envíos ni cambios de configuración en Mautic.

## Objetivo

Demostrar con un contacto interno autorizado que el endpoint de envío directo
de Mautic resuelve los tokens suministrados por cada entrega y no campos
mutables del contacto.

## Procedimiento requerido

1. Crear y validar un backup antes de cualquier cambio operativo.
2. Utilizar una plantilla aislada y un destinatario interno autorizado.
3. Preparar dos InquirySnapshot distintos para el mismo correo, con oferta,
   fecha, modalidad y enlaces diferentes.
4. Enviar ambos acuses por el endpoint directo de Mautic.
5. Comprobar asunto y cuerpo renderizado de ambos mensajes.
6. Registrar únicamente evidencia redactada: IDs técnicos, SHA de plantilla y
   resultado approved o rejected; nunca payloads completos ni datos de terceros.
7. Si el resultado es approved, registrar en FLACSO_Mautic_Contract_Manifest
   el SHA-256 exacto de la plantilla aprobada.
8. Si es rejected, mantener la cola desactivada y rediseñar la vía
   transaccional. No sustituirla por una campaña.

La aceptación HTTP de Mautic confirma aceptación de la solicitud, no entrega,
apertura ni lectura.
