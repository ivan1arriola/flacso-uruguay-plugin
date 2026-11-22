# Entorno local de pruebas (WordPress + Kadence + tema hijo + plugin)

Sirve para probar cabecera, pie, foco con teclado y plantillas sin tocar producción. Usa datos ficticios.
No usa `wp-env`: en Windows con un nombre de usuario con tilde (por ejemplo `Iván`) su imagen de WP-CLI no arranca.

## Requisitos
Docker Desktop, Node 20+ y Google Chrome.

## Levantarlo
Desde esta carpeta, con los dos repos clonados (idealmente en worktrees de `main`):

```sh
export FLACSO_THEME_DIR="/ruta/a/flacso-theme-kadence"
export FLACSO_PLUGIN_DIR="/ruta/a/flacso-uruguay-plugin"

# Tema padre Kadence (una sola vez)
python -c "import urllib.request,zipfile,io;zipfile.ZipFile(io.BytesIO(urllib.request.urlopen('https://downloads.wordpress.org/theme/kadence.latest-stable.zip').read())).extractall('themes')"

docker compose up -d db wordpress
docker compose run --rm cli core install --url=http://localhost:8888 --title="FLACSO local"   --admin_user=admin --admin_password=admin --admin_email=local@example.test --skip-email
docker compose run --rm cli theme activate flacso-theme-kadence
docker compose run --rm cli plugin activate flacso-uruguay-plugin
docker compose cp seed.php wordpress:/var/www/html/seed.php
docker compose run --rm -T cli eval-file seed.php
MSYS_NO_PATHCONV=1 docker compose run --rm cli rewrite structure '/%postname%/' --hard   # en Git Bash, sin MSYS_NO_PATHCONV la ruta se convierte en C:/Program Files/Git/...
```

Sitio en http://localhost:8888 (usuario `admin`, clave `admin`, solo local).

## Capturas y comprobaciones
```sh
mkdir capturas && cd capturas && npm init -y >/dev/null && npm install --no-save --no-package-lock playwright@1.49.1
node ../baseline.mjs corrida-AAAA-MM-DD
```
Genera capturas a 320, 375, 768, 1024 y 1280 px de portada, Formación, ficha, Carta y contacto, un `informe.json`
(estado HTTP, desborde horizontal, cantidad de `#inner-wrap`, etiquetas robots) y la prueba de foco del buscador con teclado.
Como `baseline.mjs` está fuera de `capturas/`, necesita `playwright` resoluble: correrlo desde `capturas/` como arriba.

## Apagar y borrar
```sh
docker compose down        # conserva los datos
docker compose down -v     # borra la base y los archivos de WordPress
```

## Límites
- No replica Mautic, Postgres, Rank Math ni la caché de producción.
- El tema padre Kadence usa su paleta por defecto: los colores de FLACSO (palette1 a 9) se configuran en producción.

## Arnés de regresión visual y de uniformidad (`regresion.mjs`, `uniformidad.py`)

Sirve para refactorizar el CSS sin romper nada: saca capturas de **todas las familias de plantilla** a 375 y 1280 px y las compara píxel a píxel con una línea base, y mide los estilos calculados en el navegador (ancho del contenedor, familia y tamaño del h1, botón primario, enlaces, cantidad de `<main>`).

```sh
npm install                       # una vez (playwright, pixelmatch, pngjs)
docker compose cp seed.php wordpress:/var/www/html/seed.php
docker compose run --rm -T cli eval-file seed.php   # datos ficticios de cada familia + flacso-rutas.json

node regresion.mjs --update       # guarda la línea base en capturas/linea-base (antes de tocar CSS)
node regresion.mjs                # compara; sale con 1 si algo supera UMBRAL (0,05 % de píxeles por defecto)
node regresion.mjs --solo carta,ficha-oferta
node regresion.mjs --inyectar "h1{color:red}"   # autoprueba: debe marcar diferencias
```

Cada corrida deja `capturas/corrida/informe.json` (estado HTTP, métricas por página y porcentaje de diferencia) y una imagen `-diff.png` por cada página que cambió. La carpeta `capturas/` no se versiona.

**Medida de avance del sistema de diseño** (lee las reglas completas de las 25 hojas del tema):

```sh
python uniformidad.py ../../../flacso-theme-kadence
```

Cada migración de una familia debe mejorar estos conteos (colores fijos frente a `var(--global-palette*)`, radios, sombras, anchos de contenedor, breakpoints, `!important`).

El entorno local es lento con el volumen montado desde Windows: la primera carga de cada página puede tardar minutos.

**Límites conocidos de los datos ficticios:** la ficha individual de Convenio devuelve 404 con datos mínimos (no se incluye en las rutas); `/programas/` devuelve HTTP 500 mientras exista un programa sin coordinación (defecto real del tema, ver el plan maestro).
