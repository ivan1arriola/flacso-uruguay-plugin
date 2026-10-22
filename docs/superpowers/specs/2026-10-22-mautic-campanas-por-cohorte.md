# Diseño: seguimiento Mautic por oferta con estado vigente

- **Fecha:** 2026-10-22
- **Estado:** Propuesto para revisión
- **Ámbito:** campañas Mautic y plugin `flacso-uruguay-plugin`

## Objetivo

WordPress envía el acuse F1 a la consulta. Cuando Mautic acepta el envío de F1 abierto (email 4), y existe consentimiento comercial válido, incorpora al contacto a una campaña Mautic de esa oferta. La campaña espera siete días y decide con el estado vigente si envía el seguimiento F2 abierto (basado en email 6) o un mensaje de oferta cerrada (basado en email 5, con CTA a seminarios abiertos). No se consulta ni excluye a personas preinscriptas.

## Campañas

Crear 18 campañas, una por oferta académica. La consulta de solo lectura al catálogo WordPress (2026-10-22) encontró 19 registros de oferta/cohorte y 18 ofertas distintas. Siete cohortes están abiertas y ninguna oferta tiene más de una cohorte abierta al momento de esta consulta. El estado de cada cohorte puede cambiar luego; WordPress lo verificará al momento del seguimiento.

El mapa operativo de WordPress será `offerWpId → mauticCampaignId`. Cohortes de una misma oferta convergen en su campaña de oferta. Si una oferta tuviera varias cohortes abiertas a la vez, se considerará abierta mientras haya una cohorte inscribible y el CTA llevará a la página canónica de la oferta para que la persona elija la cohorte vigente.

## Recorrido de cada campaña

1. WordPress persiste la consulta y su snapshot inmutable.
2. Si la consulta corresponde a una oferta con inscripciones abiertas y consentimiento completo, WordPress envía F1 (email 4).
3. Solo después de que Mautic acepte F1, WordPress incorpora el contacto a la campaña de esa oferta. El caso de F1 fallido o con aceptación incierta no se incorpora.
4. Mautic espera siete días.
5. La campaña marca su campo de estado específico como `verificando` y llama por webhook al endpoint autenticado de WordPress. El endpoint consulta el catálogo académico vigente para esa oferta y actualiza en Mautic el campo de estado exclusivo de esa oferta.
6. La campaña evalúa ese campo: `abierta` lleva a F2 abierto; `cerrada` lleva al mensaje de cierre. Un estado pendiente o un fallo de consulta no envía ninguno de los correos y deja evidencia operativa para reintento/diagnóstico.

Mautic admite acciones de espera y webhooks en el Campaign Builder; WordPress puede consultar el estado académico en tiempo de ejecución. El orden y actualización del campo antes de la rama se comprobarán en un piloto real antes de publicar las campañas.

## Correos y personalización

- Preparar para cada oferta un F2 abierto de tipo compatible con el Campaign Builder, basado en el diseño y contenido de email 6. El CTA dirige a la página canónica de la oferta, que presenta la cohorte inscribible vigente.
- Preparar para cada oferta un mensaje de cierre basado en el email 5, con el CTA acordado para ver seminarios abiertos.
- Los datos propios de la oferta se fijan en sus correos; los datos personales se resuelven con tokens estándar de contacto Mautic. No se usan campos globales mutables para guardar la última oferta consultada.
- Mantener remitente, reply-to, baja, seguimiento y contenido aprobado de FLACSO. Revisar tokens y enlaces antes de activar.

## Cambios de WordPress

- Reemplazar la configuración de una campaña global por el mapa validado `offerWpId → mauticCampaignId`.
- Incorporar a campaña después de la aceptación de F1, no al guardar el formulario.
- Añadir un endpoint de webhook autenticado y acotado a una oferta/contacto, que consulte el catálogo de WordPress y sincronice un campo de estado independiente por oferta (`flacso_estado_oferta_{offerWpId}`).
- Limitar los valores al conjunto `verificando`, `abierta`, `cerrada` y `error`; fallos nunca deben dejar el estado previo como si fuera una lectura nueva.
- La rama de campaña solo envía con estado reciente `abierta` o `cerrada`; ante error queda sin envío y genera registro sin PII.
- Hacer idempotente la membresía y conservar trazabilidad por consulta. Mantener las reglas de consentimiento existentes.
- No alterar emails F1 4/5, campañas ya existentes, contactos, etiquetas, fallback ni consultas históricas.

## Puesta en marcha

1. Revisar esta especificación.
2. Preparar las 18 campañas con ramas, espera y correos de cada oferta; dejarlas sin publicar durante la implementación.
3. Implementar el mapeo, el webhook autenticado, la actualización de estado, la rama de error y su diagnóstico en WordPress.
4. Verificar controles automatizados, desplegar con el mapa vacío/inactivo y probar con un contacto controlado; comprobar estado abierto, estado cerrado, fallo de endpoint, consentimiento y alta posterior a F1.
5. Cargar los 18 IDs, activar en coordinación y comprobar una primera incorporación real sin duplicados.

## Criterios de aceptación

- Existen 18 campañas inequívocas, una por oferta.
- El seguimiento ocurre siete días después de la aceptación de F1.
- Cada campaña consulta el estado vigente de su propia oferta al vencerse la espera.
- Estado abierto envía F2 basado en email 6; estado cerrado envía el correo acordado basado en email 5.
- Error, estado sin verificar, falta de consentimiento o F1 fallido/incierto no producen seguimiento.
- Enlaces llevan a páginas de oferta vigentes y los datos de otra oferta no interfieren.
- El alta es idempotente y se conserva trazabilidad por consulta.
- No se consulta ni excluye a personas preinscriptas.
- La comprobación del estado y el enrutamiento se valida con los cron y la versión de Mautic instalados, antes de publicar.
