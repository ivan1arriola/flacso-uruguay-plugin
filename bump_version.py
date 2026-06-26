import re
from datetime import datetime

# 1. Bump version in flacso-uruguay.php
file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/flacso-uruguay.php"
with open(file_path, "r") as f:
    content = f.read()

# Replace Version: 6.2.0 with 6.3.0
content = re.sub(r'Version: \d+\.\d+\.\d+', 'Version: 6.3.0', content)
# Replace define('FLACSO_URUGUAY_VERSION', '6.2.0');
content = re.sub(r"define\('FLACSO_URUGUAY_VERSION',\s*'[^']+'\);", "define('FLACSO_URUGUAY_VERSION', '6.3.0');", content)

with open(file_path, "w") as f:
    f.write(content)

# 2. Add entry to CHANGELOG.md
changelog_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/CHANGELOG.md"
with open(changelog_path, "r") as f:
    changelog_content = f.read()

# Generate Spanish date (e.g. 26 de junio de 2026)
months = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"]
now = datetime.now()
date_str = f"{now.day} de {months[now.month - 1]} de {now.year}"

new_entry = f"""## Version 6.3.0 - {date_str}

### Resumen
- Integración de la API de Instagram para mostrar un feed dinámico en la página principal.
- Nuevo panel de configuración en el Gestor FLACSO para ingresar el Token de Acceso.
- Caché de 2 horas en la API para optimizar rendimiento y evitar bloqueos por rate-limiting.
- Diseño visual responsivo con cuadrícula e interacciones hover para las tarjetas del feed.

### Cambios detallados
- `modules/main-page/includes/class-flacso-main-page-unified-settings.php`: Nueva pestaña y campos para Instagram.
- `modules/main-page/includes/class-flacso-main-page-settings.php`: Soporte para nuevas configuraciones de Instagram y valores por defecto.
- `modules/main-page/includes/class-flacso-instagram-api.php`: Clase encargada de conectar con la Graph API y Basic Display API, con sistema de caché en transients.
- `modules/main-page/sections/instagram.php`: Renderizado dinámico del feed con fallback a la tarjeta estática.
- `modules/main-page/assets/css/flacso-main-page.css`: Estilos visuales para `.flacso-instagram-api-feed`.
- `flacso-uruguay.php`: Versión actualizada a 6.3.0.

---

"""

# Insert at the beginning of the file, assuming it starts with ## Version
changelog_content = re.sub(r'^(## Version)', new_entry + r'\1', changelog_content, count=1)

with open(changelog_path, "w") as f:
    f.write(changelog_content)

print("Version bumped to 6.3.0 and CHANGELOG.md updated.")
