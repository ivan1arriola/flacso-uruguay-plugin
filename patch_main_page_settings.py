import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-main-page-settings.php"
with open(file_path, "r") as f:
    content = f.read()

# Add 'reels' after 'instagram' in SECTION_KEYS and HOMEPAGE_SECTION_KEYS
content = re.sub(r"('instagram',)", r"\1\n        'reels',", content)

with open(file_path, "w") as f:
    f.write(content)

print("Patched main page settings successfully")
