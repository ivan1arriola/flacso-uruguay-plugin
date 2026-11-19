# Campaña de posgrados 2027: ejecución al 19 de noviembre de 2026

## Actualización final: despliegue y operaciones

| Tarea | Estado al cierre de esta ejecución |
|---|---|
| 1. Inventario y pruebas | Parcial: faltan accesos, dispositivos reales, WebViews y recorridos de extremo a extremo con identidad de prueba y consentimiento apropiado. |
| 2. Consultas y eventos | Parcial: plugin desplegado y HTML verificado; faltan trazas reales de conversión y deduplicación. |
| 3. Atribución y planillas | Parcial: snapshot WP y fuente del portal analizados; faltan contrato y propagación a Mautic y planillas. |
| 4. Correo 24 | Parcial: UTM aplicadas y verificadas; faltan comprobación de enlaces y decisiones sobre frecuencia y bots. |
| 5. GA4 y móvil | Parcial: inventario y muestras HTTP; faltan acceso a GA4/GTM y pruebas móviles. |
| 6. Meta | Parcial: hoja revisada; faltan acceso a Meta Ads y pruebas de anuncios y macros. |
| 7. Presupuesto | Pendiente: revisión del 25/11 y seguimiento hasta el 22/12; no se modificó presupuesto. |
| 8. Liberación | Parcial: plugin publicado y activo; faltan recorridos y eventos reales de extremo a extremo. |

