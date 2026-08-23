# FLACSO Uruguay Plugin

Plugin de WordPress que reúne las funcionalidades propias del sitio institucional de FLACSO Uruguay.

## Principios de arquitectura

El plugin se organiza por **dominios de FLACSO**, no por mecanismos técnicos ni por nombres históricos.

- **Oferta Académica** es el dominio de Maestrías, Especializaciones, Diplomas y Diplomados.
- **Seminario** es una unidad académica propia: puede ofrecerse individualmente y también integrar otras ofertas académicas.
- **Main Page** compone la portada, pero no es dueño de los datos de Eventos, Seminarios, Oferta Académica o Mailing.
- **Docentes**, **Eventos**, **Convenios**, **Formularios**, **Preinscripción**, etc. conservan sus responsabilidades específicas.
- `posgrados` y el módulo genérico `shortcodes` son compatibilidad histórica; no deben recibir funcionalidad nueva.

### Regla para código nuevo

Una funcionalidad se ubica en el módulo del dominio al que pertenece. El mecanismo de exposición no define el módulo:

```text
Correcto
Oferta Académica
├── REST
├── bloques
├── shortcodes
├── administración
└── integración con portada

Incorrecto
shortcodes/
└── shortcode-de-oferta-academica.php
```

## Carga de módulos

`includes/core/class-flacso-module-registry.php` es la fuente de verdad para módulos y dependencias.

Cada definición puede declarar:

- `depends`: dependencias obligatorias;
- `optional_depends`: integraciones que se intentan cargar, pero no bloquean el consumidor;
- `required`: si el fallo del módulo debe considerarse crítico;
- `legacy`: si existe únicamente por compatibilidad;
- `path`: nombre físico distinto de la clave canónica durante una migración.

El loader resuelve dependencias, detecta ciclos y muestra fallos en administración. Los archivos imprescindibles deben cargarse con `flacso_require()`; `flacso_optional_require()` se reserva para características opcionales.

## Portada

`main-page` administra:

- contenido editorial propio del hero y bloques institucionales;
- orden;
- visibilidad;
- configuración global;
- contrato REST para el editor;
- composición final.

Los módulos de dominio registran sus secciones mediante:

```php
add_filter('flacso_homepage_sections', function (array $sections): array {
    $sections['mi_seccion'] = [
        'function' => 'mi_renderer',
        'owner' => 'mi-modulo',
    ];
    return $sections;
});
```

Los adaptadores pueden agregar archivos de renderer mediante `flacso_main_page_component_files` mientras se completa la migración física de componentes históricos.

### Claves canónicas

La portada utiliza:

```text
hero
eventos
novedades
novedades_busqueda
seminarios
quienes
instagram
oferta_academica
mailing
contacto
congreso
```

Compatibilidad:

```text
posgrados -> oferta_academica
festejos  -> retirado
```

Los valores históricos de `festejos` y `posgrados` se archivan antes de migrar la configuración.

## Estructura

```text
flacso-uruguay-plugin/
├── flacso-uruguay.php             # bootstrap mínimo
├── includes/
│   └── core/
│       ├── loader.php
│       ├── class-flacso-module-registry.php
│       └── requires.php
├── modules/
│   ├── site/
│   ├── docentes/
│   ├── seminarios/
│   ├── oferta-academica/
│   ├── eventos/
│   ├── autoridades/
│   ├── convenios/
│   ├── charlas-abiertas/
│   ├── formularios/
│   ├── formularios-webhook/
│   ├── preinscripcion/
│   ├── mailing/
│   ├── main-page/
│   ├── posgrados/                 # legacy
│   └── shortcodes/                # legacy
├── tests/
├── API.md
└── CHANGELOG.md
```

`modules/site/` es el nombre canónico de las funciones transversales del sitio. Durante la transición, algunas clases siguen físicamente en `modules/core/` detrás del wrapper de `site`.

## Requisitos

- WordPress 6.0 o posterior;
- PHP 7.4 o posterior;
- WP-CLI para validaciones posteriores al despliegue.

El archivo principal es `flacso-uruguay.php` y el slug de instalación es `flacso-uruguay-plugin`.

## Validaciones

Antes de publicar cambios:

```bash
python3 .github/scripts/check_encoding.py

while IFS= read -r -d '' file; do
  php -l "$file"
done < <(find . -type f -name '*.php' \
  -not -path './vendor/*' \
  -not -path './node_modules/*' \
  -print0)

php tests/oferta-inscripciones-year-test.php
```

## Versionado automático

La versión desplegada se deriva del commit de `main` con el formato `<versión base>.<cantidad de commits>`. El workflow actualiza en la copia de distribución la cabecera `Version:` y `FLACSO_URUGUAY_VERSION`; no crea commits automáticos de versión.

## Despliegue

Cada push a `main` activa `.github/workflows/deploy-plugin.yml`:

1. checkout del SHA;
2. cálculo de versión;
3. encoding, archivos requeridos y sintaxis PHP;
4. ZIP artifact;
5. staging por SSH;
6. `rsync --delete`;
7. propietario/grupo `web5:client2`;
8. smoke test WP-CLI;
9. comprobación de plugin y versión.

WordPress:

```text
/var/www/clients/client2/web5/web
```

Plugin:

```text
/var/www/clients/client2/web5/web/wp-content/plugins/flacso-uruguay-plugin
```

Secrets requeridos: `EC2_HOST`, `EC2_USER`, `EC2_SSH_KEY`.

## Documentación

- `API.md`: endpoints REST.
- `CHANGELOG.md`: historial funcional.
- `docs/`: notas operativas y arquitectura.
- `modules/*/README.md`: documentación específica cuando exista.
