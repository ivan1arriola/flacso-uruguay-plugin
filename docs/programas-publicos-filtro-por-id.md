# Páginas públicas de Programas Académicos

Las páginas `/programas/` (archivo) y `/programas/{slug}` (por ejemplo `/programas/educacion/`) las dibuja el **tema hijo** `kadence-child-flacso`. El plugin solo registra el CPT `programa-academico` (`modules/oferta-academica/includes/class-cpt-programa-academico.php`).

| Pieza | Archivo (tema hijo) |
|---|---|
| Archivo de programas | `archive-programa-academico.php` |
| Página de un programa | `single-programa-academico.php` |
| Estilos | `assets/css/programa-academico.css` |
| Carga de estilos | `inc/assets.php` → `flacso_child_enqueue_programa_styles()` |

## Estilos

El CSS ya existía, pero nunca se cargaba, por eso la página se veía sin diseño y con las imágenes a tamaño completo. Ahora se carga solo en el archivo y en el single de `programa-academico`. Para cambiar el aspecto, editá `programa-academico.css`; la versión del archivo cambia sola con su fecha de modificación.

## Filtrar por ofertas o seminarios concretos: `?id=`

Agregá `?id=` con los IDs de post separados por coma. Sirve para oferta académica y para seminario, mezclados.

```text
https://flacso.edu.uy/programas/educacion/?id=102,103
```

Comportamiento:

- Muestra **solo** las ofertas y seminarios cuyo ID está en la lista **y** que pertenecen a ese programa. Un ID de otro programa, de un borrador o inexistente se ignora.
- Oculta el bloque «Presentación del Programa» y agrega al breadcrumb el enlace «Ver todas las propuestas», que lleva a la página completa.
- Si ningún ID es válido para el programa, la página queda sin tarjetas. No vuelve a mostrar todo.
- También acepta `?id[]=102&id[]=103`.
- **No sirve** `?id=102&103`: en una URL el `&` separa parámetros distintos, así que `103` no llega como ID. Usá la coma.

### Cómo obtener el ID

En `wp-admin`, abrí la oferta o el seminario y mirá `post=NNN` en la URL del editor. O por API:

```bash
python scripts/flacso_api.py get /ofertas
python scripts/flacso_api.py get /seminarios
```

## Despliegue

Los cambios viven en el repositorio del tema hijo, no en el del plugin. Se publican con el flujo de ese repositorio (`.github/workflows/deploy-theme.yml`).

## Pendiente conocido

La página con `?id=` no tiene `noindex` ni `canonical` propio. Si se comparte mucho y hace falta que Google no la indexe como duplicado, hay que agregarlo.
