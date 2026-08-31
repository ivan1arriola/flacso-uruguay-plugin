# FLACSO Uruguay Plugin

Plugin de WordPress que reúne las funcionalidades propias del sitio institucional de FLACSO Uruguay.

Incluye, entre otros módulos:

- oferta académica;
- seminarios;
- preinscripciones;
- formularios de consulta;
- charlas abiertas;
- docentes y eventos;
- shortcodes y bloques de Gutenberg.

## Requisitos

- WordPress 6.0 o posterior;
- PHP 7.4 o posterior;
- WP-CLI para las validaciones posteriores al despliegue.

El archivo principal del plugin es `flacso-uruguay.php` y el slug de instalación es `flacso-uruguay-plugin`.

## Versionado automático

La versión desplegada se calcula automáticamente para cada commit de `main` con el formato:

```text
<versión base>.<cantidad de commits>
```

Por ejemplo, si `flacso-uruguay.php` declara la versión base `6.9.15` y el commit es el número 412 del historial, la versión efectiva será `6.9.15.412`.

La versión se deriva del historial Git del commit, por lo que el workflow de despliegue y el workflow de release producen exactamente la misma versión para un mismo SHA. Durante el proceso se actualizan en la copia de distribución:

- la cabecera `Version:` del plugin;
- la constante `FLACSO_URUGUAY_VERSION`.

No se crean commits automáticos para modificar la versión base. Esta solo debe cambiarse manualmente cuando corresponda iniciar una nueva línea de versión.

## Desarrollo local

No se utiliza `./build`, `package-plugin.sh` ni otro script local para preparar entregas.

Antes de publicar cambios se recomienda ejecutar:

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

El hook opcional de `.githooks/pre-commit` ejecuta únicamente el control de encoding. Se habilita con:

```bash
git config core.hooksPath .githooks
```

## Despliegue a producción

Cada push a `main` activa `.github/workflows/deploy-plugin.yml`.

El flujo oficial es:

1. checkout del SHA que activó el workflow;
2. cálculo de la versión efectiva;
3. controles de encoding, archivos requeridos y sintaxis PHP;
4. creación y publicación del ZIP como artifact;
5. copia por SSH a un staging exclusivo bajo `/tmp`;
6. sincronización con `rsync --delete` sobre la carpeta del plugin;
7. asignación de propietario y grupo `web5:client2`;
8. smoke test de WordPress con WP-CLI como usuario `web5`;
9. comprobación del plugin activo y de la versión desplegada.

WordPress está instalado en:

```text
/var/www/clients/client2/web5/web
```

El plugin se despliega exclusivamente en:

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
