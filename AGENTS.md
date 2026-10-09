# Reglas específicas del plugin FLACSO Uruguay

## Publicación

- Todo cambio terminado debe quedar en `main`. Antes de publicar, comprobar `git status`, comparar con `origin/main`, ejecutar las pruebas y enviar el commit con `git push origin HEAD:main`.
- Después de publicar, verificar por separado el commit remoto, CI, deploy, smoke test y cualquier comprobación visual solicitada.
- Las ramas temporales y worktrees de una tarea se eliminan sólo después de comprobar que sus cambios ya están en `main` y que no tienen trabajo sin guardar.

## Plataforma de Consultas

- Cada cambio en `get_paginated_inquiries()` debe probar los modos `raw` y `grouped`, con rango de fechas y sin rango de fechas. La vista agrupada debe probar también la consulta de hijos.
- No interpolar `$where_sql` dentro de otra condición si ya contiene el prefijo `WHERE`; construir y reutilizar por separado el cuerpo de condiciones para evitar SQL como `AND (WHERE ...)`.
- Las excepciones de consultas analíticas no deben convertirse silenciosamente en `0 de 0`: registrar el error técnico sin datos personales y mostrar al administrador un aviso operativo genérico.
- Probar la vista desplegada con la base `offer_inquiries`, un rango amplio y el filtro predeterminado agrupado antes de declarar que la bandeja carga datos.

