# Página pública de Oferta Académica: reconocimientos, coordinación y columnas

Todo vive en el **tema hijo** `kadence-child-flacso` (plantilla `oferta-academica/page.php`, estilos `oferta-academica/style.css`, barra de admin en `inc/assets.php`). Los datos salen de los metas de la Oferta; el plugin no cambia.

## Botón «Editar Cohorte» en la barra de administración

En una oferta (`/oferta-academica/{slug}`), la barra negra de WordPress muestra **Editar Cohorte** a la derecha de «Editar Oferta Académica». Abre la cohorte vigente, la misma que alimenta la página (`flacso_theme_get_offer_context()['instance']['id']`).
No aparece si la oferta no tiene cohorte vigente o si el usuario no puede editarla. Función: `flacso_child_admin_bar_edit_cohort()`.

## Reconocimientos bajo el hero

Cuatro sellos en una franja visible bajo el hero, cada uno solo si está marcado en la Oferta:

| Texto | Meta de la oferta |
|---|---|
| Programa con Reconocimiento Oficial del Ministerio de Educación y Cultura (MEC) | `reconocido_mec` |
| Apostillado de La Haya | `reconocimiento_internacional` |
| Título con validez y reconocimiento en la red del Sistema Internacional FLACSO | `reconocimiento_red_flacso` |
| Convenio IIN/OEA | `convenio_iin_oea` |

Ya no aparecen dentro del acordeón «Título y certificación» ni como bloque al pie del contenido.

## Lateral del formulario

Se eliminó el bloque «Información útil» (enlaces a «Información de la cohorte» y «Requisitos de egreso»). Quedan el formulario y el contacto académico.

## Coordinación académica

Rediseño propio, distinto de los grupos docentes (que siguen siendo acordeones):

- Tarjeta grande con foto de 96 px, rol, nombre y título académico.
- El **CV va siempre desplegado**, sin acordeón.
- Las tarjetas de una misma fila se estiran a igual alto, así un CV largo no desparejea a su vecino. Una columna en móvil.
- Markup: `$render_coordinador` en `page.php`; clases `.flacso-coordinator*` en `style.css`.

## Columnas parejas

En escritorio (≥ 1021 px) el contenido y el lateral usan una grilla de 3 columnas con el mismo `gap` (24 px) que el equipo académico: el contenido ocupa 2 columnas y el formulario 1, así los bordes coinciden con las tarjetas de docentes de más abajo. Por debajo de 1021 px se apila como antes.
Los estilos nuevos están al final de `style.css` para ganar sobre las reglas anteriores del mismo selector.

## Verificación

`bash bin/check-theme.sh` en el tema (sintaxis PHP y tests de contrato). No se revisó el resultado visual en el sitio: conviene mirar una oferta con y sin cohorte, con uno y con tres coordinadores, y en móvil.
