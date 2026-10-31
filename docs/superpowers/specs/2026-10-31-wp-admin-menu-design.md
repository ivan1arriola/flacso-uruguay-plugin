# Diseño aprobado: organización del menú de wp-admin de FLACSO

## Intención

Facilitar que el equipo encuentre las tareas institucionales y académicas de uso habitual, y separar la administración técnica del trabajo de contenidos e inscripciones. La propuesta parte del inventario del menú visible en la cuenta actual de WordPress: 29 secciones principales y 117 rutas internas.

## Organización

Se conserva el menú raíz **FLACSO** y el panel actual como página de inicio. Dentro del menú se presentan seis grupos:

1. **Inicio FLACSO**: panel FLACSO, escritorio de WordPress y actualizaciones.
2. **Formación**: programas, ofertas, seminarios, tablas de precios, convenios y Personas / Equipo.
3. **Sitio y contenidos**: páginas, entradas, eventos, preguntas frecuentes, medios, autoridades y herramientas para organizar contenidos.
4. **Inscripciones y consultas**: inscripciones, formulario general, enlaces de preinscripción, bandeja de consultas, correos y reportes.
5. **Diseño del sitio**: apariencia, personalización, editor, menús, widgets, fuentes y herramientas de diseño de Kadence.
6. **Sistema y herramientas**: usuarios, ajustes, plugins, herramientas de WordPress, integraciones y herramientas técnicas de plugins.

## Criterios

- Reutilizar el panel y la raíz FLACSO existentes; no crear un segundo panel.
- Mantener las URLs de destino y el comportamiento de cada pantalla.
- Mantener las capacidades y permisos ya asignados por WordPress o por cada plugin.
- Mantener la barra superior y sus accesos actuales.
- No cambiar contenido, datos, opciones, roles ni configuración.
- Los enlaces externos de ayuda o promoción no son pantallas de wp-admin; su tratamiento se mantiene fuera del alcance de la reagrupación de rutas internas.
- El primer alcance cubre las pantallas disponibles para la cuenta observada. Los elementos no visibles por permisos deben seguir respetando los controles de capacidad originales.

## Enfoques considerados

- Reordenar el menú de wp-admin por grupos: **elegido**, porque cubre el objetivo en la navegación habitual y conserva las pantallas existentes.
- Agregar solo una portada con accesos: menor riesgo, pero deja dispersas las secciones del menú.
- Crear menús diferentes por perfil: puede reducir ruido, pero requiere definir perfiles, permisos y un mantenimiento adicional.

## Límite

Este documento aprueba la organización y los criterios de navegación. La selección técnica final para mover o reparentar entradas de plugins debe preservar los callbacks y permisos existentes, y se verificará en el plan y al ejecutar la implementación.
