import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/sections/instagram.php"
with open(file_path, "r") as f:
    content = f.read()

# Replace $item['media_url'] with $item['thumbnail_url'] in the inline style for background-image
content = re.sub(
    r"\$item\['media_url'\]",
    r"$item['thumbnail_url']",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched instagram.php successfully")
