# Transición de flujos de preinscripción

## Propiedad y dependencias

```text
Editor -> API administrativa del plugin -> InstanciaOferta
                                      |-> Tema / resolver de CTA
                                      `-> catálogo mínimo v1 -> Django

legacy_editor             -> postulaciones en Editor
gestor_preinscripciones   -> postulaciones en Django
```

`flacso-uruguay-plugin` es la fuente de verdad de cada instancia. El CPT interno
`instancia-oferta` aporta un ID WordPress estable separado del ID de la oferta.
Ofertas que todavía no tienen instancias explícitas conservan los campos y las
rutas legacy; no se crea ni migra una postulación automáticamente.

Las postulaciones y sus documentos viven exclusivamente en
`preinscripciones.flacso.edu.uy`; el plugin sólo publica el catálogo y el flujo
transitorio.

## Valores y compatibilidad

- `legacy_editor`: default y destino de toda instancia previa sin valor.
- `gestor_preinscripciones`: activación manual para nuevas cohortes piloto.

No hay backfill automático. Al abrir una instancia se registra una fecha real y
el flujo queda bloqueado. Cerrar la convocatoria no lo desbloquea.

## API administrativa

- `GET|POST /wp-json/flacso/v1/instancias-oferta`
- `GET|PATCH /wp-json/flacso/v1/instancias-oferta/{instancia_wp_id}`

DTO principal:

```json
{
  "id": 81,
  "wpId": 81,
  "academicOfferId": 26595,
  "name": "Cohorte 2027",
  "year": 2027,
  "semester": "1S",
  "number": null,
  "status": "planificada",
  "preinscriptionOpening": "2027-01-15T12:00:00+00:00",
  "preinscriptionManualClosing": null,
  "isInscriptionsOpen": true,
  "preinscriptionFlow": "gestor_preinscripciones",
  "flowLocked": true,
  "pageUrl": "https://preinscripciones.flacso.edu.uy/ofertas/26595/instancias/81/",
  "backofficeUrl": "https://preinscripciones.flacso.edu.uy/gestion/ofertas/26595/instancias/81/"
}
```

Intentar cambiar el flujo de una instancia que ya abrió devuelve HTTP 409. El
estado académico nunca se usa para representar apertura o cierre.

## Catálogo mínimo

`GET /wp-json/flacso/v1/preinscripciones/catalogo` es público y devuelve
`schema_version: 1`. Incluye sólo instancias publicadas con estado
apertura efectiva, flujo `gestor_preinscripciones` y oferta pública sin contraseña.
No expone `post_meta` ni el campo de flujo.

## URLs

- Legacy: `{permalink_oferta}/preinscripcion/`
- Gestor: `https://preinscripciones.flacso.edu.uy/ofertas/{oferta_wp_id}/instancias/{instancia_wp_id}/`
- Backoffice gestor: `https://preinscripciones.flacso.edu.uy/gestion/ofertas/{oferta_wp_id}/instancias/{instancia_wp_id}/`

El tema debe usar `flacso_get_preinscription_url()` o
`flacso_get_preinscription_cta()` y marcar el enlace con
`data-flacso-preinscription-cta="1"` para conservar analytics y atribución.

## Piloto

1. Desplegar plugin, Editor, Django y tema compatibles.
2. En Editor ejecutar `npm run migrate:instances:check` y revisar el inventario;
   luego ejecutar `npm run migrate:instances:apply` en una ventana administrativa.
3. Verificar que el cron Django consume el catálogo vacío o sólo las instancias nuevas.
4. Crear una nueva cohorte en Editor y elegir “Nuevo Gestor”.
5. Abrir preinscripciones y comprobar que aparece en el catálogo v1.
6. Ejecutar `python manage.py sincronizar_catalogo_preinscripciones` en Django.
7. Verificar URL pública, envío, Drive/correos y backoffice Django.
8. Confirmar en paralelo una oferta sin instancia explícita y una cohorte
   `legacy_editor` contra el formulario y el backoffice del Editor.

## Retirada futura

Primero se cambia el default para instancias nuevas; luego se oculta la opción
legacy al crear. Las instancias históricas conservan su valor y URL. Sólo cuando
no existan convocatorias legacy activas se puede pasar el Editor a consulta
histórica y, en una etapa independiente, retirar formularios/webhooks legacy.
