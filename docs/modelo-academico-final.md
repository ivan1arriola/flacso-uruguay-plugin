# Modelo académico final

Este documento es la fuente de verdad del modelo académico de FLACSO Uruguay a
partir de la versión 7 del plugin. No describe una transición ni conserva
contratos anteriores.

## Vista general

```text
ProgramaAcademico
├── OfertaAcademica
│   ├── Cohorte
│   └── seminarios[] ───────────────┐
└── Seminario                       │
    ├── EdicionSeminario            │
    └── componentes[] -> Seminario  │
                                     │
OfertaAcademica <────────────────────┘

Cohorte ───────────> TablaPrecio
EdicionSeminario ──> TablaPrecio

ProgramaAcademico.coordinacion[] ──> Docente
Seminario.docentes_base[] ─────────> Docente
EdicionSeminario.docentes[] ───────> Docente
```

`OfertaAcademica` y `Seminario` son raíces distintas. `Cohorte` sólo puede
pertenecer a una oferta y `EdicionSeminario` sólo puede pertenecer a un
seminario. No existe una entidad temporal genérica.

## ProgramaAcademico

CPT: `programa-academico`.

| Campo | Tipo | Regla |
|---|---|---|
| `id` | integer | ID WordPress |
| `nombre` | string | obligatorio; título del post |
| `slug` | string | único |
| `contenido` | HTML | descripción principal |
| `resumen` | string | resumen público |
| `imagen` | media | imagen destacada |
| `correo` | email | contacto del programa |
| `coordinacion` | array | `{docente_id, nombre, rol}` |
| `orden` | integer | orden institucional |
| `estado_publicacion` | enum | `publish`, `draft`, `pending`, `private` |

Agrupa ofertas y seminarios. No es una taxonomía y no existe `area_tematica`.

## OfertaAcademica

CPT: `oferta-academica`.

Taxonomía cerrada `tipo-oferta-academica`:

- `doctorado`
- `maestria`
- `especializacion`
- `diplomado`
- `diploma`

`seminario` no es un tipo de oferta.

| Campo | Tipo | Regla |
|---|---|---|
| `programa_academico_id` | integer | referencia obligatoria a ProgramaAcademico |
| `tipo` | enum | uno de los cinco tipos anteriores |
| `nombre`, `slug`, `contenido`, `resumen`, `imagen` | core WP | identidad pública estable |
| `correo` | email | contacto |
| `presentacion` | HTML | presentación académica |
| `objetivo_general` | HTML | objetivo general |
| `objetivos_especificos` | HTML[] | lista ordenada |
| `composicion_academica` | object[] | `{titulo, contenido}` |
| `forma_aprobacion` | HTML | forma de aprobación |
| `carga_horaria` | number | no negativa |
| `carga_horaria_descripcion` | string | detalle opcional |
| `creditos` | number | no negativo |
| `acreditacion` | HTML | texto institucional |
| `seminarios` | object[] | relación explícita con seminarios |

Cada elemento de `seminarios` tiene:

```json
{
  "seminario_id": 120,
  "orden": 3,
  "caracter": "obligatorio",
  "creditos_reconocidos": 4
}
```

`caracter` admite `obligatorio` u `opcional`. La relación expresa pertenencia
académica; nunca contiene fechas ni selecciona una edición.

## Cohorte

CPT interno: `cohorte`. No tiene URL pública propia.

| Campo | Tipo | Regla |
|---|---|---|
| `oferta_academica_id` | integer | padre obligatorio |
| `nombre` | string | nombre visible de la cohorte |
| `anio` | integer | 2000–2200 |
| `periodo` | string | etiqueta libre, por ejemplo `segundo semestre` |
| `numero` | integer/null | ordinal opcional |
| `fecha_inicio`, `fecha_fin` | date | `YYYY-MM-DD` |
| `precision_fecha_inicio` | enum | `dia`, `mes`, `semestre`, `anio` |
| `estado` | enum | `planificada`, `en_curso`, `finalizada`, `cancelada` |
| `calendario_academico` | HTML | detalle temporal |
| `modalidad` | string | modalidad de esa cohorte |
| `tabla_precio_id` | integer/null | referencia a TablaPrecio |
| `preinscripcion_desde` | datetime/null | inicio inclusivo |
| `preinscripcion_hasta` | datetime/null | cierre exclusivo |
| `mensaje_preinscripcion_abierta` | HTML | mensaje opcional |
| `mensaje_preinscripcion_cerrada` | HTML | mensaje opcional |

La apertura no se persiste como booleano: se deriva de estado y fechas. Una
cohorte cancelada nunca admite preinscripción.

## Seminario

CPT público: `seminario`, URL `/formacion/seminarios/{slug}/`.

