# Diseño: seguimiento Mautic por oferta y cohorte

- **Fecha:** 2026-10-22
- **Estado:** Propuesto para revisión
- **Ámbito:** campañas Mautic y plugin `flacso-uruguay-plugin`

## Objetivo

Al completar una consulta de oferta con inscripciones abiertas, WordPress envía el email transaccional F1 (Mautic email 4). Cuando Mautic acepta ese envío, WordPress incorpora al contacto a la campaña correspondiente a la oferta y cohorte. Esa campaña espera siete días y envía el seguimiento F2 (basado en email 6). No se excluyen contactos que ya se hayan preinscripto.

El seguimiento respeta el consentimiento de comunicaciones que ya exige la integración. Si F1 falla o queda en estado incierto, el contacto no se agrega a la campaña; la consulta queda registrada según el comportamiento actual.

## Campañas iniciales

Se preparará una campaña por cada cohorte con inscripciones abiertas al momento del alta. La consulta al catálogo actual de WordPress encontró siete:

- Maestría en Educación, Innovación y Tecnologías — Cohorte VII (cohorte 27466)
- Maestría en Educación, Sociedad y Política — Cohorte X (27467)
- Maestría en Género — Cohorte VI (27468)
- Diploma en Género — Cohorte IX (27639)
- Diploma en IA y Prácticas de Enseñanza — Cohorte IV (27645)
- Diploma en Salud Mental, Subjetividad y Trabajo — Cohorte VI (27648)
- Diploma en Conservación Preventiva y Primeras Intervenciones para Documentos Históricos — Cohorte II (27700)

No se crearán campañas para cohortes cerradas o no disponibles para consulta. Cuando se abra una cohorte nueva, se creará su campaña en Mautic y se añadirá el mapeo a WordPress.

## Estructura de una campaña

Cada campaña tendrá su propio email de seguimiento de tipo campaña, con contenido basado en F2 (email 6), personalizado para la oferta/cohorte de esa campaña. El email existente 6 es de tipo evento y depende de tokens de una entrega; por eso no se conectará directamente como acción de campaña sin adaptar su tipo y verificar que no queden variables sin resolver.

Flujo Mautic por cohorte:

1. Contacto agregado por WordPress tras aceptación del email 4 y con consentimiento válido.
2. Espera de siete días.
3. Envío del email F2 correspondiente a la cohorte.

Las campañas y sus emails se preparan sin publicar mientras se construye la integración y se valida el piloto. El envío automático comienza solo al publicar/activar cada campaña después de comprobarla.

## Cambios de WordPress

- Mantener una configuración explícita `cohortWpId → mauticCampaignId`, validada y administrable desde Comunicaciones.
- Al aceptar Mautic el envío inicial F1, agregar el contacto a la campaña indicada por el `cohortWpId` persistido en el snapshot de esa consulta.
- No usar datos académicos mutables del contacto para decidir la campaña.
- Hacer idempotente la incorporación; guardar el ID y resultado de campaña en el registro de la consulta.
- No incorporar consultas sin cohorte abierta, sin consentimiento completo, ni F1 aceptado.
- No alterar emails transaccionales 4/5, registros históricos, tags existentes, fallback, ni volver a procesar consultas anteriores.

## Preparación y puesta en marcha

1. Revisar esta especificación.
2. Preparar en Mautic los siete pares campaña/email; probar que cada campaña espera siete días y usa su contenido correcto; mantenerlos sin publicar inicialmente.
3. Implementar en WordPress el mapeo, alta posterior a F1 aceptado, registro de estado, controles administrativos y verificación automatizada.
4. Desplegar WordPress con el mapeo apagado/vacío; validar la integración en un piloto controlado.
5. Cargar los IDs definitivos y activar las campañas de forma coordinada; comprobar el primer alta real y que no haya incorporaciones duplicadas.

## Criterios de aceptación

- Cada una de las siete cohortes abiertas tiene una campaña inequívocamente asociada.
- La entrada ocurre después de que Mautic acepte F1, no al guardar el formulario.
- El F2 sale siete días después de la entrada, con nombre de oferta/cohorte y enlaces correctos.
- Contactos sin consentimiento, F1 fallido/incierto o sin cohorte válida no se incorporan.
- El mismo evento no crea membresías duplicadas y conserva trazabilidad por consulta.
- No se consultan ni excluyen datos de preinscripción.
- Campañas previas, contactos y correos transaccionales permanecen intactos.