El commit `cd11b1322af761af2fdb733e57d60bd53530943c` se publicó en `origin/main` y `main` local. [GitHub Actions](https://github.com/ivan1arriola/flacso-uruguay-plugin/actions/runs/44454656903) terminó correctamente en 46 segundos; WP-CLI confirmó el plugin `8.0.0.1306` activo. El GET de MESYP confirmó `strict_confirmation=true`, `direct_lead=true` y `old_marker=false`. Los resultados de las pruebas simuladas no demuestran entrega real de eventos. Reversión de código: `git revert cd11b1322af761af2fdb733e57d60bd53530943c`, con pruebas y verificación del despliegue posterior.

Se recuperó SSH y acceso API de Mautic: versión `7.2.1`, GET API 200 e integración Mautic configurada. Meta CAPI está habilitada sin modo de prueba. Entre 200 campos de contacto inspeccionados no se encontró campo UTM; no se leyó el total de resultados, por lo que esto no describe necesariamente todos los campos disponibles. La credencial CAPI actual no confirmó acceso administrativo a Meta Ads: una lectura de campaña con Graph v25 devolvió HTTP 400, código 10 de permisos.

El correo 24, «Difusión - Posgrados 2027 - 30% de descuento (resto de la base, lista invertida)», estaba en borrador, `sentCount=0`, sin publicar y revisión 2. Tras dry run y revisión se aplicó solo PATCH de UTM nativas: source `mautic`, medium `email`, campaign y content `correo_24_posgrados_2027`; antes campaign y content eran `convenios_inscripciones2027`. La lectura posterior confirmó los valores y revisión 3. HTML de 34.387 bytes, asunto, listas, publicación y contador de envíos permanecieron iguales. No se envió ni programó el correo. La reversión sería PATCH de los valores anteriores, previa comprobación del estado actual. Referencias: [UTM nativas](https://docs.mautic.org/en/8.0/utm_tags/utm_tags_emails.html) y [API de emails](https://devdocs.mautic.org/en/7.2/rest_api/emails.html).

La auditoría SQL agregada del correo 23 encontró 6.208 hits; redirects 157–165 registraron aproximadamente 480–550 hits cada uno. Hubo 254 grupos contacto-segundo con al menos cinco enlaces distintos, correspondientes a 239 contactos distintos en esos grupos. Son indicios de automatización, sin prueba concluyente. Ningún user-agent coincidió con bot, scanner, safelinks, proofpoint, barracuda o mimecast; eso tampoco prueba actividad humana. DNC para email, `channel_id=23` y razón 1 (`UNSUBSCRIBED`) devolvió 126, frente a 122 informadas anteriormente. No se filtraron ni borraron datos ni se cambió frecuencia; decidir con responsables. [Mautic documenta filtros de bots](https://docs.mautic.org/en/7.2/configuration/settings.html).

El [repositorio del portal](https://github.com/ivan1arriola/preinscripcionesFLACSOUY) tiene `main` en `582ac31` y la versión identificada en producción es `3d773d783a4f39bc09f15ec46ae2c96f534ccf6b`. La fuente remota captura cinco UTM en `submissions.utm` JSONB (migración 029), pero aún no las propaga a Sheets ni Mautic. Pixel y CAPI de `SubmitApplication` comparten ID en esa fuente. La ausencia de literales UTM o eventos en el bundle público no demuestra equivalencia de versiones ni configuración GTM. Falta acordar el contrato y desplegar una versión coordinada; no se desplegó el portal, que incluye cambios remotos ajenos no verificados.

La base identifica 82 planillas por oferta/cohorte, no una única. Muestras identificadas sin leer contactos ni añadir encabezados Canal: [DCPP cohorte II](https://docs.google.com/spreadsheets/d/1CIZ532ESalZNpuQrF0recgYDnrIIatAbQyKbjlNmpE0/edit), [DESI cohorte IV](https://docs.google.com/spreadsheets/d/1Stnnn7Q1hlSsdjz5rTB0RNHXwXLEtbVaDom13q0FWlU/edit) y [MESYP cohorte X](https://docs.google.com/spreadsheets/d/1gFRZYoUFfYZActoBdIaUbv1ONIluvIHE4rdZSWGH-6Q/edit).

La metadata de la muestra asociada en la base a DESI cohorte IV y pestaña `Inscripciones` muestra título «Diplomado de Especialización en Género con Orientación en Salud Integral — Cohorte I», pestaña visible `Hoja 1`, grilla 1000 × 31 y zona `Etc/GMT`. Solo se leyó `Hoja 1!A1:AE1`: sus 31 encabezados no incluyen Canal ni UTM. Reproducir y reconciliar esta asociación antes de cambiar las 82 planillas; la discrepancia no demuestra por sí sola un fallo de envío.

Site Kit indica propiedad GA4 `363767035`, flujo web `4891419204`, medición `G-38FYG8W6BQ` y `useSnippet=true`. Siguen pendientes acceso a GA4/GTM y Meta Ads, pruebas reales en dispositivos y de extremo a extremo con Mautic y planillas, y la decisión de contrato y versión del portal. Root supervisó y revisó; Terra preparó código y script; Luna revisó sin hallazgos bloqueantes.

La verificación final ejecutó `python scripts/agent_check.py --diff` y `python scripts/agent_check.py`, ambos con `RESULTADO: [EXITO]`: 357 archivos PHP con sintaxis válida, 8 pruebas F0 y 133 contratos PHP. También pasaron `node tests/inquiry-confirmation-client-test.js` y `git diff --check` sin observaciones.

## Evidencia previa y contexto

Plan: `docs/superpowers/plans/2026-11-18-campana-posgrados-2027.md`. La fecha de envío del correo 24 no está confirmada. La revisión presupuestaria del 25/11 y el cierre del 22/12 son futuros.

## Estado por tarea

5. **GA4 y experiencia móvil: parcial.** Muestras HTTP desde un único host, sin redirecciones: DCPP TTFB 0,753 s / total 1,172 s / 203.900 bytes; DESI 1,771 / 2,334 / 245.993; MESYP 1,905 / 2,474 / 265.696; EGCCD 0,616 / 1,166 / 224.739; DEVNNA 1,313 / 1,739 / 208.050. No son mediciones móviles ni LCP. En HTML de DCPP y DESI se observó un formulario, cinco campos UTM ocultos y tres CTA externos por página. Falta exploración GA4 y prueba en dispositivos reales.
6. **Meta: parcial.** La hoja de campaña registra 46 anuncios y una plantilla UTM ya definida con `paid_social`, campaña, `utm_id`, contenido y término específicos; no reemplazarla por la plantilla genérica del informe. Solo tres celdas marcan UTM aplicado y 43 indican FALSE; esto no confirma el estado en Meta. Faltan inventario y prueba de macros en anuncios reales.
7. **Presupuesto: pendiente.** La hoja registra 15 conjuntos y USD 62 diarios (6 + 14 × 4); no se modificó presupuesto ni se emitió una decisión de reasignación.

## Próximos pasos de analítica

Con acceso a GA4 y GTM, inventariar primero etiquetas y disparadores. Validar `generate_lead` para consultas con `form_type=consulta_oferta_academica` o `lead_type=consulta_wordpress_oferta`; verificar que esos parámetros estén disponibles antes de filtrar. Construir exploración `form_view` → `form_start` → `form_submit_attempt` → `generate_lead`, separando el flujo externo. Marcar el evento clave solo tras validar alcance y evitar contar como conversión adicional un `form_submit` automático. Ninguna configuración fue ejecutada.

El [informe de consultas en Drive](https://docs.google.com/spreadsheets/d/1pj12nWRwEuQFdf1SHB_MQG56dmlqq6Ta9bgo9NRryFw/edit) registra 621 consultas, 546 personas, 305 de campaña y 260 sin fuente (41,9 %) para el período parcial del 1 al 18 de noviembre. La zona de la hoja figura como `America/Los_Angeles`, mientras el texto menciona horario de Uruguay: revisar antes de comparar ventanas o tasas. La [hoja de campaña Meta](https://docs.google.com/spreadsheets/d/16rEK7vSbWmcJHC-IKyBZ1-3Bc5ZCMCVJEAb07Hezcxc/edit) es fuente de los datos de anuncios y presupuesto citados arriba. No se modificaron hojas. El bundle público del portal sin literales UTM o eventos no demuestra la configuración de GTM.

Se requieren accesos a GA4/GTM y Meta Ads, dispositivos y recorridos con identidad de prueba y consentimiento apropiado para completar el plan. No se realizaron pruebas móviles reales ni envíos de prueba de extremo a extremo.

