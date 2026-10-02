# Comunicaciones sólo Mautic

## Objetivo

Concentrar las comunicaciones de consultas y suscripciones en Mautic. WordPress
persiste la consulta, sincroniza el contacto y lo incorpora a la campaña
configurada; Mautic decide los correos y automatizaciones posteriores.

## Alcance

- La consola se denomina `Comunicaciones Mautic` y sólo muestra conexión,
  campaña de consultas y estado de sincronización.
- Se eliminan de la UI las credenciales, listas, métricas, plantillas, pruebas
  y referencias a Mailjet, además de los IDs de plantillas de Mautic.
- Las consultas dejan de enviar correos directamente desde WordPress. Tras
  persistirse, ejecutan la sincronización Mautic y la incorporación a campaña.
- Los reintentos y seguimientos gestionados por WordPress dejan de despachar
  correo; Mautic queda a cargo de esas reglas.
- Se preservan opciones y columnas históricas de Mailjet para trazabilidad,
  pero no se leen para realizar nuevas comunicaciones.

## Flujo

1. WordPress valida y guarda la consulta y su snapshot.
2. WordPress crea o actualiza el contacto y sus etiquetas en Mautic.
3. Si la campaña está activada y tiene ID, WordPress incorpora el contacto.
4. Mautic evalúa su campaña y envía cualquier comunicación configurada allí.
5. Una falla de Mautic se registra como estado de sincronización, sin falsear
   éxito de correo ni activar otro proveedor.

## Compatibilidad y seguridad

- No se eliminan datos ni claves existentes automáticamente.
- La campaña permanece opt-in: flag apagado e ID vacío por defecto.
- Los registros existentes de `emailStatus` y campos Mailjet se mantienen
  únicamente como historia.
- Los logs continúan sin PII ni secretos.

## Verificación

- Pruebas de servicio: persistencia exitosa aunque Mautic falle, sin llamadas
  al cliente Mailjet.
- Pruebas de interfaz/configuración: ausencia de controles Mailjet y de IDs
  de plantillas, presencia de conexión y campaña Mautic.
- Suite PHP completa y despliegue oficial con smoke test WordPress.
