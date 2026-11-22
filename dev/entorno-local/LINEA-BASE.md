# Línea base de uniformidad (2026-11-22; tema en `main` bd74a03, que incluye #86 a #90 pero no #91 ni #92)

Medida con `regresion.mjs --update` (estilos calculados en Chrome a 1280 px, datos ficticios, paleta por defecto de Kadence) y `uniformidad.py`.
Es la referencia contra la que cada migración del sistema de diseño debe mejorar. Reproducir: ver `README.md`.

## CSS propio (`python uniformidad.py ../../../flacso-theme-kadence`)

```text
CSS propio: 25 archivos, 3782 reglas, !important: 1119
Colores: 410 hex distintos, 1102 usos fijos vs 106 var(--global-palette*) (8.8 % de Kadence)
Radios: 69 valores distintos en 493 usos
Sombras: 197 valores distintos en 228 usos
Anchos de contenedor (>=900 px o variable): 10 distintos -> 1120px, 1140px, 1160px, 1180px, 1200px, 1240px, 1280px, 900px, 920px, 980px
Breakpoints distintos: 42
```

## Estilos calculados por plantilla (1280 px)

| Plantilla | HTTP | `<main>` | H1 tamaño | H1 familia | H1 peso | Cuerpo familia | Botón radio | Botón alto | Ancho de contenido |
|---|---|---|---|---|---|---|---|---|---|
| 404 | 404 | 1 | 70.4px | system-ui | 950 | system-ui | 999px | 44 | 1280 |
| portada | 200 | 1 | 64px | -apple-system | 500 | system-ui | 12px | 61 | 1280 |
| formacion | 200 | 1 | 61.44px | system-ui | 800 | system-ui | - | - | 1232 |
| taxonomia | 200 | 1 | 64px | system-ui | 800 | system-ui | - | - | 1180 |
| ficha-oferta | 200 | 1 | 44.8px | system-ui | 850 | system-ui | 999px | 52 | 1280 |
| carta | 200 | 1 | 70.4px | -apple-system | 800 | system-ui | 999px | 50 | 1280 |
| seminarios | 200 | 1 | 76.8px | system-ui | 800 | system-ui | 999px | 46 | 1280 |
| seminario | 200 | 1 | 51.2px | sans-serif | 800 | system-ui | 13px | 52 | 1100 |
| equipo | 200 | 1 | 56px | Inter | 900 | system-ui | 12px | 46 | 1136 |
| docente | 200 | 1 | 48px | Inter | 800 | system-ui | - | - | 640 |
| programas | 500 | 1 | 56px | Inter | 900 | -apple-system | - | - | 586 |
| programa | 200 | 1 | 56px | Inter | 900 | system-ui | - | - | 0 |
| convenios | 200 | 1 | 76.8px | system-ui | 850 | system-ui | - | - | 1280 |
| contacto | 200 | 1 | 75.2px | system-ui | 850 | system-ui | 999px | 52 | 1120 |
| faq | 200 | 1 | 64px | system-ui | 500 | system-ui | - | - | 1120 |
| novedades | 200 | 1 | 53.76px | system-ui | 800 | system-ui | - | - | 1080 |
| noticia | 200 | 1 | 53.76px | system-ui | 800 | system-ui | 0px | 35 | 1080 |
| evento | 200 | 1 | 53.76px | system-ui | 800 | system-ui | - | - | 1080 |
| gracias | 200 | 1 | 48px | system-ui | 500 | system-ui | 8px | 68 | 880 |
| busqueda | 200 | 0 | 40px | system-ui | 500 | system-ui | - | - | 1280 |

Notas: `programas` da HTTP 500 mientras exista un programa sin coordinación (defecto del tema, corrección puntual del plan). `busqueda` no tiene ningún `<main>`. La ficha individual de Convenio no está en las rutas (404 con datos mínimos). El «ancho de contenido» es el del hijo más ancho de `<main>` sin relleno: orienta, no es un máximo declarado.
