# FLACSO Uruguay Plugin

Plugin de WordPress que reúne las funcionalidades propias del sitio institucional de FLACSO Uruguay.

## Principios de arquitectura

El plugin se organiza por **dominios de FLACSO**, no por mecanismos técnicos ni por nombres históricos.

- **Oferta Académica** es el dominio de Maestrías, Especializaciones, Diplomas y Diplomados.
- **Seminario** es una unidad académica propia: puede ofrecerse individualmente y también integrar otras ofertas académicas.
- **Main Page** compone la portada, pero no es dueño de los datos de Eventos, Seminarios, Oferta Académica o Mailing.
- **Docentes**, **Eventos**, **Convenios**, **Formularios**, **Preinscripción**, etc. conservan sus responsabilidades específicas.
- Los módulos legados `posgrados` y `shortcodes` se retiraron. El proxy de PDFs vive en `oferta-academica`.

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

Los módulos se cargan en orden fijo desde `FLACSO_Uruguay_Plugin::load_modules()` en `flacso-uruguay.php`, mediante `FLACSO_Uruguay_Loader::load_module()`, que incluye el `init.php` de cada carpeta de `modules/`. Para agregar un módulo hay que sumarlo a esa lista, respetando el orden de dependencias. Los archivos imprescindibles deben cargarse con `flacso_require()`.

## Portada

`main-page` administra el contenido editorial del hero y de los bloques institucionales, el orden, la visibilidad, la configuración global y el contrato REST del editor. La composición visual de la portada vive en el tema.

## Estructura

```text
flacso-uruguay-plugin/
├── flacso-uruguay.php             # bootstrap mínimo
├── includes/
│   └── core/
│       ├── loader.php
│       └── requires.php
├── modules/
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
│   └── main-page/
├── tests/
├── API.md
└── CHANGELOG.md
```

## Comunicaciones

Mautic es el único canal operativo de comunicaciones. WordPress guarda la
consulta, sincroniza el contacto y lo incorpora a la campaña configurada; los
correos, seguimientos y automatizaciones se definen en Mautic. Las opciones y
registros históricos de proveedores anteriores se preservan sólo para consulta
de auditoría y no se usan para nuevos envíos.

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

El servidor no ejecuta `git pull` dentro de WordPress.

### GitHub Secrets

El workflow requiere:

- `EC2_HOST`;
- `EC2_USER`;
- `EC2_SSH_KEY`.

## Workflows

- `deploy-plugin.yml`: mecanismo oficial de despliegue a producción.
- `encoding-check.yml`: valida UTF-8, BOM y patrones de mojibake.
- `release-auto-update.yml`: publica un release y su ZIP con la misma versión derivada del commit; no despliega archivos en WordPress.

## Estructura principal

```text
flacso-uruguay-plugin/
├── flacso-uruguay.php
├── includes/
│   └── core/
├── modules/
│   ├── oferta-academica/
│   ├── seminarios/
│   ├── formularios/
│   ├── shortcodes/
│   ├── docentes/
│   └── eventos/
├── tests/
├── API.md
└── CHANGELOG.md
```

## Documentación

- `API.md`: endpoints REST.
- `CHANGELOG.md`: historial funcional.
- [**Guía visual del modelo académico**](docs/guia-visual-modelo-academico.md): diagramas, fichas de campos, relaciones y ejemplos para usuarios no técnicos.
- `docs/modelo-academico-final.md`: modelo final, campos, relaciones e invariantes.
- `docs/`: notas operativas específicas.
- `modules/*/README.md`: documentación de módulos cuando exista.

## Dominio académico

`OfertaAcademica` y `Seminario` son entidades distintas. Las ofertas admiten
`doctorado`, `maestria`, `especializacion`, `diplomado` y `diploma`; su entidad
temporal es `Cohorte`. La entidad temporal de `Seminario` es
`EdicionSeminario`. Una cohorte recibe un número entero y su etiqueta se deriva
siempre como `Cohorte {ROMANO}`.

Las preinscripciones de ambos tipos se realizan exclusivamente en
`preinscripciones.flacso.edu.uy`. El plugin no contiene migradores del esquema
anterior: los datos se exportan, transforman y validan localmente antes de
cargarlos mediante la API final.

Véase [Modelo académico final](docs/modelo-academico-final.md).

## Panel FLACSO

El menú administrativo `FLACSO` reúne Programas, Ofertas, Cohortes,
Seminarios, Ediciones y Tablas de precios. Su resumen presenta los dos
recorridos del modelo, conteos del catálogo, próximos comienzos, accesos al
Editor y a Preinscripciones, y alertas de datos esenciales incompletos.
