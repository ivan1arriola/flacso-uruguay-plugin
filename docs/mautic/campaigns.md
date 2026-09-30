# Campaña de consultas

## Correo transaccional

- Nombre: `Acuse de recibo de consulta académica`
- ID de Mautic: `3`
- Tipo: email por evento (`template` en la API de Mautic)
- Estado: publicado
- Asunto: `Recibimos tu consulta sobre {contactfield=flacso_oferta_nombre}`

El correo usa el HTML compilado desde `acuse-consulta-academica.mjml`. La vista previa y la entrega siguen pendientes de una prueba con un contacto interno; esta entrega no envió correos.

## Flujo preparado, sin activar

- Campaña: `Consultas web FLACSO`
- ID de Mautic: `2`
- Fuente: segmento `Prueba` (ID `10`)
- Nodo único: `Enviar correo electrónico`, inmediato, correo ID `3`, tipo `template`
- `Activo`: no
- `Permitir reinicio`: no

WordPress conoce el ID `2`, pero la opción `flacso_mautic_campaign_enabled` permanece en `0`. No se incorporarán contactos ni se ejecutará la campaña hasta aprobar una prueba interna de extremo a extremo.

La aceptación de la API confirma sincronización y pertenencia a campaña; no confirma entrega de correo.
