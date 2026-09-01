# Casos de validación manual

1. Crear Seminario A (4 créditos) y Seminario B (3 créditos).
2. Crear Seminario Integrado con A y B como componentes.
3. Verificar que `get_post_meta($integrado, 'creditos', true)` devuelve `7` y el campo de créditos aparece sólo lectura en el editor.
4. Crear A-2026 con docentes 1,2 y dos encuentros.
5. Crear B-2026 con docentes 2,3 y un encuentro.
6. Crear una Edición del Integrado y seleccionar A-2026 y B-2026 en `ediciones_componentes`.
7. Verificar docentes derivados `[1,2,3]` y tres encuentros ordenados cronológicamente.
8. Intentar agregar una Edición de un Seminario que no sea componente: debe descartarse al guardar.
9. Intentar seleccionar dos Ediciones de A: sólo debe conservarse la primera relación válida.
10. Modificar docentes/encuentros en A-2026 y verificar que el Integrado cambia sin copiar datos.
11. Modificar los componentes del Seminario Integrado y verificar que relaciones temporales que ya no pertenecen a componentes se eliminan.