Tiene los mismos campos académicos estables de OfertaAcademica y además:

| Campo | Tipo | Regla |
|---|---|---|
| `programa_academico_id` | integer | ProgramaAcademico obligatorio |
| `acredita_maestria` | boolean | dato estable |
| `acredita_doctorado` | boolean | dato estable |
| `docentes_base` | integer[] | referencias a Docente |
| `componentes` | object[] | `{seminario_id, orden}` |

`componentes` describe que un seminario se compone académicamente de otros
seminarios. No referencia ediciones y no puede contenerse a sí mismo.

## EdicionSeminario

CPT interno: `edicion-seminario`. No tiene URL pública propia.

| Campo | Tipo | Regla |
|---|---|---|
| `seminario_id` | integer | padre obligatorio |
| `nombre` | string | nombre visible de la edición |
| `anio` | integer | 2000–2200 |
| `fecha_inicio`, `fecha_fin` | date | `YYYY-MM-DD` |
| `estado` | enum | igual que Cohorte |
| `modalidad` | string | modalidad de esta edición |
| `encuentros_sincronicos` | object[] | `{fecha, hora_inicio, hora_fin, zona_horaria}` |
| `docentes` | integer[] | docentes efectivos de la edición |
| `tabla_precio_id` | integer/null | referencia a TablaPrecio |
| `preinscripcion_desde`, `preinscripcion_hasta` | datetime/null | ventana de apertura |
| `mensaje_preinscripcion_abierta` | HTML | mensaje opcional |
| `mensaje_preinscripcion_cerrada` | HTML | mensaje opcional |
| `mostrar_en_formulario` | boolean | inclusión en catálogo externo |
| `ediciones_componentes` | object[] | `{edicion_id, orden}` |
| `es_asincronica` | boolean derivado | verdadero si no hay encuentros sincrónicos |

`ediciones_componentes` enlaza las ediciones concretas que se cursan juntas.
No reemplaza la composición académica estable del Seminario.

## TablaPrecio

CPT interno: `tabla-precio`. Es compartida, pero sólo las entidades temporales
la referencian.

| Campo | Tipo |
|---|---|
| `nombre` | string |
| `tabla_precios_tipo` | string |
| `precios_filas` | `{concepto, uyu, usd, destacada}[]` |
| `precios_nota` | HTML |
| `mostrar_precios_dolares` | boolean |

Una tabla utilizada por una Cohorte o EdicionSeminario no puede eliminarse.
OfertaAcademica y Seminario no guardan precios.

## Preinscripción externa

El plugin no renderiza ni procesa formularios de preinscripción. El único
destino es `https://preinscripciones.flacso.edu.uy`:

```text
/ofertas/{oferta_id}/cohortes/{cohorte_id}/
/seminarios/{seminario_id}/ediciones/{edicion_id}/
```

No existe selector de flujo, formulario interno ni fallback. El catálogo
público se obtiene en `GET /wp-json/flacso/v1/preinscripciones/catalogo`.

## Reglas de integridad

1. Una OfertaAcademica requiere ProgramaAcademico y exactamente un tipo válido.
2. Un Seminario requiere ProgramaAcademico.
3. Una Cohorte requiere OfertaAcademica; no puede apuntar a Seminario.
4. Una EdicionSeminario requiere Seminario; no puede apuntar a OfertaAcademica.
5. Fechas de fin no pueden ser anteriores a fechas de inicio.
6. El cierre de preinscripción no puede ser anterior a su apertura.
7. Relaciones no admiten duplicados ni autorreferencias.
8. Los precios existen sólo en TablaPrecio y se acceden desde entidades temporales.
9. Los IDs de docentes deben corresponder al CPT `docente`.
10. El estado académico es independiente del estado de publicación WordPress.

## DTOs públicos

`FLACSO_Academic_Catalog::get_offer($id)` devuelve la oferta, todas sus cohortes
y `cohorte_vigente`. `FLACSO_Academic_Catalog::get_seminar($id)` devuelve el
seminario, todas sus ediciones y `edicion_vigente`. Se considera vigente primero
una entidad `en_curso` y luego una `planificada`.

## Migración fuera del plugin

La estrategia final es:

1. exportar los datos antiguos sin alterarlos;
2. transformarlos y validarlos localmente;
3. construir una tabla de correspondencias de IDs;
4. cargar por la API final siguiendo el orden de [API.md](../API.md);
5. verificar conteos, padres, relaciones, fechas y precios;
6. recién entonces habilitar el frontend final.

El plugin no conoce campos antiguos, no ejecuta migraciones en `init` o
activación y no ofrece endpoints de compatibilidad. El archivo transformado debe
usar únicamente los nombres y estructuras documentados aquí.
