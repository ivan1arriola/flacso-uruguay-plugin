# Migración del modelo académico v1

## Alcance

Release A unifica el dominio sin retirar todavía los puntos de compatibilidad:

```text
OfertaAcademica (doctorado | maestria | especializacion | diplomado | diploma | seminario)
        1
        |
        N
InstanciaOferta
```

`InstanciaOferta` representa cohortes y ediciones. No existe `tipo_instancia`:
la etiqueta predeterminada es **Edición** para una oferta de tipo `seminario` y
**Cohorte** para las demás. `nombre_visible` (el `post_title`) prevalece cuando
está definido.

El plugin no crea ni almacena postulaciones, documentos, postulantes, estados de
admisión o notas. `flujo_preinscripcion` sólo conserva durante la transición el
destino `legacy_editor` o `gestor_preinscripciones`.

## Estado y apertura

El estado académico sólo admite `planificada`, `en_curso`, `finalizada` y
`cancelada`. La apertura se determina exclusivamente mediante
`FLACSO_Instancia_Oferta::acepta_preinscripciones()` y
`get_preinscripcion_cierre_efectivo()`.

| Dato | OfertaAcademica | InstanciaOferta |
|---|---:|---:|
| Identidad, título, contenido, imagen | Sí | No |
| Tipo académico | Sí | No |
| Presentación, objetivos, unidades, aprobación, carga y créditos | Sí | No |
| Fecha de inicio/fin y precisión | No | Sí |
| Encuentros, docentes de edición, modalidad y precios | No | Sí |
| Estado académico | No | Sí |
| Apertura/cierre de preinscripción | No | Sí |
| Flujo transitorio | No | Sí |

- Seminario: cierre efectivo = apertura + 7 días.
- Otros tipos: cierre efectivo = `preinscripcion_cierre_manual`; `NULL` significa
  que continúa abierto.
- Los booleanos legacy se copian como evidencia de compatibilidad, pero nunca se
  inventa una fecha a partir de `post_date` o `post_modified`.

## Deduplicación explícita

Sólo se fusionan los cinco pares auditados. Otros títulos semejantes se informan
como `REPORT_ONLY`.

| Canónica | Absorbida como edición |
|---:|---:|
| 24299 | 27242 |
| 23902 | 27254 |
| 25623 | 27256 |
| 24432 | 27245 |
| 23904 | 27258 |

La oferta conserva el ID canónico. La edición absorbida reutiliza su ID como
`instancia-oferta`; la edición que estaba en el post canónico obtiene un nuevo
ID. La fuente académica elegida es la edición más reciente del grupo y el reporte
enumera cada conflicto antes de escribir. Los conflictos conocidos son
`_seminario_presentacion_seminario` en 24299/27242 y 24432/27245.

El registro 27240 se informa y se conserva sin migrar. La referencia inexistente
23911 desde 24162 se omite y se informa sin abortar. El seminario integrado 27212
se convierte en OfertaAcademica y mantiene relaciones `compuesto_por` hacia
23913 y 23918; como no tiene temporalidad propia, no genera InstanciaOferta.

## Relaciones

`FLACSO_Relacion_Oferta_Academica` es la fuente canónica y admite `integra`,
`compuesto_por` y `precede`, con destino y orden. `_oferta_seminarios_ids` se
mantiene únicamente como proyección legacy durante Release A.

## Seguridad, backup e idempotencia

No hay activation hook ni hook `init` que ejecute esta migración. Antes de cada
cambio se guarda `_flacso_academic_model_migration_backup_v1` con post, estado,
slug, contenido, extracto, taxonomías, metadatos e imagen destacada. Cada acción
se registra en `_flacso_academic_model_migration_record_v1` y en el mapa central
versionado. La aplicación usa APIs WordPress y una transacción cuando la base lo
permite.

No existe rollback automático destructivo. `--rollback-report` genera el plan y
los snapshots necesarios para una restauración revisada.

## Operación

Ejecutar siempre desde el directorio de WordPress:

```bash
wp flacso migrate academic-model --dry-run
wp flacso migrate academic-model
wp flacso migrate academic-model --verify
wp flacso migrate academic-model --rollback-report
```

El comando real pide confirmación. No debe ejecutarse en producción como parte
del deploy.

### Conteos esperados en producción

| Entidad | Máximo histórico | Aplicando regla temporal |
|---|---:|---:|
| OfertaAcademica | 64 | 64 |
| InstanciaOferta desde cohortes | 16 | 16 |
| InstanciaOferta desde seminarios | 53 | 52 |
| InstanciaOferta total | 69 | 68 |

La diferencia es 27212, oferta integrada sin instancia temporal migrable. El
dry-run calcula los valores a partir de la base y debe explicar cualquier otra
diferencia.

## Checklist pre-deploy

- Ejecutar todos los tests y controles de sintaxis/encoding.
- Obtener backup externo completo de base de datos y `wp-content/uploads`.
- Ejecutar `--dry-run` contra una copia reciente de producción.
- Confirmar 48 seminarios canónicos, cinco absorbidos y el inválido 27240.
- Confirmar conflictos, relaciones y referencia rota 23911.
- Guardar la salida JSON del dry-run.
- No incluir el comando real en CI/CD, cron ni activation hook.

## Checklist post-deploy (antes de migrar)

- Confirmar que las URLs y formularios legacy siguen funcionando.
- Confirmar que el catálogo Django responde y que el Editor puede leer instancias.
- Ejecutar nuevamente `--dry-run`; el deploy por sí solo no debe cambiar datos.

## Checklist posterior a la migración autorizada

- Ejecutar `--verify` y comparar 64 ofertas y 68 instancias esperadas.
- Revisar cinco acciones `ABSORBIDO_COMO_EDICION`.
- Revisar que 27240 siga siendo `seminario` y no exista 23911 inventado.
- Abrir URLs de seminarios migrados y verificar imagen/contenido.
- Verificar catálogo con un seminario y una oferta no seminario.
- Conservar el backup externo, dry-run, resultado y rollback report.

## Release B

Sólo después de verificar producción se podrá dejar de registrar `seminario` y
`cohorte`, retirar escrituras/endpoints legacy y limpiar metadatos históricos.
Los redirects públicos necesarios se conservarán.
