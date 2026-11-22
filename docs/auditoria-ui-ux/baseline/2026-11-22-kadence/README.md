# Auditoría de Kadence e identidad visual de FLACSO Uruguay

Paquete de trabajo del 22 de noviembre de 2026. La [fuente auditada del tema](https://github.com/ivan1arriola/flacso-theme-kadence/tree/7e6be1c) y el [sitio público](https://flacso.edu.uy/) se observaron por separado. La ejecución se rige por la [guía de auditoría](../../GUIA-SUBAGENTES.md) y el [estado de los paquetes](../../TAREAS-CI.md).

| Documento | Contenido |
|---|---|
| [Plan T3](plan-t3.md) | Decisiones propuestas, archivos por etapa, criterios de aceptación y reversión. |
| [Auditoría](auditoria.md) | Hallazgos con archivos y líneas, matriz de plantillas y capturas de cada paso. |
| [Evidencias originales](evidencias/) | 17 capturas y dos JSON con URLs, estado HTTP, estilos calculados, foco y directivas robots. |
| [Bitácora B-0050](../../bitacora/B-0050-auditoria-kadence-y-plan-t3.md) | Alcance, resultados y pendientes. |

## Cómo corregir el material

- Actualizá **auditoria.md** con la versión revisada y una referencia a la fuente, al JSON o a una captura. Indicá si el resultado viene de lectura de código, observación pública o una prueba autenticada.
- Actualizá **plan-t3.md** para cambiar alcance, orden o aceptación. Cada tarea debe identificar las rutas afectadas, el resultado verificable y la reversión.
- Adjuntá las nuevas evidencias por fecha y entorno. Conservá los archivos originales como línea base y registrá la intervención en la [bitácora](../../BITACORA.md). La edición autenticada de Personalizar y Gutenberg se prueba en staging.

## Cómo reproducir las capturas

Desde `scripts/`, con Node.js y Google Chrome.

```sh
npm install --no-save --no-package-lock playwright@1.49.1
node capturar.mjs ./corridas
node sondear.mjs ./corridas
```

Usá `FLACSO_AUDIT_BASE_URL` para apuntar a un staging autorizado con las mismas rutas. El valor por defecto es el origen público de FLACSO Uruguay. Cada comando crea un subdirectorio nuevo dentro de la carpeta de resultados. Los scripts permiten GET y HEAD del mismo origen y bloquean los endpoints conocidos de administración, analítica y formularios. El sondeo modifica estilos únicamente en el navegador de prueba.

La sesión pública puede quedar registrada en la analítica del servidor. El bloqueo de terceros puede afectar recursos externos. Indicá estas condiciones y la versión de Chrome al comparar resultados.
