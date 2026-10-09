# Procedimiento de línea base de rendimiento

Este procedimiento se ejecuta en un entorno de desarrollo o staging con datos anonimizados. No reconstruye catálogos, no envía entregas y no modifica colas ni datos de producción.

## WordPress

1. Activar un registrador de consultas únicamente en el entorno de prueba, por ejemplo Query Monitor o el filtro `query` de WordPress.
2. Medir por separado la carga del panel FLACSO, la vista de asistente y `GET /wp-json/flacso/v1/preinscripciones`.
3. Registrar cantidad de consultas, tiempo total SQL, tiempo de serialización y tamaño de la respuesta.
4. Repetir cada escenario dos veces para distinguir el primer cálculo de la respuesta cacheada.

## PostgreSQL y consultas

Ejecutar `EXPLAIN (ANALYZE, BUFFERS)` sobre copias anonimizadas de las consultas de analítica agrupada, analítica sin agrupar y resumen por período. Guardar el plan junto con el volumen de filas y el tiempo total. No crear índices ni modificar el esquema desde este procedimiento.

## Mautic y cola

Usar un transporte HTTP de prueba que cuente solicitudes sin contactar Mautic. Registrar solicitudes por entrega, entregas procesadas por ejecución, reintentos y duración del lock. El caso de error debe verificar que la entrega quede reintentable y que el lock se libere.

## Comparación

Comparar antes y después con el mismo conjunto anonimizado y los mismos filtros. Conservar la forma de las respuestas REST y de analítica, además de los estados de entrega. Investigar cualquier diferencia de contenido antes de atribuirla a una mejora de rendimiento.

Los resultados de producción requieren evidencia separada: SHA exacto, ejecución de CI, artefacto y despliegue, health check público y verificación funcional solicitada.
