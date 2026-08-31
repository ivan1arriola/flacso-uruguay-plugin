# Arquitectura y Fuentes de Verdad: `flacso-uruguay-plugin`

## 1. Responsabilidad Principal
`flacso-uruguay-plugin` actúa como la frontera explícita entre WordPress, el Editor administrativo, la web pública y los servicios de tracking.

## 2. Fuentes de Verdad Asignadas
- **Contenido Público y Publicación de WordPress**: `OfertaAcademica` y `Seminario` como raíces separadas, Novedades y Configuración de Portada (`main-page`).
- **Temporalidad académica**: `OfertaAcademica → Cohorte` y `Seminario → EdicionSeminario`.
- **Contratos REST API**: exposición del modelo final para la web pública y el Editor.
- **Tracking Centralizado**: API JavaScript única (`flacsoMetaTrackCustom`) encargada de sincronizar identificadores (`event_id`) entre Pixel, CAPI y GA4.

## 3. Lo que NO debe hacer este repositorio
- **NO almacenar postulaciones**. WordPress publica catálogo, apertura y destino transitorio; las postulaciones viven exclusivamente fuera del plugin.
- **NO definir estilos de diseño detallados o layout visual propio del tema**.

## 4. Comunicación e Integraciones
- **Hacia el Editor (`flacso-uruguay-editor`)**: Provee endpoints REST protegidos con contratos `AdminDTO`.
- **Hacia el Tema (`kadence-child-flacso`)**: Provee datos, estructuras semánticas, shortcodes y API de tracking JS.
- **Hacia Preinscripciones**: publica cohortes y ediciones abiertas; sus URLs apuntan exclusivamente a `https://preinscripciones.flacso.edu.uy`.

Ver [modelo académico final](modelo-academico-final.md) para campos, relaciones e invariantes.
