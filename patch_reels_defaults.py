import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-main-page-settings.php"
with open(file_path, "r") as f:
    content = f.read()

content = re.sub(
    r"('instagram' => \[\n\s*'title' => 'Seguinos en Instagram',\n\s*'description' => '.*?',\n\s*'profile_url' => '.*?',\n\s*'cta_label' => '.*?',\n\s*'access_token' => '',\n\s*'api_type' => 'basic',\n\s*'count' => 6,\n\s*\],)",
    r"\1\n            'reels' => [\n                'title' => 'Reels Destacados',\n            ],",
    content
)

content = re.sub(
    r"('instagram' => true,)",
    r"\1\n            'reels' => true,",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched reels defaults successfully")
