import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/sections/reels.php"
with open(file_path, "r") as f:
    content = f.read()

content = re.sub(
    r"\$title = \(string\) apply_filters\('flacso_main_page_reels_title', 'Reels Destacados'\);",
    r"$settings = class_exists('Flacso_Main_Page_Settings') ? Flacso_Main_Page_Settings::get_section('reels') : [];\n    $title = (string) apply_filters('flacso_main_page_reels_title', $settings['title'] ?? 'Reels Destacados');",
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched reels.php title logic successfully")
