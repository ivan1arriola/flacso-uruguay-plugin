# Arquitectura y Fuentes de Verdad: `flacso-uruguay-plugin`

## 1. Responsabilidad Principal
`flacso-uruguay-plugin` actúa como la frontera explícita entre WordPress, el Editor administrativo, la web pública y los servicios de tracking.

## 2. Fuentes de Verdad Asignadas
- **Contenido Público y Publicación de WordPress**: Ofertas Académicas base, Seminarios base, Novedades y Configuración de Portada (`main-page`).
- **Contratos REST API**: Exposición de DTOs diferenciados para consumo público (`PublicDTO`) y administrativo (`AdminDTO`).
- **Tracking Centralizado**: API JavaScript única (`flacsoMetaTrackCustom`) encargada de sincronizar identificadores (`event_id`) entre Pixel, CAPI y GA4.

## 3. Lo que NO debe hacer este repositorio
- **NO tomar decisiones sobre reglas de negocio complejas de inscripciones** (como el control de unicidad de cohortes o ediciones abiertas que pertenecen al Editor).
- **NO definir estilos de diseño detallados o layout visual propio del tema**.

## 4. Comunicación e Integraciones
- **Hacia el Editor (`flacso-uruguay-editor`)**: Provee endpoints REST protegidos con contratos `AdminDTO`.
- **Hacia el Tema (`kadence-child-flacso`)**: Provee datos, estructuras semánticas, shortcodes y API de tracking JS.

