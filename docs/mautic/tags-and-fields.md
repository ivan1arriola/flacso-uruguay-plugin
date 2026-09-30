# Tags y campos legacy

Los campos legacy `last_program_*`, `last_record_*`, `intereses`, `acquisition_source` y los campos de origen Meta se preservan. No se renombran ni se escriben desde el nuevo flujo.

Los tags legacy, incluidos `interes:*`, se conservan. El flujo nuevo agrega sin reemplazar:

```text
interes-{codigo}
{codigo}-c{numero}
origen-web-consultas
```

El estado de cohorte se representa exclusivamente con `flacso_cohorte_estado`.
