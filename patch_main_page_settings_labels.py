import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-main-page-settings.php"
with open(file_path, "r") as f:
    content = f.read()

content = re.sub(
    r"('instagram' => __\('Instagram', 'flacso-main-page'\),)",
    r"\1\n            'reels' => __('Reels', 'flacso-main-page'),",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched main page settings labels successfully")
