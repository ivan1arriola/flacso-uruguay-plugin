# API académica final

Base: `/wp-json/flacso/v1`.

## Entidades

| Entidad | Colección | Detalle |
|---|---|---|
| ProgramaAcademico | `/programas-academicos` | `/programas-academicos/{id}` |
| OfertaAcademica | `/ofertas` | `/ofertas/{id}` |
| Cohorte | `/cohortes` | `/cohortes/{id}` |
| Seminario | `/seminarios` | `/seminarios/{id}` |
| EdicionSeminario | `/ediciones-seminario` | `/ediciones-seminario/{id}` |
| TablaPrecio | `/tablas-precio` | `/tablas-precio/{id}` |

Todas las colecciones aceptan `GET` y `POST`. Los recursos aceptan `GET`,
`PUT|PATCH` y `DELETE`. La lectura publicada es pública; las escrituras requieren
`edit_posts`. `DELETE` envía a papelera y sólo borra definitivamente con
`?force=true`.

Las colecciones temporales aceptan `parent_id`:

- `/cohortes?parent_id={oferta_id}`
- `/ediciones-seminario?parent_id={seminario_id}`

## Catálogo de preinscripción

`GET /preinscripciones/catalogo` devuelve únicamente cohortes y ediciones con
preinscripción abierta. Los ítems tienen una de estas formas:

```json
{"kind":"oferta_academica","oferta":{},"cohorte":{}}
{"kind":"seminario","seminario":{},"edicion":{}}
```

Las URLs apuntan siempre a `https://preinscripciones.flacso.edu.uy`; el plugin
no contiene formularios locales ni selección de flujo.

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

No existe un endpoint que interprete el esquema anterior. La transformación y
la tabla de correspondencias entre IDs viejos y nuevos pertenecen al proceso
local de migración.
