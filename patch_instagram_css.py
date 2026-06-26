import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/assets/css/flacso-main-page.css"
with open(file_path, "r") as f:
    content = f.read()

# Replace background-size: cover; with background-size: contain; background-repeat: no-repeat;
content = re.sub(
    r'\.flacso-ig-feed-image \{[^}]*background-size:\s*cover;[^}]*\}',
    '.flacso-ig-feed-image {\n    width: 100%;\n    height: 100%;\n    background-size: contain;\n    background-repeat: no-repeat;\n    background-position: center;\n    position: relative;\n}',
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched css successfully")
