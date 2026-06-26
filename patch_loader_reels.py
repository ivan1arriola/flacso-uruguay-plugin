import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-main-page-loader.php"
with open(file_path, "r") as f:
    content = f.read()

content = re.sub(
    r"(require_once FLACSO_MAIN_PAGE_MODULE_PATH \. 'sections/instagram\.php';)",
    r"\1\n        require_once FLACSO_MAIN_PAGE_MODULE_PATH . 'sections/reels.php';",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched loader successfully")
