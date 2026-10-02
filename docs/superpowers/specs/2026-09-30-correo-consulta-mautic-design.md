# Correo íntegro de consultas en Mautic

## Objetivo

Reemplazar el acuse histórico de Mailjet por un correo transaccional de Mautic que conserve su contenido, estructura y datos de la consulta. WordPress seguirá siendo quien captura y persiste la solicitud; Mautic recibirá el perfil necesario para personalizar el correo y ejecutar la campaña.

## Alcance

- Mantener el MJML histórico: encabezado institucional, detalle de la oferta, modalidad, inicio, enlaces de preinscripción e información, contenido institucional, convenios y el resumen de datos ingresados.
- Sustituir las variables de Mailjet por tokens de campos de contacto Mautic.
- Extender el contrato WordPress a Mautic con los campos que faltan para el resumen del formulario.
- Crear en Mautic los campos de contacto compatibles y un correo de tipo evento/transaccional.
- Reemplazar la acción de prueba de la campaña de consultas por el correo nuevo, sin activar la campaña durante esta entrega.

## Contrato de contacto

Los campos estándar se mantienen como `email`, `firstname` y `lastname`. Se agregan los siguientes campos de contacto, de texto, todos opcionales para no impedir la sincronización de consultas históricas:

| Alias | Fuente del POST | Uso en el correo |
| --- | --- | --- |
| `flacso_pais` | `pais` | País de residencia |
| `flacso_nivel_academico` | `nivel_academico` | Nivel académico |
| `flacso_profesion` | `profesion` | Profesión |

El contrato conserva los aliases existentes: `flacso_oferta_articulo`, `flacso_oferta_nombre`, `flacso_modalidad`, `flacso_fecha_inicio`, `flacso_oferta_url` y `flacso_preinscripcion_url`.

## Plantilla

El correo se llamará `Acuse de recibo de consulta académica`, será de tipo evento/transaccional y tendrá este asunto:

```
Recibimos tu consulta sobre {contactfield=flacso_oferta_nombre}
```

El MJML adaptará las variables de Mailjet a tokens Mautic. El bloque de datos ingresados utilizará `firstname`, `lastname`, `email`, `flacso_pais`, `flacso_nivel_academico` y `flacso_profesion`. Los botones usarán `flacso_preinscripcion_url` y `flacso_oferta_url`.

## Flujo

1. El formulario público valida y persiste la consulta.
2. El servicio de marketing construye el payload de contacto, incluyendo los tres campos nuevos.
3. Mautic crea o actualiza el contacto y WordPress lo incorpora a la campaña configurada.
4. La campaña activa evalúa el evento y Mautic envía el acuse transaccional.

## Errores y compatibilidad

- Si una consulta histórica no contiene alguno de los nuevos valores, Mautic recibe una cadena vacía y el resto del contacto se sincroniza.
- WordPress no registra valores de la consulta en logs de integración.
- La aceptación de Mautic confirma sincronización e incorporación a campaña, no entrega del correo.

## Verificación

- Prueba unitaria del constructor de payload con los tres campos nuevos.
- Prueba de servicio que verifica que dichos aliases llegan al cliente Mautic.
- Revisión del correo en Mautic con un contacto interno que tenga todos los campos.
- Verificación del nodo de correo correcto en la campaña, que debe permanecer inactiva hasta la aprobación de activación y prueba de envío.
