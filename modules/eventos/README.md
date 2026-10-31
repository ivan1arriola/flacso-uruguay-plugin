# FLACSO Uruguay - Eventos

Módulo del CPT `evento`: fechas, modalidad, enlaces de acceso y lectura por REST.

La inscripción **no** se gestiona aquí. Cada evento puede guardar la URL de su formulario en Formularios FLACSO
(`_evento_formulario_url`); la página del evento (tema `single-evento.php`) muestra el botón «Inscribirme» solo si esa URL está cargada.

## Endpoints (solo lectura)

Referencia completa en [API.md](../../API.md).

- `GET /wp-json/flacso-charlas/v1/eventos` y `/eventos/{id}`

El endpoint `POST /inscripcion`, el bloque Gutenberg del formulario y el webhook de inscripciones fueron retirados.

El tipo `charla_abierta`, sus endpoints `/charlas` y `/charla-abierta` y la migración hacia `evento` fueron retirados. Los metadatos `_charla_*` se conservan como nombres internos de los campos del evento.
