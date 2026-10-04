# API académica final

Base: `/wp-json/flacso/v1`.

## Entidades

| Entidad | Colección | Detalle |
|---|---|---|
| ProgramaAcademico | `/programas-academicos` | `/programas-academicos/{id}` |
| OfertaAcademica | `/ofertas` | `/ofertas/{id}` |
| Cohorte | `/cohortes` | `/cohortes/{id}` |
| Seminario | `/seminarios` | `/seminarios/{id}` |
| Edicion | `/ediciones` | `/ediciones/{id}` |
| TablaPrecio | `/tablas-precio` | `/tablas-precio/{id}` |

Todas las colecciones aceptan `GET` y `POST`. Los recursos aceptan `GET`,
`PUT|PATCH` y `DELETE`. La lectura publicada es pública; las escrituras requieren
`edit_posts`. `DELETE` envía a papelera y sólo borra definitivamente con
`?force=true`.

Las colecciones temporales aceptan `parent_id`:

- `/cohortes?parent_id={oferta_id}`
- `/ediciones?parent_id={seminario_id}`

## Catálogo de preinscripción (v1)

`GET /wp-json/flacso/v1/preinscripciones`

Devuelve el catálogo de destinos académicos (cohortes y ediciones de seminarios) tanto abiertos como cerrados, con su configuración efectiva de formulario, revisiones canónicas (`configRevision`), orientaciones/menciones y enlaces asociados.

- Acceso público de solo lectura (sin autenticación requerida para GET).
- Encabezado `Cache-Control: public, max-age=60`.
- No almacena ni devuelve datos personales ni información sensible.
- `link_preinscripcion` se conserva en `urls.legacyRegistration` para permitir coexistencia y reversibilidad durante el rollout.

`link_preinscripcion` se carga en cada Cohorte o Edicion y debe apuntar
a `https://preinscripciones.flacso.edu.uy`. El plugin no presume una estructura
de ruta, no contiene formularios locales ni selección de flujo.

## Carga de datos

Los datos transformados localmente se cargan por `POST` en este orden:

1. programas académicos;
2. tablas de precios;
3. seminarios;
4. ofertas académicas;
5. ediciones de seminario;
6. cohortes;
7. relaciones `seminarios`, `componentes` y `ediciones_componentes` mediante
   `PATCH` cuando ya se conocen todos los IDs de WordPress.

Los `POST` de creación no deben enviar `id`. WordPress asigna IDs totalmente
nuevos y cada respuesta devuelve el ID creado. El proceso local mantiene una
tabla temporal `clave_de_origen → id_nuevo`, y usa esos IDs nuevos al cargar
padres, precios, docentes y relaciones. Esa clave de origen no se persiste en
WordPress.

No existe un endpoint que interprete el esquema anterior ni un requisito de
conservar sus IDs.
