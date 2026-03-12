# FLACSO Uruguay - Plataforma Integrada

Plugin unificado para FLACSO Uruguay con modulos de:
- oferta academica
- seminarios
- preinscripciones
- formularios de consulta
- docentes
- eventos
- shortcodes
- bloques Gutenberg

## Version

`1.1.6`

## Requisitos

- WordPress `6.0+`
- PHP `7.4+`

## Cambios recientes (1.1.6)

- Hotfix visual del carrusel 3D de oferta educativa para evitar superposicion.
- Ajuste de posicion/tamano de tarjetas con imagenes cuadradas.
- Recorte de titulo y descripcion para mantener altura estable.

## Cambios previos (1.0.4)

- Integracion de eventos Meta Pixel estandarizada en flujos clave.
- `Lead` en paginas de gracias/confirmacion.
- `SubmitApplication` en preinscripciones (posgrado y seminario).
- `ViewContent` en:
  - formulario de solicitud de informacion de oferta academica
  - pagina individual de seminario
  - shortcode hero de cartas
- Nuevo bloque Gutenberg independiente:
  - `flacso-uruguay/preinscripcion-button`
  - renderiza solo el boton "Preinscripcion 2026"
- Se mantiene el bloque anterior de consultas sin cambios funcionales.
- Ajustes en listado de seminarios:
  - grid responsivo fijo `3/2/1` (desktop/tablet/mobile)
  - soporte de filtro por programa via relacion:
    - `oferta-academica` -> `_oferta_seminarios_ids` -> `area_tematica`
  - fallback legacy para datos antiguos.
- Correccion masiva de codificacion:
  - normalizacion a UTF-8 sin BOM
  - limpieza de mojibake en archivos afectados

## Estructura principal

```text
flacso-uruguay/
|- flacso-uruguay.php
|- includes/
|  |- core/
|  |- admin/
|  |- blocks/
|  |- cpt/
|- modules/
|  |- main-page/
|  |- shortcodes/
|  |- oferta-academica/
|  |- seminarios/
|  |- formularios/
|  |- preinscripcion/
|  |- posgrados/
|  |- docentes/
|  |- eventos/
|- assets/
|- CHANGELOG.md
```

## Instalacion

1. Copiar la carpeta `flacso-uruguay` a `wp-content/plugins/`.
2. Activar el plugin en WordPress.
3. Guardar enlaces permanentes en:
   `Ajustes -> Enlaces permanentes -> Guardar`.

## Actualizacion automatica (sin SSH)

Este repositorio incluye un workflow que publica un release de GitHub en cada `push` a `main`:
- `.github/workflows/release-auto-update.yml`

Para que el sitio se actualice solo:
1. En WordPress, activar **Auto-actualizaciones** para este plugin.
2. Verificar que el plugin tenga acceso al repo configurado en `FLACSO_URUGUAY_UPDATE_REPO`.
3. Hacer `push` a `main`.

Opcional:
- Si alguna vez tienen credenciales FTP/SFTP, tambien existe deploy directo:
  `.github/workflows/deploy-plugin.yml` (solo corre si estan definidos los secrets de deploy).

## Notas de tracking (Meta Pixel)

Eventos usados por el plugin:
- `ViewContent`
- `Lead`
- `SubmitApplication`

No se usan eventos custom (`trackCustom`) en los flujos actuales.

## Soporte

Para diagnostico y mantenimiento, revisar:
- `CHANGELOG.md`
- documentacion de cada modulo en `modules/*`
