# FLACSO Uruguay - Eventos (charlas abiertas)

Módulo del CPT `evento`: fechas, modalidad, enlaces de acceso y lectura por REST.

La inscripción **no** se gestiona aquí. Cada evento puede guardar la URL de su formulario en Formularios FLACSO
(`_evento_formulario_url`); la página del evento (tema `single-evento.php`) muestra el botón «Inscribirme» solo si esa URL está cargada.

## Endpoints (solo lectura)

Referencia completa en [API.md](../../API.md).

- `GET /wp-json/flacso-charlas/v1/eventos` y `/eventos/{id}`
- `GET /wp-json/flacso-charlas/v1/charlas` y `/charlas/{id}`
- `GET /wp-json/flacso-charlas/v1/charla-abierta` y `/charla-abierta/{id}` (alias legacy)

El endpoint `POST /inscripcion`, el bloque Gutenberg del formulario y el webhook de inscripciones fueron retirados.
