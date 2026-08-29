# Arquitectura y Fuentes de Verdad: `flacso-uruguay-plugin`

## 1. Responsabilidad Principal
`flacso-uruguay-plugin` actúa como la frontera explícita entre WordPress, el Editor administrativo, la web pública y los servicios de tracking.

## 2. Fuentes de Verdad Asignadas
- **Contenido Público y Publicación de WordPress**: una única `OfertaAcademica` (incluido tipo seminario), Novedades y Configuración de Portada (`main-page`).
- **Instancias de Oferta**: identidad WordPress, relación Oferta → Cohortes/Ediciones, estado y `flujo_preinscripcion`.
- **Contratos REST API**: Exposición de DTOs diferenciados para consumo público (`PublicDTO`) y administrativo (`AdminDTO`).
- **Tracking Centralizado**: API JavaScript única (`flacsoMetaTrackCustom`) encargada de sincronizar identificadores (`event_id`) entre Pixel, CAPI y GA4.

## 3. Lo que NO debe hacer este repositorio
- **NO almacenar postulaciones**. WordPress publica catálogo, apertura y destino transitorio; las postulaciones viven exclusivamente fuera del plugin.
- **NO definir estilos de diseño detallados o layout visual propio del tema**.

## 4. Comunicación e Integraciones
- **Hacia el Editor (`flacso-uruguay-editor`)**: Provee endpoints REST protegidos con contratos `AdminDTO`.
- **Hacia el Tema (`kadence-child-flacso`)**: Provee datos, estructuras semánticas, shortcodes y API de tracking JS.
- **Hacia Preinscripciones**: publica únicamente el catálogo mínimo v1 de instancias abiertas cuyo flujo es `gestor_preinscripciones`.

Ver [transición de preinscripciones](preinscription-flow-transition.md) para contratos, migración y operación.
