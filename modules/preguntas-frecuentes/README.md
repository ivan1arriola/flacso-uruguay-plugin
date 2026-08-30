# Preguntas frecuentes

El módulo registra el CPT privado `pregunta-frecuente`. El título es la
pregunta, el contenido es la respuesta y el campo **Orden** de los atributos de
página define su posición pública.

La primera vez que se abre el administrador, si el CPT está vacío, se importan
las 14 preguntas del shortcode histórico. La opción
`flacso_faq_seed_version` evita repetir la importación.

La salida pública se obtiene directamente con
`FLACSO_Preguntas_Frecuentes::render()`. El módulo entrega HTML semántico sin
CSS ni JavaScript incrustados; la plantilla y los recursos visuales pertenecen
al tema `kadence-child-flacso`. No se registra ningún shortcode.

Al desplegar plugin y tema se debe desactivar y eliminar el snippet histórico.
La página `/preguntas-frecuentes/` no necesita contenido: su plantilla llama al
renderizador del plugin.
