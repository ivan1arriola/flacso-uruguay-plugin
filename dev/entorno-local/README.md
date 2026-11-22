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
docker compose run --rm cli rewrite structure '/%postname%/' --hard
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
