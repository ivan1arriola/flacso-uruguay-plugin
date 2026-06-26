import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/sections/landing-page.php"
with open(file_path, "r") as f:
    content = f.read()

content = re.sub(
    r"(\[\s*'key'\s*=>\s*'instagram',\s*'function'\s*=>\s*'flacso_section_instagram_render',\s*\],)",
    r"\1\n            [\n                'key' => 'reels',\n                'function' => 'flacso_section_reels_render',\n            ],",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched landing page successfully")
