import re

file_path = "/home/ivan/repositorios/Plugin y Editor FLACSO/flacso-uruguay-plugin/modules/main-page/includes/class-flacso-instagram-api.php"
with open(file_path, "r") as f:
    content = f.read()

replacement = """            $feed[] = [
                'id' => $item['id'],
                'caption' => $item['caption'] ?? '',
                'media_type' => $item['media_type'],
                'media_url' => $item['media_url'],
                'thumbnail_url' => $item['thumbnail_url'] ?? $item['media_url'],
                'permalink' => $item['permalink'],
                'timestamp' => $item['timestamp'],
            ];"""

content = re.sub(
    r"\$feed\[\] = \[\n\s*'id' => \$item\['id'\],\n\s*'caption' => \$item\['caption'\] \?\? '',\n\s*'media_type' => \$item\['media_type'\],\n\s*'media_url' => \$item\['media_type'\] === 'VIDEO' \? \(\$item\['thumbnail_url'\] \?\? \$item\['media_url'\]\) : \$item\['media_url'\],\n\s*'permalink' => \$item\['permalink'\],\n\s*'timestamp' => \$item\['timestamp'\],\n\s*\];",
    replacement,
    content
)

with open(file_path, "w") as f:
    f.write(content)

print("Patched API successfully")
