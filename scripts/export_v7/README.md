# Export FLACSO para modelo académico v7

Fuente: `flacso-local.sql.gz` a través del export conservador previo.

## Conteos
- ProgramaAcademico derivados de la antigua agrupación `area_tematica`: 7
- OfertaAcademica: 16
- Seminario canónico: 49
- Cohorte candidata: 16
- EdicionSeminario legacy: 54
- Relaciones OfertaAcademica → Seminario canonicalizadas: 49
- Relaciones estables Seminario → componentes: 2

## Principio
Los `source_key` y `legacy_id` existen exclusivamente en `staging_local/` y `auditoria/`. Nunca deben enviarse a WordPress. Los JSON bajo `data` usan sólo campos del modelo final.

## Orden de carga
1. Crear `ProgramaAcademico` con `payload_base/programas_academicos.json` y completar `staging_local/mapa_programas.csv`.
2. Ejecutar/usar el renderer para producir OfertaAcademica y Seminario con sus nuevos `programa_academico_id`.
3. Guardar los nuevos IDs en `mapa_ofertas.csv` y `mapa_seminarios.csv`.
4. Resolver `auditoria/pendientes_validacion.csv`, especialmente carácter de relaciones, precios, URLs/ventanas de preinscripción y campos temporales incompletos.
5. Generar Cohorte y EdicionSeminario con IDs padres nuevos.
6. Reescribir relaciones con los IDs nuevos.
7. Verificar conteos e integridad.

## Decisiones conservadoras
- `area_tematica` sólo se usa para proponer 7 ProgramaAcademico y luego desaparece.
- Los 54 registros legacy de seminario se canonicalizan en 49 Seminario estables y 54 EdicionSeminario.
- No se inventa `caracter` obligatorio/opcional.
- Los booleanos legacy de inscripción no se convierten en ventanas de preinscripción.
- Los precios embebidos de seminarios no se convierten silenciosamente en TablaPrecio; quedan auditados.
- El estado académico se deriva únicamente de fechas respecto del 2026-08-31 y queda auditado cuando no puede determinarse.
- Los encuentros sincrónicos se conservaron y su zona horaria se normaliza a `America/Montevideo`.
