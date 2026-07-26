# Reglas de errores para formularios públicos

Estas reglas se aplican a consultas, inscripciones, preinscripciones y futuros
formularios públicos.

| Tipo | Mensaje al usuario | ¿Avisar al administrador? |
|---|---|---|
| Validación | Indicar el campo y cómo corregirlo. Conservar los datos ingresados. | No |
| Sesión o nonce vencido | Pedir que recargue la página y vuelva a intentar. | No |
| Spam o honeypot | No revelar el control anti-spam. | No |
| Demasiados intentos | Pedir que espere unos minutos. | No, salvo que exista un patrón operativo anormal |
| Servicio externo, base de datos, correo o red | Informar que el envío **no fue confirmado**, permitir reintentar y mostrar un código de seguimiento. | Sí: Telegram y correo |
| Proceso parcial | Decir qué quedó confirmado y pedir que no duplique el envío. | Sí: Telegram y correo, prioridad alta |

## Mensajes públicos

- Deben explicar qué puede hacer la persona ahora.
- Nunca deben mostrar excepciones, HTML remoto, tokens, rutas, consultas SQL ni
  nombres internos de servicios.
- Nunca se muestra éxito si el sistema principal no confirmó la recepción.
- Todo error técnico lleva un código de seguimiento visible.

## Alertas administrativas

La alerta incluye formulario, etapa, fecha, código de seguimiento, página,
correo del usuario cuando corresponda y detalle técnico acotado. No incluye
documentos, mensajes completos, tokens ni contraseñas.

Los errores iguales se agrupan durante cinco minutos para evitar una tormenta
de avisos. Telegram usa la configuración general del módulo. El correo se envía
a `fc_destinatario_email` y, si no está configurado, al correo administrador de
WordPress.

## Responsabilidad de cada capa

- Navegador: validaciones inmediatas y foco en el primer campo inválido.
- Servidor WordPress: seguridad, validación definitiva, código de seguimiento y
  decisión de notificar.
- Servicio receptor: respuesta estructurada y sin detalles sensibles para el
  público; los detalles técnicos quedan en registros y alertas.
